regApp.controller('eventUsers', function($scope, $http, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Event Staff")').addClass('active');
	}, 100);
	$scope.userid = erSessionData.userid;
	$scope.accountid = erSessionData.useraccount;
	$scope.eventid = erSessionData.curEvent.id;

	$scope.requests = [];
	dataSvc.getEventDataFromId($scope.eventid).then((resp) => $scope.eventData = resp);

	dataSvc.getArray({"query":"eventUsers","eventid":$scope.eventid}).then(function(resp){
		$scope.users = resp;
		angular.forEach($scope.users,function(user){
			if(user.security_groups) user.security_groups = user.security_groups.split(',');
			else user.security_groups = [];
			user.include = !!user.user_eventid;
			user.request_participation = user.request_participation == 1 ? true : false;
			if(user.roles == '' && user.request_denied == 0  && user.request_participation){
				$scope.requests.push(user);
			}
		});
	});

	dataSvc.getTableRecords('security_groups', 'accountid = ' + $scope.accountid, true)
	.then((res) => $scope.groups = res);

	$scope.replytoemails = [];
	dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].email);
	});

	dataSvc.getArray({'query':'eventDataRaw','eventid':$scope.eventid})
	.then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].replytoemail);
	});

	dataSvc.getArray({'query':'accountInfo'}).then(function(res){
		$scope.acceptMessge = res[0].request_accept_email;
		$scope.denyMessage = res[0].request_deny_email;
	});

	dataSvc.getArray({"query":"registrationTypes","eventid":$scope.eventid})
	.then((resp) => $scope.regTypes = resp);

	$scope.addUser = function(which){
		if(which == 'all') angular.forEach($scope.users,(user) => user.include = true);
		else $scope.selectedUser.include = true;
	};

	$scope.removeUser = function(user){
		user.include = false;
		user.remove = true;
	};

	//get array of users for dropdown for users to add
	$scope.availableUsers = () => ($scope.users || []).filter((u) => !u.include && !Number(u.sponsorid));

	$scope.saveChanges = function(){
		erSvc.loadingDialog();
		let usersToUpdate = 0;
		let usersUpdated = 0;
		angular.forEach($scope.users,function(user){
			if(!user.include && !user.remove) return;
			usersToUpdate++;
			if(user.remove){
				if(user.user_eventid){
					dataSvc.deleteRecord({"table":"user_event","id":user.user_eventid},$scope.eventid).then(function(res){
						if(++usersUpdated == usersToUpdate) erSvc.closeLoading();
					});
				}else{
					if(++usersUpdated == usersToUpdate) erSvc.closeLoading();
				}
			}else{
				user.eventid = $scope.eventid;
				let user_event_data = {"userid":user.userid,"eventid":$scope.eventid,"id":user.user_eventid};
				dataSvc.createOrUpdateRecord({"table":"user_event","record":user_event_data},$scope.eventid)
				.then(function(res){
					user.user_eventid = user.user_eventid || res;
					if(++usersUpdated == usersToUpdate) erSvc.closeLoading();
				});
			}
		});
	};

	$scope.registerUser = function(user){
		erSvc.loadingDialog();
		let registration = angular.copy(user);
		registration.id = '';
		if(user.nonStaffReg){
			let updateRec = {"attendeeid":"0","userid":user.userid,"id":user.nonStaffReg};
			dataSvc.createOrUpdateRecord({"table":"registrations","record":updateRec}).then(function(res){
				erSvc.closeLoading();
				user.registrationid = user.nonStaffReg;
			});
			return;
		}
		dataSvc.createOrUpdateRegistration(registration, $scope.eventid).then(function(res){
			user.registered = "1";
			//send email confirmation
			let data = 	{
				"confirmation":res.confirmation,
				"first_name":user.first_name,
				"last_name":user.last_name,
				"email":user.email,
				"eventid":$scope.eventData.id
			};
			$.post('/save_registration.php', data).then(function(resp){
				$scope.saveChanges();
				$scope.$apply(() => user.registrationid = 1);
				erSvc.closeLoading();
			});
		});
	};

	/************** Email Functions **************/

	//confirm user request to attend
	$scope.confirm = function(request){
		$scope.emailHeader = 'Send Request Acceptance';
		$scope.emailSubject = $scope.eventData.name + ' Request';
		$scope.emailBody = $scope.acceptMessge;
		$scope.emailTo = request.email;
		$scope.acceptedUser = request;
		$('#emailDialog').show(500);
	};

	//deny user request to attend
	$scope.deny = function(request){
		//if the user has registered, offer an opportunity to cancel the registration
		if(request.registrationid){
			let msg = "This user has been registered for the conference. <br/> \
			Would you like to cancel the registration?";

			erSvc.easyRegConfirm({"text":msg,"title":"User Registered"}).then(function(resp){
				if(resp){
					rec = {"id":request.registrationid,"deleted":"1"};
					dataSvc.createOrUpdateRecord({"table":"registrations","record":rec});
				}
			});
		}

		$scope.emailHeader = 'Send Request Denial';
		$scope.emailSubject = $scope.eventData.name + ' Request';
		$scope.emailBody = $scope.denyMessage;
		$scope.emailTo = request.email;
		$scope.deniedUser = request;
		$('#emailDialog').show(500);
	};

	$scope.emailRecipients = 'all';

	$scope.startGroupEmail = function(){
		$scope.emailHeader = 'Email Event Staff';
		$scope.emailTo = '';
		$scope.emailBody = '';
		$scope.emailSubject = '';
		$scope.deniedUser = 0;
		$scope.acceptedUser = 0;
		$('#emailDialog').show(500);
	};

	$scope.setIncludeEmail = function(include){
		angular.forEach($scope.users,(user) => user.includeInEmail = include);
	};

	$scope.sendEmail = function(){
		erSvc.loadingDialog();
		let recipientList = [];
		if($scope.emailTo){
			recipientList.push($scope.emailTo);
		}else{
			angular.forEach($scope.users,function(user){
				if(user.include && (user.includeInEmail || $scope.emailRecipients == 'all'))
					recipientList.push(user.email);
			});
		}

		if(!$scope.deniedUser && !$scope.acceptedUser){
			let emailSentData = {
				"accountid": $scope.accountid,
				"author":erSessionData.userData.first_name + ' ' + erSessionData.userData.last_name,
				"subject": $scope.emailSubject,
				"eventid":$scope.eventid,
				"message": $scope.emailBody,
				"recipients":recipientList.toString()
			};

			dataSvc.createOrUpdateRecord({
				"table":"emails_sent",
				"record":emailSentData
			});
		}

		var body = $scope.emailBody.replace(/(?:\r\n|\r|\n)/g, '<br>');
		erSvc.sendEmail(recipientList.toString(), $scope.emailSubject, body, $scope.replytoemail)
		.then(function(){
			$scope.closeEmailDialog();
		});

		if(recipientList.length == 0) $scope.closeEmailDialog();

		//if denial sent, remove request
		if($scope.deniedUser){
			$scope.requests.splice($scope.requests.indexOf($scope.deniedUser),1);
			var updateRec = {"id":$scope.deniedUser.user_eventid,"request_denied":"1"};
			dataSvc.createOrUpdateRecord({"table":"user_event","record":updateRec});
			$scope.deniedUser = 0;
		}
		//if acceptance sent, add request to users
		if($scope.acceptedUser){
			var request = $scope.acceptedUser;
			request.include = true;
			request.presenter = request.request_participation;
			$scope.requests.splice($scope.requests.indexOf(request),1);
			$scope.saveChanges();
		}
	}; //End sendEmail()

	$scope.closeEmailDialog = function(){
		$(".dialogRight").hide(500);
		erSvc.closeLoading();
	};
});//End Controller