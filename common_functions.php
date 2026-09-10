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

function start_secure_session() {
	if (session_status() === PHP_SESSION_ACTIVE) {
		ensure_session_csrf_token();
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

$tepHttpHost = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Host used to scope XAMPP-only DB fallbacks
$tepIsLocalHost = ($tepHttpHost === 'localhost' || $tepHttpHost === '127.0.0.1'); // Production hosts must keep using tep_config.php
if ($tepIsLocalHost) { // Local XAMPP: private tep_config.php is absent, so queries.php cannot read DB_NAME_DEV
	if (!defined('DB_HOST')) define('DB_HOST', 'localhost'); // XAMPP MySQL listen address
	if (!defined('DB_PORT')) define('DB_PORT', 3306); // XAMPP default MySQL port
	if (!defined('DB_NAME_DEV')) define('DB_NAME_DEV', 'tep_local'); // Local schema so getQueryResults.php can bind information_schema
	if (!defined('DB_NAME_PROD')) define('DB_NAME_PROD', 'tep_local'); // Same local schema; easyreg hostnames are not used on XAMPP
	if (!defined('DB_USER_LOCAL')) define('DB_USER_LOCAL', 'root'); // XAMPP default MySQL user
	if (!defined('DB_PASS_LOCAL')) define('DB_PASS_LOCAL', ''); // XAMPP default empty root password
	if (!defined('DB_USER_PROD')) define('DB_USER_PROD', 'root'); // Unused on localhost; prevents a later undefined-constant fatal
	if (!defined('DB_PASS')) define('DB_PASS', ''); // Unused on localhost; prevents a later undefined-constant fatal
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
