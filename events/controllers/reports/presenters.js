regApp.controller('rptPresenters', function($scope, $http, dataSvc, erSvc, $filter, $timeout) {
	erSvc.loadingDialog();
	var presentersRetrieved = $.Deferred();
	var signupsRetrieved = $.Deferred();
	var eventid = erSessionData.curEvent.id;
	$scope.sortField = 'last_name';

	$scope.courseFilter = ['all'];
	$scope.presenterFilter = ['all'];
	$scope.sessionFilter = ['all'];
	$scope.presenters = [];
	$scope.courses = [];
	$scope.sessions = [];

	dataSvc.getArray({"query":"eventPresenters","eventid":eventid}).then(function(resp){
		$scope.sections = resp;
		angular.forEach($scope.sections,function(sec){
			sec.include = true;
			sec.showAttendees = false;
			sec.last_name = sec.last_name || '';
			sec.attendees = [];
			sec.roomsort = Number(sec.roomsort);
			if($scope.sessions.indexOf(sec.session) < 0) $scope.sessions.push(sec.session);
			if($scope.presenters.indexOf(sec.last_name + ', ' + sec.first_name) < 0) $scope.presenters.push(sec.last_name + ', ' + sec.first_name);
			if($scope.courses.indexOf(sec.course) < 0) $scope.courses.push(sec.course);
		});
		presentersRetrieved.resolve();
	});

	var signups;
	dataSvc.getArray({"query":"sectionSignups","eventid":eventid}).then(function(resp){
		signups = resp;
		signupsRetrieved.resolve();
	});

	$.when(presentersRetrieved, signupsRetrieved).then(function(){
		angular.forEach(signups,function(signup){
			angular.forEach($scope.sections,function(section){
				if(section.sectionid == signup.sectionid){
					section.attendees.push({
						"name":signup.last_name + ', ' + signup.first_name,
						"email":signup.email
					});
				}
			});
		});
		erSvc.closeLoading();
		erSvc.initializeColumns($('#presenterTable'));
	});

	$scope.quickSearchValue = '';
	$scope.search = function(){
		let compareValue = $scope.quickSearchValue.toLowerCase();
		$scope.sections.forEach(function(sec){
			if($scope.courseFilter[0] != 'all'){
				if($scope.courseFilter.indexOf(sec.course) < 0){
					sec.include = false;
					return;
				}
			}
			if($scope.presenterFilter[0] != 'all'){
				if($scope.presenterFilter.indexOf(sec.last_name + ', ' + sec.first_name) < 0){
					sec.include = false;
					return;
				}
			}
			if($scope.sessionFilter[0] != 'all'){
				if($scope.sessionFilter.indexOf(sec.session) < 0){
					sec.include = false;
					return;
				}
			}
			let secTxt = sec.last_name + sec.first_name + sec.session + sec.date + sec.starttime + sec.endtime + sec.room + sec.course + sec.description;
			sec.include = secTxt.toLowerCase().indexOf(compareValue) >= 0;
		});
		$timeout(function(){
			erSvc.initializeColumns($('#presenterTable'));
		},1);
	}

	$scope.columnFilter = function(){
		erSvc.columnFilter($('#presenterTable'));
	};
});// End Controller