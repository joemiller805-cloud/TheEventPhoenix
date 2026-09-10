angular.module("sponsorDataModule",['easyRegDataModule','erSvc'])
.service("sponsorDataService",function($http, $q, dataSvc, erSvc){
	var svc = this;
	var getRecs = dataSvc.getTableRecords;
	svc.sponsorid;

	svc.getSponsor = function(){
		var sponsorData = $q.defer();
		getRecs('sponsors', 'id = ' + this.sponsorid).then(function(sponsor){
			svc.sponsor = sponsor[0];
			svc.sponsor.payments = {};

			getRecs('users', 'sponsorid = ' + svc.sponsorid).then(function(res){
				svc.sponsor.staff = res;
				sponsorData.resolve(svc.sponsor);
			});
		});
		return sponsorData.promise;
	};//end getSponsor()

	svc.getAllEventDetails = async function(){
		let response = $q.defer();
		let vendorData = {};
		vendorData.events = await dataSvc.getObject({'query':'accountEvents'});

		let eventIds = [];
		angular.forEach(vendorData.events,function(event){
			event.regTypes = [];
			event.sponsorOptions = [];
			event.orderDetails = {"items":[],"vendorType":"","extraStaff":0};
			event.extraStaff = null;
			event.staffAllowance = 0;
			event.eventStaff = [];
			eventIds.push(event.id);
		});

		// get sponsor options
		let optionsRetrieved = $q.defer();
		let optRetrievalCount = 0;
		eventIds.forEach(function(evtId){
			dataSvc.getArray({'query':'eventOptions','eventid':evtId}).then(function(types){
				let event = vendorData.events[evtId];
				angular.forEach(types,function(type){
					type.price = Number(type.price);
					type.sunrise = erSvc.mySqlToLocalDate(type.sunrise);
					type.sunset = erSvc.mySqlToLocalDate(type.sunset);
					type.current = erSvc.dateRangeIsCurrent(type.sunrise,type.sunset);
					type.atCapacity = (type.num_available || '0') != '0' && 
						Number(type.num_available) <= Number(type.orderCount);
					type.availableForPurchase = type.current && !type.atCapacity;
					if(type.type == 'event vendor') event.regTypes.push(type);
					else if(type.type == 'event staff') event.extraStaff = type;
					else if(type.type == 'event extra') event.sponsorOptions.push(type);
				});
				if(++optRetrievalCount == eventIds.length) optionsRetrieved.resolve();
			});
		});	

		let ordersRetrieved = $q.defer();
		getRecs('vendor_orders', 'sponsorid = ' + svc.sponsorid, true).then(function(res){
			vendorData.orders = res;
			angular.forEach(vendorData.orders, o => {
				o.items = [];
				o.payments = [];
				o.due = Number(o.total) - Number(o.discount);
				o.totalPaid = 0;
			});
			ordersRetrieved.resolve();
		});

		let orderItemsRetrieved = $q.defer();
		let orderItems = {};
		dataSvc.getObject({'query':'vendorOrderItems','sponsorid':svc.sponsorid}).then(function(items){
			vendorData.orderItems = items;
			orderItemsRetrieved.resolve();
		});

		let paymentsRetrieved = $q.defer();
		let payments = {};
		dataSvc.getObject({'query':'vendorPayments','sponsorid':svc.sponsorid}).then(function(payments){
			angular.forEach(payments, p => p.method = p.method.toLowerCase());
			vendorData.payments = payments;
			paymentsRetrieved.resolve();
		});

		let eventStaffRetrieved = $q.defer();
		let eventStaff = [];
		dataSvc.getArray({'query':'sponsorRegistrations'}).then(function(res){
			eventStaff = res;
			eventStaffRetrieved.resolve();
		});

		$q.all([optionsRetrieved.promise,ordersRetrieved.promise,
			orderItemsRetrieved.promise,eventStaffRetrieved.promise,paymentsRetrieved.promise])
		.then(function(){
			angular.forEach(vendorData.orderItems,function(item){
				if(vendorData.orders[item.vendor_orders_id]){
					if(vendorData.orders[item.vendor_orders_id].cancelled == '1') return;
					vendorData.orders[item.vendor_orders_id].items.push(item);
				} 
				let evt = vendorData.events[item.eventid];
				if(!evt) return;
				if(item.type == 'event vendor'){
					evt.orderDetails.vendorType = item.event_sponsor_options_id;
					evt.staffAllowance += Number(item.staff_allowance) || 0;
					evt.staff_reg_type_id = item.registration_type_id
				} 
				else if(item.type == 'event extra') evt.orderDetails.items.push(item);
				else if(item.type == 'event staff'){
					evt.orderDetails.extraStaff = (Number(evt.orderDetails.extraStaff) || 0) + Number(item.quantity);
					evt.staffAllowance += Number(item.quantity) || 0;
				} 
			});
			angular.forEach(vendorData.payments,function(pymt){
				let order = vendorData.orders[pymt.vendor_orders_id];
				if(!order) return;
				order.payments.push(pymt);
				order.due -= Number(pymt.amount);
				order.totalPaid += Number(pymt.amount);
			});
			eventStaff.forEach(function(reg){
				if(vendorData.events[reg.eventid]) vendorData.events[reg.eventid].eventStaff.push(reg);
			});
			response.resolve(vendorData);
		});
		
		return response.promise;
	};// End getAllEventDetails()

	//get total price for a given event's orders
	svc.getEventTotal = function(event){
		if(!event?.vendorType) return 0;
		let total = 0;
		event.regTypes.forEach(t => { total += (t.id == event.vendorType ? t.price : 0)});
		event.sponsorOptions.forEach(o => { total += (o.selected ? o.price : 0) });
		total += (event.extraStaff?.quantity || 0) * (event.extraStaff?.price || 0);
		return total;
	};

	function getObjectFromArray(arry, idx){
		idx = idx || 'id';
		let obj = {};
		arry.forEach( (item) => obj[item[idx]] = item );
		return obj;
	}
});