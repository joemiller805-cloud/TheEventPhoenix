<?php
session_start();
require_once __DIR__ . '/common_functions.php';
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound fee DML
$isErSupport = $_SESSION['erSupport'] ?? '';
touch_session_activity(true); // Idle timer
if ($isErSupport != 'true') { // Support only
	header('Location: /login_er.php');
	exit; // Stop
}
$inputs = sanitize_inputs($_REQUEST);

try { // Bound INSERT or UPDATE
	$pdo = tep_dml_pdo(); // utf8mb4
	$feeId = (int)($inputs['id'] ?? 0); // Existing row
	if ($feeId > 0) { // Update
		$stmt = $pdo->prepare('UPDATE account_fee_structure SET
				reg_fee = :reg_fee,
				reg_fee_method = :reg_fee_method,
				reg_charge_to = :reg_charge_to,
				reg_charge_zero_items = :reg_charge_zero_items,
				ticket_fee = :ticket_fee,
				ticket_fee_method = :ticket_fee_method,
				ticket_charge_to = :ticket_charge_to,
				ticket_charge_zero_items = :ticket_charge_zero_items,
				start_date = :start_date,
				end_date = :end_date
			WHERE id = :id'); // Bound
		$stmt->execute(array( // No concat
			'reg_fee' => (string)($inputs['reg_fee'] ?? ''),
			'reg_fee_method' => (string)($inputs['reg_fee_method'] ?? ''),
			'reg_charge_to' => (string)($inputs['reg_charge_to'] ?? ''),
			'reg_charge_zero_items' => (string)($inputs['reg_charge_zero_items'] ?? '0'),
			'ticket_fee' => (string)($inputs['ticket_fee'] ?? ''),
			'ticket_fee_method' => (string)($inputs['ticket_fee_method'] ?? ''),
			'ticket_charge_to' => (string)($inputs['ticket_charge_to'] ?? ''),
			'ticket_charge_zero_items' => (string)($inputs['ticket_charge_zero_items'] ?? '0'),
			'start_date' => (string)($inputs['start_date'] ?? ''),
			'end_date' => (string)($inputs['end_date'] ?? ''),
			'id' => $feeId, // Posted id
		));
		print($feeId); // Unchanged
	} else { // Insert
		$stmt = $pdo->prepare('INSERT INTO account_fee_structure (
				reg_fee, reg_fee_method, reg_charge_to, reg_charge_zero_items,
				ticket_fee, ticket_fee_method, ticket_charge_to, ticket_charge_zero_items,
				start_date, end_date, accountid
			) VALUES (
				:reg_fee, :reg_fee_method, :reg_charge_to, :reg_charge_zero_items,
				:ticket_fee, :ticket_fee_method, :ticket_charge_to, :ticket_charge_zero_items,
				:start_date, :end_date, :accountid
			)'); // Bound
		$stmt->execute(array( // No concat
			'reg_fee' => (string)($inputs['reg_fee'] ?? ''),
			'reg_fee_method' => (string)($inputs['reg_fee_method'] ?? ''),
			'reg_charge_to' => (string)($inputs['reg_charge_to'] ?? ''),
			'reg_charge_zero_items' => (string)($inputs['reg_charge_zero_items'] ?? '0'),
			'ticket_fee' => (string)($inputs['ticket_fee'] ?? ''),
			'ticket_fee_method' => (string)($inputs['ticket_fee_method'] ?? ''),
			'ticket_charge_to' => (string)($inputs['ticket_charge_to'] ?? ''),
			'ticket_charge_zero_items' => (string)($inputs['ticket_charge_zero_items'] ?? '0'),
			'start_date' => (string)($inputs['start_date'] ?? ''),
			'end_date' => (string)($inputs['end_date'] ?? ''),
			'accountid' => (string)($inputs['accountid'] ?? ''),
		));
		print($pdo->lastInsertId()); // Unchanged
	}
} catch (Throwable $feeEx) { // Connect
	error_log('TEP updateAcctFee failed: ' . $feeEx->getMessage()); // Log only
	print('0'); // Generic
}
