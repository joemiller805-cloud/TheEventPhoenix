regApp.controller('videoMgmt', function($scope, $http, $filter, $q, erSvc, dataSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;
	$scope.newVideo = {};
	$scope.courses = {};
	$scope.videos = {};
	$scope.category = 'all';
	$scope.instructors = [];
	$scope.packages = {};
	let videosRetrieved = $q.defer();
	let coursesRetrieved = $q.defer();
	let packgesRetrieved = $q.defer();
	let crsAssociationsRetrieved = $q.defer();

	dataSvc.getCoursesByAccount(true, accountid).then(function(courses){
		angular.forEach(courses,function(crs){
			if(crs.archived != '1'){
				crs.videos = [];
				$scope.courses[crs.id] = crs;
			} 
		});
		coursesRetrieved.resolve();
	});

	dataSvc.getVideosByAccount(true, accountid).then(function(videos){
		angular.forEach(videos,function(video){
			video.courseAssociations = {};
			video.name = $filter('nameFromFilepath')(video.filepath);
			video.searchString = erSvc.getObjectSearchString(video);
			if(video.instructor){
				if(!$scope.instructors.includes(video.instructor))
					$scope.instructors.push(video.instructor)
			}
		});
		$scope.videos = videos;
		videosRetrieved.resolve();
	});

	let criteria = 'accountid = ' + accountid;
	let videoCrsAssociations;
	dataSvc.getTableRecords('video_association', criteria, true).then(function(associations){
		videoCrsAssociations = associations;
		crsAssociationsRetrieved.resolve();
	});

	let videoPkgDetails;
	dataSvc.getObject({'query':'video_packages'}).then(function(res){
		$scope.packages = res;
		angular.forEach($scope.packages,(pkg) => pkg.videos = []);
		dataSvc.getArray({'query':'video_package_details'}).then(function(resp){
			videoPkgDetails = resp;
			packgesRetrieved.resolve();
		});
	});
	
	dataSvc.getUsersByAccount(false, $scope.accountid).then(function(res){
		res.forEach(u => {
			if(!$scope.instructors.includes(`${u.first_name} ${u.last_name}`))
				$scope.instructors.push(`${u.first_name} ${u.last_name}`)
		});
		$scope.instructors.sort();
		$('.instructorTxt').autocomplete({
			source:$scope.instructors,
			select:(event, ui) => $(event.target).trigger('keyup')
		});
	});

	dataSvc.getVideoReviews().then(reviews => $scope.reviewData = reviews);

	$q.all([videosRetrieved.promise, packgesRetrieved.promise]).then(function(){
		videoPkgDetails.forEach(function(assoc){
			assoc.sortorder = parseFloat(assoc.sortorder);
			assoc.videoName = $scope.videos[assoc.videoid].display_name || $scope.videos[assoc.videoid].name;
			if(!$scope.packages[assoc.video_packagesid]) return;
			$scope.packages[assoc.video_packagesid].videos.push(assoc);
		});
	});

	$q.all([videosRetrieved.promise, coursesRetrieved.promise, packgesRetrieved.promise, crsAssociationsRetrieved.promise]).then(function(){
		refreshVideoAssociations();
	});

	$('#pkgVideoList').sortable({
		update: function(){
			let uiOrder = [];
			$('#pkgVideoList li').each(function(){
				uiOrder.push($(this).attr('data-video-id'));
			});
			$scope.selectedPkg.videos.forEach(v => v.sortorder = uiOrder.indexOf(v.videoid) + 1);
			$scope.$apply();
		}
	});

	$scope.editVideoDetails = function(video){
		$scope.modVideo = angular.copy(video);
		//archived value must be numeric
		$scope.modVideo.archived = $scope.modVideo.archived == '1' ? 1 : 0;
		$scope.selectedVideo = video;
		$scope.dialogTitle = "Edit Video - " + (video.display_name || video.name);
		$scope.replaceVideo = {};
		$('#editVideoDetailsDiv').show(500);
	};

	$scope.updateSelectedVideo = function(){	
		if($scope.replaceVideo.name){
			let filename = $('#replacementVideo')[0].files[0].name;
			var folder = 'videos/account' + accountid;
			$scope.modVideo.filepath = folder + '/' + filename;
			$scope.modVideo.name = filename;
			
			let msg = "Replace Video";
			if($scope.modVideo.courseList.length || $scope.modVideo.packageList.length){
				msg += "<br/> <b>Note:</b> This video is currently associated to courses and/or packages <br/>";
				msg += "All course and package associations will also be updated.<br/>"
			}
			if($scope.modVideo.courseList.length ){
				msg += "<br/>Course Associations <ul>";
				$scope.modVideo.courseList.forEach(c => msg += `<li>${c}</li>`);
				msg += "</ul>";
			}
			if($scope.modVideo.packageList.length){
				msg += "<br/>Package Associations <ul>";
				$scope.modVideo.packageList.forEach(p => msg += `<li>${p}</li>`);
				msg += "</ul>";
			}
			erSvc.easyRegConfirm({"text":msg,"title":"Replace Video?"},"Replace","Cancel").then(function(res){
				if(res){
					erSvc.loadingDialog();
					$http({
						"url": "/deleteDocument.php",
						"method": "GET",
						"params": {'document':$scope.modVideo.filepath}
					}).then(function(response){
						erSvc.uploadDocument($('#replacementVideo'), folder).then(function(res){
							erSvc.closeLoading();
							completVideoUpdate();
						});
					});
				} 
			});
		}else{
			completVideoUpdate();
		}		
	};

	function completVideoUpdate(){
		erSvc.loadingDialog();
		dataSvc.createOrUpdateRecord({"table":"videos","record":$scope.modVideo}).then(function(){
			$scope.videos[$scope.modVideo.id] = $scope.modVideo;
			$scope.closeRightDialog();
			erSvc.closeLoading();
			$scope.$applyAsync();
		});
	}

	$(document).on('focus','.instructorTxt', function(){
		$(this).autocomplete({
			source:$scope.instructors,
			select: function(event, ui){
				setTimeout(function(){
					$('.instructorTxt').trigger('keyup').trigger('blur');
					$scope.$apply();
				}, 50);
			} 
		});
	});

	//Upload new video
	$(document).on('change', '#videoInput, #replacementVideo', function(){
		let newName = $(this).val().replace(/\\/g, '/').replace(/.*\//, '');
		let dup = false; //prevent upload of duplicates
		angular.forEach($scope.videos,function(video){
			if(video.filepath.replace(/\\/g, '/').replace(/.*\//, '') == newName) dup = true;
		});
		if(dup){
			erSvc.easyRegAlert({
				"text":"A video with this name already exists.","title":"Unable to Upload Video"
			});
			$(this).val('');
		}else{
			if($(this).is('#videoInput')) $scope.selectVideo($(this));
		}
	});

	//Replace existing video
	$(document).on('change', '#replacementVideo', function(){
		$scope.replaceVideo.name = $(this).val().replace(/\\/g, '/').replace(/.*\//, '');
		$scope.$apply();
	});

	$scope.archiveStatus = '0';
	$scope.showVideo = function(video){
		let searchMatch = !$scope.quickSearch || video.searchString.indexOf($scope.quickSearch.toLowerCase()) >= 0;
		let archiveMatch = (video.archived || 0) == $scope.archiveStatus;
		return searchMatch && archiveMatch;
	};

	$scope.selectVideo = function(input){
		$scope.$apply(function(){
			$scope.newVideo.name = input.val().replace(/\\/g, '/').replace(/.*\//, '');
			$scope.newVideo.description = '';
		});
	};

	$scope.cancelVideo = function(){
		$scope.newVideo.name = '';
		$('#videoInput').val('');
	};

	$scope.uploadVideo = function(){
		erSvc.loadingDialog("Uploading Video");
		var filename = $('#videoInput')[0].files[0].name;
		var folder = 'videos/account' + accountid;
		erSvc.uploadDocument($('#videoInput'), folder).then(function(res){
			if(res == 'success'){
				erSvc.easyRegAlert({"text":"Your Video has been uploaded","title":"Success"});
				$scope.newVideo.name = '';
				var newVideo = {
					"accountid":accountid,
					"description":$scope.newVideo.description,
					"filepath": folder + '/' + filename,
					"name":filename,
					"display_name":$scope.newVideo.display_name,
					"category":$scope.newVideo.category,
					"instructor":$scope.newVideo.instructor,
					"length":$scope.newVideo.length,
					"price":$scope.newVideo.price,
					"rental_duration":$scope.newVideo.rental_duration,
					"courseAssociations":{}
				};

				dataSvc.createOrUpdateRecord({"table":"videos","record":newVideo}).then(function(res){
					newVideo.id = res;
					$scope.videos[res] = newVideo;
					$scope.videos[res].searchString = erSvc.getObjectSearchString($scope.videos[res]);
					var crsId = $scope.newVideo.courseAssoc;
					if(crsId){
						$scope.associationVideo = $scope.videos[res];
						$scope.associationVideo.courseList = $scope.associationVideo.courseList || [];
						$scope.associationVideo.packageList = $scope.associationVideo.packageList || [];
						$scope.addCrsAssociation($scope.courses[crsId]);
					}
					erSvc.closeLoading();
					$scope.newVideo = {};
				});
			}else{
				erSvc.easyRegAlert({"text":"Error. Please contact support.","title":"Error"});
				erSvc.closeLoading();
			}
			$(':file').val('');
		});
	};// End uploadVideo()

	$scope.addCrsAssociation = function(course){
		let dupFound = false;
		angular.forEach(course.videos,function(video){
			if(video.id == $scope.associationVideo.id) dupFound = true;
		});
		if(dupFound){
			erSvc.easyRegAlert({"text":"This video is already associated with the course.","title":"Error"});
			return;
		}
		var assoc = {
			"videoid": $scope.associationVideo.id,
			"courseid": course.id,
			"accountid": accountid
		};
		dataSvc.createOrUpdateRecord({"table":"video_association","record":assoc}).then(function(res){
			assoc.id = res;
			$scope.associationVideo.courseAssociations[res] = assoc;
			$scope.courses[course.id].videos.push($scope.associationVideo);
			$(".ui-dialog-content").dialog("close");
			$scope.closeRightDialog();
		});
		$scope.associationVideo.courseList.push(course.name);
	};

	$scope.addCourseVideo = function(course){
		$('#newCourseVideoDialog').show(500);
		$scope.selectedCourse = course;
		$scope.newVideo.courseAssoc = course.id;
	};

	$scope.showCourse = function(course){
		if(course.archived == 1) return false;
		if(!$scope.videoSearch) return true;
		var searchVal = $scope.videoSearch.toLowerCase();
		if(course.name.toLowerCase().indexOf(searchVal) >= 0) return true;
		let match = false;
		angular.forEach(course.videos,function(video){
			if(video.name.toLowerCase().indexOf(searchVal) >= 0) match = true;
			if(video.description.toLowerCase().indexOf(searchVal) >= 0) match = true;
		});
		return match;
	};

	$scope.showNewVideo = function(course){
		$('#newVideoBtn').click();
		$scope.selectedCourse = course;
		$scope.newVideo.courseAssoc = course.id;
		$scope.closeRightDialog();
	};

	$scope.removeAssoc = function(video, course){
		let selectedAssociation;
		if(course){
			angular.forEach(video.courseAssociations,function(assoc){
				if(assoc.courseid == course.id) selectedAssociation = assoc;
			});
		}
		confirmData = {
			"text":"Remove this video association?",
			"title":"Confirm Removal"
		};
		erSvc.easyRegConfirm(confirmData, 'Confirm', 'Cancel').then(function(res){
			if(res){
				video.courseList.splice(video.courseList.indexOf(course.name),1);
				dataSvc.deleteRecord({"table":"video_association","id":selectedAssociation.id});
				if(video.courseAssociations[selectedAssociation.id]){
					delete video.courseAssociations[selectedAssociation.id];
					//remove video from course
					let course = $scope.courses[selectedAssociation.courseid];
					course.videos.forEach(function(video){
						if(video.id == selectedAssociation.videoid){
							course.videos.splice(course.videos.indexOf(video),1);
						}
					});
				}
				$scope.closeRightDialog();
			}
		});
	};

	$scope.confirmVideoDelete = function(){
		if(!$scope.selectedVideo) return;
		let msg = "Delete Video";
		if($scope.selectedVideo.courseList.length || $scope.selectedVideo.packageList.length){
			msg += "<br/> <b>Note:</b> This video is currently associated to courses and/or packages <br/>";
			msg += "All course and package associations will also be updated.<br/>"
		}
		if($scope.selectedVideo.courseList.length ){
			msg += "<br/>Course Associations <ul>";
			$scope.selectedVideo.courseList.forEach(c => msg += `<li>${c}</li>`);
			msg += "</ul>";
		}
		if($scope.selectedVideo.packageList.length){
			msg += "<br/>Package Associations <ul>";
			$scope.selectedVideo.packageList.forEach(p => msg += `<li>${p}</li>`);
			msg += "</ul>";
		}
		erSvc.easyRegConfirm({"text":msg,"title":"Delete Video?"},"Delete","Cancel").then(function(res){
			if(res) deleteVideo();
		});
	};

	function deleteVideo(){
		let video = $scope.selectedVideo;
		$http({
			"url": "/deleteDocument.php",
			"method": "GET",
			"params": {'document':video.filepath}
		}).then(function(response){
			if(response.data == 'success'){
				dataSvc.deleteRecord({"table":"videos","id":video.id});
				delete $scope.videos[video.id];
			}else{
				var title = "Error. Contact Site Administrator";
				erSvc.easyRegAlert({"text":response.data,"title":title});
				dataSvc.deleteRecord({"table":"videos","id":video.id});
				delete $scope.videos[video.id];
			}
			$scope.closeRightDialog();
		});
	}; // End deleteVideo()

	function refreshVideoAssociations(){
		angular.forEach(videoCrsAssociations,function(assoc){
			if($scope.videos[assoc.videoid]) $scope.videos[assoc.videoid].courseAssociations[assoc.id] = assoc;
		});
		angular.forEach($scope.videos,function(video){
			video.courseList = [];
			video.packageList = [];
			angular.forEach(video.courseAssociations,function(assoc){
				let course = $scope.courses[assoc.courseid];
				if(course && !course.videos.includes(video)) course.videos.push(video);
				if($scope.courses[assoc.courseid]) video.courseList.push($scope.courses[assoc.courseid].name);
			});
			angular.forEach($scope.packages,function(pkg){
				pkg.videos.forEach(v => {if(v.videoid == video.id) video.packageList.push(pkg.name)});
			});
		});
	}

	$scope.showVideoAssociations = function(video){
		$scope.selectedVideo = video;
		$('#videoAssocDialog').show(500)
	};

	$scope.updateVideo = function(){
		$scope.videos[$scope.tempVideo.id] = $scope.tempVideo;
		angular.forEach($scope.courses,function(crs){
			for(let i = 0; i < crs.videos.length; i++){
				let video = crs.videos[i];
				if(video.id == $scope.tempVideo.id){
					crs.videos[i] = $scope.videos[$scope.tempVideo.id];
				}
			}
		});
		dataSvc.createOrUpdateRecord({"table":"videos","record":$scope.videos[$scope.tempVideo.id]});
		$scope.closeRightDialog();			
	};

	/*************** Category Management ***************/

	$scope.videoCategories = [];
	let categoryPrefId;
	let prefFilter = 'accountid =' + erSessionData.accountid + ' AND name =' +  "'videoCategories'";
	dataSvc.getTableRecords('preferences', prefFilter).then(function(res){
		if(res[0]){
			categoryPrefId = res[0].id;
			$scope.videoCategories = angular.fromJson(res[0].value);
		}
	});

	$scope.addCategory = () => $scope.videoCategories.push({"sort":$scope.videoCategories.length,"val":''});

	$('#categoryList tbody').sortable({
		update: function(event, ui){
			let newCats = [];
			let curIdx = 0;
			$('#categoryList tr').each(function(){
				let idx = $(this).attr('data-idx');
				$scope.videoCategories[idx].sort = curIdx;
				newCats[curIdx++] = $scope.videoCategories[idx];
			});
			$scope.videoCategories = [...newCats];
			$scope.$apply();
		}
	});

	$scope.removeCategory = function(idx){
		let cat = $scope.videoCategories[idx];
		erSvc.easyRegConfirm({"text":`Delete category - ${cat.val}`,"title":"Delete Category?"},"Delete","Cancel").then(function(res){
			if(res){
				let sort = cat.sort;
				$scope.videoCategories.forEach( cat => {if(cat.sort > sort) cat.sort-- });
				$scope.videoCategories.splice(idx,1);
			}
		});
	};

	$scope.saveCategories = function(){
		erSvc.loadingDialog();
		let rec = {
			"id":categoryPrefId,
			"accountid":erSessionData.accountid,
			"name":"videoCategories",
			"value": angular.toJson($scope.videoCategories)
		};
		dataSvc.createOrUpdateRecord({"table":"preferences","record":rec}).then(function(res){
			erSvc.closeLoading();
			erSvc.easyRegAlert({"text":"Categories Saved","title":"Changes Saved"},true);
			categoryPrefId = categoryPrefId || res;
		});
	};

	// ***** Package Management ******

	$scope.havePackages = () => Object.keys($scope.packages).length;

	let newPkgId = 0;
	$scope.addPackage = function(){
		$('#newPackageDialog').show(500);
		$scope.editPackage = {"accountid":$scope.accountid,"videos":[]};
	};

	$scope.createPackage = function(){
		$scope.editPackage.id = --newPkgId;
		$scope.packages[newPkgId] = angular.copy($scope.editPackage);
		$scope.editPackage = null;
		$scope.closeRightDialog();
	};

	$scope.selectVideoForPkg = function(pkg){
		$('#pkgVidoesDialog').show(500);
		$scope.selectedPkg = pkg;
	};

	$scope.addVideoToPackage = function(){
		let rec = {"video_packagesid":$scope.selectedPkg.id,"videoid":$scope.newPkgVideo};
		rec.sortorder = $scope.selectedPkg.videos.length + 1;
		rec.videoName = $scope.videos[rec.videoid].display_name || $scope.videos[rec.videoid].name;
		$scope.selectedPkg.videos.push(rec);
		let video = $scope.videos[rec.videoid];
		if(!video.packageList.includes($scope.selectedPkg.name)) video.packageList.push($scope.selectedPkg.name);
		$scope.newPkgVideo = "";
	};

	$scope.markPkgVideoForDelete = function(video){
		video.markedForDelete = true;
		$scope.selectedPkg.videos.forEach(v => {if(v.sortorder > video.sortorder) v.sortorder -= 1});
	};

	$scope.videoNotInPackage = function(video){
		if(!$scope.selectedPkg) return;
		return $scope.selectedPkg.videos.filter(v => v.videoid == video).length == 0;
	};

	$scope.savePackages = function(){
		erSvc.loadingDialog("Saving Packages");
		let packagesFinished = 0;
		// newly created packages with temp id < 0. Delete temps after saving
		let tempRecordsToDelete = [];
		angular.forEach($scope.packages,function(pkg){
			let newPkg = pkg.id < 0;
			if(newPkg){
				tempRecordsToDelete.push(pkg.id);
				delete pkg.id;
			} 
			if(!newPkg && pkg.markedForDelete){
				dataSvc.deleteRecord({"table":"video_packages","id":pkg.id});
				if(++packagesFinished == Object.keys($scope.packages).length){
					erSvc.closeLoading();
					erSvc.easyRegAlert({"text":"Packages updated","title":"Changes Saved"},true);
					tempRecordsToDelete.forEach(id => delete $scope.packages[id]);
				}
				return;
			}
			dataSvc.createOrUpdateRecord({"table":"video_packages","record":pkg}).then(function(resp){
				pkg.id = pkg.id || resp;
				if(newPkg) $scope.packages[pkg.id] = pkg;
				let updateCount = 0;
				let updatesFinished = 0;
				pkg.videos.forEach(function(assoc){
					if(!assoc.markedForDelete){
						updateCount++;
						let rec = {
							"id":assoc.id,
							"video_packagesid":pkg.id,
							"videoid":assoc.videoid,
							"sortorder":assoc.sortorder
						};
						dataSvc.createOrUpdateRecord({"table":"video_package_details","record":rec}).then(function(res){
							assoc.id = res;
							checkStatus();
						});
					}
					if(assoc.markedForDelete){
						updateCount++;
						if(assoc.id){
							dataSvc.deleteRecord({"table":"video_package_details","id":assoc.id}).then(function(){
								pkg.videos.splice(pkg.videos.indexOf(assoc),1);
								checkStatus();
							});
						}else{
							pkg.videos.splice(pkg.videos.indexOf(assoc),1);
							checkStatus();
						}
					}
				});// End package videos loop
				checkStatus();

				function checkStatus(){
					if(++updatesFinished == updateCount || updateCount == 0){
						if(++packagesFinished == (Object.keys($scope.packages).length - tempRecordsToDelete.length)){
							erSvc.closeLoading();
							erSvc.easyRegAlert({"text":"Packages updated","title":"Changes Saved"},true);
							tempRecordsToDelete.forEach(id => delete $scope.packages[id]);
						}
					}
				}
			});
		});// End package loop
	};// End savePackages()

	function createPkgVideos(pkg){
		newVideoIds.forEach(function(id){
			if(oldVideoIds.includes(id)) return;
			dataSvc.createOrUpdateRecord({"table":"video_package_details","record":rec}).then(function(id){
				$scope.newPkgVideo.id = id;
				$scope.selectedPkg.videos.push(angular.copy($scope.newPkgVideo));
			});
		});
	}

	$scope.showReviews = function(resource, type){
		if(type == 'video' && $scope.reviewData.videos[resource.id])	
			$scope.reviewList = $scope.reviewData.videos[resource.id].reviewList;
		else if($scope.reviewData.packages[resource.id]) 
			$scope.reviewList = $scope.reviewData.packages[resource.id].reviewList;
		$scope.dialogTitle = (resource.display_name || resource.name) + " Reviews";
		$('#reviewListDiv').show(500);
	}

	$scope.getStarType = function(val, rating){
		let retClass = "star_border";
		if(rating >= val) retClass = "star";
		if(rating < val && rating > (val - 1)){
			if(rating%1 >= 0.7) retClass = "star";
			else if(rating%1 >= 0.3) retClass = "star_half";
		}
		return retClass;
	};

	// ********** Learning Center Settings ***********

	dataSvc.getPreferenceByName('learningCenterMsg', accountid)
	.then(function(res){
		if(res[0]) $scope.learningCenterMsg = res[0];
		else $scope.learningCenterMsg = {"name":"learningCenterMsg","accountid":accountid};
	});

	$scope.discountCodes = [];
	dataSvc.getTableRecords('discount_codes',`accountid = '${accountid}' AND type = 'learningCenter'`)
	.then(function(res){
		$scope.discountCodes = res;
	});

	let pymtPrefId;
	dataSvc.getPreferenceByName('learningCenterPymts', $scope.accountid).then(function(res){
		$scope.pymtPrefs = {};
		if(res[0]){
			try{
				$scope.pymtPrefs = angular.fromJson(res[0].value);
				pymtPrefId = res[0].id;
			}catch(e){}
		}
	});

	let invoicMsgId;
	dataSvc.getPreferenceByName('learningCenterInvoiceMsg', $scope.accountid).then(function(res){
		$scope.invoiceMsg = '';
		if(res[0]){
			$scope.invoiceMsg = res[0].value;
			invoicMsgId = res[0].id;
		} 
	});

	let discountPrefId;
	dataSvc.getPreferenceByName('learningCenterDiscounts', $scope.accountid).then(function(res){
		$scope.discounts = [];
		if(res[0]){
			try{
				$scope.discounts = angular.fromJson(res[0].value);
				discountPrefId = res[0].id;
			}catch(e){}
		}
	});

	$scope.addDiscount = () => $scope.discounts.push({"val":0,"method":"amount","discount":0});

	$scope.deleteDiscount = function(discount){
		let txt = "Delete this discount?";
		erSvc.easyRegConfirm({"text":txt,"title":"Confirm Delete"},"Confirm","Cancel").then((res) => {
			if(res){
				$scope.discounts.splice($scope.discounts.indexOf(discount),1);
			}
		});
	};

	$scope.addDiscountCode = function(){
		$scope.discountCodes.push({
			"code":"",
			"type":"learningCenter",
			"method":"percent",
			"discount":"",
			"sunrise":"",
			"sunset":"",
			"frequency":"oneTime",
			"accountid":accountid
		});
	};

	let codesToDelete = [];
	$scope.deleteCode = function(code){
		if(code.id) codesToDelete.push(code.id);
		$scope.discountCodes.splice($scope.discountCodes.indexOf(code),1);
	}

	$scope.saveLearningSettings = function(){
		erSvc.loadingDialog();
		var discountsSaved = $q.defer();
		var pymtSettingsSaved = $q.defer();
		var invoiceMsgSaved = $q.defer();
		var msgSaved = $q.defer();

		dataSvc.createOrUpdateRecord({"table":"preferences","record":$scope.learningCenterMsg})
		.then(function(res){
			$scope.learningCenterMsg.id = $scope.learningCenterMsg.id || res;
			msgSaved.resolve();	
		});

		//discount settings
		let disc = {
			"id":discountPrefId,
			"accountid":accountid,
			"name":"learningCenterDiscounts",
			"value":angular.toJson($scope.discounts)
		};
		dataSvc.createOrUpdateRecord({"table":"preferences","record":disc}).then(function(res){
			discountsSaved.resolve();
		});

		//payment settings
		let pymts = {
			"id":pymtPrefId,
			"accountid":accountid,
			"name":"learningCenterPymts",
			"value":angular.toJson($scope.pymtPrefs)
		};
		dataSvc.createOrUpdateRecord({"table":"preferences","record":pymts}).then(function(res){
			pymtSettingsSaved.resolve();
		});

		$scope.discountCodes.forEach(function(code){
			dataSvc.createOrUpdateRecord({"table":"discount_codes","record":code});
		});
		codesToDelete.forEach(function(codeid){
			dataSvc.deleteRecord({"table":"discount_codes","id":codeid});
		});

		let invoiceMsg = {
			"id":invoicMsgId,
			"accountid":accountid,
			"name":"learningCenterInvoiceMsg",
			"value":$scope.invoiceMsg
		};	
		dataSvc.createOrUpdateRecord({"table":"preferences","record":invoiceMsg}).then(function(res){
			invoiceMsgSaved.resolve();
		});	

		$q.all([discountsSaved.promise,msgSaved.promise,pymtSettingsSaved.promise,invoiceMsgSaved.promise]).then(function(){
			erSvc.closeLoading();
			erSvc.easyRegAlert({"text":"Changes Saved","title":"Changes have been saved"}, true);
		});
	};

	$scope.closeRightDialog = function(){ $(".dialogRight").hide(500); };
});//end controller
