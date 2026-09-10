<?php 
	session_start(); 
	if($_SESSION['erSupport'] != 'true'){ header('Location: /login_er.php'); } 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EasyReg Tickets</title>
<?php include("common_functions.php");  ?>
<?php include("commonStyles.php");?>
<?php include("commonJs.php");?>
<script type="text/javascript">
	var app = angular.module('regApp', ['easyRegDataModule','erSvc']);
	app.controller('regCtrl', function($scope, $http, erSvc, dataSvc){
		$scope.status = 'open';
		$scope.ticketType = 'all'; 

		dataSvc.getTableRecords('support_tickets', '', true).then(function(res){
			$scope.tickets = res;
			angular.forEach($scope.tickets,function(ticket){
				ticket.searchString = `${ticket.description}${ticket.contact_name}${ticket.contact_email}`.toLowerCase();
				ticket.type = ticket.type == 'feature' ? 'Feature' : 'Issue';
				ticket.created_time = erSvc.mySqlToLocalDate(ticket.created_time);
			});
		});

		$scope.meetsSearch = function(ticket){
			if($scope.status != ticket.status) return false;
			if($scope.ticketType != 'all' && ticket.type != $scope.ticketType) return false;
			if(!$scope.filterTxt) return true;
			return ticket.searchString.includes($scope.filterTxt.toLowerCase());
		};

		$scope.changeStatus = function(ticket){
			ticket.status = ticket.status == 'open' ? 'closed' : 'open';
			let newRec = {"id":ticket.id,"status":ticket.status};
			dataSvc.createOrUpdateRecord({"table":"support_tickets","record":newRec});
		};
	}); //End Controller
</script>
</head>
<body ng-app="regApp" style="padding-top: 5px;">
<div ng-controller="regCtrl" style="padding:0px 3em">
	<div style="display:flex">
		<h1 style="margin-top:0px;flex:50%">EasyRegPro Issues/Requests</h1>
		<section style="flex:48%;text-align:right">
			<a href="/accounts.php"><button class="btn btn-primary"> Account List</button></a>
			<a href="" onclick="erUtils.logout(); return false;">
				<button class="btn btn-primary"> <span class="bi bi-box-arrow-left"></span> Log Out</button>
			</a>
		</section>
	</div>

	<div style="display:flex">
		<label style="font-weight: bold;font-size: large; flex:30%"></label>
		<input type="text" class="form-control" ng-model="filterTxt" style="flex:30%" placeholder="Quick Search" />
		<div style="flex:30%;text-align:center">
			<b>Status</b>
			<select ng-model="status" style="margin-right:3em">
				<option value="open">Open</option>
				<option value="closed">Closed</option>
			</select>
			<b>Type</b>
			<select ng-model="ticketType">
				<option value="all">All</option>
				<option value="Feature">Feature</option>
				<option value="Issue">Issue</option>
			</select>
		</div>
	</div>
	<table class="table scrollable striped" style="margin-top:.5em">
		<thead>
			<tr class="headerRow sticky noPad">
				<th>Type</th>
				<th>Submitted</th>
				<th>Created By</th>
				<th>Email</th>
				<th>Phone</th>
				<th>Description</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<tr ng-repeat="ticket in tickets | orderObjectBy:'created_time'" ng-if="meetsSearch(ticket)">
			    <td>{{ticket.type}}</td>
			    <td>{{ticket.created_time}}</td>
			    <td style="white-space: nowrap;">{{ticket.contact_name}}</td>
			    <td style="white-space: nowrap;">{{ticket.contact_email}}</td>
			    <td style="white-space: nowrap;">{{ticket.contact_phone}}</td>
			    <td>
			    	<div style="white-space: pre-wrap" ng-show="!ticket.viewAll"> {{ticket.description | truncate:500}}</div>
			    	<div style="white-space: pre-wrap" ng-show="ticket.viewAll"> {{ticket.description}}</div>
			    	<button class="btn btn-primary btn-sm" ng-click="ticket.viewAll = true" 
			    		ng-show="ticket.description.length > 500 && !ticket.viewAll" 
			    		title="View Entire Comment">
			    		<span class="bi bi-eye"></span>
			    	</button>
			    	<button class="btn btn-danger btn-sm" ng-click="ticket.viewAll = false" ng-show="ticket.viewAll" 
			    		title="Truncate Comment">
			    		<span class="bi bi-eye-slash"></span>
			    	</button>
			    </td>
			    <td>
			    	<button class="btn btn-danger btn-xs bold" ng-click="changeStatus(ticket)" ng-show="ticket.status == 'open'" 
			    		title="Close Ticket"> -
			    	</button>
			    	<button class="btn btn-success btn-xs bold" ng-click="changeStatus(ticket)" ng-show="ticket.status == 'closed'"
			    		title="Re-Open Ticket"> +
			    	</button>
			    </td>
			</tr>
		</tbody>
	</table>
</div> <!-- End Controller -->
<er-Footer />
</body>
</html>
