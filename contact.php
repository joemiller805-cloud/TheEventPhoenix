<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PSUGevents.com</title>
	<?php include("common_functions.php");  ?>
	<?php include("commonStyles.php");?>
	<?php include("commonJs.php");?>
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			$scope.eventData;
			dataSvc.getEventData(<?= tep_js_string($_REQUEST['slug'] ?? '') ?>).then(function(resp){
				$scope.hasEvtPage = resp.pages.length > 0;
				$scope.eventData = resp;
				dataSvc.getArray({
					'query':'accountContactInfo','accountid':$scope.eventData.accountid
				}).then(function(resp){
					$scope.contact = resp[0];
					$scope.$applyAsync();
				});
			});
		});
	</script>
</head>
<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController">
		<div class="row">
			<h1 class="page-header">{{eventData.eventName}}</h1>
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="/">PSUG Events</a></li>
				<li class="breadcrumb-item">
					<a href="/e/{{eventData.slug}}" ng-show="hasEvtPage">{{eventData.eventName}}</a>
					<span ng-show="!hasEvtPage">{{eventData.eventName}}</span>
				</li>
				<li class="breadcrumb-item active">Contact Information</li>
			</ol>
		</div>
		<div class="wrapper">
			<event-sidebar class="sidebar" ng-controller="eventSidebarController"></event-sidebar>
			<div class="main-content">
				<h1 class="page-header">Contact Information</h1>
					<p>
						<div>{{contact.contact_name}}</div>
						<div>{{contact.contact_add1}}</div>
						<div ng-show="contact.contact_add2">{{contact.contact_add2}}</div>
						{{contact.contact_city}}, {{contact.contact_state}} {{contact.contact_zip}}
					</p>
					<p>
						<i class="fa fa-phone"></i>
						<abbr title="Phone">P</abbr>: {{contact.contact_phone}}
					</p>
					<p ng-show="contact.contact_fax">
						<i class="fa fa-fax"></i>
						<abbr title="Fax">F</abbr>: {{contact.contact_fax}}
					</p>
					<p>
						<i class="fa fa-envelope-o"></i>
						<abbr title="Email">E</abbr>:
						<a href="mailto:{{contact.contact_email}}">{{contact.contact_email}}</a>
					</p>
			</div>
		</div>
	</div>
	<er-Footer />
</body>
</html>