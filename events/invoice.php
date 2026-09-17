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
	<script type="text/javascript">
		let app = angular.module('regApp', ['easyRegDataModule','erSvc']);
		app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
			let eventid = new URL(location).searchParams.get('eventid');
			let confirmations = new URL(location).searchParams.get('confirmation').split(',');
			$scope.origin = window.location.origin;
			$scope.today = erSvc.today();
			$scope.attendees = {};
			let xtraOrders = [];
			let regsRetrieved = $q.defer();
			let xtrasRetrieved = $q.defer();
			let regTypesRetrieved = $q.defer();
			let accountid = '<?=$_SESSION['accountid'] ?>';
			if(!accountid){
				dataSvc.getArray({'query':'accountidFromConfirmation','confirmation':confirmations}).then(function(resp){
					$.post('/set_session_account.php',{'accountid':resp[0].accountid}, getRegistrations);
				});
			}else{
				getRegistrations();
			}

			function getRegistrations(){
				dataSvc.getRegistrations(eventid, confirmations.length == '1' ? confirmations : '').then(function(response){
					angular.forEach(response,function(attendee){
						if(!confirmations.includes(attendee.confirmation)) return;
						attendee.xtraOrders = {};
						attendee.serviceFee = Number(attendee.serviceFee);
						attendee.price = Number(attendee.price);
						attendee.balance = Number(attendee.balance);
						attendee.payments = Number(attendee.payments);
						attendee.cc_fees = Number(attendee.cc_fees);
						$scope.attendees[attendee.id] = attendee;
					});
					regsRetrieved.resolve();
					dataSvc.getArray({'query':'accountInfo'}).then(r =>{
						$scope.acctData = r[0];
						$scope.$applyAsync();
					});
				});
			}
			
			dataSvc.getArray({'query':'eventDataRaw','eventid':eventid}).then(r => $scope.eventData = r[0]);
			dataSvc.getArray({'query':'regExtraOrdersByEvent','eventid':eventid}).then(function(res){
				xtraOrders = res;
				xtrasRetrieved.resolve();
			});

			$q.all([regsRetrieved.promise,xtrasRetrieved.promise]).then(function(){
				xtraOrders.forEach(function(order){
					let att = $scope.attendees[order.registrationid];
					if(!att) return;
					if(!att.xtraOrders[order.extraId]){
						att.xtraOrders[order.extraId] = {
							'label':order.label,
							'qty':0,
							'price':order.price
						}
					}
					att.xtraOrders[order.extraId].qty += Number(order.quantity);
				});
			});
		});//end controller
	</script>
</head>

<body ng-app="regApp">
    <div ng-controller="regController">
		<div class="page" ng-repeat="attendee in attendees">
			<section class="threeCol">
				<div><img ng-src="{{eventData.logo}}" style="max-width:100%;max-height:100px;"/></div>
				<div class="center">
					<H3 style="margin-top:0px">Invoice</H3>
					Invoice #: {{attendee.confirmation}}<br/>
					Invoice Date: {{today}}
				</div>
				<div style="padding-left:5em;font-size:11px">
					Fax: {{acctData.fax}}<br/>
					Phone: {{acctData.phone}}<br/>
					Email: {{acctData.email}}
				</div>
			</section>
			<hr style="margin:8px 0px"/>
			<section style="display:flex;margin-top:1em">
				<div style="padding-left:1em;width:22em">
					{{attendee.first_name}}	{{attendee.last_name}}<br/>
					<span ng-if="attendee.business">{{attendee.business}}<br/></span>
					<span ng-if="attendee.address1">{{attendee.address1}}<br/></span>
					<span ng-if="attendee.address2">{{attendee.address2}}<br/></span>
					{{attendee.city}}, {{attendee.state}} {{attendee.zip}}
				</div>
				<div style="padding-left:.5em;flex-grow: 1">
					<b>{{eventData.name}}</b><br/>
					<span ng-show="eventData.site">{{eventData.site}}<br/></span>
					<span ng-show="eventData.city || eventData.state">
						{{eventData.city}}, {{eventData.state}}<br/>
					</span>
					<span ng-show="eventData.startdate != eventData.enddate">
						{{eventData.startdate | mySqlToLocalDate}} - {{eventData.enddate | mySqlToLocalDate}}
					</span>
					<span ng-show="eventData.startdate == eventData.enddate">
						{{eventData.startdate | mySqlToLocalDate}}
					</span>
				</div>
			</section>

			<table style="width:95%;margin:1em auto" id="orderTable">
				<thead>
					<tr>
						<th>Item</th>
						<th>Quantity</th>
						<th>Unit Price</th>
						<th class="right">Total</th>
					</tr>
				</thead>
				<tbody>
					<tr ng-if="attendee.reg_type_price > 0">
						<td>{{attendee.registration_type}}</td>
						<td>1</td>
						<td>{{attendee.reg_type_price | currency}}</td>
						<td class="right">{{attendee.reg_type_price | currency}}</td>
					</tr>
					<tr ng-repeat="xtra in attendee.xtraOrders">
						<td>{{xtra.label}}</td>
						<td>{{xtra.qty}}</td>
						<td>{{xtra.price | currency}}</td>
						<td class="right">{{(xtra.qty * xtra.price) | currency}}</td>
					</tr>
				</tbody>
				<tfoot style="border-top: 1px solid black;">
					<tr ng-show="attendee.cc_fees">
						<td colspan="3" class="right">CC Service Fee</td>
						<td class="right">{{attendee.cc_fees | currency}}</td>
					</tr>
					<tr ng-if="attendee.serviceFee">
						<td colspan="3" class="right">Service Fee</td>
						<td class="right">{{attendee.serviceFee | currency}}</td>
					</tr>
					<tr ng-if="attendee.payments">
						<td colspan="3" class="right">Total Charge</td>
						<td class="right">{{(attendee.serviceFee + attendee.price + attendee.cc_fees) | currency}}</td>
					</tr>
					<tr ng-if="attendee.payments">
						<td colspan="3" class="right">Payments Received</td>
						<td class="right">{{attendee.payments | currency}}</td>
					</tr>
					<tr ng-if="attendee.discount && attendee.discount !='0.00'">
						<td colspan="3" class="right">Discount</td>
						<td class="right">{{attendee.discount | currency}}</td>
					</tr>
					<tr>
						<td colspan="3" class="right">Balance Due</td>
						<td class="right">{{attendee.balance | currency}}</td>
					</tr>
				</tfoot>
			</table>

			<div ng-show="acctData.ccProvider != 'none' && attendee.balance > 0">
				To pay by credit card visit: {{origin}}/register.php?slug={{eventData.slug}}&confirmation={{attendee.confirmation}}
			</div>

			<div ng-show="eventData.checkinstructions">
				To pay by  check:<br/>
				<div style="white-space: pre-wrap;">{{eventData.checkinstructions}}</div>
			</div>
		</div> <!-- End Page -->
	</div>
	<!-- END CONTROLLER   -->
</body>
<style>
	.page{
		margin-left:auto;
		margin-right: auto;
		width: 8.0in;
		min-height: 880px;
	}
	.threeCol{ display:flex; align-items: center;}
	.threeCol div{ width:33%; }
	#orderTable tbody tr:last-child td{
		padding-bottom:.5em;
	}
	#orderTable tfoot tr:first-child td, #orderTable tbody tr:first-child td{
		padding-top:.5em;
	}
</style>
</html>