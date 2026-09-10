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
	.tab-pane:not(.active){	display:none; }
	.searchDiv{
		display:inline-block;
		margin-left:2em;
		font-weight:bold;
	}
	#receiptDialog{
		position: fixed;
		top:8vh;
		left:25vw;
		width:50vw;
		height:85vh;
		z-index:1300;
		display:none;
		background:white;
		-webkit-box-shadow: 5px 5px 15px 5px #000000; 
		box-shadow: 5px 5px 15px 5px #000000;
	}
	#receiptDialog img{
		display:block;
		margin:auto;
		max-width:95%;
		max-height:95%;
	}
	#receiptDialog iframe{
		width:100%;
		height:100%;
	}
	#receiptHeader{
		background:var(--header);
		color:white;
		padding:3px;
		margin-bottom:3px;
		font-weight: bold;
		padding-left:1em;
	}
	#receiptHeader button{
		float:right;
		margin-right:1em;
	}
</style>
<script type="text/javascript">
	<?php print " var userid = \"{$_SESSION['userid']}\"\n;"; ?>
	if(!userid) window.location = "/login.php";

	var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
	app.controller('regController', function($scope, $http, $q, dataSvc, erSvc) {
		<?php print "\$scope.userid = \"{$_SESSION['userid']}\"\n;"; ?>
		<?php print "\$scope.accountid = \"{$_SESSION['accountid']}\"\n;"; ?>
		$scope.requestRole = 'p';
		var userRetrieved = $q.defer();
		dataSvc.getArray({'query':'getCurrentUserData'}).then(function(userData){
			$scope.userData = userData[0];
			userRetrieved.resolve();
		});

		dataSvc.getAccountFeatures().then(function(re){
			$scope.suveyEnabled = re.survey;
			$scope.expensesEnabled = re.staff_expense;
			$scope.requestsEnabled = re.evt_requests;
			$scope.docsEnabled = re.docs;
		});

		$scope.expenseCategories = {};
		dataSvc.getTableRecords('expense_categories', 'accountid = ' + $scope.accountid, true)
		.then(function(res){
			angular.forEach(res,function(cat){
				cat.max = Number(cat.max);
				if(cat.archived != '1') $scope.expenseCategories[cat.id] = cat;
			});
			$scope.hasExpenses = Object.keys($scope.expenseCategories).length > 0;
		});

		//get user/event data
		var eventsRetrieved = $q.defer();
		var rolesRetrieved = $q.defer();
		var userDataRetrieved = $q.defer();

		var replytoemail = "postmaster@easyregpro.com";
		dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
			if(resp[0]) replytoemail = resp[0].email;
		});

		dataSvc.getObject({'query':'accountEvents'}).then(function(events){
			angular.forEach(events,function(event){
				event.attending = false;
				event.requestedParticipation = false;
				event.requestedDenied = false;
				event.userEventData = {"eventid":event.id,"userid":$scope.userid};
			});
			$scope.events = events;
			eventsRetrieved.resolve();
		});

		dataSvc.getTableRecords('user_event', 'userid = ' + $scope.userid).then(function(res){
			$scope.userEvents = res;
			rolesRetrieved.resolve();
		});

		let qryParams = {'query':'eventUserData','eventid':'', 'userid':$scope.userid};
		dataSvc.getArray(qryParams).then(function(resp){
			$scope.eventData = resp;
			userDataRetrieved.resolve();
		});

		$q.all([eventsRetrieved.promise,rolesRetrieved.promise,userDataRetrieved.promise])
		.then(function(){
			angular.forEach($scope.userEvents,function(evt){
				if($scope.events[evt.eventid]) $scope.events[evt.eventid].attending = true;
			});

			angular.forEach($scope.eventData,function(evt){
				var event = $scope.events[evt.eventid];
				if(!event) return;
				event.requestedParticipation = evt.request_participation == 1;
				event.requestedDenied = evt.request_denied == 1;
				event.userEventData = evt;
				event.userEventData.arrival_date = erSvc.mySqlToLocalDate(event.userEventData.arrival_date);
				event.userEventData.departure_date = erSvc.mySqlToLocalDate(event.userEventData.departure_date);
				event.userEventData.hotel_room_type = event.userEventData.hotel_room_type || 'king';
				event.userEventData.hotel_smoking = event.userEventData.hotel_smoking || 'non';
				if(!event.userEventData.shirt_quantity) event.userEventData.shirt_quantity = 0;
			});
		});

		dataSvc.getArray({'query':'master_email_alerts'}).then(function(resp){
			$scope.master_email_alerts = resp;
		});

		$scope.getEventData = function(event){
			$scope.attending = event.attending;
			erSvc.loadingDialog();
			$scope.selectedEvent = event;
			var queryParams = {"query":"eventPresenters","eventid":event.id, "userid":$scope.userid};
			dataSvc.getArray(queryParams).then(function(resp){
				$scope.userEventSessions = resp;
				getDocumentAssociations();
				erSvc.closeLoading();
			});
			dataSvc.getArray({"query":"sectionSignups","eventid":event.id}).then(function(resp){
				$scope.sectionSignups = resp;
			});
			let userEvtParams = {
				'query':'eventUserData',
				'eventid':$scope.selectedEvent.id,
				'userid':$scope.userid
			};
			dataSvc.getArray(userEvtParams).then(function(resp){
				$scope.requestedParticipation = false;
				if(resp[0]){
					$scope.requestedParticipation = resp[0].request_participation == 1;
				}
				$scope.userEventData = resp[0] ? resp[0] : {};
				$scope.selectedEvent.userEventData = $scope.userEventData;
			});

			$scope.sessions;
			dataSvc.getObject({'query':'eventSessions','eventid':event.id}).then(function(sessions){
				$scope.sessions = sessions;
				angular.forEach($scope.sessions,function(session){
					session.sections = [];
				});
				dataSvc.getObject({'query':'eventSections','eventid':event.id}).then(function(sections){
					$scope.hasSchedule = Object.keys(sections).length > 0;
					angular.forEach(sections,function(section){
						$scope.sessions[section.sessionid].sections.push(section);
					});
				});
				dataSvc.getArray({"query":"eventSectionSessions","eventid":event.id}).
				then(function(sections){
					angular.forEach(sections,function(section){
						$scope.sessions[section.sessionid].sections.push({
							"room":section.roomName,
							"first_name":section.presenters,
							"course":section.courseName
						});
					});
				});
			});

			let expParams = {
				'query':'eventUserExpenses',
				'eventid':$scope.selectedEvent.id,
				'userid':$scope.userid
			};
			$scope.expenses = {};
			dataSvc.getObject(expParams).then(function(resp){
				$scope.expenses = resp;
				angular.forEach($scope.expenses,(exp) => exp.paid = exp.paid || 0);
			});

			getSurveyResults();
		}; //End getEventData()

		function getDocumentAssociations(){
			dataSvc.getDocuments($scope.accountid, $scope.selectedEvent.id).then(function(res){
				$scope.documents = res.documents;
				angular.forEach($scope.documents, d => d.curUser = d.upload_userid == userid);
				angular.forEach($scope.userEventSessions,function(session){
					session.courseDocs = {};
					session.visibleDocs = {};
					if(res.courses[session.courseid]){
						let docList = res.courses[session.courseid].documents;
						angular.forEach(docList,function(doc){
							if(!session.courseDocs[doc.id])	session.courseDocs[doc.id] = doc;
						});
						session.visibleDocs = res.courses[session.courseid].visibleDocuments;
					}
				});
				$scope.$applyAsync();
			});
		}

		/** ************* DOCUMETATION ************* **/

		$scope.editDocs = function(session){
			$scope.dialogTitle = "Course Documents"
			$scope.selectedSession = session;
			$('#documentsDiv').show(500);
		};

		//is course document visible to attendees
		$scope.notAssociated = function(doc){
			retValue = true;
			angular.forEach($scope.selectedSession.visibleDocs,function(visible){
				if(visible.id == doc.id) retValue = false;
			});
			return retValue;
		};

		$scope.showMyDocs = function(){
			if(!$scope.selectedSession) return false;
			let show = false;
			angular.forEach($scope.selectedSession.courseDocs,function(doc){
				if($scope.notAssociated(doc) && doc.curUser) show = true;
			});
			return show;
		};

		$scope.showOtherDocs = function(){
			if(!$scope.selectedSession) return false;
			let show = false;
			angular.forEach($scope.selectedSession.courseDocs,function(doc){
				if($scope.notAssociated(doc) && !doc.curUser) show = true;
			});
			return show;
		};
		
		//determine if any visible documents have been explicitly associated with a course
		//otherwise, the visible document is only based on most recent upload
		$scope.noCurAssociations = function(){
			if(!$scope.selectedSession) return false;
			var retValue = true;
			angular.forEach($scope.selectedSession.visibleDocs,function(doc){
				angular.forEach(doc.courseAssociations,function(assoc){
					if(assoc.eventid == $scope.selectedEvent.id){
						retValue = false;
					}
				});
			});
			return retValue;
		};

		//has document been explicitly associated with this event
		$scope.explicitAssoc = function(doc){
			var retVal = false;
			angular.forEach(doc.courseAssociations,function(assoc){
				if(assoc.eventid == $scope.selectedEvent.id && assoc.courseid == $scope.selectedSession.courseid){
					retVal = true;
				}
			});
			return retVal;
		};

		$scope.removeCrsAssoc = function(doc){
			erSvc.loadingDialog();
			angular.forEach(doc.courseAssociations,function(assoc){
				if(assoc.eventid == $scope.selectedEvent.id && assoc.courseid == $scope.selectedSession.courseid){
					dataSvc.deleteRecord({"table":"document_association","id":assoc.id},$scope.eventid);
					delete $scope.selectedSession.courseDocs[assoc.id];
					delete doc.courseAssociations[assoc.id];
					erSvc.closeLoading();
					getDocumentAssociations();
				}
			});
		};

		$scope.addDocToCourse = function(doc){
			var newAssoc = {
				"documentid":doc.id,
				"accountid":$scope.accountid,
				"eventid":$scope.selectedEvent.id,
				"courseid":$scope.selectedSession.courseid
			};
			dataSvc.createOrUpdateRecord({"table":"document_association","record":newAssoc})
			.then(function(res){
				newAssoc.id = res;
				doc.courseAssociations[newAssoc.id] = newAssoc;
				$scope.selectedSession.visibleDocs[newAssoc.id] = doc;
				$scope.selectedSession.courseDocs[newAssoc.id] = doc;
				getDocumentAssociations();
			});
		};

		$(document).on('change', '#docInput', function() {
			var newName = $('#docInput').val().replace(/\\/g, '/').replace(/.*\//, '');
			//prevent upload of duplicates
			var dup = false;
			var matchedDoc;
			angular.forEach($scope.documents,function(doc){
				if(doc.filepath.replace(/\\/g, '/').replace(/.*\//, '') == newName){
					if(doc.category == 'course'){
						dup = true;
						matchedDoc = doc;
					}
				}
			});
			if(dup){
				confirmData = {
					"text":"A document with this name already exists.  Would you like to associate the document with this event?",
					"title":"Unable to Upload Document"}
				erSvc.easyRegConfirm(confirmData,'Yes', 'No').then(function(res){
					if(res){
						var newAssoc = {
							"documentid":matchedDoc.id,
							"accountid":$scope.accountid,
							"eventid":$scope.selectedEvent.id,
							"courseid":$scope.selectedSession.courseid
						};
						dataSvc.createOrUpdateRecord({
							"table":"document_association",
							"record": newAssoc
						},$scope.selectedEvent.id).then(function(res){
							newAssoc.id = res;
							matchedDoc.courseAssociations[newAssoc.id] = newAssoc;
							$scope.selectedSession.courseDocs[newAssoc.id] = matchedDoc;
							$scope.selectedSession.visibleDocs[newAssoc.id] = matchedDoc;
						});
					}
				});
				$('#docInput').val('');
			}else{
				 $scope.selectDocument();
			}
		});

		$scope.newDoc = {};
		$scope.selectDocument = function(){
			$scope.$apply(function(){
				$scope.newDoc.name = $('#docInput').val().replace(/\\/g, '/').replace(/.*\//, '');
				$scope.newDoc.description = '';
				$scope.newDoc.author = $scope.userData.last_name + ', ' + $scope.userData.first_name;
				$scope.newDoc.upload_userid = $scope.userData.id;
				$scope.newDoc.classification = '1';
			});
		};

		$scope.updateDocClass = function(doc){
			dataSvc.createOrUpdateRecord({"table":"documents","record":doc}).then(function(){
				doc.updated = true;
				setTimeout(function(){
					$scope.$apply(function(){
						doc.updated = false;
					});
				}, 1300);
			});
		};

		$scope.uploadDoc = function(){
			erSvc.loadingDialog("Uploading Document");
			var filename = $('#docInput')[0].files[0].name;
			var folder = 'documents/account' + $scope.accountid + '/course_docs';
			erSvc.uploadDocument($('#docInput'), folder).then(function(res){
				if(res == 'success'){
					erSvc.easyRegAlert({"text":"Your Document has been uploaded","title":"Success"}, true);
					$scope.newDoc.name = '';
					var newDoc = {
						"accountid":$scope.accountid,
						"category": 'course',
						"author":$scope.newDoc.author,
						"description":$scope.newDoc.description,
						"classification":$scope.newDoc.classification,
						"date_uploaded":erSvc.today(),
						"filepath": folder + '/' + filename,
						"upload_userid":erSessionData.userData.id,
						"eventAssociations":{},
						"courseAssociations":{}
					};

					dataSvc.createOrUpdateRecord({"table":"documents","record":newDoc},$scope.selectedEvent.id)
					.then(function(res){
						newDoc.id = res;
						$scope.documents[res] = newDoc;
						var newAssoc = {"documentid":res,"accountid":$scope.accountid};
						newAssoc.eventid = $scope.selectedEvent.id;
						if($scope.selectedSession) newAssoc.courseid = $scope.selectedSession.courseid;
						dataSvc.createOrUpdateRecord({
							"table":"document_association",
							"record":newAssoc
						},$scope.selectedEvent.id).then(function(res){
							newAssoc.id = res;
							$scope.documents[newDoc.id] = newDoc;
							newDoc.courseAssociations[newAssoc.id] = newAssoc;
							$scope.selectedSession.visibleDocs[newAssoc.id] = newDoc;
							$scope.selectedSession.courseDocs[newAssoc.id] = newDoc;
							getDocumentAssociations();
						});
						//submit another course association as a general one with no eventid
						newAssoc.eventid = '';
						dataSvc.createOrUpdateRecord({
							"table":"document_association",
							"record":newAssoc
						},$scope.selectedEvent.id);
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

		/** ************* EVENT REQUEST ************* **/

		$scope.requestEvent = function(){
			var req = {
				"eventid":$scope.selectedEvent.id,
				"userid":$scope.userid,
				"request_participation":true,
				"request_notes":$scope.requestNotes
			};
			dataSvc.createOrUpdateRecord({"table":"user_event","record":req})
			.then(function(resp){
				if(resp == 'error'){
					erSvc.easyRegAlert({"text":"There was an error submitting your request.  Please contact the event administrator.","title":"Error"});
				}else{
					erSvc.easyRegAlert({"text":"Your request has been submitted.","title":"Success"});
					sendEmail('request');
					angular.forEach($scope.master_email_alerts,function(alert){
						if(alert.alert_evt_req == '1') sendEvtReqEmail(alert.email);
					});
				}
				$scope.events[$scope.selectedEvent.id].requestedParticipation = true;
				$scope.selectedEvent = null;
			});
		};

		function sendEvtReqEmail(recip){
			var subject = "New Event Participation Request";
			var body = $scope.userData.first_name + " " + $scope.userData.last_name + " has submitted an event participation request. <br/><br/>";
			body += "Event: " + $scope.selectedEvent.name + "<br/><br/>";
			body += "Requested role: " + ($scope.requestRole == 'a' ? "Staff" : "Presenter");
			if($scope.requestNotes){
				body += "<br/><br/>Request Note: <br/>" + $scope.requestNotes;
			}
			erSvc.sendEmail(recip, subject, body, replytoemail);
		}

		/** ************* MY SCHEDULE TAB ************* **/

		$scope.getRoster = function(session){
			var signups = [];
			angular.forEach($scope.sectionSignups,function(signup){
				if(signup.sectionid == session.sectionid){
					signups.push(signup.last_name + ', ' + signup.first_name + ' - ' + signup.email);
				}
				signups.sort();
			});
			var msg = '';
			angular.forEach(signups,function(signup){
				msg += "<div>" + signup + "</div>";
			});
			erSvc.easyRegAlert({"text":msg,"title":"Roster - " + session.course});
		};

		$scope.getStatusMessage = function(event){
			if(event.attending) return 'Scheduled to Attend';
			else if(event.requestedDenied) return "Request To Attend Declined";
			else if(event.requestedParticipation)return "Request Pending";
			else return "Not Scheduled";
		};

		$scope.updateAttendance = function(session){
			const rec = { id:session.sectionid, attendance:session.attendance };
			dataSvc.createOrUpdateRecord({"table":"sections","record":rec}).then(() => {
				erSvc.easyRegAlert({"text":"Attendance Submitted","title":"Attendance Updated"});
			});
		};

		/** ************* ININERARY TAB  ************* **/

		$scope.updateEventData = function(){
			erSvc.loadingDialog();
			let evtData = $scope.selectedEvent.userEventData;
			evtData.eventid = evtData.eventid || $scope.selectedEvent.id; 
			evtData.userid = evtData.userid || $scope.userid;
			dataSvc.createOrUpdateRecord({"table":"user_event","record":evtData}).then(function(resp){
				if(resp != 'error'){
					if(Number(resp))evtData.id = resp;
					erSvc.easyRegAlert({"text":"Your Itinerary Has Been Saved","title":"Success"});
					angular.forEach($scope.master_email_alerts,function(alert){
						if(alert.alert_itinerary_chg == '1') sendItineraryUpdateEmail(alert.email);
					});
				}else{
					erSvc.easyRegAlert({"text":"There was an error saving your updates. Please contact the event administrator.","title":"Error"});
				}
				erSvc.closeLoading();
			});
		};

		$scope.updateUserEvtData = function(whichData){
			erSvc.loadingDialog();
			var userEvtData = $scope.selectedEvent.userEventData;
			var submitData = {
				"id": userEvtData.id,
				"eventid": $scope.selectedEvent.id,
				"userid": userEvtData.userid || $scope.userData.id
			};
			if(whichData == 'schedule'){
				submitData["sched_signoff"] = userEvtData.sched_signoff;
				submitData["sched_notes"] = userEvtData.sched_notes;
			}else if(whichData == 'participation'){
				submitData["request_notes"] = userEvtData.request_notes;
			}
			dataSvc.createOrUpdateRecord({"table":"user_event","record":submitData})
			.then(function(resp){
				if(resp != 'error'){
					if(Number(resp))$scope.selectedEvent.userEventData.id = resp;
					erSvc.easyRegAlert({"text":"Your response has been submitted","title":"Success"});
					angular.forEach($scope.master_email_alerts,function(alert){
						if(alert.alert_sched_signoff == '1' && whichData == 'schedule'){
							sendSchedUpdateEmail(alert.email);
						}
					});
				}else{
					erSvc.easyRegAlert({"text":"There was an error saving your updates. Please contact the event administrator.","title":"Error"});
				}
				erSvc.closeLoading();
			});
		};

		function sendItineraryUpdateEmail(recip){
			var ued =  $scope.selectedEvent.userEventData;
			var subject = "Event Itinerary Update";
			var body = $scope.userData.first_name + " " + $scope.userData.last_name + " has updated his/her itinerary. <br/><br/>";
			body += "Travel Method: " + (ued.travel_method == 'drive' ? "Driving" : "Flying") + "<br/>";
			body += "Arrival: " + ued.arrival_date + " " + ued.arrival_time + " " + ued.arrival_flight_num + "<br/>";
			body += "Departure: " + ued.departure_date + " " + ued.departure_time + " " + ued.departure_flight_num + "<br/>";
			if(ued.shirt_quantity){
				body += "Shirt Request: " + ued.shirt_quantity + " " + (ued.shirt_gender == "F" ? "Female " : "Male ") + ued.shirt_size;
			}
			body += "Hotel: " + (ued.hotel_room_type=="queen" ? "Queen " : "King ");
			body += (ued.hotel_smoking == 'non' ? "Non-Smoking" : "Smoking") + "<br/>";
			body += "Notes: " + ued.notes;
			erSvc.sendEmail(recip, subject, body, replytoemail);
		}

		function sendSchedUpdateEmail(recip){
			var ued =  $scope.selectedEvent.userEventData;
			var subject = "Easyreg Presenter Schedule Feedback";
			var body = $scope.userData.first_name + " " + $scope.userData.last_name + " has responded regarding his/her schedule. <br/><br/>";
			body += "Status: " + (ued.sched_signoff == '1' ? "Approved" : "Not Approved") + "<br/>";
			if(ued.sched_notes)	body += "Notes: " + ued.sched_notes + "<br/>";
			erSvc.sendEmail(recip, subject, body, replytoemail);
		}

		sendEmail = function(){
			var subject = "Event Request Received";
			var body =  "Thank you for expressing interest in attending our event \
				( " + $scope.selectedEvent.name + "). <br/> Someone will review your request, \
				and you will receive an email when your request has been reviewed.";
			erSvc.sendEmail($scope.userData.email, subject, body, replytoemail);
		};

		$scope.closeRightDialog = function(){
			$(".dialogRight").hide(500);
		};

		/** ************* EXPENSES ************* **/
		let receiptChange = false;
		$('#receiptInput').change(() => receiptChange = true);

		$scope.newExpense = function(){
			receiptChange = false;
			$scope.selectedExpense = {
				"userid":$scope.userid,
				"eventid":$scope.selectedEvent.id
			}
			$('#expenseDialog').show(500);
		};

		$scope.$watch('selectedExpense.expense_category_id', function(){
			if(!$scope.selectedExpense) return;
			let cat = $scope.expenseCategories[$scope.selectedExpense.expense_category_id];
			if(cat)	$scope.selectedExpense.max = cat.max;
		});

		$scope.editExpense = function(exp){
			receiptChange = false;
			$scope.originalExpense = exp;
			$scope.selectedExpense = angular.copy(exp);
			$('#expenseDialog').show(500);
		};

		$scope.exceedsCap = function(){
			if(!$scope.selectedExpense || !$scope.selectedExpense.max) return false;
			else return Number($scope.selectedExpense.amount) > $scope.selectedExpense.max;
		};

		$scope.saveExpense = function(){
			if(!$scope.selectedExpense.expense_date || !$scope.selectedExpense.amount || !$scope.selectedExpense.expense_category_id){
				msg = "Expense date, category, and amount required.";
				erSvc.easyRegAlert({"text":msg,"title":"Unable to Save Expense"});
				return;
			}
			erSvc.loadingDialog();
			if(receiptChange){
				let dt = new Date();
				let fileExt = $('#receiptInput')[0].files[0].name;
				fileExt = fileExt.substring((fileExt.lastIndexOf('.')+ 1));
				if(!['png','jpg','jpeg','gif','pdf','svg'].includes(fileExt.toLowerCase())){
					let text = "Invalid file format. Must be one of: <ul>";
					text += "<li>PNG</li><li>JPG</li><li>JPEG</li><li>GIF</li>";
					text += "<li>SVG</li><li>PDF</li>";
					text += "</ul>";
					erSvc.easyRegAlert({"text":text,"title":"Unable to Upload Receipt"});
					erSvc.closeLoading();
					return;
				}
				let filename = `${dt.getFullYear()}-${$scope.selectedEvent.prefix}`;
				filename += `-${erSessionData.userData.last_name}${erSessionData.userData.id}-`;
				filename += $scope.expenseCategories[$scope.selectedExpense.expense_category_id].name;
				filename += '-' + dt.getMonth() + 1;
				filename += `${dt.getDate()}${dt.getHours()}${dt.getMinutes()}`;
				filename += `${dt.getSeconds()}`;
				filename += '.' + fileExt;
				$scope.uploadReceipt(filename);
				$scope.selectedExpense.receipt = filename;
			}
			dataSvc.createOrUpdateRecord({"table":"staff_expenses","record":$scope.selectedExpense}).then(function(res){
				if(!$scope.selectedExpense.id){
					$scope.selectedExpense.id = res;
					$scope.expenses[res] = $scope.selectedExpense;
				}else{
					$scope.expenses[$scope.selectedExpense.id] = $scope.selectedExpense;
				}
				$scope.closeRightDialog();
				erSvc.closeLoading();
			});
		};

		$scope.uploadReceipt = function(filename){
			let folder = `documents/account${$scope.accountid}/staff_receipts/evt-${$scope.selectedEvent.id}`;
			erSvc.uploadDocument($('#receiptInput'), folder, filename).then(function(res){
				$(':file').val('');
			});
		};// End uploadreceipt()

		$scope.removeReceipt = function(){
			$scope.selectedExpense.receipt = '';
		};

		$scope.confirmExpenseDelete = function(){
			let txt = "Delete this expense?";
			erSvc.easyRegConfirm({"text":txt,"title":"Delete Expense"},"Confirm","Cancel")
			.then((res) => 	{ if(res) deleteExpense(); } );
		};

		function deleteExpense(){
			erSvc.loadingDialog();
			dataSvc.deleteRecord({"table":"staff_expenses","id":$scope.originalExpense.id}).then(function(){
				delete $scope.expenses[$scope.originalExpense.id];
				erSvc.closeLoading();
				$scope.closeRightDialog();
			});
		}

		$scope.showReceipt = function(expense){
			let folder = `documents/account${$scope.accountid}/staff_receipts/evt-${$scope.selectedEvent.id}`;
			if(expense.receipt.indexOf('.pdf') > 0){
				$('#receiptDialog iframe').attr('src', folder + '/' + expense.receipt);
				$('#receiptDialog iframe').show();
				$('#receiptDialog img').hide();
			}else{
				$('#receiptDialog img').attr('src', folder + '/' + expense.receipt);
				$('#receiptDialog iframe').hide();
				$('#receiptDialog img').show();
			}
			$('#receiptDialog').show('300');
		};

		$scope.hideReceipt = () =>	$('#receiptDialog').hide('300') ;

		$scope.expenseTotal = function(prop){
			ttl = 0;
			angular.forEach($scope.expenses,(exp) => ttl += (Number(exp[prop]) || 0));
			return ttl;
		};

		/** ************* SURVEY ************* **/
		let surveyCourses = [];
		let surveyQeustions = [];
		$scope.surveyFilters = {
			course:{"val":"","prop":"course","label":"Course","options":surveyCourses},
			type:{"val":"","prop":"questionType","label":"Answer Type","options":["Text","Yes/No","Numeric"]},
			type:{"val":"","prop":"question","label":"Question","options":surveyQeustions}
		}

		function getSurveyResults(){
			dataSvc.getArray({'query':'surveyResponsesPresenter','eventid':$scope.selectedEvent.id})
			.then(function(resp){
				$scope.sectionResponses = [];
				$scope.eventSurveyResponses = [];
				angular.forEach(resp,function(res){
					res.show = true;
					if(!surveyCourses.includes(res.course)) surveyCourses.push(res.course);
					if(!surveyQeustions.includes(res.question)) surveyQeustions.push(res.question);
					if(res.questionAssoc == 'section') $scope.sectionResponses.push(res);
					else $scope.eventSurveyResponses.push(res);
				});
				getSummaryData();
			});
		}

		$scope.quickSearch = "";

		$scope.surveyFilter = function(){
			let noneFound = true;
			let s = $scope.quickSearch.toLowerCase();
			for(let i = 0; i < $scope.sectionResponses.length; i++){
				let r = $scope.sectionResponses[i];
				r.show = true;
				if(s){
					r.show = (
						r.event.toLowerCase().indexOf(s)>=0 ||
						r.course.toLowerCase().indexOf(s)>=0 ||
						r.question.toLowerCase().indexOf(s)>=0 ||
						r.response.toLowerCase().indexOf(s)>=0
					);
				}
				if(!r.show) continue;
				angular.forEach($scope.surveyFilters,function(f){
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
				if(r.show) noneFound = false;
			}//End response loop
			$scope.noResults = noneFound;
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
			for(let i = 0; i < $scope.sectionResponses.length; i++){
				let resp = $scope.sectionResponses[i];
				if(!resp.show) continue;
				if(resp.questionType == 'numeric'){
					nt.responseCount++;
					nt.responseSum += (Number(resp.response) || 0);
					nt[resp.response].count++;
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
		}// end getSummaryData()

	})//End controller
	.filter('eventStatus', function(){
		return function(events){
			var filtered = [];
			angular.forEach(events,function(event){
				event.visible_to_staff = event.visible_to_staff || '0';
				if(event.visible_to_staff != '0' && (event.status == 'future' || event.status == 'recent')){
					filtered.push(event);
				}
			});
			filtered.sort(function(a,b){
				return a.startdate > b.startdate ? 1 : -1;
			});
			return filtered;
		}
	});//end controller
</script>
</head>

<body ng-app="regApp">
<top-nav ng-controller="navController"></top-nav>
<div class="container-fluid" ng-controller="regController">
	<div class="col-sm-11 form-horizontal">
		<H1 class="page-header">Events</H1>
		<div class="mb-2 row"> <!-- EVENT LIST -->
			<label class='control-label col-sm-1'></label>
			<span class='col-sm-10'>
				<table class="table">
					<tr>
						<th>Event</th>
						<th>Dates</th>
						<th>Status</th>
					</tr>
					<tr ng-repeat="event in events | eventStatus"
						ng-class="{selectedEvent : event == selectedEvent}">
						<td>
							<a href="#" ng-click="getEventData(event)">{{event.name}}</a>
						</td>
						<td>
							{{event.startdate | mySqlToLocalDate}} -
							{{event.enddate | mySqlToLocalDate}}
						</td>
						<td>
							<span>{{getStatusMessage(event)}}</span>
						</td>
					</tr>
				</table>
			</span>
		</div> <!-- END EVENT LIST -->

		<!-- EVENT REQUEST -->
		<div ng-show="!attending && !requestedParticipation && selectedEvent" class="center">
			<h3>You are not currently scheduled to attend this event.</h3>
			<div style="width:40em;margin:auto;text-align:left" class="bold" ng-show="requestsEnabled">
				Participation Comment<br/>
				<textarea class="form-control" ng-model="requestNotes" rows="5"
					style="width:40em;display:inline-block;">
				</textarea>
			</div>
			<div style="width:40em;margin:auto;text-align:right" ng-show="requestsEnabled">
				<button class="btn btn-primary" ng-click="requestEvent()" style="margin-left:25px">
					Request Participation
				</button>
			</div>
		</div>
		<div ng-show="!attending && requestedParticipation && selectedEvent" class="center">
			<h3>You have requested to attend this event. Your request is currently pending.</h3>
		</div>
		<!-- END EVENT REQUEST -->

		<div class="form-group col-sm-10" style="margin-left:8%" 
			ng-show="selectedEvent && attending">
			<ul class="nav nav-tabs">
				<li class="nav-link active">
					<a data-bs-toggle="tab" href="#mySchedule">My Schedule</a>
				</li>
				<li class="nav-link">
					<a data-bs-toggle="tab" href="#myItinerary" 
						ng-hide="accountid == '1006'">My Itinerary
					</a>
				</li>
				<li class="nav-link">
					<a data-bs-toggle="tab" href="#eventSchedule">Event Schedule</a>
				</li>
				<li class="nav-link" ng-show="hasExpenses && expensesEnabled">
					<a data-bs-toggle="tab" href="#expenses">Expenses</a>
				</li>
				<li class="nav-link" ng-show="suveyEnabled">
					<a data-bs-toggle="tab" href="#surveyResults">Survey Results</a>
				</li>
			</ul>

			<div id="mySchedule" class="tab-pane fade show active">
				<div class='alert alert-info' role='alert' style="margin-top:.5em;padding:5px" ng-if="docsEnabled">
					<b style="color:#071865;margin-right:.7em" class="bi bi-hand-index"></b>
					Need to manage your documents - 
					upload, delete, or replace existing documents with updated versions?
					Go to <a class="bold" href="user_details.php#!#documents">My Documents</a> 
				</div>
				<table class="table">
					<thead>
						<tr>
							<th>Session</th>
							<th>Date/Time</th>
							<th>Course</th>
							<th>Room</th>
							<th>Signups</th>
							<th>Attendance</th>
							<th ng-if="docsEnabled">Documents</th>
							<th ng-if="docsEnabled"></th>
						</tr>
					</thead>
					<tbody>
						<tr ng-repeat="session in userEventSessions">
							<td>{{session.session}}</td>
							<td>{{session.date}} {{session.starttime}} - {{session.endtime}}</td>
							<td>{{session.course}}</td>
							<td>{{session.room}}</td>
							<td>
								<a ng-click="getRoster(session)" href="#">{{session.signups}}</a>
							</td>
							<td>
								<input ng-model="session.attendance" type="number" ng-blur="updateAttendance(session)" 
									style="width:5em" class="form-control"  string-to-number/>
							</td>
							<td ng-show="docsEnabled">
								<div ng-repeat="doc in session.visibleDocs">
									<a href="/{{doc.filepath}}" target="_blank">{{doc.filepath | nameFromFilepath}}</a>
								</div>
							</td>
							<td ng-show="docsEnabled">
								<span class="btn btn-info btn-xs bi bi-pencil-fill"
									ng-click="editDocs(session)" title="Edit Documents">
								</span>
							</td>
						</tr>
					</tbody>
				</table>

				<table class="table">
					<tr>
						<td class="bold" style="width:15em">Participation Comment</td>
						<td style="width:35em">
							<textarea ng-model="selectedEvent.userEventData.request_notes"
								class="form-control" rows="5">
							</textarea>
						</td>
						<td>
							<button class="btn btn-primary" ng-click="updateUserEvtData('participation')">
								Update Comment
							</button>
						</td>
					</tr>
				</table>

				<table class="table" ng-show="selectedEvent.staff_sched_review == '1'">
					<tr>
						<th style="width:15em">Agree to this schedule</th>
						<th style="width:35em">Schedule Comments</th>
						<th></th>
					</tr>
					<tr>
						<td style="border-top:none">
							<input type="checkbox" ng-model="selectedEvent.userEventData.sched_signoff"
								ng-true-value="1" ng-false-value="0"/><br/>
						</td>
						<td style="border-top:none">
							<textarea cols="50" rows="4" class="form-control"
								ng-model="selectedEvent.userEventData.sched_notes"></textarea>
						</td>
						<td style="border-top:none">
							<button class="btn btn-primary" ng-click="updateUserEvtData('schedule')">
								Submit Schedule Review
							</button>
						</td>
					</tr>
				</table>
			</div> <!-- END MY SCHEDULE -->

			<div id="myItinerary" class="tab-pane fade">
				<div class="mb-2 row" style="margin-top:.5em">
					<label class="col-2 col-form-label">Travel Method</label>
					<span class='col-sm-6'>
						<select ng-model="selectedEvent.userEventData.travel_method"
							class="form-select">
							<option value=""></option>
							<option value="drive">Driving</option>
							<option value="fly">Flying</option>
						</select>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Arrival</label>
					<span class='col-sm-2'>
						Date
						<input type="text" class="form-control datepicker"
							ng-model="selectedEvent.userEventData.arrival_date" />
					</span>
					<span class='col-sm-2'>
						Time
						<input type="text" class="form-control"
							ng-model="selectedEvent.userEventData.arrival_time" />
					</span>
					<span class='col-sm-2' ng-show="selectedEvent.userEventData.travel_method == 'fly'">
						Airline	
						<input type="text" class="form-control"
							ng-model="selectedEvent.userEventData.arrival_airline" />
					</span>
					<span class='col-sm-2' ng-show="selectedEvent.userEventData.travel_method == 'fly'">
						Flight #
						<input type="text" class="form-control"
							ng-model="selectedEvent.userEventData.arrival_flight_num" />
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Departure</label>
					<span class='col-sm-2'>
						Date
						<input type="text" class="form-control datepicker"
							ng-model="selectedEvent.userEventData.departure_date" />
					</span>
					<span class='col-sm-2'>
						Time
						<input type="text" class="form-control"
							ng-model="selectedEvent.userEventData.departure_time" />
					</span>
					<span class='col-sm-2' ng-show="selectedEvent.userEventData.travel_method == 'fly'">
						Airline
						<input type="text" class="form-control"
							ng-model="selectedEvent.userEventData.departure_airline" />
					</span>
					<span class='col-sm-2' ng-show="selectedEvent.userEventData.travel_method == 'fly'">
						Flight #
						<input type="text" class="form-control"
							ng-model="selectedEvent.userEventData.departure_flight_num" />
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Shirt(s) Needed</label>
					<span class='col-sm-3'>
						Gender
						<select ng-model="selectedEvent.userEventData.shirt_gender" class="form-select">
							<option value="F">Female</option>
							<option value="M">Male</option>
						</select>
					</span>
					<span class='col-sm-3'>
						Size
						<select ng-model="selectedEvent.userEventData.shirt_size" class="form-select">
							<option value="s">Small</option>
							<option value="m">Medium</option>
							<option value="l">Large</option>
							<option value="xl">X-Large</option>
							<option value="xxl">XX-Large</option>
							<option value="xxxl">XXX-Large</option>
						</select>
					</span>
					<span class='col-sm-1'>
						Qty
						<input type="text" ng-model="selectedEvent.userEventData.shirt_quantity"
							class="form-control" />
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Hotel</label>
					<span class='col-sm-3'>
						Smoking
						<select ng-model="selectedEvent.userEventData.hotel_smoking" class="form-select">
							<option value="non">Non-Smoking</option>
							<option value="smoking">Smoking</option>
						</select>
					</span>
					<span class='col-sm-3'>
						Room Type
						<select ng-model="selectedEvent.userEventData.hotel_room_type" class="form-select">
							<option value="king">King</option>
							<option value="queen">Queen/Double</option>
						</select>
					</span>
				</div>
				<div class="mb-2 row">
					<label class="col-2 col-form-label">Notes</label>
					<span class='col-sm-8'>
						<textarea ng-model="selectedEvent.userEventData.notes"
							rows="3" cols="50" class="form-control" />
						</textarea>
					</span>
				</div>
				<div class="mb-2 row">
					<span class='col-sm-10 right'>
						<button class="btn btn-primary" ng-click="updateEventData()">Update Itinerary</button>
					</span>
				</div>
			</div> <!-- END MY ITINERARY -->

			<div id="eventSchedule" class="tab-pane fade">
				<div ng-repeat="session in sessions | orderObjectBy:'starttime':false"">
					<H4 style="border-bottom:1px solid black">
						{{session.name}} {{session.weekDay}} {{session.sessionDate}}
						{{session.displayStart}} - {{session.displayEnd}}
					</H4>
					<table class="table">
						<tbody>
							<tr ng-repeat="section in session.sections | orderObjectBy:'room':false">
								<td style="width:60%">{{section.course}}</td>
								<td style="width:20%">{{section.room}}</td>
								<td>{{section.first_name}} {{section.last_name}}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div><!-- END EVENT SCHEDULE -->

			<div id="expenses" class="tab-pane fade">
				<div class="button-row" style="margin-bottom:.5em">
					<button class="btn btn-success" ng-click="newExpense()">
						New Expense &nbsp;
						<span class="badge"><i class="bi bi-plus-circle"></i></span>
					</button>
				</div>						
				<table class="table scrollable striped">
					<thead>
					<tr>
						<th></th>
						<th>Date</th>
						<th>Category</th>
						<th>Description</th>
						<th class='center'>Receipt</th>
						<th class="right" style="width:7em;margin-right:2em">Amount</th>
						<th class="right">Approved</th>
						<th class="right">Paid</th>
					</tr>
					<thead>
					<tbody>
					<tr ng-repeat="expense in expenses">
						<td>
							<button class='btn btn-primary'	ng-show="expense.approved != '1'" ng-click="editExpense(expense)">
								<i class="bi bi-pencil"></i>
							</button>
						</td>
						<td>{{expense.expense_date | mySqlToLocalDate}}</td>
						<td>{{expenseCategories[expense.expense_category_id].name}}</td>
						<td>{{expense.description}}</td>
						<td class="center">
							<span ng-show="!expense.receipt">No Receipt</span>
							<span ng-show="expense.receipt">
								<button class="btn btn-primary btn-xs" 
									ng-click="showReceipt(expense)"
									style="margin-left:1em">
									<span class="bi bi-eye"></span>
								</button>
							</span>
						</td>
						<td class="right" style="margin-right:2em">{{expense.amount | currency}}</td>
						<td class="right">{{expense.approved_amt | currency}}</td>
						<td class="right">{{expense.paid | currency}}</td>
					</tr>
					</tbody>
					<tr style="background:silver">
						<td class="right bold" colspan="5" style="margin-right:2em">Total</td>
						<td class="right bold" style="margin-right:2em">{{expenseTotal('amount') | currency}}</td>
						<td class="right bold" style="margin-right:2em">{{expenseTotal('approved_amt') | currency}}</td>
						<td class="right bold" style="margin-right:2em">{{expenseTotal('paid') | currency}}</td>
					</tr>
				</table>
			</div><!-- END EXPENSES -->

			<div id="surveyResults" class="tab-pane fade">
				<div class="row">
					<div class='searchDiv'>
						Quick Search
						<input class="form-control" ng-model="quickSearch" ng-keyup="surveyFilter()" style="width:15em"/>
					</div>
					<div class="searchDiv" ng-repeat="fltr in surveyFilters">
						{{fltr.label}}
						<select ng-change="surveyFilter()" class="form-select" style="width:20em"
							ng-model="fltr.val">
							<option value="">--All {{fltr.label}}s--</option>
							<option ng-repeat="opt in fltr.options | orderBy" value="{{opt}}">{{opt}}</option>
						</select>
					</div>
				</div> <!-- End Filters -->
				<div id="responseSummary">
					<table style="margin:auto;margin-top:1em">
						<tr>
							<td style="padding-right:8em">
								<ul style="list-style-type:none">
									<li class='bold'>AVE: {{numericTotals.responseAve}}</li>
									<li ng-repeat="i in [5,4,3,2,1]">
										{{i}} Star: <meter value="{{numericTotals[i].percent}}" min="0" max="100"></meter>
										{{numericTotals[i].percent}}%
									</li>
								</ul>
							</td>
							<td class='center' style="width:5em;vertical-align:top;"><b>Yes</b><br>{{yesResponses}}</td>
							<td class='center' style="width:5em;vertical-align:top;"><b>No</b><br>{{noResponses}}</td>
						</tr>
					</table>
				</div>
				<div class="button-row">
					<button class="btn btn-primary exportResultsBtn"
						data-table-id="responseTable" data-file-name="Survey Responses">
						<span class="bi bi-floppy"></span> Export To CSV
					</button>
				</div>
				<table class="table" id="responseTable">
					<thead>
						<tr>
							<th>Session</th>
							<th>Course</th>
							<th>Question</th>
							<th>Response</th>
						</tr>
					</thead>
					<tbody>
						<tr ng-repeat="res in sectionResponses" ng-show="res.show">
							<td>{{res.session_name}}</td>
							<td>{{res.course}}</td>
							<td>{{res.question}}</td>
							<td>{{res.response}}</td>
						</tr>
					</tbody>
				</table>
			</div> <!--  -->

		</div> <!-- END EVENT TAB CONTENT -->

	</div> <!-- END MAIN CONTENT -->

	<div id="documentsDiv" class="dialogRight">
		<div class="dialogTitle">{{dialogTitle}}</div>
		<div class="dialogContents">
			<H3>{{selectedSession.course}}</H3>
			<div class='alert alert-info' role='alert' ng-show="noCurAssociations()">
				No documents have been explicitly associated with this course for the current event.<br/>
				Attendees will see the most recently uploaded document associated with this course.
			</div>

			<label class="btn btn-primary btn-primary">
				<i class="bi bi-plus-circle"></i>
				New Document
				<input type="file" id="docInput" name="document" style="display:none"/>
			</label>
			<input type="text" class="form-control" readonly ng-model="newDoc.name"
				style="width:200px;display:inline-block;margin-left:2em">
			<div ng-show="newDoc.name">
				<table class="table">
					<tr>
						<td class="bold" style="width:10em">Description</td>
						<td>
							<textarea rows="2" cols="50" ng-model="newDoc.description"
								class="form-control">
							</textarea>
						</td>
					</tr>
						<td class="bold"> Classification</td>
						<td>
							<select ng-model="newDoc.classification" class="form-select">
								<option ng-value="1">Primary Presentation Document</option>
								<option ng-value="2">Supporting Document</option>
							</select>
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<label class="btn btn-danger" ng-click="newDoc.name = ''">
								Cancel
							</label>
							<label class="btn btn-primary btn-success" ng-click="uploadDoc()"
								ng-disabled="!newDoc.name">
								Upload
							</label>
						</td>
					</tr>
				</table>
			</div>

			<H3 style="text-align:left">Visible For This Event</H3>
			<table>
				<tr>
					<th style="width:28%">Document</th>
					<th style="width:33%">Classification</th>
					<th style="width:23%">Author</th>
					<th>Uploaded</th>
					<th></th>
				</tr>
				<tr ng-repeat="doc in selectedSession.visibleDocs">
					<td>
						<a href="/{{doc.filepath}}" target="_blank">
							{{doc.filepath | nameFromFilepath}}
						</a>
					</td>
					<td>
						<select ng-model="doc.classification" ng-change="updateDocClass(doc)"
							class="form-select">
							<option ng-value="1">Primary Presentation Document</option>
							<option ng-value="2">Supporting Document</option>
						</select>
						<div class='alert alert-success' role='alert' ng-show="doc.updated"
							style="display:inline-block;height:2em;padding:3px">
								&#10004;
						</div>
					</td>
					<td>{{doc.author}}</td>
					<td>{{doc.date_uploaded}}</td>
					<td>
						<button class="btn btn-danger  btn-xs" ng-click="removeCrsAssoc(doc)"
							ng-show="explicitAssoc(doc)">
							<span class="bi bi-trash" title="Remove"></span>
						</button>
					</td>
				</tr>
			</table>
			<H3 style="text-align:left" ng-show="showMyDocs()">
				My Documents Associated With Course
			</H3>
			<table ng-show="showMyDocs()">
				<tr>
					<th style="width:28%">Document</th>
					<th style="width:33%">Classification</th>
					<th style="width:23%">Author</th>
					<th>Uploaded</th>
					<th></th>
				</tr>
				<tr ng-repeat="doc in selectedSession.courseDocs" 
					ng-show="notAssociated(doc) && doc.curUser">
					<td>
						<a href="/{{doc.filepath}}" target="_blank">
							{{doc.filepath | nameFromFilepath}}
						</a>
					</td>
					<td>
						<span ng-show="doc.classification == 1">
							Primary Presentation Document
						</span>
						<span ng-show="doc.classification == 2">
							Supporting Document
						</span>
					</td>
					<td>{{doc.author}}</td>
					<td>{{doc.date_uploaded}}</td>
					<td>
						<button class="btn btn-success btn-xs" ng-click="addDocToCourse(doc)"
							title="Add To Course">
							<i class="bi bi-plus-circle"></i>
						</button>
					</td>
				</tr>
			</table>
			<H3 style="text-align:left" ng-show="showOtherDocs()">
				Other Documents Associated With Course
			</H3>
			<table ng-show="showOtherDocs()">
				<tr>
					<th style="width:28%">Document</th>
					<th style="width:33%">Classification</th>
					<th style="width:23%">Author</th>
					<th>Uploaded</th>
					<th></th>
				</tr>
				<tr ng-repeat="doc in selectedSession.courseDocs" 
					ng-show="notAssociated(doc) && !doc.curUser">
					<td>
						<a href="/{{doc.filepath}}" target="_blank">
							{{doc.filepath | nameFromFilepath}}
						</a>
					</td>
					<td>
						<span ng-show="doc.classification == 1">
							Primary Presentation Document
						</span>
						<span ng-show="doc.classification == 2">
							Supporting Document
						</span>
					</td>
					<td>{{doc.author}}</td>
					<td>{{doc.date_uploaded}}</td>
					<td>
						<button class="btn btn-success btn-xs" ng-click="addDocToCourse(doc)"
							title="Add To Course">
							<i class="bi bi-plus-circle"></i>
						</button>
					</td>
				</tr>
			</table>
			<div class="button-row">
				<button class="btn btn-primary btn-primary" ng-click="closeRightDialog()">
					<span class="bi bi-check-lg"></span> Done
				</button>
			</div>
		</div>
	</div> <!-- End Document Dialog -->
	
	<div id="expenseDialog" class="dialogRight">
		<div class="dialogTitle">
			<span ng-show="selectedExpense.id">Edit</span>
			<span ng-show="!selectedExpense.id">New</span>
			Expense
		</div>
		<table class="table">
			<tr>
				<td clas="bold">Date</td>
				<td>
					<input type="text" datePicker class="form-control"
						style="width:8em"
						ng-model="selectedExpense.expense_date" />
				</td>
			</tr>
			<tr>
				<td clas="bold">Category</td>
				<td>
					<select ng-model="selectedExpense.expense_category_id" 
						class="form-select" style="width:12em">
						<option ng-repeat="cat in expenseCategories | orderObjectBy:'name'"
							value="{{cat.id}}">
							{{cat.name}}
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<td clas="bold">Description</td>
				<td>
					<textarea class="form-control" rows="4" cols="40" style="width:30em"
						ng-model="selectedExpense.description">
					</textarea>
				</td>
			</tr>
			<tr>
				<td clas="bold">Receipt</td>
				<td>
					<span ng-show="!selectedExpense.receipt">
						<input type="file" id="receiptInput" />
					</span>
					<span ng-show="selectedExpense.receipt">
						{{selectedExpense.receipt}}
						<button class="btn btn-danger btn-xs" style="margin-left:1em"
							ng-click="removeReceipt()">
							x
						</button>
						<button class="btn btn-primary btn-xs" 
							ng-click="showReceipt(selectedExpense)"
							style="margin-left:1em">
							<span class="bi bi-eye"></span>
						</button>
					</span>
				</td>
			</tr>
			<tr>
				<td clas="bold">Amount</td>
				<td>
					<input type="number" style="width:8em;display:inline-block;" 
					class="form-control" string-to-number ng-model="selectedExpense.amount" />
						<span ng-show="selectedExpense.max" style="color:silver;margin-left:1em">
							Reimbursement Cap - <b>{{selectedExpense.max | currency}}</b>
						</span>

					<div class='alert alert-warning' role='alert' style="margin-top:4px"
						ng-show="exceedsCap()">
						Amount exceeds reimbursement cap. Full amount may not be reimbursed.
					</div>
				</td>
			</tr>
		</table>
		<div class="button-row">
			<button class="btn btn-primary" ng-click="closeRightDialog()">
				<span class="bi bi-chevron-left"></span> Cancel
			</button>
			<button class="btn btn-danger" ng-click="confirmExpenseDelete()"
				ng-show="selectedExpense.id">
				<span class="bi bi-trash"></span> Delete
			</button>
			<button class="btn btn-success" ng-click="saveExpense()">
				<span class="bi bi-check-lg"></span> Save
			</button>
		</div>
	</div> <!-- End Expense Dialog -->

	<div id="receiptDialog">
		<div id="receiptHeader">
			Receipt
			<button class="btn btn-primary btn-xs" ng-click="hideReceipt()">x</button>
		</div>
		<iframe></iframe>
		<img />
	</div>
</div> <!-- END CONTROLLER   -->
<er-Footer />
</body>
</html>
