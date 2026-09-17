<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PSUGevents.com</title>
<?php 
	include("commonStyles.php"); 
 	include("common_functions.php");
 	include("commonJs.php");
	$inputs = sanitize_inputs($_REQUEST);
	if (substr($_SERVER['HTTP_HOST'], 0, 4) == "easy"){
		print("<script language=\"javascript\" src=\"https://app.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
	}
	else{
		print("<script language=\"javascript\" src=\"https://sandbox.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
	} 
?>
<style>
	.alert{ width: 80%; float:right; }
	.grid{ display: grid; grid-template-columns: 33% 33% 33%; }
	#tokenizer-container{ margin-top: 2em; border-top: 1px solid silver; }
</style>
<script type="text/javascript">
	let eventInfo;
	<?php 
		require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound registration lookup
		$rid = (int)($_REQUEST['rid'] ?? 0); // Bound
		try { // PDO event logos; never interpolate rid
			$pdo = tep_dml_pdo(); // utf8mb4
			$stmt = $pdo->prepare('SELECT
					events.logo,
					accounts.web_logo,
					events.replytoemail,
					accounts.id AS accountid,
					accounts.cc_charge_rt,
					events.apply_cc_fee
				FROM registrations
				JOIN events ON registrations.eventid = events.id
				JOIN accounts ON accounts.id = events.accountid
				WHERE registrations.id = :rid
				LIMIT 1'); // Bound
			$stmt->execute(array('rid' => $rid)); // Posted registration
			$attendeeInfo = $stmt->fetch(PDO::FETCH_ASSOC); // One row
			if ($attendeeInfo) { // Found
				print('eventInfo = ' . json_encode($attendeeInfo) . ';'); // Unchanged AngularJS assign
			}
		} catch (Throwable $payPgEx) { // Connect
			error_log('TEP payRegistration lookup failed: ' . $payPgEx->getMessage()); // Log only
		}
	?>

	var app = angular.module('regApp', ['easyRegDataModule','erSvc', 'navMod']);
	app.controller('regController', function($scope, $http, dataSvc, erSvc) {
		if(eventInfo){
			$scope.eventLogo = eventInfo.logo;
			$scope.accountLogo = eventInfo.web_logo;
		}

		$scope.ccProvider = 'none';
		dataSvc.getArray({'query':'ccProvider'}).then(function(cc){
			if(cc[0] && cc[0].ccProvider) $scope.ccProvider = cc[0].ccProvider;
			if($scope.ccProvider == 'basys') initializeBasysToken(cc[0].apiKey);
		});

		//Credit Card Functionality
		$scope.card = {};
		$scope.card.expYear = new Date().getFullYear();
		$scope.years = [$scope.card.expYear];
		for (var i=1; i<10; i++){
			$scope.years.push($scope.card.expYear + i);
		}

		$scope.card.expMonth = '01';
		$scope.months = ['01','02','03','04','05','06','07','08','09','10','11','12'];
		var regid = new URL(location).searchParams.get('rid');
		dataSvc.getTableRecords('registrations', 'id = ' + regid).then(function(res){
			let reg = res[0];
			dataSvc.getRegistrationData(reg.confirmation, reg.eventid).then(function(reg){
				if(reg){
					$scope.totalDue = Number(reg.price);
					angular.forEach(reg.extraOrders, xtra => $scope.totalDue += (xtra.price * xtra.quantity));
					$scope.totalDue -= Number((reg.payments || '0'));
					$scope.ccServiceFee = 0;
					if(eventInfo.apply_cc_fee == '1'){
						let rate = Number(eventInfo.cc_charge_rt)/100;
						$scope.ccServiceFee = Math.round($scope.totalDue * rate * 100) / 100;
					}
					$scope.totalDue += $scope.ccServiceFee;
					$scope.card.amount = Number($scope.totalDue.toFixed(2));
					reg.payments = Number(reg.payments);
					$scope.reg = reg;
					$scope.invalidRegNum = false;
				}else{
					$scope.invalidRegNum = true;
				}
			});
		});

		$scope.states = erSvc.getStateOptions();

		let regInfo;
		$scope.submitPayment = function(){
			$scope.paymentSuccess = false;
			$scope.paymentFailure = false;
			$scope.paymentAmount = $scope.card.amount;
			$scope.authMessage = '';
			$scope.paymentMessage = '';
			erSvc.loadingDialog("Processing Payment");
			
			regInfo = [{
				"id":$scope.reg.id,
				"confirmation":$scope.reg.confirmation,
				"total":Number($scope.card.amount),
				"cc_fee":$scope.ccServiceFee || '0'
			}];
			
			if($scope.ccProvider == 'basys'){
				basysToken.submit();
				return;
			}

			//if we're still here, we're using MagicWrighter
			var submitData = {
				"name":$scope.card.name,
				"email":$scope.card.email,
				"street":$scope.card.street,
				"city":$scope.card.city,
				"state":$scope.card.state,
				"zip":$scope.card.zip,
				"amount":Number($scope.card.amount).toFixed(2),
				"cardNumber":$scope.card.number,
				"cvv":$scope.card.cvv,
				"expiration":$scope.card.expMonth + $scope.card.expYear.toString().substr(-2),
				"regConfirmation":$scope.reg.confirmation,
				"regId":$scope.reg.id,
				"accountid":eventInfo.accountid,
				"registrations":JSON.stringify(regInfo)
			};

			$http({
				"url": '/process_payment.php',
				"method": 'POST',
				"data": $.param(submitData),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
			}).then(function(response){
				try{
					var paymentInfo = response.data.paymentDetails.PaymentInfo;
					var authInfo = response.data.paymentDetails.AuthInfo;
					if(paymentInfo['@attributes'].ErrorCode == 0){
						$scope.paymentSuccess = true;
						$scope.paymentConfirmation = paymentInfo['@attributes'].ConfirmationNumber;
						$scope.reg.payments = Number($scope.reg.payments || 0) +
							$scope.paymentAmount;
						sendConfirmationEmail();
						$scope.totalDue = 0;
					}else{
						$scope.paymentFailure = true;
						$scope.authMessage = authInfo['@attributes'].Message;
						$scope.paymentMessage = paymentInfo['@attributes'].PaymentMessage;
					}
				}catch(e){
					$scope.paymentFailure = true;
				}
				erSvc.closeLoading();
			});
		}

		function processBasysPymt(resp){
			if(resp.status == 'success'){
				let email = resp.user.email || '';
				$scope.card.email = email;
				let submitData = {
					"amount": ($scope.totalDue * 100),
					"order_id":$scope.reg.confirmation,
					"email":email,
					"token":resp.token,
					"description": $scope.reg.event_name + " registration",
					"first_name": resp.user.first_name,
					"last_name": resp.user.last_name,
					"address_line_1": resp.billing.address,
					"city": resp.billing.city,
					"state": resp.billing.state,
					"postal_code": resp.billing.zip,
					"country": resp.billing.country,
					"accountid": eventInfo.accountid,
					"registrations": JSON.stringify(regInfo)
				}
				$http({
					"url": '/process_basys_pymt.php',
					"method": 'POST',
					"data": $.param(submitData),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				}).then(function(res){
					res = res.data;
					erSvc.closeLoading();
					if(res.status == 'success' && res.data && res.data.status == 'pending_settlement'){
						$scope.paymentSuccess = true;
						$scope.paymentConfirmation = res.data.id;
						$scope.reg.payments = Number($scope.reg.payments || 0) + $scope.paymentAmount;
						sendConfirmationEmail();
						$scope.totalDue = 0;
					}else{
						$scope.paymentFailure = true;
						$scope.authMessage = res.data.response;
					}
					erSvc.closeLoading();
				});
			}else{
				$scope.paymentFailure = true;
				if(resp.invalid) $scope.authMessage = "Invalid: " + resp.invalid;
				$scope.$apply();
				erSvc.closeLoading();
			}	
		}

		let basysToken;
		function initializeBasysToken(apiKey){
			basysToken = new Tokenizer({
				apikey: apiKey,
				container: '#tokenizer-container',
				submission: resp => processBasysPymt(resp),
				settings: {
					payment: {
						calculateFees: true,
						showTitle: true,
						placeholderCreditCard: '0000 0000 0000 0000',
						showExpDate: true,
						showCVV: true,
						card: {
							strict_mode: true, // Set to true to allow for 19 digit cards
							requireCVV: true // Default false - true to require cvv
						},
					},
					user: {
						showInline: true,
						showName: true,
						showEmail: true,
						showPhone: false,
						showTitle: false
					},
					billing: {
						show: true,
						showTitle: true
					},
					styles: {
						"#tokenizer-form": {
							"background-color": "white",
							'padding': "10px",
						},
						'input': { 'margin': '4px' },
						"#card .fieldset": { 'padding': "0px" },
						'svg': {'color': "maroon" },
						'.cvv-input': {	'margin-left': '10px','width':'89%' },
						'.exp-input': {	'margin-left': '10px', 'width': '89%'}
					}
				}
			});
		}

		function sendConfirmationEmail(){
			var emailBody = "Your payment for the " + $scope.reg.event_name + " has been received. <br/>";
			emailBody += "Please keep this message for your records. <br/>" + "<b>Event Conformation:</b> ";
			emailBody += $scope.reg.confirmation + "<br/>";
			emailBody += "<b>Payment Confirmation Number: </b>" + $scope.paymentConfirmation +  "<br/>";
			emailBody += "<b>Payment Total: $" +  Number($scope.card.amount).toFixed(2);
			erSvc.sendEmail($scope.card.email, "Thank You For Your Payment", emailBody,
				eventInfo.replytoemail);
		}

		function processRegistrationExtras(registration){
			var selectedExtras = [];
			var startingExtras = angular.copy($scope.initialExtras);
			$scope.initialExtras = [];
			angular.forEach($scope.regType.extraOptions,function(extra){
				if(extra.selected) selectedExtras.push(extra.id);
			});

			//Add New Extras
			var newExtra, found;
			angular.forEach(selectedExtras,function(extra){
				found = false;
				angular.forEach(startingExtras,function(initial){
					if(extra == initial.extraId){
						found = true;
						$scope.initialExtras.push(initial);
					}
				});
				if(!found){
					newExtra = {
						"registrationid": registration.id,
						"registration_extras_id": extra
					};
					dataSvc.createOrUpdateRecord({"table":"registration_extra_orders","record":newExtra}).then(function(res){
							$scope.initialExtras.push({"extraId":extra,"orderId":res});
					});
				}
			});

			//Remove necessary extras
			angular.forEach(startingExtras,function(starting){
				if(selectedExtras.indexOf(starting.extraId) < 0){
					dataSvc.deleteRecord({"table":"registration_extra_orders","id":starting.orderId});
				}
			});
		}

	});//end controller
</script>
</head>
<body ng-app="regApp">
	<div class="container-fluid col-sm-12" ng-controller="regController">
		<div class="grid" style="margin-bottom:2em">
		<div><img ng-src="{{accountLogo}}" style="max-height: 100px;" /></div>
		<H1>{{reg.event_name}}</H1>
		<div>
			<H4>Attendee - {{reg.first_name}} {{reg.last_name}}</H4>
			<table>
				<caption>Order Details</caption>
				<tr>
					<td colspan="2">Conference Registration ({{reg.registration_type}})</td>
					<td style="padding-left:2em" class="right">{{reg.price | currency}}</td>
				</tr>
				<tr ng-show="ccServiceFee">
					<td colspan="2">CC Service Fee</td>
					<td style="padding-left:2em" class="right">{{ccServiceFee | currency}}</td>
				</tr>
				<tr ng-repeat="order in reg.extraOrders">
					<td>{{order.label}}</td>
					<td>
						<span ng-show="order.quantity > 1">
							({{order.quantity}} @ {{order.price | currency}})
						</span>
					</td>
					<td style="padding-left:2em" class="right">{{(order.price * order.quantity) | currency}}</td>
				</tr>
				<tr ng-if="reg.payments">
					<td colspan="2">Payments Received</td>
					<td style="padding-left:2em" class="right">({{reg.payments | currency}})</td>
				</tr>
				<tr class="bold">
					<td colspan="2">Total Due</td>
					<td style="padding-left:2em" class="right">{{totalDue | currency}}</td>
				</tr>
			</table>
		</div>
		</div>
		<div id="tokenizer-container" style="width:93%;margin:auto"></div>
		<form name="paymentForm" id="paymentForm" style='width:80%;margin:auto'>
			<table id="ccTable" class="table" ng-show="ccProvider=='magicWrighter'">
				<tr>
					<td class="bold" style="border-bottom:1px solid black" colspan="2">Card Information</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<i ng-show="totalDue > 5000">
							Maximum $5000.00. Larger payments must be made in part.<br/>
							Please allow 24 hours between payments to ensure proper processing.
						</i>
					</td>
				</tr>
				<tr>
					<td class="bold right">Payment Amount</td>
					<td>
						{{card.amount | currency}}
					</td>
				</tr>
				<tr>
					<td class="bold right">Card Holder Name</td>
					<td>
						<input type='text' ng-model="card.name" class='form-control'
							ng-required="true"/>
					</td>
				</tr>
				<tr>
					<td class="bold right">Email</td>
					<td>
						<input type='email' ng-model="card.email" class='form-control' size="50"
							ng-required="true"/>
					</td>
				</tr>
				<tr>
					<td class="bold right">Street</td>
					<td>
						<input type='text' ng-model="card.street" class='form-control'
							ng-required="true"/>
					</td>
				</tr>
				<tr>
					<td class="bold right">City, State Zip</td>
					<td>
						<input type='text' ng-model="card.city" class='form-control'
							ng-required="true" style="display:inline;max-width:15em;" />
						<select ng-model="card.state" class='form-select' ng-required="true"
							style="display:inline;max-width:5em;margin:0px 5px" ng-options="state as state for state in states"/>

						<input type='text' ng-model="card.zip" class='form-control'
							ng-required="true" style="display:inline;max-width:5em;"/>
					</td>
				</tr>
				<tr>
					<td class="bold right">Card Number</td>
					<td>
						<input type='text' ng-model="card.number" class='form-control'
							ng-required="true"/>
					</td>
				</tr>
				<tr>
					<td class="bold right">Expiration</td>
					<td>
						<select ng-model="card.expMonth" class='form-select' ng-options="month for month in months"
							 ng-required="true" style="width:20%;display:inline">
						</select>
						<span style="margin-right:10px">Month</span>
						<select class='form-select' style="width:20%;display:inline" ng-model="card.expYear"
							ng-required="true" ng-options="year for year in years">
						</select>
						<span style="margin-right:10px">Year</span>
					</td>
				</tr>
				<tr>
					<td class="bold right">Cvv</td>
					<td>
						<input type='text' ng-model="card.cvv" class='form-control'
							ng-required="true" style="width:20%" />
					</td>
				</tr>
			</table>
		</form>

		<div class="button-row" style="margin-bottom:1em" ng-if="totalDue > 0">
			<button class="btn btn-primary" ng-click="submitPayment()">
				Submit Payment
			</button>
		</div>

		<!-- Confirmation/Failure Messages -->
		<div ng-show="paymentAmount && !paymentFailure">
			<div ng-show="totalDue == 0" class='alert alert-success'>
				Your registration has been paid in full.  Thank you!<br/>
			</div>

			<div ng-if="paymentSuccess"  class='alert alert-success'>
				<H2 style="text-decoration:underline">Thank you for your payment.</H2> <br/>
				Your payment of {{paymentAmount | currency}} has been successfully processed.<br/>
				Your confirmation number is: <b>{{paymentConfirmation}}</b><br/>
				An email with this information has been sent to {{card.email}}
			</div>
		</div>

		<div ng-show="paymentFailure" class='alert alert-danger' role='alert' >
			<H4>Error Processing Credit Card Payment</H4>
			{{authMessage}}<br/>
			{{paymentMessage}}<br/>
			Please check the credit/debit card details you've submitted for accuracy.
		</div>
	</div><!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>