var navApp = angular.module('navMod', ['easyRegDataModule', 'erSvc']);

navApp.controller('navController', function($rootScope, $scope, $http, dataSvc, erSvc) {
	$(document).on('acctChange',function(){
		getEvents();
		getLogo();
		getSponsorStatus();
	});

	if(!(erSessionData.accountLogo || '').includes(erSessionData.accountid)){
		getEvents();
		getLogo();
	}

	if(location.pathname.includes('learningCenter/')){
		setTimeout(function(){
			$http({"url": "/session_data.php","method": "GET"}).then(function(response){
				if(response.data.accountid == '1000') $scope.logoSrc = '/img/ERP-no-tag.png';
				$scope.showLogo = true;
			});
		}, 200);
	}else{
		$scope.showLogo = true;
	}

	if(erSessionData.accountEvents && ((erSessionData.accountid || 'none') == erSessionData.accountEventsFor)){
		$scope.events = erSessionData.accountEvents;
	}else{
		getEvents();
	}

	function getEvents(){
		dataSvc.getObject({'query':'accountEvents'}).then(function(resp){
			let acctData = resp;
			let sessionEvtData = {};
			for(evt in acctData){
				if(acctData[evt].archived == '1') continue;
				sessionEvtData[evt] = {
					"slug": (acctData[evt].slug || '').replace(/\"/g, ''),
					"name": (acctData[evt].name || '').replace(/\"/g, ''),
					"status": acctData[evt].status,
					"visible": acctData[evt].visible,
					"visible_to_staff": acctData[evt].visible_to_staff,
					"start_date": acctData[evt].startdate
				};
			}
			$http({
				"url": '/setUserData.php',
				"method": 'POST',
				"data": $.param({"accountEvents":sessionEvtData, "accountEventsFor":(erSessionData.accountid || "none")}),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
			});
			erSessionData.accountEvents = sessionEvtData;
			$scope.events = sessionEvtData;
		});
	}

	if(erSessionData.userData) $scope.user = erSessionData.userData;
	else getUserData();

	function getUserData(){
		if(!erSessionData.currentSponsorData){
			dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
				if(resp[0]){
					let userData = resp[0];
					for(prop in userData){
						if(typeof userData[prop] == 'string'){
							userData[prop] = userData[prop].replace(/\"/g, '');
						}
					}
					$http({
						"url": '/setUserData.php',
						"method": 'POST',
						"data": $.param({"userData":userData}),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
					});
					$scope.user = resp[0];
				}
			});
		}
	}

	if(erSessionData.accountLogo) $scope.logoSrc = erSessionData.accountLogo;
	else getLogo();

	function getLogo(){
		dataSvc.getArray({'query':'accountWebLogo'}).then(function(resp){
			$scope.logoSrc = '/img/logo.png';
			if(resp[0]){
				if(resp[0].web_logo){
					$scope.logoSrc = resp[0].web_logo;
					$http({
						"url": '/setUserData.php',
						"method": 'POST',
						"data": $.param({"accountLogo":resp[0].web_logo}),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
					});
				}
			}
			else{
				erSvc.getAccountIdFromURL();
			}
		});
	}

	$scope.sponsor = erSessionData.currentSponsorData || null;

	if(erSessionData.sponsors_enabled) $scope.sponsors_enabled = erSessionData.sponsors_enabled;
	else getSponsorStatus();

	function getSponsorStatus(){
		dataSvc.getArray({'query':'sponsors_enabled'}).then(function(resp){
			if(resp[0]){
				$scope.sponsors_enabled = resp[0].sponsors_enabled == '1';
				erSessionData.sponsors_enabled = $scope.sponsors_enabled;
				$http({
					"url": '/setUserData.php',
					"method": 'POST',
					"data": $.param({"sponsors_enabled":$scope.sponsors_enabled}),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
				});
			}
		});
	}

	$scope.requestUri = window.location.pathname;

	$scope.logout = function(){
		erSessionData = null;
		erUtils.logout(window.location.pathname);
	}

// ************************************  End Nav Controller  ************************************

}).controller('adminHeaderController',function($scope, $http, $rootScope, dataSvc, erSvc){
	var eventid = new URL(location).searchParams.get('eventid');
	let sessionEventId = '';

	if(erSessionData.curEvent) sessionEventId = erSessionData.curEvent.id;
	$scope.masterUser = erSessionData.master == '1';

	$rootScope.erMaster = erSessionData.erMaster == 'true';

	if(eventid  && eventid != sessionEventId){
		dataSvc.getArray({'query':'eventDataRaw','eventid':eventid}).then(function(resp){
			let evt = resp[0];
			if(!evt) return;
			delete evt.categories;
			angular.forEach(evt,function(prop, key){
				evt[key] = (prop || '').toString().replace(/\"/g,'');
			});
			$http({
				"url": '/setUserData.php',
				"method": 'POST',
				"data": $.param({"curEvent":evt}),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
			});
		});
	}

		dataSvc.getArray({'query':'accountEvents'}).then(r => {
			$scope.events = {"future":[],"recent":[]};
			let eventAccess = (erSessionData.eventAccess ||  '').split(',');
			r.forEach(evt => {
				if(evt.archived == '1') return;
				if(masterUser || erUtils.hasId(eventAccess, evt.id)){
					if(evt.status == 'future') $scope.events.future.push(evt);
					else if(evt.status == 'recent') $scope.events.recent.push(evt);
				} 
			}); 
		});

	$scope.changeEvent = function(){
		erSvc.loadingDialog();
		erSvc.setSelectedEvent($scope.selectedEvt).then(function(){
			//if there is an event id in the location, remove it.  Then reload page for new event
			let loc = window.location.href.split('/');
			let lastHash = loc[loc.length-1];
			if(!isNaN(lastHash)) window.location.href = window.location.href.replace('/' + lastHash,'');
			else window.location.href = window.location.href;
			window.location.reload();
		});
	};

	$scope.createTicket = function(){
		$scope.newTicket = { 
			"type":"feature", 
			"description":"",
			"accountid": erSessionData.accountid,
			"contact_userid": erSessionData.userData.id,
			"contact_name": erSessionData.userData.last_name + ', ' + erSessionData.userData.first_name ,
			"contact_email": erSessionData.userData.email,
			"contact_phone": erSessionData.userData.phone
		};
		$('#ticketDialog').show(500);
	};

	$scope.submitTicket = function(){
		erSvc.loadingDialog();
		dataSvc.createOrUpdateRecord({"table":"support_tickets","record":$scope.newTicket}).then(function(res){
			erSvc.closeLoading();
			let msg = `Thank you for your feedback.  Your 
				${$scope.newTicket.type == 'feature' ? ' feature request' : ' issue'} has been successfully submitted.`;
			erSvc.easyRegAlert({"text":msg,"title":"Ticket Submitted"});
			$scope.closeRightDialog();
		});
	};

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);
// ************************************  End Nav Sidebar Controller  ************************************

}).controller('eventSidebarController', function($rootScope, $scope, $q, $http, dataSvc, erSvc) {
	function resolveEventId(){
		let response = $q.defer();
		let eventid = $scope.eventData?.eventid || $scope.$parent?.eventData?.eventid;
		if(eventid){
			response.resolve(eventid);
			return response.promise;
		}

		let pathParts = window.location.pathname.split('/').filter(Boolean);
		let slugIndex = pathParts.indexOf('e');
		let slug = slugIndex >= 0 ? pathParts[slugIndex + 1] : '';
		if(!slug){
			response.resolve('');
			return response.promise;
		}

		dataSvc.getArray({"query":"getEventIdFromUrl","slug":slug}).then(function(res){
			if(res[0] && res[0].id) response.resolve(res[0].id);
			else response.resolve('');
		}, function(){
			response.resolve('');
		});
		return response.promise;
	}

	$scope.login = function(){
		$scope.invalidRegNum = false;
		resolveEventId().then(function(eventid){
			if(!eventid){
				$scope.invalidRegNum = true;
				$scope.$applyAsync();
				return;
			}
			erSvc.attendeeLogin($scope.loginConfirmation, eventid).then(function(res){
				$scope.invalidRegNum = !res;
				if(res){
					$scope.sessionData.has_extras = res.has_extras;
					$scope.$parent.confirmation_number = $scope.loginConfirmation;
					if($scope.$parent.lookupRegistration) $scope.$parent.lookupRegistration();
					if(res.registrationid) getUserData();
				}
				$scope.$applyAsync();
			});
			$scope.$applyAsync();
		});
	};
	$rootScope.loginUser = function(confirmation){
		$scope.loginConfirmation = confirmation;
		$scope.login();
	}
	$scope.logout = function(){
		erSvc.loadingDialog();
		$http({
			"url": "/forget_confirmation.php",
			"method": "POST",
			"data": $.param(erUtils.withCsrf({})),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		}).then(function(res){
			window.location.href = '/e/' + $scope.eventData.slug;
		});
	};
	function getUserData(){
		erSvc.getUserRoles().then(function(resp){
			$scope.sessionData = resp;
			$scope.showLogin = !$scope.sessionData.confirmation;
			$scope.$applyAsync();
		});
	}
	getUserData();

	if(erSessionData.accountid){
		let filter = "name = 'attendeeMenu' AND accountid = " + erSessionData.accountid;
		dataSvc.getTableRecords('preferences', filter).then(function(res){
			try{ 
				$scope.menuSettings = angular.fromJson(res[0].value); 
				prefId = res[0].id;
				$scope.$applyAsync();
			}catch(e){}
		});
	}

	$scope.getLabel = function(lbl){
		if(!$scope.menuSettings || !$scope.menuSettings[lbl]) return lbl;
		else return $scope.menuSettings[lbl].label;
	};

	$scope.eventHasHome = function(){
		if(!$scope.eventData) return false;
		if(!$scope.eventData.pages) return false;
		return $scope.eventData.pages.filter(p => p.home).length;
	};

	$scope.showItem = function(item){
		if(item == 'Event Tickets' && !$scope.sessionData.has_extras) return false;
		if(!$scope.menuSettings || !$scope.menuSettings[item]) return true;
		else return !$scope.menuSettings[item].disabled;
	};
	

// ************************************  End Event Sidebar Controller  ************************************

}).filter('upcomingEvents', function(){
	return function(events, scope){
		var filtered = [];
		angular.forEach(events,function(event){
			if(event.status == 'future' && (event.visible == '1' || (scope.user && event.visible_to_staff == '1'))){
				filtered.push(event);
			}
		});
		filtered.sort(function(a,b){
			return a.startdate > b.startdate ? 1 : -1;
		});
		return filtered;
	}
}).filter('pastEvents', function(){
	return function(events, scope){
		var filtered = [];
		angular.forEach(events,function(event){
			if((event.status == 'past' || event.status=='recent') && (event.visible == '1' || (scope.user && event.visible_to_staff == '1'))){
				filtered.push(event);
			}
		});
		filtered.sort(function(a,b){
			return a.startdate > b.startdate ? -1 : 1;
		});
		return filtered;
	}
});
