regApp.controller('seasonPassesAct', function($scope, $http, $rootScope, $location, $q, dataSvc, erSvc){
	const accountid = erSessionData.accountid;
	let initialPasses;
	dataSvc.getTableRecords('season_passes', `accountid = ${accountid}`, true).then(function(res){
		$scope.passes = res;
		for(const id in $scope.passes){
			pass = $scope.passes[id];
			try{
				pass.event_details = angular.fromJson(pass.event_details);
			}catch(e){
				pass.event_details = {};
			}
		};
		initialPasses = angular.copy($scope.passes);
	});

	$scope.eventSelected = function(evt){
		if(!$scope.selectedPass) return false;
		return erUtils.hasIdKey($scope.selectedPass.event_details, evt.id);
	};

	$scope.toggleEvent = function(evt){
		if(!$scope.selectedPass) return;
		if($scope.eventSelected(evt)){
			delete $scope.selectedPass.event_details[evt.id];
		}else{
			$scope.selectedPass.event_details[evt.id] = {"regType":evt.regTypes[0].id,"tickets":{}};
		}
	};

	$scope.newPass = function(){
		$scope.dialogTitle = "New Season Pass";
		$scope.selectedPass = {"accountid":accountid,"event_details":{}};
		$('#editDialog').show(500);
	};

	let originalPass = {};
	$scope.editPass = function(pass){
		originalPass = angular.copy(pass);
		$scope.selectedPass = angular.copy(pass);
		$scope.dialogTitle = "Edit - " + pass.name;
		$('#editDialog').show(500);
	};

	$scope.savePass = function(){
		erSvc.loadingDialog();
		let pass = angular.copy($scope.selectedPass);
		if(!pass.name){
			erSvc.easyRegAlert({"text":"Please enter name","title":"Error"});
			erSvc.closeLoading();
			return;
		};
		pass.event_details = angular.toJson(pass.event_details || {});
		let esxistingPass = $scope.selectedPass.id;
		if(esxistingPass){
			getPurchasedPasses().then(function(){
				if(purchasedPasses.length && orderUpdatesNeeded()){
					let txt = `Some orders already exist for this pass, and some events and/or tickets have been added <br/><br/>
						If you continue, existing orders will be updated to include and added events or tickets.<br/><br/>
						Any events or tickets that have been removed/reduced will not be reflected in current pass orders, 
						but will be applied to future purchases.`;
					erSvc.easyRegConfirm({"text":txt,"title":"Confirm Pass Update"},"Update Pass","Cancel").then(function(res){
						if(res) finishPassUpdate();
						else erSvc.closeLoading();
					});
				}else{
					finishPassUpdate();
				}
			});
		}else{
			finishPassUpdate();
		}
		function finishPassUpdate(){
			dataSvc.createOrUpdateRecord({"table":"season_passes","record":pass}).then(function(res){
				$scope.selectedPass.id = $scope.selectedPass.id || res;
				$scope.passes[pass.id] = angular.copy($scope.selectedPass);
				if(esxistingPass) updateCurrentlyPurchasedPasses();
				initialPasses[$scope.selectedPass.id].event_details = $scope.selectedPass.event_details;
				$scope.closeRightDialog();
				erSvc.closeLoading();
			});
		}
	};

	$scope.deletePass = function(pass){
		erSvc.easyRegConfirm({"text":`Delete Pass - ${pass.name}`,"title":`Confirm Delete`},"Continue","Cancel")
		.then(function(res){
			if(res){
				dataSvc.deleteRecord({"table":"season_passes","id":pass.id});
				delete $scope.passes[pass.id];
			}
		});
	};

	$scope.events = {};
	dataSvc.getArray({'query':'accountEvents'}).then(r => {
		r.forEach(e => {
			if(!['recent','future'].includes(e.status)) return;
			$scope.events[e.id] = {
				"name":e.name,
				"id":e.id,
				"startdate":e.startdate,
				"regTypes":[],
				"tickets":[]
			}
			getEventDetails($scope.events[e.id]);
		});
	});

	$scope.regTypeMap = {};
	$scope.ticketMap = {};
	function getEventDetails(evt){
		let regTypesRetrieved = $q.defer();
		let ticketsRetrieved = $q.defer();
		dataSvc.getTableRecords('registration_types',`eventid = ${evt.id}`).then(r => {
			evt.regTypes = r || [];
			regTypesRetrieved.resolve();
			r.forEach(rt => $scope.regTypeMap[rt.id] = rt);
		});
		dataSvc.getTableRecords('registration_extras',`eventid = ${evt.id}`).then(r => {
			evt.tickets = r;
			ticketsRetrieved.resolve();
			r.forEach(t => $scope.ticketMap[t.id] = t);
		});
		$q.all([regTypesRetrieved.promise,ticketsRetrieved.promise]).then(function(){
			if(evt.regTypes.length == 0) delete $scope.events[evt.id];
		});
	}

	$scope.closeRightDialog = () => $('#editDialog').hide(500);

	/************  Logic to update passes already purchased ****************/

	let purchasedPasses = [];
	//existing ticket purchases keyed by [ssnPassOrderId-regTypeId-regExtrasId] = registration
	let purchasedTickets = {};
	//existing registrations keyed by [ssnPassOrderId-regTypeId] = registration
	let existingRegMap = {};

	//determine if any events or tickets have been added to $scope.selectedPass
	function orderUpdatesNeeded(){
		let newEvents = {}; //events added to selectedPass 
		let newTickets = {}; //tickets added to selectedPass
		let increasedTickets = {}; //ticket quantities increased for selectedPass
		let initialEvts = initialPasses[$scope.selectedPass.id].event_details;
		let curEvts = $scope.selectedPass.event_details;
		for(eventId in curEvts){
			let curEvent = curEvts[eventId];
			let prevEvent = initialEvts[eventId];
			if(!prevEvent){
				newEvents[eventId] = curEvent;
				continue;
			}
			for(tktId in curEvent.tickets){
				let curTkts = curEvent.tickets[tktId];
				let prevTkts = prevEvent.tickets[tktId];
				if(curTkts && !prevTkts) newTickets[tktId] = curTkts;
				else if(curTkts > prevTkts) increasedTickets[tktId] = curTkts;
			}
		}
		return Object.keys(newEvents).length || Object.keys(newTickets).length || Object.keys(increasedTickets).length || true;
	}

	//update purchasedPasses(controller global variable) to include current purchases of $scope.selectedPass
	function getPurchasedPasses(){
		let passesRetrieved = $q.defer();
		purchasedPasses = [];
		dataSvc.getArray({'query':'seasonPassOrders','seasonPassId':$scope.selectedPass.id}).then(function(resp){
			purchasedPasses = resp;
			purchasedPasses.forEach(function(purchase){
				try{ purchase.order_details = angular.fromJson(purchase.order_details);	}
				catch(e){console.log(e)}
			});
			passesRetrieved.resolve();
		});
		return passesRetrieved.promise;
	}

	//compare previous pass details to current and update orders accordingly
	function updateCurrentlyPurchasedPasses(){
		existingRegMap = {};
		purchasedTickets = {};
		let registrationsRetrieved = $q.defer();
		let ticketsRetrieved = $q.defer();
		dataSvc.getArray({'query':'registrationsBySsnPass','seasonPassId':$scope.selectedPass.id}).then(function(resp){
			resp.forEach(reg => existingRegMap[reg.season_pass_ordersid + '-' + reg.registration_typeid] = reg);
			registrationsRetrieved.resolve();
		});
		dataSvc.getArray({'query':'extrasOrdersBySsnPass','seasonPassId':$scope.selectedPass.id}).then(function(resp){
			resp.forEach(t => purchasedTickets[t.pass_order_id+'-'+t.registration_typeid+'-'+t.registration_extras_id] = t);
			ticketsRetrieved.resolve();
		});
		$q.all([registrationsRetrieved.promise,ticketsRetrieved.promise]).then(function(){
			purchasedPasses.forEach(passPurchase => updatePassForCurStatus(passPurchase));
		});
	}

	//for a given pass purchase make necessary adjustments (registrations, ticket orders)
	function updatePassForCurStatus(passPurchase){
		let curEvts = $scope.selectedPass.event_details;
		let registrationsToCreate = registrationsCreated = 0;
		for(eventid in curEvts){
			let evt = curEvts[eventid];
			if(!existingRegMap[passPurchase.id + '-' + evt.regType]){
				++registrationsToCreate;
				createRegistration(passPurchase, eventid, evt).then(function(){
					if(++registrationsCreated == registrationsToCreate) createOrUpdateTickets(passPurchase);
				});
			}	
		};
		if(registrationsToCreate == 0) createOrUpdateTickets(passPurchase);
	}

	function createOrUpdateTickets(passPurchase){
		let curEvts = $scope.selectedPass.event_details;
		for(eventid in curEvts){
			let evt = curEvts[eventid];
			for(tktId in evt.tickets){ createOrUpdateTicketOrder(passPurchase, evt.regType, tktId); }
		}
	}

	function createOrUpdateTicketOrder(passPurchase, regType, ticketId){
		let targetQty = getTotalTktsRequired(passPurchase, regType, tktId);
		let curOrder = purchasedTickets[passPurchase.id + '-' + regType + '-' + ticketId];
		if(curOrder){
			if(Number(curOrder.quantity) < targetQty){
				let rec = {"id":curOrder.extra_order_id,"quantity":targetQty};
				dataSvc.createOrUpdateRecord({"table":"registration_extra_orders","record":rec});
			}
		}else{
			let reg = existingRegMap[passPurchase.id + '-' + regType];
			let rec = {"registrationid":reg.id,"registration_extras_id":ticketId,"quantity":targetQty};
			dataSvc.createOrUpdateRecord({"table":"registration_extra_orders","record":rec});
		}
	}

	//for a given order, get total tickets due based on regType/ticket combination	
	function getTotalTktsRequired(passPurchase, regType, ticketId){
		let total = 0;
		passPurchase.order_details.forEach(function(pass){
			let eventDetails = $scope.passes[pass.passId].event_details;
			for(eventId in eventDetails){
				let details = eventDetails[eventId];
				if(!details.regType == regType) continue;
				for(tktId in details.tickets){
					if(tktId == ticketId) total += details.tickets[tktId] * pass.quantity;
				}
			}
		});
		return total;
	}

	function createRegistration(passPurchase, eventid, eventDetails){
		let done = $q.defer();
		let reg = {
			"attendeeid":passPurchase.attendeeid, 
			"payment_number": passPurchase.ref_nbr,
			"registration_typeid": eventDetails.regType,
			"first_name":passPurchase.first_name,
			"last_name":passPurchase.last_name,
			"email":passPurchase.email,
			"season_pass_ordersid": passPurchase.id
		};
		dataSvc.createOrUpdateRegistration(reg, eventid).then(function(res){
			reg.id = res;
			existingRegMap[passPurchase.id + '-' + eventDetails.regType] = reg;
			done.resolve();
		});
		return done.promise;
	}
});
