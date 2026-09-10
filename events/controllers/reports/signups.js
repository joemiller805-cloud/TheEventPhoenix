regApp.controller('rptSignups', function($scope, $http, $q, dataSvc, erSvc, $filter, $timeout) {
	erSvc.loadingDialog();
	let eventid = erSessionData.curEvent.id;
	let signupsRetrieved = $q.defer();
	let regsRetrieved = $q.defer();
	let fieldsRetrieved = $q.defer();
	$scope.columns =  [
		{fld:"session", label:"Session"},
		{fld:"date", label:"Date"},
		{fld:"course", label:"Course"},
		{fld:"room", label:"Room"},
		{fld:"presenter", label:"Presenter"}
	];
	let signups = [];
	$scope.cols = [];
	dataSvc.getArray({"query":"eventSignups","eventid":eventid}).then(function(resp){
		signups = resp;
		erSvc.closeLoading();
		signupsRetrieved.resolve();
	});

	dataSvc.getRegistrations(eventid).then(function(regs){
		$scope.registrations = regs;
		regsRetrieved.resolve();
	});

	dataSvc.getRegFields(erSessionData.accountid, eventid).then(flds => {
		$scope.attendeeFields = flds;
		let attFields = Object.values(flds).map(f => {
			return {fld:f.field,label:f.label,options:f.options};
		});
		$scope.columns = [...attFields, ...$scope.columns];
		fieldsRetrieved.resolve();
	});

	$q.all([signupsRetrieved.promise, regsRetrieved.promise, fieldsRetrieved.promise]).then(function(){
		signups.forEach(function(signup){
			signup.searchString = erSvc.getObjectSearchString(signup);
			let attendee = $scope.registrations[signup.registrationid];
			Object.values($scope.attendeeFields).forEach(r => {
				if(attendee){
					signup[r.field] = attendee[r.field];
					if(r.options && r.options.filter(opt => opt !="").length){
						signup[r.field] = r.options[attendee[r.field]];
					} 
				} 
			});
			let reg = $scope.registrations[signup.registrationid]
			if(!reg) return;
			for(key in reg){
				if(reg[key]) signup.searchString += String(reg[key]).toLowerCase();
			}
		});
		$scope.signups = signups;
	});
});// End Controller