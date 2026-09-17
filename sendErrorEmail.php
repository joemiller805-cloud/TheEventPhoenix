<?php
	require_once __DIR__ . '/config/bootstrap.php'; // Secure session; no raw session_start
	if (!tep_session_has_principal()) { // Anonymous clients cannot mail session dumps
		http_response_code(401); // Fail closed
		print('error'); // Generic
		exit; // Stop
	}
	$inputs = sanitize_inputs($_REQUEST); // Trim
	$page = preg_replace('/[^A-Za-z0-9_\/.\-:?=&]/', '', (string)($inputs['page'] ?? '')); // Allow URL chars only
	error_log('TEP client error tenant=' . tep_session_accountid() . ' page=' . $page); // No session JSON, no posted stack, no mailbox dump
	print('ok'); // dataAccess.js does not require a body
