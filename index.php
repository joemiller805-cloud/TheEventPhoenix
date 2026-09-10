<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PSUGevents.com</title>
	<style>
		.navbar{display:none;} /* Existing: hide until accountRetrieved then jQuery .show() */
		html, body { overscroll-behavior-y: contain; } /* Dashboard PTR owns the top-edge gesture; avoid a double native reload */
		.tep-dashboard .btn-primary,
		.tep-dashboard a.btn-primary,
		.tep-dashboard button.btn-primary { min-height: 48px; padding-top: 12px; padding-bottom: 12px; display: inline-flex; align-items: center; justify-content: center; } /* WCAG 2.5.5-style 48px primary tap targets */
		.tep-dashboard input,
		.tep-dashboard select,
		.tep-dashboard textarea { min-height: 48px; } /* Same floor for form controls if this view adds them */
		.navbar-toggler { min-height: 48px; min-width: 48px; } /* Mobile nav hamburger on this page */
		.tep-ptr-indicator { min-height: 48px; line-height: 48px; text-align: center; color: #E65100; font-weight: bold; } /* Pull-to-refresh status row */
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
		app.controller('regController', function($scope, $http, dataSvc, erSvc, $element, $timeout) { // $element = PTR host; $timeout = check-in search debounce
			function hideLoading() { // Explicit dismiss for every dashboard AJAX success and error
				if (erSvc && typeof erSvc.hideLoading === 'function') { // Preferred helper
					erSvc.hideLoading(); // jQuery UI loadingDialog + legacy $.unblockUI
				} else if (erSvc && typeof erSvc.closeLoading === 'function') { // Older erSvc without hideLoading
					erSvc.closeLoading(); // Dialog only
				}
			}
			erSvc.loadingDialog("Loading Event Data");
			$scope.polls = []; // Live poll widget rows; ng-repeat is empty until loadPolls runs
			$scope.pushNotify = { supported: false, busy: false, enabled: false, blocked: false, message: '' }; // Dashboard Web Push toggle; never throws into the layout
			$scope.pullRefresh = { dy: 0, busy: false, message: '' }; // Pull-to-refresh indicator bound on the dashboard view
			$scope.checkIn = { q: '', rows: [], busy: false, message: '', searchTimer: null }; // Staff check-in card; never throws into the layout
			$scope.vendorOps = { booth: null, leads: [], form: { attendee_name: '', email: '', company: '', ticket: '', notes: '' }, busy: false, message: '' }; // Vendor booth + lead capture
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
					$scope.refreshPushNotifyState(); // Reflect existing permission/subscription without prompting
					$scope.loadVendorStatus(); // Booth assignment + recent leads for Vendor Operations
					$scope.$applyAsync();
				}).catch(function () { // accountInfo transport/parse failure
					hideLoading(); // Do not leave "Loading Event Data" on screen
					$scope.$applyAsync(); // Digest
				});
				dataSvc.getArray({'query':'currentSeasonPassCount'}).then(function(resp){
					if(resp[0] && resp[0].curPassCount) $scope.hasSeasonPasses = resp[0].curPassCount > 0;
				}).catch(function () { // Season-pass flag failed
					hideLoading(); // Spinner must not stick if this GET is the last one standing
				});
			}).catch(function () { // URL/account helper failed before events were requested
				hideLoading(); // getAccountEvents never ran; dismiss the modal here
				$scope.$applyAsync(); // Digest
			});

			function getAccountEvents(){
				return dataSvc.getObject({'query':'accountEvents'}).then(function(resp){ // Return the promise so pull-to-refresh can wait
					$scope.events = resp;
					$scope.recentEvents = [];
					angular.forEach($scope.events,function(evt){
						evt.datesPending = evt.startdate.indexOf('0000-00') >= 0;
						if(['recent','past'].includes(evt.status) && evt.hide_after_start != '1' && evt.visible == '1' && evt.archived != '1'){
							$scope.recentEvents.push(evt);
						} 
					});
					$scope.$applyAsync();
				}).catch(function () { // accountEvents $http rejection
					$scope.events = $scope.events || {}; // Keep the dashboard usable
					$scope.recentEvents = $scope.recentEvents || []; // Empty recent row
					$scope.$applyAsync(); // Digest
				}).finally(hideLoading); // Success or error: never leave "Loading Event Data" up
			}

			$scope.upcomingEvents = event => event.status == 'future' && event.visible == '1';		
			$scope.accountImg = img => img.indexOf('/account') > 0;

			$scope.selectEvent = function(evt){
				$.post('/attendee/clearAttendeeSessionInfo.php',function(){
					if(evt.id == '1097') window.location = '/learningCenter/psugevents';
					else window.location = '/e/' + evt.slug + '/' + (evt.homePg ? evt.homePg : 'register');
				}).fail(function () { // Session-clear POST failed
					hideLoading(); // Do not leave a spinner over a stuck click
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
				}).catch(function () { // getActivePoll rejection
					$scope.$applyAsync(); // Digest
				}).finally(hideLoading); // Success or error: drop a leftover Loading Event Data modal
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
				}).catch(function () { // submitPollVote rejection
					poll.busy = false; // Unlock the 48px buttons
					poll.message = 'Vote could not be saved. Try again.'; // Fail-soft
					$scope.$applyAsync(); // Digest
				}).finally(hideLoading); // Success or error: never leave the spinner up
			};
			var TEP_VAPID_PUBLIC_KEY = <?php echo json_encode(tep_vapid_public_key(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; // applicationServerKey; tep_config overrides the local fallback
			function tepUrlBase64ToUint8Array(base64String) { // Chrome subscribe() wants a Uint8Array, not a string
				var padding = '='.repeat((4 - (base64String.length % 4)) % 4); // Restore standard base64 padding
				var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/'); // URL-safe → standard
				var rawData = atob(base64); // Binary string
				var outputArray = new Uint8Array(rawData.length); // Byte view for applicationServerKey
				for (var i = 0; i < rawData.length; ++i) { // Copy each byte
					outputArray[i] = rawData.charCodeAt(i); // Uint8
				}
				return outputArray; // Ready for pushManager.subscribe
			}
			function tepRequestNotifyPermission() { // Promise wrapper; old Safari used a callback only
				try {
					var result = Notification.requestPermission(); // May return a Promise or undefined
					if (result && typeof result.then === 'function') { // Modern browsers
						return result; // granted | denied | default
					}
					return Promise.resolve(Notification.permission); // Sync fallback after the prompt
				} catch (permErr) { // API missing or threw
					return Promise.resolve(Notification.permission || 'denied'); // Fail closed; do not break the dashboard
				}
			}
			$scope.refreshPushNotifyState = function () { // Feature-detect and read current subscription; no permission prompt
				var state = $scope.pushNotify; // Bound toggle model
				state.supported = !!(window.Notification && navigator.serviceWorker && window.PushManager); // Secure-context APIs
				if (!state.supported) { // HTTP non-localhost, old browsers
					state.blocked = false; // Not a user deny
					state.enabled = false; // Cannot subscribe
					state.message = 'Notifications are not available in this browser.'; // Honest empty state
					$scope.$applyAsync(); // Digest the message
					return; // Leave events/polls alone
				}
				if (Notification.permission === 'denied') { // User or browser blocked the site
					state.blocked = true; // Disable the toggle
					state.enabled = false; // Treat as off
					state.message = 'Notifications are blocked in this browser. Allow them in site settings to enable.'; // Recovery hint
					$scope.$applyAsync(); // Digest
					return; // Do not call requestPermission (it will not re-prompt)
				}
				state.blocked = false; // default or granted
				if (!navigator.serviceWorker.ready) { // No ready promise
					state.enabled = false; // Treat as off
					$scope.$applyAsync(); // Digest
					return; // SW IIFE still registers on load
				}
				navigator.serviceWorker.ready.then(function (reg) { // Wait for /sw.js
					return reg.pushManager.getSubscription(); // Existing PushSubscription or null
				}).then(function (sub) {
					state.enabled = !!sub; // Toggle on when a subscription already exists
					if (state.enabled && !state.message) { // First paint with an existing sub
						state.message = 'Notifications on.'; // Confirm without a prompt
					}
					$scope.$applyAsync(); // Digest button label
				}).catch(function () { // getSubscription can fail if SW is broken
					state.enabled = false; // Stay off; dashboard remains usable
					$scope.$applyAsync(); // Digest
				});
			};
			$scope.togglePushNotifications = function () { // Permission prompt + subscribe or unsubscribe; fail-soft
				var state = $scope.pushNotify; // Bound toggle model
				if (state.busy || state.blocked || !state.supported) { // Ignore taps that cannot succeed
					return; // Do not throw
				}
				state.busy = true; // Disable the 48px button while async work runs
				state.message = ''; // Clear prior status
				if (state.enabled) { // Toggle off: drop the browser subscription (server row can stay until send 410)
					navigator.serviceWorker.ready.then(function (reg) { // Same /sw.js registration
						return reg.pushManager.getSubscription(); // Need the PushSubscription to unsubscribe
					}).then(function (sub) {
						if (sub && typeof sub.unsubscribe === 'function') { // Standard Push API
							return sub.unsubscribe(); // Resolves true/false
						}
					}).then(function () {
						state.enabled = false; // Button returns to Enable
						state.busy = false; // Re-enable taps
						state.message = 'Notifications off.'; // Confirm without reload
						$scope.$applyAsync(); // Digest
					}).catch(function () { // Unsubscribe failed; keep current UI honest
						state.busy = false; // Unlock the button
						state.message = 'Could not turn notifications off. Try again.'; // Dashboard still works
						$scope.$applyAsync(); // Digest
					});
					return; // Enable path is below
				}
				tepRequestNotifyPermission().then(function (perm) { // Browser chrome prompt (or immediate denied)
					if (perm !== 'granted') { // dismissed or denied
						state.busy = false; // Unlock
						if (perm === 'denied' || Notification.permission === 'denied') { // Sticky block
							state.blocked = true; // Disable further prompts
							state.message = 'Notifications are blocked in this browser. Allow them in site settings to enable.'; // Recovery hint
						} else { // default / dismissed
							state.message = 'Notifications were not enabled.'; // No layout break
						}
						$scope.$applyAsync(); // Digest
						return Promise.reject(new Error('tep-push-not-granted')); // Skip subscribe without an uncaught throw in AngularJS
					}
					if (!TEP_VAPID_PUBLIC_KEY) { // No applicationServerKey
						state.busy = false; // Unlock
						state.message = 'Push is not configured on this server.'; // Fail-soft
						$scope.$applyAsync(); // Digest
						return Promise.reject(new Error('tep-push-no-vapid')); // Skip subscribe
					}
					return navigator.serviceWorker.ready; // /sw.js must be active for pushManager
				}).then(function (reg) {
					var opts = { userVisibleOnly: true }; // Chrome requires visible notifications (no silent push)
					opts.applicationServerKey = tepUrlBase64ToUint8Array(TEP_VAPID_PUBLIC_KEY); // VAPID public key bytes
					return reg.pushManager.subscribe(opts); // Creates or returns the PushSubscription
				}).then(function (sub) {
					var json = {}; // PushSubscription.toJSON()
					try { json = sub.toJSON() || {}; } catch (jsonErr) { json = {}; } // Fail-soft
					var keys = json.keys || {}; // p256dh + auth
					var endpoint = json.endpoint || ''; // HTTPS push endpoint
					var p256dh = keys.p256dh || ''; // Client public key
					var auth = keys.auth || ''; // Auth secret
					if (!endpoint || !p256dh || !auth) { // Browser omitted keys
						state.busy = false; // Unlock
						state.message = 'This browser did not return push keys.'; // Stay on the dashboard
						$scope.$applyAsync(); // Digest
						return; // Do not call savePushSubscription
					}
					return dataSvc.getArray({ // Existing GET query path (PDO upsert server-side)
						'query': 'savePushSubscription', // Sprint 6 endpoint
						'endpoint': endpoint, // Bound HTTPS URL
						'p256dh': p256dh, // Bound key
						'auth': auth // Bound secret
					}).then(function (rows) { // HTTP 200 JSON rows
						var result = (angular.isArray(rows) && rows[0]) ? rows[0] : {}; // ok / reason
						state.busy = false; // Unlock
						if (result.ok === '1') { // Stored
							state.enabled = true; // Toggle on
							state.message = 'Notifications on.'; // Confirm without reload
						} else { // Transport error string or ok:0
							state.enabled = true; // Browser is subscribed even if save failed
							state.message = 'On this device, but they could not be saved. Try again.'; // Honest status
						}
						$scope.$applyAsync(); // Digest button + message
					}).catch(function () { // savePushSubscription rejection
						state.busy = false; // Unlock
						$scope.$applyAsync(); // Digest
					}).finally(hideLoading); // Success or error: drop a leftover spinner
				}).catch(function (err) { // Permission skip, subscribe throw, or SW failure
					if (err && (err.message === 'tep-push-not-granted' || err.message === 'tep-push-no-vapid')) { // Already messaged
						return; // Avoid a second status line
					}
					state.busy = false; // Unlock
					if (window.Notification && Notification.permission === 'denied') { // Race: user blocked during subscribe
						state.blocked = true; // Disable the toggle
						state.message = 'Notifications are blocked in this browser. Allow them in site settings to enable.'; // Recovery hint
					} else { // AbortError, InvalidStateError, etc.
						state.message = 'Notifications could not be enabled in this browser.'; // Fail-soft
					}
					$scope.$applyAsync(); // Digest — dashboard events/polls unchanged
				}).finally(hideLoading); // Permission skip and subscribe errors still dismiss the modal
			};
			$scope.refreshDashboard = function () { // Reload dashboard data without location.reload (keeps AngularJS $scope)
				if ($scope.pullRefresh.busy) { // Ignore a second pull while in flight
					return; // Do not stack getAccountEvents
				}
				$scope.pullRefresh.busy = true; // Show the 48px status row
				$scope.pullRefresh.message = 'Refreshing…'; // Bound label
				$scope.pullRefresh.dy = 0; // Collapse the rubber-band
				$scope.$applyAsync(); // Digest the indicator
				$scope.loadPolls(); // Same Network-First poll path as first paint
				$scope.refreshPushNotifyState(); // Re-read permission/subscription; no prompt
				$scope.loadVendorStatus(); // Refresh booth + lead list
				dataSvc.getArray({'query':'currentSeasonPassCount'}).then(function (resp) { // Same season-pass flag as first paint
					if (resp[0] && resp[0].curPassCount) { // Truthy count
						$scope.hasSeasonPasses = resp[0].curPassCount > 0; // Card visibility
					} else {
						$scope.hasSeasonPasses = false; // Hide if the refresh returns empty
					}
					$scope.$applyAsync(); // Digest season-pass card
				}).catch(function () { // Season-pass refresh failed
					hideLoading(); // Do not leave the initial modal up
				});
				Promise.resolve(getAccountEvents()).then(function () { // Events list is the slow path
					$scope.pullRefresh.busy = false; // Hide the spinner row
					$scope.pullRefresh.message = ''; // Clear status
					$scope.$applyAsync(); // Digest
				}).catch(function () { // dataSvc failure must not freeze the UI
					$scope.pullRefresh.busy = false; // Unlock another pull
					$scope.pullRefresh.message = ''; // Clear status
					$scope.$applyAsync(); // Digest — existing events stay on screen
				}).finally(hideLoading); // getAccountEvents already finallys; this is a second safe dismiss
			};
			(function tepBindPullToRefresh() { // Touch-only PTR on the dashboard root; desktop mouse scroll unchanged
				var el = $element && $element[0]; // ng-controller host (the .tep-dashboard div)
				if (!el || !('ontouchstart' in window)) { // No touch surface
					return; // Leave desktop behavior alone
				}
				var ptrStartY = 0; // Finger Y at touchstart
				var ptrTracking = false; // True only while pulling at scroll-top
				var PTR_THRESHOLD = 64; // Pixels of downward travel before release refreshes
				function tepScrollTop() { // Page scroll, not the inner card overflow
					return window.pageYOffset || document.documentElement.scrollTop || 0; // 0 = at top
				}
				el.addEventListener('touchstart', function (e) { // Remember the start Y
					if ($scope.pullRefresh.busy) { // Already refreshing
						return; // Ignore
					}
					if (!e.touches || !e.touches[0]) { // Defensive
						return; // Ignore
					}
					if (tepScrollTop() > 0) { // User is mid-list
						ptrTracking = false; // This is a normal scroll
						return; // Do not capture
					}
					ptrTracking = true; // Candidate pull
					ptrStartY = e.touches[0].clientY; // Anchor
					$scope.pullRefresh.dy = 0; // Reset the indicator
				}, { passive: true }); // Never block the first touch
				el.addEventListener('touchmove', function (e) { // Rubber-band while at top
					if (!ptrTracking || $scope.pullRefresh.busy) { // Not a pull
						return; // Let the browser scroll
					}
					if (!e.touches || !e.touches[0]) { // Defensive
						return; // Ignore
					}
					var dy = e.touches[0].clientY - ptrStartY; // Downward is positive
					if (tepScrollTop() > 0 || dy < 0) { // Scrolled away or pulling up
						ptrTracking = false; // Hand back to native scroll
						if ($scope.pullRefresh.dy !== 0) { // Clear a leftover indicator
							$scope.pullRefresh.dy = 0; // Hide the row
							$scope.$applyAsync(); // Digest
						}
						return; // Do not preventDefault
					}
					$scope.pullRefresh.dy = Math.min(dy, 96); // Cap so the row does not grow forever
					$scope.$applyAsync(); // Digest Pull / Release copy
					if (dy > 12 && e.cancelable) { // Only after a clear downward intent
						e.preventDefault(); // Keep the gesture on PTR instead of the browser's native reload
					}
				}, { passive: false }); // Need preventDefault on the rubber-band
				el.addEventListener('touchend', function () { // Refresh or cancel
					if (!ptrTracking) { // Was a normal scroll
						return; // Ignore
					}
					ptrTracking = false; // End the gesture
					var shouldRefresh = $scope.pullRefresh.dy >= PTR_THRESHOLD && !$scope.pullRefresh.busy; // Threshold met
					$scope.pullRefresh.dy = 0; // Collapse the rubber-band
					if (shouldRefresh) { // Fire the existing dashboard loaders
						$scope.refreshDashboard(); // $applyAsync inside
					} else {
						$scope.$applyAsync(); // Digest the collapsed indicator
					}
				}, { passive: true }); // End does not need preventDefault
				el.addEventListener('touchcancel', function () { // Finger interrupted
					ptrTracking = false; // Abort
					$scope.pullRefresh.dy = 0; // Hide the row
					$scope.$applyAsync(); // Digest
				}, { passive: true }); // Cancel is always passive
			})();
			$scope.searchCheckIns = function () { // getAttendeeCheckInStatus by name or ticket; HTTP 200 rows
				if ($scope.checkIn.searchTimer) { // Cancel a pending debounce
					$timeout.cancel($scope.checkIn.searchTimer); // Do not double-fetch
					$scope.checkIn.searchTimer = null; // Clear handle
				}
				var q = ($scope.checkIn.q || '').trim(); // Search box
				if (q.length < 2) { // Server also rejects short q
					$scope.checkIn.rows = []; // Hide stale hits
					$scope.checkIn.message = q.length ? 'Type at least two characters.' : ''; // Hint vs idle
					$scope.checkIn.busy = false; // Unlock
					$scope.$applyAsync(); // Digest
					hideLoading(); // No GET fired; still drop a stuck initial spinner
					return; // Do not call the API
				}
				$scope.checkIn.busy = true; // Disable search while in flight
				$scope.checkIn.message = ''; // Clear prior status
				dataSvc.getArray({ // Existing GET query path (PDO select server-side)
					'query': 'getAttendeeCheckInStatus', // Staff search
					'q': q // Name or confirmation/ticket
				}).then(function (rows) { // HTTP 200 JSON rows
					if (!angular.isArray(rows)) { // Transport "error" string
						$scope.checkIn.rows = []; // Keep the card
						$scope.checkIn.message = 'Search could not be completed. Try again.'; // Fail-soft
					} else {
						$scope.checkIn.rows = rows; // ng-repeat source
						$scope.checkIn.message = rows.length ? '' : 'No matching attendees.'; // Empty state
					}
					$scope.checkIn.busy = false; // Unlock
					$scope.$applyAsync(); // Digest the list
				}).catch(function () { // getAttendeeCheckInStatus rejection
					$scope.checkIn.busy = false; // Unlock Search
					$scope.checkIn.message = 'Search could not be completed. Try again.'; // Fail-soft
					$scope.$applyAsync(); // Digest
				}).finally(hideLoading); // Success or error: never leave "Loading Event Data" up
			};
			$scope.onCheckInQueryChange = function () { // Debounced search as the staff types
				if ($scope.checkIn.searchTimer) { // Replace the previous timer
					$timeout.cancel($scope.checkIn.searchTimer); // One in-flight debounce
				}
				$scope.checkIn.searchTimer = $timeout(function () { // 350ms after last key
					$scope.searchCheckIns(); // Same path as the Search button
				}, 350); // Fast enough for a door line, slow enough to skip per-key GETs
			};
			$scope.onCheckInKey = function ($event) { // Enter submits immediately
				if ($event && $event.which === 13) { // Return key
					$event.preventDefault(); // Do not submit a phantom form
					$scope.searchCheckIns(); // Skip the debounce
				}
			};
			$scope.toggleCheckIn = function (row) { // Instant check-in / undo; HTTP 200 ok/reason
				if (!row || row.busy || $scope.checkIn.busy) { // Ignore double-taps
					hideLoading(); // Do not leave a spinner over a ignored tap
					return; // Do not fire a second checkInAttendee
				}
				row.busy = true; // Disable this 48px button
				$scope.checkIn.message = ''; // Clear list-level status
				dataSvc.getArray({ // Existing GET query path (PDO update server-side)
					'query': 'checkInAttendee', // Toggle write
					'id': row.id // registrations.id
				}).then(function (rows) { // HTTP 200 JSON rows
					var result = (angular.isArray(rows) && rows[0]) ? rows[0] : {}; // ok / checked_in / reason
					if (result.ok === '1') { // Toggle stored
						row.checked_in = result.checked_in; // Flip the button label
						$scope.checkIn.message = (result.checked_in === '1') ? 'Checked in.' : 'Check-in cleared.'; // Confirm without reload
					} else { // invalid / not_found / unavailable
						$scope.checkIn.message = 'Check-in could not be saved. Try again.'; // Stay on the dashboard
					}
					row.busy = false; // Re-enable
					$scope.$applyAsync(); // Digest button + message
				}).catch(function () { // checkInAttendee rejection
					row.busy = false; // Unlock the 48px button
					$scope.checkIn.message = 'Check-in could not be saved. Try again.'; // Fail-soft
					$scope.$applyAsync(); // Digest
				}).finally(hideLoading); // Success or error: dismiss spinner / blockUI
			};
			$scope.loadVendorStatus = function () { // getVendorStatus booth + recent_leads_json
				dataSvc.getArray({'query':'getVendorStatus'}).then(function (rows) { // HTTP 200 JSON rows
					if (!angular.isArray(rows) || !rows.length) { // No booth for this login
						$scope.vendorOps.booth = null; // Hide assignment details
						$scope.vendorOps.leads = []; // Empty list
						$scope.$applyAsync(); // Digest
						return; // Card still shows an empty state
					}
					$scope.vendorOps.booth = rows[0]; // First assignment
					var leads = []; // recent_leads_json
					try { leads = JSON.parse(rows[0].recent_leads_json || '[]'); } catch (leadErr) { leads = []; } // Fail-soft
					$scope.vendorOps.leads = angular.isArray(leads) ? leads : []; // ng-repeat
					$scope.$applyAsync(); // Digest booth + leads
				}).catch(function () { // getVendorStatus rejection
					$scope.vendorOps.booth = $scope.vendorOps.booth || null; // Keep last known booth
					$scope.$applyAsync(); // Digest
				}).finally(hideLoading); // Success or error: never leave "Loading Event Data" up
			};
			$scope.saveVendorLead = function () { // saveVendorLead; HTTP 200 ok/reason
				if ($scope.vendorOps.busy) { // Ignore double-taps
					hideLoading(); // Do not leave a spinner over an ignored tap
					return; // Do not fire a second insert
				}
				var form = $scope.vendorOps.form; // Bound inputs
				var name = (form.attendee_name || '').trim(); // Required
				if (name.length < 2) { // Same floor as the PDO endpoint
					$scope.vendorOps.message = 'Enter the attendee name (at least two characters).'; // Stay on the card
					$scope.$applyAsync(); // Digest
					hideLoading(); // Validation return must still clear Loading Event Data
					return; // Do not call the API
				}
				$scope.vendorOps.busy = true; // Disable the 48px save button
				$scope.vendorOps.message = ''; // Clear prior status
				dataSvc.getArray({ // Existing GET query path (PDO insert server-side)
					'query': 'saveVendorLead', // Lead write
					'attendee_name': name, // Bound name
					'email': (form.email || '').trim(), // Optional
					'company': (form.company || '').trim(), // Optional
					'ticket': (form.ticket || '').trim(), // Optional confirmation
					'notes': (form.notes || '').trim(), // Optional
					'eventid': ($scope.vendorOps.booth && $scope.vendorOps.booth.eventid) ? $scope.vendorOps.booth.eventid : 0 // Booth event when present
				}).then(function (rows) { // HTTP 200 JSON rows
					var result = (angular.isArray(rows) && rows[0]) ? rows[0] : {}; // ok / reason
					$scope.vendorOps.busy = false; // Unlock
					if (result.ok === '1') { // Stored
						$scope.vendorOps.form = { attendee_name: '', email: '', company: '', ticket: '', notes: '' }; // Clear for the next attendee
						$scope.vendorOps.message = 'Lead saved.'; // Confirm without reload
						$scope.loadVendorStatus(); // Refresh count + recent list
					} else { // invalid / unavailable
						$scope.vendorOps.message = 'Lead could not be saved. Check the name and email.'; // Fail-soft
					}
					$scope.$applyAsync(); // Digest form + message
				}).catch(function () { // saveVendorLead rejection
					$scope.vendorOps.busy = false; // Unlock Save lead
					$scope.vendorOps.message = 'Lead could not be saved. Check the name and email.'; // Fail-soft
					$scope.$applyAsync(); // Digest
				}).finally(hideLoading); // Success or error: dismiss spinner / blockUI
			};
		});//End Controller
	</script>
</head>
<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid tep-dashboard" ng-controller="regController" ng-cloak 
		ng-show="accountRetrieved"> <!-- tep-dashboard: 48px tap CSS + pull-to-refresh host -->
		<div class="tep-ptr-indicator" ng-show="pullRefresh.busy || pullRefresh.dy > 12"> <!-- 48px pull-to-refresh status; hidden until a pull -->
			<span ng-show="pullRefresh.busy">{{pullRefresh.message}}</span> <!-- Refreshing… from $scope.pullRefresh -->
			<span ng-show="!pullRefresh.busy && pullRefresh.dy >= 64">Release to refresh</span> <!-- Threshold met -->
			<span ng-show="!pullRefresh.busy && pullRefresh.dy < 64">Pull to refresh</span> <!-- Still dragging -->
		</div>
		<div class="row">
			<div class="col-lg-12">
				<H2 class="page-header">{{accountName}}</H2>
				<div class='alert alert-danger' role='alert' ng-show="attendeeMessage"
					style="font-size:large">
					{{attendeeMessage}}
				</div>
			</div>
			<!-- Web Push toggle: Notification.requestPermission + pushManager.subscribe + savePushSubscription -->
			<div class="col-md-6" ng-cloak>
				<div class="card">
					<div class="card-header bold">
						<h5 class="card-title">Notifications</h5>
						<div>Get TEP alerts on this device.</div>
					</div>
					<div class="card-body">
						<button type="button" class="btn btn-primary"
							style="min-height:48px;width:100%;margin-bottom:8px;font-size:1.1em;"
							ng-click="togglePushNotifications()"
							ng-disabled="pushNotify.busy || pushNotify.blocked || !pushNotify.supported">
							<span ng-show="!pushNotify.enabled">Enable notifications</span>
							<span ng-show="pushNotify.enabled">Notifications on — tap to turn off</span>
						</button>
						<div ng-show="pushNotify.message" style="margin-top:8px;">{{pushNotify.message}}</div>
					</div>
				</div>
			</div>
			<!-- Staff check-in card: getAttendeeCheckInStatus + checkInAttendee; $scope.checkIn; 48px taps -->
			<div class="col-md-6" ng-cloak>
				<div class="card">
					<div class="card-header bold">
						<h5 class="card-title">Staff check-in</h5>
						<div>Search by name or ticket, then tap to check in.</div>
					</div>
					<div class="card-body">
						<input type="search" class="form-control"
							style="min-height:48px;margin-bottom:8px;font-size:1.1em;"
							placeholder="Name or ticket"
							ng-model="checkIn.q"
							ng-change="onCheckInQueryChange()"
							ng-keyup="onCheckInKey($event)"
							ng-disabled="checkIn.busy">
						<button type="button" class="btn btn-primary"
							style="min-height:48px;width:100%;margin-bottom:8px;font-size:1.1em;"
							ng-click="searchCheckIns()"
							ng-disabled="checkIn.busy">
							Search
						</button>
						<div ng-repeat="row in checkIn.rows" style="margin-bottom:8px;">
							<div class="bold">{{row.first_name}} {{row.last_name}}</div>
							<div>Ticket {{row.confirmation}} · {{row.event_name}}</div>
							<button type="button" class="btn btn-primary"
								style="min-height:48px;width:100%;margin-top:4px;font-size:1.1em;"
								ng-click="toggleCheckIn(row)"
								ng-disabled="row.busy">
								<span ng-show="row.checked_in != '1'">Check in</span>
								<span ng-show="row.checked_in == '1'">Checked in — tap to undo</span>
							</button>
						</div>
						<div ng-show="checkIn.message" style="margin-top:8px;">{{checkIn.message}}</div>
					</div>
				</div>
			</div>
			<!-- Vendor Operations: getVendorStatus booth details + saveVendorLead; 48px taps -->
			<div class="col-md-6" ng-cloak>
				<div class="card">
					<div class="card-header bold">
						<h5 class="card-title">Vendor Operations</h5>
						<div ng-show="vendorOps.booth">{{vendorOps.booth.vendor_name}} · Booth {{vendorOps.booth.booth}}</div>
						<div ng-show="!vendorOps.booth">Booth assignment and lead capture.</div>
					</div>
					<div class="card-body">
						<div ng-show="vendorOps.booth" style="margin-bottom:8px;">
							<div>{{vendorOps.booth.hall}}</div>
							<div ng-show="vendorOps.booth.event_name">{{vendorOps.booth.event_name}}</div>
							<div ng-show="vendorOps.booth.notes">{{vendorOps.booth.notes}}</div>
							<div>{{vendorOps.booth.lead_count}} lead<span ng-show="vendorOps.booth.lead_count != 1">s</span> captured</div>
						</div>
						<div ng-show="!vendorOps.booth" style="margin-bottom:8px;">No booth assignment for this login.</div>
						<input type="text" class="form-control"
							style="min-height:48px;margin-bottom:8px;font-size:1.1em;"
							placeholder="Attendee name"
							ng-model="vendorOps.form.attendee_name"
							ng-disabled="vendorOps.busy || !vendorOps.booth">
						<input type="email" class="form-control"
							style="min-height:48px;margin-bottom:8px;font-size:1.1em;"
							placeholder="Email (optional)"
							ng-model="vendorOps.form.email"
							ng-disabled="vendorOps.busy || !vendorOps.booth">
						<input type="text" class="form-control"
							style="min-height:48px;margin-bottom:8px;font-size:1.1em;"
							placeholder="Company (optional)"
							ng-model="vendorOps.form.company"
							ng-disabled="vendorOps.busy || !vendorOps.booth">
						<input type="text" class="form-control"
							style="min-height:48px;margin-bottom:8px;font-size:1.1em;"
							placeholder="Ticket (optional)"
							ng-model="vendorOps.form.ticket"
							ng-disabled="vendorOps.busy || !vendorOps.booth">
						<textarea class="form-control"
							style="min-height:72px;margin-bottom:8px;font-size:1.1em;"
							placeholder="Notes (optional)"
							ng-model="vendorOps.form.notes"
							ng-disabled="vendorOps.busy || !vendorOps.booth"></textarea>
						<button type="button" class="btn btn-primary"
							style="min-height:48px;width:100%;margin-bottom:8px;font-size:1.1em;"
							ng-click="saveVendorLead()"
							ng-disabled="vendorOps.busy || !vendorOps.booth">
							Save lead
						</button>
						<div ng-repeat="lead in vendorOps.leads" style="margin-bottom:8px;">
							<div class="bold">{{lead.attendee_name}}</div>
							<div ng-show="lead.company">{{lead.company}}</div>
							<div ng-show="lead.email">{{lead.email}}</div>
						</div>
						<div ng-show="vendorOps.message" style="margin-top:8px;">{{vendorOps.message}}</div>
					</div>
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