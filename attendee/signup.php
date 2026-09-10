<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PSUGevents.com</title>

<?php
	$root = $_SERVER['DOCUMENT_ROOT'];
	include($root."/common_functions.php");
	include($root."/commonStyles.php");
	include($root."/commonJs.php");
?>
<style>
	div.session div.panel-heading span.date {
		font-style:italic;
		font-size:80%;
	}
	div.panel-heading{ padding:4px 15px; }
	div.panel-body{	padding-top:0px	}
	.disabled { color:#ccc; }
	i.description {
		color:#999;
		cursor:pointer;
	}
	.sessionDetails{
		display:inline-block;
		width:25em;
	}
	.selectedCourse{ display:inline-block; }
	.sectionHeader{ background-color: silver; }
	.undecided{ 
		color:silver;
		font-style:italic;
	}
</style>

<script>
var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.slug = '<?= $_REQUEST["slug"] ?>';
	$scope.confirmation = '<?= $_SESSION["confirmation"] ?>' || '<?= $_REQUEST["confirmation"] ?>';
	$scope.attendee_first = "<?= $_SESSION['attendee_first'] ?>";
	$scope.attendee_last = "<?= $_SESSION['attendee_last'] ?>";
	$scope.eventData;
	$scope.evtHasLive = false;
	$scope.evtHasVirtual = false;
	var existingSignups = [];
	$scope.sectionsAllowed = 9999;
	$scope.totalSignups = 0;
	$scope.showSaveButton = window.self == window.top;
	var sectionLimitMet = false;

	if(!$scope.confirmation) $scope.showLogin = true;
	else{
		erSvc.loadingDialog();
		dataSvc.getRegTypeInfoFromConfirmation($scope.confirmation).then(function(res){
			$scope.sections_allowed = res.sections_allowed;
		});
	}

	dataSvc.getEventData($scope.slug).then(function(resp){
		$scope.eventData = resp;
		dataSvc.getEventScheduleInfo($scope.eventData.eventid, true).then(function(res){
			$scope.sessions = res;
			prepSessions();
			if($scope.confirmation) getAttendeeInfo();
		});
		dataSvc.getTableRecords('courses', 'accountid = ' + $scope.eventData.accountid, true).then(function(res){
			$scope.courseDescriptions = res;
		});

		dataSvc.getObject({'query':'getUsersWithSponsors'}).then(function(users){
			$scope.presenters = users;
		});
	});// End data initialization

	function prepSessions(){
		angular.forEach($scope.sessions,function(session){
			session.selectedSections = {0:{"sectionid":0,"coursename":"Undecided"}};
			session.displayTime = erSvc.getDateTimeParts(session.starttime,'datetime');
			session.displayTime += ' to ' + erSvc.getDateTimeParts(session.endtime,'time');
			session.show = true;
			session.showLiveHeader = true;
			session.showVirutalHeader = true;
			angular.forEach(session.sections,function(section){
				section.show = true;
				section.tracknames = section.tracknames || '';
				section.presenternames = section.presenternames || '';
				if(section.additionalsessions && typeof section.additionalsessions == 'string'){
					section.additionalsessions = section.additionalsessions.split(',').map(function(id){
						return Number(id);
					});
				}
				section.is_virtual = section.is_virtual == '1';
				section.exclude_att_limit = section.exclude_att_limit == '1';
				if(!section.is_virtual){
					$scope.evtHasLive = true;
					session.hasLiveSections = true;
				}else{
					$scope.evtHasVirtual = true;
					session.hasVirtualSections = true;
				}
				if(section.excludeFromSched == '1') delete session.sections[section.sectionid];
				if(section.additionalsessions) setAdditionalSessions(section);
			});
			if(!Object.keys(session.sections).length) delete $scope.sessions[session.id];
		});
	}//end prepSessions()

	function getAttendeeInfo(){
		dataSvc.getTableRecords('registrations', 'confirmation = ' + "'" +
			$scope.confirmation + "'").then(function(res){
			if(res[0]){
				$scope.deleted = res[0].deleted == '1';
				$scope.registrationid = res[0].id;
				dataSvc.getTableRecords('signups', 'registrationid = ' + res[0].id).then(function(res){
					existingSignups = res;
					res.forEach(function(signup){
						if(signup.video_viewed && signup.video_viewed.indexOf('0000') == 0) signup.video_viewed = '';
						var session = $scope.sessions[signup.sessionid];
						if(session){
							angular.forEach(session.sections,function(sec){
								if(sec.sectionid == signup.sectionid){
									sec.selected = true;
									sec.video_viewed = signup.video_viewed;
									delete session.selectedSections[0];
									session.selectedSections[sec.sectionid] = sec;
									if(sec.additionalsessions) setAdditionalSessionSelections(sec);
								}
							});
						}
					});
					getDisabledSessions();
					erSvc.closeLoading();
					$scope.dataReady = true;
					checkSectionLimit();
					$scope.$applyAsync();
				});
			}
			else{
				erSvc.closeLoading();
				$scope.dataReady = true;
				$scope.$applyAsync();
			}
		});
	}//end getAttendeeInfo()

	function checkSectionLimit(){
		$scope.totalSignups = 0;
		angular.forEach($scope.sessions,function(session){
			angular.forEach(session.selectedSections,function(section){
				if(!section.exclude_att_limit && section.sessionid == session.id && section.sectionid != 0){
					$scope.totalSignups++;
					if(section.additionalsessions) $scope.totalSignups += section.additionalsessions.length;
				}
			});
		});
		sectionLimitMet = $scope.totalSignups >= $scope.sections_allowed;
	}

	$scope.disabledSessions = [];
	function getDisabledSessions(){
		$scope.disabledSessions = [];
		angular.forEach($scope.sessions,function(session){
			angular.forEach(session.selectedSections,function(sec){
				if(sec.sectionid == 0 || sec.is_virtual) return;
				if(!sec.exclude_att_limit && sec.video_viewed){
					$scope.disabledSessions.push(session.id);
				}
				angular.forEach(sec.additionalsessions,function(sess){
					$scope.disabledSessions.push(sess);
				});
			});
		});
	}

	//only the base section comes in with additional session information
	//set additional session info for sections in other sessions
	function setAdditionalSessions(section){
		section.additionalsessionnames = section.additionalsessionnames.split(',');
		var sessionids = [Number(section.sessionid)];
		var sessionNames = [$scope.sessions[section.sessionid].name];
		angular.forEach(section.additionalsessions, function(sessionid){
			if(sessionids.indexOf(sessionid) < 0) sessionids.push(sessionid);
			if(sessionNames.indexOf($scope.sessions[sessionid].name) < 0) sessionNames.push($scope.sessions[sessionid].name);
		});
		angular.forEach(section.additionalsessions, function(sessionid){
			var sec = $scope.sessions[sessionid].sections[section.sectionid];
			if(sec) sec.additionalsessionnames = angular.copy(sessionNames);
		});
	}

	//when section is selected, set selection for additional sessions
	function setAdditionalSessionSelections(sec){
		sec = $scope.sessions[sec.sessionid].sections[sec.sectionid];
		angular.forEach(sec.additionalsessions,function(sess){
			var sectionMatch = $scope.sessions[sess].sections[sec.sectionid];
			if(sectionMatch) $scope.sessions[sess].selectedSections[sec.sectionid] = sectionMatch;
		});
	}

	$scope.getSectionNames = function(session){
		var courses = [];
		angular.forEach(session.selectedSections,function(sec){
			if(sec.sectionid <= 0) return;
			let name = sec.coursename;
			if(sec.extensionOf) name += ' (Extension of ' + sec.extensionOf + ')';
			courses.push(name);
		});
		if(!courses.length) courses.push("Undecided");
		return courses;
	};

	$scope.getSectionCapacity = function(section){
		if (section.capacity == -1) return section.roomcapacity;
		else if (section.capacity == -2) return 0;
		else if (section.capacity == 0) return "Unlimited";
		else return section.capacity;
	};

	$scope.getAdditionalSessionNames = function(session, section){
		if(!section.additionalsessionnames) return;
		var resp = [];
		angular.forEach(section.additionalsessionnames,function(nm){
			if(nm != session.name) resp.push(nm);
		});
		return resp.toString();
	};

	$scope.showCourseDesc = function(section){
		$scope.selectedCourse = $scope.courseDescriptions[section.courseid];
		if(section.presenterphotos) $scope.selectedCourse.photos = section.presenterphotos.split(',');
		if(section.presenterids) $scope.selectedCourse.presenters = section.presenterids.split(',');
		$("div#course_dialog").modal("show");
	};

	$scope.closeDescription = function(){ $("div#course_dialog").modal("hide"); };

	$scope.showPresenters = function(section){
		$scope.selectedCourse = $scope.courseDescriptions[section.courseid];
		$scope.selectedCourse.photos = section.presenterphotos.split(',');
		$scope.selectedCourse.presenters = section.presenterids.split(',');
		$("div#presenter_dialog").modal("show");
	}

	$scope.closePresenters = function(){ $("div#presenter_dialog").modal("hide"); };

	$scope.sectionSelected = function(session, section){
		return session.selectedSections[section.sectionid];
	}

	$scope.selectSection = function(section, sessionid){
		let conflicts = conflictingSections(section);
		if(conflicts.length){
			setTimeout(function(){
				$(`input:radio[name="session_${sessionid}"][value="-1"]`).click();
			}, 100);
			let msg = `This section also meets during the following sessions <br/> 
				&nbsp;&nbsp;&nbsp;<b>${section.additionalsessionnames.toString()}</b> <br/><br/>
				All associated sessions must be set to undecided to select this section`;
			erSvc.easyRegAlert({"text":msg,"title":"Scheduling Conflicts"});
			return;
		}

		var session = $scope.sessions[section.sessionid];
		if(!session) return;
		//live sections, remove other signups during this session as necessary
		if(!section.is_virtual){
			angular.forEach(session.selectedSections,function(sec){
				//remove undecided placeholder if necessary
				if(sec.sectionid == 0){
					delete sec;
					return;
				}
				if(!sec.is_virtual){
					delete session.selectedSections[sec.sectionid];
					//if this was a multi-session section, remove other signups from associated sessions
					let additionalsessions = $scope.sessions[sec.sessionid].sections[sec.sectionid].additionalsessions;
					if(additionalsessions){
						additionalsessions.forEach(function(sess){
							if($scope.sessions[sess]) delete $scope.sessions[sess].selectedSections[sec.sectionid];
						});
					}
				}
			});
		}
		session.selectedSections[section.sectionid] = section;
		setAdditionalSessionSelections(section);
		getDisabledSessions();
		if(!section.is_virtual) checkSectionLimit();
	};

	//for multi-session sections, see if there is a conflict scheduled in an associated session
	function conflictingSections(section){
		if(section.is_virtual || !section.additionalsessions) return [];
		let conflicts = [];
		section.additionalsessions.forEach(function(sess){
			let session = $scope.sessions[sess];
			if(!session) return;
			angular.forEach(session.selectedSections,function(section){
			 	if(section.sectionid != 0 && !section.is_virtual) 
			 		conflicts.push({"session":session.name,"course":section.coursename});
			 });
		});
		return conflicts;
	}

	$scope.selectVirtualSection = function(section){
		$scope.selectSection(section);
		if(!section.selected){  //remove additional section sessions
			angular.forEach($scope.sessions,function(sess){
				if(sess.selectedSections[section.sectionid]){
					sess.sections[section.sectionid].selected = false;
					delete sess.selectedSections[section.sectionid];
				}
			});
		}
		checkSectionLimit();
	};

	$scope.disableSection = function(section, sessionid){
		if(!section.exclude_att_limit){
			if(sectionLimitMet && !section.selected) return true;
			if(section.video_viewed) return true;
		}
		var courseScheduled = false;
		angular.forEach($scope.sessions,function(sess){
			if(sess.id == sessionid) return;
			angular.forEach(sess.selectedSections,function(sec){
				if(sec.courseid == section.courseid) courseScheduled = true;
			});
		});
		// if(courseScheduled) return true;
		if(section.sessionid == sessionid && section.is_virtual) return false;
		if(section.full) return true;
		if ($scope.disabledSessions.indexOf(Number(sessionid)) >= 0  && !section.is_virtual) return true;

		return false;
	};

	$scope.undecidedDisbled = function(session){
		if($scope.disabledSessions.indexOf(Number(session.id)) >= 0) return true;
	};

	$scope.setUndecided = function(session){
		angular.forEach(session.selectedSections,function(sec){
			if(!sec.sessionid || sec.is_virtual) return;
			delete session.selectedSections[sec.sectionid];
			let coreSec = $scope.sessions[sec.sessionid].sections[sec.sectionid];
			if(coreSec){
				angular.forEach(coreSec.additionalsessions,function(sess){
					delete $scope.sessions[sess].selectedSections[sec.sectionid];
				});
			}
		});
		session.selectedSections[0] ={"sectionid":0,"coursename":"Undecided"};
		getDisabledSessions();
		checkSectionLimit();
	};

	$scope.expandAll = function(show){
		angular.forEach($scope.sessions,function(session){ session.expanded = show;	});
	};

	$scope.toggleSession = function(session){ session.expanded = !session.expanded; };

	$scope.checkCapacities = function(){
		erSvc.loadingDialog();
		var fullSections = [];
		var currentSelections = [];
		var sectionsToCheck = 0;
		var sectionsChecked = 0;
		var allSectionsChecked = $q.defer();
		angular.forEach(existingSignups,function(sec){
			currentSelections.push(sec.sectionid)
		});
		angular.forEach($scope.sessions,function(session){
			angular.forEach(session.selectedSections,function(sec){
				if(sec.sectionid == 0) return;
				if(currentSelections.indexOf(sec.sectionid) < 0){
					sectionsToCheck++;
					dataSvc.getArray({'query':'sectionSeatsAvailable','sectionid':sec.sectionid})
					.then(function(resp){
						var avail = Number(resp[0].available);
						if (avail <= 0)	fullSections.push(sec); 
						if(++sectionsChecked == sectionsToCheck) allSectionsChecked.resolve();
					});
				}
			});
		});
		if(sectionsToCheck == 0) allSectionsChecked.resolve();

		$q.all([allSectionsChecked.promise]).then(function(){
			if(fullSections.length) alertCapacity(fullSections);
			else $scope.saveSelections();
		});
	};

	function alertCapacity(fullSections){
		var text = "The following sections have reached capacity and are no longer available.";
		text += "<br/> Please choose an alternate offering for the following:<br/><ul>";
		angular.forEach(fullSections,function(sec){
			text += "<li>" + sec.coursename + "</li>";
		});
		text += '</ul>';
		erSvc.closeLoading();
		erSvc.easyRegAlert({"text":text,"title":"Section Full"});
	};

	$scope.saveSelections = function(){
		var saveComplete = $q.defer();
		var deletesComplete = $q.defer();
		var recordsToUpdate = 0;
		var updatesCompleted = 0;
		angular.forEach($scope.sessions,function(session){
			angular.forEach(session.selectedSections,function(sec){
				if(sec.sectionid == 0 || sec.extensionOf) return;
				recordsToUpdate++;
				var recid = existingSignups[0] ? existingSignups[0].id : null;
				dataSvc.createOrUpdateSignup(sec.sessionid, sec.sectionid, sec.video_viewed, recid)
				.then(function(){
					if(++updatesCompleted == recordsToUpdate) saveComplete.resolve();
				});
				existingSignups.splice(0,1);
			});
		});
		if(recordsToUpdate == 0) saveComplete.resolve();

		if(existingSignups.length){
			var completedDeletes = 0;
			angular.forEach(existingSignups,function(signup){
				dataSvc.deleteAttendeeSignup(signup.id).then(function(){
					if(++completedDeletes == existingSignups.length) deletesComplete.resolve();
				});
			});
		}else{
			deletesComplete.resolve();
		}

		$q.all([saveComplete.promise,deletesComplete.promise]).then(function(){
			setTimeout(erSvc.closeLoading,1000); 
			$('#reloadSched', window.parent.document).click();
		});
	};//end saveSelections()

	$scope.searchSections = function(){
		var searchVal = $scope.searchVal.toLowerCase();
		if(searchVal) $scope.expandAll(true);
		angular.forEach($scope.sessions,function(session){
			session.show = false;
			session.showLiveHeader = false;
			session.showVirutalHeader = false;
			angular.forEach(session.sections,function(sec){
				sec.show = sec.coursename.toLowerCase().indexOf(searchVal) >= 0 ||
					sec.presenternames.toLowerCase().indexOf(searchVal) >= 0 ||
					sec.tracknames.toLowerCase().indexOf(searchVal) >= 0 ||
					$scope.courseDescriptions[sec.courseid].description.toLowerCase().indexOf(searchVal) >= 0;
				if(sec.show){
					session.show = true;
					if(sec.is_virtual) session.showVirutalHeader = true;
					else session.showLiveHeader = true;
				}
			});
		});
	};
});// End Controller
</script>

</head>
<body ng-app="regApp" style="padding-top:.5em">
	<div class="container-fluid" ng-controller="regController">
		<div class="row">
			<div class="col-md-12">
				<div class='alert alert-info' role='alert' ng-show="dataReady">
					You may sign up for a total of {{sections_allowed}} sections. Once this this limit has been met,
					additional sections may not be chosen.<br/>
					Some sessions (e.g. Vendor Sessions) do not count toward this limit.  These sessions are identified
					with (**) preceding the course name.<br/>
					<div class="center">Sections Selected <b>{{totalSignups}} of {{sections_allowed}}</b></div>
				</div>
				<div ng-show="confirmation && !deleted" class="button-row" style="margin-bottom:1em">
					<b>Search</b> <input type="text" ng-model="searchVal" ng-keyup="searchSections()"
						style="margin-right:10em;width:20em;display:inline-block;" class="form-control"/>
					<button type="button" class="btn btn-primary" ng-click="expandAll(true)">Expand All</button>
					<button type="button" class="btn btn-primary" ng-click="expandAll(false)">Collapse All</button>
				</div>
				<form class="form-horizontal" ng-if="confirmation && !deleted && dataReady">
					<div ng-repeat="session in sessions | orderObjectBy:'starttime'" sessionid="{{session.id}}"
						class="panel panel-primary session" ng-show="session.show">
						<div class='panel-heading'>
							<span ng-show="!session.expanded" class="pointer" ng-click="toggleSession(session)"
								title="Show Sections">
								&#9658;
							</span>
							<span ng-show="session.expanded" class="pointer" ng-click="toggleSession(session)"
								title="Hide Sections">
								&#9660;
							</span>
							<div class="sessionDetails">
								<span style="margin-left:1em">{{session.name}}</span>
								<span class='date'>{{session.displayTime}}</span>
							</div>
							<div class="selectedCourse" style="font-weight: bold;">
								<div ng-repeat="crs in getSectionNames(session)" 
									ng-class="{'undecided': crs== 'Undecided'}">
									{{crs}}
								</div>
							</div>
						</div> <!-- End Session Heading -->
						<div class='panel-body' ng-show="session.expanded">
							<table class='table sections'>
								<tr>
									<th style='width:5%'>&nbsp;</th>
									<th style='width:30%'>Course</th>
									<th style='width:20%'>Tracks</th>
									<th style='width:20%'>Presenters</th>
									<th>Also meets</th>
									<th style='width:5%'>Cap</th>
									<th>&nbsp;</th>
								</tr>
								<tr class="sectionHeader" ng-show="session.showLiveHeader"
									ng-if="evtHasLive && evtHasVirtual && session.hasLiveSections">
									<td colspan="7">
										<b>Live Sections</b> These sections are live
									</td>
								</tr>
								<tr ng-if="session.hasLiveSections" ng-show="!searchVal">
									<td>
										<input type='radio' class='section'
											ng-click="setUndecided(session)"
											ng-disabled="undecidedDisbled(session)"
											name='session_{{session.id}}'
											value='-1' />
									</td>
									<td>Undecided</td>
									<td colspan="5">&nbsp;</td>
								</tr>
								<tr ng-repeat="section in session.sections | orderObjectBy:'coursename'"
									sectionid="{{section.sectionid}}" ng-class="{'disabled full':section.full}"
									ng-if="!section.is_virtual" ng-show="section.show && !section.extensionOf">
									<td>
										<input type='radio' class='section'
											name='session_{{session.id}}'
											ng-click="selectSection(section, session.id)"
											ng-checked="sectionSelected(session, section)"
											ng-disabled="disableSection(section, session.id)">
									</td>
									<td>
										<span ng-if="section.exclude_att_limit">**</span>
										{{section.coursename}}
										<i class='description bi bi-list-task'
											courseid='{{section.courseid}}' ng-click="showCourseDesc(section)">
										</i>
									</td>
									<td>{{section.tracknames}}</td>
									<td>
										<p style="margin-bottom:0px">{{section.presenternames}}</p>
										<img ng-repeat="p in section.presenterphotos.split(',')"
											ng-src="{{p}}" height="60" ng-if="section.presenterphotos"/>
									</td>
									<td>{{getAdditionalSessionNames(session, section)}}</td>
									<td>{{getSectionCapacity(section)}}</td>
									<td><span ng-show="{{section.full}}">Full</span></td>
								</tr> <!-- End ng-repeat="section in session.sections" Live Sections -->

								<tr class="sectionHeader" ng-show="session.showVirutalHeader"
									ng-if="evtHasLive && evtHasVirtual && session.hasVirtualSections">
									<td colspan="7">
										<b>Virtual Sections</b>
										These sections are recorded or hosted virtually
									</td>
								</tr>
								<tr ng-repeat="section in session.sections | orderObjectBy:'coursename'"
									sectionid="{{section.sectionid}}" ng-class="{'disabled full':section.full}"
									ng-if="section.is_virtual" ng-show="section.show">
									<td>
										<input type='checkbox' class='section'
											ng-change="selectVirtualSection(section)"
											ng-model="section.selected"
											ng-checked="sectionSelected(session, section)"
											ng-disabled="disableSection(section, session.id)">
									</td>
									<td>
										<span ng-if="section.exclude_att_limit">**</span>
										{{section.coursename}}
										<i class='description bi bi-list-task'
											courseid='{{section.courseid}}' 
											ng-click="showCourseDesc(section)">
										</i>
									</td>
									<td>{{section.tracknames}}</td>
									<td>
										<p style="margin-bottom:0px;color:blue" class="pointer"
											ng-click="showPresenters(section)">	
											{{section.presenternames}}
										</p>
										<img ng-repeat="p in section.presenterphotos.split(',')"
											ng-src="{{p}}" height="60" ng-if="section.presenterphotos"/>
									</td>
									<td>
										{{getAdditionalSessionNames(session, section)}}
									</td>
									<td>{{getSectionCapacity(section)}}</td>
									<td><span ng-show="{{section.full}}">Full</span></td>
								</tr><!-- End ng-repeat="section in session.sections" Virtual Sections -->
							</table>
						</div> <!-- End Session Panel -->
					</div> <!-- End ng-repeat="session in sessions" -->
				</form>
				<div class="right" ng-show="showSaveButton">
					<button type="save" class="btn btn-primary" id="schedSaveBtn" ng-click="checkCapacities()">Save</button>
				</div>
				<attendee-login></attendee-login>

				<div class='alert alert-danger' role='alert' ng-show="deleted">
					Your registration has been canceled.  Session signup is not available.
				</div>
			</div>
		</div>

		<!-- Course Dialog -->
		<div class="modal fade" id="course_dialog">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title">{{selectedCourse.name}}</h4>
					</div>
					<div class="modal-body">
						<p class="center" ng-repeat="presenter in selectedCourse.presenters">
							<b>{{presenters[presenter].first_name}} {{presenters[presenter].last_name}}</b>
						</p>
						<p style="white-space:pre-line">{{selectedCourse.description}}</p>
						<p style="text-align:center;">
							<button class="btn btn-primary" ng-click="closeDescription()">OK</button>
						</p>
					</div>
				</div><!-- /.modal-content -->
			</div><!-- /.modal-dialog -->
		</div>
		<!-- End Course Dialog -->

		<!-- Presenter Dialog -->
		<div class="modal fade" id="presenter_dialog">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title">{{selectedCourse.name}}</h4>
					</div>
					<div class="modal-body">
						<p class="center" ng-repeat="presenter in selectedCourse.presenters">
							<b>{{presenters[presenter].first_name}} {{presenters[presenter].last_name}}</b><br/>
							<img ng-src="{{presenters[presenter].photo}}" width="150" /><br/>
							{{presenters[presenter].bio}}
						</p>
						<p style="text-align:center;">
							<button class="btn btn-primary" ng-click="closePresenters()">OK</button>
						</p>
					</div>
				</div><!-- /.modal-content -->
			</div><!-- /.modal-dialog -->
		</div>
		<!-- End Presenter Dialog -->

	</div> <!-- End Controller -->
</body>
</html>
