regApp.controller('registrationExtras', function($scope, $http, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Extras")').addClass('active');
	}, 100);
	$scope.eventId = erSessionData.curEvent.id;
	$scope.regOptions = {};
	
	dataSvc.getObject({'query':'eventExtrasSummary','eventid':$scope.eventId}).then(function(xtraOrders){
		dataSvc.getArray({"query":"registrationExtras","eventid":$scope.eventId}).then(function(res){
			$scope.regOptions = res;
			angular.forEach($scope.regOptions,function(type){
				type.sunrise = erSvc.mySqlToLocalDate(type.sunrise);
				type.sunset = erSvc.mySqlToLocalDate(type.sunset);
				type.sortorder = parseFloat(type.sortorder);
				type.registration_types = type.registration_types.split(',');
				type.ordered = false;
				if(xtraOrders[type.id])	type.ordered = xtraOrders[type.id].orderCount != '0';
			});
			$('#xtrasTbody').sortable({
				axis: "y",
				handle: ".handle",
				opacity: 0.7,
				revert: true,
				update: function( event, ui ) {
					$('#xtrasTbody tr').each(function(idx, el){
						angular.element(el).scope().xtra.sortorder = idx;
					});
					$scope.$applyAsync();
				}
			});
		});
	});

	dataSvc.getArray({"query":"registrationTypes","eventid":$scope.eventId}).then(function(types){
		$scope.registration_types = types;
		if($scope.registration_types.length == 0){
			dataSvc.getEventDataFromId($scope.eventId).then(function(evt){
				let regType = {
					"eventid":$scope.eventId,
					"name":"Tickets",
					"price":"0",
					"sortorder":"0",
					"vendor_reg":'0',
					"sunrise":evt.registrationstartdate,
					"sunset":evt.registrationenddate
				}
				dataSvc.createOrUpdateRecord({"table":"registration_types","record":regType}).then(function(id){
					regType.id = id;
					$scope.registration_types.push(regType)
				});
			});
			
		}
	});

	$scope.addNew = function(){
		$scope.regOptions.push({
			id:"0",
			label:"", 
			description:"", 
			price:"0.00",
			sunrise:"00/00/0000",
			sunset:"00/00/0000", 
			registration_types:$scope.acctType == 'ticketing' ? $scope.registration_types[0].id : "",
			sortorder:$scope.regOptions.length,
			purchases:'0'
		});		
	};

	$scope.saveTypes = function(){
		$('.successMessage').hide();
		let savedTotal = 0;
		angular.forEach($scope.regOptions,function(type){
			let xtraClone = angular.copy(type);
			xtraClone.eventid = $scope.eventId;
			if(xtraClone.id == '0') delete xtraClone.id;
			dataSvc.createOrUpdateRecord({"table":"registration_extras","record":xtraClone},$scope.eventId).then(function(res){
				if(res != '0') type.id = res;	
			 	if(++savedTotal == $scope.regOptions.length) $('.successMessage').show();
			});
		});
	};

	$scope.deleteConfirm = function(reg){
		let txt = `Delete ${reg.label}?`;
		erSvc.easyRegConfirm({"text":txt,"title":"Confirm Delete"},"Confirm - Delete","Cancel").then(function(res){
			if(res){
				erSvc.loadingDialog();
				dataSvc.deleteRecord({"table":"registration_extras","id":reg.id}).then(function(){
					$scope.regOptions.splice($scope.regOptions.indexOf(reg),1);
					erSvc.closeLoading();
				});
			}
		});
	};

	$scope.reload = () => location.reload();
});//End Controller