regApp.controller('staffCtrl', function($scope, $http, $q, accountid, dataSvc, erSvc) {
$scope.staffEdit = function(staff){
	$scope.selectedStaff = staff || {};
	$scope.selectedStaff.passChange = false;
	$scope.staffDialogTitle = staff ? "Edit Staff Member" : "Add Staff Member";
	$('form[name="staffForm"]').removeClass('submitted');
	$('#staffDialog').show();
};

$(document).on('change', ':file', function() {
	$scope.selectImage($(this));
});

$scope.selectImage = function(input){
	erSvc.loadingDialog();
	if($scope.selectedStaff.photo){
		$http({
			"url": "/deleteDocument.php",
			"method": "POST",
			"data": $.param({"document":$scope.selectedStaff.photo.substr(1)}),
			"headers": {"Content-Type": "application/x-www-form-urlencoded"}
		});
	}
	var imgDestination = "img/account" + $scope.vendor.accountid + "/users";
	var imgName =  input.val().replace(/\\/g, '/').replace(/.*\//, '');
	erSvc.uploadDocument($('#imageInput'), imgDestination).then(function(){
		var newStaffData = {
			"id":$scope.selectedStaff.id,
			"photo": $scope.selectedStaff.photo
		}
		dataSvc.createOrUpdateRecord({"table":"users","record":newStaffData}).then(function(){
			$('#userImg').attr("src",$scope.selectedStaff.photo);
			erSvc.closeLoading();
			$scope.selectedStaff.photo = "/" + imgDestination + "/" + imgName;
		});
	});
};

$scope.updateStaff = function(){
	var pwReady = $q.defer();
	$scope.selectedStaff.accountid = $scope.vendor.accountid;
	$scope.selectedStaff.sponsorid = $scope.vendor.id;
	$('form[name="staffForm"]').addClass('submitted');
	if(!$scope.staffForm.$valid) return;
	//encrypt pw if changed
	if($scope.selectedStaff.passChange){
		erSvc.encrypt($scope.selectedStaff.pass).then(function(res){
			$scope.selectedStaff.pass = res;
			pwReady.resolve();
		});
	}else{
		pwReady.resolve();
	}

	$q.when(pwReady.promise).then(function(){
		dataSvc.createOrUpdateRecord({"table":"users","record":$scope.selectedStaff}).then(function(res){
			$(".dialogRight").hide(500);
			if(res > '0'){
				$scope.selectedStaff.id = res;
				$scope.vendor.staff.push($scope.selectedStaff);
				$scope.$applyAsync();
			}
		});
	});
};
});