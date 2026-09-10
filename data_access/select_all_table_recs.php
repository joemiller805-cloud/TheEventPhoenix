<?php
session_start();

include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php";

touch_session_activity(true);

header('Content-Type: application/json');

function out_json_error($message, $httpCode = 400) {
	http_response_code($httpCode);
	print json_encode(["error" => $message]);
	exit;
}

function parse_filter_value($rawValue, &$sqlOperator) {
	$value = trim((string)$rawValue);

	if (preg_match('/^(null)$/i', $value)) {
		if ($sqlOperator === '=') $sqlOperator = 'IS';
		if ($sqlOperator === '!=' || $sqlOperator === '<>') $sqlOperator = 'IS NOT';
		return ["uses_param" => false, "sql_value" => "NULL"];
	}

	if (preg_match('/^(true|false)$/i', $value)) {
		return [
			"uses_param" => true,
			"type" => "i",
			"value" => strtolower($value) === 'true' ? 1 : 0
		];
	}

	if (preg_match('/^-?\d+$/', $value)) {
		return ["uses_param" => true, "type" => "i", "value" => (int)$value];
	}

	if (preg_match('/^-?(?:\d+\.\d*|\d*\.\d+)$/', $value)) {
		return ["uses_param" => true, "type" => "d", "value" => (float)$value];
	}

	if (
		(preg_match("/^'(.*)'$/s", $value, $matches)) === 1 ||
		(preg_match('/^"(.*)"$/s', $value, $matches)) === 1
	) {
		$unescaped = str_replace(["\\'", '\\"', "\\\\"], ["'", '"', "\\"], $matches[1]);
		return ["uses_param" => true, "type" => "s", "value" => $unescaped];
	}

	return null;
}

function build_where_clause($filter, $validColumns) {
	$filter = trim((string)$filter);
	if ($filter === '') return ["sql" => '', "types" => '', "params" => []];
	if (preg_match('/\bOR\b/i', $filter)) return null;

	$clauses = preg_split('/\s+AND\s+/i', $filter);
	if (!$clauses) return null;

	$whereParts = [];
	$types = '';
	$params = [];

	foreach ($clauses as $clause) {
		$clause = trim($clause);
		if ($clause === '') return null;

		if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*(IS\s+NOT|IS|=|!=|<>|>=|<=|>|<)\s*(.+)$/is', $clause, $matches)) {
			return null;
		}

		$column = $matches[1];
		$operator = strtoupper(preg_replace('/\s+/', ' ', $matches[2]));
		$valueData = parse_filter_value($matches[3], $operator);

		if (!isset($validColumns[$column]) || $valueData === null) return null;
		if (($operator === 'IS' || $operator === 'IS NOT') && $valueData["uses_param"]) return null;
		if (($operator !== 'IS' && $operator !== 'IS NOT') && !$valueData["uses_param"]) return null;

		if ($valueData["uses_param"]) {
			$whereParts[] = "`{$column}` {$operator} ?";
			$types .= $valueData["type"];
			$params[] = $valueData["value"];
		} else {
			$whereParts[] = "`{$column}` {$operator} {$valueData['sql_value']}";
		}
	}

	return [
		"sql" => ' WHERE ' . implode(' AND ', $whereParts),
		"types" => $types,
		"params" => $params
	];
}

$allAccess = ["user_event","courses","events_courses","sessions","sections","documents","document_association","registrations","signups","event_master_sched_pages","registration_messages","survey_questions","survey_evt_association","survey_responses","sponsors","preferences","registration_field_exclusions"];
$sponsorAccess = ["event_sponsor_options","sponsor_orders","registration_types","sponsor_payments","sponsors","users","events","sponsor_packages","sponsor_pkg_opt_assoc","sponsor_pending_pymts","vendor_orders"];
$userAccess = isset($_SESSION['tableAccess']) ? explode(',', $_SESSION['tableAccess']) : [];

$inputs = sanitize_inputs($_REQUEST);
$table = isset($inputs['table']) ? trim((string)$inputs['table']) : '';
$filter = isset($inputs['filter']) ? $inputs['filter'] : '';
$sponsor = $_SESSION['sponsorid'] ?? null;
$master = $_SESSION['master'] ?? '';
$ersupport = ($_SESSION['erSupport'] ?? '') === 'true';

if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
	out_json_error('Invalid table', 400);
}

$authorized = false;
if ($master === '1') $authorized = true;
if ($ersupport) $authorized = true;
if (in_array($table, $userAccess, true)) $authorized = true;
if ($sponsor && in_array($table, $sponsorAccess, true)) $authorized = true;
if (in_array($table, $allAccess, true)) $authorized = true;

if (!$authorized) {
	out_json_error('Not Authorized', 403);
}

$resourceID = database_connect();

try {
	$validColumns = [];
	$columnResult = mysqli_query($resourceID, "SHOW COLUMNS FROM `{$table}`");
	if (!$columnResult) {
		throw new Exception('Unable to read table metadata');
	}

	while ($column = mysqli_fetch_assoc($columnResult)) {
		$validColumns[$column['Field']] = true;
	}
	mysqli_free_result($columnResult);

	$where = build_where_clause($filter, $validColumns);
	if ($where === null) {
		out_json_error('Invalid filter. Supported format: column operator value joined by AND.', 400);
	}

	$sql = "SELECT * FROM `{$table}`" . $where['sql'];
	$stmt = mysqli_prepare($resourceID, $sql);
	if (!$stmt) {
		throw new Exception('Unable to prepare statement');
	}

	if ($where['types'] !== '') {
		if (!mysqli_stmt_bind_param($stmt, $where['types'], ...$where['params'])) {
			mysqli_stmt_close($stmt);
			throw new Exception('Unable to bind statement parameters');
		}
	}

	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		throw new Exception('Unable to execute statement');
	}

	$resultID = mysqli_stmt_get_result($stmt);
	if ($resultID === false) {
		mysqli_stmt_close($stmt);
		throw new Exception('Unable to fetch statement results');
	}

	$response = [];
	while ($responseInfo = mysqli_fetch_assoc($resultID)) {
		$response[] = $responseInfo;
	}

	mysqli_free_result($resultID);
	mysqli_stmt_close($stmt);
	print json_encode(["rows" => $response]);
} catch (Exception $e) {
	error_log('select_all_table_recs.php failed: ' . $e->getMessage());
	out_json_error('A database error occurred. Please try again.', 500);
} finally {
	mysqli_close($resourceID);
}
?>
