<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PSUGevents.com</title>
<?php include("common_functions.php");?>
<?php include("commonStyles.php");?>
<?php include("commonJs.php");?>
<style>
	.selectedEvent{ background-color: #03ff0336; }
	div .table tbody tr td{ line-height: 1; }
	input[type="checkbox"].form-control{ display:inline-block; }
	table.table.bottom td{ vertical-align: bottom; }
	.courseLabel{
		display: block;
		font-weight:normal;
	}
</style>
<script type="text/javascript">
	<?php print " var userid = \"{$_SESSION['userid']}\"\n;"; ?>
	if(!userid) window.location = "/login.php";

	var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
	app.controller('regController', function($scope, $http, $q, $filter, dataSvc, erSvc) {
		$('.dtPicker').datepicker();
		$scope.userid = '<?=$_SESSION['userid'] ?>';
		$scope.accountid = '<?=$_SESSION['accountid'] ?>';
		$scope.master = "<?=$_SESSION['master'] ?>" == '1';
		$scope.states = erSvc.getStateOptions();
		$scope.dietary_restrictions = erSvc.getDietaryRestrictions();

		$scope.docStatus = '0';
		if(window.location.href.includes('documents')) $('a[href="#documents"]').click();

		$scope.courses = {};
		var coursesRetrieved = $q.defer();
		dataSvc.getArray({'query':'courseList'}).then(function(resp){
			angular.forEach(resp, crs => {if(crs.archived != '1') $scope.courses[crs.id] = crs });
			coursesRetrieved.resolve();
		});

		var replytoemail = "postmaster@easyregpro.com";
		dataSvc.getArray({'query':'accountInfo'}).then(res => { if(res[0]) replytoemail = res[0].email });

		dataSvc.getArray({'query':'master_email_alerts'}).then(res => $scope.master_email_alerts = res);

		var userRetrieved = $q.defer();
		dataSvc.getArray({'query':'getCurrentUserData'}).then(function(userData){
			$scope.userData = userData[0];
			userRetrieved.resolve();
		});

		$scope.pymt_methods = [];
		dataSvc.getPreferenceByName('expensePymtMethods', $scope.accountid)
		.then(function(res){
			if(res[0]){
				let prefEntry = res[0];
				try{ $scope.pymt_methods = JSON.parse(prefEntry.value);}
				catch(e){ console.error(e); $scope.pymt_methods = []; }
			} 
		});

		$scope.documents = {};
		let docNames = [];
		dataSvc.getTableRecords('documents','accountid = ' + $scope.accountid, true).then(function(res){
			angular.forEach(res,function(doc){
				if(doc.upload_userid == $scope.userid) $scope.documents[doc.id] = doc;
				docNames.push($filter('nameFromFilepath')(doc.filepath));
			});
			angular.forEach($scope.documents,doc => doc.courses = []);
			dataSvc.getTableRecords('document_association',`accountid = ${$scope.accountid}`,true)
			.then(function(res){
				angular.forEach(res,function(assoc){
					let doc = $scope.documents[assoc.documentid];
					if(doc && assoc.courseid  && assoc.courseid != '0'){
						if(! doc.courses.includes(assoc.courseid)) doc.courses.push(assoc.courseid);
					} 
				});
				angular.forEach($scope.documents, d => sortDocCourses(d));
			});
		});

		//set preferred and able courses
		$q.all([coursesRetrieved.promise, userRetrieved.promise]).then(function(){
			var preferred = $scope.userData.courses_preferred.split(',').map(c => Number(c));
			var able = $scope.userData.courses_able.split(',').map(c => Number(c));
			let vendorCourses = {};
			angular.forEach($scope.courses,function(course){
				course.preferred = preferred.indexOf(course.id) >= 0;
				course.able = able.indexOf(course.id) >= 0;
				if($scope.userData.sponsorid == course.sponsorid) vendorCourses[course.id] = course;
			});
			if($scope.userData.sponsorid  && $scope.userData.sponsorid != '0') $scope.courses = vendorCourses;
		});

		$scope.setCrsPref = function(course, ablePref){
			if(ablePref == 'preferred' && course.preferred) course.able = false;
			if(ablePref == 'able' && course.able) course.preferred = false;
		};

		//get user/event data
		var roleDefs = {"a":"Administrator","c":"Clerk","p":"Presenter"};
		var eventsRetrieved = $q.defer();
		var rolesRetrieved = $q.defer();
		var userDataRetrieved = $q.defer();

		$scope.updateUser = function(){
			if($scope.userForm.$valid){
				erSvc.loadingDialog("Saving User Data");
				var able = [];
				var preferred = [];
				angular.forEach($scope.courses,function(course){
					if(course.able) able.push(Number(course.id));
					if(course.preferred) preferred.push(Number(course.id));
				});
				$scope.userData.courses_preferred = preferred.toString();
				$scope.userData.courses_able = able.toString();
				dataSvc.userSelfUpdate($scope.userData).then(function(resp){
					if(resp != 'error')
						erSvc.easyRegAlert({"text":"Your information has been updated.","title":"Success"});
					else erSvc.easyRegAlert({"text":"Your data has not been successfully updated. Please contact the event administrator.","title":"Error"});
					erSvc.closeLoading();
				});
			}else{
				erSvc.easyRegAlert({"text":"Please Complete Required Fields","title":"Submission Error"});
			}
		};

		$(document).on('change', '#imageInput', function() {
			$scope.selectImage($(this));
		});

		$scope.selectImage = function(input){
			erSvc.loadingDialog();
			if($scope.userData.photo){
				$http({
					"url": "/deleteDocument.php",
					"method": "GET",
					"params": {"document":$scope.userData.photo.substr(1)}
				});
			}
			var imgDestination = "img/account" + $scope.accountid + "/users";
			var imgName =  input.val().replace(/\\/g, '/').replace(/.*\//, '');
			if(imgName.indexOf(',') >= 0){
				erSvc.easyRegAlert({"text":"Please rename the image with no commas in the name","title":"Unable To Upload Image"});
				erSvc.closeLoading();
				return;
			}
			$scope.$apply(function(){
				$scope.userData.photo = "/" + imgDestination + "/" + imgName;
			});
			erSvc.uploadDocument($('#imageInput'), imgDestination).then(function(){
				$('#userImg').attr("src",$scope.userData.photo);
				erSvc.closeLoading();
			});
		};

		//select course as able or preferred to teach
		//@type = 'preferred' or 'able'
		$scope.selectCourse = function(type){
			if(type=='preferred') angular.forEach($scope.selectPreferred, crs => crs.preferred = true);
			else angular.forEach($scope.selectAble, crs => crs.able = true);
		};

		//deselect course as able or preferred to teach
		//@type = 'preferred' or 'able'
		$scope.delesectCourse = function(type){
			if(type == 'preferred')	angular.forEach($scope.currentPreferred,crs => crs.preferred = false);
			else angular.forEach($scope.currentAble, crs => crs.able = false);
		};

		$scope.resetPassword = function(){
			$scope.noPasswordMatch = $scope.newPassword != $scope.newPasswordConfirm;
			if(!erSvc.validatePassword($scope.newPassword)) return;
			if($scope.pwResetForm.$valid){
				$http({
					"url": "/er_encrypt.php?value=" + $scope.currentPassword,
					"method": "POST"
				}).then(function(response){
					$scope.badCurrentPw = response.data != $scope.userData.pass;
					if(!$scope.badCurrentPw && !$scope.noPasswordMatch){
						erSvc.encrypt($scope.newPassword).then(function(hashedPw){
							dataSvc.userPasswordReset($scope.userData.email, hashedPw).then(function(resp){
								if(resp == 'error'){
									erSvc.easyRegAlert({"text":"There was an error with your update. Please contact the event administrator.","title":"Error"});
								}else{
									erSvc.easyRegAlert({"text":"You have successfully updated your password.","title":"Success"});
									sendEmail('pwReset');
								}
							});
						});
					}
				});
			}
		};

		$scope.newCourse = {'teaching_preference':'prefer'};
		$scope.proposeCourse = function(){
			if($scope.courseProposalForm.$valid){
				$scope.newCourse.userid = $scope.userData.id;
				dataSvc.createOrUpdateRecord({"table":"course_proposals","record":$scope.newCourse})
				.then(function(resp){
					if(Number(resp)){
						erSvc.easyRegAlert({"text":"Your course proposal has been submitted","title":"Proposal Submitted"},true);

						angular.forEach($scope.master_email_alerts,function(alert){
							if(alert.alert_course_proposal)	sendCourseProposalAlert(alert.email);
						});
						$scope.newCourse.title = '';
						$scope.newCourse.description = '';
						$scope.newCourse.teaching_preference = 'prefer';
					}else{
						erSvc.easyRegAlert({"text":"There was an error submitting your request. Please contact the event administrator.","title":"Error"});
					}
				});
			}
		};

		function sendCourseProposalAlert(recip){
			var subject = "New Course Proposal";
			var body = $scope.userData.first_name + " " + $scope.userData.last_name + " has submitted a course proposal. <br/><br/>";
			body += $scope.newCourse.title + "<br/><br/>";
			body += $scope.newCourse.description;
			erSvc.sendEmail(recip, subject, body, replytoemail);
		}

		sendEmail = function(){
			var subject = "EasyRegPro Password Reset Notification";
			var body = "Your password for EasyRegPro has been reset.  If you did \
				not request this action, please contact the site administrator.";
			erSvc.sendEmail($scope.userData.email, subject, body, replytoemail);
		};

		$scope.updateAlerts = function(){
			erSvc.loadingDialog();
			var alertRec = {
				"id":$scope.userData.id,
				"alert_evt_req":$scope.userData.alert_evt_req,
				"alert_itinerary_chg":$scope.userData.alert_itinerary_chg,
				"alert_course_proposal":$scope.userData.alert_course_proposal,
				"alert_sched_signoff":$scope.userData.alert_sched_signoff
			};
			dataSvc.createOrUpdateRecord({"table":"users","record":alertRec}).then(function(){
				erSvc.closeLoading();
				erSvc.easyRegAlert({"text":"Changes Saved","title":""},true);
			});
		};

		$scope.closeRightDialog = function(){
			$(".dialogRight").hide(500);
			$(':file').val('');
			$scope.uploadDocName = '';
		};

		dataSvc.getAccountFeatures().then(re => $scope.features = re);

		$(document).on('change', '#docInput', function() {
			let newName = $('#docInput').val().replace(/\\/g, '/').replace(/.*\//, '');
			if(docNames.includes(newName) && 
				$filter('nameFromFilepath')($scope.tempDoc.filepath) != newName){
				erSvc.easyRegAlert({"text":"A document with this name already exists","title":"Unable to Use File"});
				$(':file').val('');
				return;
			}
			$scope.tempDoc.filepath = `documents/account${$scope.accountid}/course_docs/${newName}`;
			$scope.uploadDocName = newName;
			$scope.$apply();
		});

		$scope.editDoc = function(doc){
			doc = doc || {
				"accountid":$scope.accountid,
				"category":"course",
				"upload_userid":$scope.userData.id,
				"author":$scope.userData.first_name + ' ' + $scope.userData.last_name,
				"archived":"0",
				"date_uploaded":erSvc.today(),
				"courses":[]
			}
			$scope.selectedDoc = doc;
			$scope.tempDoc = angular.copy(doc);
			if(doc.id) $scope.dialogTitle = "Edit " + $filter('nameFromFilepath')(doc.filepath);
			else $scope.dialogTitle = "New Document";
			$('#editDocDiv').show(500);
		};

		$scope.updateDoc = function(){
			let curDoc = $scope.documents[$scope.tempDoc.id];
			//if document changed, delete previous document
			if(curDoc && curDoc.filepath != $scope.tempDoc.filepath){
				$http({"url": "/deleteDocument.php","method":"GET","params":{'document':curDoc.filepath}});
			}
			if(!curDoc && !$scope.uploadDocName){
				erSvc.easyRegAlert({"text":"No Document Selected","title":"Error"});
				return;
			}
			if($scope.uploadDocName){
				var folder = `documents/account${$scope.accountid}/course_docs`;
				erSvc.uploadDocument($('#docInput'), folder);
			}
			//add new course associations
			curDoc = curDoc || {"courses":[]};
			dataSvc.createOrUpdateRecord({"table":"documents","record":$scope.tempDoc}).then(function(res){
				if(!$scope.tempDoc.id) $scope.tempDoc.id = res;
				$scope.documents[$scope.tempDoc.id] = $scope.tempDoc;
				$scope.tempDoc.courses.forEach(function(crs){
					if(!curDoc.courses.includes(crs)){
						let newAssoc = {
							"accountid":$scope.accountid,
							"documentid":$scope.tempDoc.id,
							"courseid":crs
						}
						dataSvc.createOrUpdateRecord({"table":"document_association","record":newAssoc});
					}
				});
				//remove deleted course associations
				curDoc.courses.forEach(function(crs){
					if(!$scope.tempDoc.courses.includes(crs)){
						let filters = `courseid = ${crs} AND documentid = ${$scope.tempDoc.id}`;
						dataSvc.getTableRecords('document_association', filters).then(function(res){
							res.forEach(function(assoc){
								dataSvc.deleteRecord({"table":"document_association","id":assoc.id});
							});
						});
					}
				});
				$scope.closeRightDialog();	
				$scope.$applyAsync();	
			});				
		};

		$scope.removeDocCrs = (c) => $scope.tempDoc.courses.splice($scope.tempDoc.courses.indexOf(c),1);

		$scope.getAvailableDocCourses = function(category){
			if(!$scope.courses || !$scope.tempDoc || !$scope.tempDoc.courses) return [];

			return Object.values($scope.courses).filter(function(crs){
					if($scope.tempDoc.courses.includes(crs.id)) return false;
					if(category === 'preferred') return !!crs.preferred;
					if(category === 'able') return !!crs.able;
					if(category === 'other') return !crs.able && !crs.preferred;
					return false;
				}).sort((a,b) => a.name < b.name ? -1 : 1);
		};

		$scope.hasAvailableDocCourses = (category) => $scope.getAvailableDocCourses(category).length > 0;

		$scope.confirmDelete = function(){
			var doc = $scope.selectedDoc;
			erSvc.easyRegConfirm({"text":"Delete Document?","title":"Confirm Delete"}, 'Confirm', 'Cancel').then(function(res){
				if(res) $scope.deleteDoc();
			});
		};// end confirmDelete()

		$scope.deleteDoc = function(){
			var doc = $scope.selectedDoc;
			docNames.splice(docNames.indexOf($filter('nameFromFilepath')(doc.filepath)),1);
			$http({"url": "/deleteDocument.php","method": "GET","params": {'document':doc.filepath}})
			.then(function(response){
				if(response.data == 'success'){
					dataSvc.deleteRecord({"table":"documents","id":doc.id});
					delete $scope.documents[doc.id];
				}else{
					var title = "Error. Contact Site Administrator";
					erSvc.easyRegAlert({"text":response.data,"title":title});
				}
				$scope.closeRightDialog();
			});
		}; // End deleteDoc()

		$scope.addCrsToDoc = function(crs){
			if(!$scope.tempDoc.courses.includes(crs.id)) $scope.tempDoc.courses.push(crs.id);
			else erSvc.easyRegAlert({"text":"Association Already Exists","title":"Already There"});
			$scope.showCourseButtons = false;
			sortDocCourses($scope.tempDoc);
		};

		function sortDocCourses(doc){
			doc.courses.sort((a,b) => $scope.courses[a].name < $scope.courses[b].name ? -1 : 1);
			$scope.$applyAsync()
		}

	});//End controller
</script>
</head>
<body ng-app="regApp">
<top-nav ng-controller="navController"></top-nav>
<div class="container-fluid" ng-controller="regController">	<br/>
<ul class="nav nav-tabs">
	<li>
		<a class="nav-link active" data-bs-toggle="tab" href="#userProfile">My Profile</a>
	</li>
	<li ng-if="features.docs">
		<a class="nav-link" data-bs-toggle="tab" href="#documents">My Documents</a>
	</li>
	<li>
		<a class="nav-link" data-bs-toggle="tab" href="#passwordReset">Reset Password</a>
	</li>
	<li ng-if="features.course_proposals">
		<a class="nav-link" data-bs-toggle="tab" href="#courseProposal">Propose A New Course</a>
	</li>
	<li ng-if="master">
		<a class="nav-link" data-bs-toggle="tab" href="#alerts">Email Alerts</a>
	</li>
</ul>
<br/>

<div class="tab-content">
	<div class="row tab-pane fade show active" id="userProfile" ng-cloak>
		<div class="col-sm-11">
			<form class="form-horizontal" role="form" name="userForm" id="registration">
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Name Last, First</label>
					<span class='col-sm-4'>
						<input type='text' ng-model="userData.last_name" class='form-control' required>
					</span>
					<span class='col-sm-4'>
						<input type='text' ng-model="userData.first_name" class='form-control' required>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Email</label>
					<span class='col-sm-10 col-lg-8'>
						<input type='email' ng-model="userData.email" class='form-control' required>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Business/District</label>
					<span class='col-sm-10 col-lg-8'>
						<input type='text' ng-model="userData.business" class='form-control'>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Work Address</label>
					<span class='col-lg-6'>
						<input type='text' ng-model="userData.address1" class='form-control'>
					</span>
					<span class='col-lg-3'>
						<b>Unit/Suite:</b>
						<input type='text' ng-model="userData.address2" class='form-control'
							style="display:inline;width:10em">
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">City/State/Zip</label>
					<span class='col-sm-3'>
						<input type='text' ng-model="userData.city" class='form-control'>
					</span>
					<span class='col-sm-2'>
						<select ng-options="state for state in states" ng-model="userData.state" class="form-select">
						</select>
					</span>
					<span class='col-sm-3'>
						<input type='text' ng-model="userData.zip" class='form-control'>
					</span>
				</div>

				<div class="mb-2 row">
					<label class="col-2 col-form-label">Home Address</label>
					<span class='col-lg-6'>
						<input type='text' ng-model="userData.home_add1" class='form-control'>
					</span>
					<span class='col-lg-3'>
						<b>Unit/Suite:</b>
						<input type='text' ng-model="userData.home_add2" class='form-control'
							style="display:inline;width:10em">
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">City/State/Zip</label>
					<span class='col-sm-3'>
						<input type='text' ng-model="userData.home_city" class='form-control'>
					</span>
					<span class='col-sm-2'>
						<select ng-options="state for state in states" 
							ng-model="userData.home_state" class="form-select">
						</select>
					</span>
					<span class='col-sm-3'>
						<input type='text' ng-model="userData.home_zip" class='form-control'>
					</span>
				</div>

				<div class="mb-2 row">
					<label class="col-2 col-form-label">Phone</label>
					<span class='col-sm-3'>
						<input type='text' ng-model="userData.phone" class='form-control'>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Dietary Restrictions</label>
					<span class='col-sm-3'>
						<select ng-options="res.value as res.label for res in dietary_restrictions"
							ng-model="userData.dietary_restrictions" class='form-select'>
						</select>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Payment Preferences</label>
					<span class='col-sm-3'>
						Preferred Payment Method
						<select ng-options="meth for meth in pymt_methods"
							ng-model="userData.pymt_method_pref" class='form-select'>
						</select>
					</span>
					<span class='col-sm-5'>
						Notes
						<input type="text" class="form-control" ng-model="userData.pymt_method_notes" />
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Bio</label>
					<span class='col-sm-3'>
						<textarea rows="4" cols="90" ng-model="userData.bio"></textarea>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Photo</label>
					<span class='col-sm-10'>
						Please use your name as the image filename to avoid conflicts with other users.<br/>
						<img id="userImg" ng-src="{{userData.photo}}" height="150"/>
						<label class="btn btn-primary btn-primary">
							Browse
							<input type="file" name="image" id="imageInput" style="display:none">
						</label>
						<input type="text" class="form-control" ng-model="userData.photo"
							style="display:none">
					</span>
				</div>
				<!-- Course Preferences -->
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Course Preferences</label>
					<span class='col-sm-10'>
						<table class="table scrollable striped">
							<thead>
								<tr class="headerRow sticky noPad">
									<th>Course</th>
									<th>I Prefer To Teach This Course</th>
									<th>I Am Able To Teach This Course</th>
								</tr>
							</thead>
							<tbody>
								<tr ng-repeat="course in courses | orderObjectBy:'name'">
									<td>{{course.name}}</td>
									<td>
										<input type="checkbox" class="form-check-input" ng-model="course.preferred"
											ng-change="setCrsPref(course, 'preferred')"/>
									</td>
									<td>
										<input type="checkbox" class="form-check-input" ng-model="course.able"
											ng-change="setCrsPref(course, 'able')"/>
									</td>
								</tr>
							</tbody>
						</table>
					</span>
				</div>					
			</form>
			<div class="button-row">
				<button class="btn btn-primary" ng-click="updateUser()">Update My Info</button>
			</div>
		</div>
	</div> <!-- End Profile Tab -->

	<!-- Document Tab -->
	<div class="row tab-pane" id="documents" ng-cloak>
		<div class="col-sm-11 form-horizontal">
			<button class="btn btn-success" ng-click="editDoc()" style="margin:1em">
				<i class="bi bi-plus-circle"></i> New Document
			</button>
			<b style="margin-left:1em">Status</b>
			<label style="margin-left:1em;font-weight:normal">
				<input type="radio" name="docArchiveStatus" ng-model="docStatus" value="0" /> Active
			</label>
			<label style="margin-left:1em;font-weight:normal">
				<input type="radio" name="docArchiveStatus" ng-model="docStatus" value="1" /> Archived
			</label>
			<table class="table scrollable striped">
				<thead>
					<tr>
						<th>Document</th>
						<th>Description</th>
						<th>Classification</th>
						<th>Date Uploaded</th>
						<th>Associated Course(s)</th>
					</tr>
				</thead>
				<tbody>
					<tr ng-repeat="doc in documents" ng-if="doc.archived == docStatus">
						<td>
							<span class="btn btn-primary btn-xs bi bi-pencil-fill" 
								ng-click="editDoc(doc)" title="Edit">
							</span>
							<a href="/{{doc.filepath}}" target="_blank">{{doc.filepath | nameFromFilepath}}	</a>
						</td>
						<td>{{doc.description}}</td>
						<td>
							<span ng-show="doc.classification == 1">Primary Presentation Document</span>
							<span ng-show="doc.classification == 2">Supporting Document</span>
							<span ng-show="!['1','2'].includes(doc.classification)">
								No Classification
							</span>
						</td>
						<td>{{doc.date_uploaded}}</td>
						<td>
							<div ng-repeat="crs in doc.courses" style="margin-top:4px">
								{{courses[crs].name}}
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div> <!-- End Document Tab -->

	<div id="editDocDiv" class="dialogRight" style="width:80%">
		<div class="dialogTitle">{{dialogTitle}}</div>
		<div class="dialogContents">
			<table>
				<tr>
					<td colspan="2">
						<label class="btn btn-primary btn-primary">
							<span ng-show="tempDoc.id">
								<span class="bi bi-arrow-clockwise"></span>
								Replace This Document With An Updated File
							</span>
							<span ng-show="!tempDoc.id">Select Document</span>
							<input type="file" id="docInput" name="document" style="display:none"/>
						</label>
						<input type="text" class="form-control" readonly ng-model="uploadDocName"
						style="width:200px;display:inline-block;margin-left:2em">
					</td>
				</tr>
				<tr>
					<td class="bold">Description</td>
					<td>
						<textarea ng-model="tempDoc.description" rows="4" cols="20" class="form-control"></textarea>
					</td>
				</tr>
				<tr ng-show="tempDoc.category == 'course'">
					<td class="bold">Classification</td>
					<td>
						<select ng-model="tempDoc.classification" class="form-select">
							<option value="1">Primary Presentation Document</option>
							<option value="2">Supporting Document</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="bold">Add Course Association</td>
					<td>
						<div class="d-flex flex-wrap gap-2 align-items-start">
						<div class="dropdown btn-tracks">
							<button type="button"
								class="btn btn-primary btn-sm dropdown-toggle"
								data-bs-toggle="dropdown" aria-expanded="false"
								ng-disabled="!hasAvailableDocCourses('preferred')">
								My Preferred Courses
							</button>
							<ul class="dropdown-menu" style="min-width:16rem">
								<li ng-repeat="crs in getAvailableDocCourses('preferred')">
									<button type="button" class="dropdown-item" ng-click="addCrsToDoc(crs)">{{crs.name}}</button>
								</li>
								<li ng-if="!hasAvailableDocCourses('preferred')">
									<span class="dropdown-item-text text-muted">No preferred courses available</span>
								</li>
							</ul>
						</div>
						<div class="dropdown btn-tracks">
							<button type="button"
								class="btn btn-primary btn-sm dropdown-toggle"
								data-bs-toggle="dropdown" aria-expanded="false"
								ng-disabled="!hasAvailableDocCourses('able')">
								My Able Courses
							</button>
							<ul class="dropdown-menu" style="min-width:16rem">
								<li ng-repeat="crs in getAvailableDocCourses('able')">
									<button type="button" class="dropdown-item" ng-click="addCrsToDoc(crs)">{{crs.name}}</button>
								</li>
								<li ng-if="!hasAvailableDocCourses('able')">
									<span class="dropdown-item-text text-muted">No Able courses available</span>
								</li>
							</ul>
						</div>
						<div class="dropdown btn-tracks">
							<button type="button"
								class="btn btn-primary btn-sm dropdown-toggle"
								data-bs-toggle="dropdown" aria-expanded="false"
								ng-disabled="!hasAvailableDocCourses('other')">
								Other Courses
							</button>
							<ul class="dropdown-menu" style="min-width:16rem">
								<li ng-repeat="crs in getAvailableDocCourses('other')">
									<button type="button" class="dropdown-item" ng-click="addCrsToDoc(crs)">{{crs.name}}</button>
								</li>
								<li ng-if="!hasAvailableDocCourses('other')">
									<span class="dropdown-item-text text-muted">No other courses available</span>
								</li>
							</ul>
						</div>
						</div>
					</td>
				</tr>
				<tr>
					<td class="bold">Current Course Associations</td>
					<td>
						<label ng-repeat="crs in tempDoc.courses" class="courseLabel">
							<button class="btn btn-danger btn-xs" 
								ng-click="removeDocCrs(crs)"> x
							</button>
							{{courses[crs].name}}
						</label>
					</td>
				</tr>
				<tr>
					<td class="bold">Archived</td>
					<td>
						<input type="checkbox" ng-model="tempDoc.archived" class="form-check-input"
							ng-true-value="1" ng-false-value="0"/>
					</td>
				</tr>
			</table>
			<div class="button-row" style="margin-bottom:1em"
				ng-show="!showMultiCourseDelete">
				<button class="btn btn-primary" ng-click="closeRightDialog()">
					<span class="bi bi-chevron-left"></span> Cancel
				</button>
				<button class="btn btn-danger" ng-click="confirmDelete()">
					<span class="bi bi-trash"></span> Delete
				</button>
				<button class="btn btn-success" ng-click="updateDoc()">
					<span class="bi bi-check-lg"></span> Update
				</button>
			</div>
		</div> <!-- End Dialog Contents -->
	</div> <!-- End Edit Document Dialog -->

	<!-- Password Tab -->
	<div class="row tab-pane" id="passwordReset" ng-cloak>
		<form name="pwResetForm">
		<div class="col-sm-11 form-horizontal">
			<div class="mb-2 row">
				<label class="col-2 col-form-label">Current Password</label>
				<span class='col-sm-8'>
					<input type="password" ng-model="currentPassword" required
						class="form-control"/>
				</span>
			</div>
			<div class="mb-2 row">
				<label class="col-2 col-form-label">New Password</label>
				<span class='col-sm-8'>
					<input type="password" ng-model="newPassword" required
						class="form-control" minlength="7"/>
				</span>
			</div>
			<div class="mb-2 row">
				<label class="col-2 col-form-label">New Password Confirm</label>
				<span class='col-sm-8'>
					<input type="password" ng-model="newPasswordConfirm" required
						class="form-control" minlength="7"/><br/>
					<password-requirements></password-requirements>
				</span>
			</div>
			<div class="mb-2 row" ng-show="badCurrentPw">
				<label class="col-2 col-form-label"></label>
				<span class='alert alert-danger col-sm-8 offset-sm-2' role='alert'>Current Password Is Not Correct</span>
			</div>
			<div class="mb-2 row" ng-show="noPasswordMatch">
				<label class="col-2 col-form-label"></label>
				<span class='alert alert-danger col-sm-8 offset-sm-2' role='alert'>Passwords Do Not Match</span>
			</div>
			<div class="button-row">
				<button class="btn btn-primary" ng-click="resetPassword()">Update Password</button>
			</div>
		</div>
		</form>
	</div><!-- End Password Tab -->

	<!-- Course Proposal Tab -->
	<div class="row tab-pane" id="courseProposal" ng-cloak>
		<form name="courseProposalForm">
		<div class="col-sm-11 form-horizontal">
			<div class="mb-2 row">
				<label class="col-2 col-form-label">Title</label>
				<span class='col-sm-8'>
					<input type="text" ng-model="newCourse.title" required
						class="form-control"/>
				</span>
			</div>
			<div class="mb-2 row">
				<label class="col-2 col-form-label">Description</label>
				<span class='col-sm-8'>
					<textarea rows="8" cols="110" ng-model="newCourse.description"
						class="form-control" required>
					</textarea>
				</span>
			</div>
			<div class="mb-2 row">
				<label class="col-2 col-form-label">Teaching Preference</label>
				<span class='col-sm-8'>
					<select ng-model="newCourse.teaching_preference" class="form-select" required>
						<option value="prefer">I prefer to teach this course</option>
						<option value="able">I am able to teach this course</option>
						<option value="suggestion">Suggestion only</option>
					</select>
				</span>
			</div>
			<div class="button-row">
				<button class="btn btn-primary" ng-click="proposeCourse()">Propose New Course</button>
			</div>
		</div>
		</form>
	</div><!-- End Course Proposal Tab -->

	<!-- Email Alerts Tab -->
	<div class="row tab-pane" id="alerts" ng-cloak ng-if="master">
		<div class="col-sm-11">
			<form class="form-horizontal" role="form" name="userForm">
				<table class="table bottom">
					<tr>
						<td class="right">
							<input type="checkbox" class="form-check-input"
								ng-model="userData.alert_evt_req"
								ng-true-value="1" ng-false-value="0" />
						</td>
						<td class="bold">Event Requests</td>
						<td>User requests to participate as a presenter or administrator in an an event</td>
					</tr>
					<tr>
						<td class="right">
							<input type="checkbox" class="form-check-input"
								ng-model="userData.alert_itinerary_chg"
								ng-true-value="1" ng-false-value="0" />
						</td>
						<td class="bold">Itinerary Updates</td>
						<td>User updates event itinerary</td>
					</tr>
					<tr>
						<td class="right">
							<input type="checkbox" class="form-check-input"
								ng-model="userData.alert_course_proposal"
								ng-true-value="1" ng-false-value="0" />
						</td>
						<td class="bold">Course Proposal</td>
						<td>Course proposal submission</td>
					</tr>
					<tr>
						<td class="right">
							<input type="checkbox" class="form-check-input"
								ng-model="userData.alert_sched_signoff"
								ng-true-value="1" ng-false-value="0" />
						</td>
						<td class="bold">Presenter Schedule Cmt/Sign-off</td>
						<td>
							Presenters approve or comment on their schedules
						</td>
					</tr>
				</table>
				<div class="button-row">
					<button class="btn btn-primary" ng-click="updateAlerts()">Save</button>
				</div>
			</form>
		</div>
	</div><!-- End email alerts -->
</div> <!-- End Tab Content -->
</div> <!-- END CONTROLLER   -->
<er-Footer />
</body>
</html>
