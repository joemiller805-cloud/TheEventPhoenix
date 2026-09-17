regApp.controller('acctDetailsCtrl', function($scope, $http, $location, dataSvc, erSvc) {
	if(!masterUser)	$location.path('/event_management');
	$scope.states = erSvc.getStateOptions();
	let paymentPrefs = ['basysEnabled','basysPrivateKey','basysPublicKey'];
	let savedAccount = {};
	$scope.account = {};
	// this query is used to show data on Account Settings 
	dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
		$scope.account = resp[0];
		$scope.accountid = $scope.account.id;
		dataSvc.accountid = $scope.accountid;
		// this query is used to show data on Online Payments 
		dataSvc.getArray({'query':'getMagicwrighterInfo'}).then(function(pymtData){
			$scope.account.paymentData = pymtData[0];
			$scope.account.paymentData['accountid'] = dataSvc.accountid;
			savedAccount = angular.copy($scope.account);
			if($scope.account.paymentData.enable_online_pymts == '1') 
				$scope.paymentProvider = 'magicWrighter';
			else $scope.paymentProvider = 'basys';
		});

		//Basys Settings
		$scope.account.prefs = {};
		paymentPrefs.forEach(function(pref){
			$scope.account.prefs[pref] = {"name":pref,"value":"","accountid":$scope.accountid};
		});

		dataSvc.getArray({'query':'account_preferences'}).then(function(prefs){
			prefs.forEach(function(pref){
				if(paymentPrefs.includes(pref.name)) $scope.account.prefs[pref.name] = pref;
			});			
			if($scope.account.prefs['basysPrivateKey']){
				$http({
					"url": '/er_decrypt.php',
					"method": 'POST',
					"data": $.param({"value":$scope.account.prefs['basysPrivateKey'].value}),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				}).then(function(response){
					$scope.account.prefs['basysPrivateKey'].value = response.data;
				}).catch(function(){
					erSvc.easyRegAlert({
						"text":"The saved Basys private key could not be decrypted. It may have been encrypted with a different environment key and may need to be re-entered.",
						"title":"Basys Key Error"
					});
				});
			}
		});
		getImages();
	});

	function getImages(){
		dataSvc.getImageList().then( (resp) => $scope.images = resp );
	}

	$scope.formError = false;
	$scope.saveCreds = function(){
		if(!$scope.credsForm.$valid){
			$scope.formError = true;
		}else{
			dataSvc.createOrUpdateRecord({"table":"accounts","record":$scope.account});
			for(key in $scope.account.prefs){
				let rec = $scope.account.prefs[key];
				if(key == 'basysPrivateKey'){
					$http({
						"url": '/er_encrypt_nohash.php',
						"method": 'POST',
						"data": $.param({"value":rec.value}),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
					}).then(function(response){
						let recCopy = angular.copy(rec);
						recCopy.value = response.data;
						dataSvc.createOrUpdateRecord({"table":"preferences","record":recCopy});
					});
				}else{
					dataSvc.createOrUpdateRecord({"table":"preferences","record":rec});
				}
			}
			
			if($scope.account.prefs.basysEnabled.value == '1')
				$scope.account.paymentData.enable_online_pymts = '0'
			
			dataSvc.createOrUpdateRecord({"table":"account_magicwrighter_info","record":$scope.account.paymentData})
			.then(function(resp){
				$scope.formError = false;
				if(!$scope.account.paymentData.id){
					$scope.account.paymentData.id = resp;
				}
				erSvc.easyRegAlert({"text":"Changes Saved","title":"Success"});
				savedAccount = $scope.account;
			});
		}
	};

	$scope.uploadImage = function(){
		erSvc.loadingDialog("Uploading Image");
		erSvc.uploadDocument($('#imageInput'), 'img/account' + $scope.accountid).then(function(res){
			if(res == 'success'){
				erSvc.easyRegAlert({"text":"Your image has been uploaded","title":"Success"}, true);
				getImages();
			}else{
				erSvc.easyRegAlert({"text":"Error. Please contact support.","title":"Error"});
			}
			erSvc.closeLoading();
			$scope.imageName = '';
		});
	};

	$(document).on('change', ':file', function() {$scope.selectImage($(this));});

	$scope.selectImage = function(input){
		$scope.$apply(function(){
			$scope.imageName = input.val().replace(/\\/g, '/').replace(/.*\//, '');;
		});
	};

	$scope.saveAccountLogo = function(){
		erSvc.loadingDialog();
		var updateRec = {"web_logo":$scope.account.web_logo,"id":$scope.accountid};
		dataSvc.createOrUpdateRecord({"table":"accounts","record":updateRec}).then(erSvc.closeLoading);
		$http({
			"url": '/set_session_account.php',
			"method": 'POST',
			"data": $.param({"accountLogo":$scope.account.web_logo}),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		});
		angular.element($('[ng-controller="navController"]')[0]).scope().logoSrc = $scope.account.web_logo;
	};

	$scope.promptDelete = function(img){
		$scope.selectedImage = img;
		erSvc.easyRegConfirm({"text":"","title":"Delete Image"},"Confirm - Delete","Cancel").then(function(res){
			if(res) $scope.confirmDelete();
		});
	};

	$scope.confirmDelete = function(){
		$http({
			"url": "/deleteDocument.php",
			"method": "POST",
			"data": $.param({"document":'img/account' + $scope.accountid + '/' + $scope.selectedImage.name}),
			"headers": {"Content-Type": "application/x-www-form-urlencoded"}
		}).then(function(response){
			if(response.data == 'success'){
				erSvc.easyRegAlert({"text":"The image has been deleted.","title":"Image Deleted"}, true);
				getImages();
			}else{
				erSvc.easyRegAlert({"text":"Error Deleting Image","title":"ERROR"});
			}
		});
	};

	$scope.validateSlug = function(){
		let valid = /^[a-zA-Z0-9_-]+$/.test($scope.account.slug);
		$scope.account.slug = $scope.account.slug.replace(/[^a-zA-Z0-9_-]/g,'');
		$scope.account.slug = $scope.account.slug.replace(/\s/g,'');
		if(!valid){
			let txt = "No white-space or special characters allowed except underscore ( _ ) and hyphen ( - )"
			erSvc.easyRegAlert({"text":txt,"title":"Invalid Identifier"});
		}
	};

	let usedSlugs = [];
	dataSvc.getArray({'query':'usedAcctSlugs'}).then(resp => usedSlugs = resp.map(s => s.slug));

	$scope.checkSlugStatus = function(){
		if(usedSlugs.includes($scope.account.slug)){
			$scope.account.slug = '';
			let txt = "This identifier is already in use.  Please choose another."
			erSvc.easyRegAlert({"text":txt,"title":"Identifier In Use"});
		}
	};

	$scope.selectedTab = $location.$$hash;
});//end controller
