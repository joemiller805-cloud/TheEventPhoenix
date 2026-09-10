regApp.controller('registrationMessages', function($scope, $http, $timeout, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Registration Messages")').addClass('active');
	}, 100);
	$scope.userid = erSessionData.userid;
	$scope.accountid = erSessionData.useraccount;
	$scope.eventid = erSessionData.curEvent.id;

	$scope.empty = false;

	let msgFilter = `eventid = ${$scope.eventid}`;
	dataSvc.getTableRecords('registration_messages', msgFilter, true, $scope.eventid).then(function(res){
		$scope.messages = res;
		checkForEmpty();
	});

	$scope.createMessage = function(){
		$('form[name="newMessgeForm"]').addClass('submitted');
		if(!$scope.newMessgeForm.$valid) return;
		let msg = {
			"from_email":$scope.newMessgeFrom,
			"subject":$scope.newMessageSubject,
			"message":$scope.newMessageBody,
			"eventid":$scope.eventid
		};
		dataSvc.createOrUpdateRecord({"table":"registration_messages","record":msg},$scope.eventid).then(function(res){
			msg.id = res;
			$scope.messages[res] = msg;
			$scope.empty = false;
			$scope.newMessgeFrom = '';
			$scope.newMessageSubject = '';
			$scope.newMessageBody = '';
			$scope.closeDialog();
			$('form[name="newMessgeForm"]').removeClass('submitted');
			$scope.$applyAsync();
		});
	};

	$scope.newMsg = () =>  $('#newMsgDialog').show(500);

	$scope.closeDialog = () => $('#newMsgDialog').hide(500);

	$scope.upateMsg = function(msg){
		erSvc.loadingDialog("Saving");
		if(!msg.from_email || !msg.message || !msg.subject){
			let msg = "Please check that all values are present and valid."
			erSvc.easyRegAlert({"text":msg,"title":"Error"});
			erSvc.closeLoading();
			return;
		}
		dataSvc.createOrUpdateRecord({"table":"registration_messages","record":msg},$scope.eventid).then(function(res){
			erSvc.closeLoading();
			erSvc.easyRegAlert({"text":"Message Updated","title":"Success"},true);
			checkForEmpty();
		});
	};

	$scope.deleteMsg = function(msg){
		erSvc.easyRegConfirm({"text":"Delete message","title":"Confirm Delete"},"Confirm","Cancel").then(function(res){
			if(res){
				dataSvc.deleteRecord({"table":"registration_messages","id":msg.id},$scope.eventid).then(function(){
					delete $scope.messages[msg.id];
					erSvc.easyRegAlert({"text":"Message Deleted","title":"Success"},true);
					checkForEmpty();
				});
			}
		});
	};

	function checkForEmpty(){
		$scope.empty = Object.keys($scope.messages).length == 0;
	}

});//end controller