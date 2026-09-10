regApp.controller('redeemExtrasCtrl', function($scope, $http, $q, dataSvc, erSvc) {
	let eventid = erSessionData.curEvent.id;
	let regsRetrieved = $q.defer();
	let extrasRetrieved = $q.defer();

	$scope.registrations = {};
	dataSvc.getRegistrations(eventid).then(function(response){
		angular.forEach(response,reg => $scope.registrations[reg.id] = reg );
		regsRetrieved.resolve();
	});

	let regExtras = {};
	function getExtras(aggregate){
		regExtras = {};
		dataSvc.getArray({'query':'regExtraOrdersByEvent','eventid':eventid}).then(function(resp){
			resp.forEach(xtra => regExtras[xtra.orderId] = xtra );
			extrasRetrieved.resolve();
			if(aggregate) aggregateExtras();
		});
	}
	getExtras();

	$scope.extrasSummary = {};
	$scope.extrasList = []
	$q.all([regsRetrieved.promise,extrasRetrieved.promise]).then(aggregateExtras);
	$q.all([regsRetrieved.promise,extrasRetrieved.promise]).then(checkOfflineTransactions);

	function aggregateExtras(){
		$scope.extrasList = [];
		angular.forEach(regExtras, function(xtra){
			xtra.quantity = Number(xtra.quantity);
			let reg = $scope.registrations[xtra.registrationid];
			if(!reg) return;
			if(!$scope.extrasSummary[xtra.label]) $scope.extrasSummary[xtra.label] = {"ordered":0,"redeemed":0};
			let xtraSummaryItem = $scope.extrasSummary[xtra.label];
			xtra.confirmation = reg.confirmation; 
			xtra.first_name = reg.first_name;
			xtra.last_name = reg.last_name;
			xtra.email = reg.email;
			xtra.registration_type = reg.registration_type;
			xtra.price = reg.price; 
			xtra.payments = reg.payments; 
			xtra.balance = reg.balance;
			xtra.items = [];
			if(xtra.redeemed) xtra.redeemed = (xtra.redeemed || "").split(',').map(x => Number(x));
			else xtra.redeemed = [];
			xtraSummaryItem.ordered += xtra.quantity;
			for(let i = 1; i <= xtra.quantity; i++) { 
				let newExtra = {...xtra};
				newExtra.num = i;
				newExtra.redeemed = xtra.redeemed.includes(i); 
				if(newExtra.redeemed) xtraSummaryItem.redeemed++;
				newExtra.searchString = erSvc.getObjectSearchString(newExtra);
				newExtra.barcode = `${eventid}-${xtra.registrationid}-${xtra.orderId}-${i}`;
				$scope.extrasList.push(newExtra);
			}
		});
		$scope.filterExtras();
	}

	function checkOfflineTransactions(){
		let storedExtras = [];
		try{
			storedExtras = JSON.parse(localStorage.getItem("erpExtras") || '[]');
		}catch(e){}
		if(!storedExtras.length) return;
		erSvc.loadingDialog("Redeeming Extras");
		xtrasUpdated = 0;
		redeemNext();
		function redeemNext(){
			let xtra = storedExtras.shift();
			if(xtra){
				$scope.redeemExtraItem(xtra, true).then(redeemNext);
			}else{
				erSvc.closeLoading();
				getExtras(true);
				erSvc.easyRegAlert({"text":"Extras redeemed while offline have been updated","title":"Offline Transactions Updated"});
				localStorage.setItem("erpExtras","[]");
			}
		}
	}

	let successNotification = new Audio('/img/confirm.wav');
	let errorNotification = new Audio('/img/error.wav');

	$scope.search = '';
	let lastBarcode = '';
	$scope.filterExtras = function(keepErr){
		if(!window.navigator.onLine){
			erSvc.easyRegAlert({"text":"Internet Connection Lost","title":"Offline"});
			return;
		}
		$scope.successMsg = '';
		$scope.cancellationMsg = '';
		let val = $scope.search.toLowerCase();
		let barcode = $scope.search.split('-').length == 4 && $scope.search.split('-')[3].length;
		$scope.filteredExtras = $scope.extrasList.filter(function(xtra){
			if(!$scope.search) return true;
			if(barcode)	return xtra.barcode == val;
			else return xtra.searchString.includes(val);
		});
		if(!$scope.search) return;
		if(!keepErr) $scope.errorMsg = '';
		if(barcode){
			let matchedXtra = $scope.filteredExtras[0];
			if(!matchedXtra){
				if($scope.search != lastBarcode){
					lastBarcode = $scope.search;
					getExtras(true);
				}else{
					lastBarcode = '';
					$scope.errorMsg = `No matches found for ticket ${$scope.search}`;
					$scope.search = '';
					$scope.filterExtras(true);
				}
			}else{
				lastBarcode = '';
				if(matchedXtra.redeemed){
					$scope.errorMsg = `Ticket # ${matchedXtra.barcode} has already been redeemed`;
					errorNotification.play();
				}else{
					matchedXtra.redeemed = true;
					$scope.redeemExtraItem(matchedXtra);
				}
				$scope.search = '';
				$scope.filterExtras();
			} 
		} 
	};

	$scope.errorMsg = '';
	$scope.redeemExtraItem = function(xtra, noAlert){
		$scope.successMsg = '';
		$scope.cancellationMsg = '';
		$scope.errorMsg = '';
		let response = $q.defer();
		let regExtra = regExtras[xtra.orderId];
		if(!regExtra) return; 
		dataSvc.getArray({'query':'registrationExtrasRedeemed','orderid':xtra.orderId}).then(function(res){
			if(res == 'error'){
				handleRedeemErr(xtra);
			}else if(offlineAlerted){
				checkOfflineTransactions();
				offlineAlerted = false;
			}
			let redeemed = res[0].redeemed || '';
			redeemed = redeemed.split(',');
			if(xtra.redeemed && redeemed.includes(xtra.num.toString())){
				if(!noAlert) $scope.errorMsg = `${xtra.label} #${xtra.num} has already been redeemed`;
				response.resolve();
				return;
			}
			if(xtra.redeemed){
				if(!noAlert) $scope.successMsg = `${xtra.label} #${xtra.num} redeemed.`;
				if(!regExtra.redeemed.includes(xtra.num)) regExtra.redeemed.push(xtra.num);
				if(!noAlert) successNotification.play();
			}else{
				regExtra.redeemed.splice(regExtra.redeemed.indexOf(xtra.num),1);
				if(!noAlert) $scope.cancellationMsg = `${xtra.label} #${xtra.num} redemption cancelled.`;
			} 
			$scope.extrasSummary[xtra.label].redeemed += xtra.redeemed ? 1 : -1;
			let rec = {"id":xtra.orderId,"redeemed":regExtra.redeemed.toString()};
			dataSvc.createOrUpdateRecord({"table":"registration_extra_orders","record":rec}).then(function(){
				response.resolve();
			});
		});
		return response.promise;
	};

	let offlineAlerted = false;
	function handleRedeemErr(xtra){
		let rec = {
			orderId:xtra.orderId,
			num:xtra.num,
			label:xtra.label,
			redeemed:xtra.redeemed
		}
		let storedExtras = [];
		try{
			storedExtras = JSON.parse(localStorage.getItem("erpExtras") || '[]');
		}catch(e){}
		storedExtras.push(rec);
		localStorage.setItem("erpExtras", JSON.stringify(storedExtras));
		if(!offlineAlerted){
			let msg = `Your internet connection has been lost.  
				Redeemed extras will be stored locally on this device and redeemed when the connection has been reestablished.`;
			erSvc.easyRegAlert({"text":msg,"title":"Internet Loss"});
			offlineAlerted = true;
		}
	}

	$scope.$watch('errorMsg',function(){
		if($scope.errorMsg){
			errorNotification.play();
		}else if(!lastBarcode){
			$scope.search = '';
			$scope.filterExtras();
		}
	});
});//End Controller