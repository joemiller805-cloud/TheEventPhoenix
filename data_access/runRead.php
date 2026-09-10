<?php
session_start();

include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php";
touch_session_activity(true);

header('Content-Type: application/json');

// if (!isset($_SESSION['userid'])) {
// 	http_response_code(403);
// 	print json_encode(["error" => "Unauthorized"]);
// 	exit;
// }

$inputs = sanitize_inputs($_REQUEST);
$resourceID = database_connect();

function out_json_error($message, $httpCode = 400) {
	http_response_code($httpCode);
	print json_encode(["error" => $message]);
	exit;
}

function in_str($inputs, $key, $default = '') {
	if (!isset($inputs[$key])) return $default;
	return trim((string)$inputs[$key]);
}

function in_int($inputs, $key, $default = 0) {
	if (!isset($inputs[$key]) || $inputs[$key] === '') return $default;
	return (int)$inputs[$key];
}

function fetch_all_stmt($resourceID, $sql, $types = '', $params = []) {
	$stmt = mysqli_prepare($resourceID, $sql);
	if (!$stmt) return false;

	if ($types !== '') {
		if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
			mysqli_stmt_close($stmt);
			return false;
		}
	}

	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		return false;
	}

	$result = mysqli_stmt_get_result($stmt);
	if ($result === false) {
		mysqli_stmt_close($stmt);
		return false;
	}

	$rows = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$rows[] = $row;
	}

	mysqli_free_result($result);
	mysqli_stmt_close($stmt);
	return $rows;
}

function has_account_override_access() {
	return
		(isset($_SESSION['master']) && $_SESSION['master'] === '1') ||
		(isset($_SESSION['erSupport']) && $_SESSION['erSupport'] === 'true');
}

function get_scoped_accountid($inputs) {
	$sessionAccountId = isset($_SESSION['accountid']) ? (int)$_SESSION['accountid'] : 0;
	$requestedAccountId = in_int($inputs, 'accountid', 0);

	if (has_account_override_access() && $requestedAccountId > 0) {
		return $requestedAccountId;
	}

	return $sessionAccountId;
}

$op = in_str($inputs, 'op');
$rows = false;

switch ($op) {
	case 'getPreferenceByName':
		$accountId = get_scoped_accountid($inputs);
		$name = in_str($inputs, 'name');
		if ($accountId <= 0 || $name === '') break;
		$rows = fetch_all_stmt(
			$resourceID,
			"SELECT * FROM preferences WHERE accountid = ? AND name = ?",
			"is",
			[$accountId, $name]
		);
		break;

	case 'getCoursesByAccount':
		$accountId = get_scoped_accountid($inputs);
		if ($accountId <= 0) break;
		$rows = fetch_all_stmt(
			$resourceID,
			"SELECT * FROM courses WHERE accountid = ?",
			"i",
			[$accountId]
		);
		break;

	case 'getVideosByAccount':
		$accountId = get_scoped_accountid($inputs);
		if ($accountId <= 0) break;
		$rows = fetch_all_stmt(
			$resourceID,
			"SELECT * FROM videos WHERE accountid = ?",
			"i",
			[$accountId]
		);
		break;

	case 'getUsersByAccount':
		$accountId = get_scoped_accountid($inputs);
		if ($accountId <= 0) break;
		$rows = fetch_all_stmt(
			$resourceID,
			"SELECT * FROM users WHERE accountid = ?",
			"i",
			[$accountId]
		);
		break;

	case 'getEventsByAccount':
		$accountId = get_scoped_accountid($inputs);
		if ($accountId <= 0) break;
		$rows = fetch_all_stmt(
			$resourceID,
			"SELECT * FROM events WHERE accountid = ?",
			"i",
			[$accountId]
		);
		break;

	case 'getEventCourses':
		$eventId = in_int($inputs, 'eventid');
		if ($eventId <= 0) break;
		$rows = fetch_all_stmt(
			$resourceID,
			"SELECT * FROM events_courses WHERE eventid = ?",
			"i",
			[$eventId]
		);
		break;

	default:
		out_json_error('Unsupported read operation', 400);
}

if ($rows === false) {
	http_response_code(400);
	print json_encode(["error" => "Read query failed"]);
} else {
	print json_encode(["rows" => $rows]);
}

mysqli_close($resourceID);
?>
