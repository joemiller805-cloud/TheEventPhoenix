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
<script type="text/javascript">
var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
	let showConfirmation = sessionStorage.getItem("showConfirmation");
	sessionStorage.setItem("showConfirmation", "false");
	$scope.confirmation = '<?= $_SESSION["confirmation"] ?>';
	$scope.attendee_first = "<?= $_SESSION['attendee_first'] ?>";
	$scope.attendee_last = "<?= $_SESSION['attendee_last'] ?>";
	$scope.attendee_email;
	$scope.registrationid = "<?= $_SESSION['registrationid'] ?>";
	$scope.access = "<?= $_SESSION['sections_allowed'] ?>" != '0';
	var accountid = "<?= $_SESSION['accountid'] ?>";
	var eventid;
	var docsRetrieved = $q.defer();
	var scheduleRetrieved = $q.defer();
	var eventDataRetrieved = $q.defer();
	var sessionsRetrieved = $q.defer();
	var questionsForThisEvent;
	var courseDocs;
	var sessions;

	if(showConfirmation == 'true'){
		let msg = `
			Please keep the following registration number for your records.<br/><br/>
			 <b>${$scope.confirmation}</b>
		`;
		erSvc.easyRegAlert({"text":msg,"title":"Registration Completed"});
	}

	$scope.showW9 = accountid == 1000;
	dataSvc.getEventData('<?= $_REQUEST["slug"] ?>').then(function(resp){
		$scope.eventData = resp;
		accountid = resp.accountid;
		eventid = resp.eventid;
		dataSvc.getDocuments(resp.accountid, resp.eventid).then(function(res){
			courseDocs = res.courses;
			docsRetrieved.resolve();
		});
		getSessions();		
		eventDataRetrieved.resolve();
	});

	function getSessions(){
		dataSvc.getTableRecords('sessions', 'eventid = ' + eventid, true, eventid).then(function(res){
			sessions = res;
			angular.forEach(sessions,function(session){
				session.date = erSvc.getDateTimeParts(session.starttime,'date');
				session.time = erSvc.getDateTimeParts(session.starttime,'time');
			});
			sessionsRetrieved.resolve();
		});
	}

	$q.all([eventDataRetrieved.promise, sessionsRetrieved.promise]).then(function(){
		if($scope.confirmation && $scope.registrationid){
			$scope.getAttendeeSchedule();
		}else if('<?= $_REQUEST["confirmation"] ?>'){
			$scope.loginConfirmationNumber = '<?= $_REQUEST["confirmation"] ?>';
			$scope.logIn();
		}else{
			$scope.showLogin = true;
		}
		dataSvc.getRegistrationData($scope.confirmation, eventid).then(function(res){
			$scope.attendee_email = res.email || 'attendee@easyreg.com';
		});
	});

	$scope.sectionList = [];
	$scope.allowsignups = true;
	$scope.getAttendeeSchedule = function(){
		$scope.sectionList = [];
		dataSvc.getObject({
			'query':'attendeeSignups',
			'registrationid':$scope.registrationid
		},'sectionid').then(function(resp){
			$scope.sections = resp;
			angular.forEach($scope.sections,function(section){
				section.date = erSvc.getDateTimeParts(section.starttime,'date');
				section.time = erSvc.getDateTimeParts(section.starttime,'time');
				section.documents = [];
				if(section.web_link){
					if(section.web_link.indexOf('http') < 0) section.web_link = "https://" + section.web_link;
				}
				$scope.sectionList.push(section);
				if(section.additionalsessions){
					section.additionalsessions.split(',').forEach(function(sess){
						let session = sessions[Number(sess.trim())];
						if(!session) return;
						let newSection = angular.copy(section);
						newSection.date = session.date;
						newSection.time = session.time;
						newSection.starttime = session.starttime;
						newSection.session = session.name;
						$scope.sectionList.push(newSection);
					});
				}
			});
			scheduleRetrieved.resolve();
			erSvc.closeLoading();
			$('.dialogRight').hide(500);
			dataSvc.getRegTypeInfoFromConfirmation($scope.confirmation).then(function(res){
				var vStart = erSvc.mySqlToLocalDate(res.video_start_dt);
				var vEnd = erSvc.mySqlToLocalDate(res.video_end_dt);
				$scope.videosVisible = erSvc.dateRangeIsCurrent(vStart, vEnd);
				var sStart = erSvc.mySqlToLocalDate(res.reg_start_dt);
				var sEnd = erSvc.mySqlToLocalDate(res.reg_end_dt);
				$scope.allowsignups = erSvc.dateRangeIsCurrent(sStart, sEnd);
			});
		});
	};

	$scope.hasSchedule = function(){
		if(!$scope.sections) return false;
		return Object.keys($scope.sections).length > 0;
	};

	function loadSurveyData(){
		var getRecs = dataSvc.getTableRecords;
		var questionsRetrieved = $q.defer();
		var evtQuestionsRetrieved = $q.defer();
		var responsesRetrieved = $q.defer();

		getRecs("survey_questions", "accountid = " + accountid, true).then(function(res){
			$scope.questions = res;
			questionsRetrieved.resolve();
		});

		getRecs('survey_evt_association', 'eventid = ' + eventid, false, eventid).then(function(res){
			if(res[0]) questionsForThisEvent = res[0];
			evtQuestionsRetrieved.resolve();
		});

		var existingResponses = [];
		if(!$scope.registrationid) return;
		getRecs('survey_responses', 'registrationid = ' + $scope.registrationid, false).then(function(res){
			existingResponses = res;
			responsesRetrieved.resolve();
		});

		$scope.eventQuestions = {};
		$scope.sectionQuestions = {};
		$q.all([questionsRetrieved.promise, evtQuestionsRetrieved.promise, responsesRetrieved.promise]).then(function(){
			if(!questionsForThisEvent) return;
			questionsForThisEvent.questions.split(',').forEach(function(q){
				var curQuestion = $scope.questions[q];
				if(!curQuestion) return;
				curQuestion.optionList = (curQuestion.options || '').split('**');
				if(curQuestion){
					if(curQuestion.assoc == 'event') $scope.eventQuestions[curQuestion.id] = curQuestion;
					else if(curQuestion.assoc == 'section') $scope.sectionQuestions[curQuestion.id] = curQuestion;
				}
			});
			angular.forEach($scope.sections,function(sec){
				sec.questions = angular.copy($scope.sectionQuestions);
				if(Object.keys(sec.questions).length == 0) sec.questions = null;
			});

			existingResponses.forEach(function(res){
				if(res.sectionid){ //section questions
					var section = $scope.sections[res.sectionid];
					if(section && section.questions[res.questionid]){
						section.questions[res.questionid].response = res.response;
						section.questions[res.questionid].responseid = res.id;
					}
				}else{ // event questions
					if(!$scope.questions[res.questionid]) return;
					$scope.questions[res.questionid].response = res.response;
					$scope.questions[res.questionid].responseid = res.id;
				}
			});
			$scope.showEventSurvey = Object.keys($scope.eventQuestions).length > 0 && $scope.eventData.surveyVisible == '1';
		});
	}//End loadSurveyData()

	$q.all([docsRetrieved.promise, scheduleRetrieved.promise, eventDataRetrieved.promise]).then(function(){
		associateDocuments();
		dataSvc.getAccountFeatures().then(function(res){
			$scope.surveysEnabled = res.survey;
			$scope.docsEnabled = res.docs;
			if(res.survey) loadSurveyData();
		});
		getVendors();
	});

	function associateDocuments(){
		if(!courseDocs) return;
		angular.forEach($scope.sections,function(sec){
			if(courseDocs[sec.courseid]){
				angular.forEach(courseDocs[sec.courseid].visibleDocuments,function(doc){
					sec.documents.push(doc);
				});
			}
		});
	}

	$scope.vendors = [];
	function getVendors(){
		let virtualVendors = [];
		dataSvc.getArray({'query':'eventSponsorAttendance'}).then(function(resp){
			resp.forEach(function(rec){
				if(rec.eventid == eventid) virtualVendors.push(rec.sponsorid);
			});
			dataSvc.getTableRecords('sponsors', 'accountid = ' + accountid).then(function(res){
				angular.forEach(res,function(vendor){
					if(virtualVendors.includes(vendor.id)) $scope.vendors.push(vendor);
					if(vendor.web_address.indexOf('http') < 0) vendor.web_address = 'http://' + vendor.web_address;
				});
			});
		});
	}

	$scope.logIn = function(){
		if($scope.newConfirmation) $scope.loginConfirmationNumber = $scope.newConfirmation;
		erSvc.attendeeLogin($scope.loginConfirmationNumber, $scope.eventData.eventid)
		.then(function(res){
			$scope.newConfirmation = "";
			$scope.invalidRegNum = !res;
			if($scope.invalidRegNum) $scope.changeAttendee();
			if(res.registrationid){
				$scope.confirmation = $scope.loginConfirmationNumber;
				$scope.attendee_first = res.attendee_first;
				$scope.attendee_last = res.attendee_last;
				$scope.registrationid = res.registrationid;
				$scope.showLogin = false;
				$scope.getAttendeeSchedule();
				$('#loginDiv').dialog('close');
			}
		});
	};

	$scope.changeAttendee = function(){
		$('#loginDiv').dialog({"title":"Log In","modal":true});
		$('.ui-dialog-titlebar').show();
	};

	$scope.showWeblink = function(section){
		if(!$scope.videosVisible) return false;
		if(section.isOver == '1') return !!section.web_link;
		return !!section.web_link || (!!section.web_link2 && !!section.web_pass);
	};

	$scope.selectVideo = function(section){
		if(section.exclude_att_limit == '1'){
			launchVideo(section);
			return;
		}
		var text = "Launching this video will lock this section into your schedule.  You will not ";
		text += "be able to switch this session after the video has been launched."
		erSvc.easyRegConfirm({"text":text,"title":"Confirm Video Launch"},"Launch Video","Cancel")
		.then(function(res){
			if(res) launchVideo(section);
		});
	};

	function launchVideo(section){
		if(section.web_link.includes('zoom.us')){
			window.open(section.web_link, "_blank"); 
		}else{
			if(section.isOver == '1'  || !section.web_link2){
				if(section.web_link_external == '1'){
					window.open(section.web_link, "_blank"); 
				}
				else{
					$('#videoFrame').attr('src',section.web_link);
					$scope.showVideo = true;
				}
				return;
			}
		}
		// var mtgId = section.web_link2.replace(/[^0-9]/g,'');
		// var frameSrc = '/zoom.php?mtgid=' + mtgId; 
		// frameSrc += '&mtgpass=' + section.web_pass + '&name=' + $scope.attendee_first + ' ';
		// frameSrc += $scope.attendee_last + '&email=' + $scope.attendee_email;
		// $('#videoFrame').attr('src',frameSrc);
		// $scope.showVideo = true;
		var rec = {"query":"recordAttendeeVideoView","sectionid":section.sectionid};
		$http({
			"url": '/data_access/runUserDML.php',
			"method": 'POST',
			"data": $.param(rec),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		});
	}

	$scope.closeVideo = function(){
		$('#videoFrame').attr('src','');
		$scope.showVideo = false;
	};

	$scope.showDocs = function(section){
		$scope.currentDocs = section.documents;
		$('#documentsDialog').dialog({
			"title": section.course + ' Documents',
			"width": 500,
			"modal":true
		});
		$('.ui-dialog-titlebar-close').show();
	};

	$scope.surveySection;
	$scope.showSurvey = function(section){
		$scope.surveySection = section;
		$('#sectionSurveyDialog').show(500);
		$scope.dialogTitle = "Course Survey " + section.course;
	};

	$scope.showEvtSurvey = function(){ $('#eventSurveyDialog').show(500); };

	$scope.closeRightDialog = function(){ $('.dialogRight').hide(500); };

	$scope.submitSurvey = function(surveyType){
		erSvc.loadingDialog();
		let updateCount = 0;
		let completCount = 0;
		let questions = (surveyType == 'event') ? $scope.eventQuestions : $scope.surveySection.questions;
		angular.forEach(questions,function(q){
			if(!q.response) return;
			++updateCount;
			let cleanResp = (typeof q.response == 'string') ? q.response.replace(/'/g,"''") : q.response;
			let responseData = {
				"query": q.responseid ? "updateSurveyResponse" : "submitSurveyResponse",
				"eventid":eventid,
				"registrationid":$scope.registrationid,
				"questionid":q.id,
				"response":cleanResp,
				"sectionid": (surveyType == 'section') ? $scope.surveySection.sectionid : 'null',
				"responseid": q.responseid
			};
			$http({
				"url": '/data_access/runUserDML.php',
				"method": 'POST',
				"data": $.param(responseData),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
			}).then(function(res){
				if(++completCount == updateCount){
					erSvc.closeLoading();
					$('.dialogRight').hide(500);
					let txt = "Thank You";
					erSvc.easyRegAlert({"text":txt,"title":"Survey Submitted"}, true);
				}
				if(res.data.insertid > 0){
					q.responseid = res.data.insertid;
				}
			});
		}); // end question loop
		if(updateCount == 0) erSvc.closeLoading();
	}

	$scope.getStarClass = function(val, question){
		let retClass = "bi bi-star survey-star";
		if(val <= question.response) retClass = "bi bi-star-fill survey-star";
		return retClass;
	};

	$scope.modifySched = function(){
		$('#modifySchedFrame').attr('src','/e/' + $scope.eventData.slug + '/signup');
		$('#modifySchedDialog').show(500);
	};

	$scope.submitSchedule = function(){
		$('#modifySchedFrame').contents().find('#schedSaveBtn').trigger('click');
	};

	$scope.printSched = function(){ window.print();	};
});//end controller
</script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<!-- Controller Start -->
	<div class="container-fluid" ng-controller="regController">

	<div class="row">
		<div class="col-lg-12">
			<ol class="breadcrumb noPrint">
				<li class="breadcrumb-item"><a href="/">PSUG Events</a></li>
				<li class="breadcrumb-item">
					<a href="/e/{{eventData.slug}}">{{eventData.eventName}}</a>
				</li>
				<li class="breadcrumb-item active">My Schedule</li>
			</ol>
		</div>
	</div>

	<div class="wrapper" ng-show="access">
	<event-sidebar ng-controller="eventSidebarController"></event-sidebar>
	<div class="main-content">
		<div ng-show="confirmation" id="attendeeInfoDiv">
			<b>{{attendee_first}} {{attendee_last}}</b> - {{confirmation}}<br/>
			<a href="#" ng-click="changeAttendee()">View Information for Another Attendee</a>

			<a class="btn btn-primary" href="/PSUG Events W9.pdf" ng-if="showW9"
				target="_blank" style="float:right;margin-right:5em">W-9</a>
			<a class="btn btn-primary" href="/certificate.php?confirmation={{confirmation}}"
				ng-show="eventData.kioskcertificate == '1'"
				target="_blank" style="float:right;margin-right:1em">Certificate</a>
			<a class="btn btn-primary" href="/events/invoice.php?confirmation={{confirmation}}&eventid={{eventData.eventid}}"
				ng-show="eventData.kioskinvoice == '1'"
				target="_blank" style="float:right;margin-right:1em">Invoice</a>
			<a class="btn btn-primary" style="float:right;margin-right:1em" 
				ng-click="showEvtSurvey()" ng-show="showEventSurvey && surveysEnabled">
				Event Survey
			</a>
		</div>

		<!-- ATTENDEE SCHEDULE -->
		<H3 style="margin-bottom:2em;margin-top:.5em;padding-right:2.7em">
			My Schedule
			<button class="btn btn-primary noPrint" ng-click="printSched()" 
				id="printBtn" ng-show="hasSchedule()">
				<span class="bi bi-printer"></span> Print Schedule
			</button>
			<button class="btn btn-primary noPrint" ng-click="modifySched()" 
				id="modifySchedBtn" ng-show="confirmation && allowsignups">
				<span class="bi bi-pencil"></span> Modify Schedule
			</button>
		</H3>

		<div class='alert alert-info' role='alert' ng-cloak
			ng-if="confirmation && !allowsignups">
			Session signup is not currently available. Please check back later.
		</div>

		<div ng-show="confirmation && !hasSchedule()" class='alert alert-info' role='alert'>
			There are no sections currently scheduled.<br/><br/>
			Click the Modify Schedule button to select your schedule.
		</div>

		<div ng-show="confirmation && hasSchedule()">
			<table class="table" id="sectionsTable">
				<thead>
					<tr>
						<th>Session</th>
						<th>Course</th>
						<th>Room</th>
						<th>Tracks</th>
						<th>Presenters</th>
						<th class="noPrint">Resources <span ng-show="surveysEnabled">/Survey</span></th>
					</tr>
				</thead>
				<tbody ng-repeat="section in sectionList | orderBy:'starttime'">
					<tr>
						<td>{{section.session}} {{section.date}} {{section.time}}</td>
						<td>{{section.course}}</td>
						<td>{{section.room}}</td>
						<td>{{section.tracknames}}</td>
						<td>{{section.presenternames}}</td>
						<td class="buttonCell noPrint">
							<span>
								<button class="btn btn-primary btn-sm"
									ng-if="showWeblink(section)"
									ng-click="selectVideo(section)" title="View Video">
									<i class="bi bi-film pointer"></i>
								</button>
							</span>
							<span ng-if="docsEnabled">
								<button class="btn btn-primary btn-sm" title="Course Documents"
									ng-if="section.documents.length" ng-click="showDocs(section)">
									<i class="bi bi-folder2-open"></i>
								</button>
							</span>
							<span ng-if="surveysEnabled">
								<button class="btn btn-primary btn-sm" ng-show="section.questions"
									ng-click="showSurvey(section)" title="Survey">
									<i class="bi bi-chat-fill"></i>
								</button>
							</span>
						</td>
					</tr>
				</tbody>
			</table>
		</div> <!-- END ATTENDEE SCHEDULE -->
	</div> <!-- END COLUMN -->
	</div> <!-- END ROW -->
		
	<div id="videoDiv" ng-show="showVideo">
		<table>
			<tr id="vendorRow">
				<td>
					<a ng-repeat="vendor in vendors" href="{{vendor.web_address}}" target="_blank">
						<img ng-src="/img/account{{eventData.accountid}}/vendorLogos/{{vendor.id}}/{{vendor.logo}}"
							alt="{{vendor.name}}"/>
					</a>
				</td>
				<td>
					<button class="btn btn-danger" title="Close Video" ng-click="closeVideo()">
						x
					</button>
				</td>
			</tr>
			<tr id="videoRow">
				<td colspan="2">
					<iframe id="videoFrame" frameborder="0" allow="autoplay; fullscreen"
						allowfullscreen="">
					</iframe>
				</td>
			</tr>
		</table>
	</div> <!-- END VIDEO DIALOG -->

	<div id="documentsDialog">
		<div ng-repeat="doc in currentDocs track by $index" style="margin-bottom:.5em">
			<a ng-href="/downloadDocument.php?id={{doc.id}}&amp;eventid={{eventData.eventid}}"
				title="download">
				<span class="bi bi-download btn btn-primary btn-xs"></span>
			</a>
			<a target="_blank" ng-href="{{doc.filepath | fileHref}}" title="view">
				<span class="bi bi-eye btn btn-primary btn-xs"></span>
			</a>
			{{doc.filepath | nameFromFilepath}}
		</div>
	</div> <!-- END DOCUMENTS DIALOG -->

	<!-- SECTION SURVEY DIALOG -->
	<div id="sectionSurveyDialog" class="dialogRight">
		<div class="dialogTitle">{{dialogTitle}}</div>
		<div class="dialogContents">

			<div class="question" ng-repeat="question in surveySection.questions | orderObjectBy:'sortorder'">
				<div class="{{question.type=='label' ? 'qLabel' : ''}} questionText ">{{question.text}}</div>
				<div style="padding-left:2em">
					<div ng-show="question.type == 'numeric'">
						<span ng-repeat="val in [1,2,3,4,5]">
							<span ng-class="getStarClass(val, question)" ng-click="question.response = val"></span>
						</span>
					</div>
					<textarea ng-if="question.type == 'freeForm'" rows="8" cols="100" class="form-control"
						ng-model="question.response" style="width:70%"></textarea>
					<div ng-show="question.type == 'yesNo'" style="font-size:15pt">
						<label><input type="radio" value="yes" ng-model="question.response" /> Yes</label>
						<label><input type="radio" value="no" ng-model="question.response" style="margin-left:2em" /> No</label>
					</div>
					<div ng-show="question.type == 'multipleChoice'">
						<select
							style="width:90%"
							class="form-select"
							ng-options="q for q in question.optionList"
							ng-model="question.response">
						</select>
					</div>
				</div>
			</div>

			<div class="button-row" style="margin-top:1em">
				<button class="btn btn-primary" ng-click="closeRightDialog()">
					<span class="bi bi-chevron-left"></span> Cancel
				</button>
				<button class="btn btn-success" ng-click="submitSurvey('section')">
					<span class="bi bi-check-lg"></span> Submit
				</button>
			</div>
		</div>
	</div> <!-- END SECTION SURVEY DIALOG -->

		<!-- EVENT SURVEY DIALOG -->
	<div id="eventSurveyDialog" class="dialogRight">
		<div class="dialogTitle">Event Survey</div>
		<div class="dialogContents">

			<div class="question" ng-repeat="question in eventQuestions | orderObjectBy:'sortorder'">
				<div class="questionText {{question.type=='label'? 'qLabel' : ''}}">{{question.text}}</div>
				<div style="padding-left:2em">
					<div ng-show="question.type == 'numeric'">
						<span ng-repeat="val in [1,2,3,4,5]">
							<span ng-class="getStarClass(val, question)" ng-click="question.response = val"></span>
						</span>
					</div>
					<textarea ng-if="question.type == 'freeForm'" rows="8" cols="100" class="form-control"
						ng-model="question.response" style="width:70%"></textarea>
					<div ng-show="question.type == 'yesNo'" style="font-size:15pt">
						<label><input type="radio" value="yes" ng-model="question.response" /> Yes</label>
						<label><input type="radio" value="no" ng-model="question.response" style="margin-left:2em" /> No</label>
					</div>
					<div ng-show="question.type == 'multipleChoice'">
						<select
							style="width:90%"
							class="form-select"
							ng-options="q for q in question.optionList"
							ng-model="question.response">
						</select>
					</div>
				</div>
			</div>

			<div class="button-row" style="margin-top:1em">
				<button class="btn btn-primary" ng-click="closeRightDialog()">
					<span class="bi bi-chevron-left"></span> Cancel
				</button>
				<button class="btn btn-success" ng-click="submitSurvey('event')">
					<span class="bi bi-check-lg"></span> Submit
				</button>
			</div>
		</div>
	</div> <!-- END EVENT SURVEY DIALOG -->

	<!-- MODIFY SCHEDULE DIALOG -->
	<div id="modifySchedDialog" class="dialogRight">
		<div class="dialogTitle">Modify Schedule</div>
		<div class="dialogContents">
			<iframe id="modifySchedFrame" style="width:100%;height:	85vh;"></iframe>
		</div>
		<div class="button-row">
			<button class="btn btn-primary" ng-click="closeRightDialog()">
				<span class="bi bi-chevron-left"></span> Cancel
			</button>
			<button class="btn btn-success" ng-click="submitSchedule()">
				<span class="bi bi-check-lg"></span> Save
			</button>
		</div>
	</div> <!-- END MODIFY SCHEDULE DIALOG -->

	<button style="display:none" ng-click="getAttendeeSchedule()" id="reloadSched">Reload</button>

	<div class="hide">
		<div id="loginDiv">
			<div class="bold" style="margin-bottom:5px">Confirmation Number</div>
			<input ng-model="newConfirmation"><br/>
			<div class="right" style="margin:20px 10px">
				<button type="button" class="btn btn-primary" ng-click="logIn()">Log In</button>
			</div>
			<div class="list-group-item list-group-item-danger" ng-show="invalidRegNum">
				A registration with this confirmation number could not be found.
			</div>
			<a href="recover"><u>I don't know my confirmation number</u></a>
		</div>
	</div>
	</div><!-- END CONTROLLER   -->
	<style type="text/css">
		#videoDiv{
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0,0,0,0.8);
			z-index: 3000;
			padding-top:2em;
			overflow:auto;
		}
		#videoDiv table{
			width:98%;
			margin: auto;
		}
		tr#vendorRow{
			text-align: center;
			background: white;
		}
		#vendorRow td{ height: 7vh; }
		#vendorRow img{
			padding:0px 2em;
			margin:.5em 3em;
			height:6vh;
			max-width:18em;
			display:inline-block;
		}
		#videoFrame{
			display: block;
			background: #7a7575;
			width: 100%;
			height: 100%;
			margin:auto;
			margin-top:0px;
			margin-bottom:3em;
		}
		#videoRow td{ height:87vh; }
		/*End Video Styles*/

		#sectionsTable tr td.buttonCell{ padding: 2px 5px; }
		.buttonCell span{
			width:2.5em;
			display:inline-block;
		}
		.survey-star{
			font-size:23pt;
			cursor:pointer;
			color:#807a7a;
		}
		.bi-star-fill.survey-star,
		.bi-star.survey-star:hover{
			color:#dbbc15;
		}
		.presenterSpan{
			float:right;
			margin-right:2em;
		}
		#printBtn{ float:right; }
		#modifySchedBtn{
			float:right;
			margin-right:1em;
		}
		#attendeeInfoDiv a.btn{ margin-top:-1em; }
		#modifySchedDialog{ min-width:70%; }
		#modifySchedDialog .dialogContents{ padding:0px; }
		#modifySchedDialog .dialogTitle{ margin:0px; }
		.qLabel{
			font-size:large;
			font-weight:bold;
			border-bottom:1px solid black;
			margin-top:1em;
			margin-bottom:1em;
		}
		@media print{
			#attendeeInfoDiv a{ display:none; }
		}
	</style>
	<div style="height: 60px"></div>
	<er-Footer />
	
</body>
</html>
