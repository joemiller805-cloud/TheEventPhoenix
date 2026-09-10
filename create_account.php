<?php
session_start();
include("common_functions.php");
$inputs = sanitize_inputs($_REQUEST);
$isErSupport = $_SESSION['erSupport'] ?? '';
touch_session_activity(true);
if($isErSupport != 'true'){
	header('Location: /login_er.php');
} 
$resourceID = database_connect();
$acctQuery = "
	INSERT INTO accounts (name, slug, contact_name, contact_email, contact_phone, type, trial, disabled, sis)
	VALUES ('{$inputs["name"]}', '{$inputs["slug"]}', '{$inputs["contact_name"]}', '{$inputs["contact_email"]}', '{$inputs["contact_phone"]}', '{$inputs["type"]}', '{$inputs["trial"]}', '{$inputs["disabled"]}', '{$inputs["sis"]}')
";
mysqli_query($resourceID, $acctQuery);
$insertId = mysqli_insert_id($resourceID);
$userQuery = "
	INSERT INTO users (accountid, email, pass, first_name, last_name, master)
	VALUES ($insertId, 'support@easyregpro.com', 'PSDWAjFh.tAp6', 'EasyReg', 'Support', 1)
";
mysqli_query($resourceID, $userQuery);
print($insertId);
mysqli_close($resourceID);
?>
