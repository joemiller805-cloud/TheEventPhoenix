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
<script src="/controllers/loginController.js?_=<?= rand() ?>"></script>
<script type="text/javascript">
	var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod','loginMod']);
	app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
		let id = '<?= $_REQUEST["id"] ?>';
		let accountid = '<?= $_SESSION["accountid"] ?>';
		id = id || location.pathname.split('/')[2];
		document.addEventListener("loginSuccess", e => {
			$scope.loggedIn = true;
			$scope.$applyAsync();
			$http({"url": "/session_data.php","method": "GET"}).then(function(response){
				accountid = response.data.accountid;
				dataSvc.getArray({'query':'currentSponsorData', sponsorid:id}).then(function(resp){
					erSvc.closeLoading();
					$scope.sponsor = resp[0];
					if(!$scope.sponsor || $scope.sponsor.pass != 'tempRequest'){
						erSvc.easyRegAlert({"text":"Sponsor Not Found","title":"Error"});
					}
					$scope.$applyAsync();
				});
			});
		});

		$scope.approveRequest = () =>{
			let newPw = `erPw${Math.floor(Math.random() * 1000000)}`;
			erSvc.encrypt(newPw).then(pw => {
				$scope.sponsor.pass = pw;
				dataSvc.createOrUpdateRecord({"table":"sponsors","record":$scope.sponsor})
				.then(res => {
					let emailData = {
						subject:'EasyRegPro Vendor Request',
						address:$scope.sponsor.email,
						replytoemail: 'no-reply@easyregpro.com'
					};
					let url = `https://${location.host}/sponsor/login.php?accountid=${accountid}`;
					let msg = `
						Your vendor request for EasyRegPro has been approved.<br/>
						Your temporary password is ${newPw}. <br/>
						Please log in as soon as possible and update your password.<br/>
						Log in at <a href="${url}">${url}</a>
					`;
					
					erSvc.sendEmail($scope.sponsor.email, 'EasyRegPro Vendor Request', msg).then(() => {
						$scope.status = 'approved';
					});
				});
			});
		};

		$scope.rejectRequest = () =>{
			let msg = `Please confirm that you would like to reject and delete this request.`
			erSvc.easyRegConfirm({"text":"","title":"Confirm"},"Confirm","Cancel").then(res => {
				if(res) $scope.status = 'rejected';
			});
			
		};
	});//end controller
</script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController" style="width:70em;margin:auto">
		<login-form ng-controller="loginController" stay-on-pg="true" ng-hide="loggedIn">
		</login-form>
		<div ng-show="sponsor" ng-cloak>
			<h1>New Sponsor Request</h1>
			<div class="row">
				<div class="col-sm-2 bold">Name</div>
				<div class="col-sm-8">{{sponsor.name}}</div>
			</div>
			<div class="row">
				<div class="col-sm-2 bold">Web Address</div>
				<div class="col-sm-8">{{sponsor.web_address}}</div>
			</div>
			<div class="row">
				<div class="col-sm-2 bold">Bio</div>
				<div class="col-sm-8">{{sponsor.bio}}</div>
			</div>
			<div class="row">
				<div class="col-sm-2 bold">Address</div>
				<div class="col-sm-8">
					{{sponsor.address1}}
					{{sponsor.address2}}
					{{sponsor.city}}
					{{sponsor.state}}
					{{sponsor.zip}}
				</div>
			</div>
			<div class="row">
				<div class="col-sm-2 bold">Contact</div>
				<div class="col-sm-8">
					{{sponsor.contact_first_name}}
					{{sponsor.contact_last_name}}
				</div>
			</div>
			<div class="row">
				<div class="col-sm-2 bold">Email</div>
				<div class="col-sm-8">{{sponsor.email}}</div>
			</div>
			<div class="row">
				<div class="col-sm-2 bold">Phone</div>
				<div class="col-sm-8">{{sponsor.phone}}</div>
			</div>
			<div class="button-row" ng-hide="status">
				<button class="btn btn-success" ng-click="approveRequest()">Approve</button>
				<button class="btn btn-danger" ng-click="rejectRequest()">Reject</button>
			</div>
			<div class='alert alert-success' role='alert' ng-show="status == 'approved'">
				The vendor request has been approved.  An email has been sent to the vendor
				confirming approval.
			</div>
			<div class='alert alert-info' role='alert' ng-show="status == 'rejected'">
				This request has been rejected and deleted.
			</div>
		</div><!-- END Sponsor Div   -->
	</div> <!-- END CONTROLLER   -->
	<er-Footer />
</body>
</html>