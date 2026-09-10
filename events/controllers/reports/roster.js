regApp.controller('rptRoster', function($scope, $http, dataSvc, erSvc, $filter, $timeout, $q) {
	erSvc.loadingDialog();
	let eventid = erSessionData.curEvent.id;
	let accountid = erSessionData.accountid;
	erSvc.loadingDialog();
	let preLoad = [
		dataSvc.getRegistrations(eventid),
		dataSvc.getArray({'query':'eventSignups', eventid: eventid}),
		dataSvc.getEventDataFromId(eventid),
		dataSvc.getExtraRegFields(eventid)
	];

	$q.all(preLoad).then(([regs, signups, evtData, xtraFlds]) =>{
		erSvc.closeLoading();
		Object.values(regs).forEach(reg =>{
			reg.signups = [];
			reg.registration_date = $filter('mySqlToLocalDate')(reg.create_date);
			reg.registration_time = $filter('mySqlToLocalTime')(reg.create_date);
		});
		signups.forEach(su => {
			regs[Number(su.registrationid)]?.signups?.push(su.course)
		});
		regs = Object.values(regs).filter(r => r.deleted != 1);
		angular.forEach(regs, reg => reg.signups = reg.signups?.join('<br/>'));
		$scope.registrations = regs;
		$scope.eventData = evtData;
		$scope.extraFields = xtraFlds;
		xtraFlds.forEach(fld => $scope.columns.push({fld:fld.field,label:fld.label}));
		$scope.columns.push({fld:'signups',label:'Courses'})
		//TO DO - export disabled if no selection
		erSvc.closeLoading();
	});

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
		{fld:"signupCount",label:"Signup Count"}
	];
});// End Controller