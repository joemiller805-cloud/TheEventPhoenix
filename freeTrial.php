<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="EasyRegPro event management software was designed by event planners. It has the scalability, convenience, and functionality that event planners love.">

	<script type="application/ld+json">
	{
	  "@context" : "http://schema.org",
	  "@type" : "SoftwareApplication",
	  "url" : "https://easyregpro.com/",
	  "name": "EasyRegPro Event Registration Software"
	}
	</script>
	<title>EasyRegPro Event Management Software</title>
	<?php
		$root = $_SERVER['DOCUMENT_ROOT'];
		include($root."/common_functions.php");
		include($root."/commonStyles.php");
		include($root."/commonJs.php");
	?>
	<link href="https://fonts.googleapis.com/css?family=Arbutus+Slab|Copse|Old+Standard+TT|Scheherazade|Suranna|Trocchi&display=swap" rel="stylesheet">
	<style>
		#easyRegBanner{
			background: #2F358F;
			color: white;
			margin: 15px 0px 8em 0px;
			border-radius: 20px 20px 20px 20px;
			-moz-border-radius: 20px 20px 20px 20px;
			-webkit-border-radius: 20px 20px 20px 20px;
			border: 0px solid #000000;
			padding: 5px 0px 0em 0px;
			font-size: 38pt;
			margin-top: 0px;
			font-family: Times New Roman;
			text-align: center;
			margin-bottom:0px;
			margin-left:.5em;
			padding-left: 1em;
			padding-right: 1em;
		}
		#pageHeader{
			color:#5b9a9a;
			font-weight: bold;
			font-size:15pt;
		}
	</style>
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc', 'navMod']);
		app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
			// let usedSlugs = [];
			// dataSvc.getArray({'query':'usedAcctSlugs'}).then(resp => usedSlugs = resp.map(s => s.slug));
			// $scope.type = new URL(location).searchParams.get('type') == 'ticket' ? 'ticketing' : 'event';
			// $scope.account = {"type":$scope.type};

			// $scope.validateSlug = function(){
			// 	let valid = /^[a-zA-Z0-9_-]+$/.test($scope.account.slug);
			// 	$scope.account.slug = $scope.account.slug.replace(/[^a-zA-Z0-9_-]/g,'');
			// 	$scope.account.slug = $scope.account.slug.replace(/\s/g,'');
			// 	if(!valid){
			// 		let txt = "No white-space or special characters allowed except underscore ( _ ) and hyphen ( - )"
			// 		erSvc.easyRegAlert({"text":txt,"title":"Invalid Identifier"});
			// 	}
			// };

			// $scope.checkSlugStatus = function(){
			// 	if(usedSlugs.includes($scope.account.slug)){
			// 		$scope.account.slug = '';
			// 		let txt = "This identifier is already in use.  Please choose another."
			// 		erSvc.easyRegAlert({"text":txt,"title":"Identifier In Use"});
			// 	}
			// };

			// $scope.createAccount = function(){
			// 	if(!$scope.accountForm.$valid || !erSvc.validatePassword($scope.account.login_pw)) return;
			// 	erSvc.loadingDialog("Creating Account");
			// 	let msg = `A new EasyRegPro trial account has been created.

			// 		Account Name - ${$scope.account.name}
					
			// 		Email - ${$scope.account.email}
					
			// 		Phone - ${$scope.account.phone}
			// 	`;
			// 	let emails = 'doribaldwin@gmail.com,joemiller805@gmail.com,emschaitel@gmail.com';
			// 	erSvc.sendEmail(emails,'New ER Account',msg);
			// 	erSvc.encrypt($scope.account.login_pw).then(pw => {
			// 		$scope.account.login_pw = pw;
			// 		$http({
			// 			"url": '/create_demo_account.php',
			// 			"method": 'POST',
			// 			"data": $.param($scope.account),
			// 			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
			// 		}).then( r => window.location.href = 'login.php/' + $scope.account.slug);
			// 	});
			// };
		});// End Controller
	</script>
</head>

<body style="padding-top:1px" ng-app="regApp" ng-controller="regController">
	<div style="width:98%;display:flex;justify-content:center;align-items:center" id="pageHeader">
		<div style="padding-left:3em;text-align:center" >
			<img src="/img/ERP-no-tag.png" height="120" style="margin-top:3px" ng-show="type=='event'">
			<img src="/img/K12-Logo.png" height="120" style="margin-top:3px" ng-show="type=='ticketing'">
		</div>
		<div id="easyRegBanner" style="flex-grow: 1;">
			<span ng-show="type=='event'">Dynamic Event Management Software</span>
			<span ng-show="type=='ticketing'">Event Ticketing Software</span>
			<div style="font-size:15pt">Start your Free Trial</div>
		</div>
	</div>

    <div class="container-fluid" style="margin-top:1em">
		<form class="row" name="accountForm">
			<div class="col-md-12">
<!-- 				<table class="table" style="width:90%;margin-top:1em;margin:auto">
					<tr>
						<td colspan="2">
							<h2 style="display:inline-block;margin-right:1em">Account Information</h2>
						</td>
					</tr>
					<tr>
						<td class="bold" style="width:20em">Account Name</td>
						<td>
							<input type="text" class="form-control" ng-model="account.name" required />
						</td>
					</tr>
					<tr>
						<td class="bold">Email</td>
						<td>
							<input type="email" class="form-control" ng-model="account.email" required/>
						</td>
					</tr>
					<tr>
						<td class="bold">URL Identifier</td>
						<td>
							This is the identifier that will be used in the web address to direct visitors to your events 
							(e.g <i>easyregpro.com/<b>myIdentifier</b></i>)
							<input type="text" class="form-control" ng-model="account.slug" required
								maxlength="45" ng-change="validateSlug()" ng-blur="checkSlugStatus()" />
						</td>
					</tr>
					<tr>
						<td class="bold">Phone/Fax</td>
						<td>
							<input type="text" class="form-control" ng-model="account.phone" placeholder="Phone" 
								style="width:40%;display: inline-block;" required/>
							<input type="text" class="form-control" ng-model="account.fax" placeholder="Fax"
								style="width:40%;display: inline-block;margin-left:2em" />
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<h2 style="display:inline-block;margin-right:1em">Login Information</h2>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<div style="display:inline-block;width:49%">
								<b>First Name</b>
								<input type="text" style="display:inline-block" class="form-control" 
									required ng-model="account.login_fn" />
							</div>
							<div style="display:inline-block;width:49%">
								<b>Last Name</b>
								<input type="text" style="display:inline-block" class="form-control" 
									required ng-model="account.login_ln" />
							</div>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<div style="display:inline-block;width:49%;vertical-align: top;">
								<b>Login Email</b>
								<input type="email" style="display:inline-block" class="form-control" 
									required ng-model="account.login_email" />
							</div>
							<div style="display:inline-block;width:49%">
								<b>Password</b>
								<input type="password" style="display:inline-block" class="form-control" 
									required ng-model="account.login_pw" />
								<span style="font-weight: normal;">
									<password-requirements></password-requirements>
								</span>
							</div>
						</td>
					</tr>					
				</table> -->
				<!-- <div class="button-row">
					<button class="btn btn-primary" ng-click="createAccount()" 
						style="background:#2F358F;border:none;">
						Start my Free Trial
					</button>
				</div> -->
			</div> <!-- End column -->
		</form> <!-- End row -->
	</div>
	<er-Footer />
</body>
</html>