regApp.controller('registrationTypes', function($scope, $http, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Registration Types")').addClass('active');
	}, 100);
	$scope.eventId = erSessionData.curEvent.id;
	$scope.registrationTypes = {};

	dataSvc.getTableRecords('registration_types', 'eventid = ' + $scope.eventId,false,$scope.eventId).then(function(response){
		$scope.registrationTypes = response;
		angular.forEach($scope.registrationTypes,function(type){
			type.sortorder = parseFloat(type.sortorder);
			type.sections_allowed = parseFloat(type.sections_allowed);
			type.ordered = false;
		});
		$('#regTypesTbody').sortable({
			axis: "y",
			handle: ".handle",
			opacity: 0.7,
			revert: true,
			update: function( event, ui ) {
				$('#regTypesTbody tr').each(function(idx, el){
					angular.element(el).scope().regType.sortorder = idx;
				});
				$scope.$applyAsync();
			}
		});
		dataSvc.getTableRecords('event_sponsor_options', 'eventid = ' + $scope.eventId,false,$scope.eventId).then(function(res){
			res.forEach(function(type){
				let regType = getRegType(type.registration_type_id);
				if(regType){
					regType.sponsor_opt_data = type;
					regType.sponsor_opt_data.price = Number(regType.sponsor_opt_data.price).toFixed(2);
				}
			});
		});
		dataSvc.getArray({'query':'eventRegSummary','eventid':$scope.eventId}).then(function(regOrders){
			regOrders.forEach(function(ro){
				if(Number(ro.orderCount) > 0){
					$scope.registrationTypes.forEach(function(rt){
						if(rt.id == ro.id) rt.ordered = true;
					});
				}
			});
		});
	});

	dataSvc.getTableRecords('account_reg_types', 'accountid = ' + accountid,false)
	.then((res) => $scope.acctTypes = res);

	$scope.addAcctType = function(id){
		let selectedType;
		$scope.acctTypes.forEach(type => {if(type.id == id) selectedType = type });
		if(!selectedType) return;
		$scope.registrationTypes.push({
			name:selectedType.name,
			price:selectedType.price,
			sunrise:getRegDate(selectedType.sunrise_days, 'before'),
			sunset:getRegDate(selectedType.sunset_days, selectedType.sunset_before_after),
			reg_start_dt:getRegDate(selectedType.signup_start_days, 'before'),
			reg_end_dt:getRegDate(selectedType.signup_end_days, selectedType.signup_end_before_after),
			video_start_dt:getRegDate(selectedType.video_start_days, selectedType.video_start_before_after),
			video_end_dt:getRegDate(selectedType.video_end_days, selectedType.video_end_before_after),
			sortorder:$scope.registrationTypes.length,
			vendor_reg:selectedType.vendor_reg,
			sections_allowed:selectedType.sections_allowed,
			sponsor_opt_data: {
				description:selectedType.description,
				staff_allowance:selectedType.staff_allowance,
				price:selectedType.price,
				type:'event vendor'
			}
		});
	};

	getRegDate = (days, when) => erSvc.addDays(eventStartDt, when == 'before' ? Number(days) * -1 : days);
	getRegType = (id) => $scope.registrationTypes.filter(t => t.id == id)[0];

	let eventStartDt;
	dataSvc.getEventDataFromId($scope.eventId).then(function(response){
		eventStartDt = response.startdate;
		$scope.hasVirtual = response.has_virtual == '1';
	});

	$scope.sponsors_enabled = false;
	dataSvc.getArray({'query':'sponsors_enabled'}).then(function(resp){
		if(resp[0]) $scope.sponsors_enabled = resp[0].sponsors_enabled == '1';
	});

	$scope.attendeeRegLength = function(){
		let len = 0;
		angular.forEach($scope.registrationTypes, t => len += t.vendor_reg == 0 ? 1 : 0);
		return len;
	};

	$scope.addNew = function(type){
		let vendorComp = type == "vendor" ? '1' : '0';
		let start = "00/00/0000";
		let end = "00/00/0000";
		let signupStart = "00/00/0000";
		let signupEnd = "00/00/0000";
		let videoStart = "00/00/0000";
		let videoEnd = "00/00/0000";
		angular.forEach($scope.registrationTypes,function(typ){
			if(typ.vendor_reg == vendorComp){
				start = typ.sunrise;
				end = typ.sunset;
				signupStart = typ.reg_start_dt;
				signupEnd = typ.reg_end_dt;
				videoStart = typ.video_start_dt;
				videoEnd = typ.video_end_dt;
			}
		});
		let newType = {
			name:"",
			price:"0.00",
			sunrise:start,
			sunset:end,
			reg_start_dt:signupStart,
			reg_end_dt:signupEnd,
			video_start_dt:videoStart,
			video_end_dt:videoEnd,
			sortorder:type == 'vendor' ? '0' : $scope.attendeeRegLength(),
			vendor_reg: type == 'vendor' ? '1' : '0'
		};

		if(type == 'vendor'){
			newType.sponsor_opt_data = {
				name:"",
				price:"0.00",
				sunrise:start,
				sunset:end,
				staff_allowance: '0',
				description:'',
				type: 'event vendor'
			}
		};

		$scope.registrationTypes.push(newType);
	};

	$scope.saveTypes = function(){
		$('.successMessage').hide();
		erSvc.loadingDialog('Saving Changes');
		let savedTotal = 0;
		angular.forEach($scope.registrationTypes,function(type){
			let typeClone = angular.copy(type);
			typeClone.eventid = $scope.eventId;
			dataSvc.createOrUpdateRecord({"table":"registration_types","record":typeClone}, 
				$scope.eventId).then(function(resp){
				if(resp != '0'){
					type.id = resp;
					if(type.sponsor_opt_data){
						type.sponsor_opt_data.registration_type_id = resp;
						typeClone.sponsor_opt_data.registration_type_id = resp;
					}
				} 
				if(type.sponsor_opt_data && type.vendor_reg == '1'){
					type.sponsor_opt_data.sunrise = type.sunrise;
					type.sponsor_opt_data.sunset = type.sunset;
					type.sponsor_opt_data.name = type.name;
					type.sponsor_opt_data.eventid = $scope.eventId;
					let optClone = angular.copy(type.sponsor_opt_data);
					dataSvc.createOrUpdateRecord({"table":"event_sponsor_options","record":optClone},$scope.eventId).then(function(r){
						if(resp != '0') type.sponsor_opt_data.id = r;
						if(++savedTotal == $scope.registrationTypes.length) finished();
					});
				}else{
					if(++savedTotal == $scope.registrationTypes.length) finished();
				}	
			});// End record update
		});// End reg type loop
	}; //End saveTypes()

	function finished(){
		$('.successMessage').show();
		erSvc.closeLoading();
	}

	$scope.reload = function(){	location.reload(); };
});//End Controller