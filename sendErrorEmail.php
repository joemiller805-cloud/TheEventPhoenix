<?php
	session_start();
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$errorMsg = "Session Data - " . json_encode($_SESSION) . "\r\n";
	$errorMsg .= $inputs['page'] . " \r\n" . $inputs['error'] . "\r\n";
	$errorMsg .= "ATTEMPT: " . $inputs['attempt'] . "\r\n" . date("m/d/y h:i p") ;
	error_log($errorMsg ,1,'emschaitel@gmail.com','easyregErr@easyregpro.com');
?>