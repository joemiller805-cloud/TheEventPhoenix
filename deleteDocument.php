<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session + roles
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // CSRF JSON helper + account lookup
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { // Writes are POST-only
	http_response_code(405); // Method not allowed
	echo 'error'; // Callers compare to success
	exit; // Stop
}
tep_require_csrf_token(); // X-CSRF-Token
if (!tep_session_can_manage_files()) { // Staff / support / sponsor only
	echo 'error'; // Not an attendee ticket
	exit; // Stop
}
$accountId = (int)($_SESSION['accountid'] ?? 0); // Logged-in tenant
$relative = tep_account_storage_relative((string)($_POST['document'] ?? $_REQUEST['document'] ?? ''), $accountId); // Jail
if ($relative === null || $accountId < 1) { // Traversal or wrong prefix
	echo 'error'; // Closed
	exit; // Stop
}

try { // Confirm the tenant exists
	$pdo = tep_dml_pdo(); // Bound PDO
	$acct = $pdo->prepare('SELECT id FROM accounts WHERE id = :id LIMIT 1'); // No concat
	$acct->execute(array('id' => $accountId)); // Session account
	if (!$acct->fetch(PDO::FETCH_ASSOC)) { // Unknown tenant
		echo 'error'; // Closed
		exit; // Stop
	}
} catch (Throwable $delEx) { // Connect
	error_log('TEP deleteDocument account lookup failed: ' . $delEx->getMessage()); // Log only
	echo 'error'; // Generic
	exit; // Stop
}

$root = realpath((string)$_SERVER['DOCUMENT_ROOT']); // Web root
if ($root === false) { // Misconfigured host
	echo 'error'; // Closed
	exit; // Stop
}
$fullPath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)); // Canonical
$rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR; // Prefix
if ($fullPath === false || strpos($fullPath, $rootPrefix) !== 0 || !is_file($fullPath)) { // Outside root or missing
	echo 'error'; // Closed
	exit; // Stop
}
$allowedRel = tep_account_storage_relative(str_replace('\\', '/', substr($fullPath, strlen($rootPrefix))), $accountId); // Re-check after realpath
if ($allowedRel === null) { // Escaped the jail
	echo 'error'; // Closed
	exit; // Stop
}
echo unlink($fullPath) ? 'success' : 'error'; // Same contract as before
