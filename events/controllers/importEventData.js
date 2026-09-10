regApp.controller('importEventData', function($scope, $http, $q, dataSvc, erSvc) {
setTimeout(function(){
	$('.nav-tabs li').removeClass('active');
	$('.nav-tabs li:contains("Import")').addClass('active');
}, 100);
$('.toggle.expanded').prepend('<span class="toggleIndicator">&#9660;</span>');
$('.toggle.collapsed').prepend('<span class="toggleIndicator">&#9658;</span>');
$('.toggle.collapsed').next('div').hide();

$('.toggle').click(function(){
	$(this).next('div').toggle();
	if($(this).hasClass('expanded')) $(this).find('.toggleIndicator').html('&#9658;');
	else $(this).find('.toggleIndicator').html('&#9660;');
	$(this).toggleClass('expanded');
	$(this).toggleClass('collapsed');
});

$scope.currentEventId = erSessionData.curEvent.id;
$scope.currentEvent = {};
$scope.targetEvent = {};
$scope.hasRooms = false;
$scope.hasCourses = false;
$scope.hasSessions = false;
$scope.hasSections = false;
$scope.targetEventSelected = false;

$scope.startDate;
dataSvc.getEventDataFromId($scope.currentEventId).then(function(response){
	$scope.startDate = erSvc.localToMySqlDate(response.startdate);
});

dataSvc.getObject({'query':'accountEvents'}).then(resp => $scope.events = resp);

$scope.getCurrentRooms = function(){
	dataSvc.getObject({'query':'eventRooms','eventid':$scope.currentEventId}).then(function(resp){
		$scope.currentEvent.rooms = resp;
		$scope.hasRooms = Object.keys(resp).length > 0;
	});
};

$scope.getCurrentCourses = function(){
	dataSvc.getObject({'query':'eventCourses','eventid':$scope.currentEventId})
	.then(function(resp){
		$scope.currentEvent.courses = resp;
		$scope.hasCourses = Object.keys(resp).length > 0;
	});
};

$scope.getCurrentSessions = function(){
	dataSvc.getObject({'query':'eventSessions','eventid':$scope.currentEventId})
	.then(function(resp){
		$scope.currentEvent.sessions = resp;
		$scope.hasSessions = Object.keys(resp).length > 0;
	});
};

$scope.getCurrentSections = function(importPresentersAfter){
	dataSvc.getObject({'query':'eventSections','eventid':$scope.currentEventId})
	.then(function(resp){
		$scope.currentEvent.sections = resp;
		$scope.hasSections = Object.keys(resp).length > 0;
		if(importPresentersAfter) $scope.importPresenters();
	});
};

$scope.getCurrentPresenters = function(){
	dataSvc.getObject({'query':'eventPresenterList','eventid':$scope.currentEventId}).then(function(resp){
		$scope.currentEvent.presenters = resp;
		$scope.presenterArray = [];
		$scope.presenterIds = [];
		angular.forEach($scope.currentEvent.presenters,function(presenter){
			$scope.presenterArray.push(presenter);
			$scope.presenterIds.push(presenter.id);
		});
	});
};


$scope.getEventData = function(eventid){
	var eventStartRetrieved = $q.defer();
	var sessionsRetrieved = $q.defer();
	dataSvc.getEventDataFromId(eventid).then(function(response){
		$scope.targetEvent.startDate = erSvc.localToMySqlDate(response.startdate);
		eventStartRetrieved.resolve();
	});

	dataSvc.getObject({'query':'eventCourses','eventid':eventid}).then(function(resp){
		$scope.targetEvent.courses = resp;
		angular.forEach($scope.targetEvent.courses, course => course.import = true);
	});

	dataSvc.getObject({'query':'eventRooms','eventid':eventid}).then(function(resp){
		$scope.targetEvent.rooms = resp;
		angular.forEach($scope.targetEvent.rooms, room => room.import = true);
	});
	dataSvc.getObject({'query':'eventSessions','eventid':eventid}).then(function(resp){
		$scope.targetEvent.sessions = resp;
		angular.forEach($scope.targetEvent.sessions, session => session.import = true);
		sessionsRetrieved.resolve();
	});

	dataSvc.getObject({'query':'eventSections','eventid':eventid}).then(function(resp){
		$scope.targetEvent.sections = resp;
		angular.forEach($scope.targetEvent.sections, function(section){
			section.import = true;
			if(!$scope.presenterIds.includes(section.userid)) section.userid = '';
		});
	});
	$('#eventHeader, #roomHeader').trigger('click');
	$scope.targetEventSelected = true;

	//adjust dates based on days from orignal evt start relative to new evt start
	$q.all([eventStartRetrieved.promise,sessionsRetrieved.promise]).then(function(){
		let targetStart = dateFromString($scope.targetEvent.startDate);
		angular.forEach($scope.targetEvent.sessions,function(sess){
			let newStart = dateFromString($scope.startDate);
			let sessSt = dateFromString(sess.starttime.split(' ')[0]);
			const diffDays = Math.round(Math.abs((targetStart - sessSt) / (24 * 60 * 60 * 1000)));
			newStart.setDate(newStart.getDate() + diffDays);
			newStart  = newStart.getFullYear() + '-' + (newStart.getMonth() + 1) + '-' + newStart.getDate();
			sess.starttime = newStart + ' ' + sess.starttime.split(' ')[1];
			sess.endtime = newStart + ' ' + sess.endtime.split(' ')[1];
		});
	});
};

// yyyy-mm-dd to js date
function dateFromString(dateString){
	let parts = dateString.split('-');
	if(parts.length < 3) return null;
	return new Date(parts[0], Number(parts[1]) - 1, parts[2]);
}

$scope.getCurrentRooms();
$scope.getCurrentCourses();
$scope.getCurrentSessions();
$scope.getCurrentSections();
$scope.getCurrentPresenters();

//imports
$scope.importRooms = function(){
	erSvc.loadingDialog();
	var roomsToImport = 0;
	var roomsImported = 0;
	angular.forEach($scope.targetEvent.rooms,function(room){
		if(room.import){
			let roomCopy = angular.copy(room);
			delete roomCopy.id;
			roomCopy.eventid = $scope.currentEventId;
			roomsToImport++;
			dataSvc.createOrUpdateRecord({"table":"rooms","record":roomCopy})
			.then(function(resp){
				if(++roomsImported == roomsToImport){
					$scope.getCurrentRooms();
					$scope.hasRooms = true;
					$('#roomHeader, #courseHeader').trigger('click');
					erSvc.closeLoading();
				}
			});
		}
	});
};

$scope.importCourses = function(){
	erSvc.loadingDialog();
	var coursesToImport = 0;
	var coursesImported = 0;
	angular.forEach($scope.targetEvent.courses,function(course){
		if(course.import){
			coursesToImport++;
			let curRecord = {"courseid":course.id,"eventid":$scope.currentEventId}
			dataSvc.createOrUpdateRecord({"table":"events_courses","record":curRecord})
			.then(function(){
				if(++coursesImported == coursesToImport){
					$scope.getCurrentCourses();
					$scope.hasCourses = true;
					$('#courseHeader, #sessionHeader').trigger('click');
					erSvc.closeLoading();
				}
			});
		}
	});
};

$scope.importSessions = function(){
	erSvc.loadingDialog();
	var sessionsToImport = 0;
	var sessionsImported = 0;
	angular.forEach($scope.targetEvent.sessions,function(session){
		if(session.import){
			let sessionCopy = {
				"eventid":$scope.currentEventId,
				"starttime":session.starttime,
				"endtime":session.endtime,
				"name":session.name
			};
			sessionsToImport++;
			dataSvc.createOrUpdateRecord({"table":"sessions","record":sessionCopy})
			.then(function(){
				if(++sessionsImported == sessionsToImport){
					$scope.getCurrentSessions();
					$scope.hasSessions = true;
					$('#sessionHeader, #sectionHeader').trigger('click');
					erSvc.closeLoading();
				}
			});
		}
	});
};

$scope.importSections = function(){
	erSvc.loadingDialog();
	let sectionsToImport = 0;
	let sectionsImported = 0;
	let newSections = angular.copy($scope.targetEvent.sections);
	let curSessions = {};
	angular.forEach($scope.currentEvent.sessions, (sess) => curSessions[sess.name] = sess.id);
	let curRooms = {};
	angular.forEach($scope.currentEvent.rooms, (room) => curRooms[room.name.trim()] = room.id);

	$scope.failedSectionImports = [];
	angular.forEach(newSections,function(section){
		if(!section.import) return;
		let newSection = {
			"courseid":section.courseid,
			"capacity":section.capacity,
			"excludefromschedule":section.excludefromschedule,
			"is_virtual":section.is_virtual,
			"web_link":section.web_link,
			"web_link2":section.web_link2,
			"exclude_att_limit":section.exclude_att_limit
		};
		newSection.sessionid = curSessions[section.session];
		newSection.roomid = curRooms[section.room.trim()];

		// check for room/session/course errors
		if(!$scope.currentEvent.courses[section.courseid]){
			$scope.failedSectionImports.push({"reason":"Course not in course catalog","section":section});
			return;
		}
		if(!newSection.sessionid){
			$scope.failedSectionImports.push({"reason":"Session not found","section":section});
			return;
		} 
		if(!newSection.roomid){
			$scope.failedSectionImports.push({"reason":"Room not found","section":section});
			return;
		}
		sectionsToImport++;
		dataSvc.createOrUpdateRecord({"table":"sections","record":newSection}).then(function(res){
			if(section.additionalSessions){
				section.additionalSessions.split(',').forEach(function(session){
					let sessionid = curSessions[session];
					if(!sessionid) return;
					let newSectionSession = {"sectionid":res,"sessionid":sessionid};
					dataSvc.createOrUpdateRecord({"table":"section_sessions","record":newSectionSession});
				});
			}
			if(++sectionsImported == sectionsToImport){
				$scope.getCurrentSections(true);
				$scope.hasSections = true;
				erSvc.closeLoading();
				if($scope.failedSectionImports.length){
					erSvc.scrollToElement($('#sectionImportFailureAlert'));
				} 
			}
		});
	});// end section loop
};

$scope.importPresenters= function(){
	erSvc.loadingDialog();
	var curPresenterRec;
	angular.forEach($scope.currentEvent.sections, function(currentSection){
		angular.forEach($scope.targetEvent.sections, function(targetSection){
			if(currentSection.course == targetSection.course && currentSection.session == targetSection.session){
				currentSection.userid = targetSection.userid;
			}
		});
		if(currentSection.userid){
			curPresenterRec = {
				"sectionid":currentSection.id,
				"userid":currentSection.userid
			};
			dataSvc.createOrUpdateRecord({"table":"section_presenters","record":curPresenterRec});
		}
		erSvc.closeLoading();
	});
};

//updates
$scope.updateRoom = (room) => dataSvc.updateRoom($scope.currentEventId, room);

$scope.updateSession = (sess) => dataSvc.updateSession($scope.currentEventId, sess);

$scope.removeCourse = function(course){
	dataSvc.deleteCourse($scope.currentEventId, course).then(function(resp){
		angular.forEach($scope.currentEvent.courses,function(value, key){
			if(value.events_courses_id == course.events_courses_id){
				delete $scope.currentEvent.courses[key];
			}
		});
	});
};
});// end controller