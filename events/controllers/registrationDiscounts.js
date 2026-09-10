regApp.controller('registrationDiscounts', function($scope, $http, $timeout, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Discount Codes")').addClass('active');
	}, 100);
	$scope.userid = erSessionData.userid;
	$scope.accountid = erSessionData.useraccount;
	$scope.eventid = erSessionData.curEvent.id;

	dataSvc.getTableRecords('discount_codes', 'eventid = ' + $scope.eventid).then(function(res){
		$scope.codes = res;
	});

	$scope.addCode = function(){
		$scope.codes.push({
			code:"",
			type:"attendee",
			method:"percent",
			discount:"",
			sunrise:"",
			sunset:"",
			frequency:"oneTime",
			eventid: $scope.eventid,
			accountid:$scope.accountid
		});
	};

	$scope.save = function(){
		erSvc.loadingDialog('Updating Discount Codes');
		$scope.codes.forEach(function(code){
			dataSvc.createOrUpdateRecord({"table":"discount_codes","record":code}).then(function(res){
				code.id = res;
			});
		});

		codesToDelete.forEach(function(code){
			dataSvc.deleteRecord({"table":"discount_codes","id": code});
		});
		$timeout(function(){
			erSvc.closeLoading();
		}, 1500);
	};

	let codesToDelete = [];
	$scope.deleteCode = function(code){
		if(code.id) codesToDelete.push(code.id);
		$scope.codes.splice($scope.codes.indexOf(code),1);
	};
});//end controller