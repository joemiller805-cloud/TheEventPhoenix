<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound INSERT
$inputs = sanitize_inputs($_REQUEST);
$isErSupport = $_SESSION['erSupport'] ?? '';
touch_session_activity(true); // Idle timer
if ($isErSupport != 'true') { // Support only
	header('Location: /login_er.php'); // Unchanged gate
	exit; // Stop
}

try { // Bound account + support user insert
	$pdo = tep_dml_pdo(); // utf8mb4
	$acctStmt = $pdo->prepare('INSERT INTO accounts (name, slug, contact_name, contact_email, contact_phone, type, trial, disabled, sis)
		VALUES (:name, :slug, :contact_name, :contact_email, :contact_phone, :type, :trial, :disabled, :sis)'); // Bound
	$acctStmt->execute(array( // No concat
		'name' => (string)($inputs['name'] ?? ''),
		'slug' => (string)($inputs['slug'] ?? ''),
		'contact_name' => (string)($inputs['contact_name'] ?? ''),
		'contact_email' => (string)($inputs['contact_email'] ?? ''),
		'contact_phone' => (string)($inputs['contact_phone'] ?? ''),
		'type' => (string)($inputs['type'] ?? ''),
		'trial' => (string)($inputs['trial'] ?? ''),
		'disabled' => (string)($inputs['disabled'] ?? ''),
		'sis' => (string)($inputs['sis'] ?? ''),
	));
	$insertId = (int)$pdo->lastInsertId(); // New account
	$userStmt = $pdo->prepare('INSERT INTO users (accountid, email, pass, first_name, last_name, master)
		VALUES (:accountid, :email, :pass, :first_name, :last_name, :master)'); // Bound support user
	$userStmt->execute(array( // Existing default support hash
		'accountid' => $insertId, // New tenant
		'email' => 'support@easyregpro.com', // Existing identity lookup — not display branding
		'pass' => 'PSDWAjFh.tAp6', // Existing crypt hash constant
		'first_name' => 'EasyReg', // Existing
		'last_name' => 'Support', // Existing
		'master' => 1, // Existing
	));
	print($insertId); // Unchanged body
} catch (Throwable $acctEx) { // Connect / constraint
	error_log('TEP create_account failed: ' . $acctEx->getMessage()); // Log only
	print('0'); // Generic
}
