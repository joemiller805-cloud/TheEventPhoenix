regApp.controller('securityGroups', function($scope, $rootScope, $http, dataSvc, erSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;
	$scope.adminUser = false;

	dataSvc.getTableRecords('security_groups', 'accountid = ' + $scope.accountid, true).then(function(res){
		$scope.groups = res;
		angular.forEach($scope.groups,function(grp){
			grp.account_pages = grp.account_pages.split(',');
			grp.event_pages = grp.event_pages.split(',');
			grp.users = [];
			grp.searchString = '';
		});
		dataSvc.getUsersByAccount(false, $scope.accountid).then(function(res){
			res.forEach(function(user){
				(user.security_groups || '').split(',').forEach(function(grp){
					if($scope.groups[grp]) $scope.groups[grp].users.push(user.last_name + ', ' + user.first_name);
				});
			});
			getSearchStrings();
		});
	});

	function getSearchStrings(){
		angular.forEach($scope.groups,function(grp){
			grp.searchString += (grp.name.toString() || '').toLowerCase(); 
			grp.searchString += (grp.users.toString() || '').toLowerCase(); 
			grp.account_pages.forEach((pg) => grp.searchString += $scope.getPgName(pg).toLowerCase());
			grp.event_pages.forEach((pg) => grp.searchString += $scope.getPgName(pg).toLowerCase());
		});
	}

	$scope.quickSearch = '';
	$scope.showGroup = function(group){
		if($scope.quickSearch == '') return true;
		return group.searchString.indexOf($scope.quickSearch.toLowerCase()) >= 0;
	}

	$scope.getPgName = function (pg){
		let name = '';
		angular.forEach($scope.pages,function(cat){
			angular.forEach(cat.pages,function(page){
				if(page.route == pg) name = page.label; 
			});
		});
		return name;
	};

	$scope.getPgType = function(pg){
		let type = '';
		angular.forEach($scope.pages,function(cat){
			angular.forEach(cat.pages,function(page){
				if(page.route == pg) type = cat.category; 
			});
		});
		return type;
	};

	$scope.showPg = function(pg){
		if($scope.acctType == 'ticketing' && !pg.ticketing) return false;
		if(!pg.if) return true;
		return $rootScope[pg.if];
	}

	$scope.pages = [{	
			"category":"Account Pages",
			"pages":[
				{
					"route":"admin_alerts",
					"label":"Alerts",
					"ticketing":true
				},
				{
					"route":"expense_management",
					"label":"Expense Mgmt -Staff Expenses",
					"if":'enable_staff_expense'
				},
				{
					"route":"expense_pymt_methods",
					"label":"Expense Mgmt - Payment Methods",
					"if":'enable_staff_expense'
				},
				{
					"route":"expense_categories",
					"label":"Expense Mgmt - Expense Categories",
					"if":'enable_staff_expense'
				},
				{
					"route":"acct_reg_types",
					"label":"Account Config - Registration Types"
				},
				{
					"route":"security_groups",
					"label":"Account Config - Security Groups",
					"ticketing":true
				},
				{
					"route":"survey_management",
					"label":"Account Config - Survey Questions",
					"if":"enable_survey"
				},
				{
					"route":"account_registrations",
					"label":"All Registrations",
					"ticketing":true
				},
				{
					"route":"course_management",
					"label":"Courses"
				},
				{
					"route":"document_management",
					"label":"Document Management",
					"if":"enable_docs"
				},
				{
					"route":"event_management",
					"label":"Event Management",
					"ticketing":true
				},
				{
					"route":"users",
					"label":"Staff",
					"ticketing":true
				},
				{
					"route":"tracks",
					"label":"Tracks"
				},
				{
					"route":"vendor_management",
					"label":"Vendor Management",
					"if":"sponsors_enabled",
					"ticketing":true
				},
				{
					"route":"vendor_details",
					"label":"Vendor Mgmt - Vendor Details",
					"if":"sponsors_enabled",
					"ticketing":true
				},
				{
					"route":"video_mgmt",
					"label":"Video Management",
					"if":"enable_videos"
				}
			]
		},{
			"category":"Account Reports",
			"pages":[
				{
					"route":"rpt_course_history",
					"label":"Course History"
				},
				{
					"route":"email_history",
					"label":"Email History",
					"ticketing":true
				},
				{
					"route":"rpt_event_sponsor_att",
					"label":"Evt Vendor Attendance",
					"if":"sponsors_enabled"
				},
				{
					"route":"rpt_event_user_att",
					"label":"Evt Staff Attendance",
					"ticketing":true
				},
				{
					"route":"acct_expense_rpt",
					"label":"Staff Expenses",
					"if":'enable_staff_expense'
				},
				{
					"route":"acct_expense_pymts",
					"label":"Staff Expense Payments",
					"if":'enable_staff_expense'
				},
				{
					"route":"rpt_outstanding_balances",
					"label":"Outstanding Balances",
					"ticketing":true
				},
				{
					"route":"rpt_registrations",
					"label":"Registrations",
					"ticketing":true
				},
				{
					"route":"rpt_report_users",
					"label":"Staff",
					"ticketing":true
				},
				{
					"route":"rpt_survey_responses",
					"label":"Survey Responses",
					"if":"enable_survey"
				},
				{
					"route":"rpt_report_sponsor_orders",
					"label":"Vendor Orders",
					"if":"sponsors_enabled"
				},
				{
					"route":"rpt_video_orders",
					"label":"Video Orders",
					"if":"enable_videos"
				}
			]
		},{
			"category":"Event Pages",
			"pages":[
				{
					"route":"event_details",
					"label":"Event Details",
					"ticketing":true
				},
				//Registration Management
				{
					"route":"registration_form",
					"label":"Reg Mgmt - Registration Form",
					"ticketing":true
				},
				{
					"route":"registration_types",
					"label":"Reg Mgmt - Registration Types"
				},
				{
					"route":"registration_extras",
					"label":"Reg Mgmt - Registration Extras",
					"ticketing":true
				},
				{
					"route":"registration_messages",
					"label":"Reg Mgmt - Registration Messages",
					"ticketing":true
				},
				{
					"route":"sponsor_types",
					"label":"Vendor Options",
					"if":"sponsors_enabled"
				},
				//Schedule Management
				{
					"route":"manage_schedule",
					"label":"Sched Mgmt - Event Schedule"
				},
				{
					"route":"sessions",
					"label":"Sched Mgmt - Sessions"
				},
				{
					"route":"rooms",
					"label":"Sched Mgmt - Rooms"
				},
				{
					"route":"event_users",
					"label":"Sched Mgmt - Event Staff"
				},
				{
					"route":"course_catalog",
					"label":"Sched Mgmt - Event Courses"
				},
				{
					"route":"import_event_data",
					"label":"Sched Mgmt - Import Event Data"
				},
				{
					"route":"master_schedule",
					"label":"Event Program"
				},
				{
					"route":"evt_document_management",
					"label":"Document Management",
					"if":"enable_docs"
				},
				{
					"route":"survey_questions",
					"label":"Survey Questions",
					"if":"enable_survey"
				},
				{
					"route":"registrations",
					"label":"Registrations",
					"ticketing":true
				},
				{
					"route":"redeem_extras",
					"label":"Redeem Extras",
					"ticketing":true
				},
				{
					"route":"station",
					"label":"Checkin"
				},
				{
					"route":"manage_page",
					"label":"Manage Pages",
					"ticketing":true
				}
			]
		},{
			"category":"Event Reports",
			"pages":[
				{
					"route":"evt_expense_rpt",
					"label":"Staff Expenses",
					"if":'enable_staff_expense'
				},
				{
					"route":"evt_staff_pymt_rpt",
					"label":"Staff Expense Payments",
					"if":'enable_staff_expense'
				},
				{
					"route":"payments",
					"label":"Payments",
					"ticketing":true
				},
				{
					"route":"rpt_presenters",
					"label":"Presenters"
				},
				{
					"route":"rpt_rooms",
					"label":"Rooms"
				},
				{
					"route":"rpt_sessions",
					"label":"Sessions"
				},
				{
					"route":"rpt_signups",
					"label":"Signups"
				},
				{
					"route":"rpt_staff",
					"label":"Staff"
				},
				{
					"route":"order_summary",
					"label":"Order Summary",
					"ticketing":true
				}
			]
		}		
	];

	$scope.newGroup = function(){
		$scope.originalGroup = null;
		$scope.dialogTitle = "New Security Group";
		$scope.selectedGroup = {
			"name":"",
			"accountid":$scope.accountid,
			"account_pages":[],
			"event_pages":[]
		};
		$('#editDialog').show(500);
	};

	$scope.editGroup = function(group){
		$scope.originalGroup = $scope.groups[group.id];
		$scope.dialogTitle = "Edit Security Group - " + group.name;
		$scope.selectedGroup = angular.copy(group);
		$('#editDialog').show(500);
	};

	$scope.saveGroup = function(){
		if(!$scope.selectedGroup.name){
			erSvc.easyRegAlert({"text":"Please enter a name for the group.","title":"Error"});
			return;
		}
		let acctPgs = $scope.selectedGroup.account_pages;
		if(acctPgs.includes('')) acctPgs.splice(acctPgs.indexOf(''),1);
		
		dataSvc.createOrUpdateRecord({"table":"security_groups","record":$scope.selectedGroup}).then(function(res){
			if(res != '0'){
				$scope.selectedGroup.id = res;
				$scope.groups[res] = $scope.selectedGroup;
			}else{
				$scope.groups[$scope.originalGroup.id] = $scope.selectedGroup;
			}
			$scope.closeRightDialog();
			$scope.$applyAsync();
		});
	};

	$scope.hasAccess = function(route){
		if(!$scope.selectedGroup) return;
		let grp = $scope.selectedGroup;
		return grp.account_pages.indexOf(route) >= 0 || grp.event_pages.indexOf(route) >= 0;
	};

	$scope.pageChange = function(evt, cat, pg){
		let active = $(evt.currentTarget).is(':checked');
		let currentList = $scope.selectedGroup.account_pages;
		if(cat.category.indexOf('Event') >= 0) currentList = $scope.selectedGroup.event_pages;
		if(active) currentList.push(pg.route);
		else currentList.splice(currentList.indexOf(pg.route),1);
	};

	$scope.deleteConfirm = function(){
		let txt = 'Delete Security Group - ' + $scope.selectedGroup.name + '?';
		erSvc.easyRegConfirm({"text":txt,"title":"Confirm Delete"}).then(function(res){
			if(res){
				dataSvc.deleteRecord({"table":"security_groups","id":$scope.selectedGroup.id}).then(function(){
					delete $scope.groups[$scope.selectedGroup.id];
					$scope.closeRightDialog();
					$scope.$applyAsync();
				});
			}
		});
	};

	$scope.cancelUpdate = function(){
		$scope.closeRightDialog();
	}

	$scope.closeRightDialog = () => $('.dialogRight').hide(500) ;
});
