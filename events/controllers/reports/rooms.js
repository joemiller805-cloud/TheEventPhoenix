regApp.controller('rptRooms', function($scope, $http, dataSvc, erSvc, $filter) {
	erSvc.loadingDialog();
	var eventid = erSessionData.curEvent.id;

	$scope.rooms;
	dataSvc.getObject({'query':'eventRooms','eventid':eventid}).then(function(rooms){
		$scope.rooms = rooms;
	});

	$scope.sessions;
	$scope.sessionDates = [];
	dataSvc.getObject({'query':'eventSessions','eventid':eventid}).then(function(sessions){
		$scope.sessions = sessions;
		angular.forEach(sessions,function(session){
			if($scope.sessionDates.indexOf(session.sessionDate) < 0){
				$scope.sessionDates.push(session.sessionDate);
			}
		});
		erSvc.closeLoading();
	});

	$scope.sections = [];
	dataSvc.getObject({'query':'eventSections','eventid':eventid}).then(function(sections){
		$scope.sections = sections;
	});

	$scope.section_sessions = [];
	dataSvc.getArray({"query":"eventSectionSessions","eventid":eventid}).then(function(ss){
		$scope.section_sessions = ss;
	});

	$scope.getSessionsInDay = function(date){
		var sessions = [];
		angular.forEach($scope.sessions,function(session){
			if(session.sessionDate == date && session.exc_master_sched_daily != '1') sessions.push(session);
		});
		return sessions;
	};

	$scope.getSection = function(room, session){
		var course = '';
		angular.forEach($scope.sections,function(section){
			if(section.roomid == room.id && section.sessionid == session.id){
				course = section.course;
				if(section.last_name) course += ' (' + section.first_name +  ' ' + section.last_name + ')';
				return;
			}
		});
		if(course == ''){
			angular.forEach($scope.section_sessions,function(sectionSession){
				if(sectionSession.roomid == room.id && sectionSession.sessionid == session.id){
					course = sectionSession.courseName;
					if(sectionSession.presenters) course += ' (' + sectionSession.presenters + ')';
					return;
				}
			});
		}
		return course || 'No Scheduled Session' ;
	};
});// End Controller