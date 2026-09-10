regApp.controller('page', function($scope, $http, $rootScope, $location, $routeParams, $timeout, dataSvc, erSvc) {
	var pageid = $routeParams.id;
	$scope.eventid = erSessionData.curEvent.id;
	$scope.page = {"eventid": $scope.eventid,"content":""};

	if(pageid > 0){
		dataSvc.getTableRecords('pages', 'id = ' + pageid, false, $scope.eventid).then(function(res){
			$scope.page = res[0];
			$timeout(function(){
				erSvc.initTinyMce('content_text');
			}, 10);
			
		});
	}else{
		erSvc.initTinyMce('content_text');
	}

	$scope.nameChange = function(){
		$scope.page.slug = 'evt_' + string_to_slug($scope.page.name);
	};

	$scope.savePage = function(){
		tinyMCE.triggerSave();
		erSvc.loadingDialog();
		$scope.page.content = tinyMCE.get('content_text').getContent();
		dataSvc.createOrUpdateRecord({"table":"pages","record":$scope.page},$scope.eventid).then(function(res){
			erSvc.closeLoading();
			if(!$scope.page.id) $location.path('/event_details'); 
			erSvc.setSelectedEvent($scope.eventid).then(function(){
				erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"},true);
				erSvc.closeLoading();
			});
		});
	};

	$scope.deletePage = function(){
		dataSvc.deleteRecord({"table":"pages","id":$scope.page.id},$scope.eventid).then(function(){
			erSvc.setSelectedEvent($scope.eventid).then(function(){
				erSvc.easyRegAlert({"text":"Page Deleted","title":"Success"},true);
				setTimeout(function(){
					$location.path('/event_details');
					$scope.$apply();
				}, 5);
			});
		});
	};

	//update session data page list
	function updatePageList(){
		erSvc.setSelectedEvent($scope.eventid).then();
		dataSvc.getArray({'query':'eventPagesFromId','eventid':$scope.eventid}).then(function(resp){
			$http({
				"url": '/setUserData.php',
				"method": 'POST',
				"data": $.param({"navPages":resp}),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
			}).then(function(){
				window.location = "/events/manage_event_details.php?eventid=" + $scope.eventid;
			});
		});
	}

	$scope.goBack = function(){
		history.back();
	};
}); //End Controller

