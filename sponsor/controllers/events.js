regApp.controller('eventsCtrl', function($scope, $http, $q, $timeout, $filter, accountid, dataSvc, erSvc, sponsorDataService) {
$scope.curProcess = 'order';
$scope.cart = {"events":[],"total":0};
$scope.payment_method = 'cc';
$scope.card = {};

$scope.initializeCcInfo();

let filter = 'accountid = ' + $scope.accountid + ' AND name =' +  "'vendorDiscountSettings'";
let thresholds = [];
dataSvc.getTableRecords('preferences',  filter).then(function(res){
	if(res[0]){
		try{ thresholds = angular.fromJson(res[0].value); }
		catch(e){ thresholds = []; }
	}
});

dataSvc.getArray({'query':'vendorDiscountCodesAvailable'})
	.then((resp) => $scope.discCdAvailable = Number(resp[0].count) > 0);

function refreshEvents(){
	sponsorDataService.getAllEventDetails().then(function(res){
		$scope.evtData = res.events;
		angular.forEach($scope.evtData,function(evt){
			if(evt.status != 'future') return;
			evt.availableExtras = [];
			evt.sponsorOptions.forEach(function(option){
				if(!option.availableForPurchase) return;
				if(evt.orderDetails.items.filter(i => i.event_sponsor_options_id == option.id).length == 0)
					evt.availableExtras.push(option);
				else option.ordered = true;
				option.vendor_type_restrictions = (option.vendor_type_restrictions || '').split(',');
			});
		});
		$scope.$apply();
	});
}
refreshEvents();

$scope.showEvent = function(event){
	if(event.status != 'future') return false;
	if(event.orderDetails.vendorType) return true;
	return event.regTypes.filter(t => t.availableForPurchase).length > 0;
};

$scope.showAsPreviousEvent = (event) => event.status != 'future' && event.orderDetails.vendorType;

$scope.addEditEvent = function(event){
	$scope.selectedEvent = angular.copy(event);
	if(!$scope.selectedEvent.vendorType){
		let curPrice = 0;
		$scope.selectedEvent.regTypes.forEach(function(type){
			if(!type.current || ! type.availableForPurchase) return;
			if(Number(type.price) > curPrice){
				$scope.selectedEvent.vendorType = type.id;
				curPrice = Number(type.price);
			} 
		});
	}
	$('#selectedEventDiv').dialog({
		"modal":true,
		"title":$scope.selectedEvent.name,
		"width":"500px"
	});
};

$scope.extraAvailable = function(type){
	let vendorType = $scope.selectedEvent.vendorType || $scope.selectedEvent.orderDetails.vendorType;
	if(!type.availableForPurchase) return false;
	let restrictions = type.vendor_type_restrictions;
	return restrictions.includes('') || restrictions.includes(vendorType.toString());
};

$scope.vendorTypeChange = function(){
	let sponsorType = $scope.selectedEvent.vendorType;
	$scope.selectedEvent.sponsorOptions.forEach(function(option){
		if(!$scope.extraAvailable(option)) option.selected = false;
	});
};

$scope.removeFromCart = function(event){
	$scope.selectedEvent = null;
	event.vendorType = null;
	event.sponsorOptions.forEach(o => o.selected = false);
	if(event.extraStaff) event.extraStaff.quantity = 0;
	$scope.updateCart();
};

$scope.updateCart = function(){
	if($scope.selectedEvent) $scope.evtData[$scope.selectedEvent.id] = angular.copy($scope.selectedEvent);
	$scope.cart.events = [];
	$scope.cart.total = 0;
	$scope.cart.discount = 0;
	angular.forEach($scope.evtData,function(event){
		if(event.vendorType){
			$scope.cart.events.push(event);
			$scope.cart.total += $scope.getEventTotal(event);
		}else if(eventExtrasOrdered(event)){
			$scope.cart.events.push(event);
			$scope.cart.total += $scope.getEventExtrasTotal(event);
		}
	});
	thresholds.forEach(function(threshold){
		if(threshold.val < $scope.cart.total){
			let discount = 0;
			if(threshold.method == 'percent'){
				discount = (threshold.discount/100) * $scope.cart.total;
				discount = Math.round(discount * 100) / 100;
			}else{
				discount = threshold.discount;
			}
			if(discount > $scope.cart.discount) $scope.cart.discount = discount;
		}
	});
	if($scope.cart.discountCode){
		let cd = $scope.cart.discountCode;
		let total = $scope.cart.total - $scope.cart.discount;
		let discount = 0;
		if(cd.method == 'percent'){
			discount = (Number(cd.discount)/100) * total;
			discount = Math.round(discount * 100) / 100;
		}else{
			discount = Number(cd.discount);
		}
		$scope.cart.discount = $scope.cart.discount + discount;
	}
	$scope.cart.due = $scope.cart.total - $scope.cart.discount;

	$scope.paymentAmount = ($scope.cart.due <= 5000 || $scope.ccProvider != 'magicWrighter') 
		? $scope.cart.due : 5000;
	$(".ui-dialog-content").dialog("close");
	$scope.card.amount = $scope.paymentAmount;
};

$scope.enterDiscountCode = function(){
	erSvc.easyRegFeedback("Discount Code", "Enter Discount Code","Submit Code","Cancel")
	.then(function(res){
		if(res){
			dataSvc.getArray({'query':'vendorDiscountCodeDetails','code':res}).then(function(resp){
				if(resp.length == 0){
					erSvc.easyRegAlert({"text":"This discount code is invalid.","title":"Invalid Code"});
				}else{
					let disc = resp[0]
					$scope.cart.discountCode = disc;
					$scope.updateCart();
					let msg; 
					let amt = $filter('currency')(disc.discount);
					if(disc.method == 'percent'){
						msg = `This discount code qualifies for a ${disc.discount} % discount.`;
					}else{
						msg = `This discount code qualifies for a ${amt} discount.`;
					}
					erSvc.easyRegAlert({"text":msg,"title":"Discount Code Applied"});
				}
			});
		}
	});
};

$scope.$watch('payment_method', function() {
    if($scope.payment_method == 'cc' && $scope.ccEnabled) 
    		$scope.paymentAmount = $scope.cart.due <= 5000 ? $scope.cart.due : 5000;
    else $scope.paymentAmount = $scope.cart.due;
});

$scope.getEventTotal = event => sponsorDataService.getEventTotal(event);
$scope.cancelEvent = () => $('#selectedEventDiv').dialog('close');
$scope.showExtras = evt => evt?.sponsorOptions.filter(e => e.availableForPurchase).length > 0;
$scope.showXtraStaff = evt => evt?.extraStaff?.current;

$scope.getSelectedVendorType = function(evt){
	let match;
	evt.regTypes.forEach(type => { if(type.id == evt.vendorType) match = type} );
	return match;
};

$scope.getSelectedExtras = evt => evt.sponsorOptions.filter(o => o.selected);

let currentOrderId;
$scope.submitPayment = function(){
	erSvc.loadingDialog();
	if($scope.ccProvider == 'magicWrighter' && $scope.cart.total > 0){
		$('#paymentForm').addClass('submitted');
		if(!$scope.paymentForm.$valid) {
			erSvc.closeLoading();
			return;
		}
	}
	//create order and send resulting id to cc payment
	let order = {
		"sponsorid":$scope.vendor.id,
		"total":$scope.cart.total,
		"discount":$scope.cart.discount,
		"payment_method":$scope.payment_method,
		"payment_number":$scope.payment_number
	};
	if($scope.cart.discountCode) order.discount_code = $scope.cart.discountCode.code;
	dataSvc.createOrUpdateRecord({"table":"vendor_orders","record":order}).then(function(res){
		currentOrderId = res;
		if($scope.payment_method == 'cc' && $scope.ccEnabled && $scope.cart.due > 0){
			$scope.processPayment($scope.card, $scope.paymentAmount, currentOrderId).then(function(status){
				$scope.cc_confirmation = status.payment_number;
				$scope.paymentError = status.paymentError;
				$scope.authMessage = status.authMessage;
				$scope.paymentMessage = status.paymentMessage;
				if(!$scope.paymentError) completeOrder();
				else deleteOrder(currentOrderId);
			});
		}else{
			completeOrder();
			$scope.curProcess = 'payment';
		}
	});
};

function deleteOrder(orderId){
	dataSvc.deleteRecord({"table":"vendor_orders","id":orderId});
	erSvc.closeLoading();
};

function completeOrder(){
	let totalInserts = 0;
	let insertsCompleted = 0;
	$scope.cart.events.forEach(function(evt){
		let vendorType = $scope.getSelectedVendorType(evt);
		let extras = $scope.getSelectedExtras(evt);
		let orderItem = {"vendor_orders_id":currentOrderId};
		if(vendorType){
			totalInserts++;
			let orderItem = {
				"vendor_orders_id":currentOrderId,
				"event_sponsor_options_id":vendorType.id, 
				"quantity":1,
				"unit_price":vendorType.price
			}
			dataSvc.createOrUpdateRecord({"table":"vendor_order_details","record":orderItem}).then(function(){
				if(++insertsCompleted == totalInserts) orderCompleted();
			});
			extras.forEach(function(extra){
				totalInserts++;
				orderItem.event_sponsor_options_id = extra.id;
				orderItem.unit_price = extra.price;
				dataSvc.createOrUpdateRecord({"table":"vendor_order_details","record":orderItem}).then(function(){
					if(++insertsCompleted == totalInserts) orderCompleted();
				});
			});
		}else{
			evt.availableExtras.forEach(function(extra){
				if(extra.selected){
					totalInserts++;
					orderItem.event_sponsor_options_id = extra.id;
					orderItem.unit_price = extra.price;
					dataSvc.createOrUpdateRecord({"table":"vendor_order_details","record":orderItem}).then(function(){
						if(++insertsCompleted == totalInserts) orderCompleted();
					});
				}
			});
		}
		
		if(evt.extraStaff?.quantity){
			totalInserts++;
			orderItem.event_sponsor_options_id = evt.extraStaff.id;
			orderItem.quantity = evt.extraStaff.quantity;
			orderItem.unit_price = evt.extraStaff.price;
			dataSvc.createOrUpdateRecord({"table":"vendor_order_details","record":orderItem}).then(function(){
				if(++insertsCompleted == totalInserts) orderCompleted();
			});
		}
	});
}

function orderCompleted(){
	erSvc.closeLoading();
	$scope.curProcess = 'summary';
}

$scope.getVendorType = function(event){
	let typ = {};
	let regType = event.orderDetails.vendorType;
	event.regTypes.forEach(t => {if(t.id == regType) typ = {"name":t.name,"staffAllowed":t.staff_allowance}});
	return typ;
};

$scope.extrasAvailable = evt => {
	if(evt.extraStaff?.current) return true;
	let vendorType = evt.vendorType || evt.orderDetails.vendorType;
	if(!vendorType) return (evt.availableExtras || []).length;
	if(!evt.sponsorOptions?.length) return false;
	let opts = evt.sponsorOptions.filter(type => {
		if(!type.availableForPurchase || type.ordered) return false;
		return type.vendor_type_restrictions.includes('') || 
			type.vendor_type_restrictions.includes(vendorType.toString());
	});
	return opts.length;
};

$scope.orderExtras = function(event){
	$scope.selectedEvent = angular.copy(event);
	$('#eventExtrasDiv').dialog({ "modal":true, "title":$scope.selectedEvent.name, "width":"500px" });
};

$scope.cancelExtras = () => $('#eventExtrasDiv').dialog('close');

$scope.optionOrdered = function(opt){
	return $scope.selectedEvent.orderDetails.items.filter(i => i.event_sponsor_options_id == opt.id).length > 0
};

$scope.getEventExtrasTotal = function(evt){
	if(!evt) return;
	let total = 0;
	(evt.availableExtras || []).forEach(xtra => { if(xtra.selected) total += xtra.price });
	if(evt.extraStaff?.quantity) total += (evt.extraStaff.price * evt.extraStaff.quantity);
	return total;
};

let eventExtrasOrdered = function(evt){
	if(!evt) return;
	if((evt.availableExtras || []).filter(xtra => xtra.selected).length) return true;
	return (evt.extraStaff?.quantity);
}

// Event Staff
$scope.staffAllowed = function(){
	if($scope.selectedSponsorOption){
		return Number($scope.selectedSponsorOption.staff_allowance) + Number($scope.extraStaffCount);
	}
	else return 0;
};

$scope.addStaff = function(){
	erSvc.loadingDialog();
	if($scope.totalRegistrations >= $scope.staffAllowed()){
		erSvc.closeLoading();
		return;
	}

	//get registration type based on selected sponsorship option
	var selectedRegType;
	angular.forEach($scope.reg_types,function(type){
		if(type.name.toLowerCase() == $scope.selectedSponsorOption.name.toLowerCase()){
			selectedRegType = type.id;
		}
	});
	if(!selectedRegType) selectedRegType = $scope.reg_types[0].id;
	var s = $scope.newStaff;
	var newReg = {
		"email":s.email,
		"business":$scope.vendor.name,
		"first_name":s.first_name,
		"last_name":s.last_name,
		"address1":s.address1,
		"address2":s.address2,
		"city":s.city,
		"state":s.state,
		"zip":s.zip,
		"phone":s.phone,
		"userid":s.id,
		"web_address":$scope.vendor.web_address,
		"registration_typeid": selectedRegType
	};
	dataSvc.createOrUpdateRegistration(newReg, $scope.selectedEvent.id).then(function(res){
		$scope.eventRegistrations[res.id] = res;
		$scope.totalRegistrations++;
		var emailData = 	{
			"confirmation":res.confirmation,
			"first_name":res.first_name,
			"last_name":res.last_name,
			"email":res.email,
			"eventid":$scope.selectedEvent.id
		};
		$.post('/save_registration.php', emailData);
		erSvc.closeLoading();
	});
}

let attendeeEvent = ''
$scope.showAttendeeList = function(event){
	erSvc.loadingDialog();
	dataSvc.getArray({'query':'eventAttendeeList','eventid':event.id}).then(function(resp){
		$scope.attendees = resp;
		$scope.dialogTitle = `Attendees - ${event.name}`;
		$('#attendeeDialog').show(500);
		$scope.$applyAsync();
		erSvc.closeLoading();
	});
	attendeeEvent = event.name;
};

$scope.exportAttendees = function(){
	exportData($('#attendeesTable'), `${attendeeEvent} Attendees`);
};

})//End Controller
.directive('eventsPaymentDialog',function(){
	return {
        restrict: 'AEC',
        templateUrl: '/directives/paymentDialog.html?v_' + Math.random(),
        link: function (scope, element, attrs, ngModelCtrl) {
            $(element).find('.dialogRight').removeClass('dialogRight');
            $(element).find('.dialogTitle, .btn btn-danger').remove();
            $(element).find('tr:contains("Payment Amount")').remove();
        }
    };
})
.filter('orderableEvents', function(){
	return function(events){
		let filtered = [];
		angular.forEach(events,function(evt){
			if(evt.status == 'future' && !evt.orderDetails.vendorType) filtered.push(evt);
		});
		return filtered.sort((a,b) => a.startdate > b.startdate ? 1 : -1);
	}
});//end filters;