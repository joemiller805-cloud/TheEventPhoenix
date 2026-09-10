<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PSUGevents.com</title>
	<?php
		include("common_functions.php");
		include("commonStyles.php");
		include("commonJs.php");
		if(substr(str_replace('www.','',$_SERVER['HTTP_HOST']), 0, 4) == "easy"){
			print("<script language=\"javascript\" src=\"https://app.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
		}
		else{
			print("<script language=\"javascript\" src=\"https://sandbox.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
		} 
	;?>
	<script type="text/javascript" src="/js/jquery-barcode.min.js"></script>
	<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc', 'navMod']);
		app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
			//ERIC - ensure this works with Basys
			erSvc.loadingDialog("Loading Event Data");
			let eventsRetrieved = $q.defer();
			let passesRetrieved = $q.defer();
			let matchedEmail;
			$scope.view = 'passes';
			$scope.ticketView = 'condensed';

			$scope.card = {};
			$scope.card.expYear = new Date().getFullYear();
			$scope.years = [$scope.card.expYear];
			for (let i=1; i<10; i++){ $scope.years.push($scope.card.expYear + i) }

			$scope.card.expMonth = '01';
			$scope.months = ['01','02','03','04','05','06','07','08','09','10','11','12'];

			erSvc.getAccountIdFromURL().then(function(urlAcct){
				getAccountEvents();
				getSeasonPasses();
				dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
					$scope.accountid = urlAcct || '<?=$_SESSION['accountid'] ?>';
					if(!$scope.accountid) window.location = "/about/home.php";
					else $scope.accountRetrieved = true;
					setTimeout(() => $('.navbar').show() , 200);
					$scope.accountName = resp[0].name;
				});
			});

			function getAccountEvents(){
				dataSvc.getObject({'query':'accountEvents'}).then(function(resp){
					$scope.events = resp;
					eventsRetrieved.resolve();
				});
			}

			function getSeasonPasses(){
				dataSvc.getArray({'query':'currentSeasonPassDetails'}).then(function(resp){
					$scope.seasonPasses = resp;
					let ticketIds = [];
					$scope.seasonPasses.forEach(function(pass){
						try{
							pass.details = angular.fromJson(pass.event_details);
							for(eventid in pass.details){
								let curIds = Object.keys(pass.details[eventid].tickets || []);
								curIds.forEach(function(id){
									if(!ticketIds.includes(id)) ticketIds.push(id);
								});
							}
						} catch(e){console.error(e);}
					});
					if(!ticketIds.length){
						passesRetrieved.resolve();
						return;
					}
					dataSvc.getObject({'query':'registrationExtrasDetails','ids':ticketIds.join(',')}).then(function(resp){
						$scope.tickets = resp;
						passesRetrieved.resolve();
					});
					
				});
			}

			$q.all([eventsRetrieved.promise,passesRetrieved.promise]).then(function(){
				erSvc.closeLoading();
				$scope.seasonPasses.forEach(function(pass){
					for(eventid in pass.details){
						let detail = pass.details[eventid];
						detail.eventName = $scope.events[eventid].name;
						detail.startdate = $scope.events[eventid].startdate;
					}
					pass.quantity = 0;
					pass.price = Number(pass.price);
				});
				if(erSessionData.attendeeid){
					dataSvc.getArray({'query':'currentAttendeeInfo'}).then(function(resp){
						if(resp[0]){
						 	$scope.attendee = resp[0];
						 	getAttendeePasses(true);
						}
					});
				}
			});

			$scope.purchasePass = function(pass){
				$scope.selectedPass = pass;
				$scope.price = Number(pass.price);
				let feeRate = $scope.ccFeeRate || 0;
				$scope.ccFee = Math.round($scope.price * feeRate * 100) / 100;
				$scope.amountDue = Number($scope.price + $scope.ccFee);
				$scope.view = 'payment';
			};

			$scope.ccFeeRate = 0;
			dataSvc.getArray({'query':'ccProvider'}).then(function(cc){
				try{
					if(cc[0] && cc[0].ccProvider) $scope.ccProvider = cc[0].ccProvider;
					$scope.ccEnabled = $scope.ccProvider != 'none';
					if($scope.ccProvider == 'basys') initializeBasysToken(cc[0].apiKey);
				}catch(error){
					logError('ccProvider', error);
				}
			});
			dataSvc.getArray({'query':'ccChargeRt'}).then(resp => { 
				if(resp[0]) $scope.ccFeeRate = Number(resp[0].cc_charge_rt || '0') / 100;
			});

			$scope.calculateTotal = function(){
				let total = 0;
				$scope.seasonPasses.forEach(function(pass){
					if(!pass.quantity) return;
					if(pass.quantity < 0) pass.quantity	= 0;
					pass.quantity = Math.round(pass.quantity);
					total += (pass.price * pass.quantity);
				});
				$scope.purchaseTotal = total;
				$scope.purchaseTotal = Math.round($scope.purchaseTotal * 100) / 100;

				let feeRate = $scope.ccFeeRate || 0;
				$scope.ccFee = Math.round($scope.purchaseTotal * feeRate * 100) / 100;
				$scope.amountDue = Number($scope.purchaseTotal + $scope.ccFee);
			};

			$scope.completePurchase = function(){
				if (!$scope.attendeeForm.$valid){
					$('form[name="attendeeForm"]').addClass('submitted');
					return;
				}
				erSvc.encrypt($scope.regAttendee.password).then(function(pw){
					dataSvc.getArray({'query':'checkAttendeeExists','email':$scope.regAttendee.email}).then(function(resp){
						let attendeeFound = resp.length > 0;
						let matchedAttendee;
						resp.forEach(att => { if(att.password == pw) matchedAttendee = att });
						if(matchedAttendee){ //log in attendee
							loginAttendee($scope.regAttendee.email, $scope.regAttendee.password).then(function(resp){
								if(resp.data == 'success'){
									$scope.attendee = matchedAttendee;
									submitPayment().then(() => {if(!$scope.paymentFailure) createRegistrations()});
								}
							});							
						}else if(attendeeFound){ //notify attendee exists but wrong password
							matchedEmail = $scope.regAttendee.email;
							$('#foundLoginWrongPass').dialog({modal:true,title:`Account Found`,width:600});
						}else{ //crate attendee
							let newAttendee = angular.copy($scope.regAttendee);
							newAttendee.password = pw;
							dataSvc.createVideoAttendee(newAttendee).then(function(res){
								if(res.status == 'success'){
									newAttendee.id = res.insertid;
									loginAttendee($scope.regAttendee.email, $scope.regAttendee.password).then(function(resp){
										if(resp.data == 'success'){
											$scope.attendee = angular.copy(newAttendee)
											submitPayment().then(() => { if(!$scope.paymentFailure) createRegistrations() });
										}
									});							
								}else{
									erSvc.easyRegAlert({"text": "Error creating account","title": "Error"});
								}
							});
						}
					});
				});
			}; //$scope.completePurchase()

			$scope.showLogin = () => $('#loginForm').dialog({modal:true, title:`Log In`,	width:'20em'});
			$scope.closeLogin = () => $('#loginForm').dialog('close');

			$scope.submitLogin = function(){
				if($scope.login.email && $scope.login.pass){
					loginAttendee($scope.login.email,$scope.login.pass).then(function(res){
						if(res.data == 'success'){
							erSvc.easyRegAlert({"text":"You have successfully logged in","title":"Log In Success"});
							$('#loginForm').dialog('close');
							erSvc.encrypt($scope.login.pass).then(function(pw){
								let params = {'query':'checkAttendeeCredentials','email':$scope.login.email};
								dataSvc.getArray(params).then(function(att){
									$scope.attendee = att[0];
									getAttendeePasses(true);
								});
							});
						}else{
							dataSvc.getArray({'query':'checkAttendeeExists','email':$scope.login.email}).then(function(resp){
								if(resp.length){
									matchedEmail =$scope.login.email;
									$('#foundLoginWrongPass').dialog({modal:true,title:`Invalid Password`,width:600});
								}else{
									erSvc.easyRegAlert({"text":"Invalid log in credentials","title":"Error"});
								}
							});
						}
					});
				}
			};

			function getAttendeePasses(showPasses){
				dataSvc.getArray({'query':'seasonPassByAttendee'}).then(function(resp){
					$scope.eventTickets = {};
					let firstEvt;
					resp.forEach(function(ticket){
						if(ticket.curStatus != 'future') return;
						let evt = $scope.events[ticket.eventid];
						if(!firstEvt) firstEvt = evt;
						if(evt.startdate < firstEvt.startdate) firstEvt = evt;
						if(!$scope.eventTickets[ticket.eventid]){
							$scope.eventTickets[ticket.eventid] = {
								"name": evt.name,
								"site": evt.site,
								"startdate":evt.startdate,
								"starttime":evt.starttime,
								"logo":evt.logo,
								"attendees":{},
								"collapsed":true
							};
						}
						let curEvent = $scope.eventTickets[ticket.eventid];
						if(!curEvent.attendees[ticket.confirmation]){
							curEvent.attendees[ticket.confirmation] = {
								"confirmation":ticket.confirmation,
								"regType":ticket.regType,
								"tickets":[]
							};
						} 
						let curTickets = curEvent.attendees[ticket.confirmation].tickets;
						for(let i = 1; i <= Number(ticket.quantity); i++){
							curTickets.push({
								"label":`${ticket.label} ${i}`,
								"code":`${ticket.eventid}-${ticket.regid}-${ticket.orderId}-${i}`,
								"current": false
							});
						}
						curEvent.attendees[ticket.confirmation].currentTicket = curTickets[0];
					});
					if(firstEvt) $scope.eventTickets[firstEvt.id].collapsed = false;
					else $scope.noCurrentTickets = true;
					initBarcodes();
					if(showPasses) $scope.view = 'purchasedPasses';
				});
			}

			function initBarcodes(){
				setTimeout(function(){
					$('.barcode').each(function(){
						$(this).barcode($(this).attr('data-data'), 'code128',{"barHeight":30});
					});
				}, 0);
			}

			$scope.nextTicket = function(attendee){
				let idx = attendee.tickets.indexOf(attendee.currentTicket);
				attendee.currentTicket = attendee.tickets[idx + 1];
				initBarcodes();
			};

			$scope.prevTicket = function(attendee){
				let idx = attendee.tickets.indexOf(attendee.currentTicket);
				attendee.currentTicket = attendee.tickets[idx -1];
				initBarcodes();
			};

			$scope.disableTicketChg = function(prevOrNext, att){
				let idx = att.tickets.indexOf(att.currentTicket);
				if(prevOrNext == 'next') return idx == att.tickets.length - 1; 
				else return idx == 0; 
			}

			function loginAttendee(email, pass){
				let res = $.Deferred();
				$http({
					"url": '/login_attendee.php',
					"method": 'POST',
					"data": $.param({"email":email,"pass":pass,"accountid":$scope.accountid}),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				}).then(r => res.resolve(r));
				return res;
			}

			function postUserDml(params){
				return $http({
					"url": '/data_access/runUserDML.php',
					"method": 'POST',
					"data": $.param(params || {}),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				});
			}

			$scope.attendeeInitials = function(){
				if(!$scope.attendee) return;
				if (!$scope.attendee.first_name || !$scope.attendee.last_name) return;
				return $scope.attendee.first_name.substr(0, 1) + $scope.attendee.last_name.substr(0, 1);
			};

			let paymentStatus;
			function submitPayment(){
				try{
					$scope.paymentAmount = $scope.amountDue.toFixed(2);
					paymentStatus = $.Deferred();
					$scope.paymentSuccess = false;
					$scope.paymentFailure = false;
					$scope.authMessage = '';
					$scope.paymentMessage = '';
					erSvc.loadingDialog("Processing Payment");

					if($scope.ccProvider == 'basys'){
						basysToken.submit();
						return paymentStatus.promise();
					}

					//if we're still here, we're using MagicWrighter
					let pymtNum = $scope.attendee.first_name.substring(0,1);
					pymtNum += $scope.attendee.last_name.substring(0,1);
					pymtNum += $scope.attendee.id + 'SsnPss';
					var submitData = {
						"name":$scope.card.name,
						"email":$scope.card.email,
						"street":$scope.card.street,
						"city":$scope.card.city,
						"state":$scope.card.state,
						"zip":$scope.card.zip,
						"amount":$scope.paymentAmount,
						"cardNumber":$scope.card.number,
						"cvv":$scope.card.cvv,
						"expiration":$scope.card.expMonth + $scope.card.expYear.toString().substr(-2),
						"regConfirmation":pymtNum,
						"regId":$scope.attendee.id,
						"accountid":$scope.accountid
					};
					$http({
						"url": '/process_payment.php',
						"method": 'POST',
						"data": $.param(submitData),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
					}).then(function(response){
						try{
							var paymentInfo = response.data.paymentDetails.PaymentInfo;
							var authInfo = response.data.paymentDetails.AuthInfo;
							if(paymentInfo['@attributes'].ErrorCode == 0){
								$scope.paymentSuccess = true;
								$scope.paymentConfirmation = paymentInfo['@attributes'].ConfirmationNumber;
								paymentStatus.resolve(true);
							}else{
								$scope.paymentFailure = true;
								$scope.authMessage = authInfo['@attributes'].Message;
								$scope.paymentMessage = paymentInfo['@attributes'].PaymentMessage;
								paymentStatus.resolve(false);
							}

						}catch(e){
							logError('submitPayment - process_payment callback', e, '', JSON.stringify(response));
							console.error(e)
							$scope.paymentFailure = true;
							paymentStatus.resolve(false);
						}
						erSvc.closeLoading();
					});
					return paymentStatus.promise();
				}catch(error){
					logError('submitPayment', error);
				}
			}// End submitPayment()

			async function createRegistrations(){
				let passOrderId = await createPassPurchaseRecord();
				let registrationsToCreate = {};
				//build registrations/tickets to be purchased
				$scope.seasonPasses.forEach(function(pass){
					if(!pass.quantity) return;
					for(evtid in pass.details){
						let evtDetails = pass.details[evtid];
						if(!registrationsToCreate[evtid]){
							registrationsToCreate[evtid] = {};
							registrationsToCreate[evtid][evtDetails.regType] = {};
						}else{
							if(!registrationsToCreate[evtid][evtDetails.regType]) 
								registrationsToCreate[evtid][evtDetails.regType] = {};
						}
						let tickets = registrationsToCreate[evtid][evtDetails.regType];
						for(tktId in evtDetails.tickets){
							if(!evtDetails.tickets[tktId]) continue;
							if(!tickets[tktId]) tickets[tktId] = evtDetails.tickets[tktId] * pass.quantity;
							else tickets[tktId] += (evtDetails.tickets[tktId] * pass.quantity);
						}
					}
				});

				// At this point, registrationsToCreate will look something like this 
				// 	123:{ - eventid
				// 		456:{ - registration type id 
				// 			111:2, ticketid and quantity
				// 			222:1
				// 		},... more reg types if included
				// 	},... more events if included
				let registrationsDone = 0;
				for(eventid in registrationsToCreate){
					for(regTypeid in registrationsToCreate[eventid]){
						let ticketDetails = registrationsToCreate[eventid][regTypeid];
						completeRegistration(regTypeid, eventid, ticketDetails, passOrderId).then(function(){
							if(++registrationsDone == Object.keys(registrationsToCreate).length){
								getAttendeePasses(false);
								$scope.seasonPasses.forEach(p => p.quantity = 0);
							}
						});
					}
				}
			};

			function completeRegistration(regTypeid, eventid, ticketDetails, passOrderId){
				let done = $.Deferred();
				let reg = {
					"attendeeid":$scope.attendee.id, 
					"payment_number": $scope.paymentConfirmation,
					"registration_typeid": regTypeid,
					"first_name":$scope.attendee.first_name,
					"last_name":$scope.attendee.last_name,
					"email":$scope.attendee.email,
					"season_pass_ordersid": passOrderId
				};
				dataSvc.createOrUpdateRegistration(reg, eventid).then(function(res){
					if(!res.confirmation){
						erSvc.easyRegAlert({"text":"Error saving registration.  Please contact site administrator","title":"Error"});
					}else{
						reg.confirmation = res.confirmation;
						reg.id = res.id;
					}
					processRegistrationExtras(eventid, reg, ticketDetails).then(() => done.resolve());
				});
				return done;
			} // end completeRegistration()

			function processRegistrationExtras(eventid, reg, ticketDetails){
				let done = $.Deferred();
				if(!Object.keys(ticketDetails).length) done.resolve();
				let ordersComplete = 0;
				try{
					for(const id in ticketDetails){
						let newExtra = {
							"registrationid": reg.id,
							"registration_extras_id": id,
							"quantity":ticketDetails[id]
						};
						dataSvc.createOrUpdateRecord({"table":"registration_extra_orders","record":newExtra}).then(function(res){
							if(++ordersComplete == Object.keys(ticketDetails).length) done.resolve();
						});
					}					
				}catch(error){
					logError('processRegistrationExtras', error);
					let msg = "There was an error creating your order. Please contact the vendor."
					erSvc.easyRegAlert({"text":msg,"title":"Error"});
					done.resolve();
				}
				return done;
			}//end processRegistrationExtras()

			function createPassPurchaseRecord(){
				let response = $q.defer();
				let orderDetails = [];
				$scope.seasonPasses.forEach(function(pass){
					if(!pass.quantity) return;
					orderDetails.push({
						"passId":pass.id,
						"price":pass.price,
						"quantity":pass.quantity
					});
				});
				let passData = {
					"query":"createSeasonPassPurchase",
					"accountid":$scope.accountid,
					"order_details":angular.toJson(orderDetails),
					"amt_charged":$scope.purchaseTotal,
					"cc_fee":$scope.ccFee, 
					"ref_nbr":$scope.paymentConfirmation
				};
				postUserDml(passData).then(function(res){
					response.resolve(res.data.insertid);
				});
				return response.promise;
			};

			function processBasysPymt(resp){
				try{
					if(resp.status == 'success'){
						let pymtNum = $scope.attendee.first_name.substring(0,1);
						pymtNum += $scope.attendee.last_name.substring(0,1);
						pymtNum += $scope.attendee.id + 'SsnPss';
						let email = resp.user.email || '';
						$scope.card.email = email;
						let submitData = {
							"amount": (Number($scope.paymentAmount) * 100),
							"order_id":pymtNum,
							"email":$scope.attendee.email,
							"token":resp.token,
							"description": "Season Pass Purchase",
							"first_name": resp.user.first_name,
							"last_name": resp.user.last_name,
							"address_line_1": resp.billing.address,
							"city": resp.billing.city,
							"state": resp.billing.state,
							"postal_code": resp.billing.zip,
							"country": resp.billing.country,
							"accountid": $scope.accountid
						}
						$http({
							"url": '/process_basys_pymt.php',
							"method": 'POST',
							"data": $.param(submitData),
							"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
						}).then(function(res){
							res = res.data;
							erSvc.closeLoading();
							if(res.status == 'success' && res.data && res.data.status == 'pending_settlement'){
								$scope.paymentSuccess = true;
								$scope.paymentConfirmation = res.data.id;
								paymentStatus.resolve(true);
							}else{
								$scope.paymentFailure = true;
								$scope.authMessage =
									(res && res.data && res.data.response) ||
									(res && res.response) ||
									(res && res.message) ||
									'Payment could not be processed.';
								paymentStatus.resolve(false);
							}
						}, function(errorResponse){
							erSvc.closeLoading();
							$scope.paymentFailure = true;
							$scope.authMessage =
								errorResponse?.data?.response ||
								errorResponse?.data?.message ||
								'Payment request failed.';
							paymentStatus.resolve(false);
						});
					}else{
						$scope.paymentFailure = true;
						if(resp.invalid) $scope.authMessage = "Invalid: " + resp.invalid;
						paymentStatus.resolve(false);
						erSvc.closeLoading();
					}
				}catch(error){
					logError('processBasysPymt', error);
				}
			} // end processBasysPymt()

			let basysToken;
			function initializeBasysToken(apiKey){
				try{
					basysToken = new Tokenizer({
						apikey: apiKey,
						container: '#tokenizer-container',
						submission: resp => processBasysPymt(resp),
						settings: {
							payment: {
								calculateFees: true,
								showTitle: true,
								placeholderCreditCard: '0000 0000 0000 0000',
								showExpDate: true,
								showCVV: true,
								card: {
									strict_mode: true, // Set to true to allow for 19 digit cards
									requireCVV: true // Default false - true to require cvv
								},
							},
							user: {
								 showInline: true,
								 showName: true,
								 showEmail: true,
								 showPhone: false,
								 showTitle: false
							   },
							   billing: {
								 show: true,
								 showTitle: true
							   },
							styles: {
								"#tokenizer-form": {
									"background-color": "white",
									'padding': "10px",
								},
								'input': { 'margin': '4px' },
								"#card .fieldset": { 'padding': "0px" },
								'svg': {'color': "maroon" },
								'.cvv-input': {	'margin-left': '10px','width':'89%' },
								'.exp-input': {	'margin-left': '10px', 'width': '89%'}
							}
						}
					});
				}catch(error){
					logError('initializeBasysToken', error);
				}
			}

			function logError(func, error){ console.log(error) }

			//Reset forgotten password
			$scope.resetPassword = function(){
				$(".ui-dialog-content").dialog("close");
				let text = `If you choose to continue, an email containing a new password 
					will be sent to ${matchedEmail}`;
				let title = "Confirm Password Reset";
				erSvc.easyRegConfirm({
					"text": text,
					"title": title
				}, "Reset Password", "Cancel").then(function(res){
					if (!res) return;
					$http({
						"url": '/reset_attendee_password.php',
						"method": 'POST',
						"data": $.param({"email": matchedEmail}),
						"headers": {
							"Content-Type": "application/x-www-form-urlencoded"
						}
					}).then(function(response){
						$('#loginDiv').dialog('close');
						let txt = `Your password has been reset. Please check your inbox for your new password.`
						erSvc.easyRegAlert({
							"text": txt,
							"title": "Password Reset Successful"
						});
					});
				});
			};

			$scope.closeRightDialog = () => $('.dialogRight').hide(500);

			$scope.logOut = () => {
				$.post('/forget_confirmation.php', erUtils.withCsrf({}));
				$scope.attendee = null;
				$scope.view = 'passes';
				erSvc.easyRegAlert({"text": "You have been logged out",	"title": "Logged Out"});
			};

			$scope.openProfileMgmt = function(){
				$scope.profile = angular.copy($scope.attendee);
				$('#manageProfileDialog').show(500);
			};

			$scope.updateProfile = function(){
				if (!$scope.profileForm.$valid){
					$('form[name="profileForm"]').addClass('submitted');
					return;
				}
				let rec = {
					"query":"updateAttendee",
					"id":$scope.profile.id,
					"email":$scope.profile.email,
					"first_name":$scope.profile.first_name,
					"last_name":$scope.profile.last_name
				};
				postUserDml(rec).then(function(res){
					if(res.data.status == 'success'){
						$scope.closeRightDialog();
						$scope.attendee = angular.copy($scope.profile);
						erSvc.easyRegAlert({"text":"Your profile has been Updated","title":"Profile Updated"});
					}
				});
			};

			$scope.enablePwChange = () => $scope.currentPw && $scope.newPw && $scope.confirmPw;

			$scope.submitPwChange = function(){
				$scope.passwordMismatch = false;
				$scope.invalidCurrentPw = false;
				if ($scope.newPw != $scope.confirmPw){
					$scope.passwordMismatch = true;
					return;
				}
				erSvc.loadingDialog();
				erSvc.encrypt($scope.currentPw).then(function(encrypted){
					dataSvc.getArray({
						'query': 'checkAttendeeCredentials',
						'email': $scope.attendee.email,
						'password': encrypted
					}).then(function(resp){
						if (resp.length == 0){
							$scope.invalidCurrentPw = true;
							erSvc.closeLoading();
							return;
						}
						erSvc.encrypt($scope.newPw).then(function(newEncrypted){
							var rec = {
								"query": "resetAttendeePw",
								"password": newEncrypted
							};
							postUserDml(rec);
							$scope.showPwInputs = false;
							$scope.newPw = $scope.confirmPw = $scope.currentPw = '';
							setTimeout(function(){
								erSvc.closeLoading();
								$('#changePwDiv').dialog('close');
								let txt = "Your password has been updated."
								erSvc.easyRegAlert({
									"text": txt,
									"title": "Password Updated"
								});
							}, 30);
						});
					});
				});
			}; // End submitPwChange()
		});//End Controller
	</script>
</head>
<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid" ng-controller="regController" ng-cloak ng-show="accountRetrieved">
	<div class="row">

	<div class="col-lg-12">
		<H2 class="page-header">
			{{accountName}} - Season Passes
			<button class="btn btn-success" ng-click="showLogin()" style="float:right;margin-left:1em" ng-hide="attendee">
				Log In
			</button>
			<div class="nav-item dropdown d-inline-block position-relative" 
				style="margin-right: 1em;margin-left:1em;float:right" ng-show="attendee">
				<button class="btn btn-primary dropdown-toggle" style="border-radius: 60px;padding: 6px;font-weight: bold;"
				 	data-bs-toggle="dropdown">
					{{attendeeInitials()}}
				</button>
				<ul class="dropdown-menu" role="menu" style="margin-left:-7em">
					<li role="presentation" class="dropdown-header">
						{{account.first_name}} {{account.last_name}}
					</li>
					<li ng-click="openProfileMgmt()">
						<a href="#" class="dropdown-item">Manage Account</a>
					</li>
					<li ng-click="view = 'passes'">
						<a href="#" class="dropdown-item">Browse Passes</a>
					</li>
					<li ng-click="view = 'purchasedPasses'">
						<a href="#" class="dropdown-item">My Passes</a>
					</li>
					<li ng-click="logOut()">
						<a href="#" class="dropdown-item">Log Out</a>
					</li>
				</ul>
			</div>
			<button class="btn btn-success" ng-click="view='passes'" style="float:right" 
				ng-show="view == 'payment' && !paymentSuccess">
				Back
			</button>
		</H2>
	</div>
	<!-- Pass List -->
	<div class="col-lg-12" ng-show="view == 'passes'">
		<section style="max-height:70vh;overflow-y: auto;">
			<section ng-repeat="pass in seasonPasses" class="pass">
				<H3>
					{{pass.name}}
					<span style="float:right"> {{pass.price | currency}} </span>
				</H3>
				<div class="twoCol">
					<div>
						<p>{{pass.description}}</p>
						<ul>
							<li ng-repeat="evt in pass.details | orderObjectBy:'startdate'" class="eventSection">
								<label>{{evt.eventName}}</label>
								<span ng-repeat="(id, count) in evt.tickets" ng-show="count">{{count}} {{tickets[id].label}}</span>
							</li>
						</ul>
					</div>
					<div class="right">
						<label>
							Qty 
							<input type="number" min="0" ng-model="pass.quantity" class="form-control" 
								ng-change="calculateTotal()" style="display:inline-block;width:6em"/>
						</label>
					</div>
				</div>
			</section>
		</section>
		<div class="button-row" ng-show="purchaseTotal">
			<b>Total {{purchaseTotal | currency}}</b><br/>
			<button class="btn btn-primary" ng-click="view='payment'">Proceed to Checkout</button>
		</div>
	</div><!-- End Column -->

	<!-- Checkout Column -->
	<div class="col-lg-12" ng-show="view == 'payment'">
		<table id="orderSummary" ng-hide="paymentSuccess">
			<caption>Order Summary</caption>
			<tr ng-repeat="pass in seasonPasses" ng-show="pass.quantity">
				<td>{{pass.name}}</td>
				<td>{{pass.quantity}} @ {{pass.price |currency}}</td>
				<td class="right">{{(pass.quantity * pass.price) | currency}}</td>
			</tr>
			<tr>
				<td></td>
				<td class="right">Total</td>
				<td class="right bold">{{purchaseTotal | currency}}</td>
			</tr>
		</table>
		<form name="attendeeForm" ng-hide="paymentSuccess">
			<table id="attendeeTbl">
				<caption>User Information</caption>
				<tr>
					<td class="bold">First Name</td>
					<td><input class="form-control" type="text" ng-model="regAttendee.first_name" required /></td>
				</tr>
				<tr>
					<td class="bold">Last Name</td>
					<td><input class="form-control" type="text" ng-model="regAttendee.last_name" required /></td>
				</tr>
				<tr>
					<td class="bold">Email</td>
					<td><input class="form-control" type="email" ng-model="regAttendee.email" required /></td>
				</tr>
				<tr>
					<td class="bold">Password</td>
					<td><input class="form-control" type="password" ng-model="regAttendee.password" required /></td>
				</tr>
				<tr>
					<td></td>
					<td>
						You will be able to return and use your email and password to retrieve your tickets at any time.
					</td>
				</tr>
			</table>
		</form>

		<!-- CC INFO -->
		<div class="form-group" 
			ng-show="true">
			<!-- ng-show="paying && !paymentSuccess"> -->
			<div ng-if="ccProvider == 'basys'" style="width:70em;margin:auto">
				<div id="tokenizer-container"></div>
				<div style="margin-top:.5em">
					<b>Payment Amount &nbsp;</b>
					<span style="padding-left:2em">
						{{amountDue | currency}}
					</span>
				</div>
				<div class="button-row">
					<button class="btn btn-primary" ng-click="completePurchase()">Complete Purchase</button>
				</div>
			</div>

			<table id="ccTable" ng-if="ccProvider == 'magicWrighter'" ng-hide="paymentSuccess">
				<caption>Payment Information</caption>
				<tr>
					<td class="bold">Name</td>
					<td>
						<input type='text' ng-model="card.name" class='form-control' required />
					</td>
				</tr>
				<tr>
					<td class="bold">Email</td>
					<td>
						<input type='text' ng-model="card.email" class='form-control' size="50"	required />
					</td>
				</tr>
				<tr>
					<td class="bold">Street</td>
					<td>
						<input type='text' ng-model="card.street" class='form-control' required />
					</td>
				</tr>
				<tr>
					<td class="bold">City, State Zip</td>
					<td>
						<input type='text' ng-model="card.city" class='form-control' style="display:inline;max-width:15em;"
							required />
						<select ng-model="card.state" class='form-select' style="display:inline;max-width:5em;margin:0px 5px"
							required />
							<?php state_options($registration['state']); ?>
						<input type='text' ng-model="card.zip" class='form-control'	style="display:inline;max-width:5em;"
							required />
					</td>
				</tr>
				<tr>
					<td class="bold">Card Number</td>
					<td>
						<input type='text' ng-model="card.number" class='form-control' required/>
					</td>
				</tr>
				<tr>
					<td class="bold">Expiration</td>
					<td>
						<select ng-model="card.expMonth" class='form-select' ng-options="month for month in months"
							style="width:20%;display:inline" required>
						</select>
						<span style="margin-right:10px">Month</span>
						<select class='form-select' style="width:20%;display:inline" ng-model="card.expYear" 
							ng-options="year for year in years"	required>
						</select>
						<span style="margin-right:10px">Year</span>
					</td>
				</tr>
				<tr>
					<td class="bold">Cvv</td>
					<td>
						<input type='text' ng-model="card.cvv" class='form-control' style="width:20%" required/>
					</td>
				</tr>
				<tr ng-show="ccFee">
					<td class="bold">Purchase Total</td>
					<td> 
						<span style='display:inline-block;width:7em;text-align:right'>{{purchaseTotal | currency}}</span>
					</td>
				</tr>
				<tr ng-show="ccFee">
					<td class="bold">Processing Fee</td>
					<td> 
						<span style='display:inline-block;width:7em;text-align:right'>{{ccFee | currency}}</span>
					</td>
				</tr>
				<tr>
					<td class="bold">Total Due</td>
					<td> 
						<span style='display:inline-block;width:7em;text-align:right'>{{amountDue | currency}}</span>
					</td>
				</tr>
				<tr>
					<td colspan="2" class="right">
						<button class="btn btn-primary" ng-click="completePurchase()">Complete Purchase</button>
					</td>
				</tr>
			</table>
		</div> <!-- End CC info -->

		<div class='alert alert-success bold'  role='alert' ng-show="paymentSuccess">
			<H3 style="margin-top:0px">Purchase Complete</H3>	
			Your payment of {{paymentAmount | currency}} has been successfully processed.<br/>
			Your payment confirmation is: <b>{{paymentConfirmation}}</b><br/>
			An email with this information has been sent to {{card.email}}
			
			<div style="text-align:right;margin-right:1em" ng-show="ticketsOrdered() > 0">
				<button class="btn btn-primary">
					<a style="color:white" href="order_summary">View Tickets</a>
				</button>
			</div>
		</div>
		<div class="button-row" ng-show="paymentSuccess">
			<button class="btn btn-primary" ng-click="view = 'purchasedPasses'">View Tickets</button>
		</div>

		<div class="alert alert-danger" ng-if="paymentFailure" style="margin-top:10px">
			<H2><u>Error Processing Payment</u></H2>
			Payment has not been successfully completed<br/>
			Your order has not been saved.<br/>
			<div class="bold" ng-show="authMessage">{{authMessage}}</div>
			<div class="bold" ng-show="paymentMessage">{{paymentMessage}}</div>
			Please verify the information you have entered and try again or try another card.<br/>
			<div style="text-align:right;padding-right:30px">
				<button class="btn btn-primary" ng-click="noFailure()">OK</button>
			</div>
		</div>
	</div><!-- End Checkout Column -->

	<!-- Purchased Passes Column -->
	<div class="col-lg-12" ng-show="view == 'purchasedPasses'">
		<div class="button-row" id="ticketViewDiv">
			<label>
				<input type="radio" name="ticketView" ng-model="ticketView" value="condensed" />Condensed
			</label>
			<label>
				<input type="radio" name="ticketView" ng-model="ticketView" value="expanded" />Expanded
			</label>
			<label>
				<input type="radio" name="ticketView" ng-model="ticketView" value="mobile" />Mobile
			</label>
		</div>
		<div class='alert alert-info' role='alert' ng-show="noCurrentTickets">
			There are no current or upcoming event tickets.
		</div>
		<section ng-repeat="event in eventTickets | orderObjectBy:'startdate'" class="eventTicketSection">
			<h3 ng-click="event.collapsed = !event.collapsed" class="pointer">
				<span style="font-size:11pt" ng-hide="event.collapsed">&#9660;</span>
				<span style="font-size:11pt" ng-show="event.collapsed">&#9658;</span>
				{{event.name}} {{event.startdate | mySqlToLocalDate}} {{event.starttime}}
			</h3>
			<section ng-repeat="att in event.attendees" ng-hide="event.collapsed">
				<section ng-show="ticketView == 'condensed'">
					<div class="page center">
						<div style="display:flex">
							<div style="flex:30%">
								<img  style="height: 100px" ng-src="{{event.logo}}" />
							</div>
							<div style="flex: 68%">
								<H2>{{event.name}}</H2>
								{{event.site}} &nbsp;&nbsp;&nbsp; {{event.startdate | mySqlToLocalDate}}
								{{event.starttime}}
							</div>
						</div>
						<H3>{{attendee.first_name}} {{attendee.last_name}} - {{att.regType}}</H3>
						<div style="margin:auto;display:inline-block;">	
							{{att.confirmation}}
						</div>
						<section class="xtraContainer" ng-repeat="ticket in att.tickets">
							<H4 style="margin-bottom:0px">{{ticket.label}}</H4>
							<div style="margin:auto;display:inline-block;">	
								<span class="barcode" data-data="{{ticket.code}}"></span>
							</div>
						</section>
					</div>	
				</section><!-- End condensed view -->

				<section ng-show="ticketView == 'expanded'">
					<div class="page center" ng-repeat="ticket in att.tickets">
						<div style="display:flex">
							<div style="flex:30%">
								<img  style="height: 100px" ng-src="{{event.logo}}" />
							</div>
							<div style="flex: 68%">
								<H2>{{event.name}}</H2>
								{{event.site}} &nbsp;&nbsp;&nbsp; {{event.startdate | mySqlToLocalDate}}
								{{event.starttime}}
							</div>
						</div>
						<H3>{{attendee.first_name}} {{attendee.last_name}} - {{att.regType}}</H3>
						<div style="margin:auto;display:inline-block;">	
							{{att.confirmation}}
						</div>
						<section class="xtraContainer" >
							<H4 style="margin-bottom:0px">{{ticket.label}}</H4>
							<div style="margin:auto;display:inline-block;">	
								<span class="barcode" data-data="{{ticket.code}}"></span>
							</div>
						</section>
					</div>	
				</section><!-- End expanded view -->

				<section ng-show="ticketView == 'mobile'" class='center'>
					<div style="display:flex">
						<div style="flex:30%">
							<img  style="height: 100px" ng-src="{{event.logo}}" />
						</div>
						<div style="flex: 68%">
							<H2>{{event.name}}</H2>
							{{event.site}} &nbsp;&nbsp;&nbsp; {{event.startdate | mySqlToLocalDate}}
							{{event.starttime}}
						</div>
					</div>
					<H3>{{attendee.first_name}} {{attendee.last_name}} - {{att.regType}}</H3>
					<div style="margin:auto;display:inline-block;">	
						{{att.confirmation}}
					</div>
					<section class="xtraContainer">
						<H4 style="margin-bottom:0px">{{att.currentTicket.label}}</H4>
						<div style="margin:auto;display:inline-block;">	
							<span class="barcode" data-data="{{att.currentTicket.code}}"></span>
						</div>
					</section>
					<section class="noPrint">
						<button class="btn btn-primary" ng-click="prevTicket(att)" 
							ng-disabled="disableTicketChg('previous', att)">
							<
						</button>
						<button class="btn btn-primary" ng-click="nextTicket(att)" 
							ng-disabled="disableTicketChg('next', att)"	style="margin-left:1em">
							>
						</button>
					</section>
				</section><!-- End mobile view -->
			</section> <!-- End ng-repeat="att in event.attendees" -->
		</section> <!-- End ng-repeat="event in eventTickets" -->
	</div> <!-- End Purchased Passes Column -->

	</div><!-- End Row -->

	<div id="manageProfileDialog" class="dialogRight" style="min-width:50em">
		<div class="dialogTitle">Profile Details</div>
		<div class="dialogContents">
			<form name="profileForm">
			<table>
				<tr>
					<td>Email*</td>
					<td>
						<input type="email" ng-model="profile.email" placeholder="Email Address" 
							class="form-control input-field" required />
					</td>
				</tr>
				<tr>
					<td>Last Name*</td>
					<td>
						<input type="text" ng-model="profile.last_name" placeholder="Last Name" 
							class="form-control input-field" required />
					</td>
				</tr>
				<tr>
					<td>First Name*</td>
					<td>
						<input type="text" ng-model="profile.first_name" placeholder="First Name" 
							class="form-control input-field" required />
					</td>
				</tr>
				<tr>
					<td>Password*</td>
					<td>
						<section  ng-hide="showPwInputs">
						<input type="password" value="password" disabled />
							<button class="btn btn-primary btn-xs" ng-click="showPwInputs = true" ng-hide="showPwInputs">
								Reset Password
							</button>
						</section>
						<section ng-show="showPwInputs">
							<span style="display:inline-block;width:10em">Current Password </span>
							<input type="password" style="margin-bottom:5px" ng-model="currentPw"/><br/>
							<span style="display:inline-block;width:10em">New Password </span>
							<input type="password" style="margin-bottom:5px" ng-model="newPw"/><br/>
							<span style="display:inline-block;width:10em">Confirm </span>
							<input type="password" style="margin-bottom:5px" ng-model="confirmPw"/><br/>
							<button class="btn btn-primary btn-xs" ng-click="showPwInputs = false">Cancel</button>
							<button class="btn btn-primary btn-xs" ng-click="submitPwChange()" ng-disabled="!enablePwChange()">
								Reset Password
							</button>
							<div class='alert alert-danger' role='alert' ng-show="invalidCurrentPw">
								Current password is not correct
							</div>
							<div class='alert alert-danger' role='alert' ng-show="passwordMismatch">
								Passwords do not match
							</div>
						</section>
						
					</td>
				</tr>
			</table>
			<div class="button-row" style="margin-top:1em">
				<button class="btn btn-primary" ng-click="closeRightDialog()">Cancel</button>
				<button class="btn btn-primary" ng-click="updateProfile()">Update Profile</button>
			</div>
			</form>
		</div>
	</div>
	<!-- End Manage Profile Dialog -->

	<section style="display:none">
		<form name="loginForm" id="loginForm">
			<b>Email</b> <input class="form-control" ng-model="login.email" type="email" style="margin-bottom:.5em" required/>
			<b>Password</b> <input class="form-control" type="password" ng-model="login.pass" required/>
			<div class="button-row" style="margin-top:.5em;margin-right:0px">
				<button  class="btn btn-primary" type="button" ng-click="closeLogin()">Cancel</button>
				<button  class="btn btn-success" type="button" ng-click="submitLogin()">Log In</button>
			</div>
		</form>

		<div id="foundLoginWrongPass">
			An account with this email was found, but the provided password is incorrect.<br/><br/>
			If you have forgotten your password, you may click <a ng-click="resetPassword()">here</a> reset it.
		</div>
	</section>
	</div> <!-- End Controller -->
	<style>
		.navbar{display:none;}
		.eventSection span{ margin-left:2em; }
		.eventSection label{
			display:inline-block;
			width:25em;
		}
		.pass H3{
			background:silver;
			border-radius:5px;
			padding:5px 20px 5px 10px ;
		}
		.pass ul{
			list-style-type: none;
			margin-block-start:.5em;
		}
		.pass ul, .pass p{
			padding-left:1em;
		}
		#attendeeTbl, #ccTable, #orderSummary{
			width:50em;
			margin:auto;
		}
		#attendeeTbl td, #ccTable td{ padding:5px; }
		#foundLoginWrongPass a{
			color: var(--header);
			cursor:pointer;
		}
		.button-row input{
			display:inline-block;
			width:5em;
		}
		.xtraContainer{
			margin: 1em;
			page-break-inside: avoid;
			background: #f6f6f64a;
			-webkit-border-radius: 6px;
			-moz-border-radius: 6px;
			border-radius: 6px;
			box-shadow: 2px 2px 6px 2px rgba(192,192,192,0.75);
			-webkit-box-shadow: 2px 2px 6px 2px rgba(192,192,192,0.75);
			-moz-box-shadow: 2px 2px 6px 2px rgba(192,192,192,0.75);
			padding: 2rem;
		}
		.page{ 
			margin:auto;	
			margin-bottom:5px;
		}
		.eventTicketSection h3{
			border-bottom:1px solid silver;
		}
		#ticketViewDiv input{ width:2em; }
		#ticketViewDiv label{ margin-right:2em; }
		@media (max-width: 800px) {
		 	.xtraContainer{
		 		margin: 1em;
		 		border: 1px solid silver;
		 		page-break-inside: avoid;
		 	}
		 	#condensedView, #expandedView, .noMobile{ display:none }
		 	#mobileView {display: block; }
		}
		@media (min-width: 801px) {
			#condensedView, #expandedView{ display:block }
			#mobileView {display: none; }
		}
		@media (max-width: 400px) {
			.barcode{ font-size: 20pt; }
		}
		@media (max-width: 300px) {
			.barcode{ font-size: 15pt; }
		}
	</style>
	<er-Footer/>
</body>
</html>
