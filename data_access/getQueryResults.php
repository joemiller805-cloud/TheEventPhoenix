<?php
	include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php"; // Cookie helpers before session_start
	start_secure_session(); // SameSite=Lax + HTTPS Secure instead of raw session_start
	touch_session_activity(true);
	$inputs = sanitize_inputs($_REQUEST);
	$queryName = $inputs['query'] ?? ''; // Needed before queries.php so poll PDO does not run unauthenticated
	if (tep_is_write_query($queryName)) { // submitPollVote / checkInAttendee / saveVendorLead / savePushSubscription
		if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { // Writes are POST-only
			tep_json_fail(405, 'Method not allowed.'); // Block GET mutations
		}
		tep_require_csrf_token(); // Strict X-CSRF-Token header
		if (!tep_session_has_principal()) { // accountid alone is not enough
			tep_json_fail(401, 'Unauthorized'); // Standardized JSON 401
		}
	} elseif (!tep_is_public_query($queryName) && !tep_session_has_principal()) { // Non-public SELECTs need a real login
		tep_json_fail(401, 'Unauthorized'); // Do not unlock on session accountid alone
	}
	include "{$_SERVER['DOCUMENT_ROOT']}/data_access/queries.php";
	header('Content-Type: application/json');

	function json_error_response($statusCode, $message){
		http_response_code($statusCode);
		print json_encode(array("error" => $message));
	}

	function tep_is_poll_query($queryName) { // Prebuilt PDO payloads stay HTTP 200 for dataSvc.rows and SW Network-First
		return ($queryName === 'getActivePoll' || $queryName === 'submitPollVote' || $queryName === 'savePushSubscription' || $queryName === 'getAttendeeCheckInStatus' || $queryName === 'checkInAttendee' || $queryName === 'getVendorStatus' || $queryName === 'saveVendorLead' || $queryName === 'getEventAnalytics'); // Poll, push, check-in, vendor ops, Event Pulse aggregates
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

		try { // PHP 8.2 mysqli throws mysqli_sql_exception on missing tables / bad SQL
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
		} catch (Throwable $sqlEx) { // Missing table, connection drop, or SQL error mid-prepare
			error_log('TEP query SQL exception: ' . $sqlEx->getMessage()); // Log only — no HTML 500
			return false; // Caller emits HTTP 200 empty rows
		}
	}

	$queryName = $inputs['query'] ?? '';
	$queryDefinition = $queries[$queryName] ?? null; // May be a prebuilt poll/push/check-in/eventData rows payload
	if (is_array($queryDefinition) && isset($queryDefinition['rows']) && is_array($queryDefinition['rows'])) { // Polls, push save, check-in, and empty-slug eventData skip mysqli
		print json_encode(array("rows" => $queryDefinition['rows'])); // HTTP 200 JSON for AngularJS dataSvc
		exit; // No database_connect — poll PDO already ran (or returned empty rows)
	}
	if (tep_is_poll_query($queryName)) { // Poll name with no rows payload still must not 400/500
		json_poll_rows(array()); // HTTP 200 {"rows":[]}
		exit; // Skip mysqli
	}

	try { // PHP 8.2 mysqli_real_connect throws mysqli_sql_exception when MySQL refuses the port
		$resourceID = database_connect(); // Existing queries still use mysqli prepared statements
	} catch (Throwable $connectEx) { // Connection refused / missing schema
		error_log('TEP query DB connect failed: ' . $connectEx->getMessage()); // Same fatal previously uncaught at line 73
		json_poll_rows(array()); // HTTP 200 empty rows so AngularJS dataSvc and hideLoading still run
		exit; // No mysqli handle to close
	}

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
			if ($queryName === 'checkAttendeeCredentials') { // Magic hash gone; verify in PHP
				$postedPw = (string)($inputs['password'] ?? ''); // Optional; empty = post-login lookup
				if ($postedPw !== '') { // Current-password check from seasonPasses / learningCenter
					$filtered = array(); // Rows that match
					foreach ($response as $credRow) { // Each attendee
						$stored = (string)($credRow['password'] ?? ''); // Column
						if (tep_password_verify($postedPw, $stored) || hash_equals($stored, $postedPw)) { // Plaintext bcrypt/crypt or leftover client crypt hash
							$filtered[] = $credRow; // Keep
						}
					}
					$response = $filtered; // Fail closed when no verify
				}
			}
			foreach ($response as $rowIdx => $rowOut) { // Never return credential columns to the browser
				if (is_array($rowOut)) { // Assoc row
					unset($response[$rowIdx]['password'], $response[$rowIdx]['pass']); // Strip hashes
				}
			}
			print json_encode(array("rows" => $response));
		}else{
			json_poll_rows(array()); // Missing table / SQL error: HTTP 200 empty rows, never 500
		}
	}catch(Throwable $e){
		error_log('TEP query exception for ' . $queryName . ': ' . $e->getMessage());
		json_poll_rows(array()); // HTTP 200 empty rows instead of an unhandled 500
	}
	mysqli_close($resourceID);
?>
