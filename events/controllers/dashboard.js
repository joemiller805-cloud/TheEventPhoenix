regApp.controller('dashboardCtrl', function($scope, $http, $rootScope, $q, $routeParams, $location, dataSvc, erSvc) {
	let eventid = $routeParams.id || erSessionData.curEvent.id;
	//this is the landing page for non-master users when they land in an event
	//need to check access before collecting data, may be redirected
	erSvc.checkAccess().then(function(res){
		if(!res) return;
		$rootScope.curEventName = '';
		dataSvc.getEventDataFromId(eventid).then(function(response){
			$scope.eventData = response;
			$rootScope.curEventName = response.name;
			if($scope.eventData.startdate.indexOf('0000') >= 0) $scope.datesTbd = true;
			$scope.eventName = $scope.eventData.name;
			$scope.updateSelectedLink();
		});
		let evt = erSessionData.curEvent;
		if(!evt || (evt && evt.id != eventid)) erSvc.setSelectedEvent(eventid);
	});

	let registrationsRetrieved = $q.defer();
	let vendorOrdersRetrieved = $q.defer();
	let regtypesRetrieved = $q.defer();

	dataSvc.getArray({'query':'daysUntilEvent','eventid':eventid}).then(resp => $scope.daysUntilEvt = Number(resp[0].days) );
	dataSvc.getRegistrations(eventid).then(regs => registrationsRetrieved.resolve(regs));
	dataSvc.getObject({"query":"registrationTypes","eventid":eventid}).then(types => regtypesRetrieved.resolve(types));
	dataSvc.getArray({"query":"eventVendorOrderSummary","eventid":eventid}).then(orders => vendorOrdersRetrieved.resolve(orders));

	//Eric get target registrations for each reg type and then show pct.

	$scope.attendeeRegistrations = [];
	$scope.vendorRegistrations = {};
	$q.all([registrationsRetrieved.promise,regtypesRetrieved.promise,vendorOrdersRetrieved.promise]).then(function(){
		let vendorOrders = vendorOrdersRetrieved.promise.$$state.value;
		$scope.totalVendorSignups = 0;
		$scope.totalVendorCharged = 0;
		$scope.totalVendorPaid = 0;
		$scope.totalVendorDue = 0;
		vendorOrders.forEach(function(order){
			order.paid = Number(order.paid);
			order.price = Number(order.price);
			order.total = Number(order.total);
			if(!$scope.vendorRegistrations[order.name]) $scope.vendorRegistrations[order.name] = {
				"name":order.name,
				"signups":0,
				"charged":0,
				"paid":0,
				"due":0,
			};
			let due = order.total - order.paid;
			if(due > order.price) due = order.price;
			$scope.totalVendorSignups++;
			$scope.totalVendorCharged += order.price;
			$scope.totalVendorPaid +=  order.price - due;
			$scope.totalVendorDue += due;
			$scope.vendorRegistrations[order.name].signups++;
			$scope.vendorRegistrations[order.name].charged += order.price;
			$scope.vendorRegistrations[order.name].paid += order.price - due;
			$scope.vendorRegistrations[order.name].due += due;
		});
		$scope.registrationTypes = regtypesRetrieved.promise.$$state.value;
		angular.forEach($scope.registrationTypes,function(reg){
			reg.signups = 0;
			reg.paid = 0;
			reg.charged = 0;
			reg.discount = 0;
			reg.due = 0;
			if(reg.vendor_reg != '1') $scope.attendeeRegistrations.push(reg);
		});

		$scope.totalAttendeeSignups = 0;
		$scope.totalAttendeeCharged = 0;
		$scope.totalAttendeePaid = 0;
		$scope.totalAttendeeDiscount = 0;
		$scope.totalAttendeeDue = 0;
		angular.forEach(registrationsRetrieved.promise.$$state.value,function(reg){
			let regType = $scope.registrationTypes[reg.registration_typeid];
			if(!regType || regType.vendor_reg == '1'  || reg.deleted == '1') return;
			regType.signups += 1;
			regType.paid += Number(reg.payments) || 0;
			regType.charged += Number(reg.price) || 0;
			regType.discount += Number(reg.discount) || 0;
			regType.due += Number(reg.balance) || 0;
			$scope.totalAttendeeSignups += 1;
			$scope.totalAttendeeCharged += Number(reg.price) || 0;
			$scope.totalAttendeePaid += Number(reg.payments) || 0;
			$scope.totalAttendeeDiscount += Number(reg.discount) || 0;
			$scope.totalAttendeeDue += Number(reg.balance) || 0;
		});
	});
});//end controller