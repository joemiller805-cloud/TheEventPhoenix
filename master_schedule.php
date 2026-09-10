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
		.dateDiv{
			float:left;
			width:45%;
		}
		.dateDiv td{
			border:1px solid black;
		}
		.dateDiv td.bold{
			border:none;
		}
		.dateDiv table{
			width:90%;
			margin:auto;
			margin-bottom:1em;
		}
		.schedTable{
			width:97%;
			margin:auto;
			border:2px solid black;
		}
		.schedTable td, .schedTable th{
			border:1px solid black;
			text-align: center;
			font-size: 8pt;
		}
		.schedTable td{
			height: 4em;
		}
		.sectionCell{
			max-width: 15em;
		}
		#confHeader{
			font-weight: bold;
			font-size:18pt;
			font-family: cursive;
		}
		.roomRow td{
			line-height: 1.3;
			padding:2px 3px;
		}
		H4.header{
			width:7.5in;
			text-align: center;
			margin-left:21%;
		}
		.extraPg H3, .extraPg img{
			text-align:center;
			margin:auto;
			display:block;
			max-width: 98%;
		}
		.extraPg H3{
			margin-bottom:1em;
		}
	</style>

	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
		app.controller('regController', function($scope, $http, $timeout, dataSvc, erSvc) {
			$scope.eventData;
			var eventid;
			dataSvc.getEventData('<?= $_REQUEST["slug"] ?>').then(function(resp){
				$scope.eventData = resp;
				eventid = resp.eventid;

				$scope.days = {};
				dataSvc.getArray({'query':'eventSessions','eventid':eventid}).then(function(resp){
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

				dataSvc.getObject({'query':'eventRooms','eventid':eventid}).then(function(resp){
					$scope.rooms = resp;
				});

				dataSvc.getTableRecords('event_master_sched_pages', 'eventid = ' + eventid,
					false, eventid).then(function(res){
					if(res[0]){
						$scope.pages = res[0];
						var pgJSON = JSON.parse($scope.pages.pages);
						$scope.pages.beforeCover = pgJSON.beforeCover || [];
						$scope.pages.afterCover = pgJSON.afterCover;
						$scope.pages.afterSched = pgJSON.afterSched;
					}else{
						$scope.pages = {
							"beforeCover":[],
							"afterCover":[],
							"afterSched":[]
						}
					}
				});

				dataSvc.getEventScheduleInfo(eventid).then(function(res){
					$scope.sessions = res;
					angular.forEach($scope.sessions,function(session){
						session.displayTime = erSvc.getDateTimeParts(session.starttime,'datetime');
						session.displayTime += ' to ' + erSvc.getDateTimeParts(session.endtime,'time');
					});
				});

				var curSession, sectionVal;
				$scope.getSection = function(session, room, justid){
					if(!$scope.sessions) return '';
					sectionVal = '';
					curSession = $scope.sessions[session.id];
					if(curSession){
						angular.forEach(curSession.sections,function(section){
							if(section.roomid == room.id){
								sectionVal += `<div style="background:${section.color} !important"> ${(section.abbreviation || section.coursename)} <br>${section.presenternames}</div>`;
								if(justid) sectionVal =  "<span class='secId'>" + section.sectionid + "</span>";
							}
						});
					}
					return sectionVal;
				}

				$scope.showDayOnCover = function(day){
					var include = false;
					angular.forEach(day.sessions,function(session){
						if(session.exc_master_sched_cover != '1') include = true;
					});
					return include;
				};

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
					return true;
				};
			});

			var spanCount = 0;
			var secLength;
			getLength();
			function getLength(){
				$timeout(function(){
					secLength = $('.secId').length;
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
			var curId, prevId, newSpan;
			function mergeCells(){
				$('.sectionCell').each(function(){
					if($(this).prev('.sectionCell').length){
						curId = $(this).find('.secId').text();
						prevId = $(this).prev('.sectionCell').find('.secId').text();
						if(curId && prevId && (curId == prevId)){
							newSpan = Number($(this).prev('.sectionCell:visible').attr('colspan')) + 1;
							$(this).prev('.sectionCell').attr('colspan',newSpan);
							$(this).remove();
						}
					}
				});
			}
		});//end controller

		app.filter('cleanTime',function(){
			return function(time){
				time = time.replace('AM','').replace('PM','');
				return time.replace(new RegExp("^[0]+"), "");
			}
		});
	</script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController" ng-cloak>
		<div class="row noPrint">
			<div class="col-lg-12">
				<ol class="breadcrumb">
					<li class="breadcrumb-item"><a href="/">Home</a></li>
					<li class="breadcrumb-item">
						<a href="/e/{{eventData.slug}}">{{eventData.eventName}}</a>
					</li>
					<li class="breadcrumb-item active">Event Schedule</li>
				</ol>
			</div>
		</div>
		<div class="wrapper">
			<event-sidebar ng-controller="eventSidebarController"></event-sidebar>
			<div class="main-content">
				<div class="page extraPg" ng-repeat="page in pages.beforeCover">
					<div ng-bind-html="page.content | trustHtml"></div>
				</div>
				<div class="page">
					<H2 class="center">{{eventData.eventName}}</H2>
					<table style="width:97%;margin:auto">
						<tr>
							<td style="width:48%">
								<img ng-src="{{eventData.logo}}"  style="width:90%;margin:auto"/>
							</td>
							<td class="center bold">
								<div id="confHeader">Event <br/>Program</div>
							</td>
						</tr>
					</table>
					<div class="dateDiv">
						<div ng-repeat="day in days | orderObjectBy:'sortDate'"	ng-show="day.colPlacement == '1'">
							<table>
								<tr>
									<td colspan="2" class="bold">
										{{day.dayName}}, {{day.month}} {{day.day}}
									</td>
								</tr>
								<tr ng-repeat="session in day.sessions | orderObjectBy:'starttime'"
									ng-show="session.exc_master_sched_cover != '1'">
									<td style="width:7em;padding-left:3px">{{session.displayStart | cleanTime}} - {{session.displayEnd | cleanTime}}</td>
									<td style="padding-left:4px">{{session.name}}</td>
								</tr>
							</table>
						</div>
					</div>
					<div class="dateDiv">
						<div ng-repeat="day in days | orderObjectBy:'sortDate'"	ng-show="day.colPlacement == '2'">
							<table>
								<tr>
									<td colspan="2" class="bold">
										{{day.dayName}}, {{day.month}} {{day.day}}
									</td>
								</tr>
								<tr ng-repeat="session in day.sessions | orderObjectBy:'starttime'"
									ng-show="session.exc_master_sched_cover != '1'">
									<td style="width:7em;padding-left:3px">{{session.displayStart | cleanTime}} - {{session.displayEnd | cleanTime}}</td>
									<td style="padding-left:4px">{{session.name}}</td>
								</tr>
							</table>
						</div>
					</div>
				</div> <!-- End cover page -->

				<!-- Extra pages after cover -->
				<div class="page extraPg" ng-repeat="page in pages.afterCover">
					<div ng-bind-html="page.content | trustHtml"></div>
				</div>

				<div class="page" ng-repeat="day in days | orderObjectBy:'sortDate'"
					ng-show="day.scheduled">
					<H3 class="center">{{day.dayName}}, {{day.month}} {{day.day}}</H3>
					<table class="schedTable">
						<tr>
							<th style="width:10em">Rooms</th>
							<th ng-repeat="session in day.sessions | orderObjectBy:'starttime'"
								ng-show="session.exc_master_sched_daily != '1'">
								{{session.name}}<br/>
								{{session.displayStart | cleanTime}} - {{session.displayEnd | cleanTime}}
							</th>
						</tr>
						<tr ng-repeat="room in rooms | orderObjectBy:'sortorder'" ng-show="roomScheduled(room, day)" class="roomRow">
							<td>{{room.name}}</td>
							<td ng-repeat="session in day.sessions | orderObjectBy:'starttime'" class="sectionCell"
								ng-if="session.exc_master_sched_daily != '1'" colspan="1">
								<span style="white-space: pre-wrap" ng-bind-html="getSection(session, room) | trustHtml"></span>
								<div ng-bind-html="getSection(session, room, true) | trustHtml" style="display:none"></div>
							</td>
						</tr>
					</table>
				</div> <!-- End Daily Schedule -->
				<div class="page extraPg" ng-repeat="page in pages.afterSched">
					<div ng-bind-html="page.content | trustHtml"></div>
				</div>
			</div>
		</div>
	</div>
	<!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>