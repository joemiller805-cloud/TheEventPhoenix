<?php
	include("common_functions.php");
	require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound account lookup
	require_csrf_request();
	$inputs = sanitize_inputs($_REQUEST);
	$sessionUpdates = [];
	if($inputs['accountid']){
		$postedTenant = (int)$inputs['accountid']; // Posted
		if (tep_session_is_attendee() || tep_session_role() === TEP_ROLE_SPONSOR) { // Cannot hop tenants
			$postedTenant = tep_session_accountid(); // Session only
		}
		if ($postedTenant > 0) {
		try { // PDO account row; never interpolate accountid
			$pdo = tep_dml_pdo(); // utf8mb4
			$stmt = $pdo->prepare('SELECT type, web_logo, sponsors_enabled FROM accounts WHERE id = :id LIMIT 1'); // Bound
			$stmt->execute(array('id' => $postedTenant)); // Staff/anonymous or locked session tenant
			$response = array();
			while ($responseInfo = $stmt->fetch(PDO::FETCH_ASSOC)) {
				array_push($response, $responseInfo);
				$sessionUpdates['accountid'] = (string)$postedTenant;
				$sessionUpdates['accountType'] = $responseInfo['type'];
				$sessionUpdates['accountLogo'] = $responseInfo['web_logo'];
				$sessionUpdates['sponsors_enabled'] = $responseInfo['sponsors_enabled'];
			}
		} catch (Throwable $setEx) { // Connect
			error_log('TEP set_session_account failed: ' . $setEx->getMessage()); // Log only
		}
		}
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
