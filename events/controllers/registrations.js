regApp.controller('registrations', function($scope, $http, $routeParams, $location, $q, $rootScope, $filter, dataSvc, erSvc) {
	$('.list-group-item.active').removeClass('active');
	$('.list-group-item[href="#!registrations"]').addClass('active');

	$("a.flash").click(function(){
		$("div#body, textarea#body_text").append($(this).attr("link"));
		$scope.emailContent = $scope.emailContent + $(this).attr("link");
		$('#tinymce').append($(this).attr("link"));
	});
	
	$scope.userid = erSessionData.userid;
	$scope.accountid = erSessionData.accountid;
	$scope.eventId = $routeParams.eventid || erSessionData.curEvent.id;
	if((erSessionData.curEvent && erSessionData.curEvent.id != $scope.eventId) || !erSessionData.curEvent){
		erSvc.setSelectedEvent($scope.eventId);
	}
	erSvc.loadingDialog();
	$scope.dietary_restrictions = erSvc.getDietaryRestrictions();
	$scope.deleteClicked = false;
	$scope.selectedRegs = [];
	let eventExtras = {};

	dataSvc.getArray({"query":"registrationTypes","eventid":$scope.eventId}).then(function(data){
		$scope.registrationTypes = data;
	});

	$scope.events;
	dataSvc.getObject({'query':'accountEvents'}).then(function(response){
		$scope.events = response;
		angular.forEach($scope.events,function(event){
			if(event.id == $scope.eventId) $scope.selectedEvent = event;
		});
	});

	function getExtraOptions(){
		eventExtras = {};
		if($scope.selectedEvent && $scope.selectedEvent.slug){
			dataSvc.getEventData($scope.selectedEvent.slug).then(function(evt){
				$scope.hasExtras = Object.keys(evt.extraOptions).length;
				eventExtras = evt.extraOptions;
			});
		}
	}

	$scope.columns = [
		{fld:"confirmation",label:"Confirmation"},
		{fld:"first_name",label:"First"},
		{fld:"last_name",label:"Last"},
		{fld:"email",label:"Email"},
		{fld:"registration_type",label:"Registration Type"},
		{fld:"discount_code",label:"Discount Code"},
		{fld:"price",label:"Price",dataType:"number",filter:"currency"},
		{fld:"discount",label:"Discount",dataType:"number",filter:"currency"},
		{fld:"cc_fees",label:"CC Fee",dataType:"number",filter:"currency"},
		{fld:"serviceFee",label:"Service Fee",dataType:"number",filter:"currency"},
		{fld:"payments",label:"Payments",dataType:"number",filter:"currency"},
		{fld:"balance",label:"Balance",dataType:"number",filter:"currency"},
		{fld:"payment_method",label:"Payment Method"},
		{fld:"payment_number",label:"Payment Number"},
		{fld:"registration_date",label:"Reg Date",dataType:"date"},
		{fld:"registration_time",label:"RegTime"},
		{fld:"business",label:"Business"},
		{fld:"address1",label:"Address 1"},
		{fld:"address2",label:"Address 2"},
		{fld:"city",label:"City"},
		{fld:"state",label:"State"},
		{fld:"zip",label:"Zip"},
		{fld:"title",label:"Title"},
		{fld:"web_address",label:"Web Address"},
		{fld:"vendor_access",label:"Vendor Access"},
		{fld:"phone",label:"Phone"},
		{fld:"ec1_name",label:"EC Name"},
		{fld:"ec1_email",label:"EC Email"},
		{fld:"ec1_phone_prim",label:"EC Phone"},
		{fld:"ec1_phone_alt",label:"EC Phone Alt"},
		{fld:"ec2_name",label:"EC 2 Name"},
		{fld:"ec2_email",label:"EC 2 Email"},
		{fld:"ec2_phone_prim",label:"EC 2 Phone"},
		{fld:"ec2_phone_alt",label:"EC 2 Phone Alt"},
		{fld:"checkin",label:"Checked In"},
		{fld:"checkin_user",label:"Checked In By"},
		{fld:"dietary_restrictions",label:"Dietary Restrictions"},
		{fld:"deleted",label:"Cancelled"},
		{fld:"signupCount",label:"Signup Count"}
	];
	
	$scope.columnIncluded = fld => $scope.columns.some(c => c.fld == fld);

	$scope.extraFields = [];
	$scope.emailRecipients = [{fld:"email",label:"Attendee",selected:true}];
	dataSvc.getExtraRegFields($scope.eventId).then(function(resp){
		resp.forEach(fld => {
			let rec = {fld:fld.field, label:fld.label};
			if(['r', 's'].includes(fld.type) && Array.isArray(fld.options)) {
				rec.valueMap = {};
				fld.options.forEach((option, idx) => {
					rec.valueMap[idx.toString()] = option;
				});
			}

			$scope.columns.push(rec);
			if(fld.type == 'e') $scope.emailRecipients.push({...rec,selected:false});
		});
		$scope.extraFields = resp;
		getRegistrations();
	});

	$scope.replytoemails = [];
	dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].email);
	});

	dataSvc.getArray({'query':'eventDataRaw','eventid':$scope.eventId}).then(function(resp){
		if(resp[0]){
			if(!$scope.replytoemails.includes(resp[0].replytoemail))
				$scope.replytoemails.push(resp[0].replytoemail);
			$rootScope.curEventName = resp[0].name;
		} 
	});

	//Remove fields not used by account
	dataSvc.getPreferenceByName('regScreenConfig', $scope.accountid).then(function(res){
		if(res[0]){
			let settings = {};
			try{ settings = angular.fromJson(res[0].value); }
			catch{ settings = {}; }
			for(fld in settings){
				if(settings[fld].hide){
					filds = [];
					if(fld == 'address') fields = ['address1','address2','city','state','zip']; 
					else if(fld == 'ec1') fields = ['ec1_name','ec1_email','ec1_phone_prim','ec1_phone_alt'];
					else if(fld == 'ec2') fields = ['ec2_name','ec2_email','ec2_phone_prim','ec2_phone_alt'];
					else fields = [fld];
					fields.forEach(f => {
						let col = $scope.columns.find(c => c.fld == f);
						$scope.columns.splice($scope.columns.indexOf(col),1);
					});
				}
			}
		}
		if($scope.acctType == 'ticketing') {
			$scope.columns =  $scope.columns.filter(c => c.fld != 'registration_type');
		}
	});

	let allRegistrations = [];
	function getRegistrations(){
		allRegistrations = [];
		$scope.eventRegistrations = [];
		$scope.activeRegistrations = 0;
		$scope.inactiveRegistrations = 0;
		dataSvc.getRegistrations($scope.eventId).then(function(response){
			let regs = {};
			angular.forEach(response,function(reg){
				regs[reg.id] = reg;
				reg.paymentTotal = reg.balance > 0 ? reg.balance:'0.00';
				reg.registration_date = $filter('mySqlToLocalDate')(reg.create_date);
				reg.registration_time = $filter('mySqlToLocalTime')(reg.create_date);
				reg.vendor_access = reg.vendor_access == '1' ? 'Yes':'No';
				if(reg.deleted != 1) $scope.activeRegistrations ++;
				else $scope.inactiveRegistrations ++;
				if(reg.confirmation == $routeParams.confirmation){
					reg.selected = true;
					$scope.selectedRegs.push(reg);
					if($routeParams.function == 'edit') $scope.edit_registration();
					if($routeParams.function == 'sched') $scope.edit_schedule();
				}
			});
			allRegistrations = Object.values(regs);
			$scope.includeCancelled = $scope.includeCancelled ?? false;
			$scope.eventRegistrations = Object.values(regs).filter
				(r => r.deleted == '0' || $scope.includeCancelled);
			erSvc.closeLoading();
			$('#registrationDialog').hide(500);
			erSvc.initTinyMce('body_text', true);
			$scope.$watch('eventRegistrations',() => {
				$scope.selectedRegs = $scope.eventRegistrations.filter(r => r.selected);
			},true);
		});
		getExtraOptions();
	}

	$scope.$watch('includeCancelled',function(){
		if(!$scope.eventRegistrations) return;
		$scope.eventRegistrations = allRegistrations.filter(r => r.deleted == '0' || $scope.includeCancelled);
	});

	$scope.addPayment = function(){
		$scope.selectedRegs.forEach(r => r.payment_type = 'Check')
		$scope.dialogTitle = "Enter Payment(s)";
		$('#pymtDialog').show(500);
		$('#pymtDialog [ng-model="reg.ref_nbr"]').first().focus();
		$('#pymtDialog').scrollTop(0);
	};

	$scope.pymtTypeChg = reg =>{
		if(['Credit','Refund'].includes(reg.payment_type)) reg.paymentTotal = '';
	};

	$scope.savePayment = function(){
		angular.forEach($('.paymentForm'),function(payment){
			let registration = $scope.eventRegistrations.filter(r => r.id == $(payment).attr('data-regId'))[0];
			if(!registration) return;
			registration.paymentTotal = parseFloat(registration.paymentTotal);
			if(registration.payment_type == 'Refund' && registration.paymentTotal > 0){
				registration.paymentTotal = -1 * registration.paymentTotal;
			}
			let submitData = {
				"registrationid":registration.id,
				"userid": $scope.userid,
				"amount": registration.paymentTotal,
				"payment_type":registration.payment_type,
				"ref_nbr": registration.ref_nbr,
				"note": registration.pymtNote,
				"eventid": $scope.eventId
			}
			registration.balance = registration.balance - registration.paymentTotal;
			registration.payments = parseFloat(registration.payments) + parseFloat(registration.paymentTotal);
			$http.get('/save_payment.php', {params:submitData});
		});
		$("#pymtDialog").hide(500);
	};

	$scope.manage_payments = function(){
		var queryParams = {
			"query":"payments",
			"eventid":$scope.eventId,
			"confirmation":$scope.selectedRegs[0].confirmation
		};
		dataSvc.getArray(queryParams).then(function(response){
			$scope.payments = response;
			$scope.regPayments = 0;
			angular.forEach($scope.payments,function(pymt){
				$scope.regPayments += parseFloat(pymt.amount);
			});
			$scope.dialogTitle = "Payments";
			$('#paymentsDialog').show(500);
			$scope.$applyAsync();
		});
	};

	function getSelRegConfirmations(){
		var response = $q.defer();
		if($scope.selectedRegs.find(r => r.deleted == '1')){
			var msg = "The current selection includes canceled reservations.  Would you like to include canceled reservations?"
			erSvc.easyRegConfirm({"text":msg,"title":"Include Canceled?"},"Include","Do Not Include").then(function(res){
				let regs = $scope.selectedRegs.filter(r => r.deleted != '1' || res)
					.map(r => r.confirmation);
				response.resolve(regs);
			});
		}else{
			response.resolve($scope.selectedRegs.map(r => r.confirmation));
		}
		return response.promise;
	}

	$scope.generate_invoice = function(){
		getSelRegConfirmations().then(r => {
			window.open(`/events/invoice.php?eventid=${$scope.eventId}&confirmation=${r.toString()}`);
		});
	};

	$scope.generate_certificate = function(){
		getSelRegConfirmations().then(r => {
			window.open("/certificate.php?confirmation=" + r.toString());
		});
	};

	$scope.generate_badge = function(){
		getSelRegConfirmations().then(r => {
			window.open(`/events/badge.php?eventid=${$scope.eventId}&confirmation=${r.toString()}`);
		});
	};

	$scope.checkin_user = function(){ $('#checkinDialog').show(500); };

	$scope.completeCheckin = function(){
		erSvc.loadingDialog('Checking Attendees In');
		let updatedRecords = 0;
		angular.forEach($scope.selectedRegs,function(reg){
			if(reg.checkin){
				if(++updatedRecords == $scope.selectedRegs.length){
					erSvc.closeLoading();
					$scope.closeRightDialog();
				}
				return;
			}
			dataSvc.checkinAttendee(reg.id, $scope.userid, $scope.eventId).then(function(resp){
				reg.checkin = 'This Session';
				reg.checkin_user = 'You';
				if(++updatedRecords == $scope.selectedRegs.length){
					erSvc.closeLoading();
					$scope.closeRightDialog();
				}
			});
		});
	};

	$scope.edit_registration = function(){
		$scope.newRegistration = false;
		$scope.editReg = angular.copy($scope.selectedRegs[0]);
		$scope.editReg.extraOrders = angular.copy(eventExtras);
		angular.forEach($scope.editReg.extraOrders,order => order.quantity = 0);
		dataSvc.getRegistrationData($scope.editReg.confirmation, $scope.eventid).then(function(resp){
			resp.extraOrders.forEach(function(o){
				if($scope.editReg.extraOrders[o.id]){
					$scope.editReg.extraOrders[o.id].quantity = o.quantity;
					$scope.editReg.extraOrders[o.id].orderId = o.orderId;
				} 
			});
			$scope.$applyAsync();
		});
		$scope.dialogTitle = "Edit Registration";
		$('#registrationDialog').show(500);
	};

	$scope.new_registration = function(){
		$scope.newRegistration = true;
		let newReg = {};
		angular.forEach($scope.columns, field => newReg[field.fld] = "" );
		$scope.editReg = newReg;
		$scope.editReg.extraOrders = angular.copy(eventExtras);;
		angular.forEach($scope.editReg.extraOrders,order => order.quantity = 0);
		$scope.dialogTitle = "New Registration";
		$('#registrationDialog').show(500);
	};

	$scope.toggleCheckbox = function(field, val){
		$scope.editReg[field] = $scope.editReg[field] || [];
		if(typeof $scope.editReg[field] == 'string') $scope.editReg[field] = JSON.parse($scope.editReg[field]);
		let idx = $scope.editReg[field].indexOf(val);
		if(idx < 0) $scope.editReg[field].push(val);
		else $scope.editReg[field].splice(idx, 1);
	};

	$scope.editRegComplete = function(){
		$scope.editReg = $scope.editReg || {};
		return ($scope.editReg.last_name && $scope.editReg.first_name && $scope.editReg.registration_typeid)
	};

	$scope.updateRegistration = function(){
		erSvc.loadingDialog();
		angular.forEach($scope.registrationTypes,function(type){
			if(type.id == $scope.editReg.registration_typeid) $scope.editReg.registration_type = type.name;
		});
		dataSvc.createOrUpdateRegistration($scope.editReg, $scope.eventId).then(function(res){
			angular.forEach($scope.editReg.extraOrders,function(order){
				if(!order.orderId && order.quantity <= 0) return;
				if(order.orderId && order.quantity <= 0){
					dataSvc.deleteRecord({"table":"registration_extra_orders","id":order.orderId});
					return;
				}
				let payload = {
					"registrationid":$scope.editReg.id,
					"registration_extras_id":order.id,
					"quantity":order.quantity,
					"id":order.orderId ? order.orderId : null
				}
				dataSvc.createOrUpdateRecord({"table":"registration_extra_orders","record":payload});		
			});
			getRegistrations();
		});
	};

	$scope.edit_schedule = function(){
		erSvc.loadingDialog('Fetching Schedule');
		if(!$scope.sessions){
			dataSvc.getObject({'query':'eventSessions','eventid':$scope.eventId}).then(function(resp){
				$scope.sessions = resp;
				angular.forEach($scope.sessions,function(sess){
					sess.liveSections = {};
					sess.virtualSections = {};
				});
				dataSvc.getObject({'query':'eventSections','eventid':$scope.eventId}).then(function(sections){
					angular.forEach(sections,function(section){
						if(!$scope.sessions[parseFloat(section.sessionid)]) return;
						if(section.is_virtual == '1'){
							$scope.hasVirtual = true;
							$scope.sessions[parseFloat(section.sessionid)].virtualSections[section.id] = section;
						}else{
							$scope.sessions[parseFloat(section.sessionid)].liveSections[section.id] = section;
							$scope.sessions[parseFloat(section.sessionid)].hasLive = true;
						}
					});
					getSignups();
				});
			});
		}else{
			getSignups();
		}
	};

	function getSignups(){
		var signups;
		var queryParams = {
			"query":"registrationSignups",
			"confirmation":$scope.selectedRegs[0].confirmation
		};
		dataSvc.getArray(queryParams).then(function(resp){
			angular.forEach($scope.sessions,function(session){
				delete session.selectedSection;
				angular.forEach(session.virtualSections,function(sec){
					sec.selected = false;
					sec.video_viewed = false;
					sec.signupid = '';
				});
			});
			signups = resp;
			angular.forEach(signups,function(signup){
				let session = $scope.sessions[signup.sessionid];
				if(!session) return;
				if(session.liveSections[signup.sectionid]){
					session.selectedSection = signup.sectionid;
				}
				if(session.virtualSections[signup.sectionid]){
					session.virtualSections[signup.sectionid].selected = true;
					session.virtualSections[signup.sectionid].video_viewed = signup.video_viewed == '1';
					session.virtualSections[signup.sectionid].signupid = signup.signupid;
				}
			});
			$scope.dialogTitle = "Attendee Schedule";
			$('#schedDialog').show(500);
			erSvc.closeLoading();
		});
	};

	$scope.updateVideoViewed = function(section){
		erSvc.loadingDialog();
		if(!section.signupid) return;
		let rec = {"id":section.signupid};
		if(section.video_viewed) rec.video_viewed = "now()";
		else rec.video_viewed = 'null';
		dataSvc.createOrUpdateRecord({"table":"signups","record":rec}).then(function(res){
			erSvc.closeLoading();
		});
	};

	$scope.extraSession = function(session){
		if(!session.selectedSection) return false;
		var extra = 'Multiple Session Section';
		angular.forEach(session.liveSections,function(section){
			if (section.id == session.selectedSection) extra = false;
		});
		return extra;
	};

	$scope.selectUserSection = function(session){
		erSvc.loadingDialog('Saving Selection');
		var reg = $scope.selectedRegs[0].id;
		dataSvc.createSignup($scope.eventId, reg, session.selectedSection).then(function(){
			getSignups();
		});
	};

	$scope.updateVirtualSignup = function(section){
		erSvc.loadingDialog('Updating Schedule');
		var reg = $scope.selectedRegs[0].id;
		if(section.selected){
			dataSvc.createSignup($scope.eventId, reg, section.id, section.is_virtual)
			.then(function(){
				erSvc.closeLoading();
				getSignups();
			});
		}else{
			dataSvc.deleteSignup($scope.eventId, reg, section.id).then(function(){
				getSignups();
				erSvc.closeLoading();
			});
		}
	};

	$scope.removeSignup = function(session){
		erSvc.loadingDialog('Removing Session');
		dataSvc.deleteSignup($scope.eventId, $scope.selectedRegs[0].id, session.selectedSection).then(function(){
			delete session.selectedSection;
			getSignups();
		});
	};

	$scope.modifyEmailRecipientCount = () => {
		let flds = $scope.emailRecipients.filter(f => f.selected).map(f => f.fld);
		let recipientCount = 0;
		$scope.selectedRegs.forEach(reg => {
			flds.forEach(fld => {
				if(reg[fld]) recipientCount++;
			});
		});
		$("input#to").val((recipientCount) + " recipient(s)");
		$('#send_email').prop('disabled', recipientCount == 0);
	};

	$scope.send_email = function(){
		tinyMCE.triggerSave();
		erSvc.loadingDialog();
		let body = $('#body_text').val();
		//record email sent
		let emailList = [];
		let host = window.location.origin;
		let slug = $scope.selectedEvent.slug;
		let eventUrl = `${host}/e/${$scope.accountid}/${slug}`;
		let emailData = {
			subject: $('#emailSubject').val(),
			replytoemail: $('#replytoemail').val()
		}
		angular.forEach($scope.selectedRegs,function(reg){
			let curEmails = [];
			let regUrl = `${host}/e/${$scope.accountid}/${slug}/register/${reg.confirmation}`;
			let signupUrl = `${host}/e/${$scope.accountid}/${slug}/signup/${reg.confirmation}`;
			let invoiceUrl = `${host}/events/invoice.php?eventid=${reg.eventid}&confirmation=${reg.confirmation}`;
			let msg = body.replaceAll("[eventurl]",`<a href="${eventUrl}">${eventUrl}</a>`);
			msg = msg.replaceAll("[registrationurl]", `<a href="${regUrl}">${regUrl}</a>`);
			msg = msg.replaceAll("[sessionsignupurl]", `<a href="${signupUrl}">${signupUrl}</a>`);
			msg = msg.replaceAll("[invoiceurl]", `<a href="${invoiceUrl}">${invoiceUrl}</a>`);
			msg = msg.replaceAll("[confirmation]", reg.confirmation);
			$scope.emailRecipients.filter(f => f.selected).forEach(eml => {
				emailList.push(reg[eml.fld]);
				curEmails.push(reg[eml.fld]);
			});
			emailData.address = curEmails.join(',');
			emailData.body = msg;
			erSvc.closeLoading();
			$.ajax({ // POST + CSRF header; send_email_simple.php removed
				url: "/send_email.php",
				method: "POST",
				data: emailData,
				headers: {"X-CSRF-Token": (window.erGetCsrfToken ? window.erGetCsrfToken() : '')}
			}).always(function(){
				$("div#email_dialog").modal("hide");
				erSvc.closeLoading();
			});
		});

		let emailSentData = {
			"accountid": $scope.accountid,
			"eventid":$scope.eventId,
			"author":erSessionData.userData.first_name + ' ' + erSessionData.userData.last_name,
			"subject": $scope.emailSubject,
			"message": $('#body_text').val(),
			"recipients":emailList.toString()
		};
		dataSvc.createOrUpdateRecord({"table":"emails_sent","record":emailSentData},$scope.eventId);
		
	};

	$scope.checkout = function(){
		angular.forEach($scope.selectedRegs,function(registration){
			registration.checkin = '';
			registration.checkin_user = '';
			dataSvc.checkoutAttendee(registration.id, registration.eventid);
		});
		erSvc.easyRegAlert({"text":"Attendee Checked Out","title":"Success"}, true);
	};

	$scope.delete_click = function(){
		let title = "Confirm Cancellation";
		let text = "Please confirm that you would like to cancel this registration."
		erSvc.easyRegConfirm({"text":text,"title":title},"Continue","Back").then(function(res){
			if(res) $scope.deleteRegistration();
		});
	};

	$scope.deleteRef_click = function(){
		let title = "Confirm Cancel/Refund";
		let text = "Please confirm that you would like to cancel this registration and zero the balance due."
		text += "This will refund any payments made by the attendee and set the balance due to $0.00.";
		erSvc.easyRegConfirm({"text":text,"title":title},"Continue","Cancel")
		.then(function(res){
			if(res){
				$scope.deleteRegistration();

				//refund any necessary payments and add credit
				let registration = $scope.selectedRegs[0];
				let paid = Number(registration.payments);
				let price = Number(registration.price);

				let submitData = {
					"registrationid":registration.id,
					"userid": $scope.userid,
					"note": "Admin cancellation and clearing of attendee balance.",
					"eventid": $scope.eventId
				};

				if(paid > 0){
					let refData = angular.copy(submitData);
					refData.amount = paid * -1;
					refData.payment_type = 'Refund';
					$http.get('/save_payment.php', {params:refData});
				}

				submitData.amount = price;
				submitData.payment_type = "Credit";
				$http.get('/save_payment.php', {params:submitData});

				registration.balance = 0;
				registration.payments = price;
			}
		});
	}

	$scope.deleteRegistration = function(){
		let registration = $scope.selectedRegs[0];
		registration.deleted = '1';
		registration.selected = false;
		$scope.activeRegistrations --;
		$scope.inactiveRegistrations ++;
		$scope.deleteClicked = false;
		dataSvc.updateRegActiveStatus($scope.eventId, registration.confirmation, "1");
	};

	$scope.undeleteRegistration = function(){
		let registration = $scope.selectedRegs[0];
		if(Number(registration.payments) > 0){
			let queryParams = {
				"query":"payments",
				"eventid":$scope.eventId,
				"confirmation":$scope.selectedRegs[0].confirmation
			};
			dataSvc.getArray(queryParams).then(function(response){
				let credits = 0;
				let creditPayments = response.filter(r => r.payment_type == 'Credit');
				creditPayments.forEach(pymt => credits += Number(pymt.amount));
				if(credits > 0){
					credits = $filter('currency')(credits);
					msg = `There is a ${credits} credit for this registration. <br/>
						Would you like to remove this credit?`
					erSvc.easyRegConfirm({"text":msg,"title":"Remove Credits?"},"Yes - Remove Credits","No - LeaveCredits").then(function(res){
						if(!res) return;
						let paymentsDeleted = 0;
						creditPayments.forEach(function(pymt){
							dataSvc.deleteRecord({"table":"registration_payments","id":pymt.id},$scope.eventId)
							.then(function(res){
								if(++paymentsDeleted == creditPayments.length) window.location.reload();
							});
						});
					});
				}
			});
		}
		registration.deleted = '0';
		$scope.activeRegistrations ++;
		$scope.inactiveRegistrations --;
		dataSvc.updateRegActiveStatus($scope.eventId, registration.confirmation, "0");
	};

	$scope.getEventRegTypes = function(){
		dataSvc.getArray({"query":"registrationTypes","eventid":$scope.switchEvent.id})
		.then(function(data){
			$scope.switchRegTypes = data;
		});
	};

	$scope.processEvtSwitch = function(){
		erSvc.loadingDialog();
		dataSvc.getConfirmation($scope.editReg, $scope.switchEvent.id).then(function(conf){
			let updateRec = {
				"id":$scope.editReg.id,
				"eventid":  $scope.switchEvent.id,
				"registration_typeid": $scope.switchRegType
			};
			dataSvc.createOrUpdateRecord({"table":"registrations","record":updateRec}).then(function(res){
				erSvc.closeLoading();
				let txt = `This registration event has been switched to ${$scope.switchEvent.name}.<br/>`;
				erSvc.easyRegAlert({"text":txt,"title":"Registration Event Switch Successful"});
				$scope.closeRightDialog();
				$scope.eventRegistrations = $scope.eventRegistrations.filter(r => r.id != $scope.editReg.id);
				$scope.$applyAsync();
			});
		});
	};

	$scope.prepPaymentEdit = function(payment){
		payment.editing = true;
		$scope.editPayment = angular.copy(payment);
		$scope.selectedPayment = payment;
	};

	$scope.cancelPayment = function(){
		$scope.editPayment = null;
		$scope.selectedPayment.editing = false;
	}

	$scope.savePaymentEdit = function(){
		erSvc.loadingDialog();
		$scope.selectedPayment.entered_date = $scope.editPayment.entered_date;
		$scope.selectedPayment.payment_type = $scope.editPayment.payment_type;
		$scope.selectedPayment.entered_by = $scope.editPayment.entered_by;
		$scope.selectedPayment.note = $scope.editPayment.note;
		$scope.selectedPayment.ref_nbr = $scope.editPayment.ref_nbr;
		$scope.selectedPayment.amount = $scope.editPayment.amount;
		let updateData = {"table":"registration_payments","record":$scope.selectedPayment};
		dataSvc.createOrUpdateRecord(updateData, $scope.eventId).then(function(){
			window.location.reload();
		});
	};

	$scope.pymtError = function(reg){
		if(reg.payment_type == 'Refund') return false;
		return (reg.balance - reg.paymentTotal < 0);
	};

	$scope.closeRightDialog = function(){ $(".dialogRight").hide(500); };
});//end controller
