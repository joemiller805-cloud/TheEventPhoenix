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
if($inputs['id']){
	$feeQuery = "
		UPDATE account_fee_structure
		SET reg_fee = '{$inputs['reg_fee']}', 
			reg_fee_method = '{$inputs['reg_fee_method']}', 
			reg_charge_to = '{$inputs['reg_charge_to']}', 
			reg_charge_zero_items = {$inputs['reg_charge_zero_items']}, 
			ticket_fee = '{$inputs['ticket_fee']}', 
			ticket_fee_method = '{$inputs['ticket_fee_method']}',
			ticket_charge_to = '{$inputs['ticket_charge_to']}',
			ticket_charge_zero_items = {$inputs['ticket_charge_zero_items']},
			start_date = '{$inputs['start_date']}',
			end_date = '{$inputs['end_date']}'
		WHERE id = {$inputs['id']}
	";
	$resultID = mysqli_query($resourceID, $feeQuery);
	print($inputs['id']);
}else{
	$feeQuery = "
		INSERT INTO account_fee_structure(
			reg_fee,
			reg_fee_method,
			reg_charge_to,
			reg_charge_zero_items,
			ticket_fee,
			ticket_fee_method,
			ticket_charge_to,
			ticket_charge_zero_items,
			start_date,
			end_date,
			accountid
		)
		VALUES(
			'{$inputs['reg_fee']}', 
			'{$inputs['reg_fee_method']}', 
			'{$inputs['reg_charge_to']}', 
			{$inputs['reg_charge_zero_items']}, 
			'{$inputs['ticket_fee']}', 
			'{$inputs['ticket_fee_method']}',
			'{$inputs['ticket_charge_to']}',
			{$inputs['ticket_charge_zero_items']},
			'{$inputs['start_date']}',
			'{$inputs['end_date']}',
			'{$inputs['accountid']}'
		)	
	";
	$resultID = mysqli_query($resourceID, $feeQuery);
	$insertId = mysqli_insert_id($resourceID);
	print($insertId);
}
mysqli_close($resourceID);
?>
