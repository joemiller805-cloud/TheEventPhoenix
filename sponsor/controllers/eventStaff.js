regApp.controller('evtStaffCtrl', function($scope, $http, accountid, dataSvc, erSvc, sponsorDataService) {
	sponsorDataService.getAllEventDetails().then(function(res){
		$scope.evtData = res.events;
		$scope.$apply();
	});

	$scope.staffAttendingEvt = (evt, staff) => erUtils.hasId(evt.eventStaff.map(s => s.userid), staff.id);

	$scope.changeStaffAtt = function(event, staff){
		erSvc.loadingDialog();
		if(!$scope.staffAttendingEvt(event, staff)) addStaffMember(event, staff);
		else removeStaffMember(event, staff);
	};

	$scope.disableStaffCheck = function(event,staff){
		if($scope.staffAttendingEvt(event, staff)) return false;
		else return event.eventStaff.length >= event.staffAllowance;
	};

	function addStaffMember(event, staff){
		var newReg = {
			"email":staff.email,
			"business":$scope.vendor.name,
			"first_name":staff.first_name,
			"last_name":staff.last_name,
			"address1":staff.address1,
			"address2":staff.address2,
			"city":staff.city,
			"state":staff.state,
			"zip":staff.zip,
			"phone":staff.phone,
			"userid":staff.id,
			"web_address":$scope.vendor.web_address,
			"registration_typeid": event.staff_reg_type_id
		};
		dataSvc.createOrUpdateRegistration(newReg, event.id).then(function(res){
			event.eventStaff.push(res);
			var emailData = 	{
				"confirmation":res.confirmation,
				"first_name":res.first_name,
				"last_name":res.last_name,
				"email":res.email,
				"eventid":event.id
			};
			$.post('/save_registration.php', emailData);
			erSvc.closeLoading();
			$scope.$apply();
		});
	}

	function removeStaffMember(event, staff){
		let reg = event.eventStaff.filter(s => s.userid == staff.id)[0];
		dataSvc.deleteRecord({"table":"registrations","id":reg.id}).then(function(res){
			event.eventStaff.splice(event.eventStaff.indexOf(reg),1);
			erSvc.closeLoading();
		});
	}
});
