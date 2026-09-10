regApp.controller('inventoryCtrl', function($scope, $http, dataSvc, erSvc) {
	let catFilter = 'accountid = ' + accountid + ' AND name =' +  "'inventorySettings'";
	$scope.newItemQty = 1;
	let potentialProperties = ['brand','item_condition','location','model','notes','purchase_date','purchase_price','serial_number'];
	$scope.usedProperties = [];
	dataSvc.getTableRecords('preferences',  catFilter).then(function(res){
		$scope.categories = [];
		$scope.locations = [];
		$scope.conditions = [];
		if(res[0]){
			try{
				let settings = angular.fromJson(res[0].value);
				$scope.categories = settings.categories;
				$scope.locations = settings.locations;
				$scope.conditions = settings.conditions;
				inventoryPrefId = res[0].id;
				$scope.newItemCategory = $scope.categories[0];
			}catch(e){}
			$scope.categories.forEach(function(cat){
				potentialProperties.forEach(function(prop){
					if(cat[prop] && !$scope.usedProperties.includes(prop)) 
						$scope.usedProperties.push(prop);
				});
			});
		}
	});

	$scope.showItem = function(item){
		if(!$scope.showRetired && item.retired) return false;
		if(!$scope.quickSearch) return true;
		return item.searchString.indexOf($scope.quickSearch.toLowerCase()) >= 0;pre
	};

	dataSvc.getTableRecords('inventory', 'accountid = ' + accountid, true).then(function(res){
		$scope.inventory = res;
		angular.forEach($scope.inventory, item => {
			item.searchString = getSearchString(item);
			item.retired = item.retired != '0';
		});		
	});

	function getSearchString(item){
		let str = '';
		for(const prop in item){ if(item[prop]) str += item[prop].toString().toLowerCase(); }
		return str;
	};

	$(document).on('change', '.newItemRow input, .newItemRow select', function(idx){
		let prop = $(this).attr('ng-model').split('.')[1];
		if(['serial_number'].includes(prop)) return;
		$scope.newItems.forEach( item => { if(!item[prop]) item[prop] = $(this).val() });
		$scope.$apply();
	});

	$scope.selectNewItemType = function(){
		$('#typeSelectorDialog').dialog({"title":"New Inventory Items","modal":true});
	};

	$scope.cancelNewItem = () => $(".ui-dialog-content").dialog("close");

	$scope.createNewItems = function(){
		$('#newItemDialog').show(500);
		$(".ui-dialog-content").dialog("close");
		$scope.newItems = [];
		for(let i = 0; i < $scope.newItemQty; i++){
			$scope.newItems.push({
				"brand":"",
				"model":"",
				"serial_number":"",
				"notes":"",
				"condition":"",
				"purchase_date":"",
				"price":""
			});
		}
	};

	$scope.saveNewItems = function(){
		$('.dialogRight').hide(500);
		$scope.newItems.forEach(function(item){
			item.accountid = accountid;
			item.category = $scope.newItemCategory.name;
			item.type = $scope.newItemType;
			dataSvc.createOrUpdateRecord({"table":"inventory","record":item}).then(function(res){
				item.searchString = getSearchString(item);
				item.id = res;
				$scope.inventory[res] = item;
				$scope.$applyAsync();
			});
		});
	};

	$scope.confirmDelete = function(){
		erSvc.loadingDialog();
		let txt = `Delete this item?`;
		erSvc.easyRegConfirm({"text":txt,"title":`Delete ${$scope.editItem.type}`},"Delete","Cancel")
		.then(function(res){
			if(res){
				dataSvc.deleteRecord({"table":"inventory","id":$scope.editItem.id}).then(function(){
					delete $scope.inventory[$scope.editItem.id];
					erSvc.closeLoading();
					$scope.closeRightDialog();
				});
			}
			if(!res) erSvc.closeLoading();
		});
	};

	$scope.openItemForEdit = function(item){
		$scope.editItem = angular.copy(item);
		$scope.categories.forEach((c) => { if(c.name == item.category) $scope.editItemCategory = c });
		$('#editItemDialog').show(500);
	};

	$scope.saveEditItem = function(){
		$scope.editItem.searchString = getSearchString($scope.editItem);
		$scope.inventory[$scope.editItem.id] = $scope.editItem;
		erSvc.loadingDialog('Saving Item Information');
		dataSvc.createOrUpdateRecord({"table":"inventory","record":$scope.editItem}).then(function(){
			erSvc.closeLoading();
			$scope.closeRightDialog();
			$scope.$applyAsync();
		});
	}

	$scope.closeRightDialog = () => $('.dialogRight').hide(500);
});//end controller