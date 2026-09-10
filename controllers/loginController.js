var navApp = angular.module('loginMod', ['easyRegDataModule', 'erSvc']);
navApp.controller('loginController', function($http, $scope, $attrs, dataSvc, erSvc) {
	erSvc.getAccountIdFromURL().then(function(acctId){
		if(acctId){
			$scope.accountid = acctId;
			$('#resetPwLink').attr('href', $('#resetPwLink').attr('href') + '?accountid=' + acctId);
		}else{
			$scope.accountid = localStorage.getItem('easyRegLastLoginAcct');
		}
	});
	dataSvc.getArray({'query':'accountList'}).then(resp => $scope.accounts = resp );

	$scope.getAccountName = function(){
		if(!$scope.accountid) return;
		let name = '';
		angular.forEach($scope.accounts,function(acct){
			if(acct.id == $scope.accountid) name = acct.name;
		});
		return name;
	};
	let loginTries = 0;
	$scope.login = function(){
		if(!$scope.loginform.$valid) return false;
		localStorage.setItem("easyRegLastLoginAcct",$scope.accountid);
		erSvc.loadingDialog();
		$http({
			"url": '/login_process.php',
			"method": 'POST',
			"data": $.param({"accountid":$scope.accountid,"email":$scope.email,"pass":$scope.pass}),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		}).then(function(response){
			console.log("res: ", response)
			if(response.data == 'success'){
				dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
					console.log(resp)
					if(resp[0]){
						let userData = resp[0];
						for(prop in userData){
							console.log(userData[prop])
							if(userData[prop]) userData[prop] = (userData[prop] || '').toString().replace(/\"/g, '');
						}
						$http({
							"url": '/setUserData.php',
							"method": 'POST',
							"data": $.param({"userData":userData}),
							"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
						}).then(function(res){
							console.log("R2: ", res)
							if($attrs.stayOnPg){
								erSvc.easyRegAlert({"text":"You are now logged in","title":"Success"});
								document.dispatchEvent(new CustomEvent("loginSuccess"));
								return;
							}
							erSvc.closeLoading();
							if(userData.master!='1' && userData.security_groups=='') window.location = '/user_events.php';
							else window.location = '/admin.php#!/event_management';
						});
					}
				});
			}
			else{
				$('.alert-danger').show();
				erSvc.closeLoading();
				if(++loginTries > 10) $('body').empty();
			}
		});
	}
});