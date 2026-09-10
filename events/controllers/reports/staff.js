regApp.controller('rptStaff', function($scope, $http, $q, dataSvc, erSvc, $filter, $timeout) {
	erSvc.loadingDialog();
	var eventid = erSessionData.curEvent.id;
	$scope.staff = {};
	var staffRetrieved = $q.defer();
	var coursesRetrieved = $q.defer();
	
	dataSvc.getArray({'query':'eventUserData','eventid':eventid}).then(function(resp){
		angular.forEach(resp, function(staff){
			staff.include = true;
			let existing = $scope.staff[staff.user_id];
			if(existing){
				for(prop in staff){
					existing[prop] = existing[prop] || staff[prop];
				}
			}
			
			if(staff.request_denied != '1'  && !$scope.staff[staff.user_id]){
				$scope.staff[staff.user_id] = staff;
			}
		});
		staffRetrieved.resolve();
		erSvc.closeLoading();
		erSvc.initializeColumns($('#staffTable'));
	});

	let courses;
	dataSvc.getObject({'query':'courseList'}).then(function(resp){
		courses = resp;
		coursesRetrieved.resolve();
	});
	$q.all([staffRetrieved.promise,coursesRetrieved.promise]).then(function(){
		angular.forEach($scope.staff,function(staff){
			staff.courses_able = (staff.courses_able || '').split(',').map(c => courses[c]?.name);
			staff.courses_preferred = (staff.courses_preferred || '').split(',').map(c => courses[c]?.name);
			staff.searchString = erSvc.getObjectSearchString(staff);
		});
	});

	$scope.quickSearch = function(){
		var compareValue = $scope.quickSearchValue.toLowerCase();
		angular.forEach($scope.staff,function(staff){
			staff.include = staff.searchString.indexOf(compareValue) >= 0;
		});
		$timeout(function(){
			erSvc.initializeColumns($('#staffTable'))
		},1);
	}

	$scope.columnFilter = function(){
		erSvc.columnFilter($('#staffTable'));
	};
});// End Controller