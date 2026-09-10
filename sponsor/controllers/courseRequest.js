regApp.controller('courseRequestCtrl', function($scope, $http, $q, accountid, dataSvc, erSvc) {
	$scope.submitProposal = function(){
		if($scope.courseRequestForm.$valid){
			var selectedStaff = [];
			angular.forEach($scope.vendor.staff,function(member){
				if(member.selected) selectedStaff.push(member.id);
				member.selected = false;
			});
			$scope.newCourse.sponsor_staff = selectedStaff.toString();
			$scope.newCourse.sponsorid = $scope.vendor.id;
			dataSvc.createOrUpdateRecord({
				"table":"course_proposals",
				"record":$scope.newCourse})
			.then(function(res){
				$scope.newCourse.title = '';
				$scope.newCourse.description = '';
				$scope.newCourse.sponsor_staff = '';
				erSvc.easyRegAlert({
					"text":"Thank you for your proposal.  This proposal will be reviewed by event facilitators.",
					"title":"Course Proposal Submitted"
				});
			});
		}
		else{
			erSvc.easyRegAlert({
				"text":"Please Complete Title and Description",
				"title":"Submission Error"
			});
		}
	};	
});//End Controller
