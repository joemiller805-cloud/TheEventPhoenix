<?php session_start();
	if(isset($_SESSION['last_activity'])){
		$_SESSION['last_activity'] = time();
	}
?>