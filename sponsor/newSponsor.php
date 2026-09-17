<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PSUGevents.com</title>
<?php include("../common_functions.php");?>
<?php include("../commonStyles.php");?>
<?php include("../commonJs.php");?>
<script type="text/javascript">
	let startTime = Date.now();
	var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
	app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
		$scope.states = erSvc.getStateOptions();
		$scope.vendor = {};
		let accountid;

		dataSvc.getObject({'query':'accountList'}).then(resp =>	$scope.accounts = resp);

		function getContactEmail(){
			dataSvc.getArray({'query':'accountContactInfo','accountid':accountid}).then(resp => {
				$scope.contactEmail = resp[0].contact_email;
			});
		};

		$http({"url": "/session_data.php","method": "GET"}).then(function(response){
			if(!response.data.accountid) return;
			$scope.vendor.accountid = response.data.accountid;
			accountid = response.data.accountid;
			getContactEmail();
		});

		$scope.accountName = function(){
			if(!$scope.accounts || !accountid) return '';
			return $scope.accounts[accountid].name;
		};

		var currentUsernames = [];
		var currentEmails = [];
		var currentNames = [];
		var currentWebaddresses = [];
		dataSvc.getArray({'query':'sponsorUsernames'}).then(function(res){
			angular.forEach(res,function(acct){
				currentUsernames.push(acct.username);
				currentEmails.push(acct.email);
				currentNames.push(acct.name);
				currentWebaddresses.push(acct.web_address);
			});
		});

		$scope.createSponsorRequest = function(){
			//if a submission is made in under 4 seconds, probably a bot.
			if(Date.now() - startTime  < 4000 ) return $('body').empty();
			
			//check form valid
			if(!$scope.sponsorForm.$valid){
				erSvc.easyRegAlert({
					"text":"Please Complete Required Fields",
					"title":"Submission Error"
				});
				$('form').addClass('submitted');
				return;
			}

			//check for duplicate vendor account
			let errorMsg = checkDuplicates();
			if(!$scope.vendor.id && errorMsg){
				erSvc.easyRegAlert({"text":errorMsg,"title":"Cannot Create Account"});
				return;
			}

			//create vendor request if no errors found
			erSvc.loadingDialog("Submitting Request");
			$scope.vendor.pass = 'tempRequest';
			dataSvc.createOrUpdateRecord({"table":"sponsors","record":$scope.vendor}).then(res =>{
				erSvc.closeLoading(); // Request is stored; browser mail relay was removed
				$scope.requestSent = true; // Same success UI as before
				$scope.$applyAsync(); // Digest
			});
		};

		//check for duplicate sponsor values (name, email, web address)
		function checkDuplicates(){
			if(currentEmails.indexOf($scope.vendor.email) >= 0)
				return "The selected email address is already in use. Please choose another";
			if(currentNames.indexOf($scope.vendor.name) >= 0)
				return "The selected vendor name is already in use. Please choose another";
			if($scope.vendor.web_address && currentWebaddresses.includes($scope.vendor.web_address))
				return "The selected web address is already in use. Please choose another";
			return null;
		}
	});//end controller
</script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController">
		<div class="row" id="sponsorProfile" ng-cloak>
			<div class="col-sm-11">
				<form class="form-horizontal" role="form" name="sponsorForm">
					<div class="mb-2 row">
						<label class="col-2 col-form-label"></label>
						<H4 class='col-sm-2'>Vendor Account</H4>
						<H4 class="col-sm-4">{{accountName()}}</H4>
						<div class="col-sm-4 right" style="padding-top:8px">
							Already a Vendor?
							<a class="btn btn-primary btn-sm" href="/sponsor/login.php">Log In</a>
						</div>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label"></label>
						<H4 class='col-sm-5'><u>Company/Vendor Information</u></H4>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Vendor Name</label>
						<span class='col-sm-5'>
							<input type='text' ng-model="vendor.name" class='form-control' required>
						</span>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Web Address</label>
						<span class='col-sm-5'>
							<input type='text' ng-model="vendor.web_address" class='form-control' required>
						</span>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Company Bio</label>
						<span class='col-sm-8'>
							<textarea ng-model="vendor.bio" class='form-control' rows="5">
							</textarea>
						</span>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Address</label>
						<span class='col-sm-6'>
							<input type='text' ng-model="vendor.address1" class='form-control' required placeholder="Address">
						</span>
						<span class='col-sm-4'>
							<input type='text' ng-model="vendor.address2" class='form-control' 
								placeholder="Suite #">
						</span>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">City State Zip</label>
						<span class='col-sm-3'>
							<input type='text' ng-model="vendor.city" class='form-control' required placeholder="City">
						</span>
						<span class='col-sm-1'>
							<select ng-options="state for state in states" ng-model="vendor.state" class="form-select" required>
							</select>
						</span>
						<span class='col-sm-2'>
							<input type='text' ng-model="vendor.zip" class='form-control' required placeholder="Zip">
						</span>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label"></label>
						<H4 class='col-sm-5'><u>Primary Contact Information</u></H4>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Contact Name</label>
						<span class='col-sm-5'>
							<input type='text' ng-model="vendor.contact_first_name" 
								class='form-control' required placeholder="First">
						</span>
						<span class='col-sm-5'>
							<input type='text' ng-model="vendor.contact_last_name" 
								class='form-control' required placeholder="Last">
						</span>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Email/Phone</label>
						<span class='col-sm-6'>
							<input type='email' ng-model="vendor.email" 
								class='form-control' required placeholder="Email">
						</span>
						<span class='col-sm-3'>
							<input type='text' ng-model="vendor.phone" 
								class='form-control' required placeholder="Phone">
						</span>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label"></label>
						<H4 class='col-sm-5'><u>Login Information</u></H4>
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Username</label>
						<span class='col-sm-4'>
							<input type='text' ng-model="vendor.username" 
								class='form-control' required placeholder="Username">
						</span>
						
					</div>
					<div class="mb-2 row">
						<label class="col-2 col-form-label">Password</label>
						<span class='col-sm-8'>
							If your vendor account is approved, a temporary password will be assigned and sent via email.
						</span>
					</div>
				</form>
				<div class="button-row">
					<button class="btn btn-primary" ng-click="createSponsorRequest()" 
						ng-if="!requestSent">
						Request Vendor Account
					</button>
				</div>
				<div class='row' ng-if="requestSent">
					<label class="col-2 col-form-label"></label>
					<span class="alert alert-info col-9" role="alert">
						Your request has been submitted.  If approved, an email with a temporary password will be sent to {{vendor.email}}.  Please remember the username you submitted to log in once your account has been approved.
					</span>
				</div>
			</div>
		</div>
	</div> <!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>