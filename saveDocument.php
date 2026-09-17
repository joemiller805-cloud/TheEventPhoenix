<?php
ini_set('upload_max_filesize', '900M'); // Existing upload cap
ini_set('post_max_size', '900M'); // Existing POST cap
ini_set('max_input_time', 1000); // Existing
ini_set('max_execution_time', 1000); // Existing
require_once __DIR__ . '/config/bootstrap.php'; // Secure session + roles
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Account lookup + CSRF helper
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { // Uploads are POST
	http_response_code(405); // Method not allowed
	echo 'error'; // Callers compare to success
	exit; // Stop
}
tep_require_csrf_token(); // X-CSRF-Token from $http defaults (FormData still sends common headers)
if (!tep_session_can_manage_files()) { // Staff / support / sponsor
	echo 'error'; // Attendees cannot write account storage
	exit; // Stop
}
$accountId = (int)($_SESSION['accountid'] ?? 0); // Logged-in tenant
$location = tep_account_storage_relative((string)($_POST['location'] ?? ''), $accountId); // Jail the folder
if ($location === null || $accountId < 1) { // Wrong account or traversal
	echo 'error'; // Closed
	exit; // Stop
}

try { // Confirm tenant
	$pdo = tep_dml_pdo(); // Bound PDO
	$acct = $pdo->prepare('SELECT id FROM accounts WHERE id = :id LIMIT 1'); // No concat
	$acct->execute(array('id' => $accountId)); // Session account
	if (!$acct->fetch(PDO::FETCH_ASSOC)) { // Unknown
		echo 'error'; // Closed
		exit; // Stop
	}
} catch (Throwable $upEx) { // Connect
	error_log('TEP saveDocument account lookup failed: ' . $upEx->getMessage()); // Log only
	echo 'error'; // Generic
	exit; // Stop
}

$root = (string)$_SERVER['DOCUMENT_ROOT']; // Web root (folder may not exist yet)
$destDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $location); // Intended directory
if (!is_dir($destDir)) { // First upload into this folder
	if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) { // Create account folder only
		echo 'error'; // Cannot write
		exit; // Stop
	}
}
$baseName = isset($_POST['fileName']) ? basename((string)$_POST['fileName']) : basename((string)($_FILES['document']['name'] ?? '')); // Filename only
if ($baseName === '' || strpos($baseName, '..') !== false) { // Empty or traversal
	echo 'error'; // Closed
	exit; // Stop
}
$destFile = $destDir . DIRECTORY_SEPARATOR . $baseName; // Final path
$resolvedDir = realpath($destDir); // Canonical dir
$rootReal = realpath($root); // Canonical root
if ($resolvedDir === false || $rootReal === false || strpos($resolvedDir, rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) { // Jail after mkdir
	echo 'error'; // Closed
	exit; // Stop
}
if (!isset($_FILES['document']['tmp_name']) || !is_uploaded_file($_FILES['document']['tmp_name'])) { // Not an upload
	echo 'error'; // Closed
	exit; // Stop
}
echo move_uploaded_file($_FILES['document']['tmp_name'], $destFile) ? 'success' : 'error'; // Same contract
