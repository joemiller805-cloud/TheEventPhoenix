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

function tep_env_value($name, $default = '') { // Read Apache SetEnv / OS env; never echo secrets
	$raw = getenv($name); // Process environment (SetEnv, systemd, Windows)
	if ($raw !== false && $raw !== '') { // Non-empty env wins
		return (string)$raw; // Caller define()s; no request data
	}
	if (isset($_ENV[$name]) && (string)$_ENV[$name] !== '') { // php.ini variables_order includes E
		return (string)$_ENV[$name]; // Same isolation
	}
	if (isset($_SERVER[$name]) && (string)$_SERVER[$name] !== '' && strpos((string)$name, 'TEP_') === 0) { // VirtualHost SetEnv
		return (string)$_SERVER[$name]; // TEP_* prefix avoids clobbering HTTP_HOST
	}
	return $default; // Empty so later localhost fallbacks can still run
}

function tep_define_from_env($constant, $envName, $default = '') { // Fill a constant only when tep_config / env.php did not
	if (defined($constant)) { // private/tep_config.php always wins
		return; // Do not redefine
	}
	$tepEnvVal = tep_env_value($envName, $default); // Isolated name; no $_GET/$_POST
	if ($tepEnvVal === '') { // Missing env: leave undefined so localhost fallbacks below can run
		return; // Do not lock DB_HOST to empty string
	}
	define($constant, $tepEnvVal); // Non-empty process environment
}

$tepEnvFile = __DIR__ . '/config/env.php'; // Copy of config/env.example.php; gitignored
if (is_file($tepEnvFile)) { // Server handoff file present
	require_once $tepEnvFile; // May define DB_* / TEP_* when tep_config left them unset
}

tep_define_from_env('DB_HOST', 'TEP_DB_HOST', ''); // MySQL host
tep_define_from_env('DB_PORT', 'TEP_DB_PORT', ''); // MySQL port as string; database_connect casts later
tep_define_from_env('DB_NAME_DEV', 'TEP_DB_NAME_DEV', ''); // Non-easyreg schema
tep_define_from_env('DB_NAME_PROD', 'TEP_DB_NAME_PROD', ''); // easyreg* schema
tep_define_from_env('DB_USER_LOCAL', 'TEP_DB_USER_LOCAL', ''); // localhost user
tep_define_from_env('DB_PASS_LOCAL', 'TEP_DB_PASS_LOCAL', ''); // localhost password
tep_define_from_env('DB_USER_PROD', 'TEP_DB_USER_PROD', ''); // live user
tep_define_from_env('DB_PASS', 'TEP_DB_PASS', ''); // live password
tep_define_from_env('DB_CONNECT_TIMEOUT', 'TEP_DB_CONNECT_TIMEOUT', ''); // mysqli timeout seconds
tep_define_from_env('TEP_ENC_KEY_RAW', 'TEP_ENC_KEY_RAW', ''); // AES key material
tep_define_from_env('LEGACY_SALT', 'TEP_LEGACY_SALT', ''); // crypt() salt for existing hashes
tep_define_from_env('TEP_SESSION_NAME', 'TEP_SESSION_NAME', ''); // Session cookie name for start_secure_session
tep_define_from_env('TEP_VAPID_PUBLIC_KEY', 'TEP_VAPID_PUBLIC_KEY', ''); // Web Push public key
tep_define_from_env('BASE_URL', 'TEP_BASE_URL', ''); // index.php <base href>
if (!defined('TEP_IS_PRODUCTION')) { // Boolean live lock for account-1000 autologin
	$tepProdEnv = strtolower(tep_env_value('TEP_IS_PRODUCTION', '')); // true/1 or false/0
	if ($tepProdEnv === '1' || $tepProdEnv === 'true') { // Operator set live
		define('TEP_IS_PRODUCTION', true); // tep_apply_local_dev_session returns immediately
	} elseif ($tepProdEnv === '0' || $tepProdEnv === 'false') { // Operator set not-live
		define('TEP_IS_PRODUCTION', false); // Host-header gate still applies
	}
}
if (!defined('TEP_LOCAL_DEV_AUTOLOGIN')) { // Extra XAMPP toggle
	$tepAutoEnv = strtolower(tep_env_value('TEP_LOCAL_DEV_AUTOLOGIN', '')); // true/1 or false/0
	if ($tepAutoEnv === '1' || $tepAutoEnv === 'true') { // Explicit on
		define('TEP_LOCAL_DEV_AUTOLOGIN', true); // Seed account 1000 on localhost only
	} elseif ($tepAutoEnv === '0' || $tepAutoEnv === 'false') { // Explicit off
		define('TEP_LOCAL_DEV_AUTOLOGIN', false); // Use real login locally
	}
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$is_production = true; // Strict live flag: when true, developer session bypass (account 1000) is disabled

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
	if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') { // Apache SSL vhost sets HTTPS=on
		return true; // Local https://localhost and production TLS
	}
	if (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') { // Port 443 even if HTTPS env is missing
		return true; // XAMPP Listen 443
	}
	if (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'https') { // PHP 8.2 scheme from the request
		return true; // Do not miss local HTTPS when HTTPS=on is absent
	}
	if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
		return true;
	}
	return false; // HTTP on :80 keeps Secure off so the cookie is not dropped
}

function tep_is_local_host() { // Local XAMPP detector used by DB fallbacks and the dev session helper
	$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? '')); // Host header only; never trust X-Forwarded-Host here
	return ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost:') === 0); // Allow localhost:port from IDEs
}

function tep_vapid_public_key() { // URL-safe P-256 applicationServerKey for pushManager.subscribe (not a send private key)
	if (defined('TEP_VAPID_PUBLIC_KEY') && TEP_VAPID_PUBLIC_KEY !== '') { // Production tep_config.php wins when present
		return (string)TEP_VAPID_PUBLIC_KEY; // Real VAPID public key for FCM/Mozilla send
	}
	$x = hex2bin('6b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c296'); // NIST P-256 generator G.x (public curve parameter)
	$y = hex2bin('4fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5'); // NIST P-256 generator G.y (public curve parameter)
	if ($x === false || $y === false) { // hex2bin failed
		return ''; // Dashboard will fail-soft and skip subscribe
	}
	return rtrim(strtr(base64_encode("\x04" . $x . $y), '+/', '-_'), '='); // Uncompressed 65-byte point as VAPID applicationServerKey for local Chrome subscribe
}

function tep_apply_local_dev_session() { // Lightweight localhost auto-login so / skips landing.php
	global $is_production; // File-scope live flag set to true unless XAMPP flips it
	if ($is_production === true) { // Strict production check: live deploy never seeds account 1000
		return; // login_process.php remains the only auth path when $is_production = true
	}
	if (!tep_is_local_host()) { // Host-header belt-and-suspenders if the flag was flipped by mistake
		return; // Non-localhost hosts never receive the developer session
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
	$tepCookieSecure = is_https_request(); // HTTPS-only Secure; HTTP :80 stays false so local cookies are not dropped
	ini_set('session.cookie_httponly', '1'); // JS cannot read the session id
	ini_set('session.cookie_samesite', 'Lax'); // SameSite=Lax for top-level HTTPS navigations (does not require Secure)
	ini_set('session.cookie_secure', $tepCookieSecure ? '1' : '0'); // Match the request scheme
	session_set_cookie_params([ // Must run before session_start(); empty domain = host-only (localhost-safe)
		'lifetime' => 0, // Browser session cookie
		'path' => '/', // Whole TEP site
		'domain' => '', // Host-only; do not set domain=localhost (Chrome drops it)
		'secure' => $tepCookieSecure, // true on https://127.0.0.1 and https://localhost
		'httponly' => true, // Not readable from AngularJS
		'samesite' => 'Lax', // Explicit SameSite=Lax
	]);
	if (defined('TEP_SESSION_NAME') && TEP_SESSION_NAME !== '' && TEP_SESSION_NAME !== 'PHPSESSID') { // Isolated cookie name from env / tep_config
		session_name(TEP_SESSION_NAME); // Must run before session_start(); index.php raw session_start() still uses php.ini
	}
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

	$connectTimeout = defined('DB_CONNECT_TIMEOUT') ? (int)DB_CONNECT_TIMEOUT : 2; // Match PDO::ATTR_TIMEOUT so getQueryResults cannot stall 5s
	if ($connectTimeout < 1) {
		$connectTimeout = 2; // Floor: same 2-second cap as data_access/db.php
	}

	$linkID = mysqli_init();
	if ($linkID) {
		mysqli_options($linkID, MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout);
		if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
			mysqli_options($linkID, MYSQLI_OPT_READ_TIMEOUT, $connectTimeout);
		}
		$tepDbPort = defined('DB_PORT') ? (int)DB_PORT : 3306; // Env may supply a string; mysqli wants int
		if ($tepDbPort < 1) { // Invalid TEP_DB_PORT
			$tepDbPort = 3306; // Default MySQL
		}
		$tepMysqlHost = defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1'; // Prefer IPv4 loopback on XAMPP
		if ($tepMysqlHost === 'localhost' || $tepMysqlHost === '::1') { // Windows IPv6 localhost lookup ~2s
			$tepMysqlHost = '127.0.0.1'; // Same as PDO mysql:host=127.0.0.1
		}
		$linkID = mysqli_real_connect($linkID, $tepMysqlHost, $username, $password, $database, $tepDbPort) ? $linkID : false;
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
if (!defined('LEGACY_SALT')) { // login_process.php crypt() on XAMPP without tep_config
	define('LEGACY_SALT', ''); // Empty local placeholder; production must set TEP_LEGACY_SALT / tep_config
}
$encKey = TEP_ENC_KEY_RAW; // Decode path unchanged once the constant exists
$encryption_key = base64_decode($encKey); // Empty string is safe when config is missing on localhost

if (tep_is_local_host() && !(defined('TEP_IS_PRODUCTION') && TEP_IS_PRODUCTION === true)) { // XAMPP only; tep_config can lock production even on a local host name
	$is_production = false; // Flip the live flag so account 1000 autologin can run on this machine only
}
if (tep_is_local_host()) { // Local XAMPP: private tep_config.php is absent, so queries.php cannot read DB_NAME_DEV
	if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1'); // XAMPP MySQL IPv4 loopback — skip Windows localhost DNS
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
