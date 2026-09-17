regApp.controller('accountRegistrations', function($scope, $http, $q, $filter, dataSvc, erSvc) {
	$("a.flash").click(function(){
		$("div#body, textarea#body_text").append($(this).attr("link"));
	});
	$("div#body").wysiwyg();
	$('div#body').cleanHtml();
	let accountid = erSessionData.accountid;
	$scope.dietary_restrictions = erSvc.getDietaryRestrictions();
	$scope.deleteClicked = false;
	$scope.sortField = 'last_name';
	$scope.sortReverse = false;
	$scope.selectedEvent = 'recent';
	$scope.userid = erSessionData.userid;
	$scope.selectedRegistrations = [];
	$scope.showActions = false;
	$scope.columns =  [
		{"fld":"event","label":"Event"},
		{"fld":"confirmation","label":"Confirmation"},
		{"fld":"email","label":"Email"},
		{"fld":"first_name","label":"First"},
		{"fld":"last_name","label":"Last"},
		{"fld":"business","label":"District"},
		{"fld":"address1","label":"Address 1"},
		{"fld":"address2","label":"Address 2"},
		{"fld":"city","label":"City"},
		{"fld":"state","label":"State"},
		{"fld":"zip","label":"Zip"},
		{"fld":"registration_type","label":"Registration Type"},
		{"fld":"registration_date","label":"Registration Date",dataType:'date'},
		{"fld":"registration_time","label":"Registration Time"},
		{"fld":"discount_code","label":"Discount Code"},
		{"fld":"price","label":"Price",dataType:"number",filter:"currency"},
		{"fld":"serviceFee","label":"Svc. Fee",dataType:"number",filter:"currency"},
		{"fld":"cc_fees","label":"CC Fee",dataType:"number",filter:"currency"},
		{"fld":"discount","label":"Discount",dataType:"number",filter:"currency"},
		{"fld":"payments","label":"Payments",dataType:"number",filter:"currency"},
		{"fld":"balance","label":"Balance",dataType:"number",filter:"currency"},
		{"fld":"payment_method","label":"Pymt. Method"},
		{"fld":"payment_number","label":"Pymt. Number"},
		{"fld":"checkin","label":"Checked In"},
		{"fld":"checkin_user","label":"Checked In By"},
		{"fld":"dietary_restrictions","label":"Dietary Restrictions"},
		{"fld":"deleted","label":"Canceled",dataType:"boolean"}
	];
	$scope.emailRecipients = [{fld:"email",label:"Attendee",selected:true}];
	
	$scope.events = [];
	let slugMap = {};
	dataSvc.getObject({'query':'accountEvents'}).then(res => {
		$scope.events = Object.values(res).filter(e => e.archived != '1');
		Object.values(res).forEach(e => slugMap[e.id] = e.slug);
	});
	$scope.replytoemails = [];
	dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
		if(resp[0] && !$scope.replytoemails.includes(resp[0].email)) $scope.replytoemails.push(resp[0].email);
	});
	dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
		if(resp[0] && !$scope.replytoemails.includes(resp[0].email)) $scope.replytoemails.push(resp[0].email);
	});
	let allRegistrations = [];
	$scope.getRegistrations = function(){
		allRegistrations = [];
		$scope.activeRegistrations = 0;
		$scope.inactiveRegistrations = 0;
		erSvc.loadingDialog("Retrieving Registrations");
		$scope.registrations = [];
		let callParams = [$scope.selectedEvent];
		if(isNaN($scope.selectedEvent) || $scope.selectedEvent == '') callParams = 
			['', '', '', '', '', $scope.selectedEvent, '','',true];
		$scope.eventRegistrations = {};
		dataSvc.getRegistrations(...callParams).then(function(response){
			let numFields = ['price','serviceFee','cc_fees','discount','payments','balance'];
			angular.forEach(response,function(reg){
				numFields.forEach(f => reg[f] = Number(reg[f]));
				if(reg.deleted == '1') $scope.inactiveRegistrations ++;
				else $scope.activeRegistrations++;
				reg.registration_date = $filter('mySqlToLocalDate')(reg.create_date);
				reg.registration_time = $filter('mySqlToLocalTime')(reg.create_date);
				$scope.eventRegistrations[reg.id] = reg;
				reg.selected = false;
				allRegistrations.push(reg);
			});
			setTimeout(erSvc.closeLoading, 300);
			$scope.includeCancelled = $scope.includeCancelled ?? false;
			filterCancelled();
			$scope.$watch('registrations', () => {
				$scope.selectedRegistrations = $scope.registrations.filter(r => r.selected);
			},true);
			$scope.$applyAsync();
		});
	};// End getRegistrations();
	
	dataSvc.getTableRecords('registration_fields', `accountid = ${accountid} AND archived != '1'` , true)
		.then(function(fields){
		Object.values(fields).forEach(fld => {
			let rec = {"fld":fld.label.replaceAll(' ','_'),"label":fld.label};
			$scope.columns.push(rec);
			if(fld.type == 'e') $scope.emailRecipients.push({...rec,selected:false});
		});		
		$scope.getRegistrations();
	});

	$scope.$watch('includeCancelled',filterCancelled);

	function filterCancelled(){
		if(!$scope.registrations) return;
		$scope.registrations = allRegistrations.filter
			(r => r.deleted == '0' || $scope.includeCancelled);
	}

	$scope.addPayment = function(){
		$scope.dialogTitle = "Enter Payment(s)";
		$('#paymentDiv').show(500);
	};
	$scope.validatePaymtAmt = registration => {
		if(!registration) return false;

		const paymentAmount = Number(registration.paymentTotal || 0);
		const balance = Number(registration.balance || 0);

		registration.paymentTotal = Number.isFinite(paymentAmount) ? paymentAmount : 0;
		return balance - registration.paymentTotal < 0;
	};
	$scope.savePayment = function(){
		var regisration, submitData;
		angular.forEach($('.paymentForm'),function(payment){
			registration = $scope.eventRegistrations[$(payment).attr('data-regId')];
			submitData = {
				"registrationid":registration.id,
				"userid": $scope.userid,
				"amount": registration.paymentTotal,
				"payment_type":registration.payment_type,
				"ref_nbr": registration.ref_nbr,
				"note": registration.pymtNote
			}
			registration.balance = registration.balance - registration.paymentTotal;
			registration.payments = parseFloat(registration.payments) + parseFloat(registration.paymentTotal);
			$http.get('/save_payment.php', {params:submitData});
		});
		$("#paymentDiv").hide(500);
	};
	$scope.cancelPayment = () => $("#paymentDiv").hide(500);
	$scope.manage_payments = function(){
		var queryParams = {
			"query":"payments",
			"eventid":$scope.selectedRegistrations[0].eventid,
			"confirmation":$scope.selectedRegistrations[0].confirmation
		};
		dataSvc.getArray(queryParams).then(function(response){
			$scope.payments = response;
			$scope.regPayments = 0;
			angular.forEach($scope.payments, pymt => $scope.regPayments += parseFloat(pymt.amount));
			$scope.dialogTitle = "Payments";
			$('#paymentsDialog').show(500);
			$scope.$applyAsync();
		});
	};
	$scope.generate_invoice = function(){
		let events ={};
		$scope.selectedRegistrations.forEach(r => {
			if(!events[r.eventid]) events[r.eventid] = [];
			events[r.eventid].push(r.confirmation);
		});
		if(Object.keys(events).length > 1){
			let msg = `
				Multiple events have been selected.  Invoices for each event will open in a new tab.
				You may need to approve multiple tabs in your browser.
			`;
			erSvc.easyRegConfirm({"text":msg,"title":"Multiple Events Selected"},"Continue","Cancel")
			.then(res => { if(res) openInvoices() });
		}else{
			openInvoices();
		}
		
		function openInvoices(){
			for(eventid in events){
				window.open("/events/invoice.php?eventid=" + eventid + "&confirmation=" + events[eventid].toString());
			}
		}
	};
	$scope.generate_certificate = function(){
		var registrations = $scope.selectedRegistrations.map(r => r.confirmation);
		let f = $("<form target='_blank' method='POST' style='display:none;'></form>")
			.attr({action: '/certificate.php'}).appendTo(document.body);
		$('<input type="hidden" />').attr({
			name: 'confirmation',
			value: registrations.toString()
		}).appendTo(f);
		f.submit().remove();
	};
	$scope.generate_badge = function(){
		var registrations = [];
		angular.forEach($scope.selectedRegistrations, reg => registrations.push(reg.confirmation));
		window.open("/events/badge.php?confirmation=" + registrations.toString());
	};
	$scope.checkin_user = () => $('#checkinDialog').show(500);
	$scope.completeCheckin = function(){
		erSvc.loadingDialog('Checking Attendee In.');
		dataSvc.checkinAttendee($scope.selectedRegistrations[0].id, $scope.userid).then(function(resp){
			$scope.selectedRegistrations[0].checkin = 'This Session';
			$scope.selectedRegistrations[0].checkin_user = 'You';
			erSvc.closeLoading();
			$scope.closeRightDialog();
		});
	};
	$scope.edit_registration = function(func){
		// func - edit (edit registration) or sched (edit schedule)
		let eventid = $scope.selectedRegistrations[0].eventid;
		let confirmation =  $scope.selectedRegistrations[0].confirmation;
		window.open(`/admin.php#!/registrations/${eventid}/${confirmation}/${func}`);
	};
	$scope.generate_email = function(){
		erSvc.initTinyMce('body_text', true);
		$scope.modifyEmailRecipientCount();
	};

	$scope.modifyEmailRecipientCount = () => {
		let flds = $scope.emailRecipients.filter(f => f.selected).map(f => f.fld);
		let emails = new Set();
		$scope.selectedRegistrations.forEach(reg => {
			flds.forEach(fld => { if(reg[fld]) emails.add(reg[fld]); });
		});
		$("input#to").val(emails.size + " recipient(s)");
		$('#send_email').prop('disabled', emails.size == 0);
	};
	$scope.send_email = function(){
		tinyMCE.triggerSave();
		erSvc.loadingDialog();
		let body = $('#body_text').val();
		//record email sent
		let emailList = new Set();
		let host = window.location.origin;
		let emailData = {
			subject: $('#emailSubject').val(),
			replytoemail: $('#replytoemail').val()
		}
		angular.forEach($scope.selectedRegistrations,function(reg){
			let curEmails = new Set();
			let slug = slugMap[reg.eventid];
			let eventUrl = `${host}/e/${accountid}/${slug}`;
			let regUrl = `${host}/e/${accountid}/${slug}/register/${reg.confirmation}`;
			let signupUrl = `${host}/e/${accountid}/${slug}/signup/${reg.confirmation}`;
			let invoiceUrl = `${host}/events/invoice.php?eventid=${reg.eventid}&confirmation=${reg.confirmation}`;
			let msg = body.replaceAll("[eventurl]",`<a href="${eventUrl}">${eventUrl}</a>`);
			msg = msg.replaceAll("[registrationurl]", `<a href="${regUrl}">${regUrl}</a>`);
			msg = msg.replaceAll("[sessionsignupurl]", `<a href="${signupUrl}">${signupUrl}</a>`);
			msg = msg.replaceAll("[invoiceurl]", `<a href="${invoiceUrl}">${invoiceUrl}</a>`);
			msg = msg.replaceAll("[confirmation]", reg.confirmation);
			$scope.emailRecipients.filter(f => f.selected).forEach(eml => {
				if(reg[eml.fld]){
					emailList.add(reg[eml.fld]);
					curEmails.add(reg[eml.fld]);
				} 
			});
			emailData.address = Array.from(curEmails).join(',');
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
			"accountid": accountid,
			"author":erSessionData.userData.first_name + ' ' + erSessionData.userData.last_name,
			"subject": $('#emailSubject').val(),
			"message": $('#body_text').val(),
			"recipients":Array.from(emailList).toString()
		};
		dataSvc.createOrUpdateRecord({"table":"emails_sent","record":emailSentData});		
	};
	$scope.checkout = function(){
		angular.forEach($scope.selectedRegistrations,function(registration){
			registration.checkin = '';
			registration.checkin_user = '';
			dataSvc.checkoutAttendee(registration.id, registration.eventid);
		});
		erSvc.easyRegAlert({"text":"Attendee Checked Out","title":"Success"}, true);
	};
	$scope.delete_click = function(){
		$scope.deleteClicked = true;
		$("button#delete, button#deleteyes, button#deleteno").toggle();
	};
	$scope.deleteRegistration = function(){
		var eventid = $scope.selectedRegistrations[0].eventid;
		angular.forEach($scope.selectedRegistrations,function(registration){
			registration.deleted = '1';
			registration.selected = false;
			$scope.deleteClicked = false;
			dataSvc.updateRegActiveStatus(eventid, registration.confirmation, "1");
		});
	};
	$scope.closeRightDialog = () => $(".dialogRight").hide(500);
});//end controller
