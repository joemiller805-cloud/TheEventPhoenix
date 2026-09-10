regApp.controller('eventDetails', function($scope, $http, $rootScope, $routeParams, $location, dataSvc, erSvc) {
	erSvc.setSelectedEvent($routeParams.id);
	$('.list-group-item.active').removeClass('active');
	$('.list-group-item[href="#!event_details"]').addClass('active');
	$scope.userid = erSessionData.userid;
	$scope.accountid = erSessionData.useraccount;
	$scope.trial = erSessionData.trial == '1';
	$scope.eventId = $routeParams?.id || erSessionData.curEvent?.id;

	$scope.registrationTypes = [];

	dataSvc.getArray({'query':'ccProvider'}).then(resp => { 
		$scope.ccEnabled = resp[0].ccProvider != 'none';	
	});

	dataSvc.getArray({'query':'ccChargeRt'}).then(resp => { 
		if(!resp[0]) $scope.ccChargeRt = 0;
		else $scope.ccChargeRt = Number(resp[0].cc_charge_rt || '0');
	});

	$scope.categories = [];
	dataSvc.getPreferenceByName('eventCategories', accountid).then(function(res){
		if(res[0]){
			prefEntry = res[0];
			try{ $scope.categories = JSON.parse(prefEntry.value);}
			catch(e){ console.error(e); $scope.categories = []; }
		}
	});

	$scope.changeCat = function(cat){
		$scope.eventData.categories = $scope.eventData.categories || [];
		let cats = $scope.eventData.categories;
		if(cats.includes(cat)) cats.splice(cats.indexOf(cat),1);
		else cats.push(cat);
	};

	//this is the landing page for non-master users when they land in an event
	//need to check access before collecting data, may be redirected
	erSvc.checkAccess().then(function(res){
		if(!res) return;
		dataSvc.getTableRecords('account_reg_types', 'accountid = ' + accountid, false).then(function(res){
			$scope.registrationTypes = res;
		});
		if($scope.eventId > 0){
			$rootScope.curEventName = '';
			dataSvc.getEventDataFromId($scope.eventId).then(function(response){
				$scope.eventData = response;
				$rootScope.curEventName = response.name;
				$scope.eventData.categories = $scope.eventData.categories ? angular.fromJson($scope.eventData.categories) : [];
				$scope.updateSelectedLink();
				if($scope.eventData.startdate.indexOf('0000') >= 0) $scope.datesTbd = true;
				getSlugs();
			});
		}else{
			getSlugs();
		}
	});

	dataSvc.getRegistrations($scope.eventId).then(function(r){
		$scope.hasRegistrations = Object.keys(r).length > 0;
	});

	let evt = erSessionData.curEvent;
	if(!evt || (evt && evt.id != $scope.eventId)){
		if($scope.eventId != '-1') erSvc.setSelectedEvent($scope.eventId);
	}

	if($scope.eventId == '-1') erSessionData.curEvent = $rootScope.curEventName = "";

	dataSvc.getImageList('img/account' + $scope.accountid).then(resp => $scope.images = resp);

	$('.dtPicker').datepicker();
	//initialize blank event object for new events
	$scope.eventData = {
		"accountid": $scope.accountid,
		"allowsignups": "0",
		"blurb": "",
		"certificatemessage": "",
		"checkindate": "",
		"checkinstructions": "",
		"checkoutdate":"",
		"city": "",
		"enddate": "",
		"req_cc_pymt": "0",
		"apply_cc_fee": "0",
		"kioskcertificate": "0",
		"kioskinvoice": "0",
		"kiosksessionschedule": "0",
		"location": "",
		"logo": "",
		"name": "",
		"prefix": "",
		"pymt_cc_msg": "",
		"pymt_chk_msg": "",
		"pymt_po_msg": "",
		"registration_message": "",
		"registrationenddate": "",
		"registrationstartdate": "",
		"replytoemail": "",
		"requireponumber": "0",
		"showschedule": "",
		"showdocumentation": "",
		"site": "",
		"slug": "",
		"startdate": "",
		"state": "",
		"timezone": "",
		"visible": "",
		"visible_to_staff":"",
		"visible_to_sponsors":"",
		"capacity":"",
		"categories":""
	};

	if($scope.acctType == 'ticketing') $scope.eventData.capacity = '9999999';

	$scope.states = erSvc.getStateOptions();
	$scope.timezones = erSvc.getTimezoneOptions();

	function getSlugs(){
		dataSvc.getArray({'query':'usedSlugs'}).then(function(slugs){
			$scope.slugs = slugs.map(function(elem){
				//build array of strings, but exclude current slug if updating event
				return elem.slug == $scope.eventData.slug ? '' : elem.slug;
			});
		});
	}

	$scope.nameWatcher = function(){
		if($scope.eventId < 0) $scope.eventData.slug = string_to_slug($scope.eventData.name);
	};

	$scope.checkCapacity = function(){
		if($scope.trial && Number($scope.eventData.capacity) > 5){
			$scope.eventData.capacity = 5;
			erSvc.easyRegAlert({"text":"Max capacity of 5 for trial accounts","title":"Max Capacity of 5"});
		}
	};

	$scope.saveEvent = function(){
		let newEvent = !$scope.eventData.id;
		let hasRegTypes = $scope.registrationTypes.length > 0;
		if(newEvent) prepRegTypes();
		if(!$scope.eventForm.$valid) return;
		erSvc.loadingDialog();
		if($scope.slugs.indexOf($scope.eventData.slug) < 0){
			let evtCopy = angular.copy($scope.eventData)
			evtCopy.categories = angular.toJson(evtCopy.categories);
			dataSvc.createOrUpdateRecord({"table":"events","record":evtCopy},$scope.eventData.id).then(function(response){
				if(response != '0') erSvc.setSelectedEvent(response);
				if(response > "0"){
					$scope.eventData.id = response;
					$http({
						"url": '/setUserData.php',
						"method": 'POST',
						"data": $.param({"curEvent":evtCopy}),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
					}).then(function(res){
						erSessionData.curEvent = evtCopy;
						$rootScope.curEventName = $scope.eventData.name;
					});
					if(hasRegTypes && $scope.acctType != 'ticketing'){
						$scope.showRegTypes = true;
						$("html, body").animate({ scrollTop: 0 }, "slow");
					}else if($scope.acctType != 'ticketing'){
						$('#updateSavedAlert').fadeIn(500).delay(3000).fadeOut();
						erSvc.closeLoading();
					}
					//if this is a ticketing account, we'll automatically create a single reg type
					if($scope.acctType == 'ticketing'){
						let regType = {
							"eventid":$scope.eventData.id,
							"name":"Tickets",
							"price":"0",
							"sortorder":"0",
							"vendor_reg":'0',
							"sunrise":$scope.eventData.registrationstartdate,
							"sunset":$scope.eventData.registrationenddate
						}
						dataSvc.createOrUpdateRecord({"table":"registration_types","record":regType}).then(function(){
							erSvc.closeLoading();
							$('#updateSavedAlert').fadeIn(500).delay(3000).fadeOut();
						});
					}
				}else if($scope.acctType == 'ticketing'){
					dataSvc.getTableRecords('registration_types', `eventid = ${$scope.eventData.id}`).then(function(res){
						res.forEach(function(rt){
							rt.sunrise = $scope.eventData.registrationstartdate;
							rt.sunset = $scope.eventData.registrationenddate;
							dataSvc.createOrUpdateRecord({"table":"registration_types","record":rt});
						});
					});
				}
				if(!newEvent || !hasRegTypes) $('#updateSavedAlert').fadeIn(500).delay(3000).fadeOut();
				if($scope.acctType != 'ticketing' || response == '0') erSvc.closeLoading();
			});
		}else{
			erSvc.closeLoading();
			erSvc.easyRegAlert({"title":"Slug Already In Use","text":"Please enter a different Event Slug value."});
		}
	};

	$scope.saveRegTypes = function(){
		let savedTotal = 0;
		$scope.registrationTypes.forEach(function(type){
			delete type.id;
			type.eventid = $scope.eventData.id;
			dataSvc.createOrUpdateRecord({"table":"registration_types","record":type}).then(function(res){
				if(type.vendor_reg == '1'){
					let rec = angular.copy(type);
					rec.registration_type_id = res;
					rec.type = 'event vendor';
					dataSvc.createOrUpdateRecord({"table":"event_sponsor_options","record":rec}).then(function(r){
						if(++savedTotal == $scope.registrationTypes.length) finished();
					});
				}else{
					if(++savedTotal == $scope.registrationTypes.length) finished();
				}
			});
		});
	};

	function finished(){
		erSvc.easyRegAlert({"text":"Registration Types Saved","title":"Success"});
		$scope.setupComplete = true;
	}

	$scope.removeRegType = function(type){
		$scope.registrationTypes.splice($scope.registrationTypes.indexOf(type),1);
	};

	$scope.hasVendorReg = function(){
		return $scope.registrationTypes.some(t => t.vendor_reg == '1');
	};

	function prepRegTypes(){
		let start = $scope.eventData.startdate;
		if(!start) return;
		$scope.registrationTypes.forEach(function(type){
			type.sunrise = getRegDate(start, type.sunrise_days, 'before');
			type.sunset = getRegDate(start, type.sunset_days, type.sunset_before_after);			
			if($scope.eventData.has_virtual == '1'){
				type.video_start_dt = getRegDate(start, type.video_start_days, type.video_start_before_after);
				type.video_end_dt = getRegDate(start, type.video_end_days, type.video_end_before_after);
			}
		});
	}

	function getRegDate(benchmark, days, when){
		return erSvc.addDays(benchmark, when == 'before' ? Number(days) * -1 : days);
	}

	$scope.deleteEvent = function(){
		dataSvc.deleteRecord({"table":"events","id":$scope.eventData.id})
		.then(function(res){
			$location.path('event_management');
			$scope.$applyAsync();
		});
	};

	$scope.setLogo = image => $scope.eventData.logo = image.path;

	$scope.checkEvtDates = function(){
		if($scope.datesTbd){
			$scope.eventData.startdate = '';
			$scope.eventData.enddate = '';
		}
	};

	$scope.copyEvtUrl = function(){
		let evtUrl = `${window.location.origin}/e/${$scope.accountid}/${$scope.eventData.slug}`;
		navigator.clipboard.writeText(evtUrl);
		erSvc.easyRegAlert({"text":`Event URL - ${evtUrl} - has been copied to the clipboard.`,"title":"Event URL Copied"});
	};

	$scope.checkSlug = () => $scope.eventData.slug = ($scope.eventData.slug || '').trim().replaceAll(' ','-');
});//end controller
