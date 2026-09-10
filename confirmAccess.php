<?php
	session_start();
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$pages = explode(",", $_SESSION['pageAccess']);
	if(in_array($inputs['loc'], $pages) OR $_SESSION['master'] == '1'){
		print 'true';
	}else{
		print 'false';
	}
?>