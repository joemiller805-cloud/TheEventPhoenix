regApp.controller('qrConfig', function($scope, $rootScope, $http, dataSvc, erSvc) {
	$scope.qrCodes = [];
	let prefId;
	dataSvc.getPreferenceByName('qrCodeSettings', erSessionData.accountid).then(function(settings){
		if(settings[0]){
			$scope.qrCodes = angular.fromJson(settings[0].value);
			prefId = settings[0].id;
		}
	});
	
	let selectedQrIdx;
	$scope.editQr = function(qr){
		selectedQrIdx = $scope.qrCodes.indexOf(qr);
		$scope.curFunction = qr ? 'Edit' : 'New';
		$scope.editCode = angular.copy(qr) || {"name":"","content":""};
		$('#newQrDialog').show(500);
	};

	$scope.saveQr = function(){
		erSvc.loadingDialog();
		if(selectedQrIdx >= 0) $scope.qrCodes[selectedQrIdx] = angular.copy(angular.copy($scope.editCode));
		else $scope.qrCodes.push(angular.copy($scope.editCode));
		$scope.editCode = null;
		saveSettings();
	};

	$scope.deleteQr = function(){
		$scope.qrCodes.splice(selectedQrIdx,1);
		saveSettings();
	};

	saveSettings = function(){
		let qrPref = {"accountid":erSessionData.accountid,"name":"qrCodeSettings","value":angular.toJson($scope.qrCodes)};
		if(prefId) qrPref.id = prefId;
		dataSvc.createOrUpdateRecord({"table":"preferences","record":qrPref}).then(function(res){
			prefId = prefId || res;
			$scope.closeRightDialog();
			erSvc.closeLoading();
		});
	}

	$scope.addField = function(){
		$scope.editCode.content += ` [${$scope.selectedField}]`;
		$scope.selectedField = '';
	};

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);
});//end controller
