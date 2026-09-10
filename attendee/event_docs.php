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
			dataSvc.getEventData('<?= $_REQUEST["slug"] ?>').then(function(resp){
				$scope.eventData = resp;
				dataSvc.getDocuments(resp.accountid, resp.eventid).then(function(res){
					$scope.eventDocuments = res.eventDocuments;
					$scope.courseDocs = res.courses;
					$scope.documents = res.documents;
					angular.forEach($scope.documents,function(doc){
						if(doc.category == 'account') $scope.eventDocuments[doc.id] = doc;
					});
					$scope.$applyAsync();
				});
			});

			$scope.noDocs = function(crs){
				return Object.keys(crs.visibleDocuments).length == 0;
			};

			$scope.courseVisible = function(crs){
				if(!$scope.courseSearch) return true;
				else return crs.name.toLowerCase().indexOf($scope.courseSearch.toLowerCase()) >= 0;
			};

			$http({"url": "/session_data.php","method": "GET"}).then(function(response){
				if(response.data.confirmation || response.data.userid) $scope.courseDocsAvialable = true;
			});

			$scope.lookupRegistration = function(){
				var conf = $scope.confirmation_number
				if(conf){
					dataSvc.getRegistrationData(conf, $scope.eventData.eventid).
					then(function(resp){
						if(resp){
							$scope.invalidRegNum = false;
							//this call sets session variables
							$.post(
								'/lookup_confirmation.php?confirmation=' + encodeURIComponent(conf) +
								'&slug=' + encodeURIComponent($scope.eventData.slug)
							).then(function(){
								$scope.courseDocsAvialable = true;
								$scope.$applyAsync();
							});
						}else{
							$scope.invalidRegNum = true;
						}
					});
				}
			};
		});//end controller
	</script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController">
		<div class="row">
			<div class="col-lg-12">
				<ol class="breadcrumb">
					<li class="breadcrumb-item">
						<a href="/">PSUG Events</a>
					</li>
					<li class="breadcrumb-item">
						<a href="/e/{{eventData.slug}}">{{eventData.eventName}}</a>
					</li>
					<li class="breadcrumb-item active">
						Documents
					</li>
				</ol>
			</div>
		</div>
		<div class="wrapper">
			<event-sidebar ng-controller="eventSidebarController"></event-sidebar>
			<div class="main-content">
				<H3>Event Documents</H3>
				<table class="table">
					<tr ng-repeat="doc in eventDocuments">
						<td>
							<a ng-href="/downloadDocument.php?id={{doc.id}}&amp;eventid={{eventData.eventid}}"
								title="download">
								<span class="bi bi-download btn btn-primary btn-xs"></span>
							</a>
							<a target="_blank" ng-href="{{doc.filepath | fileHref}}" title="view">
								<span class="bi bi-eye btn btn-primary btn-xs"></span>
							</a>
							{{doc.filepath | nameFromFilepath}}
						</td>
					</tr>
				</table>
				<H3>Course Documents</H3>
				<div ng-show="courseDocsAvialable">
					<input class="form-control" ng-model="courseSearch" placeholder="Course Search" 
						style="width:25em"/>
					<table class="table">
						<tr>
							<th style="width:30%">Course</th>
							<th>Document(s)</th>
						</tr>
						<tr ng-repeat="crs in courseDocs | orderObjectBy:'name'" ng-show="courseVisible(crs)">
							<td>{{crs.name}}</td>
							<td>
								<div ng-repeat="doc in crs.visibleDocuments" style="padding-bottom:2px">
									<a ng-href="/downloadDocument.php?id={{doc.id}}&amp;eventid={{eventData.eventid}}"
										title="download">
										<span class="bi bi-download btn btn-primary btn-xs"></span>
									</a>
									<a target="_blank" ng-href="{{doc.filepath | fileHref}}" title="view">
										<span class="bi bi-eye btn btn-primary btn-xs"></span>
									</a>
									{{doc.filepath | nameFromFilepath}}
								</div>
								<div ng-show="noDocs(crs)">
									No Documents Available
								</div>
							</td>
						</tr>
					</table>
				</div>
				<div ng-show="!courseDocsAvialable">
					<div class='alert alert-info' role='alert'>
						Please log in to see course documents.
					</div>

					<div class="bold" style="margin-bottom:5px">Confirmation Number</div>
					<input ng-model="confirmation_number">
					<button type="button" class="btn btn-primary" ng-click="lookupRegistration()">
						Log In
					</button><br/>
					<a href="recover"><u>I don't know my confirmation number</u></a>
					<div class="list-group-item list-group-item-danger" ng-show="invalidRegNum">
						A registration with this confirmation number could not be found.
					</div>
				</div>
			</div>
		</div>
	</div><!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>
