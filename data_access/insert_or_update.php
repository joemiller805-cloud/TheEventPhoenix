<?php session_start();
	include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php";
	touch_session_activity(true);
	$openAccess = ["acme_access_requests"];
	$userAccess = explode(',',$_SESSION['tableAccess']);
	$allUserAccess = ["documents","document_association","course_proposals","user_event"];
	$sponsorAcces = ["sponsors","sponsor_orders","sponsor_pending_pymts","sponsor_payments","course_proposals","vendor_order_details","vendor_orders"];
	$inputs = sanitize_inputs($_REQUEST);
	$master = $_SESSION['master'];

	$inputs['updateData'] = str_replace("\'","'",$inputs['updateData']);

	$authorized = false;
	if($master == '1') $authorized = true;
	if(in_array($inputs['table'], $userAccess)) $authorized = true;
	if(in_array($inputs['table'], $openAccess)) $authorized = true;
	
	if($inputs['table'] == 'registration_extra_orders') $authorized = true;
	if(in_array($inputs['table'], $sponsorAcces)){
		if($inputs['command'] == 'insert'){
			$authorized = true;
		}else if(str_replace("id = ","",$inputs['whereClause']) == $_SESSION['sponsorid']){
			$authorized = true;
		}else if(strpos($inputs['updateData'], "sponsorid = '" . $_SESSION['sponsorid'] . "'") >= 0){
			$authorized = true;
		}
	}
	if($inputs['table'] == 'users' && $_SESSION['sponsorid']) $authorized = true;

	if($authorized){
		if($inputs['command'] == 'insert'){
			$query = "INSERT INTO " . $inputs['table'] . ' ' . $inputs['insertColumns'];
			$query = $query . ' VALUES ' . $inputs['insertValues'];
		}else if($inputs['command'] == 'update'){
			$query = "UPDATE " . $inputs['table'] . " SET " . $inputs['updateData'];
			$query = $query . " WHERE " . $inputs['whereClause'];
		}
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
			print "Query Execution Error";
		}
		mysqli_close($resourceID);
	}else{ //Security Violation
		http_response_code(403);
		print("Not Authorized");
	}
?>
