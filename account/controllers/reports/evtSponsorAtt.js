regApp.controller('evtSponsorAtt', function($scope, $http, $q, dataSvc, erSvc) {
	let accountid = erSessionData.accountid;
	$scope.eventFilter = 'future';
	let orders = [];
	let staffAtt = [];

	var sponsorsRetrieved = $q.defer();
	var ordersRetrieved = $q.defer();
	var staffRetrieved = $q.defer();
	$scope.sponsors = {};

	dataSvc.getTableRecords('sponsors', `accountid = ${accountid}`).then(function(res){
		res.forEach(function(sponsor){
			sponsor.archived = sponsor.archived == '1';
			sponsor.show = !sponsor.archived;
			$scope.sponsors[sponsor.id] = sponsor;
			$scope.sponsors[sponsor.id].orders = {};
		});
		sponsorsRetrieved.resolve();
	});

	dataSvc.getArray({"query":"accountEvents"}).then(function(events){
		$scope.events = events;
		$scope.filterEvents();
	});

	dataSvc.getArray({"query":"eventSponsorAttendance"}).then(function(sponsorships){
		orders = sponsorships;
		orders.forEach(order => order.staff = []);
		ordersRetrieved.resolve();
	});

	dataSvc.getArray({"query":"eventSponsorStaffAttendance"}).then(function(registrations){
		staffAtt = registrations;
		staffRetrieved.resolve();
	});

	$q.all([sponsorsRetrieved.promise, ordersRetrieved.promise, staffRetrieved.promise]).then(function(){
		orders.forEach(function(order){
			if($scope.sponsors[order.sponsorid]){
				$scope.sponsors[order.sponsorid].orders[order.eventid] = order;
			}
		});
		staffAtt.forEach(s => $scope.sponsors[s.sponsorid].orders[s.eventid].staff.push(s));
		$('#loadingSpan').remove();
	});


	$scope.filterEvents = function(){
		$scope.filteredEvents = {};
		var filter = $scope.eventFilter;
		angular.forEach($scope.events,function(event){
			if(event.status == filter) $scope.filteredEvents[event.id] = event;
			else if(filter == 'all') $scope.filteredEvents[event.id] = event;
			else if(filter == 'upcomingAndRecent' && (event.status == 'future' || event.status == 'recent'))
				$scope.filteredEvents[event.id] = event;
			else if(filter == event.id)	$scope.filteredEvents[event.id] = event;
		});
		filterSponsorsForEvent();
	};

	function filterSponsorsForEvent(){
		let evt = $scope.eventFilter;
		angular.forEach($scope.sponsors,function(sponsor){
			sponsor.show = false;
			if(evt == 'all'|| evt == 'upcomingAndRecent' || evt =='future' || evt == 'recent'){
				sponsor.show = !sponsor.archived;
			}else{
				angular.forEach(sponsor.orders,function(ord){
					if(ord.eventid == evt) sponsor.show = true;
				});
			}
		});
	}

	$scope.filterSponsors = function(){
		let srchVal = $scope.quickSearchValue ? $scope.quickSearchValue.toLowerCase() : '';
		angular.forEach($scope.sponsors,function(sponsor){
			if(sponsor.archived) sponsor.show = false;
			else if(srchVal == '') sponsor.show = !sponsor.archived;
			else if(sponsor.name.toLowerCase().indexOf(srchVal) >= 0) sponsor.show = true;
			else if(sponsor.email.toLowerCase().indexOf(srchVal) >= 0) sponsor.show = true;
			else{
				let show = false;
				angular.forEach($scope.filteredEvents,function(evt){
					if(!sponsor.orders[evt.id]) return;
					if(sponsor.orders[evt.id].name.toLowerCase().indexOf(srchVal) >= 0) show = true;
				});
				sponsor.show = show;
			}
		});
	};

	$scope.createEmail = function(){
		$('.dialogRight').show(500);
		$scope.emailTo = 'both';
	}

	$scope.closeEmailDialog = function(){
		$('.dialogRight').hide(500);
		$scope.emailSubject = '';
		$scope.emailBody = '';
	};

	$scope.sendEmail = function(){
		let emails = [];
		angular.forEach($scope.sponsors,function(sponsor){
			if($scope.emailTo != 'select'){
				if(sponsor.show && hasCurrentRegistration(sponsor)){
					if($scope.emailTo == 'both' || $scope.emailTo == 'account'){
						if(emails.indexOf(sponsor.email) < 0) emails.push(sponsor.email);
					}
					if($scope.emailTo == 'both' || $scope.emailTo == 'staff'){
						angular.forEach(sponsor.orders,function(ord){
							if($scope.filteredEvents[ord.eventid]){
								angular.forEach(ord.staff,function(staff){
									if(emails.indexOf(staff.email) < 0) emails.push(staff.email);
								});
							}
						});
					}
				}
			}else{
				if(sponsor.sendEmail && emails.indexOf(sponsor.email) < 0) {
					emails.push(sponsor.email);
					sponsor.sendEmail = false;
				}
				$scope.getSponsorStaff(sponsor).forEach(function(stf){
					if(stf.sendEmail && !emails.includes(stf.email)){
						emails.push(stf.email);
						stf.sendEmail = false;
					} 
				});	
			}
		});

		let emailSentData = {
			"accountid": accountid,
			"author":erSessionData.userData.first_name + ' ' + erSessionData.userData.last_name,
			"subject": $scope.emailSubject,
			"message": $scope.emailBody,
			"recipients":emails.toString()
		};

		dataSvc.createOrUpdateRecord({
			"table":"emails_sent",
			"record":emailSentData
		});

		erSvc.sendEmail(emails.toString(), $scope.emailSubject, $scope.emailBody)
			.then($scope.closeEmailDialog);
	};

	$scope.getSponsorStaff = function(sponsor){
		let staff = [];
		let emailList = [];
		if(sponsor.show && hasCurrentRegistration(sponsor)){
			angular.forEach(sponsor.orders,function(ord){
				if($scope.filteredEvents[ord.eventid]){
					angular.forEach(ord.staff,function(eventStaff){
						if(!emailList.includes(eventStaff.email)){
							emailList.push(eventStaff.email);
							staff.push(eventStaff);
						} 
					});
				}
			});
		}
		return staff;
	}

	function hasCurrentRegistration(sponsor){
		let hasOrder = false;
		angular.forEach(sponsor.orders,function(order){
			if($scope.filteredEvents[order.eventid]) hasOrder = true;
		});
		return hasOrder;
	}
});// End Controller