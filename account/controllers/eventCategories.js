regApp.controller('eventCategories', function($scope, $http, $rootScope, $location, $q, dataSvc, erSvc){
	const accountid = erSessionData.accountid;
	$scope.archiveFilter = '0';
	let prefEntry = {"accountid":accountid,"name":"eventCategories"};
	$scope.categories = [];
	dataSvc.getPreferenceByName('eventCategories', accountid).then(function(res){
		if(res[0]){
			prefEntry = res[0];
			try{ $scope.categories = JSON.parse(prefEntry.value);}
			catch(e){ console.error(e); $scope.categories = []; }
		}
	});
	
	$scope.addCat = function(){
		$scope.categories.unshift("");
		$('table.striped td .form-control').first().focus();
	};

	$scope.removeCat = function(cat){
		$scope.categores = $scope.categories.splice($scope.categories.indexOf(cat),1);
	};	

	$scope.saveSettings = function(){
		$scope.categories = $scope.categories.sort();
		prefEntry.value = JSON.stringify($scope.categories);
		dataSvc.createOrUpdateRecord({"table":"preferences","record":prefEntry}).then(function(res){
			if(res != '0') prefEntry.id = res;
			erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"}, 3000);
		});
	};

	$scope.closeRightDialog = () => $('#catDialog').hide(500);
});
