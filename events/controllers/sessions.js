regApp.controller('sessions', function($scope, $http, $timeout, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Sessions")').addClass('active');
	}, 100);
	var svc = erSvc;
	var eventid = erSessionData.curEvent.id;
	dataSvc.getObject({'query':'eventSessions','eventid':eventid}).then(function(resp){
		var curDt;
		angular.forEach(resp,function(session){
			curDt = session.starttime.split(' ')[0];
			session.startdate = svc.mySqlToLocalDate(curDt);
			curDt = session.endtime.split(' ')[0];
			session.enddate = svc.mySqlToLocalDate(curDt);
		});
		$scope.sessions = resp;
	});

	$scope.saveChanges = function(){
		angular.forEach($scope.sessions,function(sess){
			if(sess.id < 0 && !sess.name) delete $scope.sessions[sess.id];
		});
		$timeout(function(){ //brief timeout to let form validity reset if needed
            finishSave();
        },500);
	};

	function finishSave(){
		$scope.changesSaved = false;
		$('form').addClass('submitted');
		$scope.submitClicked = true;
		if(!$scope.sessionForm.$valid) return;
		angular.forEach($scope.sessions,function(sess){
			if(sess.id < 0){
				var origId = sess.id;
				delete sess.id;
			}
			sess.starttime = (svc.createDatetimeString(sess.startdate, sess.displayStart));
			sess.endtime = (svc.createDatetimeString(sess.enddate, sess.displayEnd));
			dataSvc.createOrUpdateRecord({"table":"sessions","record":sess,eventid})
			.then(function(res){
				if(res > 0){
					sess.id = res;
					$scope.sessions[res] = angular.copy(sess);
					delete $scope.sessions[origId];
				}
			});
		});
		$scope.changesSaved = true;
		$timeout(function(){
            $scope.changesSaved = false;
        },2000);
	}

	$scope.removeSession = function(sess){
		if(sess.id < 0){
			delete $scope.sessions[sess.id];
			return;
		}
		svc.easyRegConfirm({"text":"Remove Session?","title":"Confirm Delete"},
			"Yes - Remove","Cancel",)
		.then(function(res){
			if(res){
				if(sess.id > 0) dataSvc.deleteRecord({"table":"sessions","id":sess.id},eventid);
				delete $scope.sessions[sess.id];
			}
		});
	}

	var newEvtId = 0;
	$scope.newSession = function(){
		if(!$scope.sessionForm.$valid) return;
		$scope.changesSaved = false;
		$scope.submitClicked = false;
		$('form').removeClass('submitted');
		newEvtId--;
		var newStart = 3000 + Object.keys($scope.sessions).length;
		newStart = newStart.toString();
		$scope.sessions[newEvtId] = {"starttime":newStart,"eventid":eventid,"id":newEvtId};
		setTimeout(function(){
			$('.sessionName:last').focus();
		}, 300);
	};

	$scope.endTimeBlur = function($event){
		if($($event.target).is('.endtime:last')) $scope.newSession();
	};

	$scope.startChange = function(session){
		if(session.startdate.split('/').length == 3 && !session.enddate){
			session.enddate = session.startdate;
		}
	};
});//end controller