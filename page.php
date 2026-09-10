<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PSUGevents.com</title>
	<?php include("commonStyles.php"); ?>
    <?php include("common_functions.php");	?>
    <?php include("commonJs.php");	?>
    <style>
    	.dialogRight iframe{
    		width:100%;
    		height:87vh;
    		border:none;
    	}
    	#mainContent{display:none;}
    	.dialogRight .dialogContents{ padding:0px; }
    	div.dialogRight{ min-width:65%; }
    </style>
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			$scope.eventData;
			dataSvc.getEventData('<?= $_REQUEST["slug"] ?>').then(function(resp){
				$scope.eventData = resp;
				if($scope.eventData.visible == '0') $('#evtUnavailable').show();
				else $('#mainContent').show();
				$scope.$applyAsync();
			});
			$scope.showSessionSchedule = function(){
				$('.dialogRight').show(500);
			};
			$scope.closeRightDialog = function(){
				$('.dialogRight').hide(500);
			};
			$('#pageContent a').attr('target','_blank');
		});
	</script>
</head>
<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
    <div class="container-fluid" ng-controller="regController">
		<?php
		$resourceID = database_connect();
		$inputs = sanitize_inputs($_REQUEST);
		$query = "select id from events where slug = '{$inputs['slug']}';";
		$resultID = mysqli_query($resourceID, $query);
		if(!mysqli_num_rows($resultID)) die("<meta http-equiv='refresh' content='0;URL=/'>");
		$query = "
			SELECT
				events.id eventid,
				events.name event,
				pages.name page,
				events.slug,
				events.showschedule,
				logo,
				content,
				pages.home,
				coalesce(pages.show_registrants, 0) show_registrants,
				registrations.id registrationid,
				accounts.name acctName
			FROM pages
			JOIN events ON pages.eventid = events.id
			JOIN accounts ON accounts.id = events.accountid
			LEFT OUTER JOIN registrations ON registrations.eventid = events.id AND registrations.confirmation = '{$_SESSION['confirmation']}'
			WHERE events.slug = '{$inputs['slug']}'
			AND pages.slug = '{$inputs['page']}'

			AND (
				(events.accountid = '{$inputs['accountid']}' AND '{$inputs['accountid']}' <> '')
				OR
				(events.accountid = '{$_SESSION['accountid']}' AND '{$inputs['accountid']}' <> '')
				OR 
				'{$inputs['accountid']}' = ''
			)
		";

		$resultID = mysqli_query($resourceID, $query);
		if(!mysqli_num_rows($resultID)) die("<meta http-equiv='refresh' content='0;URL=/e/$slug'>");
		$event = mysqli_fetch_assoc($resultID);
		?>
        <div class="row" style="padding-top:1em">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/"><?=$event['acctName']?></a></li>
                <li class="breadcrumb-item"><a href="/e/<?=$event['slug']?>"><?=$event['event']?></a></li>
                <li class="breadcrumb-item active"><?=$event['page']?></li>
            </ol>
        </div>
        <div class='alert alert-danger' role='alert' id="evtUnavailable" style="display:none">
        	This event is no longer available.
        </div>
        <div class="wrapper">
			<event-sidebar class="sidebar" ng-controller="eventSidebarController"></event-sidebar>
            <div class="main-content" id="pageContent">
                <?php if ($event['show_registrants'] == "1" and $event['registrationid'] == ""){ ?>
                    <h3>This page is only available for event registrants</h3>
					<form class="form-horizontal" role="form" method="GET" action="/lookup_confirmation.php">
						<div class="mb-2 row">
							<label class="col-2 col-form-label" for='0'>Confirmation Number:</label>
							<div class='col-sm-10'>
								<input type="text" name="confirmation" class="form-control" value="<?=$inputs['confirmation']?>">
							</div>
						</div>
						<div class='form-group button-row'>
							<label class="col-2 col-form-label" for='0'>&nbsp;</label>
							<div class='col-sm-10'>
								<button type="submit" class="btn btn-primary">Log In</button>
							</div>
						</div>
						<input type="hidden" name="r" value="<?=$_SERVER['REQUEST_URI']?>">
						<input type="hidden" name="slug" value="<?=$inputs['slug']?>">
					</form>
					<div class='form-group center'>
						<label class="col-2 col-form-label">&nbsp;</label>
						<div class='col-sm-10'>
							<a href="recover">I don't know my confirmation number</a>
						</div>
					</div>
	            <?php
					}else{
						print $event['content'];
					}
				?>
            </div>
        </div>

        <?php mysqli_close($resourceID); ?>

        <div id="id" class="dialogRight">
        	<div class="dialogTitle" style="margin:0px">
        		Session Schedule
        		<span style="float:right">
        			<button class="btn btn-primary btn-xs" ng-click="closeRightDialog()">x</button>
        		</span>
        	</div>
        	<div class="dialogContents">
        		<iframe src="/e/<?= $_REQUEST["slug"] ?>/schedule"></iframe>
        		<div class="button-row">
        			<button ng-click="closeRightDialog()">Close</button>
        		</div>
        	</div>
        </div>
    </div> <!-- End Controller -->
	<er-Footer />
</body>
</html>
