regApp.controller('videoOrders', function($scope, $http, $q, dataSvc, erSvc) {

	//initialize date range to past year
	let curDt = new Date();
	let dd = curDt.getDate();
	let mm = curDt.getMonth() + 1; //January is 0
	let yyyy = curDt.getFullYear();
	if (dd < 10) dd = '0' + dd;
	if (mm < 10) mm = '0' + mm;

	$scope.startDate = mm + '/' + dd + '/' + (yyyy - 1);
	$scope.endDate = mm + '/' + dd + '/' + yyyy;
	
	$scope.getOrders = function(){
		let ordersRetrieved = $q.defer();
		let detailsRetrieved = $q.defer();

		let orderParams = {
			'query':'videoOrders',
			'startDate':$scope.startDate,
			'endDate':$scope.endDate
		}
		dataSvc.getObject(orderParams).then(function(res){
			$scope.orders = res;
			angular.forEach($scope.orders,o => {
				o.display_date = erSvc.mySqlToLocalDate(o.created_time);
				o.videos = [];
				o.packages = [];
				o.total = Number(o.total);
				o.discount = Number(o.discount);
				o.paid = Number(o.paid);
				o.show = true;
			});
			ordersRetrieved.resolve();
			$scope.quickSearch();
		});

		let orderDetails;
		let detailParams = {
			'query':'videoOrderDetails',
			'startDate':$scope.startDate,
			'endDate':$scope.endDate
		}
		dataSvc.getArray(detailParams).then(function(res){
			orderDetails = res;
			detailsRetrieved.resolve();
		});

		$q.all([ordersRetrieved.promise,detailsRetrieved.promise]).then(function(){
			orderDetails.forEach(function(detail){
				if(detail.type == 'video') $scope.orders[detail.orderid].videos.push(detail.name);
				else $scope.orders[detail.orderid].packages.push(detail.name);
			});
			angular.forEach($scope.orders,o => o.searchVal = erSvc.getObjectSearchString(o));
		});
	}

	$scope.getOrders();

	$scope.searchVal = "";
	$scope.chargeTotal = 0;
	$scope.discountTotal = 0;
	$scope.paidTotal = 0;

	$scope.quickSearch = function(){
		$scope.chargeTotal = 0;
		$scope.discountTotal = 0;
		$scope.paidTotal = 0;
		let searchVal = $scope.searchVal.toLowerCase();
		angular.forEach($scope.orders,function(order){
			order.show = searchVal == '' || order.searchVal.includes(searchVal);
			if(order.show){
				$scope.chargeTotal += order.total;
				$scope.discountTotal += order.discount;
				$scope.paidTotal += order.paid;
			}
		});
	};

	$scope.editOrder = function(order){
		$scope.selectedOrder = angular.copy(order);
		$('#editDialog').show(500);
	};

	$scope.saveUpdate = function(){
		dataSvc.createOrUpdateRecord({"table":"video_orders","record":{...$scope.selectedOrder}})
		.then(function(res){
			if(res == '0') $scope.orders[$scope.selectedOrder.id] = {...$scope.selectedOrder};
		});
		$scope.closeRightDialog();
	};

	$scope.confirmDelete = function(){
		let ttl = "Delete Order?";
		let msg = `Delete order for ${$scope.selectedOrder.last_name}, ${$scope.selectedOrder.first_name}`;
		erSvc.easyRegConfirm({"text":msg,"title":ttl},"Confirm","Cancel").then(function(res){
			if(res){
				dataSvc.deleteRecord({"table":"video_orders","id":$scope.selectedOrder.id}).then(function(){
					delete $scope.orders[$scope.selectedOrder.id];
					$scope.closeRightDialog();
				});
			}
		});
	};

	$scope.closeRightDialog = () => $(".dialogRight").hide(500);
});// End Controller