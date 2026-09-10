regApp.controller('evtDocumentMgmt', function($scope, $http, $q, $filter, dataSvc, erSvc) {
	$scope.eventid = erSessionData.curEvent.id;
	$scope.accountid = erSessionData.useraccount;
	$scope.category = 'event';
	$scope.newDoc = {};
	let docsRetrieved = $q.defer();
	let usersRetrieved = $q.defer();

	dataSvc.getDocuments($scope.accountid, $scope.eventid).then(function(res){
		$scope.eventDocuments = res.eventDocuments;
		$scope.courses = res.courses;
		$scope.documents = res.documents;
		docsRetrieved.resolve();
	});

	dataSvc.getUsersByAccount(true, accountid).then(function(res){
		$scope.users = res;
		usersRetrieved.resolve();
	});

	$q.all([docsRetrieved.promise,usersRetrieved.promise]).then(function(){
		angular.forEach($scope.eventDocuments,function(doc){
			if($scope.users[doc.upload_userid]){
				$scope.users[doc.upload_userid].archived = '0';
			}else{
				doc.upload_userid = '';
				doc.author = '';
			}
		});
	});

	var curDocInput;
	$(document).on('change', ':file', function() {
		curDocInput = $(this);
		var newName = curDocInput.val().replace(/\\/g, '/').replace(/.*\//, '');
		//prevent upload of duplicates
		var dup = false;
		var matchedDoc;
		angular.forEach($scope.documents,function(doc){
			if(doc.filepath.replace(/\\/g, '/').replace(/.*\//, '') == newName){
				if(doc.category == $scope.category){
					dup = true;
					matchedDoc = doc;
				}
			}
		});
		if(dup){
			confirmData = {
				"text":"A document with this name already exists.  Would you like to associate the document with this " + $scope.category + '?',
				"title":"Unable to Upload Document"}
			erSvc.easyRegConfirm(confirmData,'Yes', 'No')
			.then(function(res){
				if(res){
					var newAssoc = {
						"documentid":matchedDoc.id,
						"accountid":$scope.accountid,
						"eventid":$scope.eventid
					};
					if($scope.category == 'course') newAssoc.courseid = $scope.selectedCourse.id;
					dataSvc.createOrUpdateRecord({
						"table":"document_association",
						"record": newAssoc
					},$scope.eventid).then(function(res){
						newAssoc.id = res;
						if($scope.category == 'event'){
							matchedDoc.eventAssociations[newAssoc.id] = newAssoc;
							$scope.eventDocuments[matchedDoc.id] = matchedDoc;
						}else{
							matchedDoc.courseAssociations[newAssoc.id] = newAssoc;
							$scope.courses[newAssoc.courseid].documents[matchedDoc.id] = matchedDoc;
							$scope.courses[newAssoc.courseid].visibleDocuments[matchedDoc.id] = matchedDoc;
						}
					});
				}
			});
			curDocInput.val('');
		}else{
			 $scope.selectDocument();
		}
	});

	$scope.selectDocument = function(){
		$scope.$apply(function(){
			$scope.newDoc.name = curDocInput.val().replace(/\\/g, '/').replace(/.*\//, '');
			$scope.newDoc.description = '';
			$scope.newDoc.upload_userid = '';
		});
	};

	$scope.uploadDoc = function(){
		erSvc.loadingDialog("Uploading Document");
		var filename = curDocInput[0].files[0].name;
		var folder = `documents/account${$scope.accountid}/${$scope.category}_docs`;
		erSvc.uploadDocument(curDocInput, folder).then(function(res){
			if(res == 'success'){
				erSvc.easyRegAlert({"text":"Your Document has been uploaded","title":"Success"}, true);
				$scope.newDoc.name = '';
				let docUser = $scope.users[$scope.newDoc.upload_userid];
				let author = docUser ? (docUser.last_name + ', ' + docUser.first_name) : '';
				var newDoc = {
					"accountid":$scope.accountid,
					"category": $scope.category,
					"upload_userid": $scope.newDoc.upload_userid,
					"author": author,
					"description":$scope.newDoc.description,
					"classification":$scope.newDoc.classification || '',
					"date_uploaded":erSvc.today(),
					"filepath": folder + '/' + filename,
					"eventAssociations":{},
					"courseAssociations":{}
				};

				dataSvc.createOrUpdateRecord({"table":"documents","record":newDoc},$scope.eventid)
				.then(function(res){
					newDoc.id = res;
					$scope.documents[res] = newDoc;
					var newAssoc = {"documentid":res,"accountid":$scope.accountid};
					newAssoc.eventid = $scope.eventid;
					if($scope.selectedCourse) newAssoc.courseid = $scope.selectedCourse.id;
					dataSvc.createOrUpdateRecord({
						"table":"document_association",
						"record":newAssoc
					},$scope.eventid).then(function(res){
						newAssoc.id = res;
						$scope.documents[newDoc.id] = newDoc;
						if(newAssoc.courseid){
							newDoc.courseAssociations[newAssoc.id] = newAssoc;
							$scope.courses[newAssoc.courseid].documents[newDoc.id] = newDoc;
							$scope.courses[newAssoc.courseid].visibleDocuments[newDoc.id] = newDoc;
							reassessVisibleCourseDocs();
						}else{
							newDoc.eventAssociations[newAssoc.id] = newAssoc;
							$scope.eventDocuments[newDoc.id] = newDoc;
						}
					});
					erSvc.closeLoading();
				});
			}else{
				erSvc.easyRegAlert({
					"text":"Error. Please contact support.",
					"title":"Error"
				});
				erSvc.closeLoading();
			}
			$(':file').val('');
		});
	};// End uploadDoc()

	$scope.editDoc = function(doc){
		$scope.selectedDoc = doc;
		$scope.tempDoc = angular.copy(doc);
		$scope.dialogTitle = "Edit " + $filter('nameFromFilepath')(doc.filepath);
		$('#editDocDiv').show(500);
	};

	$scope.updateDoc = function(){
		$scope.eventDocuments[$scope.tempDoc.id] = $scope.tempDoc;
		let docUser = $scope.users[$scope.tempDoc.upload_userid];
		$scope.tempDoc.author = docUser ? (docUser.last_name + ', ' + docUser.first_name) : '';
		dataSvc.createOrUpdateRecord({"table":"documents","record":$scope.eventDocuments[$scope.tempDoc.id]},$scope.eventid);
		$scope.closeRightDialog();
	};

	$scope.removeEvtAssoc = function(){
		erSvc.loadingDialog();
		angular.forEach($scope.selectedDoc.eventAssociations,function(assoc){
			if(assoc.eventid == $scope.eventid){
				dataSvc.deleteRecord(
					{"table":"document_association","id":assoc.id},$scope.eventid);
				delete $scope.eventDocuments[$scope.selectedDoc.id];
				$scope.closeRightDialog();
				erSvc.closeLoading();
			}
		});
	};

	$scope.removeCrsAssoc = function(doc){
		erSvc.loadingDialog();
		angular.forEach(doc.courseAssociations,function(assoc){
			if(assoc.eventid == $scope.eventid && assoc.courseid == $scope.selectedCourse.id){
				dataSvc.deleteRecord({"table":"document_association","id":assoc.id},$scope.eventid);
				delete doc.courseAssociations[assoc.id];
				$scope.closeRightDialog();
				erSvc.closeLoading();
				reassessVisibleCourseDocs();
			}
		});
	};

	$scope.addDocToCourse = function(doc){
		var newAssoc = {
			"accountid":$scope.accountid,
			"eventid":$scope.eventid,
			"courseid":$scope.selectedCourse.id,
			"documentid":doc.id
		};
		dataSvc.createOrUpdateRecord({"table":"document_association","record":newAssoc}, $scope.eventid).then(function(res){
			newAssoc.id = res;
			doc.courseAssociations[newAssoc.id] = newAssoc;
			reassessVisibleCourseDocs();
		});
	};

	function reassessVisibleCourseDocs(){
		$scope.selectedCourse.visibleDocuments = {};
		var mostRecent, doc;
		Object.keys($scope.selectedCourse.documents).forEach(function(key){
			doc = $scope.selectedCourse.documents[key];
			if(!mostRecent) mostRecent = doc;
			else if(doc.sortDate > mostRecent.sortDate) mostRecent = doc;
			angular.forEach(doc.courseAssociations,function(assoc){
				if(assoc.courseid == $scope.selectedCourse.id && assoc.eventid == $scope.eventid){
					$scope.selectedCourse.visibleDocuments[doc.id] = doc;
				}
			});
		});
		if(!Object.keys($scope.selectedCourse.visibleDocuments).length){
			$scope.selectedCourse.visibleDocuments[mostRecent.id] = mostRecent;
		}
	}

	$scope.showCourse = function(course){
		if(!$scope.courseFilter) return true;
		else return course.name.toLowerCase().indexOf($scope.courseFilter.toLowerCase()) >= 0;
	};

	$scope.editCourse = function(course){
		$scope.selectedCourse = course;
		$scope.dialogTitle = "Edit " + course.name;
		$('#editCourseDiv').show(500);
	};

	//is course document visible to attendees
	$scope.notAssociated = function(doc){
		retValue = true;
		angular.forEach($scope.selectedCourse.visibleDocuments,function(visible){
			if(visible.id == doc.id) retValue = false;
		});
		return retValue;
	};

	//has document been explicitly associated with this event
	$scope.explicitAssoc = function(doc){
		var retVal = false;
		angular.forEach(doc.courseAssociations,function(assoc){
			if(assoc.eventid == $scope.eventid && assoc.courseid == $scope.selectedCourse.id){
				retVal = true;
			}
		});
		return retVal;
	}

	//determine if any visible documents have been explicitly associated with a course
	//otherwise, the visible document is only based on most recent upload
	$scope.noCurAssociations = function(){
		if(!$scope.selectedCourse) return false;
		var retValue = true;
		angular.forEach($scope.selectedCourse.visibleDocuments,function(doc){
			angular.forEach(doc.courseAssociations,function(assoc){
				if(assoc.eventid == $scope.eventid) retValue = false;
			});
		});
		return retValue;
	}

	$scope.closeRightDialog = function(){
		$(".dialogRight").hide(500);
	};
});//end controller
