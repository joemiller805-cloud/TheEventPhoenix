regApp.controller('invoiceCtrl', function($scope, $http, $q, accountid, dataSvc, erSvc, sponsorDataService) {
	dataSvc.getArray({'query':'accountWebLogo'}).then(function(resp){
		$scope.logoSrc = '/img/logo.png';
		if(resp[0]){
			if(resp[0].web_logo) $scope.logoSrc = resp[0].web_logo;
		}
	});

	$scope.totalPurchases = $scope.totalPayments = $scope.totalDue = $scope.totalDue = 0;

	erSvc.loadingDialog("Fetching Order Info");
	sponsorDataService.getAllEventDetails().then(function(res){
		evtData = res;
		aggregateOrderData();
		$scope.$apply();
		erSvc.closeLoading();
	});

	function aggregateOrderData(){
		$scope.orders = [];
		angular.forEach(evtData.orders,function(order){
			if(order.due <= 0 || order.cancelled == '1') return;
			$scope.totalPurchases += Number(order.total);
			$scope.totalPayments += order.totalPaid;
			$scope.totalDue += order.due;
			$scope.totalDiscount += order.discount;
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
			$scope.orders.push(order);
		});
		
		$scope.noOrders = Object.keys($scope.orders).length == 0;
	}

	$scope.today = new Date();

	dataSvc.getArray({'query':'accountContactInfo','accountid':$scope.accountid}).then(function(resp){
		$scope.contact = resp[0];
		$scope.contact.sponsor_invoice_msg = $scope.contact.sponsor_invoice_msg.replace(/(?:\r\n|\r|\n)/g, '<br>')
	});

	function getTotals(){
		angular.forEach($scope.vendor.events,function(event){
			if(event.due == 0)  return;
			$scope.totalPurchases += event.eventTotal;
			$scope.totalPayments += event.paid;
			$scope.totalDue += event.due;
		});
	}
});