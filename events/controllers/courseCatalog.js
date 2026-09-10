regApp.controller('courseCatalog', function($scope, $http, $q, dataSvc, erSvc, $timeout) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Event Courses")').addClass('active');
	}, 100);
	erSvc.loadingDialog();
	$scope.accountid = erSessionData.accountid;
	eventid = erSessionData.curEvent.id;
	$scope.statusFilter = 'all';
	let gotCourses = $q.defer();
	let gotSelections = $q.defer();
	let gotSections = $q.defer();
	let gotSignups = $q.defer();

	dataSvc.getCoursesByAccount(true, $scope.accountid).then(function(res){
		$scope.courses = res;
		gotCourses.resolve();
	});

	let selections;
	dataSvc.getEventCourses(eventid, false)
	.then(function(res){
		selections = res;
		gotSelections.resolve();
	});

	let sections = [];
	dataSvc.getArray({'query':'eventSessionsExpanded','eventid':eventid})
	.then(function(resp){
		sections = resp;
		gotSections.resolve();
	});

	let signups = [];
	dataSvc.getArray({"query":"eventSignups","eventid":eventid}).then(function(resp){
		signups = resp;
		gotSignups.resolve();
	});

	$q.all([gotCourses.promise,gotSelections.promise,gotSections.promise]).then(function(){
		angular.forEach(selections,function(selection){
			let crs = $scope.courses[selection.courseid];
			if(crs){
				crs.selected = true;
				crs.include_course_list = selection.include_course_list == '1'
				crs.events_courses_id = selection.id;
			}
		});

		angular.forEach($scope.courses,function(crs){
			if(!crs.selected && crs.archived == '1') delete $scope.courses[crs.id];
			else crs.sections = [];
		});

		angular.forEach(sections,function(sec){
			let crs = $scope.courses[sec.courseid];
			if(crs) crs.sections.push(sec);
		});

		angular.forEach(signups,function(signup){
			if(signup && signup.courseid && $scope.courses[signup.courseid])
				$scope.courses[signup.courseid].hasSignups = true;
		});
		
		erSvc.closeLoading();
	});

	$scope.selectAllChg = function(){
		angular.forEach($scope.courses,function(crs){
			if($scope.showCourse(crs)) crs.selected = $scope.selectAll;
			if(!crs.selected) crs.include_course_list = false;
		});
	};

	$scope.listAllChg = function(){
		angular.forEach($scope.courses,function(crs){
			if($scope.showCourse(crs)  && crs.selected) 
				crs.include_course_list = $scope.listAll;
		});
	};

	$scope.showCourse = function(crs){
		let quickMatch = false;
		let statusMatch = false;
		if($scope.quickSearch){
			if(crs.name.toLowerCase().indexOf($scope.quickSearch) < 0) return false;
		}
		if($scope.statusFilter != 'all'){
			if($scope.statusFilter == 'selected') return crs.selected;
			if($scope.statusFilter == 'notSelected') return !crs.selected;
			if($scope.statusFilter == 'listed') return crs.include_course_list;
		}
		return true;
	};

	$scope.selectedChg = function(crs) {
		//do not allow if scheduled and has attendees
		if(!crs.selected && crs.hasSignups){
			crs.selected = true;
			erSvc.easyRegAlert({
				"text":"This course is in the event schedule, and attendees have signed up to attend.",
				"title":"Unable To Remove Course"
			});
			return;
		}
		//check if scheduled with no attendees
		if(!crs.selected && crs.sections.length){ 
			erSvc.easyRegConfirm({
				"text":`This course has ${crs.sections.length} section(s) scheduled.
					Would you like to proceed and delete section(s) from the schedule?`,
				"title":"Course Scheduled"
			},'Proceed','Cancel').then(function(res){
				if(res) crs.include_course_list = false ;
				else crs.selected = true;
			});
		}
		if(!crs.selected) crs.include_course_list = false 
	};

	$scope.saveChanges = function(){
		erSvc.loadingDialog('Saving Changes');
		let newRec;
		let recordsToUpdate = 0;
		let recordsUpdated = 0;
		angular.forEach($scope.courses,function(crs){
			if(crs.selected){ //new envents_courses record
				recordsToUpdate ++;
				newRec = {
					"id": crs.events_courses_id,
					"courseid":crs.id,
					"eventid":eventid,
					"include_course_list":crs.include_course_list
				};
				dataSvc.createOrUpdateRecord({"table":"events_courses","record":newRec},eventid)
				.then(function(res){
					if(++recordsUpdated == recordsToUpdate) saveFinish();
					if(!crs.events_courses_id) crs.events_courses_id = res;
				});
			}else if(!crs.selected && crs.events_courses_id){ //remove events_courses record
				recordsToUpdate ++;
				crs.sections.forEach(function(sec){
					dataSvc.deleteRecord({"table":"sections","id":sec.sectionid}, eventid);
				});
				dataSvc.deleteRecord({"table":"events_courses","id":crs.events_courses_id},eventid).then(function(){
					if(++recordsUpdated == recordsToUpdate) saveFinish();
					crs.events_courses_id = null;
				});
			}
		});
	};

	function saveFinish(){
		erSvc.closeLoading();
		$scope.saveSuccess = true;
		$timeout(() => $scope.saveSuccess = false ,4000);
	}
});//end controller
