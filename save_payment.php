<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session; no raw session_start
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound INSERT
$inputs = sanitize_inputs($_REQUEST);
if (!isset($_SESSION['userid'])) { // Staff/entered_by required
	print('{"status": 0}'); // Generic; no SQL
	exit; // Stop
}
$userid = (int)$_SESSION['userid']; // Bound entered_by
touch_session_activity(true); // Idle timer

try { // Bound payment insert; registration must belong to session tenant
	$pdo = tep_dml_pdo(); // utf8mb4
	$regId = (int)($inputs['registrationid'] ?? 0); // Posted id
	$tenant = tep_session_accountid(); // Session only
	if ($regId < 1 || $tenant < 1) { // Missing
		print('{"status": 0}'); // Fail closed
		exit; // Stop
	}
	$own = $pdo->prepare('SELECT registrations.id FROM registrations JOIN events ON events.id = registrations.eventid WHERE registrations.id = :rid AND events.accountid = :accountid LIMIT 1'); // Tenant bound
	$own->execute(array('rid' => $regId, 'accountid' => $tenant)); // Session tenant
	if (!$own->fetchColumn()) { // IDOR
		print('{"status": 0}'); // Fail closed
		exit; // Stop
	}
	$stmt = $pdo->prepare('INSERT INTO registration_payments
		(registrationid, amount, payment_type, ref_nbr, entered_by, entered_date, note)
		VALUES (:registrationid, :amount, :payment_type, :ref_nbr, :entered_by, NOW(), :note)'); // Bound values
	$stmt->execute(array( // No string-built INSERT
		'registrationid' => $regId, // Owned registration
		'amount' => $inputs['amount'] ?? 0, // Posted amount
		'payment_type' => (string)($inputs['payment_type'] ?? ''), // Posted type
		'ref_nbr' => (string)($inputs['ref_nbr'] ?? ''), // Posted ref
		'entered_by' => $userid, // Session user
		'note' => (string)($inputs['note'] ?? ''), // Posted note
	));
	print('{"status": 1}'); // Unchanged success body
} catch (Throwable $payEx) { // Connect / constraint
	error_log('TEP save_payment failed: ' . $payEx->getMessage()); // Log only — never mysqli_error or SQL
	print('{"status": 0}'); // Generic JSON
}
