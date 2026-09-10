regApp.controller('rptCourseHistory', function($scope, $http, dataSvc, erSvc) {
	$scope.sortField = 'name';
	$scope.sortReverse = false;
	$scope.courses = {};
	dataSvc.getArray({'query':'courseHistory'}).then(function(resp){
		var courseList = $scope.courses;
		angular.forEach(resp,function(courseRec){
			if(!courseList[courseRec.id]){
				if(courseRec.archived == 'true'){
					courseRec.archived = true;
				}else{
					courseRec.archived = false;
				}
				courseList[courseRec.id] = {
					"include":true,
					"name":courseRec.course,
					"totalSignups":parseFloat(courseRec.signupcount),
					"sessionCount":1,
					"aveAttendance": courseRec.signupcount,
					"archived":courseRec.archived,
					"sessions": [courseRec]
				}
			}else{
				var crs = courseList[courseRec.id];
				crs.totalSignups += parseFloat(courseRec.signupcount);
				crs.sessions.push(courseRec);
				crs.sessionCount++;
				crs.aveAttendance = crs.totalSignups / crs.sessions.length;
			}
		});
		$('#loadingSpan').remove();
		$scope.pageReady = true;
	});

	$scope.changeSort = function(field){
		if($scope.sortField == field){
			$scope.sortReverse = !$scope.sortReverse;
		}else{
			$scope.sortField = field;
			$scope.sortReverse = false;
		}
	};

	$scope.showSessions = function(course){
		$('#dialogDiv').show(500);
		$scope.selectedCourse = course;
	};

	$scope.hideSessions = function(){
		$('#dialogDiv').hide(500);
	};

	$scope.quickSearch = function(){
		var searchVal = $scope.quickSearchValue.toLowerCase();
		angular.forEach($scope.courses,function(course){
			course.include = searchVal == '' || course.name.toLowerCase().indexOf(searchVal) >= 0;
		});
	};
});// End Controller