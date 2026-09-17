<?php
session_start(); // Existing cookie session before common_functions
include $_SERVER['DOCUMENT_ROOT'] . '/common_functions.php'; // DB constants + session helpers
require_once $_SERVER['DOCUMENT_ROOT'] . '/data_access/tep_dml_pdo.php'; // Bound PDO; no concatenated SQL
touch_session_activity(true); // Existing idle timer
$sponsorAccess = array('sponsor_orders', 'registrations', 'sponsor_pending_pymts', 'vendor_orders', 'vendor_order_details'); // Existing sponsor tables
$userAccess = explode(',', (string)($_SESSION['tableAccess'] ?? '')); // Existing staff table list
$inputs = sanitize_inputs($_REQUEST); // Trim; id is bound below
$master = $_SESSION['master'] ?? ''; // Staff master flag
$sponsor = $_SESSION['sponsorid'] ?? null; // Vendor session
$tableName = tep_dml_ident($inputs['table'] ?? ''); // Existing table parameter; regex only
$rowId = (string)($inputs['id'] ?? ''); // Existing id parameter
if ($rowId === '' || !preg_match('/^[0-9]+$/', $rowId)) { // Digits only; reject id=1 OR 1=1
	$rowId = null; // Force unauthorized / bad request
}
$authorized = false; // Same gates as before
if ($tableName === null || $rowId === null) { // Bad identifiers
	$authorized = false; // Stay closed
} elseif ((string)$master === '1') { // Master staff
	$authorized = true; // Unchanged
} elseif (in_array($tableName, $userAccess, true) || in_array(strtolower($tableName), array_map('strtolower', $userAccess), true)) { // Session tableAccess
	$authorized = true; // Unchanged
} elseif ($sponsor && in_array($tableName, $sponsorAccess, true)) { // Existing sponsor delete list
	$authorized = true; // Unchanged
} elseif ($tableName === 'registration_extra_orders') { // Existing extra-orders exception
	$authorized = true; // Unchanged
} elseif ($tableName === 'registrations') { // Existing registrations exception
	$authorized = true; // Unchanged
}

if (!$authorized) { // Security Violation
	tep_dml_fail(403, 'Not Authorized'); // Same message; no SQL
}

try { // PDO connect / execute
	$pdo = tep_dml_pdo(); // Bound DML handle
	$liveCols = tep_dml_table_columns($pdo, $tableName); // Table must exist
	if (empty($liveCols) || !isset($liveCols['id'])) { // Unknown table or no id column
		tep_dml_fail(400, 'Query Execution Error'); // Do not run DELETE without id
	}
	$sql = 'DELETE FROM `' . $tableName . '` WHERE `id` = :id'; // Identifier from regex + schema; id bound
	$stmt = $pdo->prepare($sql); // PDO prepared statement
	$stmt->bindValue(':id', $rowId, PDO::PARAM_STR); // Existing id parameter
	$stmt->execute(); // Delete one row
	print $pdo->lastInsertId(); // 0 after DELETE — same as mysqli_insert_id for dataSvc.deleteRecord
} catch (Throwable $delEx) { // Connect or execute failure
	error_log('TEP bound DELETE failed: ' . $delEx->getMessage()); // Log only
	tep_dml_fail(500, 'Query Execution Error'); // Never print SQL
}
