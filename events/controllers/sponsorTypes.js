regApp.controller('sponsorTypes', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.eventId = erSessionData.curEvent.id;

	$scope.vendorTypes = [];
	dataSvc.getTableRecords('event_sponsor_options', 'eventid = ' + $scope.eventId,false, $scope.eventId).then(function(types){
		$scope.sponsorTypes = types;
		$scope.sponsorTypes.sort((a,b) => Number(a.price) > Number(b.price) ? 1 : -1);
		angular.forEach($scope.sponsorTypes,function(type){
			type.price = Number(type.price).toFixed(2);
			if(type.type == 'event staff') $scope.extraStaff = type;
			else if(type.type == 'event vendor') $scope.vendorTypes.push(type);
			type.vendor_type_restrictions = type.vendor_type_restrictions.split(',');
		});
	});

	$scope.newOption = function(type){
		$scope.sponsorTypes.push({
			name:"",
			price:"0.00",
			sunrise:"00/00/0000",
			sunset:"00/00/0000",
			"description":"",
			"type":type
		});
	};

	$scope.saveTypes = function(){
		$('.successMessage').hide();
		let savedTotal = 0;
		let extraStaffFound = false;
		let extraStaffDeleted = false;
		angular.forEach($scope.sponsorTypes,function(type){
			if(type.type == 'event staff'){
				extraStaffFound = true;
				if(!type.price && type.id){
					//need to delete this record
					extraStaffDeleted = true;
					dataSvc.deleteRecord({"table":"event_sponsor_options","id":type.id}).then(function(){
						if(++savedTotal == $scope.sponsorTypes.length) $('.successMessage').show();
					});
				}
			}
			if(extraStaffDeleted) return;
			
			let typeClone = angular.copy(type);
			typeClone.eventid = $scope.eventId;
			dataSvc.createOrUpdateRecord({"table":"event_sponsor_options","record":typeClone}).then(function(res){
				if(res != '0') type.id = res;
				if(++savedTotal == $scope.sponsorTypes.length) $('.successMessage').show();
			});
		}); 

		//if extra staff record was not found in list, it needs to be added.
		if(!extraStaffFound && $scope.extraStaff){
			$scope.extraStaff.type = 'event staff';
			$scope.extraStaff.eventid = $scope.eventId;
			let typeClone = angular.copy($scope.extraStaff);
			dataSvc.createOrUpdateRecord({"table":"event_sponsor_options","record":typeClone}).then(function(res){
				if(res != '0')	$scope.extraStaff.id = res;
			});
		}
	}; //End saveTypes()

	$scope.deleteConfirm = function(type){
		//check to see if this option has already been purchased
		dataSvc.getTableRecords('vendor_order_details', 'event_sponsor_options_id = ' + type.id)
		.then(function(res){
			if(res.length){
				let txt = `This option has already been purchased and cannot be deleted.`
				erSvc.easyRegAlert({"text":txt,"title":"Unable to Delete"});
				return;
			}
			let txt = "Delete this vendor purchase option?"
			erSvc.easyRegConfirm({"text":txt,"title":"Confirm Delete"},"Continue","Cancel")
			.then(function(res){
				if(res){
					dataSvc.deleteRecord({"table":"event_sponsor_options","id":type.id});
					$scope.sponsorTypes.splice($scope.sponsorTypes.indexOf(type),1);
				}
			});
		});
	};

	$scope.reload = function(){
		location.reload();
	};
});//End Controller