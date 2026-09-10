<?php
	include("common_functions.php");
	require_csrf_request();
	session_unset();
	session_destroy();
?>
