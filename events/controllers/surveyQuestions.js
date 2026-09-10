regApp.controller('surveyQuestions', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.userid = erSessionData.userid;
	$scope.accountid = erSessionData.useraccount;
	$scope.eventid = erSessionData.curEvent.id;

	$scope.publishDate = erSvc.mySqlToLocalDate(erSessionData.curEvent.survey_date);
	let questionsRetrieved = $q.defer();
	let evtQuestionsRetrieved = $q.defer();

	//get account questions
	$scope.questions = [];
	dataSvc.getTableRecords('survey_questions', 'accountid = ' + $scope.accountid, true, $scope.eventid)
	.then(function(res){
		$scope.questions = res;
		questionsRetrieved.resolve();
	});

	//get questions questions for this event
	let eventQuestions = { "eventid":$scope.eventid, "questions":""	};
	dataSvc.getTableRecords('survey_evt_association', 'eventid = ' + $scope.eventid, false, $scope.eventid)
	.then(function(res){
		if(res[0]) eventQuestions = res[0];
		evtQuestionsRetrieved.resolve();
	});

	$q.all([questionsRetrieved.promise,evtQuestionsRetrieved.promise]).then(function(){
		eventQuestions.questions.split(',').forEach(function(q){
			if($scope.questions[q]) $scope.questions[q].active = true;
		});
	});

	$scope.selectAll = function(assoc){
		angular.forEach($scope.questions, function(q){ if(q.assoc == assoc) q.active = true; });
	};

	$scope.clearAll = function(assoc){
		angular.forEach($scope.questions, function(q){ if(q.assoc == assoc) q.active = false; });
	};

	$scope.save = function(){
		erSvc.loadingDialog();
		let selectedQuestions = [];
		angular.forEach($scope.questions,function(q){
			if(q.active) selectedQuestions.push(q.id);
		});
		eventQuestions.questions = selectedQuestions.toString();
		dataSvc.createOrUpdateRecord({"table":"survey_evt_association","record":eventQuestions})
		.then(function(res){
			erSvc.closeLoading();
			if(!eventQuestions.id) eventQuestions.id = res;
			dataSvc.createOrUpdateRecord({
				"table":"events",
				"record":{"id":erSessionData.curEvent.id,"survey_date":$scope.publishDate}
			},$scope.eventid);
			if($scope.publishDate){
				erSessionData.curEvent.survey_date = erSvc.localToMySqlDate($scope.publishDate);
				//update PHP session for current event data
				$http({
					"url": '/setUserData.php',
					"method": 'POST',
					"data": $.param({"curEvent":erSessionData.curEvent}),
					"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
				});
			}
			erSvc.easyRegAlert({"text":"Your settings have been saved","title":"Updates Successful"},3000);
		});
	};
});//End Controller

regApp.filter('typeFilter', function() {
	return function(type) {
		if(type=='freeForm') return "Free-Form";
		if(type=='numeric') return "1-5";
		if(type=='yesNo') return "Yes/No";
		return type.charAt(0).toUpperCase() + type.slice(1);
	};
});