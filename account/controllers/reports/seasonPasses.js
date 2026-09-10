regApp.controller('seasonPasses', function($scope, $http, $q, dataSvc, erSvc, dateService) {
	$scope.sortField = 'last_name';
	$scope.sortReverse = false;
	$scope.pageNumber = 1;
	$scope.passDetails = {};

	dataSvc.getArray({'query':'seasonPassOrders'}).then(function(orders){
		angular.forEach(orders,function(order){
			try	{ 
				order.passTypes = [];
				order.order_details = angular.fromJson(order.order_details); 
				angular.forEach(order.order_details, o => order.passTypes.push(o.passId));
			}
			catch(e) { console.error(e); }
			// get sort_date (YYYYMMDD) value from MySQL timestamp
			order.sort_date = order.created_time.split(' ')[0].split('-').join('');
			order.searchString = order.last_name + order.first_name + order.email;
			order.searchString = order.searchString.toLowerCase();
		});
		$scope.orders = orders;
		$scope.filteredOrders = [...orders];
		$scope.filterRecs();
	});

	dataSvc.getTableRecords('season_passes', `accountid = ${erSessionData.accountid}`, true).then(r=> $scope.passDetails = r);

	$scope.pageSize = 50;
	groupToPages = function(){
		$scope.pageNumber = 1;
	    $scope.pagedRecords = [];
	    for (var i = 0; i < $scope.filteredOrders.length; i++) {
	        if(i % $scope.pageSize === 0) {
	            $scope.pagedRecords[Math.floor(i / $scope.pageSize)] = [$scope.filteredOrders[i]];
	        }else{
	            $scope.pagedRecords[Math.floor(i / $scope.pageSize)].push($scope.filteredOrders[i]);
	        }
	    }
	    updateCols();
	};

	$scope.quickSearch = '';
	$scope.filterRecs = function(){
		// $scope.filteredOrders = [];
		$scope.totolAmount = 0;
		$scope.totolCcFee = 0;
		let searchVal = $scope.quickSearch.toLowerCase();
		let minDate = dateService.getSortDate($scope.minDate);
		let maxDate = dateService.getSortDate($scope.maxDate);
		$scope.filteredOrders = $scope.orders.filter(order => {
			if(!searchVal == '' && !order.searchString.includes(searchVal)) return false;
			if($scope.passFilter && !order.passTypes.includes($scope.passFilter)) return false;
			if(minDate && order.sort_date < minDate) return false;
			if(maxDate && order.sort_date > maxDate) return false;
			return true;
		});
		$scope.filteredOrders.forEach(order => {
			$scope.totolAmount += Number(order.amt_charged);
			$scope.totolCcFee += Number(order.cc_fee);
		});
		groupToPages();
		updateCols();
	};

	$scope.changeSort = function(field){
		field = field || 'created_time';
		if($scope.sortField == field){
			$scope.filteredOrders.reverse();
		}else{
			$scope.filteredOrders.sort(function(a,b){
				if(!a[field] && !b[field]) return -1;
				return((a[field] || '').toLowerCase() > (b[field] || '').toLowerCase() ? 1 : -1);
			});
		}
		$scope.sortField = field;
		groupToPages();
	};

	function updateCols(){
		setTimeout(function(){
			$scope.$apply(function(){
				erSvc.initializeColumns($('#ordersTable'));
			});
		}, 200);
	}

	$scope.columnFilter = function(){
		erSvc.columnFilter($('#ordersTable'));
	};

	$scope.exportResults = function(){
		let clean = (str) => str ? str.replace(/#/g,'').replace(/"/g,"'") : '';
		let data = [`"Last Name","First Name","Email","Order Details","Amount","CC Fee"`];
		$scope.filteredOrders.forEach(function(r){
			r.details = '';
			r.order_details.forEach(function(pass){
				if(r.details) r.details += '\n'
				r.details += `${$scope.passDetails[pass.passId].name} (${pass.quantity} @ ${pass.price})`;
			})
	
			data.push(`"${clean(r.last_name)}","${clean(r.first_name)}","${clean(r.email)}","${clean(r.details)}","${clean(r.amt_charged)}","${clean(r.cc_fee)}"`);
		});
		arrayToCsv(data, 'Payments');
	};
});// End Controller