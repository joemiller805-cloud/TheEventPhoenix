regApp.controller('surveyMgmt', function($scope, $http, dataSvc, erSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;
	
	dataSvc.getTableRecords('survey_questions', 'accountid = ' + $scope.accountid, true).then(function(res){
		$scope.currentQuestions = res;
		angular.forEach($scope.currentQuestions,function(q){
			q.options = q.options || '';
			q.optionList = q.options.split('**');
		});
		$('#eventQuestionsTbody').sortable({
			axis: "y",
			handle: ".handle",
			opacity: 0.7,
			revert: true,
			update: function( event, ui ) {
				$('#eventQuestionsTbody tr').each(function(idx, el){
					let question = angular.element(el).scope().$parent.question;
					question.sortorder = idx;
					dataSvc.createOrUpdateRecord({"table":"survey_questions","record":question});
				});
				$scope.$applyAsync();
			}
		});
		$('#sectionQuestionsTbody').sortable({
			axis: "y",
			handle: ".handle",
			opacity: 0.7,
			revert: true,
			update: function( event, ui ) {
				$('#sectionQuestionsTbody tr').each(function(idx, el){
					let question = angular.element(el).scope().$parent.question;
					question.sortorder = idx;
					dataSvc.createOrUpdateRecord({"table":"survey_questions","record":question});
				});
				$scope.$applyAsync();
			}
		});
	});

	$scope.curQuestion = { "accountid":$scope.accountid };

	$scope.newQuestion = function(type){
		$scope.selectedQuestion = null;
		$scope.dialogTitle = "Add " + type + " Question";
		$scope.curQuestion.id = null;
		$scope.curQuestion.text = '';
		$scope.curQuestion.type = 'numeric';
		$scope.curQuestion.options = '';
		$scope.curQuestion.optionList = [""];
		$scope.curQuestion.assoc = type == "Event" ? 'event' : 'section';
		$scope.curQuestion.sortorder = $scope.nextQuestionSort($scope.curQuestion.assoc);
		$('#questionDialog').show(400);
	};

	$scope.editQuestion = function(question){
		$scope.dialogTitle = "Edit Question";
		$scope.selectedQuestion = question;
		$scope.curQuestion = angular.copy(question);
		$('#questionDialog').show(400);
	}

	$scope.updateQuestion = function(){
		if($scope.selectedQuestion) $scope.currentQuestions[$scope.selectedQuestion.id] = {...$scope.curQuestion};
		if(!$scope.curQuestion.text){
			erSvc.easyRegAlert({"text":"Please enter question text","title":"Unable to Create Question"});
			return;
		}
		$scope.curQuestion.options = $scope.curQuestion.optionList.join('**');
		dataSvc.createOrUpdateRecord({"table":"survey_questions","record":$scope.curQuestion}).then(function(resp){
			if(resp > 0) {
				$scope.curQuestion.id = resp;
				$scope.currentQuestions[resp] = {...$scope.curQuestion};
				$scope.$applyAsync();
			}
			$scope.closeRightDialog();
		});
	};

	$scope.deleteQuestion = function(question){
		let curSort = question.sortorder;
		//reduce sort order for subsequent questions
		angular.forEach(getQuestionsByAssoc(question.assoc),function(q){
			if(q.sortorder > curSort){
				q.sortorder = Number(q.sortorder) - 1;
				dataSvc.createOrUpdateRecord({"table":"survey_questions","record":q});
			}
		});
		dataSvc.deleteRecord({"table":"survey_questions","id":question.id}).then(function(){
			delete $scope.currentQuestions[question.id];
			$scope.$applyAsync();
		});
	};

	$scope.nextQuestionSort = function(assoc){
		let largest = 0;
		angular.forEach($scope.currentQuestions,function(question){
			if(question.assoc == assoc && question.sortorder > largest) largest = Number(question.sortorder)
		});
		return largest + 1;
	};

	function getQuestionsByAssoc(assoc){
		let response = [];
		angular.forEach($scope.currentQuestions,function(q){
			if(q.assoc == assoc) response.push(q);
		});
		return response;
	}

	$scope.closeRightDialog = function(){ $('#questionDialog').hide(400); };
});//end controller

regApp.filter('typeFilter', function() {
	return function(type) {
		if(type=='freeForm') return "Free-Form";
		if(type=='numeric') return "1-5";
		if(type=='yesNo') return "Yes/No";
		return type.charAt(0).toUpperCase() + type.slice(1);
	};
});