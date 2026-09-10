regApp.controller('expenseMgmt', function($scope, $http, $rootScope, $location, $q, $filter, $timeout, dataSvc, erSvc){
	let accountid = erSessionData.accountid;
	$scope.receiptFilter = 'all';
	$scope.approvedFilter = 'all';
	$scope.paidFilter = 'all';
	$scope.staffFilter = 'all';

	$scope.events = {};
	dataSvc.getArray({'query':'accountEvents'}).then(function(resp){
		resp.forEach(function(evt){
			if(evt.archived != '1') $scope.events[evt.id] = evt;
		});
	});

	dataSvc.getUsersByAccount(true, accountid).then((r) => $scope.allStaff = r);

	let user_events = [];
	dataSvc.getArray({'query':'account_user_events'}).then((resp) => user_events = resp);

	dataSvc.getTableRecords('expense_categories', 'accountid = ' + accountid, true)
	.then((res) => $scope.expenseCategories = res);

	$scope.paymentMethods = [];
	dataSvc.getPreferenceByName('expensePymtMethods', accountid)
	.then(function(res){
		if(res[0]){
			try{ $scope.paymentMethods = JSON.parse(res[0].value);}
			catch(e){ console.error(e); $scope.paymentMethods = []; }
		} 
	});

	$scope.staff = {};
	$scope.getExpenses = function(){
		var expensesRetrieved = $q.defer();
		var paymentsRetrieved = $q.defer();
		
		let params = {'query':'expenses'};
		if(Number($scope.selectedEvent)) params.eventid = $scope.selectedEvent;
		else params.range = $scope.selectedEvent;
		dataSvc.getObject(params).then(function(resp){
			$scope.expenses = resp;
			angular.forEach($scope.expenses,function(exp){
				exp.expense_date = erSvc.mySqlToLocalDate(exp.expense_date);
				let str = exp.amount + exp.category + exp.description + exp.event_name;
				str += exp.first_name + exp.last_name;
				exp.searchString = str.toLowerCase();

				if(!$scope.staff[exp.userid]) $scope.staff[exp.userid] = {
					"id":exp.userid,
					"last_name":exp.last_name,
					"first_name": exp.first_name
				};
				exp.payments = {};
			});
			expensesRetrieved.resolve();
			$scope.filterExpenses();
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
		});
	};

	$scope.staffEvents = [];
	//find events for which selected staff is scheduled
	$scope.getStaffEvents = function(){
		if(!$scope.selectedExpense) return;
		if(!$scope.selectedExpense.userid) return $scope.events;
		$scope.staffEvents = [];
		user_events.forEach(function(evt){
			if(evt.userid == $scope.selectedExpense.userid) $scope.staffEvents.push(evt.eventid);
		});
	};

	$scope.showEvtForStaff = function(evt){
		if(!$scope.selectedExpense) return false;
		if(!$scope.selectedExpense.userid) return true;
		if($scope.selectedExpense.eventid == evt.id) return true;
		return erUtils.hasId($scope.staffEvents, evt.id);
	};

	$scope.eventStaff = [];
	$scope.getEventStaff = function(){
		if(!$scope.selectedExpense) return;
		if(!$scope.selectedExpense.eventid) return $scope.allStaff;
		$scope.eventStaff = [];
		user_events.forEach(function(userEvt){
			if(userEvt.eventid == $scope.selectedExpense.eventid) $scope.eventStaff.push(userEvt.userid);
		});
	};

	$scope.showStaffForEvt = function(staff){
		if(!$scope.selectedExpense) return false;
		if(!$scope.selectedExpense.eventid) return true;
		if($scope.selectedExpense.userid == staff.id) return true;
		return erUtils.hasId($scope.eventStaff, staff.id);
	};

	function calculatePdAndDue(expense){
		expense.totalPaid = 0;
		angular.forEach(expense.payments,(pymt) => expense.totalPaid += Number(pymt.amount));
		expense.due = Number(expense.approved_amt) - expense.totalPaid;
		updateTotals();
	}
	
	$scope.selectedEvent = 'upcoming';
	$scope.getExpenses();
	$scope.filteredExpenses = [];
	$scope.filterExpenses = function(){
		$scope.filteredExpenses = [];
		angular.forEach($scope.expenses,function(expense){
			if($scope.meetsFilter(expense)) $scope.filteredExpenses.push(expense);
		});
		updateTotals();
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
		if($scope.receiptFilter != 'all'){
			if($scope.receiptFilter == 'has' && !exp.receipt) return false;
			if($scope.receiptFilter == 'none' && exp.receipt) return false;
		}
		if($scope.approvedFilter != 'all'){
			exp.approved_amt = Number(exp.approved_amt);
			exp.amt = Number(exp.amt);
			if($scope.approvedFilter == 'part' && (exp.approved_amt == 0 || exp.approved_amt == exp.amount)) return false;
			if($scope.approvedFilter == 'full' && exp.approved_amt != exp.amount) return false;
			if($scope.approvedFilter == 'unapproved' && exp.approved_amt != 0) return false;
		}
		if($scope.paidFilter != 'all'){
			if($scope.paidFilter == 'paid' && (exp.totalPaid != exp.approved_amt || exp.approved_amt == 0)) return false;
			if($scope.paidFilter == 'unpaid' && exp.totalPaid == exp.approved_amt && exp.approved_amt != 0) return false;
		}
		if($scope.staffFilter != 'all'){
			if($scope.staffFilter != exp.userid) return false;
		}
		if($scope.quickSearch){
			if(exp.searchString.indexOf($scope.quickSearch.toLowerCase()) < 0) return false;
		}
		return true;
	};

	$scope.editExpense = function(exp){
		$scope.selectedExpense = angular.copy(exp);	
		$scope.getStaffEvents();	
		$('#expenseDialog').show(500);
	};

	$scope.newExpense = function(){
		$scope.selectedExpense = {};
		$('form[name="expenseForm"]').removeClass('submitted');
		$('#expenseDialog').show(500);
	};

	let receiptChange = false;
	$('#receiptInput').change(() => receiptChange = true);
	$scope.saveExpense = function(){
		$('form[name="expenseForm"]').addClass('submitted');
		if(!$scope.expenseForm.$valid) return;
		let exp = $scope.selectedExpense;
		let staff = $scope.allStaff[exp.userid];
		if(staff){
			exp.last_name = staff.last_name;
			exp.first_name = staff.first_name;
		}

		let prefix;
		if($scope.events[exp.eventid]){
			exp.event_name = $scope.events[exp.eventid].name;
			prefix = $scope.events[exp.eventid].prefix;
		}

		exp.category = $scope.expenseCategories[exp.expense_category_id].name;

		if(receiptChange){
			let dt = new Date();
			if(!$('#receiptInput')[0].files[0]){
				exp.receipt = '';
			}
			else{
				let fileExt = $('#receiptInput')[0].files[0].name;
				fileExt = fileExt.substring((fileExt.lastIndexOf('.')+ 1));
				//get out if invalid file type
				if(!['png','jpg','jpeg','gif','pdf','svg'].includes(fileExt.toLowerCase())){
					let text = "Invalid file format. Must be one of: <ul>";
					text += "<li>PNG</li><li>JPG</li><li>JPEG</li><li>GIF</li>";
					text += "<li>SVG</li><li>PDF</li>";
					text += "</ul>";
					erSvc.easyRegAlert({"text":text,"title":"Unable to Upload Receipt"});
					erSvc.closeLoading();
					return;
				}
				let filename = `${dt.getFullYear()}-${prefix}-${staff.last_name}${staff.id}-`;
				filename += $scope.expenseCategories[exp.expense_category_id].name;
				filename += '-' + dt.getMonth() + 1;
				filename += `${dt.getDate()}${dt.getHours()}${dt.getMinutes()}${dt.getSeconds()}`;
				filename += '.' + fileExt;
				$scope.uploadReceipt(filename, exp.eventid);
				exp.receipt = filename;
			}
		}

		if(!exp.approved_amt || exp.approved_amt == 'null') exp.approved_amt = 0;
		exp.due = exp.approved_amt - exp.totalPaid;
		updateExpenses([exp]);
		$scope.filterExpenses();
		$scope.closeRightDialog();
	};// end saveExpense()

	$scope.editPayment = function(pymt){
		$('form').removeClass('submitted');
		pymt.date_value = erSvc.mySqlToLocalDate(pymt.date_value);
		$scope.selectedPayment = angular.copy(pymt);
		$scope.selectedPayment.amount = Number($scope.selectedPayment.amount).toFixed(2);
		$scope.selectedExpense = $scope.expenses[pymt.staff_expense_id];
		$('#paymentDialog').show(500);
	};

	let lastPaymentMethod = "Check";
	$scope.addPayment = function(exp){
		$('form').removeClass('submitted');
		$scope.selectedPayment = {"staff_expense_id":exp.id};
		$scope.selectedPayment.amount = exp.due.toFixed(2);
		$scope.selectedPayment.date_value = erSvc.today();
		$scope.selectedPayment.method = lastPaymentMethod;
		$scope.selectedExpense = exp;
		$('#paymentDialog').show(500);
	};

	$scope.pymtAmtValid = function(pymt){
		if(!pymt) return true;
		let amt = Number(pymt.amount);
		if(!pymt.amount) return false;
		let due = $scope.selectedExpense.due;
		if(pymt.id && $scope.selectedExpense.payments[pymt.id]) 
			due += Number($scope.selectedExpense.payments[pymt.id].amount);
		return (amt <= due && amt > 0); 
	};

	$scope.getMaxPymtAmount = function(pymt){
		if(!pymt) return;
		let amt = Number(pymt.amount);
		let due = $scope.selectedExpense.due;
		if(pymt.id && $scope.selectedExpense.payments[pymt.id]) 
			due += Number($scope.selectedExpense.payments[pymt.id].amount);
		return due;
	};

	$scope.needsApproval = (exp) => Number(exp.amount) > Number(exp.approved_amt);

	$scope.approveExpense = function(exp){
		exp.approved_amt = exp.amount;
		updateExpenses([exp]);
	};

	$scope.invalidMassPayAmt = (exp) => Number(exp.pay_amt) > exp.due || Number(exp.pay_amt) <= 0;

	$scope.savePayment = function(){
		$('form[name="paymentForm"]').addClass('submitted');
		if(!$scope.paymentForm.$valid) return;
		lastPaymentMethod = $scope.selectedPayment.method;
		dataSvc.createOrUpdateRecord({
			"table":"staff_payments",
			"record":$scope.selectedPayment
		}).then(function(res){
			if(res != '0') $scope.selectedPayment.id = res;
			let expense = $scope.expenses[$scope.selectedPayment.staff_expense_id];
			expense.payments[$scope.selectedPayment.id] = angular.copy($scope.selectedPayment);
			calculatePdAndDue(expense);
			$scope.closeRightDialog();
			$scope.$applyAsync();
		});
	};

	$scope.confirmPaymentDelete = function(){
		let txt = "Delete this payment?";
		erSvc.easyRegConfirm({"text":txt,"title":"Delete Payment"},"Confirm","Cancel")
		.then(function(res){ if(res) deletePayment(); });
	};

	function deletePayment(){
		$scope.closeRightDialog();
		dataSvc.deleteRecord({"table":"staff_payments","id":$scope.selectedPayment.id});
		delete $scope.selectedExpense.payments[$scope.selectedPayment.id];
		calculatePdAndDue($scope.selectedExpense);
	}

	$scope.uploadReceipt = function(filename, eventid){
		let folder = `documents/account${accountid}/staff_receipts/evt-${eventid}`;
		erSvc.uploadDocument($('#receiptInput'), folder, filename).then(function(res){
			$('#receiptInput').val('');
		});
	};

	$scope.confirmExpenseDelete = function(){
		let txt = "Delete this expense?";
		erSvc.easyRegConfirm({"text":txt,"title":"Delete Expense"},"Confirm","Cancel")
		.then(function(res){ if(res) deleteExpense(); });
	};

	function deleteExpense(){
		$scope.closeRightDialog();
		dataSvc.deleteRecord({"table":"staff_expenses","id":$scope.selectedExpense.id});
		delete $scope.expenses[$scope.selectedExpense.id];
		$scope.filterExpenses();
	};

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);

	$scope.massApprove = function(){
		$scope.approveExpenses = [];
		angular.forEach($scope.filteredExpenses,function(exp){
			if(Number(exp.approved_amt) < Number(exp.amount)){
				exp.temp_approved = parseFloat(exp.amount).toFixed(2);
				$scope.approveExpenses.push(exp);
			} 
		});
		$('#massApproveDialog').show(500);
	};

	$scope.saveApprovals = function(){
		erSvc.loadingDialog();
		let complete = 0;
		$scope.approveExpenses.forEach(function(exp){
			exp.approved_amt = exp.temp_approved;
			dataSvc.createOrUpdateRecord({"table":"staff_expenses","record":exp}).then(function(){
				if(++complete == $scope.approveExpenses.length){
					erSvc.closeLoading();
					$scope.closeRightDialog();
					$scope.$applyAsync();
				} 
			});
		});
	};

	$scope.overApproved = function(exp){
		let cat = $scope.expenseCategories[exp.expense_category_id];
		if(!cat || !parseFloat(cat.max)) return false;
		if(exp.temp_approved) return parseFloat(exp.temp_approved) > parseFloat(cat.max);
		return parseFloat(exp.amount) > parseFloat(cat.max);
	}

	$scope.massPay = function(){
		$scope.paymentExpenses = $scope.filteredExpenses.filter(function(exp){
			if(!exp.due) return false;
			exp.pay_amt = exp.due.toFixed(2);
			exp.pay_date = erSvc.today();
			exp.method = lastPaymentMethod;
			return true;
		});
		$('#massPayDialog').show(500);
	};

	$scope.setAllPayDt = () => $scope.paymentExpenses.forEach((e) => e.pay_date = $scope.massPayDate);
	$scope.setAllMethod = () => $scope.paymentExpenses.forEach((e) => e.method = $scope.massMethod);
	$scope.setAllNotes = () => $scope.paymentExpenses.forEach((e) => e.notes = $scope.massPayNotes);

	$scope.submitPayments = function(){
		if($('#massPayDialog .alert-danger:visible').length){
			erSvc.easyRegAlert({"text":"Please Correct Payment Amounts","title":"Error"});
			return;
		}
		erSvc.loadingDialog();
		$scope.paymentsProcessing = true;
		let complete = 0;
		let updates = 0;
		$scope.paymentExpenses.forEach(function(exp){
			if(exp.pay_amt == 'null' || exp.apy_amt == '0') return;
			updates++;
			let pymt = {
				"staff_expense_id":exp.id,
				"date_value":exp.pay_date,
				"method":exp.method,
				"amount":exp.pay_amt
			};
			dataSvc.createOrUpdateRecord({"table":"staff_payments","record":pymt}).then(function(res){
				if(res != '0') pymt.id = res;
				exp.payments[pymt.id] = angular.copy(pymt);
				calculatePdAndDue(exp);
				if(++complete == updates){
					$scope.closeRightDialog();
					erSvc.closeLoading();
					$timeout(() => $scope.paymentsProcessing = false, 750);
					$scope.$applyAsync();
				} 
			});
		});
	};

	function updateExpenses(expenses){
		erSvc.loadingDialog('Updating');
		recordsUpdated = 0;
		expenses.forEach(function(expense){
			dataSvc.createOrUpdateRecord({"table":"staff_expenses","record":expense}).then(function(res){
				if(res != '0') expense.id = res;
				$scope.expenses[expense.id] = expense;
				calculatePdAndDue($scope.expenses[expense.id]);
				if(++recordsUpdated == expenses.length){
					erSvc.closeLoading();
					$scope.filterExpenses();
				} 
				$scope.$applyAsync();
			});
		});
	}

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

	$scope.exportResults = function(){
		let data = [];
		data.push(["Event","Staff","Expense Date","Category","Description","Has Receipt","Amount","Approved","Paid","Due"]);
		let newRow = [];
		let clean = (str) => str ? str.replace(/#/g,'').replace(/"/g,"'") : '';
		$scope.filteredExpenses.forEach(function(exp){
			newRow = [];
			newRow.push(`"${clean(exp.event_name)}"`);
			newRow.push(`"${clean(exp.last_name)}, ${clean(exp.first_name)}"`);
			newRow.push(`"${$filter('mySqlToLocalDate')(exp.expense_date)}"`);
			newRow.push(`"${clean(exp.category)}"`);
			newRow.push(`"${clean(exp.description)}"`);
			newRow.push(`"${exp.receipt ? 'yes' : 'no'}"`);
			newRow.push(`"${clean(exp.amount)}"`);
			newRow.push(`"${exp.approved_amt}"`);
			newRow.push(`"${exp.totalPaid}"`);
			newRow.push(`"${exp.due}"`);
			data.push(newRow.join(','));
		});
		arrayToCsv(data, 'Expenses');
	};

	$scope.downloadReceipts = function(){
		let receipts = [];
		angular.forEach($scope.filteredExpenses,function(exp){
			if(!exp.receipt) return;
			let folder = `documents/account${accountid}/staff_receipts/evt-${exp.eventid}`;
			receipts.push(folder + '/' + exp.receipt);
		});
		erUtils.downloadFiles(receipts, 'receipts.zip');
	};
});
