regApp.controller('courseMgmt', function($scope, $http, $q, dataSvc, erSvc) {
	dataSvc.accountid = erSessionData.accountid;
	$scope.statusFilter = '0';
	$scope.trackFilter = 'all';
	$scope.sponsorFilter = 'all';
	let sponsorsRetrieved = $q.defer();
	let tracksRetrieved = $q.defer();
	let videosRetrieved = $q.defer();
	$scope.coursePresenterMap = {};
	$scope.presenters = {};
	$scope.courseVideoMap = {};
	$scope.videos = {};

	$q.all([sponsorsRetrieved.promise,tracksRetrieved.promise]).then(getCourses);

	dataSvc.getTableRecords('sponsors', `accountid = ${accountid} AND archived != '1'`, true).then(function(res){
		$scope.sponsors = res;
		sponsorsRetrieved.resolve();
	});

	dataSvc.getObject({'query':'accountTracks'}).then(function(response){
		$scope.tracks = response;
		tracksRetrieved.resolve();
	});

	let crsFilter = `accountid = ${accountid}`;
	dataSvc.getTableRecords('users', crsFilter, true).then(function(res){
		$scope.presenters = res;
		angular.forEach($scope.presenters,function(pres){
			pres.courses_preferred = pres.courses_preferred || '';
			pres.courses_able = pres.courses_able || '';
			pres.courses_preferred.split(',').forEach(function(crs){
				if(!$scope.coursePresenterMap[crs]){
					$scope.coursePresenterMap[crs] = { "preferred":[], "able":[] };
				}
				$scope.coursePresenterMap[crs].preferred.push(`${pres.last_name}, ${pres.first_name}`);
			});
			pres.courses_able.split(',').forEach(function(crs){
				if(!$scope.coursePresenterMap[crs]){
					$scope.coursePresenterMap[crs] = { "preferred":[], "able":[] };
				}
				$scope.coursePresenterMap[crs].able.push(`${pres.last_name}, ${pres.first_name}`);
			});
		});
	});

	dataSvc.getObject({'query':'videos'}).then(function(resp){
		$scope.videos = resp;
		angular.forEach($scope.videos,function(vid){
			vid.courses = vid.courses || '';
			vid.courses.split(',').forEach(function(crs){
				if(!$scope.courseVideoMap[crs])	$scope.courseVideoMap[crs] = [];
				$scope.courseVideoMap[crs].push(vid);
			});
		});
	});

	$scope.getTrackNames = function(course){
		course.tracks = course.tracks || [];
		let names = [];
		angular.forEach(course.tracks,function(track){
			if($scope.tracks[track]) names.push(' ' + $scope.tracks[track].name);
		});
		return names.toString();
	}

	$scope.editCourse = function(course){
		$scope.selectedCourse = course;
		$('#editDiv').show(350);
	};

	$scope.addCoruse = function(){
		$scope.selectedCourse = {
			"name": "",
			"abbreviation": "",
			"description": "",
			"excludefromschedule": "0",
			"excludefromdocs": "0",
			"archived": "0",
			"tracks": "",
			"sponsorid":""
		};
		$('#editDiv').show(350);
	};

	$scope.saveEdits = function(){
		//edit existing course
		if($scope.selectedCourse.id){
			dataSvc.updateCourse($scope.selectedCourse).then(function(){
				$('#editDiv').hide(350);
			});
		}else{ //add new course
			dataSvc.createCourse($scope.selectedCourse).then(function(response){
				getCourses();
				$('#editDiv').hide(350);
			});
		}
	};

	$scope.closeRightDialog = function(){ $('.dialogRight').hide(350); };

	function getCourses(){
		dataSvc.getArray({'query':'courseList'}).then(function(response){
			angular.forEach(response,function(course){
				if(course.tracks) course.tracks = course.tracks.split(',').map(String);
			});
			$scope.courses = response;
			$scope.$applyAsync();
		});
	}

	$scope.showCourse = function(c){
		let qs = $scope.quickSearch ? $scope.quickSearch.toLowerCase() : '';
		let include = true;
		if(c.archived != $scope.statusFilter) include = false;
		if(qs && include){
			let compVal = (c.name || '') + (c.abbreviation || '') + $scope.getTrackNames(c) +
				(c.description || '') + (c.created_time || '') + (c.last_event_date || '') +
				(c.last_event_name || '');
			if($scope.sponsors[c.sponsorid]) compVal += $scope.sponsors[c.sponsorid].name;
			compVal = compVal.toLowerCase();
			if(compVal.indexOf(qs) < 0) include = false; 
		}
		if($scope.trackFilter != 'all' && include){
			if(!c.tracks.includes($scope.trackFilter)) include = false;
		}
		if($scope.sponsorFilter != 'all' && include){
			if(c.sponsorid != $scope.sponsorFilter) include = false;
		}
		return include;
	};

	// Import functionality
	$scope.import = () => $('#importDiv').show('500');

	$scope.importData;
	$scope.importFields = [
		{'field':'name','label':'Name'},
		{'field':'abbreviation','label':'Abbreviation'},
		{'field':'description','label':'Description'}
	];
	$scope.importMap = [];
	$scope.validateImport = function(){
		let files = $('#fileInput')[0].files;
		if(!files[0]) return;
		let file = files[0];
		if(!file.name.includes('.csv')){
			erSvc.easyRegAlert({"text":"Please select a file in .csv format for upload","title":"Invalid File Type"});
			return;
		}
		$scope.importMap = [];
		Papa.parse(files[0],{
			skipEmptyLines:true,
			complete: function(res){
				$scope.importData = res.data;
				let firstRow = $scope.importData[0];
				firstRow.forEach(function(val){
					$scope.importMap.push({"example":val,"field":findColMatch(val)});
				});
				$scope.$apply();
			},
			error: function(){
				psAlert({message:'Unable to process file.',title:'Error'});
			},
		});	  
	};

	$scope.fieldsMapped = function(){
		let mapped = true;
		if(!$scope.importMap.length) return false;
		$scope.importMap.forEach(f => { if(!f.field) mapped = false });
		return mapped;
	};

	$scope.processImport = function(){
		let recsImported = 0;
		$scope.importData.forEach(function(rec, idx){
			if(idx == 0) return;
			let importObject = getImportObject(rec);
			dataSvc.createOrUpdateRecord({"table":"courses","record":importObject}).then(function(res){
				importObject.id = res;
				importObject.archived = '0';
				importObject.excludefromdocs = '0';
				importObject.excludefromschedule = '0';

				$scope.courses.push(importObject);
				if(++recsImported == $scope.importData.length - 1){
					$scope.closeRightDialog();
					erSvc.easyRegAlert({"text":`${recsImported} courses have been imported.`,"title":"Import Complete"});
				}
			});
		});
	};

	function getImportObject(rec){
		let obj = {}
		rec.forEach(function(val, idx){
			obj[$scope.importMap[idx].field] = val;
		});
		obj.accountid = accountid;
		return obj;
	}

	//Functions for comparing import file headers to module fields to find closest match
	function findColMatch(val){
		let bestMatch = 0;
		let matchingCol = "student_number";
		$scope.importFields.forEach(function(field){
			let compValue = similarity(val, field.label);
			if(compValue > bestMatch){
				bestMatch = compValue;
				matchingCol = field.field;
			}
		});
		return matchingCol;
	}

	function similarity(s1, s2) {
		var longer = s1;
		var shorter = s2;
		if (s1.length < s2.length) {
			longer = s2;
			shorter = s1;
		}
		var longerLength = longer.length;
		if (longerLength == 0) 	return 1.0;
		return (longerLength - editDistance(longer, shorter)) / parseFloat(longerLength);
	}

	function editDistance(s1, s2) {
		s1 = s1.toLowerCase();
		s2 = s2.toLowerCase();
		var costs = new Array();
		for (var i = 0; i <= s1.length; i++) {
			var lastValue = i;
			for (var j = 0; j <= s2.length; j++) {
				if (i == 0)
					costs[j] = j;
				else {
					if (j > 0) {
						var newValue = costs[j - 1];
						if (s1.charAt(i - 1) != s2.charAt(j - 1))
							newValue = Math.min(Math.min(newValue, lastValue), costs[j]) + 1;
						costs[j - 1] = lastValue;
						lastValue = newValue;
					}
				}
			}
			if (i > 0)
			costs[s2.length] = lastValue;
		}
		return costs[s2.length];
	}
});//end controller
