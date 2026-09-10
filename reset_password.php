<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PSUGevents.com</title>
<?php include("common_functions.php");?>
<?php include("commonStyles.php");?>
<?php include("commonJs.php");?>

<?php 
	// ERIC Should use this for security
	function generateRandomString($length = 10) {
	    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	    $charactersLength = strlen($characters);
	    $randomString = '';
	    for ($i = 0; $i < $length; $i++) {
	        $randomString .= $characters[rand(0, $charactersLength - 1)];
	    }
	    return $randomString;
	}
?>

<script type="text/javascript">
	var passwordReset = false;
	var app = angular.module('regApp', ['easyRegDataModule','erSvc', 'navMod']);
	app.controller('regController', function($scope, $http, dataSvc, erSvc) {
		dataSvc.getObject({'query':'accountList'}).then(function(accounts){
			$scope.accounts = accounts;
		});

		let accountid = new URL(location).searchParams.get('accountid');
		if(accountid) $scope.accountid = accountid;

		var valueArray = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','1','2','3','4','5','6','7','8','9','0'];

		//generate a new random password
		var newPass = '';
		for(var i = 0; i < 10; i++){
			newPass = newPass + valueArray[Math.floor(Math.random() * 36)];
		}

		$(document).on('dialogclose', function(){
			if(passwordReset) window.location = "login.php";
		});

		$scope.submitReset = function(){
			if($scope.pwForm.$valid){
				if($scope.email == 'support@easyregpro.com') window.location = "login.php"; 
				dataSvc.getArray({'query':'checkUserExists', 'accountid':$scope.accountid, 'email':$scope.email}).then(resp => {
					if(resp.length){
						erSvc.encrypt(newPass).then(function(response){
							dataSvc.userPasswordReset($scope.email, response).then(function(resp){
								let link = 'https://<?= $_SERVER['HTTP_HOST']?>/login.php';
								var url = "/send_pw_reset_email.php?email=" + $scope.email;
								url += "&password=" + newPass + '&link=' + link;
								$http({
									"url":  url,
									"method": "POST"
								}).then(function(){
									passwordReset = true;
									erSvc.easyRegAlert({"text":"Your password has been reset. An email has been sent with your temporary password.  Please check your inbox for this message","title":"Password Reset"});
								});
							});
						});
					}else{
						erSvc.easyRegAlert({"text":"No user with that email address could be found.","title":"User Not Found"});
					}
				});
			}
		};
	});//end controller
</script>
</head>

<body ng-app="regApp">
    <top-nav ng-controller="navController"></top-nav>
    <div class="container-fluid col-sm-10" ng-controller="regController">
    	<form name="pwForm">
	    	<H2>Password Reset</H2>
			<div class="row form-horizontal">
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Account:</label>
					<span class='col-sm-5'>
						<select class="form-select" ng-options="act.id as act.name for act in accounts"
							ng-model="accountid" required>
						</select>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Email:</label>
					<span class='col-sm-5'>
						<input type="email" class="form-control" ng-model="email" required />
					</span>
				</div>
			</div>
			<div class="button-row">
				<button class="btn btn-primary" ng-click="submitReset()">Reset My Password</button>
			</div>
		</form>
	</div><!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>