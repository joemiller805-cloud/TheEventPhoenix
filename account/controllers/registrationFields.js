regApp.controller('registrationFields', function($scope, $http, dataSvc, erSvc) {
	let accountid = erSessionData.accountid;
	dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
		if(resp[0]) $scope.accountName = resp[0].name;
	});

	//property for account preferences for showing and labeling default registration fields.
	$scope.defaultConfig = {
		"accountid":accountid,
		"name":"regScreenConfig",
		"fields":{
			"email":{"name":"Email","hide":false,"label":"Email"},
			"first_name":{"name":"First Name","hide":false,"label":"First Name"},
			"last_name":{"name":"Last Name","hide":false,"label":"Last Name"},
			"title":{"name":"Position/Title","hide":false,"label":"Position/Title"},
			"business":{"name":"Business","hide":false,"label":"Business"},
			"address":{"name":"Business Address","hide":false,"label":"Business Address"},
			"phone":{"name":"Business Phone","hide":false,"label":"Business Phone"},
			"vendor_access":{"name":"Share Info With Vendors","hide":false,"label":"Share my information with vendors"},
			"web_address":{"name":"Web Address","hide":false,"label":"Web Address (Optional)"},
			"dietary_restrictions":{"name":"Dietary Restrictions","hide":false,"label":"Dietary Restrictions"},
			"ec1":{"name":"Emergency Contact 1","hide":false,"label":"Emergency Contact 1"},
			"ec2":{"name":"Emergency Contact 2","hide":false,"label":"Emergency Contact 2"}
		}
	};

	dataSvc.getPreferenceByName('regScreenConfig', accountid).then(function(config){
		if(config[0]){
			$scope.defaultConfig = config[0];
			$scope.defaultConfig.fields = angular.fromJson($scope.defaultConfig.value);
		}
	});

	var fieldsToRemove = [];
	$scope.reg_fields = [];
	$scope.regFieldsReady = false;
	dataSvc.getTableRecords('registration_fields', 'accountid = ' + accountid).then(function(fields){
		$scope.reg_fields = fields;
		angular.forEach($scope.reg_fields,function(field){
			field.options = field.options.replace(/;/g, '\r\n');
		});
		$('#regFieldsTbody').sortable({
			axis: "y",
			handle: ".handle",
			opacity: 0.7,
			revert: true,
			update: function( event, ui ) {
				$('#regFieldsTbody tr').each(function(idx, el){
					angular.element(el).scope().field.sortorder = idx;
				});
				$scope.$applyAsync();
			}
		});
		$scope.regFieldsReady = true;
		$scope.$applyAsync();
	});

	$scope.setSort = function(field){
		let oldSort = field.sortorder;
		if(field.archived == '1'){
			field.sortorder = 9999;
			$scope.reg_fields.forEach(function(f){
				if(f.archived == '0' && f.sortorder > oldSort) f.sortorder -= 1;
			});
		}else{
			field.sortorder = $scope.reg_fields.filter(f => f.archived == '0').length - 1;
		} 
	};

	$scope.addRow = function(){
		if(!$scope.regFieldsReady) return;
		$scope.reg_fields.push({
			"accountid":accountid,
			"type": "t",
			"label": "",
			"name": "",
			"required": 0,
			"size": "100",
			"archived": 0,
			"sortorder":$scope.reg_fields.length,
			"options": ""
		});
	};

	$scope.remove = function(field){
		$scope.reg_fields.splice($scope.reg_fields.indexOf(field),1);
		fieldsToRemove.push(field);
	};

	$scope.saveChanges = function(){
		erSvc.loadingDialog();
		$scope.defaultConfig.value = angular.toJson($scope.defaultConfig.fields);
		dataSvc.createOrUpdateRecord({"table":"preferences","record":$scope.defaultConfig});
		angular.forEach(fieldsToRemove,function(field){
			if(!field.id) return;
			dataSvc.deleteRecord({"table":"registration_fields","id":field.id});
		});
		var fieldsUpdated = 0;
		angular.forEach($scope.reg_fields,function(field){
			dataSvc.createOrUpdateRecord({"table":"registration_fields","record":field}).then(function(res){
				if(!field.id) field.id = res;
				if(++fieldsUpdated == $scope.reg_fields.length) erSvc.closeLoading();
				erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"},true);
			});
		});
		if(!$scope.reg_fields.length) erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"},true);
	};

	$scope.optionMsgs = {
		"r":"Enter radio button labels with a new line for each option",
		"s":"Enter drop-down option with a new line for each option",
		"c":"Enter checkbox labels with a new line for each option",
		"a":"Enter a message to which the user must agree to complete registration"
	};
});
