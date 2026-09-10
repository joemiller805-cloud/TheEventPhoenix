<?php session_start();
	include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php";
	touch_session_activity(true);
	$inputs = sanitize_inputs($_REQUEST);
	include "{$_SERVER['DOCUMENT_ROOT']}/data_access/queries.php";
	header('Content-Type: application/json');

	function json_error_response($statusCode, $message){
		http_response_code($statusCode);
		print json_encode(array("error" => $message));
	}

	function tep_is_poll_query($queryName) { // Live-poll endpoints stay HTTP 200 for dataSvc.rows and SW Network-First
		return ($queryName === 'getActivePoll' || $queryName === 'submitPollVote'); // Only these two names
	}

	function json_poll_rows($rows) { // Always HTTP 200 JSON {"rows":[...]} — never 400/500 for polls
		http_response_code(200); // Override any prior status so PWA cache and AngularJS see success
		print json_encode(array("rows" => is_array($rows) ? $rows : array())); // Same shape as every other getQueryResults success
	}

	function bind_stmt_params($stmt, $types, $params){
		if($types === '' || empty($params)) return true;
		$bindArgs = array($types);
		foreach($params as $key => $value){
			$bindArgs[] = &$params[$key];
		}
		return call_user_func_array(array($stmt, 'bind_param'), $bindArgs);
	}

	function execute_query_definition($resourceID, $queryDefinition){
		if(!is_array($queryDefinition)) return false;

		$stmt = mysqli_prepare($resourceID, $queryDefinition['sql']);
		if(!$stmt){
			error_log('TEP query prepare failed: ' . mysqli_error($resourceID));
			return false;
		}

		$types = $queryDefinition['types'] ?? '';
		$params = $queryDefinition['params'] ?? array();
		if(!bind_stmt_params($stmt, $types, $params)){
			error_log('TEP query bind failed for query.');
			mysqli_stmt_close($stmt);
			return false;
		}

		if(!mysqli_stmt_execute($stmt)){
			error_log('TEP query execute failed: ' . mysqli_stmt_error($stmt));
			mysqli_stmt_close($stmt);
			return false;
		}

		$resultID = mysqli_stmt_get_result($stmt);
		if($resultID === false && mysqli_stmt_errno($stmt)){
			error_log('TEP query result fetch failed: ' . mysqli_stmt_error($stmt));
		}
		mysqli_stmt_close($stmt);
		return $resultID;
	}

	$queryName = $inputs['query'] ?? '';
	$queryDefinition = $queries[$queryName] ?? null; // May be a prebuilt poll/eventData rows payload
	if (is_array($queryDefinition) && isset($queryDefinition['rows']) && is_array($queryDefinition['rows'])) { // Polls and empty-slug eventData skip mysqli
		print json_encode(array("rows" => $queryDefinition['rows'])); // HTTP 200 JSON for AngularJS dataSvc
		exit; // No database_connect — poll PDO already ran (or returned empty rows)
	}
	if (tep_is_poll_query($queryName)) { // Poll name with no rows payload still must not 400/500
		json_poll_rows(array()); // HTTP 200 {"rows":[]}
		exit; // Skip mysqli
	}

	$resourceID = database_connect(); // Existing queries still use mysqli prepared statements

	try{
		if($queryName === '' || !isset($queries[$queryName])){
			json_error_response(400, 'Unknown query.');
			mysqli_close($resourceID);
			exit;
		}

		$queryDefinition = $queries[$queryName];
		if(!is_array($queryDefinition)){
			error_log('TEP blocked legacy query definition for query: ' . $queryName);
			json_error_response(400, 'Query is not available.');
			mysqli_close($resourceID);
			exit;
		}

		$resultID = execute_query_definition($resourceID, $queryDefinition);
		if($resultID !== false){
			$response = array();
			while ($responseInfo = mysqli_fetch_assoc($resultID)){
				array_push($response, $responseInfo);
			}
			print json_encode(array("rows" => $response));
		}else{
			json_error_response(500, 'Query execution failed.');
		}
	}catch(Exception $e){
		error_log('TEP query exception for ' . $queryName . ': ' . $e->getMessage());
		json_error_response(500, 'Query execution error.');
	}
	mysqli_close($resourceID);
?>
