<?php
// Phase 9: copy this file to config/env.php on the server (env.php is gitignored).
// Prefer Apache SetEnv / OS environment variables of the same TEP_* names so passwords never sit in the web tree.
// Do not put real production passwords in this example. private/tep_config.php still wins when it already defined a constant.

if (!defined('DB_HOST')) { // MySQL hostname
	$tepDbHost = getenv('TEP_DB_HOST'); // Apache SetEnv TEP_DB_HOST
	define('DB_HOST', ($tepDbHost !== false && $tepDbHost !== '') ? $tepDbHost : '127.0.0.1'); // XAMPP IPv4 loopback; not localhost
}
if (!defined('DB_PORT')) { // MySQL port
	$tepDbPort = getenv('TEP_DB_PORT'); // Apache SetEnv TEP_DB_PORT
	define('DB_PORT', ($tepDbPort !== false && $tepDbPort !== '') ? (int)$tepDbPort : 3306); // XAMPP example default
}
if (!defined('DB_NAME_DEV')) { // Non-easyreg hostname schema
	$tepDbNameDev = getenv('TEP_DB_NAME_DEV'); // Apache SetEnv TEP_DB_NAME_DEV
	define('DB_NAME_DEV', ($tepDbNameDev !== false && $tepDbNameDev !== '') ? $tepDbNameDev : 'tep_local'); // Local example schema
}
if (!defined('DB_NAME_PROD')) { // easyreg* hostname schema
	$tepDbNameProd = getenv('TEP_DB_NAME_PROD'); // Apache SetEnv TEP_DB_NAME_PROD
	define('DB_NAME_PROD', ($tepDbNameProd !== false && $tepDbNameProd !== '') ? $tepDbNameProd : 'tep_local'); // Replace on live
}
if (!defined('DB_USER_LOCAL')) { // User when HTTP_HOST is localhost
	$tepDbUserLocal = getenv('TEP_DB_USER_LOCAL'); // Apache SetEnv TEP_DB_USER_LOCAL
	define('DB_USER_LOCAL', ($tepDbUserLocal !== false && $tepDbUserLocal !== '') ? $tepDbUserLocal : 'root'); // XAMPP example user
}
if (!defined('DB_PASS_LOCAL')) { // Password when HTTP_HOST is localhost
	$tepDbPassLocal = getenv('TEP_DB_PASS_LOCAL'); // Apache SetEnv TEP_DB_PASS_LOCAL
	define('DB_PASS_LOCAL', ($tepDbPassLocal !== false && $tepDbPassLocal !== '') ? $tepDbPassLocal : ''); // XAMPP empty root; set a real value on staging
}
if (!defined('DB_USER_PROD')) { // User when HTTP_HOST is not localhost
	$tepDbUserProd = getenv('TEP_DB_USER_PROD'); // Apache SetEnv TEP_DB_USER_PROD
	define('DB_USER_PROD', ($tepDbUserProd !== false && $tepDbUserProd !== '') ? $tepDbUserProd : 'replace_me'); // Live MySQL user
}
if (!defined('DB_PASS')) { // Password when HTTP_HOST is not localhost
	$tepDbPass = getenv('TEP_DB_PASS'); // Apache SetEnv TEP_DB_PASS
	define('DB_PASS', ($tepDbPass !== false && $tepDbPass !== '') ? $tepDbPass : ''); // Live MySQL password — never commit
}
if (!defined('DB_CONNECT_TIMEOUT')) { // Seconds for mysqli connect
	$tepDbTimeout = getenv('TEP_DB_CONNECT_TIMEOUT'); // Apache SetEnv TEP_DB_CONNECT_TIMEOUT
	define('DB_CONNECT_TIMEOUT', ($tepDbTimeout !== false && $tepDbTimeout !== '') ? (int)$tepDbTimeout : 2); // Match PDO::ATTR_TIMEOUT (2 seconds)
}
if (!defined('TEP_ENC_KEY_RAW')) { // Base64 AES key used by encryptthis / decryptthis
	$tepEncKey = getenv('TEP_ENC_KEY_RAW'); // Apache SetEnv TEP_ENC_KEY_RAW
	define('TEP_ENC_KEY_RAW', ($tepEncKey !== false && $tepEncKey !== '') ? $tepEncKey : ''); // Empty local placeholder; production must set this
}
if (!defined('LEGACY_SALT')) { // crypt() salt for staff / attendee / sponsor passwords
	$tepLegacySalt = getenv('TEP_LEGACY_SALT'); // Apache SetEnv TEP_LEGACY_SALT
	define('LEGACY_SALT', ($tepLegacySalt !== false && $tepLegacySalt !== '') ? $tepLegacySalt : ''); // Must match existing password hashes
}
if (!defined('TEP_SESSION_NAME')) { // PHP session cookie name (applied in start_secure_session before session_start)
	$tepSessionName = getenv('TEP_SESSION_NAME'); // Apache SetEnv TEP_SESSION_NAME
	define('TEP_SESSION_NAME', ($tepSessionName !== false && $tepSessionName !== '') ? $tepSessionName : 'PHPSESSID'); // Default PHP name if unset
}
if (!defined('TEP_VAPID_PUBLIC_KEY')) { // Web Push applicationServerKey
	$tepVapid = getenv('TEP_VAPID_PUBLIC_KEY'); // Apache SetEnv TEP_VAPID_PUBLIC_KEY
	define('TEP_VAPID_PUBLIC_KEY', ($tepVapid !== false && $tepVapid !== '') ? $tepVapid : ''); // Empty uses the local NIST P-256 fallback in tep_vapid_public_key()
}
if (!defined('BASE_URL')) { // AngularJS <base href> on index.php
	$tepBaseUrl = getenv('TEP_BASE_URL'); // Apache SetEnv TEP_BASE_URL
	define('BASE_URL', ($tepBaseUrl !== false && $tepBaseUrl !== '') ? $tepBaseUrl : '/'); // Root-relative default
}
if (!defined('TEP_IS_PRODUCTION')) { // When true, localhost account-1000 autologin is disabled
	$tepIsProd = getenv('TEP_IS_PRODUCTION'); // Apache SetEnv TEP_IS_PRODUCTION
	if ($tepIsProd !== false && $tepIsProd !== '') { // Only define when the operator set it
		$tepIsProdNorm = strtolower((string)$tepIsProd); // true/1 vs false/0
		define('TEP_IS_PRODUCTION', ($tepIsProdNorm === '1' || $tepIsProdNorm === 'true')); // Boolean for tep_apply_local_dev_session
	}
}
if (!defined('TEP_LOCAL_DEV_AUTOLOGIN')) { // Extra local toggle; ignored when TEP_IS_PRODUCTION is true
	$tepAuto = getenv('TEP_LOCAL_DEV_AUTOLOGIN'); // Apache SetEnv TEP_LOCAL_DEV_AUTOLOGIN
	if ($tepAuto !== false && $tepAuto !== '') { // Only define when set
		$tepAutoNorm = strtolower((string)$tepAuto); // true/1 vs false/0
		define('TEP_LOCAL_DEV_AUTOLOGIN', ($tepAutoNorm === '1' || $tepAutoNorm === 'true')); // XAMPP dashboard skip-login
	}
}
