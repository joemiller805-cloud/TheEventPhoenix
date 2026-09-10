regApp.controller('usersRpt', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.sortField = 'last_name';
	$scope.sortReverse = false;
	$scope.users = {};
	$scope.courseSearchPref = 'both';
	$scope.selectedCourse = '';
	var usersRetrieved = $q.defer();
	var coursesRetrieved = $q.defer();

	dataSvc.getObject({'query':'getUsers'}).then(function(users){
		$scope.users = users;
		angular.forEach($scope.users,function(user){
			$.each(user, function(key, value){
				if(!value) user[key] = '';
			});
			user.master = user.master || '0';
			user.archived = user.archived || '0';
			user.include = user.archived != '1';
			user.courses_preferred = user.courses_preferred.split(',');
			user.courses_able = user.courses_able.split(',');
		});
		usersRetrieved.resolve();
		$('#loadingSpan').remove();
		erSvc.initializeColumns($('#userTable'));
	});

	$scope.courses = {};
	dataSvc.getArray({'query':'courseList'}).then(function(response){
		angular.forEach(response,function(course){
			if(course.archived != '1') $scope.courses[course.id] = course;
		});
		coursesRetrieved.resolve();
	});

	$q.all([usersRetrieved.promise, coursesRetrieved.promise]).then(function(){
		var courseList;
		angular.forEach($scope.users,function(user){
			courseList = [];
			angular.forEach(user.courses_preferred,function(course){
				if(course){
					if($scope.courses[course]) courseList.push($scope.courses[course].name);
				}
			});
			user.courses_preferred_list = courseList.toString();
			courseList = [];
			angular.forEach(user.courses_able,function(course){
				if(course){
					if($scope.courses[course]) courseList.push($scope.courses[course].name);
				}
			});
			user.courses_able_list = courseList.toString();
			user.searchString = JSON.stringify(user).toLowerCase();
		});
	});

	$scope.changeSort = function(field){
		if($scope.sortField == field){
			$scope.sortReverse = !$scope.sortReverse;
		}else{
			$scope.sortField = field;
			$scope.sortReverse = false;
		}
	};

	$scope.quickSearch = function(){
		var include;
		var compareValue = $scope.quickSearchValue ? $scope.quickSearchValue.toLowerCase() : '';
		var curUser;
		angular.forEach($scope.users,function(user){
			user.include = (
				user.searchString.indexOf(compareValue) >=0 || !compareValue
			) &&
			(user.archived != '1' || $scope.includeArchived);
		});
		erSvc.initializeColumns($('#userTable'));
	};

	$scope.courseSearch = function(){
		$scope.quickSearchValue = '';
		angular.forEach($scope.users,function(user){
			if($scope.selectedCourse == ''){
				user.include = true;
			}else if($scope.courseSearchPref == 'both'){
				user.include = user.courses_preferred.indexOf($scope.selectedCourse) >= 0 ||
					user.courses_able.indexOf($scope.selectedCourse) >= 0;
			}else if($scope.courseSearchPref == 'preferred'){
				user.include = user.courses_preferred.indexOf($scope.selectedCourse) >= 0
			}
			else{
				user.include = user.courses_able.indexOf($scope.selectedCourse) >= 0
			}
		});
	};

	$scope.columnFilter = () =>	erSvc.columnFilter($('#userTable'));
});// End Controller