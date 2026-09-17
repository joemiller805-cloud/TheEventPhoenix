<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound page slug lookup
$inputs = sanitize_inputs($_REQUEST);
$sessionAccountId = (string)($_SESSION['accountid'] ?? '');
touch_session_activity(true); // Idle timer
$slug = (string)($inputs['slug'] ?? ''); // Bound
$accountId = (string)($inputs['accountid'] ?? ''); // Public event URL tenant
if (tep_session_has_principal()) { // Logged-in principal never switches tenant from the URL
	$accountId = (string)tep_session_accountid(); // Session only
}

try { // PDO first page for event; never interpolate slug/accountid
	$pdo = tep_dml_pdo(); // utf8mb4
	$stmt = $pdo->prepare('SELECT pages.slug
		FROM pages
		JOIN events ON pages.eventid = events.id
		WHERE events.slug = :slug
		AND (
			events.accountid = :session_accountid
			OR events.accountid = :accountid
			OR :accountid_empty = \'\'
		)
		ORDER BY pages.home DESC, pages.name ASC
		LIMIT 1'); // Bound
	$stmt->execute(array( // No concat
		'slug' => $slug, // Event slug
		'session_accountid' => $sessionAccountId, // Session tenant
		'accountid' => $accountId, // Request tenant
		'accountid_empty' => $accountId, // Empty-slug branch
	));
	$page = $stmt->fetch(PDO::FETCH_ASSOC); // One row or none
} catch (Throwable $evtEx) { // Connect
	error_log('TEP event.php page lookup failed: ' . $evtEx->getMessage()); // Log only
	$page = false; // Fall through to register
}

$eventPath = '/e/' . ($accountId !== '' ? $accountId . '/' : '') . $slug;
if ($accountId !== '' && !tep_session_has_principal()) { // Anonymous public event pages only
	$_SESSION['accountid'] = $accountId; // Do not overwrite a login
	touch_session_activity(true); // Idle
}
if (!$page) { // No CMS page
	header('Location:' . $eventPath . '/register');
} else {
	header('Location:' . $eventPath . '/' . $page['slug']);
}
