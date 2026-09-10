regApp.controller('vendorMgmt', function($scope, $http, $location, dataSvc, erSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;
	$scope.statusFilter = '0';
	$scope.states = erSvc.getStateOptions();

	$scope.vendors = {};
	dataSvc.getTableRecords('sponsors', 'accountid = ' + $scope.accountid).then(function(vendors){
		angular.forEach(vendors,function(vendor){
			vendor.archived = vendor.archived || '0';
			vendor.searchString = erSvc.getObjectSearchString(vendor);
			$scope.vendors[vendor.id] = vendor;
		});
	});

	let catFilter = 'accountid = ' + $scope.accountid + ' AND name =' +  "'vendorCategories'";
	dataSvc.getTableRecords('preferences',  catFilter).then(function(res){
		$scope.categorySettings = {"categories":[],"allowOther":false};
		if(res[0]){
			try{
				$scope.categorySettings = angular.fromJson(res[0].value);
			}catch(e){}
		}
	});

	$scope.showVendor = function(vendor){
		if(vendor.archived != $scope.statusFilter) return false;
		return !$scope.quickSearch || vendor.searchString.indexOf($scope.quickSearch.toLowerCase()) >= 0;
	};

	$scope.updateVendor = function(vendor){
		if(vendor) $scope.editVendor = angular.copy(vendor);
		else $scope.editVendor = {
			"id":"-1", "name":"", "accountid":$scope.accountid, "address1":"", "address2":"",
			"city":"", "state":"", "zip":"", "contact_first_name":"", "contact_last_name":"",
			"email":"", "phone":"", "username":"", "pass":"","archived":"0"
		};

		$scope.editVendor.pass = '';
		$scope.selectedVendorId = $scope.editVendor.id;
		$scope.categorySelect = $scope.editVendor.category;
		if($scope.categorySelect && !$scope.categorySettings.categories.includes($scope.categorySelect)) 
			$scope.categorySelect = 'Other';
		$scope.resetPassword = false;
		$('#editDialog').show(500);
	};

	$scope.categoryChange = function(){
		if($scope.categorySelect != 'Other') $scope.editVendor.category = $scope.categorySelect;
	};

	$scope.saveVendor = function(){
		var passwordSet = $.Deferred();
		if($scope.resetPassword){
			if(!erSvc.validatePassword($scope.editVendor.pass)) return;
			erSvc.encrypt($scope.editVendor.pass).then(function(data){
				$scope.editVendor.pass = data;
				passwordSet.resolve();
			});
		}else{
			$scope.editVendor.pass = '';
			passwordSet.resolve();
		}

		$.when(passwordSet).then(function(){
			var newVendor = $scope.editVendor.id == "-1";
			if(newVendor) delete $scope.editVendor.id;
			dataSvc.createOrUpdateRecord({"table":"sponsors","record":$scope.editVendor}).then(function(resp){
				if(newVendor){
					$scope.vendors[resp] = angular.copy($scope.editVendor);
					$scope.vendors[resp].id = resp;
				}else{
					$scope.vendors[$scope.selectedVendorId] = angular.copy($scope.editVendor);
				}
				$('#editDialog').hide(500);
				$scope.$applyAsync();
			});
		});
	};

	$scope.closeRightDialog = () => $(".dialogRight").hide(500);

	$scope.loadVendorDetails = function(vendor){
		erSvc.selectedVendor = vendor;
		$location.path('/vendor_details');
	};

	$scope.downloadLogos = function(which){
		let logos = [];
		angular.forEach($scope.vendors,function(v){
			let logo = v.logo || v.logo2;
			if(which === 'logo2') logo = v.logo2 || v.logo; 
			if(logo && String(v.archived) === $scope.statusFilter){
				logos.push(`img/account${$scope.accountid}/vendorLogos/${v.id}/${logo}`);
			}
		});
		erUtils.downloadFiles(logos, 'logos.zip');
	};

	$scope.getLink = function(addr){
		if(addr.indexOf('http') == 0) return addr;
		else return 'https://' + addr;
	}
});//end controller
