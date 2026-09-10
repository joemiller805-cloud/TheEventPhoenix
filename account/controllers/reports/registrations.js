regApp.controller('registrationsRpt', function($scope, dataSvc) {
	$scope.records = [];
	$scope.filteredRecs = [];
	$scope.events = {};
	$scope.allEvents = true;
	$scope.columns = [
		{fld:"last_name",label:"Last Name"},
		{fld:"first_name",label:"First Name"},
		{fld:"email",label:"Email"},
		{fld:"business",label:"Business"},
		{fld:"title",label:"Title"},
		{fld:"address1",label:"Address 1"},
		{fld:"address2",label:"Address 2"},
		{fld:"city",label:"City"},
		{fld:"state",label:"State"},
		{fld:"zip",label:"Zip"},
		{fld:"phone",label:"Phone"},
		{fld:"confirmation",label:"Confirmation"},
		{fld:"deleted",label:"Deleted"},
		{fld:"payment_method",label:"Payment Method"},
		{fld:"payment_number",label:"Payment Number"},
		{fld:"web_address",label:"Web Address"},
		{fld:"event",label:"Event"},
		{fld:"eventYear",label:"Event Year",dataType:"number"},
		{fld:"registrationType",label:"Registration Type"}
	];

	const eventFilterReady = () => Object.keys($scope.events).length > 0;
	const sortByLastName = records => records.sort((a, b) => {
		const lastNameA = (a.last_name || '').toLowerCase();
		const lastNameB = (b.last_name || '').toLowerCase();
		if(lastNameA < lastNameB) return -1;
		if(lastNameA > lastNameB) return 1;
		return 0;
	});

	$scope.filterRecs = () => {
		if(!$scope.records) return;
		$scope.filteredRecs = sortByLastName($scope.records.filter(rec => {
			return !eventFilterReady() || ($scope.events[rec.eventid] && $scope.events[rec.eventid].show);
		}));
		$scope.allEvents = Object.values($scope.events).every(evt => evt.show);
	};

	dataSvc.getArray({'query':'registrationsRpt'}).then(resp => {
		$scope.records = resp;
		$scope.filterRecs();
	});

	dataSvc.getObject({'query':'accountEvents'}).then(resp => {
		$scope.events = resp;
		angular.forEach($scope.events, evt => evt.show = true);
		$scope.filterRecs();
	});

	$scope.setAllEvents = () => {
		angular.forEach($scope.events, evt => evt.show = $scope.allEvents);
		$scope.filterRecs();
	};

	$scope.showEvents = () => {
		$('#eventDialog').show(500);
	};

	$scope.closeRightDialog = () => {
		$('.dialogRight').hide(500);
	};
});//end controller
