regApp.controller('acmeMgmt', function($scope, $http, dataSvc, erSvc) {
	dataSvc.getTableRecords('acme_access_requests', '', true).then(res => {
		$scope.requests = res;
		angular.forEach(res,req => {if(req.approved == '0') $scope.hasUnapproved = true});
	});
	$scope.show = 0;
	$scope.saveRequests = function(){
		let updates = 0;
		let total = 0;
		angular.forEach($scope.requests,function(request){
			if(!request.changed) return;
			total++;
			dataSvc.createOrUpdateRecord({"table":"acme_access_requests","record":request}).then(function(){
				if(++updates == total) erSvc.easyRegAlert({"text":"Approvals Sent","title":"Updates Saved"});
			});
			if(request.approved != 1) return;
			let subject = "ACME Plugin Access Request Approved";
			let body = `Your request for access to ACME PowerSchool Plugins at EasyRegPro has been approved. <br/>
			You may download the plugins at https://easyregpro.com/acme.php?auth=${request.email}`;
			erSvc.sendEmail(request.email, subject, body);
		});
	}

	$scope.deleteUnapproved = function(){
		let title = "Delete Unapproved Requests";
		let txt = "Please confirm you would like to delete all unapproved requests";
		erSvc.easyRegConfirm({"text":txt,"title":""},"Delete Requests","Cancel")
		.then(function(res){
			if(res){
				let updates = 0;
				let total = 0;
				angular.forEach($scope.requests,function(req){
					if(req.approved == 1) return;
					total++;
					dataSvc.deleteRecord({"table":"acme_access_requests","id":req.id}).then(function(){
						if(++updates == total) erSvc.easyRegAlert({"text":"Requests Deleted","title":"Updates Saved"});
					});
					delete $scope.requests[req.id];
				});
			}
		});
	};

	$scope.exportResults = function(){
		let data = [];
		data.push(["Name","District","Email","Approved"]);
		let newRow = [];
		let clean = (str) => str ? str.replace(/#/g,'').replace(/"/g,"'") : '';
		angular.forEach($scope.requests,function(req){
			newRow = [];
			newRow.push(`"${clean(req.last_name)}, ${clean(req.first_name)}"`);
			newRow.push(`"${clean(req.district)}"`);
			newRow.push(`"${req.email}"`);
			newRow.push(`"${req.approved == '1' ? 'Yes' : 'No'}"`);
			data.push(newRow.join(','));
		});
		arrayToCsv(data, 'ACME Requests');
	};
});//end controller