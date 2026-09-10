regApp.controller('attendeeMenu', function($scope, $http, dataSvc, erSvc) {
	let accountid = erSessionData.accountid;

	let prefId;
	if($scope.acctType == 'events'){
		$scope.menuItems = {
			"Event Home":{"label":"Event Home","disabled":false,},
			"Contact":{"label":"Contact","disabled":false},
			"Register":{"label":"Register","disabled":false},
			"Manage Registration":{"label":"Manage Registration","disabled":false},
			"Event Program":{"label":"Event Program","disabled":false},
			"My Schedule":{"label":"My Schedule","disabled":false},
			"My Vendor Info":{"label":"My Vendor Info","disabled":false},
			"Courses Offered":{"label":"Courses Offered","disabled":false},
			"Event Tickets":{"label":"Event Tickets","disabled":false},
			"Documents":{"label":"Documents","disabled":false}
		};
	}else{
		$scope.menuItems = {
			"Event Home":{"label":"Event Home","disabled":false,},
			"Contact":{"label":"Contact","disabled":false},
			"Register":{"label":"Register","disabled":false},
			"Manage Registration":{"label":"Manage Registration","disabled":false},
			"Event Tickets":{"label":"Event Tickets","disabled":false}
		};
	}

	$scope.allowDisable = function(item){
		return !['Register','Manage Registration'].includes(item);
	};

	let defaultItems = angular.copy($scope.menuItems);

	dataSvc.getPreferenceByName('attendeeMenu', accountid)
		.then(function(res){
		if(res[0]){
			try{ 
				$scope.menuItems = angular.fromJson(res[0].value); 
				prefId = res[0].id;
			}catch(e){}
		}
		for(key in defaultItems){
			if(!$scope.menuItems[key]) $scope.menuItems[key] = defaultItems[key];
		}
	});

	$scope.saveSettings = function(){
		erSvc.loadingDialog();
		let rec = {
			"name":"attendeeMenu",
			"value":angular.toJson($scope.menuItems),
			"accountid":accountid,
			"id":prefId
		};
		dataSvc.createOrUpdateRecord({"table":"preferences","record":rec}).then(function(res){
			if(!prefId) prefId = res;
			erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"});
			erSvc.closeLoading();
		});
	};
	
});//end controller
