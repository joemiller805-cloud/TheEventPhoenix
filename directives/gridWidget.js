angular.module("gridWidget",[]).directive('erGridWidget', function($compile) {
return {
	restrict: 'A', // Attribute directive
	scope: {
		data: '=',
		columns: '=',
		pageSz: '=?',
		itemsPerPg: '@?',
		enableSelection: '@?',
		exportName: '@?',
		noColSelect: '@?',
		noSearch: '@?',
		noFilters: '@?'
	},
	templateUrl: '/directives/grid_table.html?_=' + Math.random(),
	controller: function($scope, $filter, erSvc) {
		let loc = window.location.hash ?? window.location.pathname;
		$scope.data = $scope.data || [];
		$scope.filteredData = [];
		$scope.tblGridFilters = {};
		$scope.itemsPerPg = Number(localStorage.getItem(`erRowCount-${loc}`)) || parseInt($scope.itemsPerPg) || 10;
		$scope.sortColumn = null;
		$scope.sortReverse = false;
		$scope.searchQuery = '';
		$scope.state = {
			noSearch: $scope.noSearch == 'true',
			noColSelect: $scope.noColSelect == 'true',
			noFilters: $scope.noFilters == 'true',
			enableSelection: $scope.enableSelection == 'true',
			allRowsSelected: false,
			curPg: 0
		};
		$scope.showFooter = $scope.columns.filter(c => c.showTotal).length;
		let selectedInited = false;
		$scope.$watch('data', function(newDta, oldDta) {
			if (newDta && (!selectedInited || newDta.length != oldDta.length)){
				if(!$scope.state.enableSelection)	newDta.forEach(r => r.selected = true);
				selectedInited	= true;
				$scope.filterData();
			} 
		},true);

		$scope.toggleAllSelected = () => {
			$scope.filteredData.forEach(r => r.selected = $scope.state.allRowsSelected);
		};

		$scope.updateAllRowsSelected = () => {
			$scope.state.allRowsSelected = $scope.filteredData.every(r => r.selected);
		};

		// Apply per-column value maps first, then fall back to named AngularJS filters.
		$scope.formatVal = (val, col) => {
			if(!col) return val;
			if(col.valueMap && Object.prototype.hasOwnProperty.call(col.valueMap, val)) {
				return col.valueMap[val];
			}
			return col.filter ? $filter(col.filter)(val) : val;
		};	

		$scope.currentPage = 0;
		$scope.pageSz = $scope.pageSz ?? 10;
		$scope.paginatedData = [];

		//for debugging - log row on double-click in table cell
		$scope.log = row => console.log(row);


		/*********************** Filtering Logic ***********************/
		$scope.$watch('searchQuery', () => $scope.filterData() );
		$scope.filterData = function() {
			$scope.columns.forEach(c => c.hasFilter = false);
			let filters = $scope.visibleCols().filter(c =>
				(c.filters && c.filters.length) || (c.values && !c.values.every(v => v.selected))
			);
			if(!$scope.searchQuery && !filters.length){
				$scope.filteredData = $scope.data;
				getTotals();
				return ;
			}
			
			let filteredData = $scope.data;
			let srch = $scope.searchQuery.toLowerCase();
			if(srch){
				filteredData = filteredData.filter(r => {
					return $scope.visibleCols().some(c => {
						if(r[c.fld] == undefined || r[c.fld] == null) return false;
						return (r[c.fld].toString() || '').toLowerCase().includes(srch)
					});
				});
			}

			filters.forEach(fltr => {
				let col = $scope.columns.find(c => c.fld === fltr.fld);
				if (col) col.hasFilter = true;
				if(fltr.values){
					let selectedVals = fltr.values.filter(v => v.selected).map(v => v.lbl);
					filteredData = filteredData.filter(r => selectedVals.includes(r[fltr.fld]));
				}else if(fltr.filters){
					fltr.filters.forEach(filter => {
						if(!['empty','notEmpty'].includes(filter.comparator)  &&
							!filter.hasOwnProperty('value') && !filter.value && filter.value !== 0){
							col.filters.splice(col.filters.indexOf(filter),1);
							col.hasFilter = col.filters.length > 0;
							return;
						} 
						filteredData = filteredData.filter(r => meetsFilter(filter, r[fltr.fld], fltr.dataType));
					});
				}
			});

			function meetsFilter(filter, val, dataType){
				let filterVal = filter.value;
				let fieldVal = val;
				if(typeof filterVal == 'string'){
					filterVal = filterVal.toLowerCase();
					fieldVal = (fieldVal || '').toLowerCase();
				}else if(filterVal instanceof Date){
					filterVal = filterVal.getTime();
					if(!(fieldVal instanceof Date)) fieldVal = new Date(fieldVal).getTime();
				}
				
				switch (filter.comparator){
					case "eq":
						return fieldVal == filterVal;
						break;
					case "gt":
						return fieldVal > filterVal;
						break;
					case "gte":
						return fieldVal >= filterVal;
						break;
					case "lt":
						return fieldVal < filterVal;
						break;
					case "lte":
						return fieldVal <= filterVal;
						break;
					case "contains":
						return fieldVal.includes(filterVal);
						break;
					case "notContains":
						return !fieldVal.includes(filterVal);
						break;
					case "empty":
						return !fieldVal && fieldVal !== 0;
						break;
					case "notEmpty":
						return !!fieldVal || fieldVal === 0;
						break;
				}	
			}
			$scope.filteredData = filteredData;
			getTotals();
			let pgs = $scope.nbrOfPgs();
			if(pgs && $scope.state.curPg + 1 > pgs) $scope.state.curPg = pgs -1;
		};

		function getTotals(){
			$scope.columns.filter(c => c.showTotal).forEach(col => {
				let ttl = 0;
				$scope.filteredData.forEach(r => ttl += Number(r[col.fld]));
				col.ttl = ttl;
			});
		}

		$scope.clearFilters = () =>{
			$scope.filterCol.filters = null;
			$scope.filterCol.values?.forEach(f => f.selected = true);
			$scope.filterData();
		};

		$scope.showFilters = col => {
			if(!col.filters && !col.values){
				let values = new Set();
				$scope.data.forEach(r => values.add(r[col.fld]));
				values = Array.from(values);
				if(values.length <= 10) col.values = values.map(v => { return {lbl:v,selected:true} });
				else col.filters = [{comparator:'eq'}];
			}
			$scope.filterCol = col;
			$scope.updateFltrAll();
		};

		$scope.updateFltrAll = () =>  {
			let col = $scope.filterCol;
			$scope.gridSelectAll = col.values && col.values.every(v => v.selected);
		};

		$scope.removeFilter = fltr => {
			$scope.filterCol.filters.splice($scope.filterCol.filters.indexOf(fltr),1);
			if($scope.filterCol.filters.length == 0) $scope.filterCol.filters = null;
		}

		$scope.toggleFilterSelections = () => {
			$scope.filterCol.values.forEach(v => v.selected = $scope.gridSelectAll);
		};

		$scope.addFilterCriteria = () => $scope.filterCol.filters.push({comparator:'eq'});

		$scope.sortBy = (column) => {
			let dataType = 'string';
			let selected = $scope.columns.filter(c => c.fld == column);
			if(selected[0]) dataType = selected[0].dataType ?? 'string';
			if($scope.sortColumn === column) {
				$scope.sortReverse = !$scope.sortReverse;
			} else {
				$scope.sortColumn = column;
				$scope.sortReverse = false;
			}
			$scope.filteredData.sort((a, b) => {
				if(dataType == 'number'){
					if(!$scope.sortReverse) return Number(a[column]) - Number(b[column]);
					else return Number(b[column]) - Number(a[column]);
				} 
				if(dataType == 'date'){
					a = erSvc.localToMySqlDate(a[column]);
					b = erSvc.localToMySqlDate(b[column]);
					if(a < b) return $scope.sortReverse ? 1 : -1;
					if(a > b) return $scope.sortReverse ? -1 : 1;
					return 0;
				}
				let aVal = (a[column] ?? '').toLowerCase();
				let bVal = (b[column] ?? '').toLowerCase();
				if(aVal < bVal) return $scope.sortReverse ? 1 : -1;
				if(aVal > bVal) return $scope.sortReverse ? -1 : 1;
				return 0;
			});
		}

		$scope.pagedData = function() {
			let start = $scope.state.curPg * $scope.itemsPerPg;
			return $scope.filteredData.slice(start, start + $scope.itemsPerPg);
		};

		$scope.nbrOfPgs = () => Math.ceil($scope.filteredData.length / $scope.itemsPerPg);
		$scope.nextPg = () => { if($scope.state.curPg < $scope.nbrOfPgs() - 1) $scope.state.curPg++; };
		$scope.lastPg = () => { $scope.state.curPg = $scope.nbrOfPgs() - 1 };
		$scope.prevPg = () => { if($scope.state.curPg > 0)	$scope.state.curPg--; };
		$scope.firstPg = () => { $scope.state.curPg = 0 };

		$scope.$watch('settings.itemsPerPg', () => $scope.state.curPg = 0);

		/*********************** Column and Page Size Visibility Logic ***********************/

		$scope.$watch('columns', () => {
			//Load Column Preferences from localStorage
			let storedPrefs = localStorage.getItem(`erColPrefs-${loc}`);
			if(storedPrefs) {
				let savedCols = JSON.parse(storedPrefs);
				$scope.columns.forEach(c => {
					if(savedCols[c.fld] !== undefined) c.visible = savedCols[c.fld];
				});
			}else{
				$scope.columns.forEach(c => c.visible = true);
			}
		},true);

		$scope.$watch('itemsPerPg', () => {
			localStorage.setItem(`erRowCount-${loc}`, $scope.itemsPerPg);
		},true);

		$scope.visibleCols = () => $scope.columns.filter(col => col.visible);

		// Save Column Preferences to localStorage
		function saveColPrefs() {
			let visibilityMap = {};
			$scope.columns.forEach(col => visibilityMap[col.fld] = col.visible);
			localStorage.setItem(`erColPrefs-${loc}`, JSON.stringify(visibilityMap));
		};

		// Toggle All Columns
		$scope.toggleCols = function() {
			$scope.columns.forEach(c => c.visible = $scope.allColsSelected);
			saveColPrefs();
		};

		// Update "Select All" Checkbox State
		$scope.updateColState = function() {
			$scope.allColsSelected = $scope.columns.every(c => c.visible);
			saveColPrefs();
		};

		$scope.export = () =>{
			let fileName = ($scope.exportName || 'export') + '.csv';
			let visibleCols = $scope.visibleCols();
			let data = $scope.filteredData.filter(r => r.selected);
			if(!data || !data.length) return;
			const headers = visibleCols.map(c => `"${c.label.replaceAll('"', '""')}"`);
			const csvRows = [
				headers.join(','),
				...data.map(row => visibleCols.map(fld => JSON.stringify(row[fld.fld] || ''))
					.join(','))
			].join('\n');

			const blob = new Blob([csvRows], { type: 'text/csv;charset=utf-8;' });
			const link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.setAttribute('download', fileName);
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		};
	}
};
});
