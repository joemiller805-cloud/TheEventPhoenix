<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PSUGevents.com</title>
<?php 
	include("../commonStyles.php"); 
 	include("../common_functions.php");
 	include("../commonJs.php");
	$inputs = sanitize_inputs($_REQUEST);
	if (substr($_SERVER['HTTP_HOST'], 0, 4) == "easy"){
		print("<script language=\"javascript\" src=\"https://app.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
	}
	else{
		print("<script language=\"javascript\" src=\"https://sandbox.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
	} 
?>
<script src="/js/angular-route.js"></script>
<script src="/js/sponsorModule.js?_=<?= rand() ?>"></script>
<style>
	.preWrap{ white-space: pre-wrap; }
	.bi-check-lg{ color:green; }
	.bi-x-circle{ color:firebrick; }
	.pkgName{ font-size: x-large; }
	#ccTable tr td{ border:none; }
	.table tr td{ padding: 1px 8px !important;}
	@media only screen and (max-width: 400px){
		body{ padding-top: 240px !important; }
	}
	.eventTable, .orderTable{
		-webkit-box-shadow: 5px 5px 15px -1px #BCBCBC; 
		box-shadow: 5px 5px 15px -1px #BCBCBC;
		-webkit-border-radius: 2px;
		-moz-border-radius: 2px;
		border-radius: 2px;
		padding-bottom: 3px;
	}
	.table th{
		background: var(--header);
		color: white;
	}
</style>
<script type="text/javascript">
	//Prevent sponsor from landing on this page with the accountid in session having a value other than the sponsor's account
	<?php 
		if($_SESSION['sponsorid']){
			require_once __DIR__ . '/../data_access/tep_dml_pdo.php'; // Bound sponsor tenant
			try { // PDO; never interpolate sponsorid
				$pdo = tep_dml_pdo(); // utf8mb4
				$stmt = $pdo->prepare('SELECT accounts.id AS accountid, accounts.web_logo
					FROM sponsors
					JOIN accounts ON accounts.id = sponsors.accountid
					WHERE sponsors.id = :sponsorid
					LIMIT 1'); // Bound
				$stmt->execute(array('sponsorid' => (int)$_SESSION['sponsorid'])); // Session principal
				$row = $stmt->fetch(PDO::FETCH_ASSOC); // One row
				if ($row && $_SESSION['accountid'] != $row['accountid']) {
					$_SESSION['accountid'] = $row['accountid'];
					$_SESSION['accountEventsFor'] = $row['accountid'];
					$_SESSION['accountLogo'] = $row['web_logo'];
					echo('location.reload()');
				}
			} catch (Throwable $spHomeEx) { // Connect
				error_log('TEP sponsor/home.php tenant lookup failed: ' . $spHomeEx->getMessage()); // Log only
			}
		}
	?>

	var regApp = angular.module('regApp', ['ngRoute','easyRegDataModule','sponsorDataModule','erSvc','navMod']);
	regApp.constant("accountid", "<?=$_SESSION['accountid'] ?>");

	regApp.config(['$locationProvider', function($locationProvider) {
		$locationProvider.hashPrefix('');
	}]);

	regApp.config(function($routeProvider) {
		var sfx = '?_=' + Math.random();
		$routeProvider
		.when("/", {templateUrl:"views/profile.html" + sfx, controller: "profile"})
		.when("/passwordReset",{templateUrl:"views/passwordReset.html" + sfx,controller: "passwordReset"})
		.when("/staff", {templateUrl:"views/staff.html" + sfx, controller:"staffCtrl"})
		.when("/events", {templateUrl:"views/events.html" + sfx, controller:"eventsCtrl"})
		.when("/eventStaff", {templateUrl:"views/eventStaff.html" + sfx, controller:"evtStaffCtrl"})
		.when("/orders", {templateUrl:"views/orders.html" + sfx, controller:"ordersCtrl"})
		.when("/courseRequest", {templateUrl:"views/courseRequest.html" + sfx, controller:"courseRequestCtrl"})
		.when("/invoice", {templateUrl:"views/invoice.html" + sfx, controller:"invoiceCtrl"});
	});

	regApp.controller('regController', function($scope, $http, $q, dataSvc,sponsorDataService,erSvc) {
		$scope.$on('$routeChangeStart', function($event, next, current) {
			setActiveTab(next);
		});

		function setActiveTab(next){
			$('.nav-tabs li').removeClass('active');
			if($('.nav-tabs li').length == 0) setTimeout(function(){ setActiveTab(next) }, 500);
			else  $(`a[href="#${next.originalPath}"]`).closest('li').addClass('active');
		}

		$scope.newAcct = new URL(location).searchParams.get('new');
		let sponsorid = new URL(location).searchParams.get('id');
		$scope.accountid = "<?=$_SESSION['accountid'] ?>";
		<?php 
			if($_GET['id'] != ''){
				$_SESSION['sponsorid'] = $_GET['id'];
			} 
		?>
		$scope.sponsorid = "<?=$_SESSION['sponsorid'] ?>";
		$scope.masterUser = "<?=$_SESSION['master'] ?>";

		if($scope.masterUser && sponsorid) $scope.sponsorid = sponsorid;

		$scope.states = erSvc.getStateOptions();
		$scope.months = ['01','02','03','04','05','06','07','08','09','10','11','12'];
		var cardYr = new Date().getFullYear();
		$scope.years = [cardYr];
		for (var i=1; i<10; i++){
			$scope.years.push(cardYr + i);
		}

		dataSvc.getObject({'query':'accountEvents'}).then(function(events){
			$scope.events = events;
			angular.forEach($scope.events,function(event){
				//treat recent and past status the same.  On this page, it's all about whether it's visible to sponsors
				if(event.status == 'recent') event.status = 'past';
				event.regTypes = [];
				event.sponsorOptions = [];
				if(event.visible_to_sponsors != '1') delete $scope.events[event.id];
			});
		});

		sponsorDataService.sponsorid = $scope.sponsorid;

		$scope.getSponsorStatus = function(){
			var statusRetrieved = $q.defer();
			sponsorDataService.getSponsor().then(function(res){
				$scope.vendor = res;
				statusRetrieved.resolve();
				erSvc.closeLoading();
			});
			return statusRetrieved.promise;
		};

		$scope.getSponsorStatus();

		$scope.replytoemail = "postmaster@easyregpro.com"; // Routing mailbox — not display branding
		dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
			if(resp[0]) $scope.replytoemail = resp[0].email;
		});

		/********* CC Processing **********/

		$scope.initializeCcInfo = function(){
			$scope.ccProvider = 'none';
			dataSvc.getArray({'query':'ccProvider'}).then(function(cc){
				if(cc[0] && cc[0].ccProvider) $scope.ccProvider = cc[0].ccProvider;
				$scope.ccEnabled = $scope.ccProvider != 'none';
				if(!$scope.ccEnabled) $scope.payment_method = 'po';
				if($scope.ccProvider == 'basys') initializeBasysToken(cc[0].apiKey);
			});
		};

		let paymentResponse;
		let purchaseAmount;
		let paymentStatus;
		let currentOrderId;
		$scope.processPayment = function(card, amount, orderid){
			paymentResponse = $q.defer();
			paymentStatus = {};
			paymentStatus.paymentError = false;
			purchaseAmount = amount;
			if($scope.ccProvider == 'basys'){
				currentOrderId = orderid;
				basysToken.submit();
				return paymentResponse.promise;
			}

			//if we're still here, this is a MagicWrighter payment
			amount = Number(amount).toFixed(2);
			let submitData = {
				"amount":amount,
				"regConfirmation":$scope.vendor.id,
				"name":card.name,
				"email":card.email,
				"street":card.street,
				"city":card.city,
				"state":card.state,
				"zip":card.zip,
				"cardNumber":card.number,
				"cvv":card.cvv,
				"expiration":card.expMonth + card.expYear.toString().substr(-2),
				"regId":$scope.vendor.id,
				"accountid":$scope.accountid,
				"sponsorid": $scope.vendor.id,
				"orderid":orderid
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
						paymentStatus.paymentSuccess = true;
						paymentStatus.ccConfirmation = paymentInfo['@attributes'].ConfirmationNumber;
						paymentStatus.payment_number = paymentInfo['@attributes'].ConfirmationNumber;
						var recipient = card.email;
						var subject = "Thank You For Your Payment";
						var body = "Your payment has been received. <br/>";
						body += "Please keep this message for your records. <br/>";
						body += "<b>Payment Confirmation Number: </b>" + paymentStatus.ccConfirmation +  "<br/>";
						body += "<b>Payment Total: $" +  amount;

						erSvc.sendEmail(recipient, subject, body, $scope.replytoemail);
						paymentResponse.resolve(paymentStatus);
					}else{
						paymentStatus.paymentError = true;
						paymentStatus.authMessage = authInfo['@attributes'].Message;
						paymentStatus.paymentMessage = paymentInfo['@attributes'].PaymentMessage;
						paymentResponse.resolve(paymentStatus);
						erSvc.closeLoading();
					}
				}catch(e){
					console.error(e)
					paymentStatus.paymentError = true;
					paymentResponse.resolve(paymentStatus);
					erSvc.closeLoading();
				}
			});
			return paymentResponse.promise;
		}; //END processPayment()

		function processBasysPymt(resp){
			if(resp.status == 'success'){
				let email = resp.user.email || '';
				let submitData = {
					"amount": (purchaseAmount * 100),
					"trueAmount": purchaseAmount,
					"orderid":currentOrderId,
					"email":email,
					"token":resp.token,
					"description": "Vendor Order",
					"first_name": resp.user.first_name,
					"last_name": resp.user.last_name,
					"address_line_1": resp.billing.address,
					"city": resp.billing.city,
					"state": resp.billing.state,
					"postal_code": resp.billing.zip,
					"country": resp.billing.country,
					"accountid": $scope.accountid,
					"sponsorid": $scope.vendor.id
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
						paymentStatus.paymentSuccess = true;
						paymentStatus.ccConfirmation = res.data.id;
						paymentStatus.payment_number = res.data.id;
						if(res.data?.billing_address?.email){
							var subject = "Thank You For Your Payment";
							var body = "Your payment has been received. <br/>";
							body += "Please keep this message for your records. <br/>";
							body += "<b>Payment Confirmation Number: </b>" + paymentStatus.ccConfirmation +  "<br/>";
							body += "<b>Payment Total: $" + Number(purchaseAmount).toFixed(2);
							erSvc.sendEmail(res.data.billing_address.email, subject, body, $scope.replytoemail);
						}
						paymentResponse.resolve(paymentStatus);
					}else{
						paymentStatus.paymentError = true;
						paymentStatus.authMessage = res.data.response;
						paymentResponse.resolve(paymentStatus);
					}
				});
			}else{
				paymentStatus.paymentError = true;
				paymentStatus.authMessage = "Invalid: " + resp.invalid.toString();
				erSvc.closeLoading();
				paymentResponse.resolve(paymentStatus);
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
					user:{
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
		} // End initializeBasysToken()

		$scope.closeRightDialog = () =>	$(".dialogRight").hide(500);
	});
</script>
<script src="controllers/profile.js?_=<?= rand() ?>"></script>
<script src="controllers/staff.js?_=<?= rand() ?>"></script>
<script src="controllers/events.js?_=<?= rand() ?>"></script>
<script src="controllers/eventStaff.js?_=<?= rand() ?>"></script>
<script src="controllers/orders.js?_=<?= rand() ?>"></script>
<script src="controllers/invoice.js?_=<?= rand() ?>"></script>
<script src="controllers/courseRequest.js?_=<?= rand() ?>"></script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController" class="noPrint"></top-nav>
	<div class="container-fluid" ng-controller="regController">
		<div class='alert alert-success noPrint' role='alert' ng-show="newAcct" style="margin-top:10px">
			You have successfully created your account
		</div>
		<br class="noPrint"/>
		<div class="row">
			<ul ng-if="vendor.id" class="nav nav-tabs noPrint" style="float:left">
				<li class="nav-link active" id="profileTab">
					<a href="#/">Vendor Profile</a>
				</li>
				<li class="nav-link" id="staffTab">
					<a href="#/staff">Staff</a>
				</li>
				<li class="nav-link" id="eventsTab">
					<a href="#/events">Events</a>
				</li>
				<li class="nav-link" id="evtStaffTab">
					<a href="#/eventStaff">Event Staff</a>
				</li>
				<li class="nav-link" id="ordersTab">
					<a href="#/orders">Order History</a>
				</li>
				<li class="nav-link" id="invoiceTab">
					<a href="#/invoice">Invoice</a>
				</li>
				<li class="nav-link" id="courseReqTab">
					<a href="#/courseRequest">Propose a New Course</a>
				</li>
			</ul>
		</div>
		
		<div ng-if="masterUser" style="float:right;margin-right:5em" class="row">
			<a href="/admin.php#!/vendor_management">
				<button class="btn btn-primary">
					<em class="bi bi-arrow-left-circle"></em> Return to Vendor Mgmt
				</button>
			</a>
		</div>
		<br class="noPrint"/>

		<div ng-view></div>
	</div> <!-- END CONTROLLER   -->
	<div style="height: 60px"></div>
	<er-Footer />
</body>
</html>
