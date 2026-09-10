regApp.controller('payments', function($scope, $http, dataSvc, erSvc) {
	$scope.eventId = erSessionData.curEvent.id;
	//ERIC fix this.  Pass confirmation in another way
	//$scope.confirmation = new URL(location).searchParams.get('confirmation');

	$scope.totalPayments = 0;
	var queryParams = {
		"query":"payments",
		"eventid":$scope.eventId,
		"confirmation":$scope.confirmation
	};
	dataSvc.getArray(queryParams).then(function(response){
		$scope.payments = response;
		angular.forEach($scope.payments,function(payment){
			payment.include = true;
			payment.searchVal = JSON.stringify(payment).toLowerCase();
			$scope.totalPayments += parseFloat(payment.amount);
		});
	});

	$scope.quickSearch = function(){
		var compareValue = $scope.quickSearchValue.toLowerCase();
		angular.forEach($scope.payments,function(pymt){
			pymt.include = pymt.searchVal.indexOf(compareValue) >=0;
		});
	}
});