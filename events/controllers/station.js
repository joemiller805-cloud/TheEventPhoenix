regApp.controller('station', function($scope, $http, dataSvc, erSvc) {
	erSvc.loadingDialog();

	$("html").click(function(){
		$("input#search").focus().select();
	});
	$scope.message = '';
	$scope.searchText = '';
	$scope.alphabet = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
	$scope.eventAdministrator = true;
	$scope.userId = erSessionData.userid;
	$scope.accountid = erSessionData.useraccount;
	$scope.eventName = erSessionData.curEvent.name;
	$scope.eventId = erSessionData.curEvent.id;

	$scope.eventRegistrations = {};
	dataSvc.getRegistrations($scope.eventId).then(function(response){
		angular.forEach(response,function(reg){
			$scope.eventRegistrations[reg.confirmation] = reg;
			reg.hasBalance = parseFloat(reg.balance) > 0;
			reg.show = false;
		});
		erSvc.closeLoading();
		$("input#search").focus();
	});

	$scope.search = function($event, letter){
		angular.forEach($scope.eventRegistrations,function(reg){
			reg.show = reg.deleted == '0' && (letter == 'all' || reg.last_name.trim().substring(0,1).toUpperCase() == letter);
		});
		$("ul#letters li").removeClass("active");
		$($event.currentTarget).closest("li").addClass("active");
		$scope.searchText = '';
		$("input#search").focus().select();
	};

	$scope.searchInput = function(){
		var searchVal = $scope.searchText.toUpperCase();
		var matchCount = 0;
		var confirmation;
		angular.forEach($scope.eventRegistrations,function(reg){
			reg.show = false;
			if(reg.last_name.trim().toUpperCase().indexOf(searchVal) == 0 || reg.confirmation.toUpperCase().indexOf(searchVal) == 0){
				reg.show = true;
				confirmation = reg.confirmation;
				matchCount++;
			}
		});
		if(matchCount == 1){
			$scope.selectRegistration(confirmation);
			$scope.searchText = '';
		}
	};

	$scope.selectRegistration = function(confirmation){
		$scope.selectedRegistration = $scope.eventRegistrations[confirmation];
		if(!$scope.selectedRegistration.hasBalance || $scope.hideBalanceAlert){
			$scope.completeCheckin();
		}else{
			$('#checkinDialog').show(500);
		}
	};

	$scope.completeCheckin = function(){
		if($scope.selectedRegistration.checkin){
			var text = $scope.selectedRegistration.first_name + " " + $scope.selectedRegistration.last_name + " has already been checked in.";
			erSvc.easyRegAlert({"text":text,"title":"Already Checked In"});
			$scope.clearSearch();
		}else{
			dataSvc.checkinAttendee($scope.selectedRegistration.id, $scope.userId, $scope.eventId).then(function(resp){
				$scope.message = 'Registrant ' + $scope.selectedRegistration.first_name + ' ' + $scope.selectedRegistration.last_name + ' has been checked in.';
				$scope.selectedRegistration.checkin = 'This Session';
				$scope.selectedRegistration.checkin_user = 'You';
				$scope.closeRightDialog();
				$scope.$applyAsync();
			});
		}
	};

	$scope.clearSearch = function(){
		angular.forEach($scope.eventRegistrations, reg => reg.show = false);
		$scope.searchText = '';
		$("input#search").focus().select();
		$scope.message = '';
	};

	$scope.closeRightDialog = function(){
		$(".dialogRight").hide(500);
	};
});//End Controller