regApp.controller('tracks', function($scope, $http, erSvc, dataSvc) {
	$scope.accountid = erSessionData.accountid;
	dataSvc.accountid = $scope.accountid;
	
	dataSvc.getObject({'query':'accountTracks'}).then(function(tracks){
		$scope.tracks = tracks;
		angular.forEach($scope.tracks,function(track){
			track.pristine = true;
		});
	});

	$scope.updateTrack = function(track){
		dataSvc.createOrUpdateRecord({"table":"tracks","record":track}).then(function(){
			track.pristine = true;
		});
	};

	$scope.addTrack = function(){
		$scope.newTrack = "";
		$('#newTrackDialog').dialog({title:"New Track",modal:true});
	};

	$scope.saveNewTrack = function(){
		var newTrack = {"name":$scope.newTrack,"accountid":$scope.accountid};
		dataSvc.createOrUpdateRecord({"table":"tracks","record":newTrack}).then(function(resp){
			$scope.tracks[resp] = {name:$scope.newTrack,id:resp,pristine:true};
			$('#newTrackDialog').dialog('close');
			$scope.$applyAsync();
		});
	};

	$scope.cancelNew = () => $('#newTrackDialog').dialog('close');

	$scope.deleteTrack = function(track){
		dataSvc.deleteRecord({"table":"tracks","id":track.id});
		delete $scope.tracks[track.id];
	};

});//end controller