<?php
	include("common_functions.php");
	session_unset();
	session_destroy();
	session_start();
	$inputs = sanitize_inputs($_REQUEST);
	$pass = crypt($_POST['pass'], LEGACY_SALT);

	if($_POST['user'] == 'admin4er' && $pass == 'PSiGgn773vaeo'){
		$_SESSION['erSupport'] = 'true';
		$_SESSION['erMaster'] = 'true';
		$_SESSION['master'] = '1';
		print('success');
	}else{
		print('error');
	}
?>