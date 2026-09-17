<?php
include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php"; // Cookie helpers before session_start
start_secure_session(); // SameSite=Lax + HTTPS-aware Secure
if (!tep_session_has_principal() && empty($_SESSION['accountid'])) { // Table dumps are never anonymous
	tep_json_fail(401, 'Unauthorized'); // Standardized JSON 401
}

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
$tepAllowedTables = array( // Explicit catalog; unknown names never reach SHOW COLUMNS / SELECT
	'account_fee_structure', 'account_reg_types', 'accounts', 'acme_access_requests', // Account admin
	'course_proposals', 'courses', 'discount_codes', 'document_association', 'documents', // Content
	'emails_sent', 'event_master_sched_pages', 'event_sponsor_options', 'events', 'events_courses', // Events
	'expense_categories', 'inventory', 'pages', 'preferences', 'registration_extra_orders', // Ops
	'registration_extras', 'registration_field_exclusions', 'registration_fields', 'registration_messages', // Register
	'registration_types', 'registrations', 'rooms', 'season_passes', 'sections', 'security_groups', // Event ops
	'sessions', 'signups', 'sponsor_orders', 'sponsor_packages', 'sponsor_payments', 'sponsor_pending_pymts', // Vendor
	'sponsor_pkg_opt_assoc', 'sponsors', 'support_tickets', 'survey_evt_association', 'survey_questions', // Surveys
	'survey_responses', 'user_event', 'users', 'vendor_order_details', 'vendor_orders', 'video_association', 'videos' // Users / video
);
$tepAllowedTables = array_values(array_unique(array_merge($tepAllowedTables, $allAccess, $sponsorAccess))); // Union ACL tables

$inputs = sanitize_inputs($_REQUEST);
$table = isset($inputs['table']) ? trim((string)$inputs['table']) : '';
$filter = isset($inputs['filter']) ? $inputs['filter'] : '';
$sponsor = $_SESSION['sponsorid'] ?? null;
$master = $_SESSION['master'] ?? '';
$ersupport = ($_SESSION['erSupport'] ?? '') === 'true';

if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
	out_json_error('Invalid table', 400);
}

if (!in_array($table, $tepAllowedTables, true)) { // Reject names not on the explicit catalog (including master)
	out_json_error('Invalid table', 400); // Never interpolate unknown identifiers
}

$authorized = false;
if ($master === '1') $authorized = true;
if ($ersupport) $authorized = true;
if (in_array($table, $userAccess, true)) $authorized = true;
if ($sponsor && in_array($table, $sponsorAccess, true)) $authorized = true;
if (tep_session_has_principal() && in_array($table, $allAccess, true)) $authorized = true; // allAccess no longer grants anonymous reads

if (!$authorized) {
	out_json_error('Not Authorized', 403);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/data_access/tep_dml_pdo.php'; // Bound SHOW COLUMNS / SELECT

try {
	$pdo = tep_dml_pdo(); // utf8mb4; $table already allowlisted + regex
	$validColumns = [];
	$columnResult = $pdo->query('SHOW COLUMNS FROM `' . $table . '`'); // Identifier from allowlist, not request concat
	if (!$columnResult) {
		throw new Exception('Unable to read table metadata');
	}

	while ($column = $columnResult->fetch(PDO::FETCH_ASSOC)) {
		$validColumns[$column['Field']] = true;
	}

	$where = build_where_clause($filter, $validColumns);
	if ($where === null) {
		out_json_error('Invalid filter. Supported format: column operator value joined by AND.', 400);
	}

	$sql = "SELECT * FROM `{$table}`" . $where['sql']; // Table allowlisted; WHERE uses ?
	$stmt = $pdo->prepare($sql); // Bound filter values
	$stmt->execute($where['params']); // No mysqli_query concat
	$response = $stmt->fetchAll(PDO::FETCH_ASSOC); // Rows
	print json_encode(["rows" => $response]);
} catch (Exception $e) {
	error_log('select_all_table_recs.php failed: ' . $e->getMessage());
	out_json_error('A database error occurred. Please try again.', 500);
}
?>
