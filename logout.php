<?php
include("common_functions.php");
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound slug lookup
require_csrf_request();
$slug = array('slug' => ''); // Default empty
try { // PDO account slug; never interpolate session accountid
	$pdo = tep_dml_pdo(); // utf8mb4
	$accountId = (int)($_SESSION['accountid'] ?? 0); // Session tenant
	if ($accountId > 0) { // Have a tenant
		$stmt = $pdo->prepare('SELECT slug FROM accounts WHERE id = :id LIMIT 1'); // Bound
		$stmt->execute(array('id' => $accountId)); // Session id
		$row = $stmt->fetch(PDO::FETCH_ASSOC); // One row
		if ($row) { // Found
			$slug = $row; // Keep key slug
		}
	}
} catch (Throwable $outEx) { // Connect
	error_log('TEP logout slug lookup failed: ' . $outEx->getMessage()); // Log only
}
session_unset();
session_destroy();
$redirectTarget = "/login.php/{$slug['slug']}";
if (!empty($_POST['r']) && substr((string)$_POST['r'], 0, 1) === '/') {
	$redirectTarget .= '?r=' . urlencode((string)$_POST['r']);
}
header("Location:{$redirectTarget}");
