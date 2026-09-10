regApp.controller('manageSchedule',function($scope,$q,$http,$rootScope,dataSvc,erSvc){
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Event Schedule")').addClass('active');
	}, 200);
	let colors = ["#ABE997","#EDA3D7","#F19F64","#64D3F1","#EDE85B","#60E9C5","#DFD182","#D1C5DA","#F79D92","#62F8A6","#A6D8BD","#B2D35E","#F4B04B","#9DC1EC","#92EE7E","#B6E1E4","#C8B8EF","#D0C155","#58CE8D","#9ABF78","#EAABAD","#BEEDB6","#60E0E6","#D5F090","#6ED0C2","#D3F264","#7FC29A","#E5AE77","#E5BA60","#B9C086","#E4EDA4","#8DF3AC","#92D26D","#EBB1CF","#EEC54F","#90C1CD","#51F0DF","#98CD93","#B3EF7C","#C5D585","#83EA92","#D0B672","#E0F380","#C3CB59","#EFDD6C","#A8C36B","#6CE9B4","#87D899","#CBF7A5","#93D283"];
	let courses = [];
	let sections = [];
	let eventid = erSessionData.curEvent.id;
	let userid = erSessionData.userid;
	$scope.domain = window.location.hostname;

	erSvc.loadingDialog("Loading Schedule");
	$scope.eventid = eventid;
	let deleteSignups;

	dataSvc.getTableRecords('events', 'id = ' + eventid,false,eventid).then(function(res){
		if(res[0].staff_sched_review == 1)  $('#presenterReveiwChk').prop('checked',true);
		$scope.eventName = res[0].name;
		$scope.hasVirtual = res[0].has_virtual == '1';
	});

	//initialize view
	$scope.view = {
		"sessionName":true,
		"sessionDate":true,
		"sessionTime":true,
		"roomName":true,
		"roomCapacity":true,
		"roomArea":true,
		"roomSub":true,
		"secCrs":true,
		"secPres":true,
		"secTrack":true,
		"editBtn":"1",
		"sessions":['all'],
		"rooms":['all']
	};

	let prefsId = "";
	let fltr = "userid = " + userid + " AND name = 'scheduleView' AND eventid = " + $scope.eventid;
	dataSvc.getTableRecords('preferences', fltr, false, eventid).then(function(res){
		if(res[0]){
			$scope.view = JSON.parse(res[0].value);
			prefsId = res[0].id;
		}
	});

	$scope.updateStaffReview = function(){
		let evtData = {
			"id": eventid,
			"staff_sched_review": $('#presenterReveiwChk').is(':checked')
		}
		dataSvc.createOrUpdateRecord({"table":"events","record":evtData,eventid});
	};

	$scope.presenters = {};
	dataSvc.getArray({'query':'eventUsers', 'eventid':eventid}).then(function(resp){
		angular.forEach(resp,function(user){
			if(user.presenter == '1' && user.user_eventid){
				user.courses_preferred = (user.courses_preferred || "").split(',');
				user.courses_able = (user.courses_able || "").split(',');
				$scope.presenters[user.userid] = user;
			}
		});
	});

	dataSvc.getObject({'query':'eventSponsorStaff', 'eventid':eventid}).then(function(resp){
		angular.forEach(resp,function(user){
			user.courses_preferred = [];
			user.courses_able = [];
			user.userid = user.id;
			$scope.presenters[user.id] = user;
		});
	});

	let coursesRetrieved = $q.defer();
	let videosRetrieved = $q.defer();
	let sessionsRetrieved = $q.defer();
	let roomsRetrieved = $q.defer();
	let sectionsRetrieved = $q.defer();
	let signupsRetrieved = $q.defer();
	let blankScheduleObject = {};

	dataSvc.getArray({'query':'eventCourses', 'eventid':eventid}).then(function(resp){
		let colorCounter = 0;
		$scope.courses = {};
		angular.forEach(resp,function(crs){
			crs.color ??= colors[colorCounter++];
			if(colorCounter > 49) colorCounter = 0;
			$scope.courses[crs.id] = crs;
		});
		$scope.tracks = [];
		angular.forEach($scope.courses,function(crs){
			crs.tracks = (crs.tracks || '').split(',');
			angular.forEach(crs.tracks,function(track){
				if($scope.tracks.indexOf(track) < 0 && track) $scope.tracks.push(track);
			});
		});
		coursesRetrieved.resolve();
	});

	dataSvc.getTableRecords('sessions', 'eventid = ' + eventid, true, eventid)
	.then(function(res){
		$scope.sessions = res;
		angular.forEach($scope.sessions,function(session){
			session.dt = erSvc.getDateTimeParts(session.starttime, 'date');
			session.start = erSvc.getDateTimeParts(session.starttime, 'time');
			session.end = erSvc.getDateTimeParts(session.endtime, 'time');
			blankScheduleObject[session.id] = {
				"starttime":session.starttime,
				"sessionid":session.id,
				"color":"#999",
				"sections":[{"blank":true}]
			};
		});
		sessionsRetrieved.resolve();
	});

	dataSvc.getTableRecords('rooms', 'eventid = ' + eventid, true, eventid).then(function(res){
		$scope.rooms = res;
		roomsRetrieved.resolve();
	});

	dataSvc.getArray({'query':'eventSectionsAggregated','eventid':eventid}).then(function(resp){
		$scope.sections = resp;
		angular.forEach($scope.sections,function(sec){
			sec.excludefromschedule = sec.excludeFromSched;
			if(sec.additionalsessions){
				angular.forEach(sec.additionalsessions.split(','),function(sess){
					let xtraSection = angular.copy(sec);
					xtraSection.sessionid = sess;
					xtraSection.extra = true;
					$scope.sections.push(xtraSection);
				});
			}
		});
		sectionsRetrieved.resolve();
	});

	let signups = [];
	dataSvc.getArray({'query':'eventSignups','eventid':eventid}).then(function(resp){
		signups = resp;
		signupsRetrieved.resolve();
	});

	let videos = [];
	setTimeout(function(){
		if($rootScope.enable_videos){
			dataSvc.getArray({'query':'videos'}).then(function(resp){
				videos = resp;
				videos.forEach((video) => video.courses = (video.courses || '').split(','));	
				videosRetrieved.resolve();
			});
		}else{
			videosRetrieved.resolve();
		}
	}, 1);

	$scope.sectionSignups = {};
	$q.all([roomsRetrieved.promise,sectionsRetrieved.promise,sessionsRetrieved.promise,coursesRetrieved.promise, signupsRetrieved.promise, videosRetrieved.pormise])
	.then(function(){
		//initialize a blank sections object for each room
		angular.forEach($scope.rooms, rm => rm.schedule = angular.copy(blankScheduleObject));
		angular.forEach($scope.sections,function(section){
			if(!$scope.courses[section.courseid]) return;
			section.color ??= $scope.courses[section.courseid].color;
			if(!$scope.sessions[section.sessionid]) return;
			section.presenterids = section.presenterids || '';
			section.presenterids = section.presenterids.split(',');
			section.additionalsessions = section.additionalsessions || '';
			section.additionalsessions = section.additionalsessions.split(',');
			section.starttime = $scope.sessions[section.sessionid].starttime;
			if($scope.rooms[section.roomid]) $scope.rooms[section.roomid].schedule[section.sessionid].sections.push(section);
		});
		angular.forEach(signups,function(signup){
			if(!$scope.sectionSignups[signup.sectionid]) $scope.sectionSignups[signup.sectionid] = [];
			$scope.sectionSignups[signup.sectionid].push({
				"email":signup.email,
				"signupsid":signup.signupsid
			});
		});
		videos.forEach(function(video){
			video.courses.forEach(function(crs){
				if($scope.courses[crs]){
					if(!$scope.courses[crs].videos) $scope.courses[crs].videos = [];
					$scope.courses[crs].videos.push(video);
				} 
			});
			
		});
		
		setTimeout(function(){
			$('.sectionDiv.editable').draggable({revert:'invalid'});
			$('.droppable').droppable();
			erSvc.closeLoading();
		}, 500);
	});

	$scope.getColor = function(sectionList){
		if(!sectionList.sections) return '';
		return sectionList.sections[sectionList.sections.length -1].color;
	}

	$scope.getEditSectionColor = () => {
		let course = $scope.courses[$scope.editSection.courseid];
		$scope.editSection.color = course.color;
	};

	let adding2ndSection = false; //indicates adding a second session to an existing room/session
	$scope.editSectionInit = function(sec, room, sessionid){
		if(!$scope.sectionEditable(sec)) return;
		adding2ndSection = sessionid;
		if(sec.sectionid){
			$scope.editSection = angular.copy(sec);
			$scope.originalSection = sec;
			$scope.getPresenters();
		}else{
			$scope.editSection = {
				"capacity":-1,
				"roomid":room.id,
				"sessionid":sec.sessionid || sessionid,
				"additionalsessions":[]
			};
		}
		$('#editSectionDialog').show();
	};

	$scope.tdEdit = function(sectionList, room){
		if(sectionList.sections.length == 1){
			$scope.editSectionInit(sectionList.sections[sectionList.sections.length - 1],
				room, sectionList.sessionid)
		}
	};

	$scope.saveSection = function(sec, orig){
		erSvc.loadingDialog('Saving');
		sec = sec || $scope.editSection;
		$scope.originalSection = orig || $scope.originalSection;

		//require course
		if(!sec.courseid){
			erSvc.easyRegAlert({"text":"Please Select a Course","title":"Course Needed"});
			erSvc.closeLoading();
			return;
		}

		//check for room change
		if($scope.originalSection && $scope.originalSection.roomid != sec.roomid){
			angular.forEach(sec.additionalsessions,function(sess){
				if(!sess) return;
				$scope.removeSection($scope.originalSection.roomid, sess, sec.sectionid);
			});
		}
		sec.id = sec.sectionid;
		dataSvc.createOrUpdateRecord({"table":"sections","record":sec},eventid).then(function(res){
			sec.sectionid = sec.sectionid || res;
			sec.coursename = $scope.courses[sec.courseid].name;
			sec.color = sec.color || $scope.courses[sec.courseid].color;
			sec.abbreviation = $scope.courses[sec.courseid].abbreviation;
			sec.roomcapacity = $scope.rooms[sec.roomid].capacity;
			sec.tracknames = [...$scope.courses[sec.courseid].tracks].toString();
			sec.presenterids = sec.presenterids || [];
			sec.presenternames = [];
			angular.forEach(sec.presenterids,function(presenter){
				if(!presenter || !$scope.presenters[presenter]) return;
				sec.presenternames.push($scope.presenters[presenter].first_name + " " + $scope.presenters[presenter].last_name);
			});
			sec.presenternames = sec.presenternames.toString();
			sec.starttime = $scope.sessions[sec.sessionid].starttime;
			if(!sec.excludefromschedule && $scope.courses[sec.courseid].excludefromschedule == '1') sec.excludefromschedule = '1';

			//update or add section
			var found = false;
			var sectionList = $scope.rooms[sec.roomid].schedule[sec.sessionid].sections;
			angular.forEach(sectionList,function(section){
				if(section.blank) return;
				if(section.sectionid == sec.id){
					found = true;
					sectionList[sectionList.indexOf(section)] = sec;
				}
			});
			if(!found) sectionList.push(sec);

			updateSectionPresenters(sec);
			updateSectionSessions(sec);
			$scope.closeRightDialog();
			erSvc.closeLoading();
			$scope.$applyAsync();
			setTimeout(function(){
				$('.sectionDiv.editable').draggable({revert:'invalid'});
				$('.droppable').droppable();
			}, 50);
		});
	};

	//update section_presenter records
	function updateSectionPresenters(sec){
		//remove necessary presenter associations
		if($scope.originalSection){
			angular.forEach($scope.originalSection.presenterids,function(presId){
				if(presId != '' && sec.presenterids.indexOf(presId) < 0){
					dataSvc.deleteSectionPresenter(eventid,sec.sectionid,presId);
				}
			});
		}
		//insert necessary presenter associations
		angular.forEach(sec.presenterids,function(pres){
			if($scope.originalSection && $scope.originalSection.presenterids.indexOf(pres)>=0){
				return;
			}
			dataSvc.createOrUpdateRecord({"table":"section_presenters","record":{
				"sectionid":sec.sectionid,
				"userid":pres
			}},eventid);
		});
	}

	function updateSectionSessions(sec){
		//remove necessary session associations
		if($scope.originalSection){
			angular.forEach($scope.originalSection.additionalsessions,function(sessionid){
				if(sessionid != '' && sec.additionalsessions.indexOf(sessionid) < 0){
					dataSvc.deleteSectionSession(eventid,sec.sectionid,sessionid);
					$scope.removeSection(sec.roomid, sessionid, sec.sectionid);
				}
			});
		}
		//insert necessary session associations
		angular.forEach(sec.additionalsessions,function(sess){
			if(!Number(sess)) return;
			let newSection = angular.copy(sec);
			newSection.sessionid = sess;
			newSection.starttime = $scope.sessions[sess].starttime;

			let found = false;
			let sectionList = $scope.rooms[newSection.roomid].schedule[newSection.sessionid].sections;
			angular.forEach(sectionList,function(section){
				if(section.blank) return;
				if(section.sectionid == newSection.sectionid){
					found = true;
					sectionList[sectionList.indexOf(section)] = newSection;
				}
			});
			if(!found) sectionList.push(newSection);

			//add to db if new
			if($scope.originalSection && $scope.originalSection.additionalsessions.indexOf(sess)>=0){
				return;
			}
			dataSvc.createOrUpdateRecord({"table":"section_sessions","record":{
				"sectionid":sec.sectionid,
				"sessionid":sess
			}},eventid);
		});
	}

	$scope.confirmDelete = function(){
		deleteSignups = $scope.sectionSignups[$scope.editSection.sectionid];
		if(deleteSignups){
			confirmDeleteAndEmail();
			return;
		}
		let title = "Confirm Section Deletion";
		let text = "Please confirm you would like to delete this section of " + $scope.editSection.coursename;
		erSvc.easyRegConfirm({"text":text,"title":title},"Continue - Delete Section","Cancel")
		.then(function(res){
			if(res)	$scope.deleteSection();
		});
	};

	function confirmDeleteAndEmail(){
		let text = `${deleteSignups.length} attendee(s) are scheduled to attend this section.
			If you proceed, the section and signups will deleted.  You will then be
			presented with an option to email all previously scheduled attendees.`;
		let title = "Attendee(s) Already Scheduled";
		erSvc.easyRegConfirm({"text":text,"title":title},"Continue - Delete Section","Cancel").then(function(res){
			if(res){
				let section = angular.copy($scope.editSection);
				deleteSignups.forEach(function(signup){
					dataSvc.deleteRecord({"table":"signups","id":signup.signupsid}
						,eventid);
				});
				$scope.deleteSection();
				$scope.editSection = section;
				$('#cancellationEmailDialog').show(400);
			}
		});
		$(".ui-dialog .ui-dialog-titlebar").fadeOut(400).fadeIn(400).fadeOut(400).fadeIn(400);
	}

	$scope.deleteSection = function(){
		let sec = $scope.editSection;
		angular.forEach(sec.additionalsessions,function(sess){
			if(sess) $scope.removeSection(sec.roomid, sess, sec.sectionid);
		});
		$scope.removeSection(sec.roomid, sec.sessionid, sec.sectionid);
		dataSvc.deleteRecord({"table":"sections","id":sec.sectionid},eventid);
		$scope.closeRightDialog();
	};

	$scope.sendEmail = function(){
		let addresses = deleteSignups.map(s => s.email);
		erSvc.sendEmail(addresses.toString(), $scope.emailSubject, $scope.emailBody)
		.then(function(res){
			$('#cancellationEmailDialog').hide(500);
		});
	};

	//determine which additional sessions should be available when editing a section
	$scope.additionalSessionAvailable = function(session){
		if(!$scope.editSection) return true;
		//hide session if it is the initial session for the section
		if(session.id == $scope.editSection.sessionid) return false;
		var room = $scope.rooms[$scope.editSection.roomid];
		//show the session as an option if it is currently empty
		if(!room.schedule[session.id].sectionid) return true;
		//show the session as an option if it is already an extra session for the section
		return erUtils.hasId($scope.editSection.additionalsessions, session.id);
	};

	$scope.removeSection = function(roomid, sessionid, sectionid){
		let sectionList = $scope.rooms[roomid].schedule[sessionid].sections;
		angular.forEach(sectionList,function(sec){
			if(sec.sectionid == sectionid) sectionList.splice(sectionList.indexOf(sec),1);
		});
	};

	$scope.sectionEditable = function(section){
		if(!section.additionalsessions) return true;
		return !erUtils.hasId(section.additionalsessions, section.sessionid);
	}

	$scope.closeRightDialog = function(){
		$('.dialogRight').hide(700);
		$scope.editSection = null;
		$scope.originalSection = null;
	};

	$scope.getPresenters = function(){
		$scope.ablePresenters = [];
		$scope.preferPresenters = [];
		$scope.otherPresenters = [];
		$scope.sponsorStaff = [];
		angular.forEach($scope.presenters,function(p){
			if(p.courses_preferred.indexOf($scope.editSection.courseid) >= 0)$scope.preferPresenters.push(p);
			else if(p.courses_able.indexOf($scope.editSection.courseid) >= 0) $scope.ablePresenters.push(p);
			else if(!p.sponsor) $scope.otherPresenters.push(p);
			else $scope.sponsorStaff.push(p);
		});
	};

	$scope.showSession = function(sessionid){
		return $scope.view.sessions.includes(String(sessionid)) || $scope.view.sessions.includes('all');
	};

	$scope.showViewSettings = function(){
		$('#editViewDialog').show();
	};

	var highlightRooms;  //indicate rooms with highlights if presenter/course/track search active
	$scope.showRoom = function(room){
		if($scope.view.rooms != 'all' && !erUtils.hasId($scope.view.rooms, room.id)) return false;
		if(highlightRooms){
			return erUtils.hasId(highlightRooms, room.id);
		}
		return true;
	};

	$scope.set_highlight = function(field, value){
		highlightRooms = [];
		$scope.highlightField = field;
		$scope.highlightValue = value;
	};

	$scope.$watch('highlightField',function(){
		if(!$scope.highlightField) highlightRooms = null;
	});

	$scope.highlighted = function(section){
		if(!$scope.highlightField) return '';
		if($scope.highlightField == 'tracks' && section.tracknames){
			if(section.tracknames.replace(/, /g ,',').split(',').indexOf($scope.highlightValue) >= 0){
				highlightRooms.push(section.roomid);
				return 'highlight';
			}
		}
		if($scope.highlightField == 'courses' && section.courseid == $scope.highlightValue){
			highlightRooms.push(section.roomid);
			return 'highlight';
		}
		if($scope.highlightField == 'presenters' && $scope.highlightValue != 0){
			if(section.presenterids && (section.presenterids[0] == "" || section.presenterids.length == 0)){
				highlightRooms.push(section.roomid);
				return 'highlight';
			}
		}
		if($scope.highlightField == 'presenters' && section.presenterids){
			if(section.presenterids.includes(String($scope.highlightValue))){
				highlightRooms.push(section.roomid);
				return 'highlight';
			}
		}
		return 'dull';
	};

	$scope.saveDisplayPrefs = function(){
		var prefRec = {
			"userid":userid,
			"name":"scheduleView",
			"eventid": $scope.eventid,
			"value": JSON.stringify($scope.view)
		};
		if(prefsId) prefRec.id = prefsId;
		dataSvc.createOrUpdateRecord({"table":"preferences","record":prefRec},eventid);
		$scope.closeRightDialog();
	};
});//end controller

/**  Directives  **/
//directives for session drag-and-drop
regApp.directive('draggableElement', function($rootScope){
   return {
	  link: function(scope, elem, attr, ctrl) {
		elem.bind('dragstart', function(e) {
			$rootScope.originalSection = scope.section;
		});
	  }
   };
});

regApp.directive('droppableArea', function($rootScope, erSvc) {
   return {
   		scope:{ room:'=',sessionid:'=' },
	   	link: function(scope, elem, attr, ctrl) {
			elem.bind('drop', function(e) {
				let orig = $rootScope.originalSection;
				if(orig.additionalsessions.indexOf(scope.sessionid)>=0){
					let txt = "Cannot move to a session that is currently an extra session of the section.";
					erSvc.easyRegAlert({"text":txt,"title":"Unable to move section"});
					$('.hoveredOver').removeClass('hoveredOver');
					return;
				}
				$(e.target).closest('.droppable').removeClass('hoveredOver');
				scope.$parent.removeSection(orig.roomid, orig.sessionid, orig.sectionid);
				let editSection = angular.copy(orig);
				editSection.roomid = scope.room.id;
				editSection.sessionid = scope.sessionid;
				scope.$parent.saveSection(editSection, orig);
			});

			elem.bind('dragover', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(e.target).closest('.droppable').addClass('hoveredOver');
			});

			elem.bind('dragleave', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(e.target).closest('.droppable').removeClass('hoveredOver');
			});
	   }
   };
});
