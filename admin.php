<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>The Event Phoenix</title>
<?php
	$root = $_SERVER['DOCUMENT_ROOT'];
	include($root."/common_functions.php");
	include($root."/commonStyles.php");
	include($root."/commonJs.php");
?>
<script src="/js/tinymce/tinymce.min.js"></script>
<script src='/js/papaparse.min.js'></script>
<script src="/js/sponsorModule.js?_=<?= rand() ?>"></script>
<script src="/js/angular-route.js"></script>
<script src="/js/jquery.hotkeys.js"></script>
<script src="/js/bootstrap-wysiwyg.js"></script>
<script type="text/javascript">
	const accountid = "<?=$_SESSION['accountid'] ?>";
	const userid = "<?=$_SESSION['userid'] ?>";
	const userAccount = "<?=$_SESSION['useraccount'] ?>";
	if(!accountid || accountid != userAccount) window.location = '/login.php';
	const masterUser = ('<?=$_SESSION['master'] ?>' || '') == '1';
	const acctType = "<?=$_SESSION['acctType'] ?>";

	let menuSettings = {
		"account":{ "Account Settings":true, "Reports":true },
		"event":{ "Attendee Information": true, "Event Settings": true, "Pages": true, "Reports": true }
	};

	try{
		let local = localStorage.getItem("easyRegAdminMenu");
		if(local) menuSettings = JSON.parse(local);
	}catch(e){ console.log(e); }

	let accountRoutes = [
		{
			"label":"Account Settings",
			"show":menuSettings.account["Account Settings"],
			"access":masterUser,
			"pages":[
				{
					"rt":"", 
					"page":"/account/views/accountDetails.html", 
					"ctrl":"acctDetailsCtrl",
					"label":"Account Configuration",
					"subMenu":[
						{
							"rt":"", 
							"page":"/account/views/accountDetails.html", 
							"ctrl":"acctDetailsCtrl",
							"label":"Account Details"
						},{
							"rt":"#attendeeMsgs", 
							"page":"/account/views/accountDetails.html", 
							"ctrl":"acctDetailsCtrl",
							"label":"Attendee Messages"
						},{
							"rt": "attendee_menu",
							"page": "/account/views/attendee_menu.html",
							"ctrl": "attendeeMenu",
							"label":"Attendee Menu"
						},{
							"rt": "event_categories",
							"page": "/account/views/eventCategories.html",
							"ctrl": "eventCategories",
							"label":"Event Categories"
						},{
							"rt":"acct_features",
							"page":"/account/views/features.html",
							"ctrl":"acctFeatures",
							"label":"Features",
							"nonTicket":true
						},{
							"rt":"#imgMgmt", 
							"page":"/account/views/accountDetails.html", 
							"ctrl":"acctDetailsCtrl",
							"label":"Image Mgmt"
						},{
							"rt":"inventory_settings", 
							"page":"/account/views/inventorySettings.html", 
							"ctrl":"inventorySettings",
							"enabled":"enable_inventory",
							"label": "Inventory Settings"
						},{
							"rt":"#onlinePayments", 
							"page":"/account/views/accountDetails.html", 
							"ctrl":"acctDetailsCtrl",
							"label":"Online Payments"
						},{
							"rt":"qr_config", 
							"page":"/account/views/qrConfig.html", 
							"ctrl":"qrConfig",
							"label": "QR Configuration",
							"nonTicket":true
						},{
							"rt":"reg_fields", 
							"page":"/account/views/registrationFields.html", 
							"ctrl":"registrationFields",
							"label":"Registration Fields"
						},{
							"rt":"acct_reg_types", 
							"page":"/account/views/registrationTypes.html", 
							"ctrl":"acctRegistrationTypes",
							"label":"Registration Types",
							"nonTicket":true
						},{
							"rt":"season_passes", 
							"page":"/account/views/seasonPasses.html", 
							"enabled":"enable_season_pass",
							"ctrl":"seasonPassesAct",
							"label":"Season Passes"
						},{
							"rt":"security_groups", 
							"page":"/account/views/securityGroups.html", 
							"ctrl":"securityGroups",
							"label": "Security Groups"
						},{
							"rt":"#staffMsgs", 
							"page":"/account/views/accountDetails.html", 
							"ctrl":"acctDetailsCtrl",
							"label":"Staff Request Messages",
							"nonTicket":true
						},{
							"rt":"survey_management", 
							"page":"/account/views/survey_management.html", 
							"ctrl":"surveyMgmt",
							"enabled":"enable_survey",
							"label": "Survey Mgmt"
						},{
							"rt":"vendor_settings", 
							"page":"/account/views/vendor_settings.html", 
							"ctrl":"vendorSettingsCtrl",
							"label": "Vendor Settings",
							"nonTicket":true
						}
					]
				},{
					"rt":"acme_mgmt", 
					"page":"/account/views/acme_mgmt.html", 
					"ctrl":"acmeMgmt",
					"label":"ACME Requests"
				},{
					"rt":"admin_alerts", 
					"page":"/account/views/adminAlerts.html", 
					"ctrl":"adminAlerts",
					"label":"Alerts"
				},{
					"rt":"account_registrations", 
					"page":"/account/views/account_registrations.html", 
					"ctrl":"accountRegistrations",
					"label":"All Registrations"
				},{
					"rt":"course_management", 
					"page":"/account/views/manageCourses.html", 
					"ctrl":"courseMgmt",
					"label":"Courses",
					"nonTicket":true
				},{
					"rt":"document_management", 
					"page":"/account/views/documentMgmt.html", 
					"ctrl":"documentMgmt",
					"enabled":"enable_docs",
					"label":"Document Mgmt"
				},{
					"rt":"event_management", 
					"page":"/account/views/manageEvents.html", 
					"ctrl":"evtMgmt",
					"label":"Event Mgmt"
				},{
					"rt":"expense_management", 
					"page":"/account/views/expenseManagement.html", 
					"ctrl":"expenseMgmt",
					"label":"Expense Mgmt",
					"conditional":true,
					"enabled":"enable_staff_expense",
					"subMenu":[
						{
							"rt":"expense_categories", 
							"page":"/account/views/expenseCategories.html", 
							"ctrl":"expenseCatMgmt",
							"enabled":"enable_staff_expense",
							"label":"Expense Categories"
						},{
							"rt":"expense_pymt_methods", 
							"page":"/account/views/expensePymtMethods.html", 
							"ctrl":"expPymtMethods",
							"enabled":"enable_staff_expense",
							"label":"Payment Methods"
						},{
							"rt":"expense_management", 
							"page":"/account/views/expenseManagement.html", 
							"ctrl":"expenseMgmt",
							"label":"Staff Expenses",
							"enabled":"enable_staff_expense"
						}
					]
				},{
					"rt":"inventory_management", 
					"page":"/account/views/inventory.html", 
					"ctrl":"inventoryCtrl",
					"enabled":"enable_inventory",
					"label":"Inventory Mgmt"
				},{
					"rt":"users", 
					"page":"/account/views/users.html", 
					"ctrl":"users",
					"label":"Staff"
				},{
					"rt":"acctReports", 
					"page":"/account/views/reports.html", 
					"label":"Reports"
				},{
					"rt":"tracks", 
					"page":"/account/views/tracks.html", 
					"ctrl":"tracks",
					"label":"Tracks",
					"nonTicket":true
				},{
					"rt":"vendor_management", 
					"page":"/account/views/vendor_management.html", 
					"ctrl":"vendorMgmt",
					"enabled":"sponsors_enabled",
					"label":"Vendor Mgmt"
				},{	
					"rt":"vendor_details/:id", 
					"page":"/account/views/vendor_details.html", 
					"ctrl":"vendorDetails"
				},{
					"rt":"video_mgmt", 
					"page":"/account/views/videoMgmt.html", 
					"ctrl":"videoMgmt",
					"enabled":"enable_videos",
					"label":"Video Mgmt",
					"nonTicket":true
				},{
					"rt":"rpt_course_history",
				 	"page":"/account/views/reports/rpt_course_history.html",
				 	"ctrl":"rptCourseHistory"
				},{
					"rt":"email_history",
				 	"page":"/account/views/reports/email_history.html",
				 	"ctrl":"emailHistory"
				},{
					"rt":"rpt_event_sponsor_att",
				 	"page":"/account/views/reports/event_sponsor_att.html",
				 	"ctrl":"evtSponsorAtt"
				},{
					"rt":"rpt_event_user_att",
				 	"page":"/account/views/reports/rpt_event_user_att.html",
				 	"ctrl":"rptEvtUserAttendance"
				},{
					"rt":"rpt_payments",
				 	"page":"/account/views/reports/payments.html",
				 	"ctrl":"paymentsRpt"
				},{
			 		"rt":"acct_expense_rpt",
			 	 	"page":"/account/views/reports/expenses.html",
			 	 	"ctrl":"staffExpenseRpt"
			 	},{
		 	 		"rt":"acct_expense_pymts",
		 	 	 	"page":"/account/views/reports/staff_pymts.html",
		 	 	 	"ctrl":"staffPaymentsRpt"
				},{
					"rt":"rpt_outstanding_balances",
				 	"page":"/account/views/reports/outstanding_balances.html",
				 	"ctrl":"outstandingBalances"
				},{
					"rt":"rpt_registrations",
				 	"page":"/account/views/reports/registrations.html",
				 	"ctrl":"registrationsRpt"
				},{
					"rt":"rpt_report_sponsor_orders",
				 	"page":"/account/views/reports/sponsor_orders.html",
				 	"ctrl":"sponsorOrders"
				},{
					"rt": "rpt_video_orders",
					"page": "/account/views/reports/video_orders.html",
					"ctrl": "videoOrders"
				},{
					"rt":"rpt_report_users",
				 	"page":"/account/views/reports/users.html", 
				 	"ctrl":"usersRpt"
				},{
					"rt":"rpt_survey_responses",
				 	"page":"/account/views/reports/survey_responses.html",
				 	"ctrl":"surveyResponses"
				},{
					"rt":"rpt_season_pass",
				 	"page":"/account/views/reports/season_pass_orders.html",
				 	"ctrl":"seasonPasses"
				}
			]
		}
	]; // End Account Routes

	let eventRoutes = [
		{
			"label":"Event Settings",
			"show":menuSettings.event["Event Settings"],
			"access":masterUser,
			"pages":[
				{
					"rt":"evt_dashboard", 
					"page":"/events/views/dashboard.html", 
					"ctrl":"dashboardCtrl",
					"label":"Dashboard",
					"nonTicket":true
				},{
					"rt":"evt_dashboard/:id", 
					"page":"/events/views/dashboard.html", 
					"ctrl":"dashboardCtrl"
				},{
					"rt":"event_details", 
					"page":"/events/views/event_details.html", 
					"ctrl":"eventDetails",
					"label":"Event Details"
				},{
					"rt":"event_details/:id", 
					"page":"/events/views/event_details.html", 
					"ctrl":"eventDetails"
				},{
					"rt":"registration_form", 
					"page":"/events/views/registration_form.html", 
					"ctrl":"registrationForm",
					"label":"Registration Mgmt",
					"conditional":true,
					"tabs":"Registration Mgmt"
				},{
					"rt":"registration_types", 
					"page":"/events/views/registration_types.html", 
					"ctrl":"registrationTypes",
					"tabs":"Registration Mgmt"
				},{
					"rt":"registration_discounts", 
					"page":"/events/views/registration_discounts.html", 
					"ctrl":"registrationDiscounts",
					"tabs":"Registration Mgmt"
				},{
					"rt":"registration_extras", 
					"page":"/events/views/registration_extras.html", 
					"ctrl":"registrationExtras",
					"tabs":"Registration Mgmt"
				},{
					"rt":"registration_messages", 
					"page":"/events/views/registration_messages.html", 
					"ctrl":"registrationMessages",
					"tabs":"Registration Mgmt"
				},{
					"rt":"sponsor_types", 
					"page":"/events/views/sponsor_types.html", 
					"ctrl":"sponsorTypes",
					"enabled":"sponsors_enabled",
					"label":"Vendor Options"
				},{
					"rt":"course_catalog", 
					"page":"/events/views/course_catalog.html", 
					"ctrl":"courseCatalog",
					"tabs":"Schedule Mgmt"
				},{
					"rt":"sessions", 
					"page":"/events/views/sessions.html", 
					"ctrl":"sessions",
					"tabs":"Schedule Mgmt"
				},{
					"rt":"rooms", 
					"page":"/events/views/rooms.html", 
					"ctrl":"rooms",
					"tabs":"Schedule Mgmt"
				},{
					"rt":"event_users", 
					"page":"/events/views/event_users.html", 
					"ctrl":"eventUsers",
					"label": acctType == 'ticketing' ? "Event Staff" : "",
					"tabs":"Schedule Mgmt"
				},{
					"rt":"manage_schedule", 
					"page":"/events/views/manage_schedule.html", 
					"ctrl":"manageSchedule",
					"label":"Schedule Mgmt",
					"conditional":true,
					"tabs":"Schedule Mgmt",
					"nonTicket":true
				},{
					"rt":"master_schedule", 
					"page":"/events/views/master_schedule.html", 
					"ctrl":"masterSchedule",
					"label":"Event Program",
					"nonTicket":true
				},{
					"rt":"import_event_data", 
					"page":"/events/views/import_event_data.html", 
					"ctrl":"importEventData",
					"tabs":"Schedule Mgmt"
				},{
					"rt":"section_attendance", 
					"page":"/events/views/section_attendance.html", 
					"ctrl":"sectionAttendance",
					"tabs":"Schedule Mgmt"
				},{
					"rt":"evt_document_management", 
					"page":"/events/views/document_management.html", 
					"ctrl":"evtDocumentMgmt",
					"label":"Document Mgmt",
					"enabled":"enable_docs"
				},{
					"rt":"evt_reports", 
					"page":"/events/views/reports.html", 
					"label":"Reports"
				},{
					"rt":"survey_questions", 
					"page":"/events/views/survey_questions.html", 
					"ctrl":"surveyQuestions",
					"enabled":"enable_survey",
					"label":"Survey Questions"
				},{
					"rt":"payments", 
					"page":"/events/views/reports/payments.html", 
					"ctrl":"payments"
				},{
					"rt":"order_summary", 
					"page":"/events/views/reports/orderSummary.html", 
					"ctrl":"orderSummary"
				},{
					"rt":"rpt_roster", 
					"page":"/events/views/reports/roster.html", 
					"ctrl":"rptRoster"
				},{
					"rt":"rpt_presenters", 
					"page":"/events/views/reports/presenters.html", 
					"ctrl":"rptPresenters"
				},{
					"rt":"rpt_rooms", 
					"page":"/events/views/reports/rooms.html", 
					"ctrl":"rptRooms"
				},{
					"rt":"evt_expense_rpt",
				 	"page":"/account/views/reports/expenses.html",
				 	"ctrl":"staffExpenseRpt"
				},{
					"rt":"rpt_sessions", 
					"page":"/events/views/reports/sessions.html", 
					"ctrl":"rptSessions"
				},{
					"rt":"rpt_signups", 
					"page":"/events/views/reports/signups.html", 
					"ctrl":"rptSignups"
				},{
					"rt":"rpt_staff", 
					"page":"/events/views/reports/staff.html", 
					"ctrl":"rptStaff"
				},{
					"rt":"evt_staff_pymt_rpt", 
					"page":"/account/views/reports/staff_pymts.html", 
					"ctrl":"staffPaymentsRpt"
				}
			]
		},{
			"label":"Attendee Information",
			"show":menuSettings.event["Attendee Information"],
			"access":masterUser,
			"pages":[
				{
					"rt":"registrations/:eventid", 
					"page":"/events/views/registrations.html", 
					"ctrl":"registrations"
				},{
					"rt":"registrations/:eventid/:confirmation/:function", 
					"page":"/events/views/registrations.html", 
					"ctrl":"registrations"
				},{
					"rt":"registrations", 
					"page":"/events/views/registrations.html", 
					"ctrl":"registrations",
					"label":"Registrations"
				},{
					"rt":"station", 
					"page":"/events/views/station.html", 
					"ctrl":"station",
					"label":"Checkin",
					"nonTicket":true
				},
				{
					"rt":"redeem_extras",
					"page":"/events/views/redeem_extras.html",
					"ctrl":"redeemExtrasCtrl",
					"icon": "ph-ticket",
					"label": acctType == 'ticketing' ? "Redeem Tickets" : "Redeem Extras"
				}
			]
		},{
			"label":"Pages",
			"show":menuSettings.event["Pages"],
			"access":masterUser,
			"pages":[
				{
					"rt":"manage_page/:id", 
					"page":"/events/views/page.html", 
					"ctrl":"page"
				}
			]
		}
	]; //End Event Routes

	let evtPageAccess = masterUser;
	let pageAccess = ('<?=$_SESSION['pageAccess'] ?>' || '').split(',');

	var regApp = angular.module('regApp', ['ngRoute','easyRegDataModule','erSvc','navMod', 'sponsorDataModule']);	
	regApp.config(function($routeProvider) {
		angular.forEach([...accountRoutes,...eventRoutes],function(cat){
			angular.forEach(cat.pages,function(pg){
				if(hasPageAccess(cat, pg)){
					pg.access = true;
					cat.access = true;
					$routeProvider.when('/' + pg.rt, {
						templateUrl: pg.page + '?_=' + Math.random(), 
						controller: pg.ctrl
					});
				}
				if(pg.subMenu){
					pg.subMenu.forEach(function(subPg){
						if(hasPageAccess(cat, subPg)){
							subPg.access = true;
							cat.access = true;
							$routeProvider.when('/' + subPg.rt, {
								templateUrl: subPg.page + '?_=' + Math.random(), 
								controller: subPg.ctrl
							});
						}
					});
					if(pg.subMenu.filter(subPg => subPg.access).length == 0) delete pg.subMenu; 
				}
			});
		});

		function hasPageAccess(cat, pg){
			if(accountid != '1000' && pg.label == 'ACME Requests') return false; 
			if(pg.label == 'Online Payments'  && (erSessionData.erSupport || 'false') != 'true') return false;
			if(masterUser) return true;
			if(pageAccess.includes(pg.rt.split('/')[0])){
				if(cat.label == 'Pages') evtPageAccess = true;
				return true;
			}
			if(pg.conditional){ //if this page is not accessible but another tab is, change rt and make accessible
				let otherFound = false;
				angular.forEach(cat.pages,function(otherPg){
					if(otherFound) return;
					if(otherPg.tabs == pg.tabs && pageAccess.includes(otherPg.rt.split('/')[0])){
						otherFound = true;
						pg.ctrl = otherPg.ctrl;
						pg.page = otherPg.page;
						pg.rt = otherPg.rt;
					}
				});
				return otherFound;
			}
			//determine if acct or event reports link is accessible
			if(pg.page == '/account/views/reports.html' || pg.page == '/events/views/reports.html'){
				let context = pg.page.includes('/account/') ? 'account' : 'events'; 
				let otherFound = false;
				angular.forEach(cat.pages,function(otherPg){
					if(otherFound) return;
					if(otherPg.page.indexOf(`${context}/views/reports/`) > 0 && pageAccess.includes(otherPg.rt.split('/')[0])){
						otherFound = true;
					}
				});
				return otherFound;
			}
			if(pg.page == '/account/views/manageEvents.html') return true;
			return false;
		}// end hasPageAccess()
	});// end regApp.config()

	regApp.controller('regController', function($scope, $http, $rootScope, $location, $route, dataSvc, erSvc, sponsorDataService){

		$scope.evtPageAccess = evtPageAccess;

		dataSvc.getArray({'query':'accountInfo'}).then(function(resp){
			let acct = resp[0];
			$rootScope.enable_course_proposals = acct.enable_course_proposals == '1';
			$rootScope.enable_docs = acct.enable_docs == '1';
			$rootScope.enable_evt_requests = acct.enable_evt_requests == '1';
			$rootScope.enable_staff_expense = acct.enable_staff_expense == '1';
			$rootScope.enable_survey = acct.enable_survey == '1';
			$rootScope.enable_videos = acct.enable_videos == '1';
			$rootScope.enable_inventory = acct.enable_inventory == '1';
			$rootScope.sponsors_enabled = acct.sponsors_enabled == '1';
			$rootScope.enable_season_pass = acct.enable_season_pass == '1' || acct.type == 'ticketing';
			$rootScope.acctType = acct.type;
			$rootScope.ccProvider = acct.ccProvider;
		});

		if(!$rootScope.curEventName && erSessionData.curEvent) erSvc.setSelectedEvent(erSessionData.curEvent.id);
		else $rootScope.curEventName = "";

		let acctRoutes = [];
		angular.forEach(accountRoutes[0].pages, (pg) => acctRoutes.push(pg.rt));
		
		$scope.$on('$locationChangeStart', function(event, next, current) {
			$rootScope.acctPage = acctRoutes.includes(next.split("/").pop());
		});

		$scope.$on('$routeChangeSuccess',function(){
			erSvc.checkAccess();
			$scope.updateSelectedLink();
			window.scrollTo(0, 0);
			setTimeout(function(){
				$scope.$applyAsync();
			}, 1000);
		}); 

		$scope.updateSelectedLink = function(){
			$('.sidebar .active').removeClass('active');
			let rt = $location.$$path.replace('/','');
			let link = $(`a[href="#!${rt}"]`);
			if(link.length){
				link.closest('li, span').addClass('active');
				link.addClass('active');
			}
		};

		$(document).on('click','#adminNavLeft .dropdown a',function(){
			let link = $(this);
			link.closest('.dropdown').addClass('active');
			setTimeout(function(){ link.closest('.dropdown').addClass('active'); }, 500);
		});

		$scope.toggleCat = function(type,cat){
			let menuSetings = { "account":{}, "event":{} };
			cat.show = !cat.show;
			angular.forEach($scope.accountRoutes, rt => menuSetings.account[rt.label] = rt.show );
			angular.forEach($scope.eventRoutes,rt => menuSetings.event[rt.label] = rt.show );
			localStorage.setItem("easyRegAdminMenu",JSON.stringify(menuSetings));
		};

		$scope.master = erSessionData.master == '1';
		$scope.accountRoutes = accountRoutes;
		$scope.eventRoutes = eventRoutes;
		$scope.pgEnabled = function(pg){
			if($rootScope.acctType == 'ticketing' && pg.nonTicket) return false;
			if(pg.enabled) return $rootScope[pg.enabled];
			return true;
		};
		
		$scope.hasPageAccess = (rt) => pageAccess.includes(rt) || masterUser;

		$scope.toggleLeftNav = function(){
			$('#adminNavLeft').toggleClass('minimize');
			if($('#adminNavLeft').hasClass('minimize')){
				$('#toggleLeftNavBtn').text('>');
			}else{
				$('#toggleLeftNavBtn').text('<');
			}
		}

		$rootScope.alerts = 0;
		dataSvc.getArray({'query':'unanswered_user_requests'}).then(requests =>	$rootScope.alerts += requests.length );
		dataSvc.getArray({'query':'courseProposals'}).then(function(proposals){
			angular.forEach(proposals,proposal => {	if(!proposal.status) $rootScope.alerts++; });
		});
		dataSvc.getArray({'query':'event_capacity_check'}).then(function(evts){
			angular.forEach(evts,evt => { if(evt.total >= evt.capacity - 10) $rootScope.alerts++; });
		});
		dataSvc.getArray({'query':'section_capacity_check'}).then(function(sections){
			angular.forEach(sections,sec => { if(sec.signups >= sec.capacity - 5) $rootScope.alerts++; });
		});

		$rootScope.acmeAlerts = 0;
		if(accountid == 1000){
			dataSvc.getArray({'query':'unapproved_acme_requests'}).then(res => $rootScope.acmeAlerts = Number(res[0].count) );
		}
	});//end controller
</script>

<!-- account controllers -->
<script src="/account/controllers/accountDetails.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/acmeMgmt.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/features.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/adminAlerts.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/manageCourses.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/documentMgmt.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/vendorSettings.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/manageEvents.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/expenseCategories.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/expensePymtMethods.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/expenseMgmt.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/registrations.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/registrationFields.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/registrationTypes.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/surveyMgmt.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/qrConfig.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/inventorySettings.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/inventory.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/securityGroups.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/eventCategories.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/seasonPasses.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/attendeeMenu.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/tracks.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/users.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/vendorMgmt.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/vendorDetails.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/videoMgmt.js?_=<?= rand() ?>"></script>
<!-- account report controllers-->
<script src="/account/controllers/reports/courseHistory.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/emailHistory.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/evtUserAttendance.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/evtSponsorAtt.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/expenses.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/staffPymts.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/outstandingBalances.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/payments.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/registrations.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/sponsorOrders.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/surveyResponses.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/seasonPasses.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/users.js?_=<?= rand() ?>"></script>
<script src="/account/controllers/reports/videoOrders.js?_=<?= rand() ?>"></script>
<!-- event controllers -->
<script src="/events/controllers/dashboard.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/eventDetails.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/registrationForm.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/registrationTypes.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/registrationExtras.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/registrationDiscounts.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/registrationMessages.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/sponsorTypes.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/courseCatalog.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/sessions.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/rooms.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/eventUsers.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/manageSchedule.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/masterSchedule.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/importEventData.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/sectionAttendance.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/evtDocumentMgmt.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/surveyQuestions.js?_=<?= rand() ?>"></script>
<!-- event report controllers -->
<script src="/events/controllers/reports/presenters.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/reports/orderSummary.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/reports/roster.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/reports/sessions.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/reports/signups.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/reports/rooms.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/reports/staff.js?_=<?= rand() ?>"></script>

<script src="/events/controllers/registrations.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/payments.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/station.js?_=<?= rand() ?>"></script>
<script src="/events/controllers/redeemExtras.js?_=<?= rand() ?>"></script>

<script src="/events/controllers/page.js?_=<?= rand() ?>"></script>
</head>

<body ng-app="regApp">
	<top-nav ng-controller="navController"></top-nav>
	<div class="container-fluid">
		<admin-header ng-controller="adminHeaderController"></admin-header>
	</div>

	<div class="wrapper" ng-controller="regController as regController">
		<div class="sidebar admin noPrint" id="sidebar">
			<span class="list-group" ng-repeat="cat in accountRoutes">
				<span class="list-group-item listHeader" ng-click="toggleCat('account',cat)"
					ng-show="cat.access">
					{{cat.label}}
					<span style="float:right;margin-right:.5em">
						<i class="bi bi-caret-right-fill" ng-show="!cat.show"></i>
						<i class="bi bi-caret-down-fill" ng-show="cat.show"></i>
					</span>
				</span>
				<span ng-show="cat.show">
					<section ng-repeat="pg in cat.pages">
						<a class="dropdown-toggle list-group-item" href="#"
							ng-if="pg.access && pg.label && pgEnabled(pg) && pg.subMenu"
							data-bs-toggle="dropdown" aria-expanded="false">
							{{pg.label}}
						</a>
						<ul class="dropdown-menu" ng-if="pg.subMenu">
							<li class="dropdown-item" ng-repeat="subPg in pg.subMenu" 
								ng-if="subPg.access && pgEnabled(subPg)">
									<a class="dropdown-item" href="#!/{{subPg.rt}}">
										{{subPg.label}}
									</a>
							</li>
						</ul>
						<a class="list-group-item" href="#!{{pg.rt}}" 
							ng-if="pg.access && pg.label && pgEnabled(pg) && !pg.subMenu">
							{{pg.label}}
							<span ng-show="pg.label=='Alerts' && alerts > 0" class="blink">
								{{alerts}}
							</span>
							<span ng-show="pg.label=='ACME Requests' && acmeAlerts > 0" class="blink">
								{{acmeAlerts}}
							</span>
						</a>
					</section>
				</span>
			</span> <!-- End Account Links -->
			<span class="list-group" ng-repeat="cat in eventRoutes" ng-if="curEventName">
				<span class="list-group-item listHeader" ng-click="toggleCat('event',cat)"
					ng-show="cat.access">
					{{cat.label}}
					<span style="float:right;margin-right:.5em">
						<i class="bi bi-caret-right-fill" ng-show="!cat.show"></i>
						<i class="bi bi-caret-down-fill" ng-show="cat.show"></i>
					</span>
				</span>
				<span ng-show="cat.show">
					<a ng-repeat="pg in cat.pages" class="list-group-item"
						href="#!{{pg.rt}}" ng-if="pg.access && pg.label && pgEnabled(pg)">
						{{pg.label}}
					</a>
					<a ng-repeat="page in navPages" class="pointer list-group-item" 
						href="#!manage_page/{{page.id}}" ng-if="cat.label=='Pages' && evtPageAccess">
						{{page.name}}
					</a>
					<a href="#!manage_page/-1" ng-if="cat.label=='Pages' && evtPageAccess"
						class="list-group-item list-group-item-success pointer">
						New page
					</a>
				</span>
			</span>
		</div> <!-- End sidebar -->
		<div id="main-content">
			<div ng-view style="margin-left:2em"></div>
		</div>
	</div>
<style>
	#main-content{
		flex-grow:1;
		margin-right:1.5em;
		max-width:85%;
	}
	#main-content.full{
		max-width:99%;
	}

	@media print{
		#main-content{
			max-width:none;
			width:100%;
			margin-right:0;
		}
		#main-content > div[ng-view]{
			margin-left:0 !important;
		}
	}
</style>
	<er-Footer />
</body>
</html>
