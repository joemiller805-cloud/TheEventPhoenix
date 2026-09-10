<?php
	session_start();
	$directory = "img/account" . $_SESSION['accountid'];
	if(is_dir($directory)){
		$files = scandir($directory);
		foreach ($files as $key => $value) {
			// ensure file has '.' so we don't get sub-folders
			if(strpos($value, '.')){
				echo '/'. $directory . "/$value,";
			}
		}
	}
?>