regApp.controller('rptSessions', function($scope, $http, dataSvc, erSvc, $filter, $timeout) {
	erSvc.loadingDialog();
	var eventid = erSessionData.curEvent.id;
	$scope.accountid = erSessionData.accountid;
	dataSvc.getArray({"query":"eventSessionsExpanded","eventid":eventid}).then(function(resp){
		resp.sort(function(a, b){
			return a.dtTime - b.dtTime;
		});
		$scope.sessions = resp;
		angular.forEach($scope.sessions,function(session){
			if(session.web_link){
				if(session.web_link.indexOf('http') < 0) session.web_link = "https://" + session.web_link;
			}
			session.include = true;
			if(!session.presenter) session.presenter = 'No Presenter Assigned';
			session.searchString = erSvc.getObjectSearchString(session);
		});
		erSvc.closeLoading();
		erSvc.initializeColumns($('#sessionTable'));
	});

	let signupsBySection = {};
	dataSvc.getArray({'query':'sectionSignups',"eventid":eventid})
	.then(function(resp){
		angular.forEach(resp,function(signup){
			if(!signupsBySection[signup.sectionid]) signupsBySection[signup.sectionid] = [];
			signupsBySection[signup.sectionid].push(signup);
		});
	});

	// Sorting
	$scope.changeSort = function(fld, type){
		$scope.sortType = type;
		$scope.$apply(function(){
			if($scope.sortField == fld) $scope.sortDesc = !$scope.sortDesc;
			else $scope.sortDesc = false;
			$scope.sortField = fld;
		});
	};

	$scope.sortField = "course";
	$scope.sortDesc = false;
	$('#sessionTable th').addClass('pointer');
	$('#sessionTable th').click(function(){
		$scope.changeSort($(this).attr('data-sort'), $(this).attr('data-sort-type'));
	});

	$scope.sortVal = function(session){
		if($scope.sortType == 'integer'){
			if(isNaN(parseFloat(session[$scope.sortField]))) return 9999;
			return parseFloat(session[$scope.sortField]);
		} 
		else if ($scope.sortType == 'date'){
			return erSvc.localToMySqlDate(session[$scope.sortField]);
		}
		return session[$scope.sortField];
	};

	$scope.quickSearch = function(){
		var compareValue = $scope.quickSearchValue.toLowerCase();
		angular.forEach($scope.sessions,function(sess){
			sess.include = sess.searchString.indexOf(compareValue) >= 0;
		});
		$timeout(function(){ erSvc.initializeColumns($('#sessionTable')) },1);
	}

	$scope.columnFilter = () => erSvc.columnFilter($('#sessionTable'));

	$scope.getAttendees = function(section){
		$scope.sectionAttendees = signupsBySection[section.sectionid];
		$scope.attendeeDialogTitle = section.course + ' - ' + section.session;
		$('#attendeeDialog').show(500);
	};

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);

	$scope.getAttendeeConfirmations = function(session){
		if(!signupsBySection[session.sectionid]) return '';
		return signupsBySection[session.sectionid].map(s => s.confirmation).toString();
	};
});// End Controller