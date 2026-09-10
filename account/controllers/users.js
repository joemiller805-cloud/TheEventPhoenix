regApp.controller('users', function($scope, $http, dataSvc, erSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;

	$scope.states = erSvc.getStateOptions();
	$scope.statusFilter = '0';
	$scope.users = {};
	dataSvc.getObject({'query':'getUsers'}).then(function(users){
		$scope.users = users;
		angular.forEach($scope.users,function(user){
			if(user.email == 'support@easyregpro.com'){
				delete $scope.users[user.id];
				return;
			}
			$.each(user, function(key, value){ if(!value) user[key] = ''; });
			user.archived = user.archived || '0';
			user.pass = '';
			user.searchStr = `${user.last_name}${user.first_name}${user.web_address}${user.phone}${user.business}${user.email}`;
			user.searchStr = user.searchStr.toLowerCase();

			if(user.security_groups) user.security_groups = user.security_groups.split(',');
			else user.security_groups = [];
		});
	});

	$scope.pymt_methods = [];
	let prefFilter = `accountid=${$scope.accountid} AND name='expensePymtMethods'`;
	dataSvc.getTableRecords('preferences', prefFilter).then(function(res){
		if(res[0]){
			let prefEntry = res[0];
			try{ $scope.pymt_methods = JSON.parse(prefEntry.value);}
			catch(e){ console.error(e); $scope.pymt_methods = []; }
		} 
	});

	dataSvc.getTableRecords('security_groups', 'accountid = ' + $scope.accountid, true)
	.then(function(res){
		$scope.securityGroups = res;
	});

	$scope.replytoemails = [];
	dataSvc.getArray({'query':'getCurrentUserData'}).then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].email);
	});

	dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
		if(resp[0]) $scope.replytoemails.push(resp[0].email);
	});

	$scope.searchVal = '';
	$scope.meetsSearch = function(user){
		if($scope.searchVal.length < 3) return true;
		let val = $scope.searchVal.toLowerCase();
		return user.searchStr.indexOf(val) >= 0;
	}

	$scope.userHasGroup = function(groupid){
		if(!$scope.editUser || !$scope.editUser.security_groups) return false;
		return $scope.editUser.security_groups.includes(groupid);
	};

	$scope.changeUserGroup = function(groupid){
		if(!$scope.editUser) return;
		let groupList = $scope.editUser.security_groups;
		if(groupList.includes(groupid)) groupList.splice(groupList.indexOf(groupid),1);
		else groupList.push(groupid);
	};

	$scope.getAddress = function(user){
		if(user.address1 == '' && user.address2 == ''){
			return '';
		}else{
			return user.address1 + ' ' + user.address2 + ' ' + user.city + ', ' + user.state + ' ' + user.zip;
		}
	};

	$scope.getHomeAddress = function(user){
		if(user.home_add1 == '' && user.home_add2 == ''){
			return '';
		}else{
			return user.home_add1 + ' ' + user.home_add2 + ' ' + user.home_city + ', ' + user.home_state + ' ' + user.home_zip;
		}
	};

	$scope.updateUser = function(user){
		if(user) $scope.editUser = angular.copy(user);
		else $scope.editUser = getEmptyUser();
		$scope.editUser.pass = '';
		$scope.editUser.photo = $scope.editUser.photo || '';
		$scope.selectedUser = $scope.editUser.id;
		$scope.resetPassword = false;
		$('#editDialog').show(500);
	};

	$scope.closeRightDialog = function(){
		$(".dialogRight").hide(500);
	};

	$scope.saveUser = function(){
		$scope.editUser.accountid = $scope.accountid;
		var passwordSet = $.Deferred();
		if($scope.resetPassword){
			if(!erSvc.validatePassword($scope.editUser.pass)) return;
			erSvc.encrypt($scope.editUser.pass).then(function(data){
				$scope.editUser.pass = data;
				passwordSet.resolve();
			});
		}else{
			delete $scope.editUser.pass;
			passwordSet.resolve();
		}

		$.when(passwordSet).then(function(){
			var newUser = $scope.editUser.id == "-1";
			if(newUser) delete $scope.editUser.id;
			dataSvc.createOrUpdateRecord({"table":"users","record":$scope.editUser}).then(function(resp){
				if(newUser){
					$scope.users[resp] = angular.copy($scope.editUser);
					$scope.users[resp].id = resp;
				}else{
					$scope.users[$scope.selectedUser] = angular.copy($scope.editUser);
				}
				$('#editDialog').hide(500);
				$scope.$applyAsync();
			});
		});
	}

	$scope.startEmail = () => $('#emailDialog').show(500);

	$scope.setIncludeEmail = function(include){
		angular.forEach($scope.users,function(user){
			user.includeInEmail = include && (user.archived == $scope.statusFilter);
		});
	};

	$scope.sendEmail = function(){
		erSvc.loadingDialog();
		var addresses = [];
		angular.forEach($scope.users,function(user){
			if(user.includeInEmail)	addresses.push(user.email);
		});

		let emailSentData = {
			"accountid": $scope.accountid,
			"author":erSessionData.userData.first_name + ' ' + erSessionData.userData.last_name,
			"subject": $scope.emailSubject,
			"message": $scope.emailBody,
			"recipients":addresses.toString()
		};

		dataSvc.createOrUpdateRecord({"table":"emails_sent","record":emailSentData});

		erSvc.sendEmail(addresses.toString(), $scope.emailSubject, $scope.emailBody, $scope.replytoemail)
		.then(function(){
			$(".dialogRight").hide(500);
			erSvc.closeLoading();
		});
		if(addresses.length == 0) erSvc.closeLoading();
	};

	function getEmptyUser(){
		return{
			"id":"-1", "email":"", "first_name":"", "last_name":"", "pass":"",
			"master":"", "business":"", "address1":"", "address2":"", "city":"",
			"state":"", "zip":"", "phone":"", "archived":"0", "bio":"","photo":"",
			"web_address":"","security_groups":[]
		}
	};

	$(document).on('change', ':file', function() {
		$scope.selectImage($(this));
	});

	$scope.selectImage = function(input){
		erSvc.loadingDialog();
		if($scope.editUser.photo){
			$http({
				"url": "/deleteDocument.php",
				"method": "GET",
				"params": {"document":$scope.editUser.photo.substr(1)}
			});
		}
		var imgDestination = "img/account" + $scope.accountid + "/users";
		var imgName =  input.val().replace(/\\/g, '/').replace(/.*\//, '');
		erSvc.uploadDocument($('#imageInput'), imgDestination).then(function(){
			var newUserData = {
				"id":$scope.editUser.id,
				"photo": $scope.editUser.photo
			}
			dataSvc.createOrUpdateRecord({"table":"users","record":newUserData})
			.then(function(){
				$('#userImg').attr("src",$scope.editUser.photo);
				erSvc.closeLoading();
				$scope.editUser.photo = "/" + imgDestination + "/" + imgName;
			});
		});
	};
});//end controller