regApp.controller('rptEvtUserAttendance', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.sortField = 'last_name';
	$scope.sortReverse = false;
	$scope.users = {};
	$scope.courseSearchPref = 'both';
	$scope.selectedCourse = '';
	$scope.eventFilter = 'future';
	$scope.includeAdmin = true;
	$scope.includePresenters = true;
	$scope.includeStaff = true;
	var usersRetrieved = $q.defer();
	var coursesRetrieved = $q.defer();
	var rolesRetrieved = $q.defer();

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
			user.hasSecurityGrp = !!user.security_groups;
			user.eventRoles = {};
		});
		usersRetrieved.resolve();
		$('#loadingSpan').remove();
	});

	$scope.courses = {};
	dataSvc.getArray({'query':'courseList'}).then(function(response){
		angular.forEach(response,function(course){
			if(course.archived != '1') $scope.courses[course.id] = course;
		});
		coursesRetrieved.resolve();
	});

	dataSvc.getArray({"query":"accountEvents"}).then(function(events){
		$scope.events = events;
		$scope.filterEvents();
	});

	dataSvc.getArray({"query":"account_user_events"}).then(function(roles){
		$scope.roles = roles;
		rolesRetrieved.resolve();
	});

	$q.all([usersRetrieved.promise, coursesRetrieved.promise, rolesRetrieved.promise]).then(function(){
		var courseList;
		angular.forEach($scope.users,function(user){
			courseList = [];
			angular.forEach(user.courses_preferred,function(course){
				if(course && $scope.courses[course]) courseList.push($scope.courses[course].name);
			});
			user.courses_preferred_list = courseList.toString();
			courseList = [];
			angular.forEach(user.courses_able,function(course){
				if(course && $scope.courses[course]) courseList.push($scope.courses[course].name);
			});
			user.courses_able_list = courseList.toString();
		});

		//set user event roles
		angular.forEach($scope.roles,function(role){
			let user = $scope.users[role.userid];
			if(user){
				if(!user.eventRoles[role.eventid]) user.eventRoles[role.eventid] = [];
				let evtRoles = user.eventRoles[role.eventid];
				if(role.presenter == '1' || user.presenter == '1') evtRoles.push("Presenter");
				if(user.hasSecurityGrp) evtRoles.push("Admin");
				if(!user.hasSecurityGrp && role.presenter != '1') evtRoles.push('Staff');
			}
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

	$scope.userSearch = function(){
		var courseMatch, roleMatch, quickSearchMatch, evtMatch, roles;
		angular.forEach($scope.users,function(user){
			courseMatch = false;
			roleMatch = false;
			evtMatch = false;

			//search for match on selected course
			if($scope.selectedCourse == '')	courseMatch = true;
			else if($scope.courseSearchPref == 'both'){
				courseMatch = user.courses_preferred.indexOf($scope.selectedCourse) >= 0 ||
					user.courses_able.indexOf($scope.selectedCourse) >= 0;
			}else if($scope.courseSearchPref == 'preferred'){
				courseMatch = user.courses_preferred.indexOf($scope.selectedCourse) >= 0
			}
			else{
				courseMatch = user.courses_able.indexOf($scope.selectedCourse) >= 0
			}

			//search for match on selected role/event(s)
			angular.forEach($scope.filteredEvents,function(event){
				if($scope.includeAdmin && $scope.includePresenters && $scope.includeStaff){
					roleMatch = true;
					return;
				}
				roles = user.eventRoles[event.id];
				if(roles){
					if(roles.indexOf('Admin') >=0 && $scope.includeAdmin) roleMatch = true;
					if(roles.indexOf('Presenter') >=0 && $scope.includePresenters ) roleMatch = true;
					if(roles.indexOf('Staff') >=0 && $scope.includeStaff ) roleMatch = true;
				}
			});

			//search for match on quick search
			var compareValue = $scope.quickSearchValue ? $scope.quickSearchValue.toLowerCase() : '';
			if(compareValue == '') quickSearchMatch = true;
			else{
				$('.userRow').each(function(){
					quickSearchMatch = (
						$('.userRow[data-id="' + user.id + '"]').text().toLowerCase().indexOf(compareValue) >=0 || !compareValue
					) &&
					(user.archived != '1' || $scope.includeArchived);
				});
			}

			let evt = $scope.eventFilter;

			if(evt == 'all'|| evt == 'upcomingAndRecent' || evt =='future' || evt == 'recent'){
				eventMatch = true;
			}else{
				eventMatch = user.eventRoles[evt];
			}
			user.include = courseMatch && roleMatch && quickSearchMatch && eventMatch;
		});//end user loop
	};

	$scope.replytoemails = ['postmaster@easyregpro.com'];
	dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].email);
	});

	$scope.emailRecipients = 'all';
	$scope.emailBody = '';
	$scope.startEmail = () => $('#emailDialog').show(500);
	$scope.closeEmailDialog = () => { $('#emailDialog').hide(500); erSvc.closeLoading(); }

	$scope.setIncludeEmail = function(include){
		angular.forEach($scope.users,(user) => user.includeInEmail = include);
	};

	$scope.sendEmail = function(){
		erSvc.loadingDialog();
		let recipientList = [];
		angular.forEach($scope.users,function(user){
			if(user.include && (user.includeInEmail || $scope.emailRecipients == 'all'))
				recipientList.push(user.email);
		});
		var body = $scope.emailBody.replace(/(?:\r\n|\r|\n)/g, '<br>');

		let emailSentData = {
			"accountid": erSessionData.accountid,
			"author":erSessionData.userData.first_name + ' ' + erSessionData.userData.last_name,
			"subject": $scope.emailSubject,
			"message": $scope.emailBody,
			"recipients":recipientList.toString()
		};

		dataSvc.createOrUpdateRecord({
			"table":"emails_sent",
			"record":emailSentData
		});

		erSvc.sendEmail(recipientList.toString(), $scope.emailSubject, body, $scope.replytoemail)
		.then(function(){
			$scope.closeEmailDialog();
		});

		if(recipientList.length == 0) $scope.closeEmailDialog();
	}; //End sendEmail()

	$scope.filterEvents = function(){
		$scope.filteredEvents = [];
		var filter = $scope.eventFilter;
		angular.forEach($scope.events,function(event){
			if(event.status == filter) $scope.filteredEvents.push(event);
			else if(filter == 'all') $scope.filteredEvents.push(event);
			else if(filter == 'upcomingAndRecent' && (event.status == 'future' || event.status == 'recent'))
				$scope.filteredEvents.push(event);
			else if(filter == event.id){
				$scope.filteredEvents.push(event);
			}
		});
		$scope.userSearch();
	};
});