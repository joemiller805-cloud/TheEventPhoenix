regApp.controller('staffPaymentsRpt', function($scope, $http, $rootScope, $location, $q, $filter, dataSvc, erSvc){
	let accountid = erSessionData.accountid;
	if($location.$$path.indexOf('evt_') >= 0){
		$scope.selectedEvent = erSessionData.curEvent.id;
	}else{
		$scope.selectedEvent = 'upcoming';
		$scope.acct = true;
	}

	$scope.staffFilter = 'all';
	$scope.events = {};
	dataSvc.getArray({'query':'accountEvents'}).then(function(resp){
		resp.forEach((evt) => { if(evt.archived != '1') $scope.events[evt.id] = evt; });
	});

	$scope.getPayments = function(){
		$scope.staff = [];
		let params = {'query':'staff_payments'};
		if(Number($scope.selectedEvent)) params.eventid = $scope.selectedEvent;
		else params.range = $scope.selectedEvent;
		dataSvc.getArray(params).then(function(resp){
			$scope.payments = resp;
			$scope.payments.forEach(function(pymt){
				pymt.lastfirst = pymt.last_name + ', ' + pymt.first_name;
				if(!$scope.staff.includes(pymt.lastfirst)) $scope.staff.push(pymt.lastfirst);
				pymt.searchString = '';
				for(prop in pymt){
					if(pymt[prop]) pymt.searchString += pymt[prop].toLowerCase();
				}
			});
			$scope.filterPayments();
		});
	};

	$scope.getPayments();
	$scope.quickSearch = '';

	$scope.filterPayments = function(){
		$scope.pymtTotal = 0;
		$scope.filteredPayments = [];
		angular.forEach($scope.payments,function(pymt){
			if(meetsFilter(pymt)){
				$scope.filteredPayments.push(pymt);
				$scope.pymtTotal += Number(pymt.amount);
			} 
		});
	};

	function meetsFilter(pymt){
		let searchStr = $scope.quickSearch.toLowerCase();
		let staffMatch =  $scope.staffFilter == pymt.lastfirst || $scope.staffFilter == 'all';
		let searchMatch =  searchStr == '' || pymt.searchString.indexOf(searchStr) >= 0;
		return staffMatch && searchMatch;
	}
});