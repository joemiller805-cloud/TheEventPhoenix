<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PSUGevents.com</title>
   	<?php include("commonStyles.php"); ?>
    <?php include("common_functions.php");?>
    <?php include("commonJs.php");	?>
    <script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			$scope.eventData;
			dataSvc.getEventData('<?= $_REQUEST["slug"] ?>').then(function(resp){
				$scope.eventData = resp;
			});

			$scope.submitEmail = function(){
				$scope.success = false;
				$scope.failed = false;
				var submitData = {
					"eventid":$scope.eventData.eventid,
					"email":$scope.email
				};
				$http({
					"url": '/send_confirmation.php',
					"method": 'POST',
					"data": $.param(submitData),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				}).then(function(response){
					if(response.data == 'success') $scope.success = true;
					else $scope.failed = true;
				});
			};
		});
	</script>
</head>
<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
    <div class="container-fluid" ng-controller="regController">
        <div class="row">
            <div class="col-lg-12">
                <ol class="breadcrumb">
                    <li><a href="/e/{{eventData.slug}}">{{eventData.eventName}}></a></li>
                    <li>Register</li>
                </ol>
            </div>
        </div>
        <div class="row">
			<event-sidebar ng-controller="eventSidebarController"></event-sidebar>
            <div class="col-md-10">
	            <h2>Recover confirmation number</h2>
				<div class="mb-2 row">
					<label class="col-2 col-form-label" for='email'>Email Address:</label>
					<div class='col-sm-10'>
						<input type="text" ng-model="email" class="form-control">
					</div>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label" for='0'>&nbsp;</label>
					<div class='col-sm-10'>
						<button type="button" style="margin-top:1em;float:right" ng-click="submitEmail()" class="btn btn-primary">
							Recover
						</button>
					</div>
				</div>
				<div class="mb-2 row">
					<div class="col-sm-12" style="margin-top:1em">
						<div class='alert alert-success' role='alert' ng-show="success">
							An email has been sent containing your registration information. Please check your inbox for your confirmation number.
						</div>

						<div class='alert alert-danger' role='alert' ng-show="failed">
							A registration with that e-mail address could not be found.
						</div>
					</div>
				</div>
			</div>
		</div>
    </div> <!-- END CONTROLLER -->
	<er-Footer />
</body>
</html>
