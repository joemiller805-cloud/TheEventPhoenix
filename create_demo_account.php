<?php
include("common_functions.php");
$resourceID = database_connect();
$inputs = sanitize_inputs($_REQUEST);

//create account
$acctQuery = "
	INSERT INTO accounts (name, email, slug, phone, fax, type, trial)
	VALUES (
		'{$inputs["name"]}',
		'{$inputs["email"]}',
		'{$inputs["slug"]}',
		'{$inputs["phone"]}',
		'{$inputs["fax"]}',
		'{$inputs["type"]}'
		,1
	)
";
mysqli_query($resourceID, $acctQuery);
$insertId = mysqli_insert_id($resourceID);

//create account user
$userQuery = "
	INSERT INTO users (accountid, email, pass, first_name, last_name, master)
	VALUES (
		$insertId,
		'{$inputs["login_email"]}',
		'{$inputs["login_pw"]}',
		'{$inputs["login_fn"]}',
		'{$inputs["login_ln"]}', 
		1
	)
";
mysqli_query($resourceID, $userQuery);

//create support account
$userQuery = "
	INSERT INTO users (accountid, email, pass, first_name, last_name, master)
	VALUES ($insertId, 'support@easyregpro.com', 'PSDWAjFh.tAp6', 'EasyReg', 'Support', 1)
";
mysqli_query($resourceID, $userQuery);

//create account_fee_structure record
$feeQuery = "
	INSERT INTO account_fee_structure (accountid, reg_fee, reg_fee_method, ticket_fee, ticket_fee_method)
	VALUES ($insertId, 0,'flat',0,'flat')
";

echo($feeQuery);
mysqli_query($resourceID, $feeQuery);
//if ticketing account, set default config for ticketing settings
if($inputs["type"] == 'ticketing'){
	$lblQuery = "
		INSERT INTO preferences (accountid,name,value) 
		VALUES (
			$insertId,'attendeeMenu',
			'{\"Event Home\":{\"label\":\"Event Home\",\"disabled\":false},\"Contact\":{\"label\":\"Contact\",\"disabled\":false},\"Register\":{\"label\":\"Purchase Tickets\",\"disabled\":false},\"Manage Registration\":{\"label\":\"Manage Order\",\"disabled\":false},\"Event Tickets\":{\"label\":\"Event Tickets\",\"disabled\":false}}'
		)
	";
	mysqli_query($resourceID, $lblQuery);

	$fldQuery = "
		INSERT INTO preferences (accountid,name,value) 
		VALUES (
			$insertId,'regScreenConfig',
			'{\"email\":{\"name\":\"Email\",\"hide\":false,\"label\":\"Email\"},\"first_name\":{\"name\":\"First Name\",\"hide\":false,\"label\":\"First Name\"},\"last_name\":{\"name\":\"Last Name\",\"hide\":false,\"label\":\"Last Name\"},\"title\":{\"name\":\"Position/Title\",\"hide\":true,\"label\":\"Position/Title\"},\"business\":{\"name\":\"Business\",\"hide\":true,\"label\":\"Business\"},\"address\":{\"name\":\"Business Address\",\"hide\":true,\"label\":\"Business Address\"},\"phone\":{\"name\":\"Business Phone\",\"hide\":true,\"label\":\"Business Phone\"},\"vendor_access\":{\"name\":\"Share Info With Vendors\",\"hide\":true,\"label\":\"Share my information with vendors\"},\"web_address\":{\"name\":\"Web Address\",\"hide\":true,\"label\":\"Web Address (Optional)\"},\"dietary_restrictions\":{\"name\":\"Dietary Restrictions\",\"hide\":true,\"label\":\"Dietary Restrictions\"},\"ec1\":{\"name\":\"Emergency Contact 1\",\"hide\":true,\"label\":\"Emergency Contact 1\"},\"ec2\":{\"name\":\"Emergency Contact 2\",\"hide\":true,\"label\":\"Emergency Contact 2\"}}'
		)
	";
	mysqli_query($resourceID, $fldQuery);
}

print($insertId);
mysqli_close($resourceID);
?>
