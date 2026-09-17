<?php
session_start();
require_once __DIR__ . '/common_functions.php';
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound UPDATE
$isErSupport = $_SESSION['erSupport'] ?? '';
touch_session_activity(true); // Idle timer
if ($isErSupport != 'true') { // Support only
	header('Location: /login_er.php');
	exit; // Stop
}
$inputs = sanitize_inputs($_REQUEST);
$accountId = (int)($inputs['accountid'] ?? 0); // Bound
if ($accountId < 1) { // Missing
	print('error');
	exit; // Stop
}

try { // Bound account update
	$pdo = tep_dml_pdo(); // utf8mb4
	$stmt = $pdo->prepare('UPDATE accounts SET
			name = :name,
			slug = :slug,
			contact_name = :contact_name,
			contact_email = :contact_email,
			contact_phone = :contact_phone,
			disabled = :disabled,
			trial = :trial,
			sis = :sis,
			type = :type,
			cc_charge_rt = :cc_charge_rt
		WHERE id = :accountid'); // Bound
	$stmt->execute(array( // No concat
		'name' => (string)($inputs['name'] ?? ''),
		'slug' => (string)($inputs['slug'] ?? ''),
		'contact_name' => (string)($inputs['contact_name'] ?? ''),
		'contact_email' => (string)($inputs['contact_email'] ?? ''),
		'contact_phone' => (string)($inputs['contact_phone'] ?? ''),
		'disabled' => (string)($inputs['disabled'] ?? '0'),
		'trial' => (string)($inputs['trial'] ?? '0'),
		'sis' => (string)($inputs['sis'] ?? ''),
		'type' => (string)($inputs['type'] ?? ''),
		'cc_charge_rt' => (string)($inputs['cc_charge_rt'] ?? ''),
		'accountid' => $accountId, // Posted tenant
	));
	print('success'); // Unchanged
} catch (Throwable $stEx) { // Connect
	error_log('TEP changeAcctStatus failed: ' . $stEx->getMessage()); // Log only
	print('error'); // Generic
}
