<html>
<head>
	<?php
		include("common_functions.php");
		start_secure_session();
	;?>
	<?php include("commonStyles.php");?>
	<?php include("commonJs.php");?>

	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc', 'navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			let path = window.location.pathname;
			path = path.split('/');
			if(path.length > 1){
				let slug = path[path.length - 1];
				erSvc.getAccountIdFromURL().then(function(acctId){
					if(acctId) window.location.replace(window.location.origin + '/index.php?accountid=' + acctId);
					else $('#404Container').show();
				});
			}else{
				$('#404Container').show()
			}
		}); //end controller
	</script>
</script>
<body ng-app="regApp">
	<div ng-controller="regController" style="display:none" id="404Container">
		<H2 class="center">
			Oops!  The page you are searching for could not be found
		</H2>
	</div>
</body>
</html>
