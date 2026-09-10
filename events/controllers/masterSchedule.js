regApp.controller('masterSchedule', function($scope, $http, $timeout, dataSvc, erSvc) {
	//make a day that is in the two-column format move to the top in a one-column format
	$(document).on('dblclick','.dayDiv',function(){
		$('#topDateDiv').append($(this));
	});

	$scope.eventid = erSessionData.curEvent.id;
	$scope.days = {};
	$scope.accountid = erSessionData.accountid;
	dataSvc.getArray({'query':'eventSessions','eventid':$scope.eventid}).then(function(resp){
		angular.forEach(resp,function(session){
			if(!$scope.days[session.sessionDate]){
				$scope.days[session.sessionDate] ={
					"dayName": session.dayName,
					"month": session.month,
					"day": session.displayDay,
					"sortDate":session.starttime,
					"sessionid":session.id,
					"sessions": {}
				}
			}
			$scope.days[session.sessionDate].sessions[session.id] = session;
		});

		//split days up into those shown in column 1 vs column 2
		var dayCount = 0;
		var daysPlaced = 0;
		angular.forEach($scope.days,function(day){
			day.showOnCover = false;
			angular.forEach(day.sessions,function(session){
				if(session.exc_master_sched_cover != '1'){
					if(!day.showOnCover) dayCount ++;
					day.showOnCover = true;
				}
			});
		});
		angular.forEach($scope.days,function(day){
			if(day.showOnCover){
				day.colPlacement = (++daysPlaced > dayCount/2) ? '2' : '1';
			}
		});
	});

	dataSvc.getArray({'query':'eventDataRaw','eventid':$scope.eventid}).then(function(resp){
		$scope.eventData = resp[0];
	});

	dataSvc.getObject({'query':'eventRooms','eventid':$scope.eventid}).then(function(resp){
		$scope.rooms = resp;
	});

	dataSvc.getTableRecords('event_master_sched_pages', 'eventid = ' + $scope.eventid,
		false, $scope.eventid).then(function(res){
		if(res[0]){
			$scope.pages = res[0];
			var pgJSON = JSON.parse($scope.pages.pages);
			angular.forEach(pgJSON.afterCover, function(pg){ delete pg.$$hashKey; });
			$scope.pages.beforeCover = pgJSON.beforeCover || [];
			$scope.pages.afterCover = pgJSON.afterCover;
			$scope.pages.afterSched = pgJSON.afterSched;
		}else{
			$scope.pages = { "beforeCover":[], "afterCover":[], "afterSched":[] };
		}
	});

	dataSvc.getEventScheduleInfo($scope.eventid).then(function(res){
		$scope.sessions = res;
		angular.forEach($scope.sessions,function(session){
			session.displayTime = erSvc.getDateTimeParts(session.starttime,'datetime');
			session.displayTime += ' to ' + erSvc.getDateTimeParts(session.endtime,'time');
			angular.forEach(session.sections,function(section){
				let track = (section.tracknames || '');
				section.presenternames = section.presenternames || '';
				section.presenternames = section.presenternames.replace('Sponsor Staff','');
				if(section.coursename.indexOf('30 Minute 1-on-1') >= 0) section.presenternames = '';
			});
		});
	});

	var spanCount = 0;
	getLength();
	function getLength(){
		$timeout(function(){
			let secLength = $('.secId').length;
			if(secLength == 0){
				getLength();
				return;
			}
			if(spanCount < $('.secId').length){
				spanCount = $('.secId').length;
				getLength();
			}else{
				mergeCells();
			}
			updateSectionDivs();
		},500);
	}

	function updateSectionDivs(){
		$('td.sectionCell').each(function(){
			if($(this).find('span div').length == 1){
				$(this).css('background', $(this).find('span div').css('background-color'))
			}
		});
	}

	//find and merge multi-session sections
	function mergeCells(){
		$('.sectionCell').each(function(){
			if($(this).prev('.sectionCell').length){
				let curId = $(this).find('.secId').text();
				let prevId = $(this).prev('.sectionCell').find('.secId').text();
				if(curId && prevId && (curId == prevId)){
					let newSpan = Number($(this).prev('.sectionCell').attr('colspan')) + 1;
					$(this).prev('.sectionCell').attr('colspan',newSpan);
					$(this).remove();
				}
			}
		});
	}

	$scope.getSection = function(session, room, justid){
		if(!$scope.sessions) return '';
		let sectionVal = '';
		let curSession = $scope.sessions[session.id];
		if(curSession){
			angular.forEach(curSession.sections.filter(s => s.roomid == room.id),function(section){
				sectionVal += `<div style="background:${section.color} !important"> ${(section.abbreviation || section.coursename)} <br>${section.presenternames}</div>`;
				if(justid) sectionVal =  "<span class='secId'>" + section.sectionid + "</span>";
			});
		}
		return sectionVal;
	};

	$scope.removeColors = () => $('td, .sectionCell div').css('background','unset');

	$scope.showEmptyRooms = true;
	$scope.roomScheduled = function(room, day){
		if(!$scope.sessions) return '';
		var found = false;
		angular.forEach(day.sessions,function(session){
			angular.forEach($scope.sessions[session.id].sections,function(section){
				if(section.roomid == room.id && session.exc_master_sched_daily != '1'){
					found = true;
					day.scheduled = true;
				}
			});
		});
		return $scope.showEmptyRooms || found;
	};

	$scope.addPg = function(){
		$scope.editPg = {"content":""};
		tinymce.remove(`#content_text`);
		$('#content_text').val('');
		erSvc.initTinyMce('content_text');
		if($scope.pgPlacement == 'beforeCover')	$scope.pages.beforeCover.push($scope.editPg);
		else if($scope.pgPlacement == 'top')	$scope.pages.afterCover.push($scope.editPg);
		else $scope.pages.afterSched.push($scope.editPg);
		$scope.pgPlacement = '';
		$('#pageDialog').show(500);
	};

	$scope.editPage = function(page){
		$scope.editPg = page;
		tinymce.remove(`#content_text`);
		$('#content_text').val(page.content);
		$('#pageDialog').show(500);
		erSvc.initTinyMce('content_text');
	};

	$scope.savePage = function(){
		tinyMCE.triggerSave();
		$scope.editPg.content = tinyMCE.get('content_text').getContent();
		$('#pageDialog').hide(500);
		$scope.save();
	};

	$scope.cancelEdit = function(){
		$('#pageDialog').hide(500);
	};

	$scope.deletePage = function(){
		erSvc.easyRegConfirm({"text":"","title":"Delete Page"},"Confirm - Delete","Cancel").then(function(res){
			if(!res) return;
			var bc = $scope.pages.beforeCover;
			var ac = $scope.pages.afterCover;
			var as = $scope.pages.afterSched;
			if(bc.indexOf($scope.editPg) >= 0) bc.splice(ac.indexOf($scope.editPg),1);
			if(ac.indexOf($scope.editPg) >= 0) ac.splice(ac.indexOf($scope.editPg),1);
			if(as.indexOf($scope.editPg) >= 0) as.splice(as.indexOf($scope.editPg),1);
			$('#pageDialog').hide(500);
			$scope.save();
		});
	};

	$scope.save = function(){
		erSvc.loadingDialog("Saving Event Schedule");
		var eventData = {"id":$scope.eventData.id,"showMasterSched":$scope.eventData.showMasterSched};
		dataSvc.createOrUpdateRecord({"table":"events","record":eventData},$scope.eventid);
		const pageData = {
			"beforeCover":$scope.pages.beforeCover,
			"afterCover":$scope.pages.afterCover,
			"afterSched":$scope.pages.afterSched
		};
		var rec = {
			"eventid":$scope.eventid,
			"pages":angular.toJson(pageData).replace(/\\/g, '\\\\')
		};
		if($scope.pages.id) rec.id = $scope.pages.id;
		dataSvc.createOrUpdateRecord({"table":"event_master_sched_pages","record":rec},
			$scope.eventid).then(function(res){
			$scope.pages.id = $scope.pages.id || res;
			erSvc.closeLoading();
		});
	};

	$scope.print = () => window.print();
});//end controller

regApp.filter('cleanTime',function(){
	return function(time){
		time = time.replace('AM','').replace('PM','');
		return time.replace(new RegExp("^[0]+"), "");
	}
});
