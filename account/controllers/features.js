regApp.controller('acctFeatures', function($scope, $rootScope, $http, dataSvc, erSvc) {
	$scope.account = {};
	dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
		$scope.account = resp[0];
		$scope.accountid = $scope.account.id;
		dataSvc.accountid = $scope.accountid;
		
		$scope.account.enable_survey = $scope.account.enable_survey == '1';
		$scope.account.enable_docs = $scope.account.enable_docs == '1';
		$scope.account.enable_course_proposals = $scope.account.enable_course_proposals == '1';
		$scope.account.enable_evt_requests = $scope.account.enable_evt_requests == '1';
		$scope.account.enable_staff_expense = $scope.account.enable_staff_expense == '1';
		$scope.account.sponsors_enabled = $scope.account.sponsors_enabled == '1';
		$scope.account.enable_videos = $scope.account.enable_videos == '1';
		$scope.account.enable_inventory = $scope.account.enable_inventory == '1';
		$scope.account.enable_season_pass = $scope.account.enable_season_pass == '1';
	});

	$scope.save = function(){
		erSvc.loadingDialog();
		dataSvc.createOrUpdateRecord({"table":"accounts","record":$scope.account}).then(function(){
			erSvc.easyRegAlert({"text":"Feature Settings Saved","title":"Success"},true);
			erSvc.closeLoading();
		});
		$rootScope.enable_course_proposals = $scope.account.enable_course_proposals;
		$rootScope.enable_docs = $scope.account.enable_docs;
		$rootScope.enable_evt_requests = $scope.account.enable_evt_requests;
		$rootScope.enable_staff_expense = $scope.account.enable_staff_expense;
		$rootScope.enable_survey = $scope.account.enable_survey;
		$rootScope.sponsors_enabled = $scope.account.sponsors_enabled;
		$rootScope.enable_videos = $scope.account.enable_videos;
		$rootScope.enable_season_pass = $scope.account.enable_season_pass;
	};
});//end controller