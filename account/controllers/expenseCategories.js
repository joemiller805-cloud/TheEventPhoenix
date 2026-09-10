regApp.controller('expenseCatMgmt', function($scope, $http, $rootScope, $location, $q, dataSvc, erSvc){
	let accountid = erSessionData.accountid;
	$scope.archiveFilter = '0';
	dataSvc.getObject({'query':'expenseCategories'}).then(function(resp){
		$scope.categories = resp;
	});

	$scope.newCat = function(){
		$scope.editCat = {"accountid":accountid,"archived":'0'};
		$('#catDialog').show(500);
	};

	$scope.editCategory = function(cat){
		$scope.editCat = angular.copy(cat);
		$('#catDialog').show(500);
	};

	$scope.saveCat = function(){
		erSvc.loadingDialog();
		if(!$scope.editCat.name){
			erSvc.easyRegAlert({"text":"Please enter a category name","title":"Error"});
			erSvc.closeLoading();
			return;
		};

		dataSvc.createOrUpdateRecord({"table":"expense_categories","record":$scope.editCat}).then(function(res){
			if(!$scope.editCat.id){
				$scope.editCat.id = res;
				$scope.categories[res] = angular.copy($scope.editCat);
			}else{
				$scope.categories[$scope.editCat.id] = $scope.editCat;
			}
			$scope.closeRightDialog();
			erSvc.closeLoading();
			$scope.$applyAsync();
		});
	}

	$scope.closeRightDialog = () => $('#catDialog').hide(500);
});