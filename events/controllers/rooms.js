regApp.controller('rooms', function($scope, $http, dataSvc, erSvc) {
	setTimeout(function(){
		$('.nav-tabs li').removeClass('active');
		$('.nav-tabs li:contains("Rooms")').addClass('active');
	}, 100);
	$scope.eventid = erSessionData.curEvent.id;
	dataSvc.getTableRecords('rooms', 'eventid = ' + $scope.eventid, false, $scope.eventid)
	.then((res) => { 
		$scope.rooms = res; 
		$scope.rooms.forEach(r => r.sortorder = Number(r.sortorder));
	});

	$scope.addRoom = function(){
		$scope.rooms.push({
			"eventid":$scope.eventid,
			"name":"",
			"subname":"",
			"area":"",
			"sortorder":$scope.rooms.length + 1
		});
	};

	$scope.checkSort = function(){
		$scope.rooms.forEach(room => {
			room.sortorder = Number(room.sortorder)
			if(room.sortorder < 1) room.sortorder = 1;
			if(room.sortorder > $scope.rooms.length) room.sortorder = $scope.rooms.length;
		});
	};

	$scope.saveRooms = function(){
		let updatesComplete = 0;
		$scope.rooms.forEach(function(room){
			if(!room.name){
				if(++updatesComplete == $scope.rooms.length){
					$scope.changesSaved = true;
					setTimeout(function(){
						$scope.changesSaved = false;
						$scope.$apply();
					}, 2000);
				}
			}else{
				dataSvc.createOrUpdateRecord({"table":"rooms","record":room},$scope.eventid).then((res) => {
					if(!room.id) room.id = res;
					if(++updatesComplete == $scope.rooms.length){
						$scope.changesSaved = true;
						setTimeout(function(){
							$scope.changesSaved = false;
							$scope.$apply();
						}, 2000);
					}
				});
			}
		});
	};

	$scope.deleteRoom = function(room){
		var confirmData = {"text":"Delete this room?","title":"Confirm Delete"};
		if(!room.id){
			$scope.rooms.splice($scope.rooms.indexOf(room),1);
		}else{
			erSvc.easyRegConfirm(confirmData,'Confirm', 'Cancel').then(function(res){
				if(res){
					if(room.id)	dataSvc.deleteRecord({"table":"rooms","id":room.id},$scope.eventid)
					$scope.rooms.splice($scope.rooms.indexOf(room),1);
				}
			});
		}
		$scope.checkSort();	
	};
});//end controller