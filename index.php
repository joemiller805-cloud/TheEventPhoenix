<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PSUGevents.com</title>
	<style>
		.navbar{display:none;}
	</style>
	<?php
		include("common_functions.php");
		include("commonStyles.php");
		include("commonJs.php");
		if (!defined('BASE_URL')) { // Sprint 1: tep_config is outside htdocs and may omit BASE_URL locally
			define('BASE_URL', '/'); // Sprint 1: root-relative base keeps existing /js /css AngularJS paths intact
		}
		$tepBaseHref = rtrim(BASE_URL, '/') . '/'; // Sprint 1: <base> needs a trailing slash or relative URLs resolve one level too high
	;?>
	<base href="<?php echo htmlspecialchars($tepBaseHref, ENT_QUOTES, 'UTF-8'); ?>"> <!-- Sprint 1: escaped BASE_URL for PWA standalone asset resolution -->
	<link rel="manifest" href="/manifest.json"> <!-- Sprint 1: TEP web app manifest for install/standalone display -->
	<meta name="theme-color" content="#E65100"> <!-- Sprint 1: fire theme color for browser and PWA chrome -->
	<link rel="apple-touch-icon" href="/img/e.png"> <!-- Sprint 1: iOS home-screen icon paired with the manifest -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc', 'navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			erSvc.loadingDialog("Loading Event Data");
			erSvc.getAccountIdFromURL().then(function(urlAcct){
				getAccountEvents();
				dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
					$scope.accountid = urlAcct || '<?=$_SESSION['accountid'] ?>';
					if(!$scope.accountid) window.location = "/landing.php";
					else $scope.accountRetrieved = true;
					setTimeout(() => $('.navbar').show() , 200);
					$scope.accountName = resp[0].name;
					$scope.attendeeMessage = resp[0].home_pg_msg;
					$scope.$applyAsync();
				});
				dataSvc.getArray({'query':'currentSeasonPassCount'}).then(function(resp){
					if(resp[0] && resp[0].curPassCount) $scope.hasSeasonPasses = resp[0].curPassCount > 0;
				});
			});

			function getAccountEvents(){
				dataSvc.getObject({'query':'accountEvents'}).then(function(resp){
					$scope.events = resp;
					$scope.recentEvents = [];
					angular.forEach($scope.events,function(evt){
						evt.datesPending = evt.startdate.indexOf('0000-00') >= 0;
						if(['recent','past'].includes(evt.status) && evt.hide_after_start != '1' && evt.visible == '1' && evt.archived != '1'){
							$scope.recentEvents.push(evt);
						} 
					});
					erSvc.closeLoading();
					$scope.$applyAsync();
				});
			}

			$scope.upcomingEvents = event => event.status == 'future' && event.visible == '1';		
			$scope.accountImg = img => img.indexOf('/account') > 0;

			$scope.selectEvent = function(evt){
				$.post('/attendee/clearAttendeeSessionInfo.php',function(){
					if(evt.id == '1097') window.location = '/learningCenter/psugevents';
					else window.location = '/e/' + evt.slug + '/' + (evt.homePg ? evt.homePg : 'register');
				});
			};
		});//End Controller
	</script>
</head>
<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController" ng-cloak 
		ng-show="accountRetrieved">
		<div class="row">
			<div class="col-lg-12">
				<H2 class="page-header">{{accountName}}</H2>
				<div class='alert alert-danger' role='alert' ng-show="attendeeMessage"
					style="font-size:large">
					{{attendeeMessage}}
				</div>
				<h2>Upcoming Events</h2>
			</div>
			<div class="col-md-3" ng-cloak ng-show="hasSeasonPasses">
				<div class='card'>
					<div class='card-header bold' style='min-height:10em;'>
						<h3 class="center bold">Season Passes</h3>
					</div>
					<div class='card-body'>
						<div style='text-align:center;min-height:10em;'>
							Season Passes Available
						</div>
						<p style='height:10em;'></p>
						<a href='/seasonPasses.php' class='btn btn-primary'>See Pass Offerings</a>
					</div>
				</div>
			</div>
			<div ng-repeat="event in events | orderObjectBy:'startdate':false | filter : upcomingEvents"
				class='col-md-3' ng-cloak>
				<div class='card'>
					<div class='card-header bold' style='min-height:10em;'>
						<h5 class="card-title">{{event.name}}</h5>
						<div>{{event.site}}</div>
						<div>
							{{event.city}}<span ng-show="event.city && event.state">,</span> 
							{{event.state}}
						</div>
						<div ng-show="!event.datesPending && event.startdate != event.enddate">
							{{event.formattedStart}} - {{event.formattedEnd}}
						</div>
						<div ng-show="!event.datesPending && event.startdate == event.enddate">
							{{event.formattedStart}}
						</div>
						<div ng-show="event.datesPending">Dates To Be Determined</div>
						<div ng-show="event.start_time">{{event.start_time}}</div>
					</div>
					<div class='card-body'>
						<div style='text-align:center;min-height:10em;'>
							<a class="pointer" ng-click="selectEvent(event)">
								<img ng-src='{{event.logo}}' ng-show="accountImg(event.logo)"
									style='height:10em;max-width:18em' />
								<img ng-src='{{event.logo}}'  ng-show="!accountImg(event.logo)"
									style='height:10em;max-width:18em' />
							</a>
						</div>
						<p style='height:10em; overflow:auto; white-space:pre-wrap;' 
							ng-bind-html="event.blurb | trustHtml"></p>
						<a href='#' ng-click="selectEvent(event)" class='btn btn-primary'>
							Learn More
						</a>
					</div>
				</div>
			</div>
		</div>

		 <div class="row" ng-cloak ng-show="recentEvents.length">
			<div class="col-lg-12">
				<h2>Recent Events</h2>
			</div>
			<div ng-repeat="event in recentEvents | orderBy:'startdate':true "
				class='col-md-3' >
				<div class='card'>
					<div class='card-header bold' style='min-height:10em;'>
						<h4>{{event.name}}</h4>
						<div>
							{{event.city}}<span ng-show="event.city && event.state">, </span>
							{{event.state}}
						</div>
						<div ng-show="event.startdate != event.enddate">
							{{event.formattedStart}} - {{event.formattedEnd}}
						</div>
						<div ng-show="event.startdate == event.enddate">
							{{event.formattedStart}}
						</div>
						<div ng-show="event.start_time">{{event.start_time}}</div>
					</div>
					<div class='card-body'>
						<div style='text-align:center;min-height:10em;'>
							<a class="pointer" ng-click="selectEvent(event)">
								<img ng-src='{{event.logo}}' ng-show="accountImg(event.logo)"
									style='height:10em;max-width:15em' />
								<img ng-src='{{event.logo}}'  ng-show="!accountImg(event.logo)"
									style='height:10em;max-width:15em' />
							</a>
						</div>
						<p style='height:10em; overflow:hidden; white-space: pre-wrap;' 
							ng-bind-html="event.blurb | trustHtml"></p>
						<a href='#' ng-click="selectEvent(event)" class='btn btn-primary'>Learn More</a>
					</div>
				</div>
			</div>
		</div>
	</div> <!-- End Controller -->
	<er-Footer/>
	<script>
		(function () { // Sprint 1: IIFE keeps SW helpers off AngularJS $scope
			if (!('serviceWorker' in navigator)) { // Sprint 1: feature-detect before async SW registration
				return; // Sprint 1: legacy browsers keep the PHP page with no worker
			}
			function registerTepSw() { // Sprint 1: async registration after first paint
				navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (err) { // Sprint 1: root SW matches manifest scope; fail soft
					console.warn('TEP service worker registration failed', err); // Sprint 1: log only — do not throw into $scope
				});
			}
			if (document.readyState === 'complete') { // Sprint 1: async-safe if this script runs after window load
				registerTepSw(); // Sprint 1: register immediately when load already fired
			} else {
				window.addEventListener('load', registerTepSw); // Sprint 1: wait for load so AngularJS digest is not blocked
			}
		})();
	</script>
</body>
</html>