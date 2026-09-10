<?php
	include("common_functions.php");
	require_csrf_request();
	if($_POST['userData'] != ''){
		$_SESSION['userData'] = $_POST['userData'];
	}
	if($_POST['accountLogo']){
		$_SESSION['accountLogo'] = $_POST['accountLogo'];
	}
	if($_POST['accountEvents']){
		$_SESSION['accountEvents'] = $_POST['accountEvents'];
	}
	if($_POST['accountEventsFor']){
		$_SESSION['accountEventsFor'] = $_POST['accountEventsFor'];
	}
	if($_POST['curEvent']){
		$_SESSION['curEvent'] = $_POST['curEvent'];
	}
	if($_POST['navPages']){
		$_SESSION['navPages'] = $_POST['navPages'];
	}
	if($_POST['currentSponsorData']){
		$_SESSION['currentSponsorData'] = $_POST['currentSponsorData'];
	}
	touch_session_activity(true);
 ?>
