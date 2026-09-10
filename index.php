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
	<link rel="apple-touch-icon" href="/pwa/icon-192.png"> <!-- Square 192 any-icon for iOS home screen; matches manifest.json -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc', 'navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			erSvc.loadingDialog("Loading Event Data");
			$scope.polls = []; // Live poll widget rows; ng-repeat is empty until loadPolls runs
			erSvc.getAccountIdFromURL().then(function(urlAcct){
				getAccountEvents();
				dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
					$scope.accountid = urlAcct || '<?=$_SESSION['accountid'] ?>';
					if(!$scope.accountid) window.location = "/landing.php";
					else $scope.accountRetrieved = true;
					setTimeout(() => $('.navbar').show() , 200);
					$scope.accountName = resp[0].name;
					$scope.attendeeMessage = resp[0].home_pg_msg;
					$scope.loadPolls(); // Live poll widget after accountid is on $scope; PDO uses the session account
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

			function tepParsePollRow(row) { // Turn API JSON into $scope-friendly option buttons
				var labels = []; // options_json is a JSON array of strings
				var counts = {}; // vote_counts_json is { "0": n, "1": n }
				try { labels = JSON.parse(row.options_json || '[]'); } catch (e) { labels = []; } // Fail-soft bad JSON
				try { counts = JSON.parse(row.vote_counts_json || '{}'); } catch (e2) { counts = {}; } // Fail-soft missing counts
				row.options = []; // ng-repeat source
				angular.forEach(labels, function (label, idx) { // One button per option
					row.options.push({ // Touch button model
						index: idx, // 0-based option_index for submitPollVote
						label: label, // Button text
						votes: Number(counts[idx] || counts[String(idx)] || 0) // Local results tally
					});
				});
				row.total_votes = Number(row.total_votes || 0); // Headline count
				row.voted = false; // Lock buttons after a successful or duplicate vote
				row.busy = false; // Disable while the GET vote is in flight
				row.message = ''; // Status line under the buttons
				return row; // Mutated row is bound on $scope.polls
			}
			$scope.loadPolls = function () { // Network-First getActivePoll; no location.reload
				dataSvc.getArray({'query':'getActivePoll'}).then(function (rows) { // Same dataSvc path as accountInfo
					if (!angular.isArray(rows)) { // getArray returns "error" on transport failure
						return; // Leave existing $scope.polls in place
					}
					$scope.polls = []; // Replace list without a full page refresh
					angular.forEach(rows, function (row) { // Parse each active poll
						$scope.polls.push(tepParsePollRow(row)); // Option buttons + vote counts
					});
					$scope.$applyAsync(); // Digest so ng-show/ng-repeat update
				});
			};
			$scope.voteOnPoll = function (poll, opt) { // Touch button handler; stays on this view
				if (!poll || !opt || poll.voted || poll.busy) { // Ignore double-taps
					return; // Do not fire a second submitPollVote
				}
				poll.busy = true; // Disable buttons immediately
				poll.message = ''; // Clear prior status
				dataSvc.getArray({ // Existing GET query endpoint (PDO insert server-side)
					'query': 'submitPollVote', // Vote write
					'pollid': poll.id, // Active poll id
					'option_index': opt.index // 0-based choice
				}).then(function (rows) { // HTTP 200 JSON rows
					var result = (angular.isArray(rows) && rows[0]) ? rows[0] : {}; // ok / reason
					if (result.ok === '1') { // New vote stored
						opt.votes = Number(opt.votes || 0) + 1; // Update local option tally
						poll.total_votes = Number(poll.total_votes || 0) + 1; // Update local total
						poll.voted = true; // Show results; lock buttons
						poll.message = 'Thanks for voting.'; // Confirm without reload
					} else if (result.reason === 'already_voted') { // Unique (pollid, voter_key)
						poll.voted = true; // Show existing counts; lock buttons
						poll.message = 'You already voted in this poll.'; // Honest status
					} else { // invalid / inactive / unavailable
						poll.message = 'Vote could not be saved. Try again.'; // Stay on the dashboard
					}
					poll.busy = false; // Re-enable only if not voted
					$scope.$applyAsync(); // Digest button disabled + counts
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
			</div>
			<!-- Live poll widget: getActivePoll + submitPollVote; $scope.polls / voteOnPoll; no page reload -->
			<div class="col-md-6" ng-repeat="poll in polls" ng-cloak>
				<div class="card">
					<div class="card-header bold">
						<h5 class="card-title">Live poll</h5>
						<div>{{poll.question}}</div>
					</div>
					<div class="card-body">
						<button type="button" class="btn btn-primary"
							style="min-height:48px;width:100%;margin-bottom:8px;font-size:1.1em;"
							ng-repeat="opt in poll.options"
							ng-click="voteOnPoll(poll, opt)"
							ng-disabled="poll.voted || poll.busy">
							{{opt.label}}
							<span ng-show="poll.voted"> — {{opt.votes}}</span>
						</button>
						<div ng-show="poll.voted" style="margin-top:8px;">
							{{poll.total_votes}} vote<span ng-show="poll.total_votes != 1">s</span>
						</div>
						<div ng-show="poll.message" style="margin-top:8px;">{{poll.message}}</div>
					</div>
				</div>
			</div>
			<div class="col-lg-12">
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