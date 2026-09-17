<?php
require_once __DIR__ . '/../config/bootstrap.php'; // Secure session + role helpers
require_once __DIR__ . '/../data_access/tep_dml_pdo.php'; // Bound PDO; no concatenated SQL
$inputs = sanitize_inputs($_REQUEST); // Trim; values are bound below
$sessionAccountId = (string)($_SESSION['accountid'] ?? ''); // Tenant already in session
touch_session_activity(true); // Idle timer
$username = (string)($inputs['username'] ?? ''); // Bound
$plain = (string)($inputs['pass'] ?? ''); // Plaintext for password_verify
if ($username === '' || $sessionAccountId === '' || $plain === '') { // Incomplete form
	print('error'); // Unchanged AngularJS body
	exit; // Stop
}

try { // PDO sponsor login; session tenant only
	$pdo = tep_dml_pdo(); // utf8mb4 + 2s timeout
	$stmt = $pdo->prepare('SELECT sponsors.id, sponsors.name, sponsors.accountid, sponsors.pass
		FROM sponsors
		WHERE LOWER(sponsors.username) = LOWER(:username)
		AND sponsors.accountid = :accountid
		LIMIT 1'); // Bound identity; hash in PHP
	$stmt->execute(array( // No password in SQL
		'username' => $username, // Posted username
		'accountid' => (int)$sessionAccountId, // Session tenant
	));
	$row = $stmt->fetch(PDO::FETCH_ASSOC); // One sponsor or none
} catch (Throwable $spEx) { // Connect / missing table
	error_log('TEP sponsor login failed: ' . $spEx->getMessage()); // Log only
	print('error'); // Generic
	exit; // Stop
}

if (!$row || !tep_password_verify($plain, (string)$row['pass'])) { // bcrypt or crypt
	print('error'); // Unchanged body
	exit; // Stop
}
if (!tep_password_is_bcrypt((string)$row['pass'])) { // Legacy crypt
	tep_password_upgrade($pdo, 'UPDATE sponsors SET pass = :pass WHERE id = :id AND accountid = :accountid', array( // Tenant-bound
		'pass' => tep_password_hash($plain), // bcrypt
		'id' => (int)$row['id'], // Sponsor
		'accountid' => (int)$row['accountid'], // From DB
	));
}
unset($row['pass']); // Never session-copy the hash

tep_login_regenerate(TEP_ROLE_SPONSOR); // New session id; vendor role
$_SESSION['roles'] = array(); // Existing key
$_SESSION['accountid'] = $row['accountid']; // Tenant from DB
$_SESSION['sponsorid'] = $row['id']; // Sponsor principal
$_SESSION['name'] = $row['name']; // Display
touch_session_activity(true); // Idle timer
print('success'); // Unchanged AngularJS contract
