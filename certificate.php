<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>The Event Phoenix</title>
	<?php
		$root = $_SERVER['DOCUMENT_ROOT'];
		include($root."/common_functions.php");
		include($root."/commonStyles.php");
		include($root."/commonJs.php");
		$inputs = sanitize_inputs($_REQUEST);
	?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc']);
		app.controller('certController', function($scope, $http, dataSvc, erSvc) {
			$scope.attendees = [];
			$http.get('/certificateData.php', {
				params:{confirmations:"<?=$inputs['confirmation']?>"}
			}).then(resp => $scope.attendees = resp.data);
			$('body').css('background','white');
		});//end controller
	</script>
</head>

<body ng-app="regApp">
    <div ng-controller="certController">
		<section class="page center bold" ng-repeat="attendee in attendees" ng-cloak>
			<img src="{{attendee.logo}}" class="eventLogo"/>
			<h1>Certificate of Participation</h1>
			<p style="font-weight: normal;">
				This is to certify that
			</p>
			<h3>{{attendee.first_name}} {{attendee.last_name}}</h3>
			<section style="font-size: 18px;">
				Has participated in the
				{{attendee.event}} Conference <br/>
				held in {{attendee.city}}, {{attendee.state}} <br/>
				{{attendee.startdate}}<br/>
				
			</section>
			<p style="font-style:italic; font-weight: normal; margin-top:1em">
				{{attendee.certificatemessage}}
			</p>
			<section ng-if="attendee.accountid = '1000'" style="font-weight:normal;">
				<img src="img/print_logo.png"  style="width:12em" /><br/>
				PSUG-Events Conference Organizing Committee
			</section>
		</section>
	</div>
	<!-- END CONTROLLER   -->
</body>
<style>
	.page{
		margin-left:auto;
		margin-right: auto;
		width: 8.5in;
		height:11in;
		background: white;
	}
	.page img.eventLogo{
		min-height: 15em;
		max-height:25em;
		max-width: 8in;
	}
	.page h1, .page h3{
		margin-top:1em;
		margin-bottom:1em;
	}
</style>
</html>