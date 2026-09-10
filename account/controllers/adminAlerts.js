regApp.controller('adminAlerts', function($scope, $http, $q, dataSvc, erSvc) {
	dataSvc.accountid = erSessionData.accountid;
	$scope.replytoemails = [];
	dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].email);
	});

	dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].email);
	});

	var requestsRetrieved = $q.defer();
	var eventsRetrieved = $q.defer();
	$scope.requests = [];
	dataSvc.getArray({'query':'unanswered_user_requests'}).then(function(requests){
		$scope.requests = requests;
		requestsRetrieved.resolve();
	});
	dataSvc.getObject({'query':'accountEvents'}).then(function(events){
		$scope.events = events;
		angular.forEach($scope.events,function(event){
			event.requests = [];
		});
		eventsRetrieved.resolve();
	});
	$q.all([requestsRetrieved.promise, eventsRetrieved.promise]).then(function(){
		angular.forEach($scope.requests,function(request){
			$scope.events[request.eventid].requests.push(request);
		});
		getRegTypes();
	});

	dataSvc.getArray({'query':'accountInfo'}).then(function(res){
		$scope.acceptMessage = res[0].request_accept_email;
		$scope.denyMessage = res[0].request_deny_email;
		$scope.proposalAcceptMessage = res[0].proposal_accept_email;
		$scope.proposalDenyMessage = res[0].proposal_deny_email;
	});

	$scope.proposals = [];
	dataSvc.getArray({'query':'courseProposals'}).then(function(proposals){
		angular.forEach(proposals,function(proposal){
			if(!proposal.status) $scope.proposals.push(proposal);
		});
	});
	$scope.eventCapacities = [];
	dataSvc.getArray({'query':'event_capacity_check'}).then(function(evts){
		angular.forEach(evts,function(evt){
			if(evt.total >= evt.capacity - 10) $scope.eventCapacities.push(evt);
		});
	});
	$scope.sectionCapacities = [];
	dataSvc.getArray({'query':'section_capacity_check'}).then(function(sections){
		angular.forEach(sections,function(sec){
			if(sec.signups >= sec.capacity - 5) $scope.sectionCapacities.push(sec);
		});
	});

	//deny user request to attend
	$scope.deny = function(request, event){
		$scope.emailDialogHeader = "Deny Event Request"
		$scope.selectedRequest = request;
		$scope.emailTo = request.email;
		$scope.emailSubject = event.name + ' Request';
		$scope.emailBody = $scope.denyMessage;
		$('#emailDialog').show(500);
		request.denied = true;
	};

	//confirm user request to attend
	$scope.confirm = function(request, event){
		$scope.emailDialogHeader = "Accept Event Request"
		$scope.selectedRequest = request;
		$scope.emailTo = request.email;
		$scope.emailSubject = event.name + ' Request';
		$scope.emailBody = $scope.acceptMessage;
		$('#emailDialog').show(500);
	};

	function registerUser(user){
		var registration = angular.copy(user);
		registration.id = '';
		registration.userid = user.id;
		dataSvc.createOrUpdateRegistration(registration, registration.eventid).then(function(res){
			user.registered = "1";
			var data = 	{
				"confirmation":res.confirmation,
				"first_name":user.first_name,
				"last_name":user.last_name,
				"email":user.email,
				"eventid":user.eventid
			};
			$.post('/save_registration.php', data).then(function(resp){
				erSvc.closeLoading();
				user.registered = true;
			});
		});
	};

	$scope.updateProposal = function(proposal, status){
		proposal.status = status;
		dataSvc.respondToCourseProposal(proposal.id, status);
		if(status == 'accepted'){
			var course = {
				"name": proposal.title,
				"abbreviation": proposal.title,
				"description": proposal.description,
				"excludefromschedule": "0",
				"archived": "0",
				"tracks": []
			};
			dataSvc.createCourse(course, erSessionData.accountid).then(function(courseid){
				// update presenter's course able or preferred
				let courseProperty = '';
				let courseValue = '';
				if(proposal.teaching_preference == 'prefer'){
					courseProperty = 'courses_preferred';
					courseValue = proposal.courses_preferred + ',' + courseid;
				}else if(proposal.teaching_preference == 'able'){
					courseProperty = 'courses_able';
					courseValue = proposal.courses_able + ',' + courseid;
				}
				if(courseProperty){
					let userUpdate = {"id":proposal.userid};
					userUpdate[courseProperty] = courseValue;
					dataSvc.createOrUpdateRecord({"table":"users","record":userUpdate});
				}
			});
		}
		//send email
		$scope.selectedRequest = null;
		if(status == 'accepted'){
			$scope.emailDialogHeader = "Accept Course Proposal";
			$scope.emailBody = $scope.proposalAcceptMessage;
		}else{
			$scope.emailDialogHeader = "Deny Course Proposal";
			$scope.emailBody = $scope.proposalDenyMessage;
		}
		$scope.emailSubject = 'Course Proposal - ' + proposal.title;
		$scope.emailTo = proposal.email;
		$('#emailDialog').show(500);
	};

	$scope.sendEmail = function(){
		erSvc.loadingDialog();
		var request = $scope.selectedRequest;
		if(request){
			let reqArray = $scope.events[request.eventid].requests;
			reqArray.splice(reqArray.indexOf(request),1);
			if(request){
				//update denied user_event record
				var requestUpdateData = {"id":request.user_eventid};
				if(request.denied){
					requestUpdateData.request_denied = '1';
					dataSvc.createOrUpdateRecord({"table":"user_event","record":requestUpdateData});
				}else{
					registerUser(request);
				}
			}
		}
		
		var body = $scope.emailBody.replace(/(?:\r\n|\r|\n)/g, '<br>');
		erSvc.sendEmail($scope.emailTo, $scope.emailSubject, body, $scope.replytoemail).then(function(){
			$(".dialogRight").hide(500);
			erSvc.closeLoading();
		});
	};//end sendEmail()

	$scope.cancelEmail = function(){
		if($scope.selectedRequest) $scope.selectedRequest.denied = false;
		$(".dialogRight").hide(500);
	};

	function getRegTypes(){
		angular.forEach($scope.events,function(event){
			if(event.requests.length){
				dataSvc.getObject({'query':'registrationTypes',"eventid":event.id}).then(function(types){
					event.registrationTypes = types;
				});
			}
		});
	}

});