regApp.controller('ordersCtrl', function($scope, $http, accountid, dataSvc, erSvc, sponsorDataService) {
	let evtData;
	function refreshVendorData(){
		erSvc.loadingDialog("Fetching Order Info");
		sponsorDataService.getAllEventDetails().then(function(res){
			evtData = res;
			aggregateOrderData();
			$scope.$apply();
			erSvc.closeLoading();
		});
	}

	refreshVendorData();

	$scope.initializeCcInfo();
	
	function aggregateOrderData(){
		angular.forEach(evtData.orders,function(order){
			order.events = {};
			order.items.forEach(function(item){
				let evt = evtData.events[item.eventid];
				if(!evt) return;
				if(!order.events[evt.id]){
					order.events[evt.id] = {
						"name":evt.name,
						"startDate":evt.startdate,
						"vendorTypeOrder":{},
						"extraStaffOrder":{},
						"eventExtraOrders":[]
					}
				}
				if(item.type == 'event vendor') order.events[evt.id].vendorTypeOrder = item;
				else if(item.type == 'event staff') order.events[evt.id].extraStaffOrder = item;
				else if(item.type == 'event extra') order.events[evt.id].eventExtraOrders.push(item);
			});
		});
		$scope.orders = evtData.orders;
		$scope.noOrders = Object.keys($scope.orders).length == 0;
	}

	$scope.selectedOrder;
	$scope.card = {};
	$scope.denyMethodSelect = true;
	$scope.payment_method = 'cc';
	$scope.openPayment = function(order){
		$('#paymentDialog').show(500);
		$scope.card.amount = order.due <= 5000 ? order.due : 5000;
		$scope.selectedOrder = order;
	};

	$scope.submitPayment = function(){
		erSvc.loadingDialog('Processing Payment');
		if($scope.ccProvider == 'magicWrighter'){
			$('#paymentForm').addClass('submitted');
			if(!$scope.paymentForm.$valid) {
				erSvc.closeLoading();
				return;
			}
		}
		$scope.processPayment($scope.card, $scope.card.amount, $scope.selectedOrder.id).then(function(status){
			$scope.paymentError = status.paymentError;
			$scope.authMessage = status.authMessage;
			$scope.paymentMessage = status.paymentMessage;
			$scope.ccConfirmation = status.payment_number;
			erSvc.closeLoading();
		});
	};

	$scope.finishPayment = function(){
		refreshVendorData();
		$scope.closeRightDialog();
		$scope.paymentError = $scope.authMessage = $scope.paymentMessage = $scope.ccConfirmation = null;
	};
});