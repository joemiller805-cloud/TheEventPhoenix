regApp.controller('surveyResponses', function($scope, $http, $q, dataSvc, erSvc) {
	$scope.sortField = 'attendeeLast';
	$scope.sortReverse = false;
	$scope.questions = [];
	$scope.attendees = [];
	$scope.courses = [];
	$scope.events = [];
	$scope.presenters = [];
	$scope.filteredResponses = [];
	$scope.pageNumber = 1;
	dataSvc.getArray({'query':'surveyResponses','eventStatus':'recent'}).then(function(resp){
		$scope.responses = resp;
		angular.forEach($scope.responses,function(r){
			r.course = r.course || '';
			r.presenternames = r.presenternames || '';
			r.attendeeLast = r.attendeeLast || r.registrationid;
			r.attendeeFirst = r.attendeeFirst || r.registrationid;
			r.lastFirst = r.attendeeLast + ', ' + r.attendeeFirst;
			if($scope.attendees.indexOf(r.lastFirst) < 0) $scope.attendees.push(r.lastFirst);
			if($scope.questions.indexOf(r.question) < 0) $scope.questions.push(r.question);
			if($scope.courses.indexOf(r.course) < 0 && r.course) $scope.courses.push(r.course);
			if($scope.events.indexOf(r.event) < 0) $scope.events.push(r.event);
			if($scope.presenters.indexOf(r.presenternames) < 0 && r.presenternames) $scope.presenters.push(r.presenternames);
			$scope.filteredResponses.push(r);
		});
		$('#loadingSpan').remove();
		erSvc.initializeColumns($('#responseTable'));
		getSummaryData();
		groupToPages();
	});

	$scope.pageSize = 50;
	// calculate page in place
	groupToPages = function(){
		$scope.pageNumber = 1;
	    $scope.pagedResponses = [];
	    for (var i = 0; i < $scope.filteredResponses.length; i++) {
	        if(i % $scope.pageSize === 0) {
	            $scope.pagedResponses[Math.floor(i / $scope.pageSize)] = [$scope.filteredResponses[i]];
	        }else{
	            $scope.pagedResponses[Math.floor(i / $scope.pageSize)].push($scope.filteredResponses[i]);
	        }
	    }
	    updateCols();
	};

	$scope.changePage = function(val){
		$scope.pageNumber += val;
		updateCols();
	};

	function updateCols(){
		setTimeout(function(){
			$scope.$apply(function(){
				erSvc.initializeColumns($('#responseTable'));
			});
		}, 200);
	}

	$scope.columnFilter = function(){
		erSvc.columnFilter($('#responseTable'));
	};

	$scope.changeSort = function(field){
		if($scope.sortField == field){
			$scope.filteredResponses.reverse();
		}else{
			$scope.filteredResponses.sort(function(a,b){
				return(a[field].toLowerCase() > b[field].toLowerCase() ? 1 : -1);
			});
		}
		$scope.sortField = field;
		groupToPages();
	};

	$scope.filters = {
		attendee:{"val":"","prop":"lastFirst","label":"Attendee","options":$scope.attendees},
		course:{"val":"","prop":"course","label":"Course","options":$scope.courses},
		event:{"val":"","prop":"event","label":"Event","options":$scope.events},
		presenter:{"val":"","prop":"presenternames","label":"Presenter","options":$scope.presenters},
		assoc:{"val":"","prop":"questionAssoc","label":"Question Type","options":['Section','Event']},
		type:{"val":"","prop":"questionType","label":"Answer Type","options":["Text","Yes/No","Numeric"]},
		type:{"val":"","prop":"question","label":"Question","options":$scope.questions}
	}
	$scope.quickSearch = "";

	$scope.filter = function(){
		$scope.filteredResponses = [];
		let noneFound = true;
		let s = $scope.quickSearch.toLowerCase();
		for(let i = 0; i < $scope.responses.length; i++){
			let r = $scope.responses[i];
			r.show = true;
			if(s && r.confirmation){
				r.show = (
					r.event.toLowerCase().indexOf(s)>=0 ||
					r.lastFirst.toLowerCase().indexOf(s)>=0 ||
					r.confirmation.toLowerCase().indexOf(s)>=0 ||
					r.email.toLowerCase().indexOf(s)>=0 ||
					r.course.toLowerCase().indexOf(s)>=0 ||
					r.presenternames.toLowerCase().indexOf(s)>=0 ||
					r.question.toLowerCase().indexOf(s)>=0 ||
					r.response.toLowerCase().indexOf(s)>=0
				);
			}
			if(!r.show) continue;
			angular.forEach($scope.filters,function(f){
				if(!r.show) return;
				if(f.val){
					if(f.prop == 'questionType'){
						r.show = (
							(f.val == 'Text' && r.questionType == 'freeForm') ||
							(f.val == 'Yes/No' && r.questionType == 'yesNo') ||
							(f.val == 'Numeric' && r.questionType == 'numeric')
						)
					}else{
						r.show = r[f.prop].toLowerCase().indexOf(f.val.toLowerCase()) >= 0;
					}
				}
			});
			if(r.show) $scope.filteredResponses.push(r);
		}//End response loop
		$scope.noResults = $scope.filteredResponses.length == 0;
		groupToPages();
		getSummaryData();
	};//End filter()

	function getSummaryData(){
		let yesNoTotal = 0;
		$scope.yesResponses = 0;
		$scope.noResponses = 0;
		$scope.numericTotals = {
			"1":{"count":0,"percent":0},
			"2":{"count":0,"percent":0},
			"3":{"count":0,"percent":0},
			"4":{"count":0,"percent":0},
			"5":{"count":0,"percent":0},
			"responseCount":0,
			"responseSum":0,
			"responseAve":0
		}
		let nt = $scope.numericTotals;
		for(let i = 0; i < $scope.filteredResponses.length; i++){
			let resp = $scope.filteredResponses[i];
			if(resp.questionType == 'numeric'){
				nt.responseCount++;
				nt.responseSum += (Number(resp.response) || 0);
				if(nt[resp.response]) nt[resp.response].count++;
			}else if(resp.questionType == 'yesNo'){
				yesNoTotal++;
				if(resp.response == 'yes') $scope.yesResponses++;
				else if(resp.response == 'no') $scope.noResponses++;
			}
		}
		for(let i=1; i < 6; i++){
			let ths = nt[i.toString()];
			if(nt["responseCount"] == 0) ths.percent = 0;
			else ths.percent = Math.round(ths.count / nt["responseCount"] * 100);
		}
		if(nt.responseCount == 0) nt.responseAve = 'N/A';
		else nt.responseAve = (nt.responseSum / nt.responseCount).toFixed(1);
	}

	$scope.exportResults = function(){
		let clean = (str) => str ? str.replace(/#/g,'').replace(/"/g,"'") : '';
		let data = [`"Event","Attendee","Confirmation","Email","Session","Course","Presenter(s)","Question","Response"`];

		$scope.filteredResponses.forEach(function(r){
			data.push(`"${clean(r.event)}","${clean(r.attendeeLast)}, ${clean(r.attendeeFirst)}","${clean(r.confirmation)}","${clean(r.email)}","${clean(r.session_name)}","${clean(r.course)}","${clean(r.presenternames)}","${clean(r.question)}","${clean(r.response)}"`);
		});

		arrayToCsv(data, 'Survey Responses');
	};
});// End Controller