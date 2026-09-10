<?php
	session_start();
	if (isset($_SESSION['accountid'])){
		if(unlink($_GET['document'])) echo "success";
		else echo "error";
	}else{
		echo 'error';
	}
?>