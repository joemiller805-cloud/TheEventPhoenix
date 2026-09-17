<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { // No GET hash oracle
	fail_request(405, 'Method not allowed.'); // Block credential harvest
}
tep_require_csrf_token(); // X-CSRF-Token from $http defaults
if (!tep_session_has_principal()) { // Logged-in staff/attendee/sponsor/support only
	http_response_code(401); // Public oracle closed
	print('error'); // Generic
	exit; // Stop
}
$inputs = sanitize_inputs($_REQUEST); // Trim
$value = (string)($inputs['value'] ?? ''); // Password
$mode = (string)($inputs['mode'] ?? 'hash'); // hash | verify
if ($mode === 'verify') { // Current-password check; bcrypt is not comparable client-side
	require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound lookup
	$ok = false; // Fail closed
	try { // Session principal hash only
		$pdo = tep_dml_pdo(); // utf8mb4
		$role = tep_session_role(); // staff / attendee / sponsor / support
		if ($role === TEP_ROLE_ATTENDEE) { // Portal
			$stmt = $pdo->prepare('SELECT password AS h FROM attendees WHERE id = :id AND accountid = :accountid LIMIT 1'); // Tenant-bound
			$stmt->execute(array('id' => (int)($_SESSION['attendeeid'] ?? 0), 'accountid' => tep_session_accountid())); // Session
		} elseif ($role === TEP_ROLE_SPONSOR) { // Vendor
			$stmt = $pdo->prepare('SELECT pass AS h FROM sponsors WHERE id = :id AND accountid = :accountid LIMIT 1'); // Tenant-bound
			$stmt->execute(array('id' => (int)($_SESSION['sponsorid'] ?? 0), 'accountid' => tep_session_accountid())); // Session
		} else { // Staff / support
			$stmt = $pdo->prepare('SELECT pass AS h FROM users WHERE id = :id AND accountid = :accountid LIMIT 1'); // Tenant-bound
			$stmt->execute(array('id' => (int)($_SESSION['userid'] ?? 0), 'accountid' => tep_session_accountid())); // Session
		}
		$stored = (string)($stmt->fetchColumn() ?: ''); // Hash or empty
		$ok = tep_password_verify($value, $stored); // password_verify / crypt
	} catch (Throwable $verEx) { // Connect
		error_log('TEP er_encrypt verify failed: ' . $verEx->getMessage()); // Log only
		$ok = false; // Fail closed
	}
	print($ok ? '1' : '0'); // AngularJS compares to 1
	exit; // Stop
}
print tep_password_hash($value); // bcrypt for new password sets
