regApp.controller('documentMgmt', function($scope, $http, $q, $filter, $rootScope, dataSvc, erSvc) {
	let accountid = erSessionData.accountid;
	let userName = erSessionData.userData.first_name + ' ' + erSessionData.userData.last_name;
	$scope.category = 'account';
	$scope.newDoc = {};
	$scope.selectedEventAssociations = [];
	$scope.selectedCourseAssociations = [];
	$scope.docStatus = '0';
	$scope.events = {};
	let docsRetrieved = $q.defer();
	let coursesRetrieved = $q.defer();
	let videosRetrieved = $q.defer();
	let usersRetrieved = $q.defer();
	dataSvc.getTableRecords('documents', 'accountid = ' + accountid, true).then(function(docs){
		angular.forEach(docs,function(doc){
			doc.eventAssociations = {};
			doc.courseAssociations = {};
			doc.videoAssociations = {};
			doc.name = $filter('nameFromFilepath')(doc.filepath);
			doc.author = doc.author || '';
			doc.description = doc.description || '';
		});
		$scope.documents = docs;

		let criteria = 'accountid = ' + accountid + ' AND sessionid IS NULL';
		dataSvc.getTableRecords('document_association', criteria, true).then(function(associations){
			angular.forEach(associations,function(assoc){
				if(assoc.eventid && $scope.documents[assoc.documentid] && !assoc.courseid){
					$scope.documents[assoc.documentid].eventAssociations[assoc.id] = assoc;
				}
				else if(assoc.courseid && $scope.documents[assoc.documentid]){
					$scope.documents[assoc.documentid].courseAssociations[assoc.id] = assoc;
				}
				else if(assoc.videoid && $scope.documents[assoc.documentid]){
					$scope.documents[assoc.documentid].videoAssociations[assoc.id] = assoc;
				}
			});
			docsRetrieved.resolve();
		});
	});

	dataSvc.getUsersByAccount(true, accountid).then(function(res){
		$scope.users = res;
		usersRetrieved.resolve();
	});

	dataSvc.getEventsByAccount(true, accountid).then(function(events){
		$scope.events = events;
		angular.forEach($scope.events,evt => evt.sort = (erSvc.localToMySqlDate(evt.startdate)));
	});

	$scope.courses = {};
	dataSvc.getCoursesByAccount(true, accountid).then(function(courses){
		angular.forEach(courses,function(crs){
			if(crs.archived != '1'){
				crs.documents = [];
				$scope.courses[crs.id] = crs;
			} 
		});
		coursesRetrieved.resolve();
	});

	$scope.videos = {};
	dataSvc.getTableRecords('videos', 'accountid = ' + accountid, true).then(function(videos){
		angular.forEach(videos,function(video){
			video.name = video.display_name ? video.display_name : 
				$filter('nameFromFilepath')(video.filepath);
			video.documents = [];
			$scope.videos[video.id] = video;
		});
		videosRetrieved.resolve();
	});

	$q.all([docsRetrieved.promise,coursesRetrieved.promise,videosRetrieved.promise])
		.then(function(){
		angular.forEach($scope.documents,function(doc){
			angular.forEach(doc.courseAssociations,function(assoc){
				let course = $scope.courses[assoc.courseid];
				if(course && !course.documents.includes(doc)) course.documents.push(doc);
			});
			angular.forEach(doc.videoAssociations,function(assoc){
				let video = $scope.videos[assoc.videoid];
				if(video && !video.documents.includes(doc)) video.documents.push(doc);
			});
		});
	});

	$q.all([docsRetrieved.promise,usersRetrieved.promise]).then(function(){
		angular.forEach($scope.documents,function(doc){
			if($scope.users[doc.upload_userid]){
				$scope.users[doc.upload_userid].archived = '0';
			}else{
				doc.upload_userid = '';
				doc.author = '';
			}
		});
	});

	$(document).on('change', ':file', function() {
		var newName = $(this).val().replace(/\\/g, '/').replace(/.*\//, '');
		//prevent upload of duplicates
		var dup = false;
		angular.forEach($scope.documents,function(doc){
			if(doc.filepath.replace(/\\/g, '/').replace(/.*\//, '') == newName){
				if(doc.category == $scope.category) dup = true;
			}
		});
		if(dup){
			erSvc.easyRegAlert({
				"text":"A document with this name already exists.","title":"Unable to Upload Document"
			});
			$(this).val('');
		}else{
			$scope.selectDocument($(this));
			$scope.closeRightDialog();
		}
	});

	$scope.cancelDoc = function(){
		$scope.newDoc.name = '';
		$('#docInput').val('');
	};

	$scope.selectDocument = function(input){
		$scope.$apply(function(){
			$scope.newDoc.name = input.val().replace(/\\/g, '/').replace(/.*\//, '');
			$scope.newDoc.description = '';
			$scope.newDoc.author = userName;
		});
	};

	$scope.uploadDoc = function(){
		erSvc.loadingDialog("Uploading Document");
		var filename = $('#docInput')[0].files[0].name;
		var folder = 'documents/account' + accountid + '/';
		if($scope.category == 'account') folder += 'account_docs';
		else folder += `${$scope.category}_docs`;
		erSvc.uploadDocument($('#docInput'), folder).then(function(res){
			if(res == 'success'){
				erSvc.easyRegAlert({"text":"Your Document has been uploaded","title":"Success"}, true);
				$scope.newDoc.name = '';
				let docUser = $scope.users[$scope.newDoc.upload_userid];
				let author = docUser ? (docUser.last_name + ', ' + docUser.first_name) : '';
				var newDoc = {
					"accountid":accountid,
					"category": $scope.category,
					"upload_userid": $scope.newDoc.upload_userid,
					"author": author,
					"description":$scope.newDoc.description,
					"date_uploaded":erSvc.today(),
					"filepath": folder + '/' + filename,
					"name":filename,
					"archived":'0',
					"eventAssociations":{},
					"courseAssociations":{}, 
					"videoAssociations":{}
				};

				if($scope.category == 'course') newDoc.classification = $scope.newDoc.classification;

				dataSvc.createOrUpdateRecord({"table":"documents","record":newDoc}).then(function(res){
					newDoc.id = res;
					$scope.documents[res] = newDoc;
					var crsId = $scope.newDoc.courseAssoc;
					var videoId = $scope.newDoc.videoAssoc;
					if($scope.category == 'course' && crsId){
						$scope.associationDoc = $scope.documents[res];
						$scope.addCrsAssociation($scope.courses[crsId]);
					}
					if($scope.category == 'video' && videoId){
						$scope.associationDoc = $scope.documents[res];
						$scope.addVideoAssociation($scope.videos[videoId]);
					}
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

	let selectedAssociation;
	$scope.editDoc = function(doc, course){
		selectedAssociation = null;
		if(course){
			angular.forEach(doc.courseAssociations,function(assoc){
				if(assoc.courseid == course.id) selectedAssociation = assoc;
			});
		}
		$scope.selectedDoc = doc;
		$scope.tempDoc = angular.copy(doc);
		$scope.dialogTitle = "Edit " + $filter('nameFromFilepath')(doc.filepath);
		$('#editDocDiv').show(500);
	};

	$scope.updateDoc = function(){
		let docUser = $scope.users[$scope.tempDoc.upload_userid];
		$scope.tempDoc.author = docUser ? (docUser.last_name + ', ' + docUser.first_name) : '';
		$scope.documents[$scope.tempDoc.id] = $scope.tempDoc;
		if($scope.tempDoc.category == 'course'){
			angular.forEach($scope.courses,function(crs){
				for(let i = 0; i < crs.documents.length; i++){
					let doc = crs.documents[i];
					if(doc.id == $scope.tempDoc.id){
						crs.documents[i] = $scope.documents[$scope.tempDoc.id];
					}
				}
			});
		}
		dataSvc.createOrUpdateRecord({"table":"documents","record":$scope.documents[$scope.tempDoc.id]},$scope.eventid);
		$scope.closeRightDialog();			
	};

	$scope.confirmDelete = function(){
		var doc = $scope.selectedDoc;
		if($scope.getCourseList(doc).length > 1){
			$scope.showMultiCourseDelete = true;
			return;
		}
		erSvc.easyRegConfirm({"text":"Delete Document?","title":"Confirm Delete"},
			'Confirm', 'Cancel').then(function(res){
			if(res) $scope.deleteDoc();
		});
	};// end confirmDelete()

	$scope.deleteDoc = function(){
		var doc = $scope.selectedDoc;
		$http({
			"url": "/deleteDocument.php",
			"method": "GET",
			"params": {'document':doc.filepath}
		}).then(function(response){
			if(response.data == 'success'){
				angular.forEach(doc.courseAssociations,function(assoc){
					dataSvc.deleteRecord({"table":"document_association","id":assoc.id});
					let course = $scope.courses[assoc.courseid];
					course.documents.forEach(function(document){
						if(document.id == assoc.documentid){
							course.documents.splice(course.documents.indexOf(document),1);
						}
					});
				});
				angular.forEach(doc.eventAssociations,function(assoc){
					dataSvc.deleteRecord({"table":"document_association","id":assoc.id});
				});

				dataSvc.deleteRecord({"table":"documents","id":doc.id});
				delete $scope.documents[doc.id];
				$scope.$applyAsync();
			}else{
				var title = "Error. Contact Site Administrator";
				erSvc.easyRegAlert({"text":response.data,"title":title});
			}
			$scope.closeRightDialog();
		});
	}; // End deleteDoc()

	$scope.addAssociation = function(doc){
		$scope.associationDoc = doc;
		$scope.selectedEventAssociations = [];
		$scope.selectedCourseAssociations = [];
		angular.forEach(doc.eventAssociations,function(assoc){
			$scope.selectedEventAssociations.push(assoc.eventid);
		});
		angular.forEach(doc.courseAssociations,function(assoc){
			$scope.selectedCourseAssociations.push(assoc.courseid);
		});
		$('#associationDiv').dialog({
			"modal":true,
			"title": `Add ${($scope.category == 'course' ? 'Course' : 'Event')} Association`,
			"width":700
		});
	};

	$scope.addCourseDocument = function(course){
		$('#newCourseDocDialog').show(500);
		$scope.selectedCourse = course;
		$scope.newDoc.courseAssoc = course.id;
	};

	$scope.addVideoDocument = function(video){
		$('#newVideoDocDialog').show(500);
		$scope.selectedVideo = video;
		$scope.newDoc.videoAssoc = video.id;
	};

	$scope.showNewDoc = function(course){
		$('#newDocBtn').click();
		$scope.selectedCourse = course;
		$scope.newDoc.courseAssoc = course.id;
	};

	$scope.addCrsAssociation = function(course){
		let dupFound = false;
		angular.forEach(course.documents,function(doc){
			if(doc.id == $scope.associationDoc.id) dupFound = true;
		});
		if(dupFound){
			erSvc.easyRegAlert({"text":"This document is already associated with the course.","title":"Error"});
			return;
		}
		var assoc = {
			"documentid": $scope.associationDoc.id,
			"courseid": course.id,
			"accountid": accountid
		};
		dataSvc.createOrUpdateRecord({"table":"document_association","record":assoc}).then(function(res){
			assoc.id = res;
			$scope.associationDoc.courseAssociations[res] = assoc;
			$scope.courses[course.id].documents.push($scope.associationDoc);
			$(".ui-dialog-content").dialog("close");
			$scope.closeRightDialog();
			$scope.$applyAsync();
		});
	};

	$scope.addVideoAssociation = function(video){
		let dupFound = false;
		angular.forEach(video.documents,function(doc){
			if(doc.id == $scope.associationDoc.id) dupFound = true;
		});
		if(dupFound){
			erSvc.easyRegAlert({"text":"This document is already associated with the video.","title":"Error"});
			return;
		}
		var assoc = {
			"documentid": $scope.associationDoc.id,
			"videoid": video.id,
			"accountid": accountid
		};
		dataSvc.createOrUpdateRecord({"table":"document_association","record":assoc}).then(function(res){
			assoc.id = res;
			$scope.associationDoc.videoAssociations[res] = assoc;
			$scope.videos[video.id].documents.push($scope.associationDoc);
			$(".ui-dialog-content").dialog("close");
			$scope.closeRightDialog();
		});
	};

	$scope.addEvtAssociation = function(event){
		var assoc = {
			"documentid": $scope.associationDoc.id,
			"eventid": event.id,
			"accountid": accountid
		};
		dataSvc.createOrUpdateRecord({"table":"document_association","record":assoc}).then(function(res){
			assoc.id = res;
			$scope.associationDoc.eventAssociations[res] = assoc;
			$('#associationDiv').dialog('close');
		});
	};

	$scope.removeAssoc = function(document, assoc){
		document = $scope.documents[document.id];
		assoc = assoc || selectedAssociation;
		confirmData = {
			"text":"Remove this document association?",
			"title":"Confirm Removal"
		};
		erSvc.easyRegConfirm(confirmData, 'Confirm', 'Cancel').then(function(res){
			if(res){
				dataSvc.deleteRecord({"table":"document_association","id":assoc.id});
				if(document.eventAssociations[assoc.id]){
					delete document.eventAssociations[assoc.id];
				}
				if(document.courseAssociations[assoc.id]){
					delete document.courseAssociations[assoc.id];
					//remove document from course
					let course = $scope.courses[assoc.courseid];
					course.documents.forEach(function(doc){
						if(doc.id == assoc.documentid){
							course.documents.splice(course.documents.indexOf(doc),1);
						}
					});
				}
				$scope.closeRightDialog();
			}
		});
	};

	$scope.showDoc = function(doc){
		if(doc.archived != $scope.docStatus) return false;
		if(doc.category != $scope.category) return false;
		if(!$scope.docSearch) return true;
		var searchVal = $scope.docSearch.toLowerCase();
		if(doc.name.toLowerCase().indexOf(searchVal) >= 0) return true;
		if(doc.description.toLowerCase().indexOf(searchVal) >= 0) return true;
		if(doc.author.toLowerCase().indexOf(searchVal) >= 0) return true;
	};

	$scope.showCourse = function(course){
		if($scope.category != 'course' || course.archived == 1) return false;
		if(!$scope.docSearch) return true;
		var searchVal = $scope.docSearch.toLowerCase();
		if(course.name.toLowerCase().indexOf(searchVal) >= 0) return true;
		let match = false;
		angular.forEach(course.documents,function(doc){
			if(doc.name.toLowerCase().indexOf(searchVal) >= 0) match = true;
			if(doc.description.toLowerCase().indexOf(searchVal) >= 0) match = true;
		});
		return match;
	};

	$scope.showVideo = function(video){
		if($scope.category != 'video') return false;
		if(!$scope.docSearch) return true;
		var searchVal = $scope.docSearch.toLowerCase();
		if(video.name.toLowerCase().indexOf(searchVal) >= 0) return true;
		let match = false;
		angular.forEach(video.documents,function(doc){
			if(doc.name.toLowerCase().indexOf(searchVal) >= 0) match = true;
			if(doc.description.toLowerCase().indexOf(searchVal) >= 0) match = true;
		});
		return match;
	};

	$scope.showEvent = function(event){
		if(!$scope.eventSearch) return true;
		else return event.name.toLowerCase().indexOf($scope.eventSearch.toLowerCase()) >= 0;
	};

	$scope.closeRightDialog = function(){
		$(".dialogRight").hide(500);
		$scope.showMultiCourseDelete = false;
	};

	$scope.getCourseList = function(doc){
		if(!doc) return [];
		let courseList = [];
		angular.forEach(doc.courseAssociations,function(assoc){
			if($scope.courses[assoc.courseid]) courseList.push($scope.courses[assoc.courseid].name);
		});
		return courseList;
	}
});//end controller
