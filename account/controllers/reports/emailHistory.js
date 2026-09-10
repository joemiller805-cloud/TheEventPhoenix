regApp.controller('emailHistory', function($scope, $http, dataSvc, erSvc) {
	let accountid = erSessionData.accountid;

	dataSvc.getEventsByAccount(true, accountid)
	.then(function(res){
		let events = res;
		dataSvc.getTableRecords('emails_sent', 'accountid = ' + accountid)
		.then(function(res){
			$scope.emails = res;
			$scope.emails.forEach(function(email){
				if(events[email.eventid]) email.eventName = events[email.eventid].name;
				email.displayDate = erSvc.mySqlToLocalDate(email.created_time);
				email.recipients = email.recipients.split(',');
				email.message = email.message.replace(/\n/g,'<br/>');
				email.searchStr = `${email.displayDate}${email.recipients}${email.message}${email.subject}${email.author}${email.eventName}`;
				email.searchStr = email.searchStr.toLowerCase();
			});
			$scope.filteredEmails = angular.copy($scope.emails);
		});
	});

	$scope.showRecipients = function(email){
		$scope.selectedEmail = email;
		$('#recipientDialog').show(500);
	};

	$scope.showContent = function(content){
		$scope.emailContent = content;
		$('#contentDialog').show(500);
	};

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);

	$scope.firstPart = (string, length) => string.substring(0,length);

	$scope.search = function(){
		let val = $scope.searchVal.toLowerCase();
		if($scope.searchVal.length < 3) $scope.filteredEmails = angular.copy($scope.emails);
		else $scope.filteredEmails = $scope.emails.filter((eml) => eml.searchStr.indexOf(val) >= 0);
	}
});// End Controller
