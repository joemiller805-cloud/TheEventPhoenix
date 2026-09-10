regApp.controller('vendorSettingsCtrl', function($scope, $http, $q, $location, dataSvc, erSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;

	//Invoice Functions
	dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
		$scope.sponsor_invoice_msg = resp[0].sponsor_invoice_msg;
	});
	
	//Category Functions
	let categoryPrefId;
	let catFilter = 'accountid = ' + $scope.accountid + ' AND name =' +  "'vendorCategories'";
	dataSvc.getTableRecords('preferences',  catFilter).then(function(res){
		$scope.categorySettings = {"categories":[],"allowOther":false};
		if(res[0]){
			try{
				$scope.categorySettings = angular.fromJson(res[0].value);
				categoryPrefId = res[0].id;
			}catch(e){}
		}
	});

	$scope.addCategory = () => $scope.categorySettings.categories.push("");
	$scope.deleteCategory = (idx) => $scope.categorySettings.categories.splice(idx,1);

	//Discount Functions
	let discountPrefId;
	let discFilter = 'accountid = ' + $scope.accountid + ' AND name =' +  "'vendorDiscountSettings'";
	dataSvc.getTableRecords('preferences',  discFilter).then(function(res){
		$scope.thresholds = [];
		if(res[0]){
			try{
				$scope.thresholds = angular.fromJson(res[0].value);
				discountPrefId = res[0].id;
			}catch(e){}
		}
	});

	$scope.discountCodes = [];
	dataSvc.getTableRecords('discount_codes', `accountid = ${$scope.accountid} AND type = 'vendor'`)
	.then(function(res){
		$scope.discountCodes = res;
	});

	$scope.addThreshold = () => $scope.thresholds.push({"val":0,"method":"amount","discount":0});

	$scope.deleteThreshold = function(threshold){
		let txt = "Delete this discount?";
		erSvc.easyRegConfirm({"text":txt,"title":"Confirm Delete"},"Confirm","Cancel").then(function(res){
			if(res){
				$scope.thresholds.splice($scope.thresholds.indexOf(threshold),1);
			}
		});
	};

	$scope.addDicountCode = function(){
		$scope.discountCodes.push({
			"code":"",
			"type":"vendor",
			"method":"percent",
			"discount":"",
			"sunrise":"",
			"sunset":"",
			"frequency":"oneTime",
			"accountid":$scope.accountid
		});
	};

	let codesToDelete = [];
	$scope.deleteCode = function(code){
		if(code.id) codesToDelete.push(code.id);
		$scope.discountCodes.splice($scope.discountCodes.indexOf(code),1);
	}

	$scope.save = function(){
		erSvc.loadingDialog();
		var discountsSaved = $q.defer();
		var categoriesSaved = $q.defer();
		var invoiceMsgSaved = $q.defer();

		//discount settings
		let rec = {
			"id":discountPrefId,
			"accountid":$scope.accountid,
			"name":"vendorDiscountSettings",
			"value":angular.toJson($scope.thresholds)
		};
		dataSvc.createOrUpdateRecord({"table":"preferences","record":rec}).then(function(res){
			discountsSaved.resolve();
		});

		$scope.discountCodes.forEach(function(code){
			dataSvc.createOrUpdateRecord({"table":"discount_codes","record":code});
		});
		codesToDelete.forEach(function(codeid){
			dataSvc.deleteRecord({"table":"discount_codes","id":codeid});
		});

		//category settings
		rec.id = categoryPrefId;
		rec.name = "vendorCategories";
		rec.value = angular.toJson($scope.categorySettings);
		dataSvc.createOrUpdateRecord({"table":"preferences","record":rec}).then(function(res){
			categoriesSaved.resolve();			
		});

		//invoice message
		let accountRec = {
			"id":$scope.accountid,
			"sponsor_invoice_msg":$scope.sponsor_invoice_msg
		};
		dataSvc.createOrUpdateRecord({"table":"accounts","record":accountRec}).then(function(){
			invoiceMsgSaved.resolve();	
		});

		$q.all([discountsSaved.promise,categoriesSaved.promise,invoiceMsgSaved.promise]).then(function(){
			erSvc.closeLoading();
			erSvc.easyRegAlert({"text":"Vendor Settings Saved","title":"Changes Saved"},true);
		});
	};
});//end controller