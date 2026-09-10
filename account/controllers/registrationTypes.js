regApp.controller('acctRegistrationTypes', function($scope, $http, $rootScope, dataSvc, erSvc) {
	$scope.registrationTypes = {};
	let accountid = erSessionData.accountid;
	$scope.registrationTypes = [];
	dataSvc.getTableRecords('account_reg_types', 'accountid = ' + accountid,false).then(function(res){
		$scope.registrationTypes = res;
		$scope.registrationTypes.forEach(t => t.sortorder = Number(t.sortorder));
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
	});

	function getRegType(id){
		let matchedType = null;
		$scope.registrationTypes.forEach(function(type){
			if(type.id == id) matchedType = type;
		});
		return matchedType;
	} 

	$scope.addNew = function(type){
		$scope.registrationTypes.push({
			accountid:accountid,
			name:"",
			price:"0.00",
			sunset_before_after:"before",
			video_start_before_after:"before",
			video_end_before_after:"before",
			vendor_reg: type == 'vendor' ? '1' : '0',
			staff_allowance:'0',
			description: "",
			sortorder:$scope.attendeeRegTypeCount()
		});
	};

	$scope.attendeeRegTypeCount = function(){
		return $scope.registrationTypes.filter(t => t.vendor_reg != '1').length;
	};

	$scope.saveTypes = function(){
		$('.successMessage').hide();
		erSvc.loadingDialog('Saving Changes');
		let savedTotal = 0;
		angular.forEach($scope.registrationTypes,function(type){
			let typeClone = angular.copy(type);
			dataSvc.createOrUpdateRecord({"table":"account_reg_types","record":typeClone}, 
				$scope.eventId).then(function(resp){
				if(resp != '0') type.id = resp; 
				if(++savedTotal == $scope.registrationTypes.length){
					$('.successMessage').show();
					erSvc.closeLoading();
				} 	
			});
		});
	};

	$scope.reload = function(){	location.reload(); };
});//End Controller