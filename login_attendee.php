<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session before reading accountid
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound PDO
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { // No GET logins
	fail_request(405, 'Method not allowed.'); // Block credential harvest
}
tep_require_csrf_token(); // X-CSRF-Token header
$inputs = sanitize_inputs($_REQUEST); // Trim; bound below
touch_session_activity(true); // Idle timer
$sessionAccountId = (string)($_SESSION['accountid'] ?? ''); // Tenant already in session
$postedAccountId = (string)($inputs['accountid'] ?? ''); // Form field from seasonPasses
$accountId = $sessionAccountId; // Prefer session tenant
if ($accountId === '' && preg_match('/^[0-9]+$/', $postedAccountId)) { // Anonymous attendee login on a public page
	$accountId = $postedAccountId; // Bind from the form, not a hijack of staff
}
if (tep_session_is_staff()) { // Staff session must not switch tenant via this form
	$accountId = $sessionAccountId; // Keep useraccount
}
$email = (string)($inputs['email'] ?? ''); // Bound
$plain = (string)($_POST['pass'] ?? ''); // Plaintext for password_verify
if ($email === '' || $accountId === '' || $plain === '') { // Incomplete
	print('error'); // Unchanged body
	exit; // Stop
}

try { // Prepared attendee lookup — hash verified in PHP
	$pdo = tep_dml_pdo(); // utf8mb4
	$stmt = $pdo->prepare('SELECT id, password FROM attendees
		WHERE LOWER(email) = LOWER(:email)
		AND accountid = :accountid
		AND password IS NOT NULL
		AND password != \'\'
		LIMIT 1'); // Session tenant, not posted hash
	$stmt->execute(array( // Bound
		'email' => $email, // Posted email
		'accountid' => (int)$accountId, // Session tenant first
	));
	$row = $stmt->fetch(PDO::FETCH_ASSOC); // One attendee or none
} catch (Throwable $attEx) { // Missing table / connect
	error_log('TEP attendee login failed: ' . $attEx->getMessage()); // Log only
	print('error'); // Generic
	exit; // Stop
}

if (!$row || !tep_password_verify($plain, (string)$row['password'])) { // bcrypt or crypt
	print('error'); // Unchanged
	exit; // Stop
}
if (!tep_password_is_bcrypt((string)$row['password'])) { // Legacy crypt
	tep_password_upgrade($pdo, 'UPDATE attendees SET password = :pass WHERE id = :id AND accountid = :accountid', array( // Tenant-bound
		'pass' => tep_password_hash($plain), // bcrypt
		'id' => (int)$row['id'], // Attendee
		'accountid' => (int)$accountId, // Session tenant
	));
}

tep_login_regenerate(TEP_ROLE_ATTENDEE); // New session id; attendee role (not staff)
$_SESSION['attendeeid'] = $row['id']; // Attendee principal
$_SESSION['accountid'] = (int)$accountId; // Tenant from session or form
touch_session_activity(true); // Idle timer
print('success'); // Unchanged AngularJS contract
