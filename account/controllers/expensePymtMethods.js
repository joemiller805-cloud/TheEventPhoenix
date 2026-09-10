regApp.controller('expPymtMethods', function($scope, $http, $rootScope, $location, $q, dataSvc, erSvc){
	let accountid = erSessionData.accountid;
	$scope.methods = [];
	let prefEntry = {"accountid":accountid,"name":"expensePymtMethods"};
	dataSvc.getPreferenceByName('expensePymtMethods', accountid).then(function(res){
		if(res[0]){
			prefEntry = res[0];
			try{ $scope.methods = JSON.parse(prefEntry.value);}
			catch(e){ console.error(e); $scope.methods = []; }
		} 
	});

	$scope.newMethod = () => $scope.methods.push('Method ' + ($scope.methods.length + 1));
	$scope.removeMethod = (idx) => $scope.methods.splice(idx,1);

	$scope.save = function(){
		prefEntry.value = JSON.stringify($scope.methods);
		dataSvc.createOrUpdateRecord({"table":"preferences","record":prefEntry}).then(function(res){
			if(res != '0') prefEntry.id = res;
			erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"}, 3000);
		});
	};

});
