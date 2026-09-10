regApp.controller('vendorDetails', function($scope, $http, $routeParams, dataSvc, sponsorDataService, erSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;
	$scope.vendorid = $routeParams.id;
	$scope.userid = erSessionData.userid;
	$scope.states = erSvc.getStateOptions();
	$scope.view = 'orders';

	sponsorDataService.sponsorid = $scope.vendorid;
	sponsorDataService.getSponsor().then(function(res){
		$scope.vendor = res;
	});

	let evtData;
	function refreshVendorData(){
		erSvc.loadingDialog("Fetching Order Info");
		sponsorDataService.getAllEventDetails().then(function(res){
			evtData = res;
			aggregateOrderData();
			$scope.$apply();
			erSvc.closeLoading();
			$scope.closeRightDialog();
			$scope.closeDialog();
		});
	}

	refreshVendorData();
	
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
						"eventid":evt.id,
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

	// ******** Edits for vendor type, extra staff, and event extras ********
	let selectedOrder;

	//Vendor type change
	$scope.changeVendorType = function(order, event){
		selectedOrder = order;
		$scope.selectedEvent = evtData.events[event.eventid];
		$scope.newVendorType = event.vendorTypeOrder.event_sponsor_options_id
		$('#changeVendorTypeDiv').dialog({
			"modal":true,
			"title":"Change Vendor Type"
		});
	};

	$scope.submitVendorChange = function(){
		erSvc.loadingDialog();
		let prevOption, newOption;
		$scope.selectedEvent.regTypes.forEach(function(type){
			if(type.id == $scope.newVendorType) newOption = type;
			else if(type.id == $scope.selectedEvent.orderDetails.vendorType) prevOption = type;
		});

		//update object for vendor_order_details record
		let orderItem = {
			"id":selectedOrder.events[$scope.selectedEvent.id].vendorTypeOrder.id,
			"event_sponsor_options_id":$scope.newVendorType,
			"unit_price":newOption.price
		};
		
		//update object for vendor_orders record
		let order = {
			"id":selectedOrder.id,
			"total": Number(selectedOrder.total) - (prevOption.price - newOption.price)
		};

		dataSvc.createOrUpdateRecord({"table":"vendor_order_details","record":orderItem}).then(() => {
			dataSvc.createOrUpdateRecord({"table":"vendor_orders","record":order}).then(() => {
				refreshVendorData();
			});
		});
	};

	//Extra staff change
	$scope.extraStaffEditRec;
	let originalStaffQuantity;
	$scope.changeExtraStaff = function(order, event){
		selectedOrder = order;
		$scope.extraStaffEditRec = angular.copy(event.extraStaffOrder);
		originalStaffQuantity = Number($scope.extraStaffEditRec.quantity)
		$scope.extraStaffEditRec.quantity = Number($scope.extraStaffEditRec.quantity);
		$scope.selectedEvent = event;
		$('#changeExtraStaffDiv').dialog({
			"modal":true,
			"width":350,
			"title":"Change Extra Staff Order"
		});
	};

	$scope.submitExtraStaffChange = function(){
		let staffDiff = originalStaffQuantity - $scope.extraStaffEditRec.quantity;
		let priceDiff = Number($scope.extraStaffEditRec.unit_price) * staffDiff;
		//update object for vendor_order_details record
		let orderItem = {
			"id":$scope.extraStaffEditRec.id,
			"quantity":$scope.extraStaffEditRec.quantity
		};
		//update object for vendor_orders record
		let order = {
			"id":selectedOrder.id,
			"total": Number(selectedOrder.total) - priceDiff
		};
		dataSvc.createOrUpdateRecord({"table":"vendor_order_details","record":orderItem}).then(() => {
			dataSvc.createOrUpdateRecord({"table":"vendor_orders","record":order}).then(() => {
				refreshVendorData();
			});
		});
	};

	//Event extras change
	$scope.changeExtraOption = function(order, event, extraItem){
		erSvc.loadingDialog();
		let txt = event.name + ": " + extraItem.name + "<br/>Delete this event extra order?";
		erSvc.easyRegConfirm({"text":txt,"title":"Delete Event Extra"},"Confirm","Cancel").then(function(res){
			if(res){
				let orderUpdate = {
					"id":order.id,
					"total": Number(order.total) - Number(extraItem.unit_price)
				};
				dataSvc.deleteRecord({"table":"vendor_order_details","id":extraItem.id}).then(() => {
					dataSvc.createOrUpdateRecord({"table":"vendor_orders","record":orderUpdate}).then(() => {
						refreshVendorData();
					});
				});
			}
			else erSvc.closeLoading();
		});
	};

	$scope.closeDialog = () => $(".ui-dialog-content").dialog("close");

	// ***** Payment Management *****
	$scope.editPayment = function(payment){
		payment.payment_date = erSvc.mySqlToLocalDate(payment.payment_date);
		let paymentCopy = angular.copy(payment);
		delete paymentCopy.created_time;
		delete paymentCopy.updated_time;
		if(payment.amount < 0){
			paymentCopy.amount = Math.abs(paymentCopy.amount);
			$scope.refund = paymentCopy;
			$('#refundDialog').show(500);
		}else{
			$scope.payment = paymentCopy;
			$('#paymentDialog').show(500);
		}
	};

	//user selects payment from Enter Payment Button
	$scope.enterPayment = function(order){
		$('form[name="paymentForm"]').removeClass('submitted');
		$scope.payment = {
			'vendor_orders_id': order.id,
			'payment_date': erSvc.today(),
			'entered_by': $scope.userid,
			'method': "cc",
			'amount': order.due.toFixed(2)
		};
		$('#paymentDialog').show(500);
	};

	$scope.submitPayment = function(){
		$('form[name="paymentForm"]').addClass('submitted');
		if(!$scope.paymentForm.$valid) return;
		erSvc.loadingDialog();
		paymentData = {"table":"vendor_payments","record":$scope.payment};
		dataSvc.createOrUpdateRecord(paymentData).then(refreshVendorData);
	};

	$scope.deletePayment = function(payment){
		erSvc.loadingDialog();
		let msg = "Please confirm you would like to delete this payment record";
		erSvc.easyRegConfirm({"text":msg,"title":"Confirm Payment Deletion"},'Confirm','Cancel').then((res) => {
			if(res) dataSvc.deleteRecord({"table":"vendor_payments","id":payment.id}).then(refreshVendorData);
			else erSvc.closeLoading();
		});
	};

	$scope.enterDiscount = function(order){
		$scope.selectedOrder = angular.copy(order);
		$('#discountDiv').dialog({ "modal":true, "title":"Order Discount" });
	};

	$scope.submitDiscount = function(){
		let newOrder = {"id":$scope.selectedOrder.id,"discount":$scope.selectedOrder.discount};
		dataSvc.createOrUpdateRecord({"table":"vendor_orders","record":newOrder}).then(refreshVendorData);
	};

	$scope.enterRefund = function(order){
		$scope.selectedOrder = order;
		$scope.refund = {
			'sponsorid': $scope.vendorid,
			'payment_date': erSvc.today(),
			'entered_by': $scope.userid,
			'payment_date':erSvc.today(),
			'vendor_orders_id':order.id,
			'amount': 0
		};
		$('#refundDialog').show(500);
	};

	$scope.submitRefund = function(){
		erSvc.loadingDialog();
		$scope.closeRightDialog();
		paymentData = {"table":"vendor_payments","record":$scope.refund};
		$scope.refund.amount = Math.abs(Number($scope.refund.amount)) * -1;
		dataSvc.createOrUpdateRecord(paymentData).then(refreshVendorData);
	};

	$scope.cancelOrder = function(order){
		let txt = "Confirm order cancellation"
		erSvc.easyRegConfirm({"text":txt,"title":"Cancel Order"},"Confirm","Back")
		.then(function(res){
			if(res){
				let rec = {"id":order.id,"cancelled":'1'};
				dataSvc.createOrUpdateRecord({"table":"vendor_orders","record":rec}).then(refreshVendorData);
			}
		});
	};

	$scope.activateOrder = function(order){
		let rec = {"id":order.id,"cancelled":"0"};
		order.cancelled = '0';
		dataSvc.createOrUpdateRecord({"table":"vendor_orders","record":rec});
		let txt = "Order # " + ('000000000' + order.id).slice(-6) + " is now an active order.";
		erSvc.easyRegAlert({"text":txt,"title":"Order Active"}, true);
	};

	// ***** Staff Management *****
	$scope.staffEdit = function(staff){
		$('#staffDialog').show(500);
		$scope.selectedStaff = staff;
	};

	$scope.updateStaff = function(){
		$('form[name="staffForm"]').addClass('submitted');
		if(!$scope.staffForm.$valid) return;
		dataSvc.createOrUpdateRecord({"table":"users","record":$scope.selectedStaff})
		.then(function(){ $(".dialogRight").hide(500); });
	};

	$scope.closeRightDialog = () => $(".dialogRight").hide(500);
});//end controller