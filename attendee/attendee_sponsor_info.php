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
		.searchDiv {
		    display: inline-block;
		    margin-left: 2em;
		    font-weight: bold;
		}
	</style>

	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			$scope.registrationid = '<?=$_SESSION['registrationid'] ?>';
			$scope.userid = '<?=$_SESSION['userid'] ?>';
			$scope.showSurvey = true;
			$scope.showAttendees = true;
			$scope.showSessions = true;

			dataSvc.getEventData(<?= tep_js_string($_REQUEST['slug'] ?? '') ?>).then(function(resp){
				$scope.eventData = resp;
				getAttendees();
				getUserEventData();
				getSurveyResults();
			});

			function getAttendees(){
				erSvc.loadingDialog();
				dataSvc.getArray({'query':'eventAttendeeList','eventid':$scope.eventData.eventid})
				.then(function(resp){
					erSvc.closeLoading();
					$scope.evtAttendees = resp;
				});
			};

			function getUserEventData(){
				var queryParams = {
					"query":"eventPresenters",
					"eventid":$scope.eventData.eventid,
					"userid":$scope.userid
				};
				dataSvc.getArray(queryParams).then(function(resp){
					$scope.userSections = resp;
				});
			}

			var surveyCourses = [];
			var surveyQeustions = [];
			$scope.surveyFilters = {
				course:{"val":"","prop":"course","label":"Course","options":surveyCourses},
				type:{"val":"","prop":"question","label":"Question","options":surveyQeustions}
			}


			$scope.surveyResponses = [];
			function getSurveyResults(){
				dataSvc.getArray({'query':'surveyResponsesPresenter','eventid':$scope.eventData.eventid})
				.then(function(resp){
					angular.forEach(resp,function(res){
						res.show = true;
						$scope.surveyResponses.push(res);
						if(!surveyCourses.includes(res.course)) surveyCourses.push(res.course);
						if(!surveyQeustions.includes(res.question)) surveyQeustions.push(res.question);
					});
					getSummaryData();
				});
			}

			$scope.quickSearch = '';
			$scope.surveyFilter = function(){
				let noneFound = true;
				let s = $scope.quickSearch.toLowerCase();
				for(let i = 0; i < $scope.surveyResponses.length; i++){
					let r = $scope.surveyResponses[i];
					r.show = true;
					if(s){
						r.show = (
							r.event.toLowerCase().indexOf(s)>=0 ||
							r.course.toLowerCase().indexOf(s)>=0 ||
							r.question.toLowerCase().indexOf(s)>=0 ||
							r.response.toLowerCase().indexOf(s)>=0
						);
					}
					if(!r.show) continue;
					angular.forEach($scope.surveyFilters,function(f){
						if(!r.show) return;
						if(f.val){
							r.show = r[f.prop].toLowerCase().indexOf(f.val.toLowerCase()) >= 0;
						}
					});
					if(r.show) noneFound = false;
				}//End response loop
				$scope.noResults = noneFound;
				getSummaryData();
			};//End filter()

			function getSummaryData(){
				let yesNoTotal = 0;
				$scope.yesResponses = 0;
				$scope.noResponses = 0;
				$scope.numericTotals = {
					"1":{"count":0,"percent":0},
					"2":{"count":0,"percent":0},
					"3":{"count":0,"percent":0},
					"4":{"count":0,"percent":0},
					"5":{"count":0,"percent":0},
					"responseCount":0,
					"responseSum":0,
					"responseAve":0
				}
				let nt = $scope.numericTotals;
				for(let i = 0; i < $scope.surveyResponses.length; i++){
					let resp = $scope.surveyResponses[i];
					if(!resp.show) continue;
					if(resp.questionType == 'numeric'){
						nt.responseCount++;
						nt.responseSum += (Number(resp.response) || 0);
						nt[resp.response].count++;
					}else if(resp.questionType == 'yesNo'){
						yesNoTotal++;
						if(resp.response == 'yes') $scope.yesResponses++;
						else if(resp.response == 'no') $scope.noResponses++;
					}
				}
				for(let i=1; i < 6; i++){
					let ths = nt[i.toString()];
					if(nt["responseCount"] == 0) ths.percent = 0;
					else ths.percent = Math.round(ths.count / nt["responseCount"] * 100);
				}
				if(nt.responseCount == 0) nt.responseAve = 'N/A';
				else nt.responseAve = (nt.responseSum / nt.responseCount).toFixed(1);
			}// end getSummaryData()

		});//end controller
	</script>
</head>

<body ng-app="regApp">
    <top-nav ng-controller="navController"></top-nav>
    <div class="container-fluid" ng-controller="regController">
		<div class="row">
			<div class="col-lg-12">
				<ol class="breadcrumb noPrint">
					<li><a href="/">PSUG Events</a></li>
					<li><a href="/e/{{eventData.slug}}">{{eventData.eventName}}</a></li>
					<li>My Vendor Info</li>
				</ol>
			</div>
		</div>
		<div class="wrapper">
			<event-sidebar ng-controller="eventSidebarController"></event-sidebar>
			<div class="main-content">
				<div class="panel panel-primary" style="overflow-x:auto">
					<div class='panel-heading pointer' ng-click="showAttendees = !showAttendees" >
						<span ng-show="!showAttendees" title="Show Attendees">&#9658;</span>
						<span ng-show="showAttendees" title="Hide Attendees">&#9660;</span>
						Event Attendees
					</div>
					<div class='panel-body' ng-show="showAttendees">
						<div style="float:right;">
							<button class="btn btn-primary btn-sm exportResultsBtn" data-table-id="attendeesTbl"
								data-file-name="Attendee List">
								<span class="bi bi-floppy"></span>
								Export Attendee List
							</button>
						</div>
						<h4>
							The following is a list of attendees who have agreed to have their contact information shared.
						</h4>
						<table class="table" id="attendeesTbl">
							<thead>
								<tr>
									<th>Last Name</th>
									<th>First Name</th>
									<th>Business</th>
									<th>Title</th>
									<th>Email</th>
									<th>Phone</th>
									<th>Addr. 1</th>
									<th>Addr. 2</th>
									<th>City</th>
									<th>State</th>
									<th>Zip</th>
								</tr>
							</thead>
							<tbody>
								<tr ng-repeat="attendee in evtAttendees">
									<td>{{attendee.last_name}}</td>
									<td>{{attendee.first_name}}</td>
									<td>{{attendee.business}}</td>
									<td>{{attendee.title}}</td>
									<td>{{attendee.email}}</td>
									<td>{{attendee.phone}}</td>
									<td>{{attendee.address1}}</td>
									<td>{{attendee.address2}}</td>
									<td>{{attendee.city}}</td>
									<td>{{attendee.state}}</td>
									<td>{{attendee.zip}}</td>
								</tr>
							</tbody>
						</table> <!-- End attendees table -->
					</div>	<!-- End attendee panel body -->
				</div> <!-- End attendee panel -->

				<!-- User Sessions -->
				<div class="panel panel-primary" ng-show="userSections">
					<div class='panel-heading pointer' ng-click="showSessions = !showSessions">
						<span ng-show="!showSessions" title="Show Attendees">&#9658;</span>
						<span ng-show="showSessions" title="Hide Attendees">&#9660;</span>
						My Sessions
					</div>
					<div class='panel-body' ng-show="showSessions">
						<table class="table">
							<tr>
								<th>Course</th>
								<th>Session</th>
								<th>Date</th>
								<th>Time</th>
								<th>Room</th>
								<th>Attendees</th>
							</tr>
							<tr ng-repeat="section in userSections">
								<td>{{section.course}}</td>
								<td>{{section.session}}</td>
								<td>{{section.date}}</td>
								<td>{{section.starttime}} - {{section.endtime}}</td>
								<td>{{section.room}}</td>
								<td>{{section.signups}}</td>
							</tr>
						</table> <!-- End session table -->
					</div> <!-- End user session panel body -->
				</div> <!-- End user sessions panel -->

				<!-- Survey Responses -->
				<div class="panel panel-primary"  ng-show="surveyResponses.length">
					<div class='panel-heading pointer' ng-click="showSurvey = !showSurvey">
						<span ng-show="!showSurvey" title="Show Attendees">&#9658;</span>
						<span ng-show="showSurvey" title="Hide Attendees">&#9660;</span>
						Survey Responses
					</div>
					<div  class='panel-body' ng-show="showSurvey">
						<div>
							<div class='searchDiv'>
								Quick Search
								<input class="form-control" ng-model="quickSearch" ng-keyup="surveyFilter()"
									style="width:15em"/>
							</div>
							<div class="searchDiv" ng-repeat="fltr in surveyFilters">
								{{fltr.label}}
								<select ng-change="surveyFilter()" class="form-select" style="width:20em"
									ng-model="fltr.val">
									<option value="">--All {{fltr.label}}s--</option>
									<option ng-repeat="opt in fltr.options | orderBy" value="{{opt}}">{{opt}}</option>
								</select>
							</div>
						</div> <!-- End Filters -->
						<div id="responseSummary">
							<table style="margin:auto;margin-top:1em">
								<tr>
									<td style="padding-right:8em">
										<ul style="list-style-type:none">
											<li class='bold'>AVE: {{numericTotals.responseAve}}</li>
											<li ng-repeat="i in [5,4,3,2,1]">
												{{i}} Star:
												<meter value="{{numericTotals[i].percent}}" min="0" max="100">
												</meter>
												{{numericTotals[i].percent}}%
											</li>
										</ul>
									</td>
									<td class='center' style="width:5em;vertical-align:top;">
										<b>Yes</b><br>{{yesResponses}}
									</td>
									<td class='center' style="width:5em;vertical-align:top;">
										<b>No</b><br>{{noResponses}}
									</td>
								</tr>
							</table>
						</div>
						<table class="table">
							<tr>
								<th>Course</th>
								<th>Question</th>
								<th>Response</th>
							</tr>
							<tr ng-repeat="response in surveyResponses" ng-show="response.show">
								<td>{{response.course}}</td>
								<td>{{response.question}}</td>
								<td>{{response.response}}</td>
							</tr>
						</table>
					</div> <!-- End survey panel body -->
				</div> <!-- End survey responses panel -->
			</div> <!-- End Column -->
		</div>
	</div>

	<!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>