regApp.controller('evtMgmt', function($scope, $http, $rootScope, $location, $q, dataSvc, erSvc){
	$scope.events = [];
	dataSvc.getArray({'query':'accountEvents'}).then(r => {
		let eventAccess = (erSessionData.eventAccess ||  '').split(',');
		r.forEach(evt => {
			if(masterUser || erUtils.hasId(eventAccess, evt.id)) $scope.events.push(evt)
		}); 
	});
	$scope.archiveFilter = '0';

	$scope.setEvent = event => erSvc.setSelectedEvent(event.id);

	$scope.modAccess = false;
	erSvc.checkAccess().then(r => $scope.modAccess = r);

	let usedSlugs;
	dataSvc.getArray({'query':'usedSlugs'}).then(slugs => usedSlugs = slugs.map(s => s.slug));

	let dateFields = ['startdate','enddate','registrationstartdate','registrationenddate'];
	let regFields;
	let regFieldExclusions;
	$scope.regTypes;
	$scope.tickets;
	$scope.copyEvent = function(evt){
		$scope.originalEvent = evt;
		$scope.newEvent = angular.copy(evt);
		$scope.newEvent.includeTix = true;
		$scope.newEvent.includeRegTypes = true;
		$scope.newEvent.id = '';
		$scope.newEvent.registrationCount = '0';
		dateFields.forEach(function(fld){
			$scope.newEvent[fld] = erSvc.mySqlToLocalDate($scope.newEvent[fld]);
		});
		$('#copyEventDialog').show(500);
		let getTblRecs = dataSvc.getTableRecords;
		getTblRecs('registration_field_exclusions',`eventid = ${evt.id}`).then(r => regFieldExclusions = r);
		getTblRecs('registration_fields',`eventid = ${evt.id}`).then(r => regFields = r);
		getTblRecs('registration_types',`eventid = ${evt.id}`).then(r => $scope.regTypes = r);
		getTblRecs('registration_extras',`eventid = ${evt.id}`).then(r => $scope.tickets = r);
	};

	//map of old reg type ids to new reg type ids
	let regTypeMap = {};
	$scope.saveCopy = function(){
		//check for reused slug
		if(usedSlugs.includes($scope.newEvent.slug)){
			erSvc.easyRegAlert({"text":"This slug is already in use. Please use another","title":"Slug in use."});
			return;
		}

		erSvc.loadingDialog('Copying Event');
		//create event
		dataSvc.createOrUpdateRecord({"table":"events","record":$scope.newEvent}).then(function(eventid){
			$scope.newEvent.id = eventid;
			dateFields.forEach(function(fld){
				$scope.newEvent[fld] = erSvc.localToMySqlDate($scope.newEvent[fld]);
			});
			$scope.events.push({...$scope.newEvent});

			//create registration types
			if($scope.newEvent.includeRegTypes){
				//if this is a ticketing account, we'll automatically create a single reg type
				if($scope.acctType == 'ticketing'){
					let rt = {
						"eventid":eventid,
						"name":"Tickets",
						"price":"0",
						"sortorder":"0",
						"vendor_reg":'0',
						"sunrise":$scope.newEvent.registrationstartdate,
						"sunset":$scope.newEvent.registrationenddate
					}
					dataSvc.createOrUpdateRecord({"table":"registration_types","record":rt}).then(function(id){
						regTypeMap[$scope.regTypes[0].id] = id;
						createTickets(eventid);
					});
				}else{
					let createdTypes = 0;
					$scope.regTypes.forEach(function(rt){
						let regTypeId = rt.id; 
						rt.eventid = eventid;
						delete rt.id;
						dataSvc.createOrUpdateRecord({"table":"registration_types","record":rt}).then(function(id){
							regTypeMap[regTypeId] = id;
							if(rt.vendor_reg == '1'){
								let rec = angular.copy(rt);
								rec.registration_type_id = id;
								rec.type = 'event vendor';
								dataSvc.createOrUpdateRecord({"table":"event_sponsor_options","record":rec})
								.then(function(r){
									if(++createdTypes == $scope.regTypes.length) createTickets(eventid);
								});
							}else{
								if(++createdTypes == $scope.regTypes.length) createTickets(eventid);
							}
						});
					});
				}
			}else{
				setTimeout(erSvc.closeLoading, 2000);
			}

			//create registration field exclusions
			regFieldExclusions.forEach(function(fld){
				delete fld.id;
				fld.eventid = eventid;
				dataSvc.createOrUpdateRecord({"table":"registration_field_exclusions","record":fld});
			});

			//create event reg fields
			regFields.forEach(function(fld){
				delete fld.id;
				fld.eventid = eventid;
				dataSvc.createOrUpdateRecord({"table":"registration_fields","record":fld});
			});
		});
	};// end saveCopy()

	$scope.chooseSetAll = function(idx, type, field, label){
		if(idx != 0 || $scope[type].length == 1) return;
		let value = $scope[type][0][field];
		if(value.length < 10) return;
		let title = "Update All";
		let txt = `Set all ${label} dates to this date?`;
		erSvc.easyRegConfirm({"text":txt,"title":title},"Yes","No").then(function(res){
			if(!res) return;
			$scope[type].forEach(function(element){
				element[field] = $scope[type][0][field];
			});
		});
	};

	function createTickets(eventid){
		let ticketsCreated = 0;
		$scope.tickets.forEach(function(ticket){
			delete ticket.id;
			ticket.eventid = eventid;
			//update ticket reg types to new reg type ids
			let ticketRegs = ticket.registration_types.split(',');
			let newTypes = [];
			ticketRegs.forEach(function(rt){
				if(regTypeMap[rt]) newTypes.push(regTypeMap[rt]);
			});
			ticket.registration_types = newTypes.join(',');
			dataSvc.createOrUpdateRecord({"table":"registration_extras","record":ticket}).then(function(){
				if(++ticketsCreated == $scope.tickets.length){
					erSvc.closeLoading();
					$scope.closeRightDialog();
				}
			});
		});
		if(!$scope.tickets.length){
			erSvc.closeLoading();
			$scope.closeRightDialog();
		} 
	}

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);
});
