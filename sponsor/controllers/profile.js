regApp.controller('profile', function($scope, $http, $q, accountid, dataSvc, erSvc){
	var currentUsernames = [];
	dataSvc.getArray({'query':'sponsorUsernames'}).then(function(res){
		angular.forEach(res,function(username){
			currentUsernames.push(username.username);
		});
	});

	if($scope.vendor) getCategories();
	else $scope.$watch('vendor',getCategories);

	document.querySelectorAll('.imageUpload').forEach(element => {
		element.addEventListener('change', function(e) {
			const file = this.files[0];
			if(!file) return;
			const allowedTypes = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
			if (!allowedTypes.includes(file.type)) {
				let msg = 'Only image files (JPEG, PNG, GIF, WEBP, SVG) are allowed.';
				erSvc.easyRegAlert({"text":msg,"title":"Invalid File Type"});
				this.value = ''; // Reset the input field
			}
			if(file.type === 'image/svg+xml'){
				const reader = new FileReader();
				reader.onload = function(e) {
					if(!isSafeSVG(e.target.result)) erUtils.logout();
				};
				reader.readAsText(file);
			} 
		});
	});

	function isSafeSVG(svgString) {
		const forbiddenTags = /<(script|iframe|foreignObject|embed|object)/i;
		const forbiddenAttributes = /\bon\w+="[^"]*"/i; // Event handlers like onclick, onload
		const forbiddenXLinks = /xlink:href=["'][^"']*["']/i; // External links in SVGs
		if (forbiddenTags.test(svgString) || forbiddenAttributes.test(svgString) || forbiddenXLinks.test(svgString)) {
			return false;
		}
		return true;
	}

	$scope.showCategory = false;
	$scope.categorySelect = "";
	function getCategories(){
		if(!$scope.vendor) return;
		let filter = 'accountid = ' + $scope.accountid + ' AND name =' +  "'vendorCategories'";
		dataSvc.getTableRecords('preferences',  filter).then(function(res){
			if(res[0]){
				try{ 
					$scope.categorySettings = angular.fromJson(res[0].value); 					
					if($scope.categorySettings.allowOther || $scope.categorySettings.categories.length)
						$scope.showCategory = true;
					$scope.categorySelect = $scope.vendor.category;
					if($scope.categorySelect && !$scope.categorySettings.categories.includes($scope.categorySelect)){
						$scope.categorySelect = 'Other';
					}
				}
				catch(e){console.log(e)}
			}
		});
	}

	$scope.categoryChange = function(){
		if($scope.vendor && $scope.categorySelect != 'Other') 
			$scope.vendor.category = $scope.categorySelect;
	};
	

	$scope.updateSponsor = function(){
		if(currentUsernames.indexOf($scope.vendor.username) >= 0 && !$scope.vendor.id){
			var msg = "This username is already in use. Please choose another";
			erSvc.easyRegAlert({"text":msg,"title":"Cannot Create Account"});
			return;
		}
		if($scope.sponsorForm.$valid){
			erSvc.loadingDialog("Saving Sponsor Data");
				dataSvc.createOrUpdateRecord({"table":"sponsors","record":$scope.vendor}).
				then(function(res){
					erSvc.closeLoading();
				});
		}else{
			erSvc.easyRegAlert({"text":"Please Complete Required Fields","title":"Submission Error"});
		}
		$('form[name="sponsorForm"]').addClass('submitted');
	};

	$(document).on('change', ':file', function() {
		$scope.selectImage($(this));
	});

	$scope.selectImage = function(input){
		$scope.$apply(function(){
			$scope.vendor[input.attr('data-target')] = input.val().replace(/\\/g, '/').replace(/.*\//, '');
		});
	};

	$scope.uploadImage = function(which){
		erSvc.loadingDialog("Uploading Image");

		var folder = 'img/account' + $scope.accountid + '/vendorLogos/' + $scope.vendor.id;
		erSvc.uploadDocument($(`[data-target="${which}"]`), folder).then(function(res){
			if(res == 'success'){
				erSvc.easyRegAlert({"text":"Your image has been uploaded","title":"Success"}, true);
				let src = '/img/account' +	$scope.accountid + '/vendorLogos/' + $scope.vendor.id + '/' + $scope.vendor[which];
				if(which == 'logo') $('#vendorImage').attr('src', src);
				else $('#vendorImage2').attr('src', src);
				let logoUpdate = {"id":$scope.vendor.id, "logo":$scope.vendor.logo, "logo2":$scope.vendor.logo2}
				dataSvc.createOrUpdateRecord({"table":"sponsors","record":logoUpdate}).then(function(res){
					erSvc.closeLoading();
				});
			}else{
				erSvc.easyRegAlert({"text":"Error. Please contact support.","title":"Error"});
				erSvc.closeLoading();
			}
		});
	};

	$scope.openPwReset = () => $('#pwResetDialog').show(500);

	$scope.resetPassword = function(){
		if(!erSvc.validatePassword($scope.newPassword)) return;
		erSvc.loadingDialog();
		$scope.noPasswordMatch = $scope.newPassword != $scope.newPasswordConfirm;
		if(!$scope.pwResetForm.$valid || $scope.noPasswordMatch){
			erSvc.closeLoading();
			return;
		}
		erSvc.verifyPassword($scope.currentPassword).then(function(res){ // Server password_verify; bcrypt cannot be compared in JS
			$scope.badCurrentPw = (res != '1' && res != 1); // er_encrypt verify prints 1/0
			if($scope.badCurrentPw){
				erSvc.closeLoading();
				return;
			}
			erSvc.encrypt($scope.newPassword).then(function(hashedPw){ // New hash is bcrypt from the server
				var updateData = {"id":$scope.vendor.id,"pass":hashedPw};
				dataSvc.createOrUpdateRecord({"table":"sponsors","record":updateData}).then(function(resp){
					if(resp == 'error'){
						erSvc.easyRegAlert({"text":"There was an error with your update. Please contact the event administrator.","title":"Error"});
						erSvc.closeLoading();
					}else{
						var subject = "The Event Phoenix Password Reset Notification"; // Sweep B legal name
						var body = "The password for the " + $scope.vendor.name + " \
							The Event Phoenix sponsor account has been reset. \
							If you did not request this action, please contact the site administrator.";
						erSvc.sendEmail($scope.vendor.email, subject, body,$scope.replytoemail).then(function(){
							erSvc.closeLoading();
						});
						$('#passwordResetConfirm').slideDown(500).delay(5000).slideUp(500);
					}
				});
			});
		});
	}; //end resetPassword()

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);
});
