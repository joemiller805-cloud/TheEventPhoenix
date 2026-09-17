<?php
// Shared PDO options for TEP data_access; keep passwords in tep_config / env, not here

if (!function_exists('tep_mysql_host')) { // common_functions may already define this for mysqli
	function tep_mysql_host() { // IPv4 loopback avoids Windows localhost → IPv6 lookup delay
		$host = defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1'; // tep_config / env / XAMPP
		if ($host === 'localhost' || $host === '::1') { // Name and IPv6 loopback both stall ~2s on Win32
			return '127.0.0.1'; // mysql:host=127.0.0.1
		}
		return $host; // Production hostnames unchanged
	}
}

function tep_pdo_options() { // Connect/read cap so a down MySQL cannot stall AngularJS dataSvc
	$opts = array( // Native prepares + exceptions; callers catch Throwable
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Fail closed
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // dataSvc-friendly rows
		PDO::ATTR_EMULATE_PREPARES => false, // Real server-side prepares
		PDO::ATTR_TIMEOUT => 2, // Cap database execution waits at 2 seconds
	);
	if (defined('PDO::MYSQL_ATTR_READ_TIMEOUT')) { // mysqlnd query/read timeout (seconds)
		$opts[PDO::MYSQL_ATTR_READ_TIMEOUT] = 2; // Match ATTR_TIMEOUT so hung SELECTs abort
	}
	return $opts; // Used by queries.php tep_poll_pdo
}
