regApp.controller('sectionAttendance', function($scope, $http, $timeout, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Section Attendance")').addClass('active');
	}, 100);
	$scope.accountid = erSessionData.accountid;
	var eventid = erSessionData.curEvent.id;
	dataSvc.getArray({"query":"eventSections","eventid":eventid}).then(function(resp){
		$scope.sortField = 'starttime';
		$scope.sessions = resp;	
		angular.forEach($scope.sessions,function(session){
			session.show = true;
			session.searchString = erSvc.getObjectSearchString(session, ['course','room','session']);
			if(Number(session.capacityRm)) session.capacityRm = Number(session.capacityRm);
		});
	});

	$scope.updateAttendance = function(session){
		let payload = {id:session.id, attendance:session.attendance};
		dataSvc.createOrUpdateRecord({"table":"sections","record":payload}).then(r => {
			session.updated = true;
			$timeout(function(){ session.updated = false; }, 2000);
		});
	};

	$scope.changeSort = function(fld){		
		if($scope.sortField == fld) $scope.sortDesc = !$scope.sortDesc;
		else $scope.sortDesc = false;
		$scope.sortField = fld;
	};

	$scope.searchSessions = function(){
		let searchStr = $scope.searchVal.toLowerCase();
		$scope.sessions.forEach(s => s.show = (!searchStr || s.searchString.includes(searchStr)));
	};
});//End Controller