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
<title>EasyReg Accounts</title>
<?php include("common_functions.php");  ?>
<?php include("commonStyles.php");?>
<?php include("commonJs.php");?>
<script type="text/javascript">
	var app = angular.module('regApp', ['easyRegDataModule','erSvc']);
	app.controller('regCtrl', function($scope, $http, erSvc, dataSvc){
		$scope.accStatus = 'enabled';
		dataSvc.getTableRecords('accounts', '', true).then(function(res){
			$scope.accounts = res;
			angular.forEach($scope.accounts,function(acct){
				acct.searchString = `${acct.name}${acct.contact_name}${acct.contact_email}`.toLowerCase();
				acct.disabled = acct.disabled == '1';
				acct.trial = acct.trial == '1';
				acct.feeHistory = [];
			});
			dataSvc.getTableRecords('account_fee_structure').then(function(res){
				res.forEach(function(acctFees){
					let acct = $scope.accounts[acctFees.accountid];
					if(!acct) return;
					acct.feeHistory.push(acctFees);
					if(erSvc.dateRangeIsCurrent(acctFees.start_date, acctFees.end_date)){
						acct.reg_fee = acctFees.reg_fee;
						acct.reg_fee_method = acctFees.reg_fee_method;
						acct.reg_charge_to = acctFees.reg_charge_to;
						acct.reg_charge_zero_items = acctFees.reg_charge_zero_items == '1';
						acct.ticket_fee = acctFees.ticket_fee;
						acct.ticket_fee_method = acctFees.ticket_fee_method;
						acct.ticket_charge_to = acctFees.ticket_charge_to;
						acct.ticket_charge_zero_items = acctFees.ticket_charge_zero_items == '1';
					}
				});
			});
			checkForTrial();
		});

		dataSvc.getTableRecords('support_tickets', "status = 'open'").then( res => $scope.openTickets = res.length );

		$scope.meetsSearch = function(acct){
			if(($scope.accStatus=='enabled' && acct.disabled) || ($scope.accStatus=='disabled' && !acct.disabled)) return false;
			if(!$scope.filterTxt) return true;
			return acct.searchString.includes($scope.filterTxt.toLowerCase());
		};

		$scope.acctLogin = function(acct){
			erSvc.loadingDialog();
			$http({
				"url": 'login_process_er_acct.php',
				"method": 'POST',
				"data": $.param({"accountid":acct.id}),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
			}).then(function(res){
				if(res.data != 'success'){
					erSvc.closeLoading();
					erSvc.easyRegAlert({"text":"Error logging into account.","title":"Error"});
					return;
				}

				dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
					if(resp[0]){
						let userData = resp[0];
						for(prop in userData){
							if(userData[prop]) userData[prop] = (userData[prop] || '').toString().replace(/\"/g, '');
						}
						$http({
							"url": '/setUserData.php',
							"method": 'POST',
							"data": $.param({"userData":userData,"accountLogo":acct.web_logo,"accountEventsFor":acct.id}),
							"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
						}).then(res => window.location = 'admin.php' );
					}
				});
			});
		};

		$scope.editAcct = function(acct){
			$('form[name="accountForm"]').removeClass('submitted');
			$scope.curAcct = acct ? angular.copy(acct) : {};
			$scope.dialogTitle = acct ? `Edit ${acct.name}` : 'New Account';
			$('#editAcctountDialog').show(500);
			$scope.curAcct.type = $scope.curAcct.type || 'event';
		};

		$scope.saveAccount = function(){
			$('form[name="accountForm"]').addClass('submitted');
			if(!$scope.accountForm.$valid) return;
			if(!$scope.curAcct.id){	//creating
				let acctData = {...$scope.curAcct};
				acctData.trial = acctData.trial ? '1' : '0';
				$http({
					"url": '/create_account.php',
					"method": 'POST',
					"data": $.param(acctData),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				}).then(function(response){
					let newAccount = angular.copy($scope.curAcct);
					newAccount.id = response.data;
					newAccount.reg_fee = '0';
					newAccount.reg_fee_method = 'flat';
					newAccount.reg_charge_to = 'attendee';
					newAccount.reg_charge_zero_items = '0';
					newAccount.ticket_fee = '0';
					newAccount.ticket_fee_method = 'flat';
					newAccount.ticket_charge_to = 'attendee';
					newAccount.ticket_charge_zero_items = '0';
					$scope.accounts[response.data] = newAccount;
					$scope.closeRightDialog();
					if(newAccount.type == 'ticketing') configTicketingAcct(newAccount);
					let newFee = angular.copy(blankFeeStatus);
					newFee.accountid = response.data;
					newFee.start_date = erSvc.localToMySqlDate('01/01/2000');
					newFee.end_date = erSvc.localToMySqlDate('01/01/2399');
					//create account_fee_status record
					$http({
						"url": 'updateAcctFee.php',
						"method": 'POST',
						"data": $.param(newFee),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
					}).then(function(res){
						newFee.id = res.data;
						newFee.start_date = erSvc.mySqlToLocalDate(newFee.start_date);
						newFee.end_date = erSvc.mySqlToLocalDate(newFee.end_date);
						newAccount.feeHistory = [newFee];
					});
					checkForTrial();
				});
			}else{ //updating
				$http({
					"url": 'changeAcctStatus.php',
					"method": 'POST',
					"data": $.param({
						"accountid":$scope.curAcct.id,
						"name":$scope.curAcct.name,
						"slug":$scope.curAcct.slug,
						"contact_name":$scope.curAcct.contact_name,
						"contact_email":$scope.curAcct.contact_email,
						"contact_phone":$scope.curAcct.contact_phone,
						"disabled":$scope.curAcct.disabled ? '1': '0',
						"trial":$scope.curAcct.trial ? '1': '0',
						"sis":$scope.curAcct.sis,
						"type":$scope.curAcct.type,
						"cc_charge_rt":$scope.curAcct.cc_charge_rt
					}),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
				}).then(function(){
					$scope.accounts[Number($scope.curAcct.id)] = angular.copy($scope.curAcct)
					$scope.closeRightDialog();
					checkForTrial();
				});
			}
		};

		$scope.editFees = function(acct){
			$scope.selectedAcct = acct;
			$scope.curFeeHistory = angular.copy(acct.feeHistory);
			$('form[name="feeForm"]').removeClass('submitted');
			$scope.dialogTitle = `Edit ${acct.name} Fees`;
			$('#editFeesDialog').show(500);
		};

		let blankFeeStatus = {
			"reg_fee": "0",
			"reg_fee_method": "flat",
			"reg_charge_to": "attendee",
			"reg_charge_zero_items": "0",
			"ticket_fee": "0",
			"ticket_fee_method": "flat",
			"ticket_charge_to": "attendee",
			"ticket_charge_zero_items": "0",
			"start_date": "",
			"end_date": ""
		};

		$scope.addFeeStatus = function(){
			let newFee = angular.copy(blankFeeStatus);
			newFee.accountid = $scope.selectedAcct.id;
			$scope.curFeeHistory.push(newFee);
		};

		$scope.saveFees = function(){
			$('form[name="feeForm"]').addClass('submitted');
			if(!$scope.feeForm.$valid) return;
			//check for overlapping dates
			let maxEndDate, minStartDate;
			let overlapFound = false;
			$scope.curFeeHistory.forEach(function(fee){
				if(!maxEndDate) maxEndDate = fee;
				if(!minStartDate) minStartDate = fee;
				if(erSvc.localToMySqlDate(fee.start_date) < erSvc.localToMySqlDate(minStartDate.start_date)){
					minStartDate = fee;
				}
				if(erSvc.localToMySqlDate(fee.end_date) > erSvc.localToMySqlDate(maxEndDate.end_date)){
					maxEndDate = fee;
				}
				$scope.curFeeHistory.forEach(function(fee2){
					if(fee == fee2) return;
					if(hasDateOverlap(fee, fee2)) overlapFound = true;
				});
			});
			if(overlapFound){
				erSvc.easyRegAlert({"text":"Please ensure there are no date overlaps","title":"Error"});
				return;
			}
			//set min and max start dates to arbitrarilly high/low values
			minStartDate.start_date = '01/01/2000';
			maxEndDate.end_date = '01/01/2399';

			//save account fee history
			erSvc.loadingDialog();
			$scope.selectedAcct.feeHistory = angular.copy($scope.curFeeHistory);
			let completedCalls = 0;
			$scope.selectedAcct.feeHistory.forEach(function(fee){
				let feeData = {...fee};
				feeData.start_date = erSvc.localToMySqlDate(fee.start_date);
				feeData.end_date = erSvc.localToMySqlDate(fee.end_date);
				$http({
					"url": 'updateAcctFee.php',
					"method": 'POST',
					"data": $.param(feeData),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
				}).then(function(res){
					fee.id = res.data;	
					if(++completedCalls == $scope.selectedAcct.feeHistory.length){
						erSvc.closeLoading();
						$scope.closeRightDialog();
					}
				});
				if(erSvc.dateRangeIsCurrent(fee.start_date, fee.end_date)){
					$scope.selectedAcct.reg_fee = fee.reg_fee;
					$scope.selectedAcct.reg_fee_method = fee.reg_fee_method;
					$scope.selectedAcct.reg_charge_to = fee.reg_charge_to;
					$scope.selectedAcct.reg_charge_zero_items = fee.reg_charge_zero_items == '1';
					$scope.selectedAcct.ticket_fee = fee.ticket_fee;
					$scope.selectedAcct.ticket_fee_method = fee.ticket_fee_method;
					$scope.selectedAcct.ticket_charge_to = fee.ticket_charge_to;
					$scope.selectedAcct.ticket_charge_zero_items = fee.ticket_charge_zero_items == '1';
				}
			});
		}; //End saveFees()

		function hasDateOverlap(fee1, fee2){
			fee1.start = erSvc.localToMySqlDate(fee1.start_date);
			fee1.end = erSvc.localToMySqlDate(fee1.end_date);
			fee2.start = erSvc.localToMySqlDate(fee2.start_date);
			fee2.end = erSvc.localToMySqlDate(fee2.end_date);
			if(fee1.start >= fee2.start && fee1.start <= fee2.end) return true;
			if(fee1.end >= fee2.start && fee1.end <= fee2.end) return true;
			return false;
		}

		//ticketing accounts have all optional reg fields disabled by default
		function configTicketingAcct(acct){
			let config = {
				"accountid":acct.id,
				"name":"regScreenConfig",
				"value":{
					"email":{"name":"Email","hide":false,"label":"Email"},
					"first_name":{"name":"First Name","hide":false,"label":"First Name"},
					"last_name":{"name":"Last Name","hide":false,"label":"Last Name"},
					"title":{"name":"Position/Title","hide":true,"label":"Position/Title"},
					"business":{"name":"Business","hide":true,"label":"Business"},
					"address":{"name":"Business Address","hide":true,"label":"Business Address"},
					"phone":{"name":"Business Phone","hide":true,"label":"Business Phone"},
					"vendor_access":{"name":"Share Info With Vendors","hide":true,"label":"Share my information with vendors"},
					"web_address":{"name":"Web Address","hide":true,"label":"Web Address (Optional)"},
					"dietary_restrictions":{"name":"Dietary Restrictions","hide":true,"label":"Dietary Restrictions"},
					"ec1":{"name":"Emergency Contact 1","hide":true,"label":"Emergency Contact 1"},
					"ec2":{"name":"Emergency Contact 2","hide":true,"label":"Emergency Contact 2"}
				}
			};
			config.value = angular.toJson(config.value);
			dataSvc.createOrUpdateRecord({"table":"preferences","record":config});
			let menuSettings = {
				"accountid":acct.id,
				"name":"attendeeMenu",
				"value":{
					"Event Home":{"label":"Event Home","disabled":false},
					"Contact":{"label":"Contact","disabled":false},
					"Register":{"label":"Purchase Tickets","disabled":false},
					"Manage Registration":{"label":"Manage Order","disabled":false},
					"Event Tickets":{"label":"Event Tickets","disabled":false}
				}
			}
			menuSettings.value = angular.toJson(menuSettings.value);
			dataSvc.createOrUpdateRecord({"table":"preferences","record":menuSettings});
		}

		function checkForTrial(){
			$scope.hasTrialAccct = false;
			for(id in $scope.accounts){
				if($scope.accounts[id].trial){
					$scope.hasTrialAccct = true;
					break;
				}
			}
		}

		$scope.closeRightDialog = () => $('.dialogRight').hide(500);
	}); //End Controller
</script>
</head>
<body ng-app="regApp" style="padding-top: 5px;">
<div ng-controller="regCtrl" style="padding:0px 3em">
	<section style="display:flex">
		<h1 style="margin-top:0px;flex:50%">The Event Phoenix Support Portal</h1>
		<section style="flex:48%;text-align:right">
			<a href="/supportTickets.php">
				<button class="btn btn-primary"> {{openTickets}} Open Tickets</button>
			</a>
			<a href="" onclick="erUtils.logout(); return false;">
				<button class="btn btn-primary"><i class="bi bi-box-arrow-left"></i> Log Out</button>
			</a>
		</section>
	</section>

	<div style="display:flex">
		<label style="font-weight: bold;font-size: large; flex:30%">Accounts</label>
		<input type="text" class="form-control" ng-model="filterTxt" style="flex:30%" placeholder="Quick Search" />
		<div style="flex:30%;text-align:right">
			<label>Enabled <input type="radio" name="enabled" ng-model="accStatus" value="enabled" /> </label>&nbsp;&nbsp;
			<label>Disabled <input type="radio" name="enabled" ng-model="accStatus" value="disabled" /> </label>
			<button class="btn btn-success btn-sm" ng-click="editAcct()" style="margin:0px 1em">
				<i class="bi bi-plus-circle"></i> New Account
			</button>
		</div>
	</div>
	<table class="table scrollable striped" style="margin-top:.5em">
		<thead>
			<tr class="headerRow sticky noPad">
				<th>Name</th>
				<th>Slug</th>
				<th>Contact Name</th>
				<th>Email</th>
				<th>Phone</th>
				<th>SIS</th>
				<th>Type</th>
				<th>CC Rate</th>
				<th>Edit</th>
				<th>Log In</th>
				<th>Reg Fee</th>
				<th>Tix Fee</th>
				<th>Edit Fees</th>
			</tr>
		</thead>
		<tbody>
			<tr ng-repeat="acct in accounts | orderObjectBy:'name'" ng-if="meetsSearch(acct) && !acct.trial">
				<td>{{acct.name}}</td>
				<td>{{acct.slug}}</td>
				<td>{{acct.contact_name}}</td>
				<td>{{acct.contact_email}}</td>
				<td>{{acct.contact_phone}}</td>
				<td>{{acct.sis}}</td>
				<td>
					{{acct.type == 'event' ? 'Event Management' : 'Ticketing'}}
				</td>
				<td>{{acct.cc_charge_rt}}%</td>
				<td>
					<button class="btn btn-primary btn-xs" ng-click="editAcct(acct)" title="Edit Account">
						<span class="bi bi-pencil-fill"></span>
					</button>
				</td>
				<td>
					<button class="btn btn-primary btn-xs" ng-click="acctLogin(acct)" title="Log In To Account">
						<span class="bi bi-box-arrow-in-right"></span>
					</button>
				</td>
				<td>
					<span ng-show="acct.reg_fee_method == 'flat'">{{acct.reg_fee | currency}}</span>
					<span ng-show="acct.reg_fee_method == 'pct'">{{acct.reg_fee | number:2}}%</span>
				</td>
				<td>
					<span ng-show="acct.ticket_fee_method == 'flat'">{{acct.ticket_fee | currency}}</span>
					<span ng-show="acct.ticket_fee_method == 'pct'">{{acct.ticket_fee | number:2}}%</span>
				</td>
				<td>
					<button class="btn btn-primary btn-xs" ng-click="editFees(acct)" title="Edit Fees">
						<span class="bi bi-pencil-fill"></span>
					</button>
				</td>
			</tr>
		</tbody>
	</table>

	<section ng-show="hasTrialAccct">
		<label style="font-weight: bold;font-size: large; flex:30%">Trial Accounts</label>
		<table class="table scrollable striped" style="margin-top:.5em">
			<thead>
				<tr class="headerRow sticky noPad">
					<th>Name</th>
					<th>Slug</th>
					<th>Contact Name</th>
					<th>Email</th>
					<th>Phone</th>
					<th>Reg Fee</th>
					<th>Tix Fee</th>
					<th>SIS</th>
					<th>Type</th>
					<th>Edit</th>
					<th>Log In</th>
				</tr>
			</thead>
			<tbody>
				<tr ng-repeat="acct in accounts | orderObjectBy:'name'" 
					ng-if="meetsSearch(acct) && acct.trial == '1'">
					<td>{{acct.name}}</td>
					<td>{{acct.slug}}</td>
					<td>{{acct.contact_name}}</td>
					<td>{{acct.contact_email}}</td>
					<td>{{acct.contact_phone}}</td>
					<td>
						<span ng-show="acct.reg_fee_method == 'flat'">{{acct.reg_fee | currency}}</span>
						<span ng-show="acct.reg_fee_method == 'pct'">{{acct.reg_fee | number:2}}%</span>
					</td>
					<td>
						<span ng-show="acct.ticket_fee_method == 'flat'">{{acct.ticket_fee | currency}}</span>
						<span ng-show="acct.ticket_fee_method == 'pct'">{{acct.ticket_fee | number:2}}%</span>
					</td>
					<td>{{acct.sis}}</td>
					<td>
						{{acct.type == 'event' ? 'Event Management' : 'Ticketing'}}
					</td>
					<td>
						<button class="btn btn-primary btn-xs" ng-click="editAcct(acct)" title="Edit Account">
							<span class="bi bi-pencil-fill"></span>
						</button>
					</td>
					<td>
						<button class="btn btn-primary btn-xs" ng-click="acctLogin(acct)" title="Log In To Account">
							<span class="bi bi-box-arrow-in-right"></span>
						</button>
					</td>
				</tr>
			</tbody>
		</table>
	</section>

	<!-- Edit Account Dialog -->
	<div id="editAcctountDialog" class="dialogRight">
		<div class="dialogTitle">{{dialogTitle}}</div>
		<div class="dialogContents">
			<form name="accountForm">
				<table>
					<tr>
						<td class="bold">Name</td>
						<td><input type="text" class="form-control" ng-model="curAcct.name" required /></td>
					</tr>
					<tr>
						<td class="bold">Slug</td>
						<td><input type="text" class="form-control" ng-model="curAcct.slug" required /></td>
					</tr>
					<tr>
						<td class="bold">Contact Name</td>
						<td><input type="text" class="form-control" ng-model="curAcct.contact_name" required /></td>
					</tr>
					<tr>
						<td class="bold">Email</td>
						<td><input type="email" class="form-control" ng-model="curAcct.contact_email" required /></td>
					</tr>
					<tr>
						<td class="bold">Phone</td>
						<td><input type="text" class="form-control" ng-model="curAcct.contact_phone" required /></td>
					</tr>
					<tr>
						<td class="bold">SIS</td>
						<td><input type="text" class="form-control" ng-model="curAcct.sis" /></td>
					</tr>
					<tr>
						<td class="bold">Type</td>
						<td>
							<select class="form-select" ng-model="curAcct.type">
								<option value="event">Event Management</option>
								<option value="ticketing">Ticketing</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="bold">CC Rate</td>
						<td>
							<input type="text" class="form-control" ng-model="curAcct.cc_charge_rt" 
								style="display:inline-block;width:5em"/> <b>%</b>
						</td>
					</tr>
					<tr>
						<td class="bold">Trial</td>
						<td><input type="checkbox" ng-model="curAcct.trial" /></td>
					</tr>
					<tr>
						<td class="bold">Disabled</td>
						<td><input type="checkbox" ng-model="curAcct.disabled" /></td>
					</tr>
				</table>
			</form>
			<div class="button-row" style="margin-top:.5em">
				<button class="btn btn-primary" ng-click="closeRightDialog()">
					Cancel
				</button>
				<button class="btn btn-success" ng-click="saveAccount()">
					Save
				</button>
			</div>
		</div>
	</div> <!-- End editAcctountDialog -->

	<!-- Edit Fees Dialog -->
	<div id="editFeesDialog" class="dialogRight">
		<div class="dialogTitle">{{dialogTitle}}</div>
		<div class="dialogContents">
			<form name="feeForm">
				<table>
					<thead>
						<th style="min-width:5em">Start</th>
						<th style="min-width:5em">End</th>
						<th style="min-width:6em">Reg Fee</th>
						<th style="min-width:9em">Reg <br/> Method</th>
						<th>Reg <br/> Charge To</th>
						<th>Reg <br/> Charge $0 Items</th>
						<th style="min-width:6em">Ticket Fee</th>
						<th style="min-width:9em">Ticket <br/> Method</th>
						<th>Ticket <br/> Charge To</th>
						<th>Ticket <br/> Charge $0 Items</th>
					</thead>
					<tr ng-repeat="config in curFeeHistory">
						<td>
							<input datepicker class="form-control" ng-model="config.start_date" required/>
						</td>
						<td>
							<input datepicker class="form-control" ng-model="config.end_date" required/>
						</td>
						<td>
							<input type="number" class="form-control" step=".01" min="0"
								ng-model="config.reg_fee" string-to-number/>
						</td>
						<td>
							<select class="form-select" ng-model="config.reg_fee_method">
								<option value="flat">Flat Rate</option>
								<option value="pct">Percent</option>
							</select>
						</td>
						<td>
							<select class="form-select" ng-model="config.reg_charge_to" style="width:11em">
								<option value="attendee">Attendee</option>
								<option value="account">Account</option>
							</select>
						</td>
						<td>
							<input type="checkbox" ng-model="config.reg_charge_zero_items" 
								ng-true-value="'1'" ng-false-value="'0'"/>
						</td>
						<td>
							<input type="number" class="form-control" step=".01" min="0"
								ng-model="config.ticket_fee" string-to-number/>
						</td>
						<td>
							<select class="form-select" ng-model="config.ticket_fee_method">
								<option value="flat">Flat Rate</option>
								<option value="pct">Percent</option>
							</select>
						</td>
						<td>
							<select class="form-select" ng-model="config.ticket_charge_to" style="width:11em">
								<option value="attendee">Attendee</option>
								<option value="account">Account</option>
							</select>
						</td>
						<td>
							<input type="checkbox" ng-model="config.ticket_charge_zero_items" 
								ng-true-value="'1'" ng-false-value="'0'"/>
						</td>
					</tr>
				</table>
			</form>
			<div class="button-row" style="margin-top:.5em">
				<button class="btn btn-primary" ng-click="closeRightDialog()">
					Cancel
				</button>
				<button class="btn btn-primary" ng-click="addFeeStatus()">
					<i class="bi bi-plus-circle"></i> Add Fee Status
				</button>
				<button class="btn btn-success" ng-click="saveFees()">
					Save
				</button>
			</div>
		</div>
	</div> <!-- End editAcctountDialog -->
</div> <!-- End Controller -->
<style>
	.inlineInputs select.form-control, .inlineInputs input.form-control {
		display: inline-block;
		width:8em;
	}
</style>
<er-Footer />
</body>
</html>
