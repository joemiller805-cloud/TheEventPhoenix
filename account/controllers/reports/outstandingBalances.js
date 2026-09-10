regApp.controller('outstandingBalances', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.event = 'any';
	$scope.quickSearchVal = '';
	$scope.events = [];
	dataSvc.getArray({'query':'outstandingBalances'}).then(function(resp){
		$scope.outstandingReg = resp;
		$scope.outstandingReg.forEach(function(reg){
			if($scope.events.indexOf(reg.event) < 0) $scope.events.push(reg.event);
			reg.payments = parseFloat(reg.payments);
			reg.price = parseFloat(reg.price);
			reg.balance = parseFloat(reg.balance);
			reg.address = `${reg.address1} ${reg.address2} ${reg.city}, ${reg.state} ${reg.zip}`;
			reg.first_name = reg.first_name || '';
			reg.last_name = reg.last_name || '';
			reg.business = reg.business || '';
			reg.show = true;
		});
		$('#loadingSpan').remove();
	});

	$scope.search = function(){
		let search = $scope.quickSearchVal.toLowerCase();;
		$scope.outstandingReg.forEach(function(reg){
			let eventMatch = $scope.event == 'any' || $scope.event == reg.event;
			if(!eventMatch){
				reg.show = false;
				return;
			}
			let searchMatch = false;
			if(!search) searchMatch = true;
			else if(reg.first_name.toLowerCase().indexOf(search) >= 0) searchMatch = true;
			else if(reg.last_name.toLowerCase().indexOf(search) >= 0) searchMatch = true;
			else if(reg.business.toLowerCase().indexOf(search) >= 0) searchMatch = true;
			else if(reg.address.toLowerCase().indexOf(search) >= 0) searchMatch = true;
			reg.show = eventMatch && searchMatch;
		});
	};
});// End Controller