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
<script type="text/javascript" src="/js/jquery-barcode.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
<style type="text/css">
	/*.barcode{ font-family: 'Libre Barcode 39 Text', cursive; font-size: 26pt; }*/
	.xtraContainer{
		margin: 1em;
		/*border: 1px solid silver;*/
		page-break-inside: avoid;
		background: #f6f6f64a;
		border-radius: 6px;
		box-shadow: 2px 2px 6px 2px rgba(192,192,192,0.75);
		padding: 2rem;
	}
	@media (max-width: 800px) {
	 	.xtraContainer{
	 		margin: 1em;
	 		border: 1px solid silver;
	 		page-break-inside: avoid;
	 	}
	 	#condensedView, #expandedView, .noMobile{ display:none }
	 	#mobileView {display: block; }
	}
	@media (min-width: 801px) {
		#condensedView, #expandedView{ display:block }
		#mobileView {display: none; }
	}
	@media (max-width: 400px) {
		.barcode{ font-size: 20pt; }
	}
	@media (max-width: 300px) {
		.barcode{ font-size: 15pt; }
	}
</style>

<script type="text/javascript">
let app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.confirmation = '<?= $_SESSION["confirmation"] ?>';
	$scope.attendee_first = "<?= $_SESSION['attendee_first'] ?>";
	$scope.attendee_last = "<?= $_SESSION['attendee_last'] ?>";
	$scope.attendee_email;
	dataSvc.getEventData(<?= tep_js_string($_REQUEST['slug'] ?? '') ?>).then(function(resp){
		$scope.eventData = resp;
		if(!$scope.confirmation) $('#loginDiv').dialog({"title":"Log In","modal":true});
		else lookupReg();
	});

	$scope.view = 'expanded';
	if(window.outerWidth <= 800) $scope.view = 'mobile';

	$scope.tickets = [];
	function lookupReg(){
		dataSvc.getRegistrationData($scope.confirmation, $scope.eventData.eventid).then(function(resp){
			if(!resp) return;
			$scope.registrationType = resp.registration_type;
			$scope.regExtras = [];
			let evt = $scope.eventData;
			resp.extraOrders.forEach(function(xtra){
				xtra.codes = [];
				for(let i = 1; i <= Number(xtra.quantity); i++){
					$scope.tickets.push({"label":`${xtra.label} ${i}`,"code":`${evt.eventid}-${resp.id}-${xtra.orderId}-${i}`});
				}
				$scope.regExtras.push(xtra);
			});
			$scope.selectedTicket = $scope.tickets[0];
			$scope.$applyAsync();
			initBarcodes();
		});
	}

	function initBarcodes(){
		setTimeout(function(){
			$('.barcode').each(function(){
				$(this).barcode($(this).attr('data-data'), 'code128',{"barHeight":30});
			});
		}, 0);
	}

	$scope.nextTicket = function(){
		if($scope.disableTicketChg('next')) return;
		let idx = $scope.tickets.indexOf($scope.selectedTicket);
		$scope.selectedTicket = $scope.tickets[++idx];
		initBarcodes();
	};

	$scope.previousTicket = function(){
		if($scope.disableTicketChg('previous')) return;
		let idx = $scope.tickets.indexOf($scope.selectedTicket);
		$scope.selectedTicket = $scope.tickets[--idx];
		initBarcodes();
	};

	$scope.disableTicketChg = function(direction){
		let idx = $scope.tickets.indexOf($scope.selectedTicket);
		if(direction == 'next' && idx == ($scope.tickets.length - 1)) return true;
		else return direction == 'previous' && idx == 0;
	}

	$scope.logIn = function(){
		$scope.invalidRegNum = false;
		erSvc.attendeeLogin($scope.newConfirmation, $scope.eventData.eventid).then(function(res){
			if(res) location.reload();
			else $scope.invalidRegNum = true;
		});
	};
	$scope.printTickets = () => window.print();
});//end controller
</script>
</head>
<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController">
	<div class="row">
		<div class="col-lg-12">
			<ol class="breadcrumb noPrint">
				<li class="breadcrumb-item"><a href="/">PSUG Events</a></li>
				<li class="breadcrumb-item">
					<a href="/e/{{eventData.slug}}">
						{{eventData.eventName}}
					</a>
				</li>
				<li class="breadcrumb-item">Order Summary</li>
			</ol>
		</div>
	</div>

	<div class="wrapper" ng-show="confirmation">
	<event-sidebar ng-controller="eventSidebarController" class="noPrint"></event-sidebar>
	<div class="col-md-9 col-sm-12" ng-show="confirmation" ng-cloak>
		<div class="center noMobile" style="margin-right:5em">
			<span style="margin-right:4em;display:inline-block;" class="center">
				View<br/>
				<label style="margin-right:2em"><input type="radio" ng-model="view" value="expanded"> Expanded</label>
				<label style="margin-right:2em"><input type="radio" ng-model="view" value="condensed"> Condensed</label>
				<label style="margin-right:2em"><input type="radio" ng-model="view" value="mobile"> Mobile</label>
			</span>
			<button class=" btn btn-primary noPrint" ng-click="printTickets()">
				<span class="bi bi-printer"></span> Print Tickets
			</button>
		</div>
		
		<section ng-show="view == 'condensed'">
			<div class="page center">
				<div style="display:flex">
					<div style="flex:30%">
						<img  style="height: 100px" ng-src="{{eventData.logo}}" />
					</div>
					<div style="flex: 68%">
						<H2>{{eventData.eventName}}</H2>
						{{eventData.site}} &nbsp;&nbsp;&nbsp; {{eventData.startdate | mySqlToLocalDate}}
						{{eventData.start_time}}
					</div>
				</div>
				<H3>{{attendee_first}} {{attendee_last}} - {{registrationType}}</H3>
				<div style="margin:auto;display:inline-block;">	
					{{confirmation}}
				</div>
				<section class="xtraContainer" ng-repeat="ticket in tickets">
					<H4 style="margin-bottom:0px">{{ticket.label}}</H4>
					<div style="margin:auto;display:inline-block;">	
						<span class="barcode" data-data="{{ticket.code}}"></span>
					</div>
				</section>
			</div>	
		</section>

		<section ng-show="view == 'expanded'">
			<div class="page center" ng-repeat="ticket in tickets">
				<div style="display:flex">
					<div style="flex:30%">
						<img  style="height: 100px" ng-src="{{eventData.logo}}" />
					</div>
					<div style="flex: 68%">
						<H2>{{eventData.eventName}}</H2>
						{{eventData.site}} &nbsp;&nbsp;&nbsp; {{eventData.startdate | mySqlToLocalDate}}
						{{eventData.start_time}}
					</div>
				</div>
				<H3>{{attendee_first}} {{attendee_last}} - {{registrationType}}</H3>
				<div style="margin:auto;display:inline-block;">	
					{{confirmation}}
				</div>
				<section class="xtraContainer" >
					<H4 style="margin-bottom:0px">{{ticket.label}}</H4>
					<div style="margin:auto;display:inline-block;">	
						<span class="barcode" data-data="{{ticket.code}}"></span>
					</div>
				</section>
			</div>	
		</section>

		<section ng-show="view == 'mobile'" class='center'>
			<div style="display:flex">
				<div style="flex:30%">
					<img  style="height: 100px" ng-src="{{eventData.logo}}" />
				</div>
				<div style="flex: 68%">
					<H2>{{eventData.eventName}}</H2>
					{{eventData.site}} &nbsp;&nbsp;&nbsp; {{eventData.startdate | mySqlToLocalDate}}
					{{eventData.start_time}}
				</div>
			</div>
			<H3>{{attendee_first}} {{attendee_last}} - {{registrationType}}</H3>
			<div style="margin:auto;display:inline-block;">	
				{{confirmation}}
			</div>
			<section class="xtraContainer">
				<H4 style="margin-bottom:0px">{{selectedTicket.label}}</H4>
				<div style="margin:auto;display:inline-block;">	
					<span class="barcode" data-data="{{selectedTicket.code}}"></span>
				</div>
			</section>
			<section class="noPrint">
				<button class="btn btn-primary" ng-click="previousTicket()" ng-disabled="disableTicketChg('previous')"><</button>
				<button class="btn btn-primary" ng-click="nextTicket()" ng-disabled="disableTicketChg('next')"
					style="margin-left:1em">
					>
				</button>
			</section>
		</section>
	</div> <!-- END COLUMN -->
	</div> <!-- END ROW -->
	<div class="hide">
		<div id="loginDiv">
			<div style="margin-bottom:5px">
				Please provide your confirmation number to continue
			</div>
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
	<er-Footer />
</body>
</html>