<?php
session_start();
include("common_functions.php");
$isErSupport = $_SESSION['erSupport'] ?? '';
touch_session_activity(true);
if($isErSupport != 'true'){
	header('Location: /login_er.php');
} 
$resourceID = database_connect();
$inputs = sanitize_inputs($_REQUEST);
$query = "
	UPDATE accounts
	SET name = '{$inputs['name']}', 
		slug = '{$inputs['slug']}', 
		contact_name = '{$inputs['contact_name']}', 
		contact_email = '{$inputs['contact_email']}', 
		contact_phone = '{$inputs['contact_phone']}',
		disabled = {$inputs['disabled']}, 
		trial = {$inputs['trial']}, 
		sis = '{$inputs['sis']}', 
		type = '{$inputs['type']}',
		cc_charge_rt = '{$inputs['cc_charge_rt']}'
	WHERE id = {$inputs['accountid']}
";
$resultID = mysqli_query($resourceID, $query);
print("success");
mysqli_close($resourceID);
?>
