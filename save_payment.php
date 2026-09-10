<?php session_start(); ?>
<?php
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	if (isset($_SESSION['userid'])){
		$userid = (int)$_SESSION['userid'];
		touch_session_activity(true);
		$resourceID = database_connect();		
		$query = "
			insert into registration_payments
				(
					registrationid,
					amount,
					payment_type,
					ref_nbr,
					entered_by,
					entered_date,
					note
				)
			values
				(
					{$inputs['registrationid']},
					{$inputs['amount']},
					'{$inputs['payment_type']}',
					'{$inputs['ref_nbr']}',
					{$userid},
					now(),
					'{$inputs['note']}'
				)		
		";				
		if(!mysqli_query($resourceID, $query)){
			$message = json_encode(mysqli_error($resourceID));
			$query = json_encode($query);
			print "{status: 0, message: \"$message\", query: \"$query\"}";
		}else{
			print "{\"status\": 1}";
		}
		mysqli_close($resourceID);	
	}
?>
