<?php
	include __DIR__ . '/common_functions.php'; // Cookie flags must load before session_start
	start_secure_session(); // SameSite=Lax + HTTPS-aware Secure (raw session_start skipped php.ini SameSite)
	session_unset(); // Fresh super-user login; cookie params already applied
	ensure_session_csrf_token(); // Restore CSRF after unset for AngularJS posts
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>The Event Phoenix Login</title>
	<?php include("commonStyles.php");?>
	<link rel="icon" href="/css/tep-logo.svg"> <!-- Tracked SVG; /img/ is gitignored -->
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
		<img src="/css/tep-logo.svg" alt="The Event Phoenix" style="height:55px"
			onerror="this.style.display='none';var n=this.nextElementSibling;if(n)n.style.display='inline';"> <!-- HTTPS-safe relative path -->
		<span style="display:none;font-size:1.5em;font-weight:bold;">The Event Phoenix</span> <!-- Text fallback if the SVG fails -->
		<h1>The Event Phoenix Login</h1>
		<form name="loginform" ng-submit="login()"> <!-- Enter submits; AngularJS prevents reload -->
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
				<button type="submit" class="btn btn-primary">Login</button>
			</div>
		</form>
	</div>
	<footer class="noPrint" style="clear:both;width:95%;margin:auto;padding:1em">
		<hr/>
		<div class="row">
			<div class="col-lg-12 copyright">
				<p style="float:left">Copyright &copy; 2015 - <?= (int)date('Y') ?> The Event Phoenix. All rights reserved.</p>
				<div style="float:right;margin-right:3em">
					<span style="color:black;margin-right:2em;vertical-align:top">Powered By </span>
					<a href="/about/home.php">
						<img src="/css/tep-logo.svg" alt="The Event Phoenix" style="width:10em"
							onerror="this.style.display='none';"> <!-- Relative HTTPS path -->
					</a>
				</div>
			</div>
		</div>
	</footer>
</body>
</html>
