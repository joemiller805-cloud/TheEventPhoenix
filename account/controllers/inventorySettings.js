regApp.controller('inventorySettings', function($scope, $http, $q, dataSvc, erSvc) {
	dataSvc.accountid = erSessionData.accountid;
	let inventoryPrefId;
	let catFilter = 'accountid = ' + erSessionData.accountid + ' AND name =' +  "'inventorySettings'";
	dataSvc.getTableRecords('preferences',  catFilter).then(function(res){
		$scope.categories = [];
		$scope.locations = [];
		$scope.conditions = [];
		if(res[0]){
			try{
				let settings = angular.fromJson(res[0].value);
				$scope.categories = settings.categories;
				$scope.locations = settings.locations;
				$scope.conditions = settings.conditions;
				inventoryPrefId = res[0].id;
			}catch(e){}
		}
	});

	$scope.addLocation = () =>{
		erSvc.easyRegFeedback("New Location", `New Inventory Location`, "Create Location", "Cancel")
		.then((res) => {if(res)	$scope.locations.push(res) });	
	};

	$scope.dleteLocation = function(index){
		let txt = `Delete ${$scope.locations[index]}`;
		erSvc.easyRegConfirm({"text":txt,"title":"Delete Inventory Location"},"Confirm","Cancel")
		.then(res =>{ if(res) $scope.locations.splice(index,1); });
	};

	$scope.addCondition = () =>{
		erSvc.easyRegFeedback("New Condition", `New Inventory Condition`, "Create Condition", "Cancel")
		.then((res) => {if(res)	$scope.conditions.push(res) });	
	};

	$scope.dleteCondition = function(index){
		let txt = `Delete ${$scope.conditions[index]}`;
		erSvc.easyRegConfirm({"text":txt,"title":"Delete Inventory Condition"},"Confirm","Cancel")
		.then(res =>{ if(res) $scope.conditions.splice(index,1); });
	};

	$scope.addCategory = () =>{
		erSvc.easyRegFeedback("New Category", `New Inventory Category`, "Create Category", "Cancel")
		.then(function(res){
			if(res)	$scope.categories.push({ "name":res,"types":[] });
			window.scrollTo(0,document.body.scrollHeight);
		});	
	};

	$scope.dleteCat = function(index){
		let cat = $scope.categories[index].name;
		let txt = `Delete ${cat}`;
		erSvc.easyRegConfirm({"text":txt,"title":"Delete Inventory Category"},"Confirm","Cancel")
		.then(res =>{ if(res) $scope.categories.splice(index,1); });
	};

	$scope.addType = category => {
		erSvc.easyRegFeedback("New Type", `New ${category.name} Inventory Type`, "Create Type", "Cancel")
		.then(res => { if(res)	category.types.push(res); });		
	};

	$scope.dleteType = function(cat, idx){
		let type = cat.types[idx];
		let txt = `Delete ${type} from ${cat.name}`;
		erSvc.easyRegConfirm({"text":txt,"title":"Delete Inventory Type"},"Confirm","Cancel")
		.then(res => { if(res) cat.types.splice(idx,1); });
	};

	$scope.save = function(){
		erSvc.loadingDialog();
		let val = { 
			"categories":$scope.categories,
			"locations":$scope.locations,
			"conditions":$scope.conditions
		};
		let rec = {
			"id":inventoryPrefId,
			"accountid":erSessionData.accountid,
			"name":"inventorySettings",
			"value": angular.toJson(val)
		};
		dataSvc.createOrUpdateRecord({"table":"preferences","record":rec}).then(function(res){
			erSvc.closeLoading();
			erSvc.easyRegAlert({"text":"Inventory Settings Saved","title":"Changes Saved"},true);
		});
	};
});