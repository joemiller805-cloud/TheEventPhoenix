<?php session_start();
	include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php";
	touch_session_activity(true);
	$sponsorAccess = ["sponsor_orders","registrations","sponsor_pending_pymts","vendor_orders","vendor_order_details"];
	$userAccess = explode(',',$_SESSION['tableAccess']);
	$inputs = sanitize_inputs($_REQUEST);
	$master = $_SESSION['master'];
	$sponsor = $_SESSION['sponsorid'];
	
	$authorized = false;
	if($master == '1') $authorized = true;
	if(in_array($inputs['table'], $userAccess)) $authorized = true;
	if($sponsor AND in_array($inputs['table'], $sponsorAccess)) $authorized = true;
	if($inputs['table'] == 'registration_extra_orders') $authorized = true;
	if($inputs['table'] == 'registrations') $authorized = true;

	if($authorized){
		$query = "DELETE FROM " . $inputs['table'] . ' WHERE id = ' . $inputs['id'];
		$query = str_replace("\'","'",$query);
		$resourceID = database_connect();
		try{
			$resultID = mysqli_query($resourceID, $query);
			if($resultID){
				$insertId = mysqli_insert_id($resourceID);
				print($insertId);
			}else{
				http_response_code(500);
				print $query;
			}
		}catch(Exeption $e){
			http_response_code(500);
			print $query;
		}
		mysqli_close($resourceID);
	}else{ //Security Violation
		http_response_code(403);
		print("Not Authorized");
	}
?>
