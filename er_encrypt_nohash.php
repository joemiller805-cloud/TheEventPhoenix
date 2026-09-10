<?php
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	print encryptthis($inputs['value']);
?>