<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PSUGevents.com</title>
<?php 
	include("commonStyles.php"); 
	include("common_functions.php");
	include("commonJs.php");
	$inputs = sanitize_inputs($_REQUEST);
	if(substr(str_replace('www.','',$_SERVER['HTTP_HOST']), 0, 4) == "easy"){
		print("<script language=\"javascript\" src=\"https://app.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
	}
	else{
		print("<script language=\"javascript\" src=\"https://sandbox.basysiqpro.com/tokenizer/tokenizer.js\"></script>");
	} 
?>
<style>
	#ccTable input:first-child, #ccTable select:first-child{
		margin-left:2em;
		margin-bottom:5px;
	}
	.attendeeDiv{
		border: 1px solid silver;
		border-radius: 5px;
		padding: 0px 0px 5px 5px;
		margin-bottom: 1em;
	}
	form div label{
		padding-top: 0px !important;
	}
	.form-horizontal .col-form-label{
		text-align: left;
	}
	div .header {
		background: #fff3cd;
		color: #664d03;
		font-weight: bold;
	}
</style>
<script type="text/javascript">
	var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
	app.controller('regController', function($scope, $rootScope, $http, $q, dataSvc, erSvc) {
		if('<?= $inputs['confirmation'] ?>'){
			erSvc.attendeeLogin('<?= $inputs['confirmation'] ?>').then(function(r){
				if(!'<?= $_SESSION['confirmation'] ?>') window.location.reload();
			});
		}
		$scope.confirmation_number = `<?= $inputs['confirmation'] ?>` || `<?= $_SESSION['confirmation'] ?>`;
		$scope.slug = `<?= $inputs['slug'] ?>`;
		$scope.dietary_restrictions = erSvc.getDietaryRestrictions();
		$scope.eventData;
		$scope.coreFieldExclusions = [];
		$scope.registrations = [];
		$scope.selectedReg = {};
		$scope.registrationTypes = {};
		$scope.payment_method = 'cc';
		$scope.updating = false;
		$scope.ccEnabled = false;
		$scope.ccRequired = true;
		$scope.regSettings = {};
		$scope.ccFeeRate = 0;
		//list of ids of registration extras that are initially selected
		let initialExtras = {};
		let eventExtras = {};
		let initialServiceFee = 0;
		//total of all extras that may be ordered without exceeding event max
		let maxCombinedExtras = 999999999;
		let includeOrderSummary = false;
		
		if(!$scope.slug){
			erSvc.sendEmail('emschaitel@gmail.com', 'er slug error', $scope.slug || 'NONE');
		}

		let emailSent = false;
		function logError(fnctn, error, subject, additionalData){
			$scope.errorThrown = true;
			subject = subject || "ER Error";
			subject += ' - ' + fnctn;
			let body = "Slug - " + $scope.slug;
			if(error) body += `<br/><br/> Err: ${error.stack || error}`;
			body += `<br/><br/> Registrations: ${JSON.stringify($scope.registrations)}`;
			body += `<br/><br/> userAgent: ${navigator.userAgent}`;
			body += `<br/><br/> eventData: ${JSON.stringify($scope.eventData)}`;
			if(additionalData) body += `<br/><br/> additionalData: ${additionalData}`;
			if(!emailSent) erSvc.sendEmail('emschaitel@gmail.com', subject, body);
			emailSent  = true;
			$scope.errorDescription = 'An error occurred at function ' + fnctn;
			console.error(error)
		}

		dataSvc.getEventData($scope.slug).then(function(evt){
			if(!evt) return;
			try{
				$scope.ccRequired = evt.req_cc_pymt == '1';
				$scope.hasEvtPage = evt.pages.length > 0;
				eventExtras = angular.copy(evt.extraOptions);
				angular.forEach(evt.extraOptions,o => o.initialOrders = 0 );
				maxCombinedExtras = evt.extras_cap - evt.extras_sold;
				$scope.eventData = evt;
				$scope.extraFields = $scope.eventData.extraFields;
				$scope.agreeTerms = [];
				$scope.allowPartialPymt = evt.allow_partial_pymt == '1';
				//select first option for required radio inputs
				angular.forEach($scope.extraFields,function(field){
					if(field.type == 'a'){
						field.options = field.options.join(' ');
						$scope.agreeTerms.push(field.options);
					} 
				});
				if(evt.soldOut == 'true' && !$scope.confirmation_number){
					$('#registration').remove();
					$scope.registrationAvailable = false;
					erSvc.easyRegAlert({"text":"This event has sold out.","title":"Sold Out"});
					return;
				}
				if(evt.registrationavailable == '0' && !$scope.confirmation_number){
					$('#registration').remove();
					$scope.registrationAvailable = false;
					erSvc.easyRegAlert({"text":"Registration is not currently available for this event","title":"Registration Unavailable"});
					return;
				}
				$scope.registrationAvailable = false;
				angular.forEach($scope.eventData.registrationTypes,function(type){
					$scope.registrationTypes[type.id] = type;
					if(type.current == '1') $scope.registrationAvailable = true;
				});
				$scope.regReady = true;
				if($scope.confirmation_number) $scope.lookupRegistration();
				else $scope.addAttendee();
				let filter = `eventid = ${$scope.eventData.eventid}`;
				dataSvc.getTableRecords('registration_messages', filter).then(function(res){
					$scope.extraMessges = res;
				});
				dataSvc.getTableRecords('registration_field_exclusions', filter).then(function(res){
					res.forEach(fld => $scope.coreFieldExclusions.push(fld.default_field));
				});
				dataSvc.getPreferenceByName('regScreenConfig', evt.accountid).then((res) => {
					if(res[0]){
						try{ $scope.regSettings = angular.fromJson(res[0].value); }
						catch(e){
							let errorData = "Call to getTableRecords ('preferences'..." + JSON.stringify(res);
							logError('Get Preferences', e, '', errorData);
						}
					}else{
						$scope.regSettings = {
							"email":{"name":"Email","hide":false,"label":"Email"},
							"first_name":{"name":"First Name","hide":false,"label":"First Name"},
							"last_name":{"name":"Last Name","hide":false,"label":"Last Name"},
							"title":{"name":"Position/Title","hide":false,"label":"Position/Title"},
							"business":{"name":"Business","hide":false,"label":"Business"},
							"address":{"name":"Business Address","hide":false,"label":"Business Address"},
							"phone":{"name":"Business Phone","hide":false,"label":"Business Phone"},
							"vendor_access":{
								"name":"Share Info With Vendors","hide":false,"label":"Share my information with vendors"
							},
							"web_address":{"name":"Web Address","hide":false,"label":"Web Address (Optional)"},
							"dietary_restrictions":{"name":"Dietary Restrictions","hide":false,"label":"Dietary Restrictions"},
							"ec1":{"name":"Emergency Contact 1","hide":false,"label":"Emergency Contact 1"},
							"ec2":{"name":"Emergency Contact 2","hide":false,"label":"Emergency Contact 2"}
						}
					}						
				});

				dataSvc.getArray({'query':'eventDiscountsAvailable', eventid: $scope.eventData.eventid}).then(function(r){
					$scope.discountAvailable = r[0] && r[0].activeCodes != 0;
				});

				$scope.ccProvider = 'none';
				$.post('/set_session_account.php',{"accountid":evt.accountid}).then(function(){
					dataSvc.getArray({'query':'ccProvider'}).then(function(cc){
						try{
							if(cc[0] && cc[0].ccProvider) $scope.ccProvider = cc[0].ccProvider;
							$scope.ccEnabled = $scope.ccProvider != 'none';
							if($scope.ccRequired && !$scope.ccEnabled){
								throw "cc required but not enabled " + $scope.eventData.eventName;
							}
							if(!$scope.ccEnabled) $scope.payment_method = 'po';
							if($scope.ccProvider == 'basys') initializeBasysToken(cc[0].apiKey);
						}catch(error){
							logError('ccProvider', error);
						}
					});
					dataSvc.getArray({'query':'ccChargeRt'}).then(resp => { 
						if(resp[0] && evt.apply_cc_fee == '1') $scope.ccFeeRate = Number(resp[0].cc_charge_rt || '0') / 100;
					});
				});	
			}catch(error){
				logError('getEventData', error);
			}
		});  // End getEventData()

		$scope.availableRegTypes = function(){
			try{
				let types = [];
				angular.forEach($scope.eventData.registrationTypes, function (t){
					if((t.current == '1' && t.vendor_reg  != '1') || $scope.selectedReg.registration_typeid == t.id) 
						types.push(t);
				});
				return types;
			}catch(error){
				logError('availableRegTypes', error);
			}
		};

		$scope.showField = function(fld){
			if(!$scope.regSettings) return false;
			try{
				if(!$scope.regSettings[fld]) return true;
				return !$scope.regSettings[fld].hide;
			}catch(error){
				logError('showField', error);
			}
		};

		$scope.addAttendee = function(){
			try{
				if(!$scope.regForm.$valid && $scope.registrations.length){
					let msg = `Please ensure current attendee info is valid and complete before adding more attendees.`;
					erSvc.easyRegAlert({"text":msg,"title":"Unable to Add Attendee"});
					$('form').addClass('submitted');
					return;
				}
				$('form').removeClass('submitted');
				let newReg = {"vendor_access":"1","extraOrders":{}};
				angular.forEach($scope.eventData.extraOptions,xtra => {
					if(xtra.current) newReg.extraOrders[xtra.id] = Object.assign({}, xtra, {'quantity':0});
				});
				if($scope.availableRegTypes().length == 1) newReg.registration_typeid = $scope.availableRegTypes()[0].id;
				angular.forEach($scope.extraFields,function(field){
					if(field.type == 'r' && field.required == '1') newReg[field.field] = "0";
				});
				if($scope.registrations.length){
					let lastReg = $scope.registrations[$scope.registrations.length-1];
					newReg.address1 = lastReg.address1;
					newReg.address2 = lastReg.address2;
					newReg.business = lastReg.business;
					newReg.city = lastReg.city;
					newReg.phone = lastReg.phone;
					newReg.registration_typeid = lastReg.registration_typeid;
					newReg.state = lastReg.state;
					newReg.vendor_access = lastReg.vendor_access;
					newReg.zip = lastReg.zip;
				}
				$scope.registrations.push(newReg);
				$scope.updateTotals();
			}catch(error){
				logError('addAttendee', error);
			}
		};

		$scope.showExtraForReg = function(reg, xtra){
			return xtra.registration_types.includes(reg.registration_typeid)
		};

		$scope.goToPayment = function(){
			try{
				if(!$scope.regForm.$valid && $scope.registrations.length){
					let msg = `Please ensure current attendee info is valid and complete before continuing.`;
					erSvc.easyRegAlert({"text":msg,"title":"Attendee Info Incomplete"});
					$('form').addClass('submitted');
					return;
				}
				$('form').removeClass('submitted');
				$scope.card.amt = $scope.amountDue;
				if($scope.amountDue > 0) $scope.paying = true;
				else $scope.submitRegistration();
			}catch(error){
				logError('goToPayment', error);
			}	
		};

		$scope.toggleCheckbox = function(field, val, reg){
			reg[field] = reg[field] || [];
			if(typeof reg[field] == 'string') reg[field] = JSON.parse(reg[field]);
			let idx = reg[field].indexOf(val);
			if(idx < 0) reg[field].push(val);
			else reg[field].splice(idx, 1);
		};

		$scope.submitRegistration = function(){
			try{
				$scope.authMessage = '';
				$scope.showRegMessage = false;
				$('form').addClass('submitted');
				if(!$scope.paymentForm.$valid && $scope.orderTotal > 0 && $scope.amountDue > 0) return;
				erSvc.loadingDialog();
				let offendingEmails = [];
				let registrationsRetrieved = 0;
				if(!$scope.updating){
					angular.forEach($scope.registrations,function(reg){
						let queryParams = {
							"query":"getRegByEmail",
							"email": reg.email,
							"eventid":$scope.eventData.eventid
						};
						dataSvc.getArray(queryParams).then(function(resp){
							if(resp.length) offendingEmails.push(reg.email);
							if(++registrationsRetrieved == $scope.registrations.length) checkStatus();
						});
					});
				}else{
					validateRegistration();
				}
			
				function checkStatus(){
					if(offendingEmails.length && $scope.eventData.allow_dup_attendee != '1'){
						erSvc.closeLoading();
						erSvc.easyRegAlert({
							"text":"A registration with this email already exists - " + offendingEmails.toString(),
							"title":"Attendee Already Registered"
						});
					}else{
						validateRegistration();
					}
				}
			}catch(error){
				logError('submitRegistration', error);
			}	
		};

		let currentReg;
		let rejectedRegs = [];
		$scope.checkAttendeeRecords = function(reg){
			return;
			try{
				currentReg = reg;
				if((reg.email || (reg.last_name && reg.first_name)) && (!reg.attendeeid || reg.attendeeid == -1)){
					dataSvc.getAttendeeMatch(reg.email, reg.last_name, reg.first_name).then(function(res){
						$scope.attendeeMatches = res.filter(r => !rejectedRegs.includes(r.id));
						if($scope.attendeeMatches.length && !dupFound){
							$('#attendeeSelector').dialog({
								modal:true,
								title:"Have You Attended Before?",
								width:"600"
							});
							$('#attendeeSelector').on('dialogclose', function() {
								rejectedRegs = [...rejectedRegs, ...$scope.attendeeMatches.map(r => r.id)];
							});
						}else{
							reg.attendeeid = -1;
						}
					});
				}
				else{
					dupFound = false;
				}
			}catch(error){
				logError('checkAttendeeRecords', error);
			}	
		};

		let dupFound = false;
		$scope.selectAttendee = function(attendee){
			try{
				dupFound = false;
				let queryParams = {
					"query":"getRegByEmail",
					"email": attendee.email,
					"eventid":$scope.eventData.eventid
				};
				dataSvc.getArray(queryParams).then(function(resp){
					if(resp.length){
						dupFound = true;
						let txt = `A registration with this email address already exists for the event. <br/><br/>
							If you have already registered, please enter your confirmation number at the left of the screen.<br/><br/>
							Otherwise, please enter a new email address.`;
						if($scope.eventData.allow_dup_attendee == '1'){
							txt = `A registration with this email address already exists for the event. <br/><br/>
								If you would like to manage that registration, please enter your confirmation number at the left of the screen.<br/><br/>
								If you would like to create a second registration under the same email, you may continue.`;
						}
						erSvc.easyRegAlert({"text":txt,"title":"Registration Already Exists"});
						$('#attendeeSelector').dialog('close');
						if($scope.eventData.allow_dup_attendee != '1'){
							currentReg.email = '';
						}else{
							currentReg.attendeeid = attendee.id;
							for(prop in attendee){
								if(prop != 'id') currentReg[prop] = attendee[prop];
							}	
						}	
					}else{
						currentReg.attendeeid = attendee.id;
						for(prop in attendee){
							if(prop != 'id') currentReg[prop] = attendee[prop];
						}
						$('#attendeeSelector').dialog('close');
					}
				});	
			}catch(error){
				logError('selectAttendee', error);
			}	
		};

		$scope.attendeeNotFound = function(){
			currentReg.attendeeid = -1;
			$('#attendeeSelector').dialog('close');
		};

		$scope.updateRegistration = function(){
			$scope.authMessage = '';
			$scope.showRegMessage = false;
			erSvc.loadingDialog();
			validateRegistration();
		};

		function validateRegistration(){
			try{
				$scope.submitClickced = true;
				$scope.paymentAmount = 0;
				$('form').addClass('submitted');
				if(!$scope.regForm.$valid){
					erSvc.closeLoading();
					return;
				}
				if($scope.agreeTerms.length){
					let txt = "<b>You have agreed to the following terms.</b><br/> <br/>" + $scope.agreeTerms.join('<br/>') + '<br/>';
					erSvc.easyRegConfirm({"text":txt,"title":"Terms"},"Continue","Cancel")
					.then(function(res){
						if(res) reCheckCapacity();
						else erSvc.closeLoading();
					});
				}else{
					reCheckCapacity();
				}
			}catch(error){
				logError('validateRegistration', error);
			}	
		}

		function reCheckCapacity(){
			capacityErrors = [];
			dataSvc.getEventData($scope.slug).then(function(evt){
				eventExtras = angular.copy(evt.extraOptions);
				angular.forEach($scope.registrations,function(reg){
					angular.forEach(reg.extraOrders,function(xtraOrder){
						if(eventExtras[xtraOrder.id]) xtraOrder.soldOut = eventExtras[xtraOrder.id].soldOut;
						$scope.checkXtraChg(reg, xtraOrder, true)
					});
				});
				if(!capacityErrors.length) completeRegistration();
				else{
					let msg = 'Some ticket availabilities have changed.';
					capacityErrors.forEach(e => msg += '<br/><br/>' + e);
					erSvc.easyRegAlert({"text":msg,"title":"Some Tickets No Longer Available"});
					$scope.paying = false;
					erSvc.closeLoading();
				}
			});
		}

		function completeRegistration(){
			try{
				erSvc.loadingDialog();
				let confirmations = [];
				let registrationsCompleted = 0;
				$scope.registrations.forEach(function(reg){
					if(!$scope.updating){
						reg.payment_method = $scope.payment_method;
						reg.payment_number = $scope.payment_number || $scope.paymentConfirmation;
					}
					dataSvc.createOrUpdateRegistration(reg, $scope.eventData.eventid, $scope.eventData).then(function(res){
						if(res.error){
							erSvc.closeLoading();
							erSvc.easyRegAlert({"text":res.error,"title":"Error"});
							return;
						}
						if(!res.confirmation){
							erSvc.closeLoading();
							erSvc.easyRegAlert({"text":"Error saving registration.  Please contact site administrator","title":"Error"});
						}else{
							reg.confirmation = res.confirmation;
							reg.id = res.id;
						}
						$scope.savedRegistrations = angular.copy($scope.registrations);
						if(++registrationsCompleted == $scope.registrations.length){
							if($scope.orderTotal == 0) $scope.payment_method = '';
							if($scope.payment_method == 'cc' && $scope.paying && $scope.ccEnabled){
								$scope.card.confirmation = res.confirmation;
								$scope.card.regId = res.id;
								submitPayment().then(function(status){
									if(status){
										processRegistrationExtras();
										$scope.hideConfirmation = false;
										if(!$scope.updating) sendExtraRegMessages();
									} 
									else if(!$scope.updating){
										$scope.hideConfirmation = true;
										deleteRegistrations();
									} 
									showRegStatus(res);
								});
							}else{
								showRegStatus(res);
								erSvc.closeLoading();
								processRegistrationExtras();
							}
						}
					});
				});
			}catch(error){
				logError('completeRegistration', error);
			}	
		} // end completeRegistration()

		function deleteRegistrations(){
			try{
				$scope.registrations.forEach(function(reg){
					dataSvc.deleteRecord({"table":"registrations","id":reg.id},$scope.eventData.id);
					delete reg.confirmation;
					delete reg.id;
				});
			}catch(error){
				logError('deleteRegistrations', error);
			}	
		}

		function showRegStatus(res){
			try{
				if(!$scope.updating && !$scope.paymentFailure){
					//send email confirmation if new registration
					$scope.registrations.forEach(function(reg){
						if(!reg.confirmation) return;
						var data = 	{
							"confirmation":reg.confirmation,
							"first_name":reg.first_name,
							"last_name":reg.last_name,
							"email":reg.email,
							"eventid":$scope.eventData.eventid,
							"includeOrderSummary":includeOrderSummary,
							"sender":$scope.eventData.eventName,
							"schedAvailable":$scope.registrationTypes[reg.registration_typeid].schedAvailable == '1'
						};
						if($scope.eventData.acctType == 'ticketing'){
							 $.post('/save_registration_ticketing.php', data, function(){
							 	if($scope.eventData.sched_after_reg == '1') goToSchedule();
							 });
						}else{
						 	$.post('/save_registration.php', data, function(){
								if($scope.eventData.sched_after_reg == '1') goToSchedule();
						 	});
						}						
					});
				}
				if(!$scope.updating && $scope.eventData.sched_after_reg != '1'){
					$([document.documentElement, document.body]).animate({
						scrollTop: $($('#resultMessage')).offset().top + 200
					}, 600);
					$scope.showRegMessage = true;
				}else if($scope.eventData.sched_after_reg != '1'){
					if(!$scope.paymentFailure) erSvc.easyRegAlert({"text":"Your registration has been updated","title":"Success"});
				}
			}catch(error){
				logError('showRegStatus', error);
			}
		};

		function goToSchedule(){
			sessionStorage.setItem("showConfirmation", "true");
			let postData = {
				'confirmation':$scope.registrations[0].confirmation,
				'slug':$scope.slug
			}
			$.post('/lookup_confirmation.php', postData, function(){
				window.location.href = 'attendee_schedule';
			});
		}

		$scope.regTotal = function(reg){
			reg.discount = 0;
			reg.discount_code = '';
			try{
				if(!reg.registration_typeid) return;
				let rt = $scope.registrationTypes[reg.registration_typeid];
				if(!rt) return;
				let price = parseFloat(rt.price);
				angular.forEach($scope.eventData.extraOptions,function(option){
					if(!reg.extraOrders[option.id]) return;
					price += (reg.extraOrders[option.id].quantity || 0) * option.price;
				});
				if(selectedDiscount){
					//if this is a one-time code, apply to first registration only 
					if(selectedDiscount.frequency == 'oneTime' && $scope.registrations.indexOf(reg) > 0) return price;

					if(selectedDiscount.method == 'percent'){
						reg.discount = price * selectedDiscount.discount / 100;
						reg.discount_code = selectedDiscount.code;
					}else{
						reg.discount = selectedDiscount.discount;
						reg.discount_code = selectedDiscount.code;
					}
				}
				return price;
			}catch(error){
				logError('regTotal', error);
			}
		};

		$scope.ticketsOrdered = function(){
			try{
				let count = 0;
				$scope.registrations.forEach(function(reg){
					for(const id in reg.extraOrders){
						if(reg.extraOrders[id].quantity) count += reg.extraOrders[id].quantity;
					}
				});
				return count;
			}catch(error){
				logError('ticketsOrdered', error);
			}
		};

		function getServiceFee(ignoreInitial){
			try{
				if(!$scope.eventData) return 0;
				let ttl = 0;
				$scope.registrations.forEach(function(reg){
					ttl += getRegServiceFee(reg);
				});
				if(!ignoreInitial) ttl -= initialServiceFee;
				return Math.round(ttl * 100) / 100;
			}catch(error){
				logError('getServiceFee', error);
			}
		};

		function getRegServiceFee(reg){
			try{
				let regFee = parseFloat($scope.eventData.reg_fee);
				if($scope.eventData.reg_charge_to != 'attendee') regFee = 0;
				let regMeth = $scope.eventData.reg_fee_method;
				let tixFee = parseFloat($scope.eventData.ticket_fee);
				if($scope.eventData.ticket_charge_to != 'attendee') tixFee = 0;
				let tixMeth = $scope.eventData.ticket_fee_method;
				let ttl = 0;
				let rt = $scope.registrationTypes[reg.registration_typeid];
				if(!rt) return 0;
				let price = parseFloat(rt.price) || 0;
				if(regFee && (price || $scope.eventData.reg_charge_zero_items == '1')){
					ttl += regMeth == 'flat' ? regFee : (price * (regFee/100));
				}
				if(tixFee){
					angular.forEach($scope.eventData.extraOptions,function(opt){
						if(!reg.extraOrders[opt.id]) return;
						let qty = Number(reg.extraOrders[opt.id].quantity || 0);
						if(!qty || (!opt.price && $scope.eventData.ticket_charge_zero_items != '1')) return;
						if (tixMeth == 'flat') ttl +=  tixFee * qty;
						else ttl += (opt.price * qty * (tixFee/100));
					});
				}
				return Math.round(ttl * 100) / 100;
			}catch(error){
				logError('getRegServiceFee', error);
			}
		}

		function processRegistrationExtras(){
			try{
				$scope.registrations.forEach(function(reg){
					for(const id in reg.extraOrders){
						if(!reg.extraOrders[id].quantity) continue;
						let newExtra = {
							"registrationid": reg.id,
							"registration_extras_id": id,
							"quantity":reg.extraOrders[id].quantity
						};
						if(reg.extraOrders[id].orderId) newExtra.id = reg.extraOrders[id].orderId;
						dataSvc.createOrUpdateRecord({"table":"registration_extra_orders","record":newExtra}).then(function(res){
							reg.extraOrders[id].orderId = reg.extraOrders[id].orderId || res;
							newExtra.orderId = reg.extraOrders[id].orderId;
							initialExtras[newExtra.registration_extras_id] = newExtra;
						});
					}
					//Remove necessary extras
					for(const id in initialExtras){
						if(!reg.extraOrders[id].quantity && initialExtras[id].orderId){
							dataSvc.deleteRecord({"table":"registration_extra_orders","id":initialExtras[id].orderId});
							delete initialExtras[id];
						}
					}
				});
				
				if(!$scope.updating) $rootScope.loginUser($scope.registrations[0].confirmation); 
			}catch(error){
				logError('processRegistrationExtras', error);
			}
		}

		function sendExtraRegMessages(){
			try{
				angular.forEach($scope.extraMessges,function(msg){
					angular.forEach($scope.registrations,function(reg){
						erSvc.sendEmail(reg.email, msg.subject, msg.message, msg.from_email);
					});	
				});
			}catch(error){
				logError('sendExtraRegMessages', error);
			}
		}

		$scope.typeDisplay = (type) => {
			try{
				return type.name + (type.price == '0.00' ? '' : ' ($' + type.price + ')' );
			}catch(error){
				logError('typeDisplay', error);
			}
		};

		$scope.lookupRegistration = function(){
			dataSvc.getRegistrationData($scope.confirmation_number, $scope.eventData.eventid).then(function(resp){
				try{
					if(!resp) return;
					if(resp.discount_code) $scope.discountCode = resp.discount_code;
					resp.ccFeesPaid = Number(resp.ccFeesPaid || '0');
					$scope.selectedReg = resp;
					$scope.updating = true;
					let regExtras = {};
					resp.extraOrders.forEach(function(xtra){
						xtra.quantity = Number(xtra.quantity);
						regExtras[xtra.id] = xtra;
						if(xtra.factor_evt_cap == '1') maxCombinedExtras += xtra.quantity;
					});
					angular.forEach($scope.eventData.extraOptions,xtra => {
						if(xtra.current && !regExtras[xtra.id]) regExtras[xtra.id] = Object.assign({}, xtra, {"quantity":0});
						if(regExtras[xtra.id]) regExtras[xtra.id].soldOut = xtra.soldOut;
					});
					resp.extraOrders = regExtras;
					$scope.registrations[0] = resp;
					initialExtras = angular.copy(resp.extraOrders);
					initialServiceFee = getServiceFee(true);
					$scope.setSelectedRegType();
					if($scope.discountCode) $scope.checkDiscountCode();
					if('<?= $inputs['confirmation'] ?>' && $scope.orderTotal > 0) $scope.goToPayment();
				}catch(error){
					logError('lookupRegistration', error);
				}
			});
		};

		$scope.setSelectedRegType = function(){
			try{
				//ERIC what happens to extra orders when reg type changes?
				angular.forEach($scope.eventData.registrationTypes,function(type){
					if(type.id == $scope.selectedReg.registration_typeid){
						$scope.selectedRegType = type;
						angular.forEach(type.extraOptions,function(option){
							angular.forEach(initialExtras,function(initial){
								if(initial.extraId == option.id) option.selected = true;
							});
						});
					}
				});
				$scope.updateTotals();
			}catch(error){
				logError('setSelectedRegType', error);
			}
		};

		$scope.getExtraOptions = function(reg){
			try{
				if(!reg.registration_typeid) return [];
				let selectedType = $scope.eventData.registrationTypes.filter(t => t.id == reg.registration_typeid)[0];
				if(!selectedType) return [];
				let options = [];
				angular.forEach($scope.eventData.extraOptions ,function(xtra){
					if(xtra.current && xtra.registration_types.includes(selectedType.id)) options.push(xtra);
				});
				return options;
			}catch(error){
				logError('getExtraOptions', error);
			}
		};

		$scope.orderTotal = 0;
		$scope.updateTotals = function(){
			if(!$scope.registrations.length) return 0;
			try{
				$scope.ccServiceFee = 0;
				let total = 0;
				$scope.totalDiscount = 0;
				$scope.registrations.forEach(function(reg){
					let regAmt = $scope.regTotal(reg) + (reg.ccFeesPaid || 0) - (reg.payments || 0);
					total += regAmt;
					reg.ccServiceFee = 0;
					if($scope.payment_method == 'cc' && $scope.ccFeeRate){
						reg.ccServiceFee = Math.round((regAmt - (reg.discount || 0)) * $scope.ccFeeRate * 100) / 100;
						$scope.ccServiceFee = $scope.ccServiceFee + reg.ccServiceFee;
					}
					$scope.totalDiscount += reg.discount;
				});
				$scope.orderTotal = Math.round(total * 1000) / 1000;
				total += initialServiceFee - $scope.totalDiscount;
				$scope.dueBeforeFees = Math.round(total * 1000) / 1000;
				total += $scope.ccServiceFee;
				$scope.feeTotal = getServiceFee();
				total += $scope.feeTotal;
				$scope.amountDue = Math.round(total * 1000) / 1000;
			}catch(error){
				logError('orderTotal', error);
			}
		};

		$scope.$watch('payment_method', function(){
			$scope.updateTotals();
		});

		//ensure user cannot change extra order qty to reduce amt due < already paid amt
		//and ensure ticket capacities are not exceeded
		let capacityErrors = [];
		$scope.checkXtraChg = function(reg, xtra, supressAlerts){
			let min = Number(xtra.min_orders) || 0;
			xtra.quantity = Number(xtra.quantity) || 0;
			if(xtra.quantity < min) xtra.quantity = min;
			try{
				angular.forEach(reg.extraOrders,function(extra){
					let curEvtExtra = eventExtras[extra.id];
					if(!curEvtExtra){
						let msg = `An error has occurred.  Please report this to the site administrator`;
						erSvc.easyRegAlert({"text":msg,"title":"Error Finding Event Extra"});
						extra.quantity = 0;
					}
					//check to see if extra order exceeds capacity for single registration
					if(extra.quantity > curEvtExtra.capacity_reg){
						let msg = `Maximum of ${extra.capacity_reg} ${extra.label} tickets per registration`;
						if(!supressAlerts) erSvc.easyRegAlert({"text":msg,"title":"Maximum Reached"});
						extra.quantity = Number(curEvtExtra.capacity_reg);
					} 

					//check to see if xtra order exceeds total allowed for event for this extra
					let evtMax = curEvtExtra.capacity_evt - curEvtExtra.purchases + extra.initialOrders;
					if(evtMax < 0) evtMax = 0;
					let ttlXtraPurchases = 0;
					//check all registrations for this ticket purchase
					angular.forEach($scope.registrations,function(curReg){
						let curXtra = (curReg.extraOrders || {})[xtra.id];
						if(!curXtra.quantity) return;
						ttlXtraPurchases += curXtra.quantity;
					});
					
					if(ttlXtraPurchases > evtMax && curEvtExtra.id == xtra.id){
						let msg = `Maximum of ${evtMax} ${xtra.label} tickets remain for this event`;
						if(!supressAlerts)  erSvc.easyRegAlert({"text":msg,"title":"Maximum Reached"});
						capacityErrors.push(msg);
						xtra.quantity = xtra.quantity - (ttlXtraPurchases - evtMax);
						if(xtra.quantity < 0) xtra.quantity = 0;
					}
				});

				//ensure total extras ordered do not exceed total event ticket capacity
				let totalExtrasOrdered = 0;
				angular.forEach($scope.registrations,function(curReg){
					angular.forEach(curReg.extraOrders,function(xtraOrder){
						if(xtraOrder.factor_evt_cap == '1') totalExtrasOrdered += Number(xtraOrder.quantity) || 0;
					});
				});

				//check to see if xtra order exceeds total tickets allowed for overall event
				if(xtra.factor_evt_cap === '0') xtra.factor_evt_cap = false;
				if(totalExtrasOrdered > maxCombinedExtras && xtra.factor_evt_cap){
					let newQty = xtra.quantity - (totalExtrasOrdered - maxCombinedExtras);
					newQty = newQty < 0 ? 0 : newQty;
					let msg = `Maximum of ${newQty} ${xtra.label} tickets remain for this event`;
					capacityErrors.push(msg);
					if(!supressAlerts)  erSvc.easyRegAlert({"text":msg,"title":"Maximum Reached"});
					xtra.quantity = newQty;
				}
				$scope.updateTotals();
				
				includeOrderSummary = true;
				//ensure user cannot change extra order qty to reduce amt due < already paid amt
				let r = $scope.selectedReg;
				if(!r.payments) return;
				if(($scope.regTotal(r) + (r.ccFeesPaid || 0) + getRegServiceFee(r)) < Number(r.payments)){
					r.extraOrders[xtra.id].quantity = initialExtras[xtra.id].quantity;
					if(!supressAlerts) erSvc.easyRegAlert({"text":"This item quantity cannot be reduced.","title":"Unable to reduce"});
				}
			}catch(error){
				logError('checkXtraChg', error);
			}
		}; // end checkXtraChg()

		/****** CC Processing Logic *********/
		$scope.card = {};
		$scope.card.expYear = new Date().getFullYear();
		$scope.years = [$scope.card.expYear];
		for (let i=1; i<10; i++){ $scope.years.push($scope.card.expYear + i) }

		$scope.card.expMonth = '01';
		$scope.months = ['01','02','03','04','05','06','07','08','09','10','11','12'];

		$scope.ccRequire = () => $scope.payment_method == 'cc' && $scope.orderTotal > 0 && $scope.ccEnabled;

		let paymentStatus;
		let paymentRegistrations;
		function submitPayment(){
			try{	
				$scope.paymentAmount = $scope.amountDue.toFixed(2);

				if($scope.allowPartialPymt){
					$scope.paymentAmount = $scope.card.amt.toFixed(2);
				}
				let fundsAvailable = parseFloat($scope.paymentAmount);
				paymentRegistrations = [];

				$scope.registrations.forEach(function(reg){
					if(fundsAvailable <= 0) return;
					let total = $scope.regTotal(reg) + (reg.ccFeesPaid || 0) + getRegServiceFee(reg) - Number(reg.payments || 0) + reg.ccServiceFee;
					if(total > fundsAvailable) total = fundsAvailable;
					paymentRegistrations.push({
						"id":reg.id,
						"confirmation":reg.confirmation,
						"total":total,
						"cc_fee":reg.ccServiceFee || '0'
					});
					fundsAvailable -= total;
				});
				paymentStatus = $.Deferred();
				$scope.paymentSuccess = false;
				$scope.paymentFailure = false;
				if($scope.allowPartialPymt){
					$scope.paymentAmount = $scope.card.amt.toFixed(2);
				}
				$scope.authMessage = '';
				$scope.paymentMessage = '';
				erSvc.loadingDialog("Processing Payment");

				if($scope.ccProvider == 'basys'){
					basysToken.submit();
					return paymentStatus.promise();
				}

				//if we're still here, we're using MagicWrighter
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
					"regConfirmation":$scope.registrations[0].confirmation,
					"regId":$scope.registrations[0].id,
					"registrations":JSON.stringify(paymentRegistrations),
					"accountid":$scope.eventData.accountid
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
							$scope.selectedReg.payments = Number($scope.selectedReg.payments || 0) + $scope.paymentAmount;
							$scope.registrations.forEach(r => r.payments = $scope.regTotal(r));
							sendConfirmationEmail();
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

		function processBasysPymt(resp){
			try{
				if(resp.status == 'success'){
					// let registrations = [];
					// $scope.registrations.forEach(function(reg){
					// 	let total = $scope.regTotal(reg) - Number(reg.payments || 0);
					// 	registrations.push({
					// 		"id":reg.id,
					// 		"confirmation":reg.confirmation,
					// 		"total":total
					// 	});
					// });
					let email = resp.user.email || '';
					$scope.card.email = email;
					let submitData = {
						"amount": (Number($scope.paymentAmount) * 100),
						"order_id":$scope.registrations[0].confirmation,
						"email":email,
						"token":resp.token,
						"description": $scope.eventData.eventName + " registration",
						"first_name": resp.user.first_name,
						"last_name": resp.user.last_name,
						"address_line_1": resp.billing.address,
						"city": resp.billing.city,
						"state": resp.billing.state,
						"postal_code": resp.billing.zip,
						"country": resp.billing.country,
						"accountid": $scope.eventData.accountid,
						"registrations": JSON.stringify(paymentRegistrations)
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
							$scope.selectedReg.payments = Number($scope.selectedReg.payments || 0) + $scope.paymentAmount;
							$scope.registrations.forEach(r => r.payments = $scope.regTotal(r));
							sendConfirmationEmail();
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

		/****** End CC Processing Logic *********/

		function sendConfirmationEmail(){
			try{
				var emailBody = `
					Your payment for the ${$scope.eventData.eventName} has been received. <br/> Please keep this message for 
					your records. <br/> 
					<b>Payment Confirmation Number: </b> ${$scope.paymentConfirmation}<br/>
					<b>Payment Total: $ ${parseFloat($scope.paymentAmount).toFixed(2)}`;
				erSvc.sendEmail($scope.card.email, "Thank You For Your Payment", emailBody, $scope.eventData.replytoemail);
			}catch(error){
				logError('sendConfirmationEmail', error);
			}
		}

		$scope.noFailure = function(){ 
			$scope.paymentFailure = false; 
			$scope.hideConfirmation = true;
			$scope.showRegMessage = false;
		};

		$scope.showUpdateRegBtn = function(){
			try{
				if($scope.selectedReg.confirmation && !$scope.paying){
					// do not allow reg update if amt due and cc payment required
					return $scope.orderTotal <= 0 || !$scope.ccRequired;
				}
				return false;
			}catch(error){
				logError('showUpdateRegBtn', error);
			}
		};

		let selectedDiscount = null;
		$scope.checkDiscountCode = function(showResults){
			if(!$scope.discountCode) return;
			selectedDiscount = null;
			let confirmation = $scope.selectedReg ? $scope.selectedReg.confirmation : '';
			dataSvc.getArray({
				query:'verifyEventDiscountCode', 
				eventid:$scope.eventData.eventid, 
				code:$scope.discountCode,
				confirmation:confirmation
			}).then(function(resp){
				if(resp[0]){
					selectedDiscount = resp[0];
					selectedDiscount.discount = Number(selectedDiscount.discount);
					if(confirmation){
						dataSvc.updateDiscountCode({
							id:$scope.selectedReg.id,
							discount_code:selectedDiscount.code
						});
					}
				}else{
					msg = 'This discount code does match any available codes.'
					erSvc.easyRegAlert({"text":msg,"title":"Discount Code Not Found"});
				}
				$scope.updateTotals();
			});
		};

		$(document).on('click','a', function(e){
			if(!$scope.selectedReg.confirmation && !$scope.showRegMessage && $scope.registrationAvailable){
				if(!confirm("Your registration is not Complete.  Would you like to navigate away without completing this registration?")){
					e.preventDefault();
				}
			}
		});
	});//End Controller
</script>
</head>
<body ng-app="regApp">
<top-nav ng-controller="navController"></top-nav>
<div class="container-fluid" ng-controller="regController" ng-cloak>

<!-- Breadcrumbs -->
<div class="row" style="padding-top:1em;" ng-cloak>
	<ol class="breadcrumb">
		<li class="breadcrumb-item"><a href="/">{{eventData.acctName}}</a></li>
		<li class="breadcrumb-item">
			<a href="/e/{{slug}}" ng-show="hasEvtPage">{{eventData.eventName}}</a>
			<span ng-show="!hasEvtPage">{{eventData.eventName}}</span>
		</li>
		<li class="breadcrumb-item active">
			{{eventData.acctType == 'ticketing' ? 'Purchase Tickets' : 'Register'}}
		</li>
	</ol>
</div>

<!-- Main Content -->
<div class="wrapper">
	<event-sidebar class="sidebar" ng-controller="eventSidebarController"></event-sidebar>
	<div class="alert alert-danger col-sm-9" role="alert" ng-if="errorThrown">
		<b>Unable to register at this time.</b><br/>
		{{errorDescription}}<br/><br/>
		Please refresh the page and try again.<br/>
		If this issue persists, please report this to the event administrator.
	</div>
	<div class="main-content" ng-show="!errorThrown">
		<h2 style="margin-right:30px" ng-if="eventData.acctType !== 'ticketing'">
			{{selectedReg.confirmation ? 'Manage Registration' : 'Register'}}
		</h2>
		<div class='alert alert-warning' role='alert' style="font-size:large;white-space:pre-line;" 
			ng-show="eventData.registration_message_primary">{{eventData.registration_message_primary}}
		</div>
		<form class="form-horizontal" role="form" name="regForm" id="registration">
		<div ng-if="(registrationAvailable || selectedReg.confirmation)" 
			ng-repeat="reg in registrations" ng-show="!paying">
			<H4 style="border-bottom:1px solid silver" ng-if="eventData.acctType !== 'ticketing'">
				Attendee {{$index + 1}}
			</H4>
			<div class='row mb-2'>
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.email.label || 'Email'}} *
				</label>
				<div class='col-sm-9'>
					<input type='email' ng-model="reg.email" class='form-control form-control-lg' 
						placeholder="{{regSettings.email.label || 'Email'}}" 
						required maxlength="100"
						style="max-width:75em;" ng-blur="checkAttendeeRecords(reg)">
				</div>
			</div>
			<div class='row mb-2'>
				<label class='col-sm-3 col-form-label col-form-label-lg'>{{regSettings.first_name.label || 'First Name'}} *</label>
				<div class='col-sm-9'>
					<input type='text' ng-model='reg.first_name' class='form-control form-control-lg' required style="max-width:40em;" 
						maxlength="50"  placeholder="{{regSettings.first_name.label || 'First Name'}}" 
						ng-blur="checkAttendeeRecords(reg)">
				</div>
			</div>
			<div class='row mb-2'>
				<label class='col-sm-3 col-form-label col-form-label-lg'>{{regSettings.last_name.label || 'Last Name'}} *</label>
				<div class='col-sm-9'>
					<input type='text' ng-model='reg.last_name' class='form-control form-control-lg' required style="max-width:40em;" 
						maxlength="50" placeholder="{{regSettings.last_name.label || 'Last Name'}}" 
						ng-blur="checkAttendeeRecords(reg)">
				</div>
			</div>
			<div class='row mb-2' 
				ng-if="showField('title') && !coreFieldExclusions.includes('title')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.title.label || 'Position/Title'}}
					{{regSettings.title.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9 form'>
					<input type='text' ng-model='reg.title' class='form-control form-control-lg' 
						style="max-width:30em;" maxlength="80" placeholder="Title" 
						ng-required="regSettings.title.require">
				</div>
			</div>
			<div class='row mb-2' ng-show="availableRegTypes().length > 1">
				<label class='col-sm-3 col-form-label col-form-label-lg'>Registration Type</label>
				<div class='col-sm-9'>
					<select	ng-model="reg.registration_typeid" class='form-select'
						ng-options="type.id as typeDisplay(type) for type in availableRegTypes()" 
						ng-change="setSelectedRegType()" required style="max-width:75em;">
					</select>
				</div>
			</div>
			<div class='row mb-2' 
				ng-if="showField('business') && !coreFieldExclusions.includes('business')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.business.label || 'Business Name'}}
					{{regSettings.business.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9'>
					<input type='text' ng-model='reg.business' class='form-control' required
						style="max-width:40em;" maxlength="50" 
						placeholder="{{regSettings.business.label || 'Business Name'}}"
						ng-required="regSettings.business.require">
				</div>
			</div>
			<div class='row mb-2' ng-if="showField('address') && !coreFieldExclusions.includes('address')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.address.label || 'Business Address'}}
					{{regSettings.address.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9'>
					<input type='text' ng-model='reg.address1' class='form-control' required
						style="max-width:40em;" maxlength="50" placeholder="Address"
						ng-required="regSettings.address.require">
				</div>
			</div>
			<div class='row mb-2' ng-if="showField('address') && !coreFieldExclusions.includes('address')">
				<label class='col-sm-3 col-form-label col-form-label-lg'></label>
				<div class='col-sm-9'>
					<input type='text' ng-model='reg.address2' class='form-control'
						style="max-width:40em;" maxlength="50" placeholder="Address 2">
				</div>
			</div>
			<div class='row mb-2' 
				ng-if="showField('address') && !coreFieldExclusions.includes('address')">
				<label class='col-sm-3 col-form-label col-form-label-lg'></label>
				<div class='col-sm-9 form-inline'>
					<input type='text' ng-model='reg.city' class='form-control' required
						style="max-width:20em;" maxlength="20" placeholder="City">
					<select ng-model='reg.state' class='form-select' required style="max-width:5em;"
						ng-required="regSettings.address.require">
						<option></option><?php state_options($registration['state']); ?>
					</select>
					<input type='text' ng-model='reg.zip' class='form-control' required
						style="max-width:10em;" maxlength="10" placeholder="Zip"
						ng-required="regSettings.address.require">
				</div>
			</div>
			<div class='row mb-2' ng-if="showField('phone') && !coreFieldExclusions.includes('phone')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.phone.label || 'Business Phone'}}
					{{regSettings.phone.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9 form-inline'>
					<input type='text' ng-model='reg.phone' class='form-control'
						style="max-width:20em;" maxlength="20" placeholder="Phone"
						ng-required="regSettings.phone.require">
				</div>
			</div>
			<div class='row mb-2' ng-show="showField('vendor_access') && !coreFieldExclusions.includes('vendor_access')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.vendor_access.label || 'Share my information with vendors'}}
				</label>
				<div class='col-sm-9 form-inline'>
					<select ng-model="reg.vendor_access" class="form-select">
						<option value="1">
							Yes, share my information. I'd like to be eligible for extra vendor promotions and drawings.
						</option>
						<option value="0"> No, please do not release my information. </option>
					</select>
				</div>
			</div>
			<div class='row mb-2' 
				ng-if="showField('web_address') && !coreFieldExclusions.includes('web_address')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.web_address.label || 'Web Address (optional)'}}
					{{regSettings.web_address.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9'>
					<input type='text' ng-model='reg.web_address'
						class='form-control' placeholder="www.myOrganization.com" style="max-width:40em;"
						ng-required="regSettings.web_address.require">
				</div>
			</div>
			<div class='row mb-2' 
				ng-if="showField('dietary_restrictions') && !coreFieldExclusions.includes('dietary_restrictions')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.dietary_restrictions.label || 'Dietary Restrictions'}}
					{{regSettings.dietary_restrictions.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9 form-inline'>
					<select ng-options="res.value as res.label for res in dietary_restrictions" 
						class='form-select' ng-model="reg.dietary_restrictions" 
						ng-required="regSettings.dietary_restrictions.require">
					</select>
				</div>
			</div>
			<div class='row mb-2' ng-show="showField('ec1') && !coreFieldExclusions.includes('ec1')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.ec1.label || 'Emergency Contact 1'}}
					{{regSettings.ec1.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9 form-inline'>
					<input type="text" class="form-control" ng-model="reg.ec1_name" placeholder="Name" 
						ng-required="regSettings.ec1.require" />
					<input type="email" class="form-control" ng-model="reg.ec1_email" placeholder="Email" 
						ng-required="regSettings.ec1.require" />
					<input type="text" class="form-control" ng-model="reg.ec1_phone_prim" 
						placeholder="Primary Phone" ng-required="regSettings.ec1.require" />
					<input type="text" class="form-control" ng-model="reg.ec1_phone_alt" 
						placeholder="Alternate Phone" ng-required="regSettings.ec1.require" />
				</div>
			</div>
			<div class='row mb-2' ng-show="showField('ec2') && !coreFieldExclusions.includes('ec2')">
				<label class='col-sm-3 col-form-label col-form-label-lg'>
					{{regSettings.ec2.label || 'Emergency Contact 2'}}
					{{regSettings.ec2.require ? ' *' : ''}}
				</label>
				<div class='col-sm-9 form-inline'>
					<input type="text" class="form-control" ng-model="reg.ec2_name" placeholder="Name" 
						ng-required="regSettings.ec2.require" />
					<input type="email" class="form-control" ng-model="reg.ec2_email" placeholder="Email" 
						ng-required="regSettings.ec2.require" />
					<input type="text" class="form-control" ng-model="reg.ec2_phone_prim" 
						placeholder="Primary Phone" ng-required="regSettings.ec2.require" />
					<input type="text" class="form-control" ng-model="reg.ec2_phone_alt" 
						placeholder="Alternate Phone" ng-required="regSettings.ec2.require" />
				</div>
			</div>
			<div class='row mb-2' data-required="{{field.required}}"
				ng-repeat="field in eventData.extraFields | orderObjectBy:'sortorder'">
				<label class='col-sm-3 col-form-label col-form-label-lg' 
					ng-class="{'header': field.type == 'l'}">
					{{field.label}}
					{{field.required == '1' ? ' *' : ''}}
				</label>
				<div class='col-sm-9 form-inline' ng-class="{'header': field.type == 'l'}">
					<input type="text" ng-model="reg[field.field]" class='form-control'
						ng-required="field.required == '1'" ng-if="field.type == 't'"/>
					<input type="email" ng-model="reg[field.field]" class='form-control'
						ng-required="field.required == '1'" ng-if="field.type == 'e'"
						placeholder="Email" />
					<select ng-options="idx as option for (idx, option) in field.options" 
						ng-if="field.type == 's'" class='form-select'
						ng-model="reg[field.field]" ng-required="field.required == '1'" >
					</select>
					<label ng-repeat="option in field.options track by $index" style="display:block" ng-if="field.type == 'r'">
						<input type="radio" ng-model="reg[field.field]" ng-required="field.required == '1'" 
							ng-value="$index.toString()">
						{{option}}
					</label>
					<label ng-repeat="option in field.options track by $index" 
						style="display:block" ng-if="field.type == 'c'">
						<input type="checkbox" ng-checked="reg[field.field].indexOf(option) > -1"
							ng-click="toggleCheckbox(field.field, option, reg)"	value="{{option}}">
						{{option}}
					</label>
					<label style="display:block" ng-if="field.type == 'a'">
						<input type="checkbox" ng-model="reg[field.field]" ng-true-value="'yes'" required/>
						{{field.options}}
						<div class='alert alert-danger' role='alert' ng-show="submitClickced && reg[field.field] != 'yes'"
							style="display:inline-block;padding: 2px 1em;margin-bottom:1px">
							Required
						</div>
					</label>
					<label ng-if="field.type == 'l'">
						<div ng-repeat="option in field.options track by $index">{{option}}&nbsp; </div>
					</label>
				</div>
			</div>
			<div class='row mb-2' ng-repeat="xtra in reg.extraOrders | orderObjectBy:'sortorder'" 
				ng-if="showExtraForReg(reg, xtra)">
				<label class='col-sm-3 col-form-label col-form-label-lg'>{{xtra.label}}</label>
				<div class='col-sm-1'>
					<input type="number" ng-model="xtra.quantity" style="width:4em"
						ng-disabled="option.current == 'false'" ng-change="checkXtraChg(reg, xtra)" 
						min="{{xtra.min_orders}}" step="1" string-to-number ng-if="!xtra.soldOut"/>
					<span ng-if="xtra.soldOut">{{xtra.quantity}}</span>
					<div ng-if="xtra.min_orders != '0'">
						Minimum {{xtra.min_orders}}
					</div>
				</div>
				<div class='col-sm-1'>{{xtra.price | currency}}</div>
				<div class='col-sm-3'>{{xtra.description}}</div>
				<div class='alert alert-danger col-sm-3' role='alert' ng-show="xtra.soldOut"
					style="padding-bottom:3px;padding-top:3px">
					Sold Out
				</div>
			</div>
			<div class='row mb-2' ng-show="getExtraOptions(reg).length">
				<label class='col-sm-3 col-form-label col-form-label-lg'>Total Cost</label>
				<div class='col-sm-3'> {{regTotal(reg) | currency}}	</div>
			</div>
		</div> <!-- End Attendee Repeat -->
		<div class='row mb-2' ng-show="discountAvailable && !paying">
			<H4 ng-show="registrations.length > 1">Discount Code</H4>
			<label class='col-sm-3 col-form-label col-form-label-lg'>Discount Code</label>
			<div class='col-sm-3'>
				<input class="form-control" ng-model="discountCode" ng-blur="checkDiscountCode(true)"/>
			</div>
		</div>
		</form>	<!-- End Attendee Reg Form -->

		<div ng-show="paying && eventData.acctType !== 'ticketing'">
			<h3>Attendees</h3>
			<div ng-repeat="reg in registrations">
				<span ng-bind="reg.first_name"></span>
				<span ng-bind="reg.last_name"></span>
				<span ng-bind="reg.email"></span>
			</div>
		</div>

		<table ng-show="paying && !showRegMessage && !paymentSuccess" style="margin:2em">
			<tr ng-if="feeTotal || ccServiceFee || totalDiscount">
				<td class="bold">Order Total</td>
				<td>{{orderTotal | currency}}</td>
			</tr>
			<tr ng-if="totalDiscount">
				<td class="bold">Discount</td>
				<td>{{totalDiscount | currency}}</td>
			</tr>
			<tr ng-if="feeTotal">
				<td class="bold">Service Fee</td>
				<td>{{feeTotal | currency}}</td>
			</tr>
			<tr ng-if="ccServiceFee">
				<td class="bold">CC Service Fee</td>
				<td>{{ccServiceFee | currency}}</td>
			</tr>
			<tr>
				<td class="bold" style="width:10em">Total Due</td>
				<td>{{amountDue | currency}}</td>
			</tr>
		</table>
		
		<form name="paymentForm">
		<!-- Payment Info -->
		<div class='row mb-2' ng-show="paying && !showRegMessage && orderTotal > 0 && !ccRequired">
			<label class='col-sm-3 col-form-label col-form-label-lg'>Payment Method</label>
			<div class='col-sm-9 form-inline'>
				<select ng-model='payment_method' class='form-select'>
					<option value="po" ng-if="!ccRequired">Purchase Order (PO)</option>
					<option value="check" ng-if="!ccRequired">Check</option>
					<option value="cc" ng-show="ccEnabled">Credit/Debit Card</option>
				</select>
				<span ng-show="payment_method == 'po' || payment_method == 'check'"
					style="margin-left:30px">
					<b>Check or PO Number</b>
					<input type='text' ng-model='payment_number' class='form-control' ng-show="!ccRequired"
						style="max-width:20em;" maxlength="20"  placeholder="Ex: FY16-12345">
				</span>
			</div>
		</div>

		<!-- CC INFO -->
		<div class="row mb-2" 
			ng-show="paying && payment_method == 'cc' && orderTotal > 0 && ccEnabled && !paymentSuccess">
			<H3>Card Information</H3>

			<div ng-if="ccProvider == 'basys'">
				<div id="tokenizer-container" style="width:93%;margin:auto"></div>
				<div style="margin-top:.5em">
					<b>Payment Amount &nbsp;</b>
					<span ng-show="!allowPartialPymt" style="padding-left:2em">
						{{amountDue | currency}}
					</span>
					<span ng-show="allowPartialPymt">
						<input class='form-control' style="width:6em;display: inline-block;" 
							ng-required="ccRequire()" type="number" ng-model="card.amt" min=".01" max="" />
					</span>
				</div>
			</div>

			<table id="ccTable" ng-if="ccProvider == 'magicWrighter'"
				style="width:50em">
				<tr>
					<td class="bold right">Name</td>
					<td>
						<input type='text' ng-model="card.name" class='form-control' ng-required="ccRequire()" />
					</td>
				</tr>
				<tr>
					<td class="bold right">Email</td>
					<td>
						<input type='text' ng-model="card.email" class='form-control' size="50"	ng-required="ccRequire()" />
					</td>
				</tr>
				<tr>
					<td class="bold right">Street</td>
					<td>
						<input type='text' ng-model="card.street" class='form-control' ng-required="ccRequire()" />
					</td>
				</tr>
				<tr>
					<td class="bold right">City, State Zip</td>
					<td>
						<input type='text' ng-model="card.city" class='form-control' style="display:inline;max-width:15em;"
							ng-required="ccRequire()" />
						<select ng-model="card.state" class='form-select' style="display:inline;max-width:5em;margin:0px 5px"
							ng-required="ccRequire()" />
							<?php state_options($registration['state']); ?>
						</select>
						<input type='text' ng-model="card.zip" class='form-control'	style="display:inline;max-width:5em;"
							ng-required="ccRequire()" />
					</td>
				</tr>
				<tr>
					<td class="bold right">Card Number</td>
					<td>
						<input type='text' ng-model="card.number" class='form-control' ng-required="ccRequire()"/>
					</td>
				</tr>
				<tr>
					<td class="bold right">Expiration</td>
					<td>
						<select ng-model="card.expMonth" class='form-select' ng-options="month for month in months"
							style="width:20%;display:inline" ng-required="ccRequire()">
						</select>
						<span style="margin-right:10px">Month</span>
						<select class='form-select' style="width:20%;display:inline" ng-model="card.expYear" 
							ng-options="year for year in years"	ng-required="ccRequire()">
						</select>
						<span style="margin-right:10px">Year</span>
					</td>
				</tr>
				<tr>
					<td class="bold right">Cvv</td>
					<td>
						<input type='text' ng-model="card.cvv" class='form-control' style="width:20%" ng-required="ccRequire()"/>
					</td>
				</tr>
				<tr>
					<td class="bold right">Payment Amount &nbsp;</td>
					<td> 
						<span ng-show="!allowPartialPymt">{{amountDue | currency}}</span>
						<span ng-show="allowPartialPymt">
							<input class='form-control' style="width:20%" ng-required="ccRequire()"
								type="number" ng-model="card.amt" min=".01" max="" />
						</span>
					</td>
				</tr>
			</table>
		</div> <!-- End CC info -->
		</form> <!-- End Payment Form -->

		<input type="hidden" name="eventid" value="{{eventData.eventid}}">
		<input type="hidden" name="event" value="{{eventData.slug}}">

		<!-- Buttons for new registration -->
		<div class='col-sm-12 button-row mb-2' 
			ng-show="registrationAvailable && !showRegMessage && !paymentSuccess">
			<button type="button" ng-click="addAttendee()" ng-show="!paying" class="btn btn-primary"
				ng-if="!selectedReg.confirmation">
				<i class="bi bi-plus-circle"></i>
				Add Attendee
			</button>
			<button type="button" ng-click="goToPayment()" class="btn btn-success" 
				ng-show="!paying && registrations[0].registration_typeid">
				{{orderTotal > 0 ? 'Continue To Payment' : 'Complete Registration'}}
			</button>
			<button type="button" ng-click="paying = false" ng-show="paying" class="btn btn-primary">
				<span class="bi bi-chevron-left"></span>
				Back to Attendee Info
			</button>
			<button type="button" ng-click="submitRegistration()" ng-show="paying" 
				class="btn btn-success">
				<span class="bi bi-check-lg"></span>
				Complete Order
			</button>
			<button type="button" ng-click="updateRegistration()" class="btn btn-primary" 
				ng-if="showUpdateRegBtn()">
				<span class="bi bi-floppy"></span>
				Update Order
			</button>
		</div>

		<div class='col-sm-12 button-row row mb-2' 
			ng-show="!registrationAvailable && !showRegMessage">
			<button type="button" ng-click="goToPayment()" class="btn btn-success" 
				ng-show="!paying && registrations[0].registration_typeid">
				<span class="bi bi-currency-dollar"></span>
				Continue to Payment Info
			</button>
			<button type="button" ng-click="submitRegistration()" class="btn btn-success"
				ng-show="paying && orderTotal > 0 && !paymentFailure">
				<span class="bi bi-check-lg"></span>
				Submit Payment
			</button>
		</div>

		<!-- <div class="button-row" ng-hide="showRegMessage || !registrationAvailable || paymentSuccess">
			<a href="/e/{{slug}}" class="btn btn-primary">Cancel</a>
		</div> -->

		<H2 ng-if="!registrationAvailable && !selectedReg.confirmation && regReady">
			Orders are not currently available for this event.
		</H2>
		<div class="alert alert-danger" ng-show="submitClickced && !regForm.$valid && !paying">
			Required information has not been completed or is invalid.  Please review and submit again.
		</div>
		
		<div id="resultMessage">
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

			<div class='alert alert-success bold' role="alert"
				ng-show="(payment_method != 'cc' || !ccEnabled) && showRegMessage">
				<div style="margin-bottom:10px">
					<H3>{{eventData.eventName}}</H3>
					Registration Complete
					<br/><br/>
					Please keep the following confirmation number for your records:
					<span ng-show="savedRegistrations.length == 1">{{savedRegistrations[0].confirmation}}</span>
					<div ng-repeat="reg in savedRegistrations" ng-show="savedRegistrations.length != 1">
						{{reg.first_name}} {{reg.last_name}}: <b>{{reg.confirmation}}</b>
					</div>
				</div>
				<div ng-show="orderTotal > 0">
					<div ng-if="selectedReg.payment_method=='check'">
						You have indicated that you will be paying by check<br>
						<div>{{eventData.pymt_chk_msg}}</div>
					</div>
					<div ng-if="selectedReg.payment_method=='po'">
						You have indicated that you will be paying by purchase order<br>
						<div>{{eventData.pymt_po_msg}}</div>
					</div>
				</div>
				<div style="text-align:right;margin-right:1em" ng-show="ticketsOrdered() > 0">
					<button class="btn btn-primary">
						<a style="color:white" href="order_summary">View Tickets</a>
					</button>
				</div>
			</div>

			<div class='alert alert-success bold'  role='alert' 
				ng-show="paymentAmount && !paymentFailure && !hideConfirmation && paymentSuccess">
				<div style="margin-bottom:10px">
					<H3>{{eventData.eventName}}</H3>

					Registration Complete 

					Please keep this confirmation number for your records
					<div ng-repeat="reg in registrations">
						{{reg.first_name}} {{reg.last_name}}: <b>{{reg.confirmation}}</b>
					</div>
				</div>
				<div ng-show="orderTotal == 0" class='alert alert-success'>
					Your order has been paid in full.  Thank you!<br/>
				</div>

				<div ng-if="paymentSuccess"  class='alert alert-success'>
					<H2 style="text-decoration:underline">Thank you for your payment.</H2> <br/>
					Your payment of {{paymentAmount | currency}} has been successfully processed.<br/>
					Your confirmation number is: <b>{{paymentConfirmation}}</b><br/>
					An email with this information has been sent to {{card.email}}
				</div>
				<div style="text-align:right;margin-right:1em" ng-show="ticketsOrdered() > 0">
					<button class="btn btn-primary">
						<a style="color:white" href="order_summary">View Tickets</a>
					</button>
				</div>
			</div>
		</div>
	</div> <!-- END COLUMN -->
</div> <!-- END ROW Main content-->

<div style="display:none">
	<div id="attendeeSelector">
		<div ng-repeat="attendee in attendeeMatches" class="attendeeDiv">
			<h3>{{attendee.first_name}} {{attendee.last_name}}</h3>
			<div>{{attendee.email}}</div>
			<div ng-show="attendee.business">{{attendee.business}}</div>
			<div ng-show="attendee.state">{{attendee.state}}</div>
			<div class="button-row">
				<button class="btn btn-success" ng-click="selectAttendee(attendee)">This Is Me</button>
				<button class="btn btn-primary" ng-click="attendeeNotFound()" ng-show="attendeeMatches.length == 1">
					Not Me
				</button>
			</div>
		</div>
		<div class="button-row" ng-show="attendeeMatches.length > 1">
			<button class="btn btn-primary" ng-click="attendeeNotFound()">Not Me</button>
		</div>
	</div>
</div>
</div> <!-- END CONTROLLER -->
<div style="height: 60px"></div>
<er-Footer />
</body>
</html>
