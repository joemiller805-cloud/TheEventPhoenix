regApp.controller('registrationForm', function($scope, $http, $q, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Registration Form")').addClass('active');
	}, 100);
	$scope.eventid = erSessionData.curEvent.id;
	var acctFieldsRetrieved = $q.defer();
	var exclusionsRetrieved = $q.defer();

	$scope.coreFields = {
		"title":{"include":true,"label":"Position/Title"},
		"address":{"include":true,"label":"Address"},
		"phone":{"include":true,"label":"Phone"},
		"web_address":{"include":true,"label":"Web Address"},
		"dietary_restrictions":{"include":true,"label":"Dietary Restrictions"},
	    "business": {"include": true,"label": "Business"},
	    "vendor_access": {"include": true,"label": "Share my information with vendors"},
	    "ec1": {"include": true,"label": "Emergency Contact 1"},
	    "ec2": {"include": true,"label": "Emergency Contact 2"}
	};

	dataSvc.getPreferenceByName('regScreenConfig', accountid).then(function(res){
		if(res[0]){
			try{
				let regSettings = angular.fromJson(res[0].value);	
				for(const prop in regSettings){
					if(!$scope.coreFields[prop]) continue;
					$scope.coreFields[prop].label = regSettings[prop].label;
					if(regSettings[prop].hide) delete $scope.coreFields[prop];
				}
			}catch(e){}
		}
		$scope.haveCoreFields = Object.keys($scope.coreFields).length;
	});

	dataSvc.getTableRecords('registration_fields', `accountid = ${accountid} AND archived != '1'` , true).
	then(function(fields){
		$scope.acct_fields = fields;
		angular.forEach($scope.acct_fields,function(field){
			field.options = field.options.replace(/;/g, '\r\n');
			field.include = true;
		});
		acctFieldsRetrieved.resolve();
		$scope.haveAcctFields = Object.keys($scope.acct_fields).length;
	});

	let exclusions;
	dataSvc.getTableRecords('registration_field_exclusions', 'eventid = ' + $scope.eventid).then(function(res){
		exclusions = res;
		exclusionsRetrieved.resolve();
	});

	$q.all([acctFieldsRetrieved.promise,exclusionsRetrieved.promise]).then(function(){
		exclusions.forEach(function(exc){
			let coreField = $scope.coreFields[exc.default_field]
			if(coreField){
				coreField.include = false;
				coreField.exclusionid = exc.id;
				return;
			}
			let acctField = $scope.acct_fields[exc.registration_fields_id]
			if(acctField){
				acctField.include = false;
				acctField.exclusionid = exc.id;	
			}
		});
	});

	var fieldsToRemove = [];
	dataSvc.getArray({'query':'extraRegFields','eventid':$scope.eventid}).then(function(fields){
		$scope.reg_fields = fields;
		angular.forEach($scope.reg_fields, fld => fld.options = fld.options.replace(/;/g, '\r\n'));
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
	});

	$scope.checkRequired = function(fld){
		if(['c','l'].includes(fld.type)) fld.required = '0';
	};

	$scope.addRow = function(){
		$scope.reg_fields.push({
			"eventid":$scope.eventid,
			"type": "t",
			"label": "",
			"name": "",
			"required": 0,
			"size": "100",
			"options": "",
			"sortorder":$scope.reg_fields.length,
			"accountField":'false'
		});
	};

	$scope.remove = function(field){
		$scope.reg_fields.splice($scope.reg_fields.indexOf(field),1);
		fieldsToRemove.push(field);
	};

	$scope.saveChanges = function(){
		erSvc.loadingDialog();
		angular.forEach(fieldsToRemove,function(field){
			dataSvc.deleteRecord({"table":"registration_fields","id":field.id});
		});
		var fieldsUpdated = 0;
		angular.forEach($scope.reg_fields,function(field){
			dataSvc.createOrUpdateRecord({"table":"registration_fields","record":field}).then(function(res){
				if(!field.id) field.id = res;
				if(++fieldsUpdated == $scope.reg_fields.length) alertChangesSaved();
				
			});
		});
		if($scope.reg_fields.length == 0){
			erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"},true);
			alertChangesSaved();
		} 
	
		for(lbl in $scope.coreFields){
			let fld = $scope.coreFields[lbl];
			if(!fld.include && !fld.exclusionid){
				let rec = {"eventid":$scope.eventid, "default_field":lbl};
				dataSvc.createOrUpdateRecord({"table":"registration_field_exclusions","record":rec})
				.then(id => fld.exclusionid = id);
			} 
			if(fld.include && fld.exclusionid){
				dataSvc.deleteRecord({"table":"registration_field_exclusions","id":fld.exclusionid});
				fld.exclusionid = null;
			}
		}
		for(id in $scope.acct_fields){
			let fld = $scope.acct_fields[id];
			if(!fld.include && !fld.exclusionid){
				let rec = {"eventid":$scope.eventid, "registration_fields_id":id};
				dataSvc.createOrUpdateRecord({"table":"registration_field_exclusions","record":rec})
				.then(id => fld.exclusionid = id);
			} 
			if(fld.include && fld.exclusionid){
				dataSvc.deleteRecord({"table":"registration_field_exclusions","id":fld.exclusionid});
				fld.exclusionid = null;
			}
		}
	};

	function alertChangesSaved(){
		erSvc.closeLoading();
		erSvc.easyRegAlert({"text":"Registration Changes Saved","title":"Changes Saved"},true);
	};

	$scope.optionMsgs = {
		"r":"Enter radio button labels with a new line for each option",
		"s":"Enter drop-down option with a new line for each option",
		"c":"Enter checkbox labels with a new line for each option",
		"a":"Enter a message to which the user must agree to complete registration"
	};
});
