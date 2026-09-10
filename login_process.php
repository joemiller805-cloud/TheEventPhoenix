<?php
include("common_functions.php");
$resourceID = database_connect();
$inputs = sanitize_inputs($_REQUEST);


// [SEC-9] Rate limiting: max 10 attempts per IP per 15 minutes
$ip_key = 'login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$attempts = $_SESSION[$ip_key]['count'] ?? 0;
$window   = $_SESSION[$ip_key]['window'] ?? 0;

if (time() > $window + 900) { // 15-minute window reset
    $attempts = 0;
    $window   = time();
}
if ($attempts >= 10) {
    $wait = ceil(($window + 900 - time()) / 60);
    print("error_rate_limit:$wait");
    exit;
}

$pass = crypt($inputs['pass'], LEGACY_SALT);

$query = "
	SELECT
		users.id,
		REPLACE(users.first_name,'\"',''),
		REPLACE(users.last_name,'\"',''),
		users.email,
		users.master,
		users.accountid,
		accounts.trial,
		accounts.type,
		GROUP_CONCAT(distinct COALESCE(security_groups.account_pages,'x'), ',', COALESCE(security_groups.event_pages,'x')) AS pages, 
		GROUP_CONCAT(distinct user_event.eventid) AS events
	FROM users
	JOIN accounts ON accounts.id = users.accountid
	LEFT JOIN security_groups ON find_in_set(security_groups.id, users.security_groups)
	LEFT JOIN user_event ON user_event.userid = users.id
	WHERE lower(users.email) = lower('{$inputs['email']}')
	AND users.accountid = {$inputs['accountid']}
	AND users.pass = '$pass'
	AND COALESCE(users.archived,0) != 1
";

$resultID = mysqli_query($resourceID, $query);
if (mysqli_num_rows($resultID)){
	session_unset();
	session_destroy();
	session_start();
	$_SESSION['last_activity'] = time();
	$_SESSION['roles'] = array();
	while($row = mysqli_fetch_assoc($resultID))	{
		$_SESSION['trial'] = $row['trial'];
		$_SESSION['acctType'] = $row['type'];
		$_SESSION['accountid'] = $row['accountid'];
		if($_SESSION['accountid'] == '') print ('error acct');
		$_SESSION['userid'] = $row['id'];
		$_SESSION['useraccount'] = $row['accountid'];
		$_SESSION['name'] = $row['first_name']. " ". $row['last_name'];
		$_SESSION['master'] = $row['master'];
		$_SESSION['pageAccess'] = str_replace(",,", ",", $row['pages']);
		$_SESSION['eventAccess'] = $row['events'];
	}

	//initialize accessible tables arry with default tables that all user have access to
	$tableAccess = [
		"user_event","sections","documents","document_association","course_proposals",
		"staff_expenses","expense_categories"
	];
	print("success");

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

	$pages = explode(',', $_SESSION['pageAccess']);
	foreach ($accessMapping as $page => $accessItems) {
		if (in_array($page, $pages)) {
			foreach ($accessItems as $item) {
				if (!in_array($item, $tableAccess)) {
					$tableAccess[] = $item;
				}
			}
		}
	}

	$_SESSION['tableAccess'] = implode(",",$tableAccess);
}else{
	print("error");
}
mysqli_close($resourceID);
?>