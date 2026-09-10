<?php
	include("common_functions.php");
	require_csrf_request();
	$inputs = sanitize_inputs($_REQUEST);
	$sessionUpdates = [];
	if($inputs['accountid']){
		$resourceID = database_connect();
		$query = "SELECT type, web_logo, sponsors_enabled FROM accounts WHERE id = '{$inputs['accountid']}'";
		$resultID = mysqli_query($resourceID, $query);
		if($resultID){
			$response = array();
			while ($responseInfo = mysqli_fetch_assoc($resultID)){
				array_push($response, $responseInfo);
				$sessionUpdates['accountid'] = $inputs['accountid'];
				$sessionUpdates['accountType'] = $responseInfo['type'];
				$sessionUpdates['accountLogo'] = $responseInfo['web_logo'];
				$sessionUpdates['sponsors_enabled'] = $responseInfo['sponsors_enabled'];
			}
		}
		mysqli_close($resourceID);
	}
	if($inputs['accountLogo']){
		$sessionUpdates['accountLogo'] = $inputs['accountLogo'];
	}
	if($inputs['sponsors_enabled']){
		$sessionUpdates['sponsors_enabled'] = $inputs['sponsors_enabled'];
	}
	if(!empty($sessionUpdates)){
		foreach($sessionUpdates as $sessionKey => $sessionValue){
			$_SESSION[$sessionKey] = $sessionValue;
		}
		touch_session_activity(true);
	}
 ?>
