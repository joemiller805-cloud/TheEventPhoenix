<?php
include $_SERVER['DOCUMENT_ROOT'] . '/common_functions.php'; // CSRF helpers + DB constants before session_start
require_once $_SERVER['DOCUMENT_ROOT'] . '/data_access/tep_dml_pdo.php'; // Bound PDO parsers; no concatenated SQL
start_secure_session(); // SameSite=Lax + HTTPS-aware Secure
tep_require_csrf_token(); // POST body or X-CSRF-Token from dataAccess.js
touch_session_activity(true); // Existing idle timer
$openAccess = array('acme_access_requests'); // Unchanged public insert table
$userAccess = explode(',', (string)($_SESSION['tableAccess'] ?? '')); // Existing staff table list
$allUserAccess = array('documents', 'document_association', 'course_proposals', 'user_event'); // Existing unused list (kept)
$sponsorAcces = array('sponsors', 'sponsor_orders', 'sponsor_pending_pymts', 'sponsor_payments', 'course_proposals', 'vendor_order_details', 'vendor_orders'); // Existing sponsor tables
$inputs = sanitize_inputs($_REQUEST); // Trim only; values are bound below
$master = $_SESSION['master'] ?? ''; // Staff master flag
$tableName = tep_dml_ident($inputs['table'] ?? ''); // Existing table parameter; regex only
$command = strtolower((string)($inputs['command'] ?? '')); // insert | update from dataAccess.js
$updateRaw = str_replace("\\'", "'", (string)($inputs['updateData'] ?? '')); // Same unescape as the old path
$whereRaw = (string)($inputs['whereClause'] ?? ''); // Existing whereClause parameter
$assignments = ($command === 'update') ? tep_dml_parse_assignments($updateRaw) : array(); // Parsed SET list
$whereId = ($command === 'update') ? tep_dml_parse_where_id($whereRaw) : null; // id = N only
$authorized = false; // Same gates as before
if (!tep_session_has_principal() && $tableName !== 'acme_access_requests') { // Public ACME insert only; all other DML needs a login
	tep_dml_fail(401, 'Unauthorized'); // Standardized JSON 401
}
if ($tableName === null) { // Reject table=users;DROP
	$authorized = false; // Stay closed
} elseif ((string)$master === '1') { // Master staff
	$authorized = true; // Unchanged
} elseif (in_array($tableName, $userAccess, true) || in_array(strtolower($tableName), array_map('strtolower', $userAccess), true)) { // Session tableAccess
	$authorized = true; // Unchanged
} elseif (in_array($tableName, $openAccess, true)) { // ACME requests
	$authorized = true; // Unchanged
} elseif ($tableName === 'registration_extra_orders') { // Existing extra-orders exception
	$authorized = true; // Unchanged
}
if (!$authorized && $tableName !== null && in_array($tableName, $sponsorAcces, true)) { // Existing sponsor tables
	$sessionSponsor = (string)($_SESSION['sponsorid'] ?? ''); // Logged-in vendor
	if ($command === 'insert') { // Existing: any insert on sponsor tables
		$authorized = true; // Unchanged
	} elseif ($whereId !== null && $whereId === $sessionSponsor) { // Existing: WHERE id = sponsorid
		$authorized = true; // Parsed, not string-replace
	} else {
		foreach ($assignments as $pair) { // Existing: SET contains sponsorid = 'session'
			if (strtolower($pair['col']) === 'sponsorid' && isset($pair['value']['val']) && (string)$pair['value']['val'] === $sessionSponsor) { // Bound value, not strpos on SQL
				$authorized = true; // Unchanged intent
				break; // Done
			}
		}
	}
}
if (!$authorized && $tableName === 'users' && !empty($_SESSION['sponsorid'])) { // Existing sponsor user row
	$authorized = true; // Unchanged
}

if (!$authorized) { // Security Violation
	tep_dml_fail(403, 'Not Authorized'); // Same message; no SQL
}

if ($command !== 'insert' && $command !== 'update') { // Unknown command
	tep_dml_fail(400, 'Query Execution Error'); // Do not echo SQL
}

try { // PDO connect / parse / execute
	$pdo = tep_dml_pdo(); // Bound DML handle
	$liveCols = tep_dml_table_columns($pdo, $tableName); // information_schema whitelist
	if (empty($liveCols)) { // Unknown table
		tep_dml_fail(400, 'Query Execution Error'); // No SQL in the body
	}
	$quotedTable = '`' . $tableName . '`'; // Identifier from regex + schema
	if ($command === 'insert') { // dataAccess.js insertColumns + insertValues
		$colNames = tep_dml_parse_columns($inputs['insertColumns'] ?? ''); // Existing column parameter
		$valTokens = tep_dml_parse_value_list($inputs['insertValues'] ?? ''); // Existing values parameter
		if ($colNames === null || $valTokens === null || count($colNames) === 0 || count($colNames) !== count($valTokens)) { // Shape mismatch
			tep_dml_fail(400, 'Query Execution Error'); // Reject
		}
		$colSql = array(); // Backticked names
		$phSql = array(); // NOW() or :c0
		$bind = array(); // Placeholder => token
		foreach ($colNames as $idx => $col) { // Preserve column order from the client
			$colKey = strtolower($col); // Match information_schema
			if (!isset($liveCols[$colKey])) { // Column does not exist on this table
				tep_dml_fail(400, 'Query Execution Error'); // Reject extra/injected names
			}
			$realCol = $liveCols[$colKey]; // Actual spelling
			$colSql[] = '`' . $realCol . '`'; // Quoted identifier
			$token = $valTokens[$idx]; // Parallel value
			if (!empty($token['sql'])) { // now()
				$phSql[] = 'NOW()'; // SQL function; not concatenated user text
			} else {
				$ph = ':c' . $idx; // Named placeholder
				$phSql[] = $ph; // Bound value
				$bind[$ph] = $token; // Bind after prepare
			}
		}
		$sql = 'INSERT INTO ' . $quotedTable . ' (' . implode(',', $colSql) . ') VALUES (' . implode(',', $phSql) . ')'; // Identifiers from whitelist only
		$stmt = $pdo->prepare($sql); // PDO prepared statement
		foreach ($bind as $ph => $token) { // Bind each non-NOW value
			tep_dml_bind_token($stmt, $ph, $token); // PARAM_STR / PARAM_NULL
		}
		$stmt->execute(); // Insert
		print $pdo->lastInsertId(); // Same response as mysqli_insert_id for dataSvc.createOrUpdateRecord
	} else { // update
		if ($assignments === null || $whereId === null || count($assignments) === 0) { // Bad SET or WHERE
			tep_dml_fail(400, 'Query Execution Error'); // id = N only
		}
		if (!isset($liveCols['id'])) { // Table has no id column
			tep_dml_fail(400, 'Query Execution Error'); // Do not run a table-wide UPDATE
		}
		$setSql = array(); // `col` = :s0
		$bind = array(); // Placeholders
		foreach ($assignments as $idx => $pair) { // Preserve SET order
			$colKey = strtolower($pair['col']); // Requested column
			if (!isset($liveCols[$colKey])) { // Not a real column
				tep_dml_fail(400, 'Query Execution Error'); // Reject
			}
			$realCol = $liveCols[$colKey]; // Actual spelling
			$token = $pair['value']; // Parsed value
			if (!empty($token['sql'])) { // now()
				$setSql[] = '`' . $realCol . '` = NOW()'; // SQL function
			} else {
				$ph = ':s' . $idx; // Named placeholder
				$setSql[] = '`' . $realCol . '` = ' . $ph; // Bound
				$bind[$ph] = $token; // Bind later
			}
		}
		$sql = 'UPDATE ' . $quotedTable . ' SET ' . implode(', ', $setSql) . ' WHERE `id` = :id'; // Bound id only
		$stmt = $pdo->prepare($sql); // PDO prepared statement
		foreach ($bind as $ph => $token) { // SET values
			tep_dml_bind_token($stmt, $ph, $token); // Bound
		}
		$stmt->bindValue(':id', $whereId, PDO::PARAM_STR); // Existing whereClause id
		$stmt->execute(); // Update
		print $pdo->lastInsertId(); // 0 after UPDATE — same as mysqli_insert_id
	}
} catch (Throwable $dmlEx) { // Connect, parse, or execute failure
	error_log('TEP bound DML failed: ' . $dmlEx->getMessage()); // Log only
	tep_dml_fail(500, 'Query Execution Error'); // Never print SQL
}
