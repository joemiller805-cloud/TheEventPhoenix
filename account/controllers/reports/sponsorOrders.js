regApp.controller('sponsorOrders', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.sortField = 'sponsor';
	$scope.sortReverse = false;
	$scope.orders = [];
	erSvc.initializeColumns($('#sponsorTable'));

	$scope.getOders = function(){
		orderDetails = [];
		$scope.orders = [];
		var startDt = erSvc.localToMySqlDate($scope.startDt);
		var endDt = erSvc.localToMySqlDate($scope.endDt);
		if(!startDt || !endDt) return;

		dataSvc.getArray({'query':'sponsor_orders','startDt':startDt,'endDt':endDt}).then(function(orders){
			$scope.orders = orders;
			if(orders.length == 0){
				erSvc.easyRegAlert({"text":"No orders found in this date range","title":"No Orders Found"});
			}
			angular.forEach($scope.orders,function(order){
				order.include = true;
				order.isCancelled = order.cancelled == '1';
				order.status = order.isCancelled ? 'Cancelled' : 'Active';
				order.payments = Number(order.payments) || 0;
				order.displayDate = erSvc.mySqlToLocalDate(order.created_time);
				order.due = Number(order.total) - Number(order.discount || 0) - order.payments;
			});
			erSvc.initializeColumns($('#sponsorTable'));
			$scope.$applyAsync();
		});

		dataSvc.getArray({'query':'sponsor_order_deatails','startDt':startDt,'endDt':endDt}).then(function(details){
			orderDetails = details;
			orderDetails.forEach(function(detail){
				if(detail.type == 'event vendor') detail.type = 'Vendor/Sponsorship';
				else if(detail.type == 'event extra') detail.type = 'Event Extra';
				else if(detail.type == 'event staff') detail.type = 'Extra Staff';
				detail.total = (detail.quantity || 1) * detail.unit_price;
			});
		});
	};

	$scope.changeSort = function(field){
		if($scope.sortField == field){
			$scope.sortReverse = !$scope.sortReverse;
		}else{
			$scope.sortField = field;
			$scope.sortReverse = false;
		}
	};

	let orderDetails = [];
	$scope.viewDetails = function(order){
		$scope.selectedOrderDetails = orderDetails.filter(d => d.orderid == order.orderid);
		$('#orderDetailsDiv').dialog({
			"title":"Order Details - Order# " + order.orderid,
			"modal":true,
			"width": 600
		});
	};

	$scope.closeDialog = () => $('#orderDetailsDiv').dialog('close');

	$scope.quickSearch = function(){
		var include;
		var compareValue = $scope.quickSearchValue ? $scope.quickSearchValue.toLowerCase() : '';
		angular.forEach($scope.orders,function(order){
			order.include =
				order.vendor.toLowerCase().indexOf(compareValue) >=0 ||
				order.status.toLowerCase().indexOf(compareValue) >= 0 ||
				!compareValue;
		});
	};

	$scope.columnFilter = () => erSvc.columnFilter($('#sponsorTable'));
});// End Controller
