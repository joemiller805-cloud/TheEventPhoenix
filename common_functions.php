<?php
$paths = [
    dirname(__DIR__,1) . '/private/tep_config.php',
    dirname(__DIR__, 2) . '/private/tep_config.php',
];

foreach ($paths as $p) {
    if (is_file($p)) {
        require_once $p;
        break;
    }
}
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

apply_security_headers();

function apply_security_headers() {
	if (headers_sent()) {
		return;
	}
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: SAMEORIGIN');
	header('Referrer-Policy: strict-origin-when-cross-origin');
}

function is_https_request() {
	if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
		return true;
	}
	if (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
		return true;
	}
	if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
		return true;
	}
	return false;
}

function tep_is_local_host() { // Local XAMPP detector used by DB fallbacks and the dev session helper
	$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? '')); // Host header only; never trust X-Forwarded-Host here
	return ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost:') === 0); // Allow localhost:port from IDEs
}

function tep_apply_local_dev_session() { // Lightweight localhost auto-login so / skips landing.php
	if (!tep_is_local_host()) { // Never seed sessions on production hosts
		return; // Production login_process.php remains the only auth path
	}
	if (!defined('TEP_LOCAL_DEV_AUTOLOGIN') || !TEP_LOCAL_DEV_AUTOLOGIN) { // Toggle: define false in tep_config.php to disable
		return; // Explicit off switch for local testing of the real login flow
	}
	if (session_status() !== PHP_SESSION_ACTIVE) { // Do not call session_start here; callers own cookie params
		return; // Wait until start_secure_session() or the page's session_start()
	}
	$script = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')); // Current PHP file, e.g. index.php
	if (in_array($script, array('login.php', 'logout.php', 'landing.php'), true)) { // Keep login/logout/landing testable locally
		return; // Those pages must not be force-authenticated
	}
	$devAccountId = defined('TEP_LOCAL_DEV_ACCOUNT_ID') ? (string)TEP_LOCAL_DEV_ACCOUNT_ID : '1000'; // Matches tep_local accounts.id
	$devUserId = defined('TEP_LOCAL_DEV_USER_ID') ? (string)TEP_LOCAL_DEV_USER_ID : '1'; // Matches tep_local users.id
	if (empty($_SESSION['accountid'])) { // Do not overwrite a real local login
		$_SESSION['accountid'] = $devAccountId; // index.php echoes this into $scope.accountid and skips landing.php
	}
	if (empty($_SESSION['useraccount'])) { // commonJs.php logs out /events and /account when these differ
		$_SESSION['useraccount'] = $_SESSION['accountid']; // Staff dashboard treats this as the logged-in account
	}
	if (empty($_SESSION['userid'])) { // navController getCurrentUserData binds users.id
		$_SESSION['userid'] = $devUserId; // Local stub user; not a production credential
	}
	if (empty($_SESSION['name'])) { // Display name used by some staff views
		$_SESSION['name'] = 'Local Dev'; // Obvious non-production label
	}
	if (empty($_SESSION['last_activity'])) { // Existing session-timeout helper reads this key
		$_SESSION['last_activity'] = time(); // Mark the seeded session as active
	}
	ensure_session_csrf_token(); // Keep CSRF token in the seeded session for AngularJS posts
}

function start_secure_session() {
	if (session_status() === PHP_SESSION_ACTIVE) {
		ensure_session_csrf_token();
		tep_apply_local_dev_session(); // Seed localhost test account when this page already called session_start()
		return;
	}
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/',
		'domain' => '',
		'secure' => is_https_request(),
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	session_start();
	ensure_session_csrf_token();
	tep_apply_local_dev_session(); // Seed after a fresh local session so /index.php has accountid
}

function ensure_session_csrf_token() {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return '';
	}
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function request_csrf_token() {
	$token = $_POST['csrf_token'] ?? '';
	if ($token !== '') {
		return (string)$token;
	}
	$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
	return (string)$headerToken;
}

function fail_request($statusCode, $message) {
	http_response_code($statusCode);
	die($message);
}

function require_post_request() {
	if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		fail_request(405, 'Method not allowed.');
	}
}

function require_csrf_request() {
	require_post_request();
	start_secure_session();
	$sessionToken = ensure_session_csrf_token();
	$requestToken = request_csrf_token();
	if ($requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
		fail_request(403, 'Security validation failed.');
	}
}

function database_connect() {
	if(substr(str_replace('www.','',$_SERVER['HTTP_HOST']), 0, 4) == "easy") {
		$database = DB_NAME_PROD;
	} else {
		$database = DB_NAME_DEV;
	}
	if ($_SERVER['HTTP_HOST'] == "localhost") {
		$username = DB_USER_LOCAL;
		$password = DB_PASS_LOCAL;
	} else {
		$username = DB_USER_PROD;
		$password = DB_PASS;
	}

	$connectTimeout = defined('DB_CONNECT_TIMEOUT') ? (int)DB_CONNECT_TIMEOUT : 5;
	if ($connectTimeout < 1) {
		$connectTimeout = 5;
	}

	$linkID = mysqli_init();
	if ($linkID) {
		mysqli_options($linkID, MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout);
		if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
			mysqli_options($linkID, MYSQLI_OPT_READ_TIMEOUT, $connectTimeout);
		}
		$linkID = mysqli_real_connect($linkID, DB_HOST, $username, $password, $database, DB_PORT) ? $linkID : false;
	}
	if (!$linkID) {
		// [SEC-3] Log error, never print it — DB errors expose server info
		error_log('TEP DB connection failed: ' . mysqli_connect_error());
		http_response_code(500);
		die('A database error occurred. Please try again.');
	}
	return $linkID;
}

function touch_session_activity($closeSession = false) {
	if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['last_activity'])) {
		$_SESSION['last_activity'] = time();
	}

	if ($closeSession && session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
}

function state_options($selected_state){
	$states= explode(",", "AA,AB,AE,AK,AL,AP,AR,AS,AZ,BC,CA,CO,CT,DC,DE,FL,FM,GA,GU,HI,IA,ID,IL,IN,KS,KY,LA,MA,MB,MD,ME,MH,MI,MN,MO,MP,MS,MT,NB,NC,ND,NE,NH,NJ,NL,NM,NS,NT,NU,NV,NY,OH,OK,ON,OR,PA,PE,PR,PW,QC,RI,SC,SD,SK,TN,TX,UT,VA,VI,VT,WA,WI,WV,WY,YT");
	foreach($states as $state){
		if ($state == $selected_state) $selected = "selected";
		else $selected = "";
		print "<option $selected>$state";
	}
}

function sanitize_inputs($array){
	foreach($array as $key=>$value) {
		if(is_array($value)) {
			$array[$key] = sanitize_inputs($value);
		} else {
			$array[$key] = normalize_input_value($value);
		}
	}
	return $array;
}

function normalize_input_value($value){
	if($value === null) return null;
	if(is_bool($value) || is_int($value) || is_float($value)) return $value;
	return trim((string)$value);
}

function alerts(){
	foreach($_REQUEST as $key => $value){
		if (strtolower(substr($key, 0, 5)) == "alert"){
			$safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
			print "<p class=\"alert $key\">$safeValue</p>";
		}
	}
}

if (!defined('TEP_ENC_KEY_RAW')) { // PHP 8.2 fatals on undefined constants; local XAMPP has no private/tep_config.php
	define('TEP_ENC_KEY_RAW', ''); // Empty local placeholder so index.php can load; production tep_config still wins when present
}
$encKey = TEP_ENC_KEY_RAW; // Decode path unchanged once the constant exists
$encryption_key = base64_decode($encKey); // Empty string is safe when config is missing on localhost

if (tep_is_local_host()) { // Local XAMPP: private tep_config.php is absent, so queries.php cannot read DB_NAME_DEV
	if (!defined('DB_HOST')) define('DB_HOST', 'localhost'); // XAMPP MySQL listen address
	if (!defined('DB_PORT')) define('DB_PORT', 3306); // XAMPP default MySQL port
	if (!defined('DB_NAME_DEV')) define('DB_NAME_DEV', 'tep_local'); // Local schema so getQueryResults.php can bind information_schema
	if (!defined('DB_NAME_PROD')) define('DB_NAME_PROD', 'tep_local'); // Same local schema; easyreg hostnames are not used on XAMPP
	if (!defined('DB_USER_LOCAL')) define('DB_USER_LOCAL', 'root'); // XAMPP default MySQL user
	if (!defined('DB_PASS_LOCAL')) define('DB_PASS_LOCAL', ''); // XAMPP default empty root password
	if (!defined('DB_USER_PROD')) define('DB_USER_PROD', 'root'); // Unused on localhost; prevents a later undefined-constant fatal
	if (!defined('DB_PASS')) define('DB_PASS', ''); // Unused on localhost; prevents a later undefined-constant fatal
	if (!defined('TEP_LOCAL_DEV_AUTOLOGIN')) define('TEP_LOCAL_DEV_AUTOLOGIN', true); // Default on; set false in tep_config.php to use real login locally
	if (!defined('TEP_LOCAL_DEV_ACCOUNT_ID')) define('TEP_LOCAL_DEV_ACCOUNT_ID', '1000'); // tep_local accounts.id used by the dashboard
	if (!defined('TEP_LOCAL_DEV_USER_ID')) define('TEP_LOCAL_DEV_USER_ID', '1'); // tep_local users.id for getCurrentUserData
}

if (session_status() === PHP_SESSION_ACTIVE) { // index.php and getQueryResults.php start the session before this include
	tep_apply_local_dev_session(); // Fill empty localhost sessions so $scope.accountid is set before landing.php redirect
}

function encryptthis($data) {
	$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
	$encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
	return base64_encode($encrypted . '::' . $iv);
}

function decryptthis($data) {
	list($encrypted_data, $iv) = array_pad(explode('::', base64_decode($data), 2),2,null);
	return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
}
?>
