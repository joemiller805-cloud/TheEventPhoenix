<?php
	include("common_functions.php");
	start_secure_session();
	print (json_encode($_SESSION));
?>
