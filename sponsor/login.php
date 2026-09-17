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
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			let accountid = <?= tep_js_string($_REQUEST['accountid'] ?? '') ?>;
			if(accountid) $.post('/set_session_account.php',{'accountid':accountid});
			$scope.login = function(){
				if(!$scope.loginform.$valid) return false;
				$http({
					"url": "/sponsor/login_process_sponsor.php",
					"method": "GET",
					"params": {"username":$scope.username,"pass":$scope.pass}
				}).then(function(response){
					if(response.data == 'success'){
						dataSvc.getArray({'query':'currentSponsorData'}).then(function(resp){
							let sponsor = resp[0];
							angular.forEach(sponsor, prop => prop = (prop || '').toString().replace(/\"/g,'') );
							$http({
								"url": '/setUserData.php',
								"method": 'POST',
								"data": $.param({"currentSponsorData":sponsor}),
								"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
							}).then(function(){
								window.location = '/sponsor/home.php';
							});
						});
					}else{
						$('.alert-danger').show();
					}
				});
			};

			//generate a new random password
			function getNewPw(){
				let valueArray = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','1','2','3','4','5','6','7','8','9','0'];
				let newPass = '';
				for(var i = 0; i < 10; i++){
					newPass = newPass + valueArray[Math.floor(Math.random() * 36)];
				}
				return newPass;
			}

			$scope.checkResetEmail = function(){
				erSvc.loadingDialog();
				$scope.emailError = !$scope.emailForm.resetEmail.$valid;
				$scope.emailNotFound = false;
				if($scope.emailError) return;
				dataSvc.getSponsorEmailExists($scope.resetEmail).then(function(res){
					if(res) resetPassword();
					else {
						$scope.emailNotFound = true;
						erSvc.closeLoading();
					}
					$scope.$applyAsync();
				});
			};
			
			function resetPassword(){
				$http({
					"url": "/send_pw_reset_email.php",
					"method": "POST",
					"data": $.param({"email": $scope.resetEmail, "sponsor": "1"}), // Server hashes; no client password
					"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
				}).then(function(){
					erSvc.closeLoading();
					erSvc.easyRegAlert({"text":"Your password has been reset. An email has been sent with your temporary password.  Please check your inbox for this message","title":"Password Reset"});
				});
			}
		});//end controller
	</script>
</head>

<body ng-app="regApp">
    <top-nav ng-controller="navController"></top-nav>
    <div class="container" ng-controller="regController">
    	<div class="row">
    		<div class="col-sm-8"><h1>Vendor Login</h1></div>
    		<div class="col-sm-4" style="padding-top:8px">
    			Not a vendor yet?
				<a class="btn btn-primary btn-sm" href="newSponsor.php">Become a Vendor</a>
    		</div>
    	</div>

    	<div class='alert alert-danger' role='alert' style="display:none">
    		The username and or password provided is not correct.
    	</div>
    	<form name="loginform">
			<div class="row">
				<div class="col-sm-9">
					<label>Username</label>
					<input type="text" class="form-control" ng-model="username" required/>
					<label>Password</label>
					<input type="password" class="form-control" ng-model="pass" required/>
					<br/>
					<button class="btn btn-primary" ng-click="login()">Log In</button>
				</div>
			</div>
		</form>
		<div class="center">
			<a href="" ng-click="forgotPass=true" style="cursor: pointer;">Forgot Password</a>
		</div>
		<div ng-show="forgotPass" class="center">
			<b>Please enter the account email address</b><br/>
			<form name="emailForm">
				<input ng-model="resetEmail" name="resetEmail" class="form-control" type="email"
					style="width:30em;display:inline-block;" required />
			</form>
		</div>
		<div class="right"  ng-show="forgotPass">
			<button class="btn btn-primary" ng-click="checkResetEmail()">Reset My Password</button>
		</div>
		<div class='alert alert-danger' role='alert' ng-show="emailError">
			Please enter a valid email address.
		</div>
		<div class='alert alert-danger' role='alert' ng-show="emailNotFound">
			Email address not found.
		</div>
	</div><!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>