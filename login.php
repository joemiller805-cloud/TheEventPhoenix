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
	<script src="/controllers/loginController.js?_=<?= rand() ?>"></script>
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod','loginMod']);
		if(erSessionData) erSessionData.accountLogo = '';
	</script>
</head>
<body ng-app="regApp">
	<div class="container">
		<img src="/img/ERP-no-tag.png" style="height:55px">
		<h1>Admin/Staff Login</h1>
		<login-form ng-controller="loginController"></login-form>
	</div>
	<er-Footer />
</body>
</html>
