regApp.controller('orderSummary', function($scope, $http, $q, dataSvc, erSvc) {
	// erSvc.loadingDialog();
	var regsRetrieved = $q.defer();
	var xtrasRetrieved = $q.defer();
	var ordersRetrieved = $q.defer();
	let eventid = erSessionData.curEvent.id;
	$scope.registrations = {};
	$scope.totals = {"price":0,"serviceFee":0,"payments":0,"balance":0,"extras":{}};
	dataSvc.getRegistrations(eventid).then(function(res){
		angular.forEach(res,function(reg){
			reg.searchString = erSvc.getObjectSearchString(reg);
			reg.include = true;
			reg.price = parseFloat(reg.price);
			reg.serviceFee = parseFloat(reg.serviceFee);
			reg.payments = parseFloat(reg.payments);
			reg.balance = parseFloat(reg.balance);
			if(reg.deleted != '1') $scope.registrations[reg.id] = reg;
		});
		regsRetrieved.resolve();
	});

	dataSvc.getObject({'query':'registrationExtras','eventid':eventid}).then(function(resp){
		$scope.extras = resp;
		angular.forEach($scope.extras,x => $scope.totals.extras[Number(x.id)] = 0);
		xtrasRetrieved.resolve();
	});

	let orders;
	dataSvc.getArray({'query':'regExtraOrdersByEvent','eventid':eventid}).then(function(resp){
		orders = resp;
		ordersRetrieved.resolve();
	});

	$q.all([regsRetrieved.promise,xtrasRetrieved.promise,ordersRetrieved.promise]).then(function(){
		let orderTemplate = {};
		angular.forEach($scope.extras,x => orderTemplate[x.id] = 0);
		angular.forEach($scope.registrations,reg => reg.orders = {...orderTemplate});
		orders.forEach(function(ord){
			ord.quantity = Number(ord.quantity);
			ord.registrationid = Number(ord.registrationid);
			ord.extraId = Number(ord.extraId);
			let reg = $scope.registrations[ord.registrationid];
			if(reg && reg.orders.hasOwnProperty([ord.extraId])){
				reg.orders[ord.extraId] += Number(ord.quantity);
			} 
		});
		$scope.updateTotals();
	});

	$scope.quickSearchValue = '';
	$scope.quickSearch = function(){
		var compareValue = $scope.quickSearchValue.toLowerCase();
		angular.forEach($scope.registrations,function(reg){
			reg.include = !$scope.quickSearchValue || reg.searchString.includes(compareValue);
		});
		$scope.updateTotals();
	};

	$scope.updateTotals = function(){
		$scope.totals.price = 0;
		$scope.totals.serviceFee = 0;
		$scope.totals.payments = 0;
		$scope.totals.balance = 0;
		for(id in $scope.totals.extras){ $scope.totals.extras[id] = 0; }
		angular.forEach($scope.registrations,function(reg){
			if(!reg.include) return;
			$scope.totals.price += reg.price;
			$scope.totals.serviceFee += reg.serviceFee;
			$scope.totals.payments += reg.payments;
			$scope.totals.balance += reg.balance;
			for(key in reg.orders){	$scope.totals.extras[key] += reg.orders[key]; }
		});
	};
});// End Controller