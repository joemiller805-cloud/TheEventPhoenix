regApp.controller('staffExpenseRpt', function($scope, $http, $rootScope, $location, $q, $filter, dataSvc, erSvc){
	let accountid = erSessionData.accountid;
	if($location.$$path.indexOf('evt_') >= 0){
		$scope.selectedEvent = erSessionData.curEvent.id;
	}else{
		$scope.selectedEvent = 'upcoming';
		$scope.acct = true;
	}

	$scope.paidFilter = $scope.staffFilter = 'all';

	$scope.events = {};
	dataSvc.getArray({'query':'accountEvents'}).then(function(resp){
		resp.forEach((evt) => { if(evt.archived != '1') $scope.events[evt.id] = evt; });
	});

	dataSvc.getUsersByAccount(true, accountid).then((r) => $scope.allStaff = r);

	$scope.staff = {};
	$scope.getExpenses = function(){
		erSvc.loadingDialog();
		$scope.staff = {};
		var expensesRetrieved = $q.defer();
		var paymentsRetrieved = $q.defer();
		
		let params = {'query':'expenses'};
		if(Number($scope.selectedEvent)) params.eventid = $scope.selectedEvent;
		else params.range = $scope.selectedEvent;
		dataSvc.getObject(params).then(function(resp){
			$scope.expenses = resp;
			angular.forEach($scope.expenses,function(exp){
				exp.expense_date = erSvc.mySqlToLocalDate(exp.expense_date);
				if(!$scope.staff[exp.userid] && (exp.eventid = $scope.selectedEvent || $scope.acct)){
					$scope.staff[exp.userid] = {
						"id":exp.userid,
						"last_name":exp.last_name,
						"first_name": exp.first_name,
						"expenses":[]
					};
				} 
				$scope.staff[exp.userid].expenses.push(exp);
				exp.payments = {};
			});
			expensesRetrieved.resolve();
			$scope.filterExpenses(true);
		});

		let paymtParams = angular.copy(params);
		paymtParams.query = 'staff_payments';
		let paymentRecords;
		dataSvc.getObject(paymtParams).then(function(resp){
			paymentRecords = resp;
			paymentsRetrieved.resolve();
		});

		$q.all([expensesRetrieved.promise,paymentsRetrieved.promise]).then(function(){
			angular.forEach(paymentRecords,function(pymt){
				let exp = $scope.expenses[pymt.staff_expense_id];
				if(exp) exp.payments[pymt.id] = pymt;
			});
			angular.forEach($scope.expenses,(exp) => calculatePdAndDue(exp));
			calcStaffTotals();
			erSvc.closeLoading();
		});
	};

	function calcStaffTotals(){
		angular.forEach($scope.staff,function(staff){
			staff.ttlExpenses = 0;
			staff.approved = 0;
			staff.paid = 0;
			staff.due = 0;
			staff.expenses.forEach(function(exp){
				if($scope.meetsFilter(exp)){
					staff.ttlExpenses += Number(exp.amount);
					staff.approved += Number(exp.approved_amt);
					staff.paid += Number(exp.totalPaid);
					staff.due += Number(exp.due);
				}
			});
		});
	};
	
	function calculatePdAndDue(expense){
		expense.totalPaid = 0;
		angular.forEach(expense.payments,(pymt) => expense.totalPaid += Number(pymt.amount));
		expense.due = Number(expense.approved_amt) - expense.totalPaid;
		updateTotals();
	}
	
	$scope.getExpenses();
	$scope.filteredExpenses = [];
	$scope.filterExpenses = function(useAllStaff){
		if(useAllStaff) $scope.staffFilter = 'all';
		$scope.filteredExpenses = [];
		angular.forEach($scope.expenses,function(expense){
			if($scope.meetsFilter(expense)) $scope.filteredExpenses.push(expense);
		});
		updateTotals();
		calcStaffTotals();
	};

	function updateTotals(){
		$scope.totalAmt = 0;
		$scope.totalApproved = 0;
		$scope.totalPaid = 0;
		$scope.totalDue = 0;
		angular.forEach($scope.filteredExpenses,function(expense){
			$scope.totalAmt += Number(expense.amount);
			$scope.totalApproved += Number(expense.approved_amt);
			$scope.totalPaid += expense.totalPaid;
			$scope.totalDue += expense.due;
		});
	}

	$scope.meetsFilter = function(exp){
		if($scope.paidFilter == 'paid' && exp.due > 0) return false;
		if($scope.paidFilter == 'unpaid' && exp.due == 0) return false;
		if($scope.staffFilter != 'all' && $scope.staffFilter != exp.userid) return false;
		return true;
	};
	
	$scope.closeRightDialog = () => $('.dialogRight').hide(500);

	$scope.showReceipt = function(expense){
		let folder = `documents/account${accountid}/staff_receipts/evt-${expense.eventid}`;
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

	$scope.hideReceipt = () => $('#receiptDialog').hide('300');
});
