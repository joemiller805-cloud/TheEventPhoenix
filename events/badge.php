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
	?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
	<script type="text/javascript" src="/js/qrcode.js"></script>
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			let eventid = new URL(location).searchParams.get('eventid');
			let confirmations = new URL(location).searchParams.get('confirmation').split(',');
			let pageTemplate = {"attendees":[]};
			let attendees = [];
			$scope.qrCodes = [];
			$scope.countPerPg = '6';
			dataSvc.getRegistrations(eventid).then(function(response){
				angular.forEach(response,function(attendee){
					if(!confirmations.includes(attendee.confirmation)) return;
					attendees.push(attendee);
				});
				$scope.changeLayout();
			});

			let prefFilters = `accountid=${erSessionData.accountid} AND name='qrCodeSettings'`;
			dataSvc.getTableRecords('preferences', prefFilters).then(function(settings){
				if(settings[0]){
					$scope.qrCodes = angular.fromJson(settings[0].value);
					$scope.$applyAsync();
				}
			});

			$scope.changeLayout = function(){
				$scope.pages = [angular.copy(pageTemplate)];
				let attendeeCount = 0;
				let currentPage = $scope.pages[0];
				attendees.forEach(function(attendee){
					if(++attendeeCount > Number($scope.countPerPg)){
						currentPage = angular.copy(pageTemplate)
						$scope.pages.push(currentPage);
						attendeeCount = 1;
					}
					currentPage.attendees.push(attendee);
				});
				let lastPg = $scope.pages[$scope.pages.length - 1];

				//ensure page formatting by adding blank attendees
				while(lastPg.attendees.length < Number($scope.countPerPg)){
					lastPg.attendees.push({});
				}
				setTimeout(generateQrCodes,1);
			};

			$scope.changeQr = function(){
				if($scope.selectedQr == '') return;
				setTimeout(generateQrCodes,1);
			};

			generateQrCodes = function(){
				$('.qrSection').empty();
				if($scope.selectedQr == '') return;
				let qrContent = '';
				$scope.qrCodes.forEach(function(cd){
					if(cd.name == $scope.selectedQr) qrContent = cd.content;
				});
				let matches = qrContent.match(/\[(.*?)\]/g) || [];
				attendees.forEach(function(attendee){
					let attendeeQr = qrContent;
					matches.forEach(function(field){
						let rawfield = field.replace('[','').replace(']','');
						attendeeQr = attendeeQr.replace(field, attendee[rawfield]);
					});
					let qrcode = new QRCode(document.getElementById(`qr${attendee.id}`), {width : 100,height : 100});
					qrcode.makeCode(attendeeQr);
				});
			};
		});//end controller
	</script>
</head>

<body ng-app="regApp">
    <div ng-controller="regController">
    	<section class="center noPrint">
	    	<label style="margin-right: 2em;">
	    		Badges Per Page
	    		<select ng-model="countPerPg" ng-change="changeLayout()" style="font-weight: normal;">
	    			<option value="6">6</option>
	    			<option value="4">4</option>
	    			<option value="2">2</option>
	    			<option value="1">1</option>
	    		</select>
	    	</label>
	    	<label style="margin-right: 2em;">
	    		QR Code
	    		<select ng-model="selectedQr" ng-change="changeQr()" style="font-weight: normal;">
	    			<option value="">No QR Code</option>
	    			<option ng-repeat="code in qrCodes" value="{{code.name}}">{{code.name}}</option>
	    		</select>
	    	</label>
	    	<label style="margin-right: 2em;">
	    		Show Confirmation # Barcode
	    		<input type="checkbox" ng-model="showBarcode" />
	    	</label>
	    </section>
		<div class="page" ng-repeat="page in pages" data-badge-count="{{countPerPg}}" ng-cloak>
			<section ng-repeat="attendee in page.attendees" class="attendeeBadge center {{!attendee.last_name ? 'noBorder':''}}">
				<H3>{{attendee.event}}</H3>
				<H4 ng-show="attendee.last_name">{{attendee.last_name}},	{{attendee.first_name}}</H4>
				<section id="qr{{attendee.id}}" ng-show="selectedQr" class="qrSection"></section>
				<p style="margin-top:.5em;font-size:20pt;" class="barcode" ng-if="showBarcode">{{attendee.confirmation}}</p>
			</section>
		</div>
	</div>
	<!-- END CONTROLLER   -->
</body>
<style>
	.page{
		margin-left:auto;
		margin-right: auto;
		width: 8.5in;
		display: flex;
		flex-wrap: wrap;
		align-items: stretch;
		justify-content: space-evenly;
	}
	.attendeeBadge{
		border: 1px solid silver;
		margin: 3px;
		padding: 12px 8px 0px 8px;
		-webkit-border-radius: 3px;
		-moz-border-radius: 3px;
		border-radius: 3px;
	}
	.attendeeBadge.noBorder{ border: none; }
	[data-badge-count="6"] .attendeeBadge{ flex-basis: 48%; min-height:3.5in; }
	[data-badge-count="4"] .attendeeBadge{ flex-basis: 48%; min-height:5.3in; }
	[data-badge-count="2"] .attendeeBadge{ flex-basis: 98%; min-height:5.3in; }
	[data-badge-count="1"] .attendeeBadge{ flex-basis: 98%; min-height:11in; }
	.attendeeBadge {margin-bottom:1em; }
	.qrSection img, .qrSection{ margin: auto; }
	.qrSection{ margin-top: 1em; }
	.barcode{ font-family: 'Libre Barcode 39 Text', cursive; }
</style>
</html>