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
app.controller('regController', function($scope, $http, $q, dataSvc, erSvc){
	dataSvc.getEventData('<?= $_REQUEST["slug"] ?>').then(function(resp){
		$scope.eventData = resp;
		var eventid = $scope.eventData.eventid;
		var accountid = $scope.eventData.accountid;
		var catalogRetrieved = $q.defer();
		var coursesRetrieved = $q.defer();
		var catalog, courses;

		dataSvc.getEventCourses(eventid, false).then(function(res){
			catalog = res;
			catalogRetrieved.resolve();
		});

		dataSvc.getCoursesByAccount(true, accountid).then(function(res){
			courses = res;
			coursesRetrieved.resolve();
		});

		$scope.listedCourses = [];
		$q.all([catalogRetrieved.promise,coursesRetrieved.promise]).then(function(){
			angular.forEach(catalog,function(cat){
				if(cat.include_course_list != '1') return;
				var crs = courses[cat.courseid];
				if(crs){
					cat.courseName = crs.name;
					cat.description = crs.description;
					$scope.listedCourses.push(cat);
				}
			});
		});
	});
});//end controller
</script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController">
		<div class="row">
			<div class="col-lg-12">
				<ol class="breadcrumb">
					<li class="breadcrumb-item"><a href="/">PSUG Events</a></li>
					<li class="breadcrumb-item"><a href="/e/{{eventData.slug}}">{{eventData.eventName}}</a></li>
					<li class="breadcrumb-item active">Courses Offered</li>
				</ol>
			</div>
		</div>
		<div class="wrapper">
			<event-sidebar ng-controller="eventSidebarController"></event-sidebar>
			<div class="main-content">				
				<H3>Courses Offered</H3>
				<div class="bold" ng-show="eventData.courseCatalogMsg" style="margin-bottom:2em">
					{{eventData.courseCatalogMsg}}
				</div>
				<table class="table scrollable striped">
					<thead>
						<tr class="headerRow sticky noPad">
							<th>Course</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<tr ng-repeat="course in listedCourses | orderBy:'courseName'">
							<td class="bold">{{course.courseName}}</td>
							<td style="white-space:pre-line">{{course.description}}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div><!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>
