<?php 
	session_start(); 
	session_unset();   
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PSUGevents.com Login</title>
	<?php include("common_functions.php");  ?>
	<?php include("commonStyles.php");?>
	<?php include("commonJs.php");?>
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc']);
		app.controller('regCtrl', function($scope, $http){
			$scope.login = function(){
				if(!$scope.loginform.$valid) return false;
				$http({
					"url": '/login_process_er.php',
					"method": 'POST',
					"data": $.param({"user":$scope.user,"pass":$scope.pass}),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				}).then(function(response){
					if(response.data == 'success') window.location = '/accounts.php';
				});
			}
		});
	</script>
</head>
<body ng-app="regApp">
<div class="container" ng-controller="regCtrl">
	<h1>EasyRegPro Login</h1>
	<form name="loginform">
		<div class="col-md-8">
			<div class="control-group form-group">
				<div class="controls">
					<label style="display:block">User</label>
					<input type="text" class="form-control" ng-model="user" required="">
				</div>
			</div>
			<div class="control-group form-group">
				<div class="controls">
					<label style="display:block">Password</label>
					<input type="password" class="form-control" ng-model="pass" required="">
				</div>
			</div>
			<button ng-click="login()" class="btn btn-primary">Login</button>
		</div>
	</form>
</div>
<er-Footer />
</body>
</html>
