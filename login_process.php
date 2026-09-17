<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session + role helpers before any $_SESSION use
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound PDO; no concatenated SQL
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { // Logins are POST-only
	fail_request(405, 'Method not allowed.'); // Block GET credential harvest
}
tep_require_csrf_token(); // X-CSRF-Token from dataAccess.js / erSvc $http default
$inputs = sanitize_inputs($_REQUEST); // Trim only; values are bound below

$ip_key = 'login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown'); // Per-IP window in this session
$attempts = (int)($_SESSION[$ip_key]['count'] ?? 0); // After start_secure_session so the counter works
$window = (int)($_SESSION[$ip_key]['window'] ?? 0); // Window start
if (time() > $window + 900) { // 15-minute window reset
	$attempts = 0; // Fresh window
	$window = time(); // Now
}
if ($attempts >= 10) { // Too many failures
	$wait = (int)ceil(($window + 900 - time()) / 60); // Minutes remaining
	print('error_rate_limit:' . $wait); // Unchanged client contract
	exit; // Stop
}

$email = (string)($inputs['email'] ?? ''); // Bound
$accountId = (int)($inputs['accountid'] ?? 0); // Login-form tenant
$plain = (string)($inputs['pass'] ?? ''); // Plaintext for password_verify
if ($email === '' || $accountId < 1 || $plain === '') { // Incomplete form
	$_SESSION[$ip_key] = array('count' => $attempts + 1, 'window' => $window); // Count the miss
	print('error'); // Unchanged body
	exit; // Stop
}

try { // PDO login; never interpolate email/accountid
	$pdo = tep_dml_pdo(); // utf8mb4 + 2s timeout
	$sql = 'SELECT
			users.id,
			users.pass,
			REPLACE(users.first_name, \'"\', \'\') AS first_name,
			REPLACE(users.last_name, \'"\', \'\') AS last_name,
			users.email,
			users.master,
			users.accountid,
			accounts.trial,
			accounts.type,
			GROUP_CONCAT(DISTINCT COALESCE(security_groups.account_pages, \'x\'), \',\', COALESCE(security_groups.event_pages, \'x\')) AS pages,
			GROUP_CONCAT(DISTINCT user_event.eventid) AS events
		FROM users
		JOIN accounts ON accounts.id = users.accountid
		LEFT JOIN security_groups ON FIND_IN_SET(security_groups.id, users.security_groups)
		LEFT JOIN user_event ON user_event.userid = users.id
		WHERE LOWER(users.email) = LOWER(:email)
		AND users.accountid = :accountid
		AND COALESCE(users.archived, 0) != 1
		GROUP BY users.id, users.pass, users.first_name, users.last_name, users.email, users.master, users.accountid, accounts.trial, accounts.type'; // Hash verified in PHP
	$stmt = $pdo->prepare($sql); // Bound identity
	$stmt->execute(array( // No password in SQL
		'email' => $email, // Posted email
		'accountid' => $accountId, // Posted account from the login form
	));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC); // Zero or more (should be one)
} catch (Throwable $loginEx) { // Missing join tables on stub schemas
	error_log('TEP staff login join query failed: ' . $loginEx->getMessage()); // Log only
	try { // Fallback without security_groups / user_event
		$pdo = isset($pdo) ? $pdo : tep_dml_pdo(); // Reuse handle when possible
		$stmt = $pdo->prepare('SELECT users.id, users.pass,
			REPLACE(users.first_name, \'"\', \'\') AS first_name,
			REPLACE(users.last_name, \'"\', \'\') AS last_name,
			users.email, users.master, users.accountid, accounts.trial, accounts.type,
			\'x\' AS pages, NULL AS events
			FROM users JOIN accounts ON accounts.id = users.accountid
			WHERE LOWER(users.email) = LOWER(:email)
			AND users.accountid = :accountid
			AND COALESCE(users.archived, 0) != 1
			LIMIT 1'); // Stub schema without ACL tables
		$stmt->execute(array('email' => $email, 'accountid' => $accountId)); // Bound identity
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC); // Maybe one row
	} catch (Throwable $loginEx2) { // Connect still dead
		error_log('TEP staff login failed: ' . $loginEx2->getMessage()); // Log only
		print('error'); // Generic
		exit; // Stop
	}
}

$match = null; // Verified staff row
foreach ($rows as $cand) { // bcrypt or legacy crypt
	if (tep_password_verify($plain, (string)($cand['pass'] ?? ''))) { // password_verify
		$match = $cand; // First match
		break; // Done
	}
}
if (!$match) { // Bad credentials
	$_SESSION[$ip_key] = array('count' => $attempts + 1, 'window' => $window); // Real rate limit
	print('error'); // Unchanged body
	exit; // Stop
}
if (!tep_password_is_bcrypt((string)$match['pass'])) { // Legacy crypt
	tep_password_upgrade($pdo, 'UPDATE users SET pass = :pass WHERE id = :id AND accountid = :accountid', array( // Tenant-bound
		'pass' => tep_password_hash($plain), // bcrypt
		'id' => (int)$match['id'], // Staff
		'accountid' => (int)$match['accountid'], // From DB
	));
}
unset($_SESSION[$ip_key]); // Clear throttle on success
$row = $match; // Primary staff row
unset($row['pass']); // Never copy the hash into the session
tep_login_regenerate(TEP_ROLE_STAFF); // New session id; staff role
$_SESSION['last_activity'] = time(); // Idle timer
$_SESSION['roles'] = array(); // Existing key
$_SESSION['trial'] = $row['trial']; // Account trial flag
$_SESSION['acctType'] = $row['type']; // Account type
$_SESSION['accountid'] = $row['accountid']; // Tenant from DB, not the query string
$_SESSION['userid'] = $row['id']; // Staff principal
$_SESSION['useraccount'] = $row['accountid']; // Bound home account
$_SESSION['name'] = $row['first_name'] . ' ' . $row['last_name']; // Display
$_SESSION['master'] = $row['master']; // Master flag
$_SESSION['pageAccess'] = str_replace(',,', ',', (string)$row['pages']); // Pages CSV
$_SESSION['eventAccess'] = $row['events']; // Event ids
if ((string)$_SESSION['accountid'] === '') { // Empty tenant
	print('error acct'); // Unchanged contract
	exit; // Stop
}

$tableAccess = [ // Default tables every staff user may touch
	"user_event","sections","documents","document_association","course_proposals",
	"staff_expenses","expense_categories"
];
$accessMapping = [
	'acct_expense_rpt' => ['users'],
	'acct_reg_types' => ['account_reg_types'],
	'account_registrations' => ['registration_fields'],
	'admin_alerts' => ['users'],
	'course_catalog' => ['events_courses'],
	'course_management' => ['courses', 'users'],
	'document_management' => ['events', 'videos'],
	'email_history' => ['events', 'emails_sent'],
	'event_details' => ['events', 'account_reg_types'],
	'event_management' => ['events'],
	'evt_document_management' => ['users'],
	'evt_expense_rpt' => ['users'],
	'expense_management' => ['users', 'staff_payments'],
	'expense_pymt_methods' => ['preferences'],
	'import_event_data' => ['rooms', 'events_courses', 'sessions', 'sections', 'section_presenters'],
	'manage_page' => ['pages'],
	'manage_schedule' => [
		'section_sessions', 'sections', 'events', 'rooms',
		'section_presenters', 'security_groups', 'preferences'
	],
	'master_schedule' => ['event_master_sched_pages', 'events'],
	'registration_extras' => ['registration_extras'],
	'registration_form' => ['registration_fields'],
	'registration_messages' => ['registration_messages'],
	'registration_types' => ['registration_types', 'event_sponsor_options', 'account_reg_types'],
	'registrations' => ['signups', 'registrations', 'registration_payments'],
	'rooms' => ['rooms'],
	'rpt_video_orders' => ['video_orders'],
	'security_groups' => ['security_groups', 'users'],
	'sessions' => ['sessions'],
	'sponsor_types' => ['event_sponsor_options'],
	'survey_management' => ['survey_questions'],
	'survey_questions' => ['survey_evt_association', 'events'],
	'tracks' => ['tracks'],
	'users' => ['users', 'security_groups', 'emails_sent'],
	'vendor_management' => [
		'sponsors', 'vendor_orders', 'vendor_order_details', 'vendor_payments',
		'sponsor_packages', 'sponsor_payments', 'sponsor_pkg_opt_assoc',
		'sponsor_evt_association', 'sponsor_pending_pymts'
	],
	'video_mgmt' => [
		'videos', 'video_reviews', 'video_packages', 'video_package_details',
		'video_orders', 'video_order_details', 'video_association', 'preferences'
	]
];
$pages = explode(',', (string)$_SESSION['pageAccess']); // From GROUP_CONCAT
foreach ($accessMapping as $page => $accessItems) { // Existing page → table map
	if (in_array($page, $pages, true)) { // Staff may open this page
		foreach ($accessItems as $item) { // Tables for that page
			if (!in_array($item, $tableAccess, true)) { // Dedupe
				$tableAccess[] = $item; // Grant
			}
		}
	}
}
$_SESSION['tableAccess'] = implode(',', $tableAccess); // select_all / DML ACL
print('success'); // Unchanged AngularJS contract
