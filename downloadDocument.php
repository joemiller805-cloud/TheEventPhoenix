<?php
session_start();
include("common_functions.php");

function download_error($message, $httpCode) {
	http_response_code($httpCode);
	die($message);
}

function fetch_one($db, $sql, $types = "", $params = []) {
	$stmt = mysqli_prepare($db, $sql);
	if (!$stmt) return null;
	if ($types) {
		$bindParams = [$types];
		foreach ($params as $key => $value) {
			$bindParams[] = &$params[$key];
		}
		call_user_func_array([$stmt, 'bind_param'], $bindParams);
	}
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	return $result ? mysqli_fetch_assoc($result) : null;
}

function has_row($db, $sql, $types = "", $params = []) {
	return fetch_one($db, $sql, $types, $params) !== null;
}

function user_can_download_document($db, $document, $eventId) {
	$sessionAccountId = (int)($_SESSION['accountid'] ?? 0);
	$isMaster = ($_SESSION['master'] ?? '') === '1';
	$isSupport = ($_SESSION['erSupport'] ?? '') === 'true';

	if (($isMaster || $isSupport) && $sessionAccountId === (int)$document['accountid']) return true;

	if (!$eventId) return false;

	$event = fetch_one(
		$db,
		"SELECT id, accountid FROM events WHERE id = ?",
		"i",
		[$eventId]
	);

	if (!$event || (int)$event['accountid'] !== (int)$document['accountid']) return false;

	if ($document['category'] === 'account') return true;

	$isEventDocument = has_row(
		$db,
		"SELECT id
		FROM document_association
		WHERE documentid = ?
		AND accountid = ?
		AND eventid = ?
		AND (courseid IS NULL OR courseid = 0)
		LIMIT 1",
		"iii",
		[(int)$document['id'], (int)$document['accountid'], $eventId]
	);

	if ($isEventDocument) return true;

	if ($document['category'] !== 'course') return false;

	$registrationId = (int)($_SESSION['registrationid'] ?? 0);
	if (!$registrationId) return false;

	$registeredForEvent = has_row(
		$db,
		"SELECT id
		FROM registrations
		WHERE id = ?
		AND eventid = ?
		AND COALESCE(deleted, 0) != 1
		LIMIT 1",
		"ii",
		[$registrationId, $eventId]
	);

	if (!$registeredForEvent) return false;

	return has_row(
		$db,
		"SELECT da.id
		FROM document_association da
		JOIN events_courses ec ON ec.courseid = da.courseid
		WHERE da.documentid = ?
		AND da.accountid = ?
		AND ec.eventid = ?
		LIMIT 1",
		"iii",
		[(int)$document['id'], (int)$document['accountid'], $eventId]
	);
}

$inputs = sanitize_inputs($_REQUEST);
$documentId = (int)($inputs['id'] ?? 0);
$eventId = (int)($inputs['eventid'] ?? 0);

if (!$documentId) download_error("Missing document.", 400);

$db = database_connect();
$document = fetch_one(
	$db,
	"SELECT id, accountid, category, filepath
	FROM documents
	WHERE id = ?
	AND COALESCE(archived, 0) != 1",
	"i",
	[$documentId]
);

if (!$document) download_error("Document not found.", 404);
if (!user_can_download_document($db, $document, $eventId)) {
	download_error("Not authorized.", 403);
}

$relativePath = ltrim(str_replace("\\", "/", $document['filepath']), "/");
$rootPath = realpath($_SERVER['DOCUMENT_ROOT']);
$fullPath = realpath($rootPath . "/" . $relativePath);
$rootPrefix = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (!$fullPath || strpos($fullPath, $rootPrefix) !== 0 || !is_file($fullPath)) {
	download_error("File not found.", 404);
}

$filename = basename($fullPath);
$fallbackName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
$encodedName = rawurlencode($filename);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeType = false;
if ($extension !== 'pdf' && function_exists('mime_content_type')) {
	$mimeType = mime_content_type($fullPath);
}
if (!$mimeType) $mimeType = "application/octet-stream";

while (ob_get_level()) ob_end_clean();

header("Content-Type: $mimeType");
header("Content-Disposition: attachment; filename=\"$fallbackName\"; filename*=UTF-8''$encodedName");
header("Content-Length: " . filesize($fullPath));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private");

readfile($fullPath);
exit;
