regApp.controller('paymentsRpt', function($scope, $http, $q, dataSvc, erSvc, dateService) {
	var attendeePymtsRetrieved = $q.defer();
	var vendorPymtsRetrieved = $q.defer();
	var videoPymtsRetrieved = $q.defer();
	let attendeePayments = [];
	let vendorPayments = [];
	let videoPayments = [];

	dataSvc.getArray({'query':'accountAttendeePayments'}).then(function(resp){
		attendeePayments = resp;
		attendeePayments.forEach(pymt => pymt.type = "Attendee");
		attendeePymtsRetrieved.resolve();
	});
	
	dataSvc.getArray({'query':'accountVendorPayments'}).then(function(resp){
		vendorPayments = resp;
		vendorPayments.forEach(pymt => pymt.type = "Vendor");
		vendorPymtsRetrieved.resolve();
	});
	
	dataSvc.getArray({'query':'accountVideoPayments'}).then(function(resp){
		videoPayments = resp;
		videoPayments.forEach(pymt => pymt.type = "Video");
		videoPymtsRetrieved.resolve();
	});

	$q.all([attendeePymtsRetrieved.promise,vendorPymtsRetrieved.promise,videoPymtsRetrieved.promise]).then(() =>{
		$scope.payments = [...attendeePayments, ... vendorPayments, ...videoPayments];
	});

	$scope.columns =  [
		{fld:"type",label:"Type"},
		{fld:"entered_date",label:"Date",dataType:'date'},
		{fld:"event",label:"Event"},
		{fld:"acct_code",label:"Account Code"},
		{fld:"last_name",label:"Last Name"},
		{fld:"first_name",label:"First Name"},
		{fld:"confirmation",label:"Confirmation #"},
		{fld:"email",label:"Email"},
		{fld:"business",label:"Business"},
		{fld:"payment_type",label:"Method"},
		{fld:"ref_nbr",label:"Ref #"},
		{fld:"note",label:"Notes"},
		{fld:"amount",label:"Amount",dataType:"number",filter:"currency",showTotal:true}
	];

});//end controller