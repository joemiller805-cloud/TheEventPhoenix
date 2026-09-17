<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session before tenant switch
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound SELECT
$inputs = sanitize_inputs($_REQUEST);
$isErSupport = $_SESSION['erSupport'] ?? '';
touch_session_activity(true); // Idle timer
if ($isErSupport != 'true') { // Support login required
	header('Location: /login_er.php'); // Unchanged gate
	exit; // Stop
}
$accountId = (int)($inputs['accountid'] ?? 0); // Bound tenant
if ($accountId < 1) { // Missing target
	print('error'); // Unchanged body
	exit; // Stop
}

try { // PDO impersonation lookup; accountid bound
	$pdo = tep_dml_pdo(); // utf8mb4
	$stmt = $pdo->prepare("SELECT
			users.id,
			REPLACE(users.first_name,'\"','') AS first_name,
			REPLACE(users.last_name,'\"','') AS last_name,
			users.email,
			users.master,
			users.accountid,
			accounts.type
		FROM users
		JOIN accounts ON accounts.id = users.accountid
		WHERE LOWER(users.email) = LOWER(:email)
		AND users.accountid = :accountid
		LIMIT 1"); // Bound support email + tenant
	$stmt->execute(array( // No concatenated accountid
		'email' => 'support@easyregpro.com', // Existing support identity lookup — not display branding
		'accountid' => $accountId, // Posted tenant
	));
	$row = $stmt->fetch(PDO::FETCH_ASSOC); // One row or none
} catch (Throwable $erEx) { // Connect
	error_log('TEP ER account impersonation failed: ' . $erEx->getMessage()); // Log only
	print('error'); // Generic
	exit; // Stop
}

if (!$row) { // No support user on that account
	print('error'); // Unchanged
	exit; // Stop
}

tep_login_regenerate(TEP_ROLE_SUPPORT); // New session id on privilege/tenant escalation
$_SESSION['erSupport'] = 'true'; // Keep support flag after regenerate
$_SESSION['last_activity'] = time(); // Idle
$_SESSION['roles'] = array(); // Existing key
$_SESSION['accountid'] = $row['accountid']; // Tenant from DB
if ((string)$_SESSION['accountid'] === '') { // Empty tenant
	print('error acct'); // Unchanged contract
	exit; // Stop
}
$_SESSION['acctType'] = $row['type']; // Account type
$_SESSION['userid'] = $row['id']; // Support user id
$_SESSION['useraccount'] = $row['accountid']; // Home account
$_SESSION['name'] = $row['first_name'] . ' ' . $row['last_name']; // Display
$_SESSION['master'] = $row['master']; // Master flag
$_SESSION['pageAccess'] = ''; // Unchanged
$_SESSION['eventAccess'] = ''; // Unchanged
$_SESSION['tableAccess'] = ''; // Unchanged
touch_session_activity(true); // Idle timer
print('success'); // Unchanged AngularJS body
