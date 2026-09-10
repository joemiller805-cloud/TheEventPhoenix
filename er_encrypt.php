<?php
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	print crypt($inputs['value'], LEGACY_SALT);
?>