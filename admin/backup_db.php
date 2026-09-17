<?php
session_start(); // Cookie session before common_functions seeds localhost admin
require_once $_SERVER['DOCUMENT_ROOT'] . '/common_functions.php'; // CSRF helpers + local DB constants
require_csrf_request(); // POST + matching csrf_token only — never GET (CSRF / cache)

$accountId = (string)($_SESSION['accountid'] ?? ''); // Tenant for the snapshot
$userAccount = (string)($_SESSION['useraccount'] ?? ''); // Staff/admin account on admin.php
$userId = (string)($_SESSION['userid'] ?? ''); // Must be a logged-in user, not a bare cookie
if ($accountId === '' || $userId === '' || $accountId !== $userAccount) { // Same gate as admin.php (accountid == useraccount)
	fail_request(403, 'Admin session required.'); // Attendee / empty sessions cannot dump SQL
}

function tep_backup_ident($name) { // Allow only unquoted MySQL identifiers after information_schema
	$ident = (string)$name; // Column or table name from reflection
	if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $ident)) { // Reject dots, spaces, backticks, injections
		return null; // Caller skips this name
	}
	return '`' . $ident . '`'; // Safe backtick wrap; never concatenate request data here
}

function tep_backup_pdo() { // Same host/schema rules as tep_poll_pdo(); constants only
	$host = defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1'; // XAMPP / tep_config
	if ($host === 'localhost' || $host === '::1') { // Windows IPv6 localhost lookup ~2s
		$host = '127.0.0.1'; // mysql:host=127.0.0.1
	}
	$port = defined('DB_PORT') ? (int)DB_PORT : 3306; // Default MySQL port
	$httpHost = str_replace('www.', '', (string)($_SERVER['HTTP_HOST'] ?? '')); // Match database_connect prod vs dev
	if (substr($httpHost, 0, 4) == 'easy') { // Production hostname
		$dbname = defined('DB_NAME_PROD') ? DB_NAME_PROD : 'tep_local'; // Live schema
		$user = defined('DB_USER_PROD') ? DB_USER_PROD : 'root'; // Live user
		$pass = defined('DB_PASS') ? DB_PASS : ''; // Live password
	} else {
		$dbname = defined('DB_NAME_DEV') ? DB_NAME_DEV : 'tep_local'; // Local tep_local
		$user = defined('DB_USER_LOCAL') ? DB_USER_LOCAL : 'root'; // XAMPP user
		$pass = defined('DB_PASS_LOCAL') ? DB_PASS_LOCAL : ''; // XAMPP empty root password
	}
	$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=utf8mb4'; // mysql:host=127.0.0.1 locally
	$pdo = new PDO($dsn, $user, $pass, array( // Exceptions → 500 without dumping credentials
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Fail closed
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Named columns for INSERT lists
		PDO::ATTR_EMULATE_PREPARES => false, // Native prepares
	));
	return array($pdo, $dbname); // Caller needs the schema name for information_schema binds
}

function tep_backup_sql_value($pdo, $value) { // Literal for the .sql file
	if ($value === null) { // SQL NULL
		return 'NULL'; // Unquoted
	}
	if (is_bool($value)) { // Tinyint-style flags
		return $value ? '1' : '0'; // Numeric
	}
	if (is_int($value) || is_float($value)) { // Already numeric
		return (string)$value; // No quotes
	}
	return $pdo->quote((string)$value); // PDO-escaped string; charset utf8mb4
}

set_time_limit(180); // Large accounts may stream many INSERT rows
ignore_user_abort(true); // Finish the stream if the tab closes mid-download

$stamp = gmdate('Ymd_His'); // UTC timestamp in the download name
$safeAccount = preg_replace('/[^0-9]/', '', $accountId); // Digits only in the filename
if ($safeAccount === '') { // Non-numeric account id
	$safeAccount = 'acct'; // Fallback label
}
$fileName = 'tep_event_snapshot_' . $safeAccount . '_' . $stamp . '.sql'; // Timestamped download

header('Content-Type: application/sql; charset=utf-8'); // .sql download even when the dump later fails
header('Content-Disposition: attachment; filename="' . $fileName . '"'); // Browser save-as
header('Cache-Control: no-store, no-cache, must-revalidate'); // Never let the SW / browser cache a dump
header('Pragma: no-cache'); // Legacy caches
header('X-Content-Type-Options: nosniff'); // Extra nosniff on the stream

while (ob_get_level() > 0) { // Drop output buffers so the file streams
	ob_end_clean(); // Discard buffered HTML
}

echo "-- TEP Event Snapshot\n"; // File header (valid SQL comments if the dump aborts)
echo '-- generated_utc: ' . gmdate('c') . "\n"; // When
echo '-- accountid: ' . str_replace(array("\n", "\r"), '', $accountId) . "\n"; // Tenant (no newlines)
echo "-- schema: reflected from information_schema (BASE TABLE only)\n"; // How tables were chosen
echo "-- data: account-scoped where a tenant column exists; other tables dump structure only\n\n"; // Tenant rule

try { // Master catch: reflection, table loop, and streaming must not 500 mid-file
	list($pdo, $schemaName) = tep_backup_pdo(); // Open TEP schema
	echo "SET NAMES utf8mb4;\n"; // Restore charset
	echo "SET FOREIGN_KEY_CHECKS=0;\n\n"; // Allow reload order to vary

	$tableStmt = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = :ttype ORDER BY TABLE_NAME'); // Reflect BASE tables only
	$tableStmt->bindValue(':schema', $schemaName, PDO::PARAM_STR); // Current TEP database
	$tableStmt->bindValue(':ttype', 'BASE TABLE', PDO::PARAM_STR); // Skip views
	$tableStmt->execute(); // Run reflection
	$rawTables = $tableStmt->fetchAll(PDO::FETCH_COLUMN); // List of names
	$tables = array(); // Whitelisted identifiers
	foreach ($rawTables as $rawName) { // One pass
		$quoted = tep_backup_ident($rawName); // Regex + backticks
		if ($quoted !== null) { // Safe name
			$tables[] = (string)$rawName; // Store unquoted for later binds
		}
	}

	$colStmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION'); // Reflect columns per table
	$hasEvents = in_array('events', $tables, true); // Child tables may filter by eventid
	$hasPolls = in_array('tep_polls', $tables, true); // Poll votes filter by pollid
	$accountParam = (int)$accountId; // Bound tenant id

	foreach ($tables as $table) { // Every reflected TEP table
		$qTable = tep_backup_ident($table); // Already validated; re-check
		if ($qTable === null) { // Defensive
			continue; // Skip
		}
		$colStmt->bindValue(':schema', $schemaName, PDO::PARAM_STR); // Schema bind
		$colStmt->bindValue(':table', $table, PDO::PARAM_STR); // Table name as data, not SQL
		$colStmt->execute(); // Column list
		$colNames = array(); // Unquoted
		$qCols = array(); // Quoted list
		foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $colName) { // Each column
			$qCol = tep_backup_ident($colName); // Regex
			if ($qCol === null) { // Weird column name
				continue; // Skip that column
			}
			$colNames[] = (string)$colName; // For has-column checks
			$qCols[] = $qCol; // For INSERT
		}
		if (empty($qCols)) { // Nothing safe to dump
			echo '-- skipped ' . $qTable . ": no safe columns\n\n"; // Note
			continue; // Next table
		}
		echo '-- table ' . $qTable . "\n"; // Section
		try {
			$createRow = $pdo->query('SHOW CREATE TABLE ' . $qTable)->fetch(PDO::FETCH_NUM); // Identifier already quoted; not request data
			$createSql = isset($createRow[1]) ? (string)$createRow[1] : ''; // CREATE TABLE ...
			if ($createSql !== '') { // Have DDL
				echo $createSql . ";\n\n"; // Structure
			}
		} catch (Exception $createEx) { // SHOW CREATE failed
			error_log('TEP backup SHOW CREATE failed for table: ' . $table); // Name only
			echo '-- SHOW CREATE TABLE failed for ' . $qTable . "\n\n"; // Continue with data if possible
		}

		$hasAccountCol = in_array('accountid', $colNames, true); // Direct tenant column
		$hasEventCol = in_array('eventid', $colNames, true); // Event child
		$hasPollCol = in_array('pollid', $colNames, true); // Poll votes
		$selectSql = ''; // Bound data query
		if ($hasAccountCol) { // Fast path
			$selectSql = 'SELECT * FROM ' . $qTable . ' WHERE accountid = :accountid'; // Bound tenant
		} elseif ($hasEventCol && $hasEvents) { // registrations and similar
			$selectSql = 'SELECT t.* FROM ' . $qTable . ' t INNER JOIN events e ON e.id = t.eventid WHERE e.accountid = :accountid'; // Bound via events
		} elseif ($hasPollCol && $hasPolls) { // tep_poll_votes
			$selectSql = 'SELECT t.* FROM ' . $qTable . ' t INNER JOIN tep_polls p ON p.id = t.pollid WHERE p.accountid = :accountid'; // Bound via polls
		}
		if ($selectSql === '') { // No tenant key
			echo '-- data omitted for ' . $qTable . " (no accountid/eventid/pollid scope)\n\n"; // Do not leak other tenants
			continue; // Structure-only
		}
		try {
			$dataStmt = $pdo->prepare($selectSql); // PDO prepared select
			$dataStmt->bindValue(':accountid', $accountParam, PDO::PARAM_INT); // Session account only
			$dataStmt->execute(); // Stream rows
			$colListSql = implode(',', $qCols); // INSERT column list
			$rowCount = 0; // For the footer comment
			while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) { // One INSERT per row (simple restore)
				$values = array(); // Literals
				foreach ($colNames as $colName) { // Same order as column list
					$values[] = tep_backup_sql_value($pdo, $row[$colName] ?? null); // Quoted / NULL
				}
				echo 'INSERT INTO ' . $qTable . ' (' . $colListSql . ') VALUES (' . implode(',', $values) . ");\n"; // One row
				$rowCount++; // Tally
				if (($rowCount % 50) === 0) { // Periodic flush
					flush(); // Stream to the browser
				}
			}
			echo '-- ' . $rowCount . ' row(s) for ' . $qTable . "\n\n"; // Per-table count
		} catch (Exception $dataEx) { // Missing join table, etc.
			error_log('TEP backup data select failed for table: ' . $table); // Name only
			echo '-- data export failed for ' . $qTable . "\n\n"; // Keep the rest of the file
		}
	}

	echo "SET FOREIGN_KEY_CHECKS=1;\n"; // Restore FK checks
	echo "-- end TEP Event Snapshot\n"; // Footer
} catch (Throwable $backupEx) { // Connect, reflection, or streaming threw after headers
	error_log('TEP backup failed: ' . $backupEx->getMessage()); // Log only — never echo the exception
	echo "\n-- TEP Event Snapshot backup failed.\n"; // Valid SQL comment; file stays restorable as comments-only
	echo "-- The dump stopped before completion. Do not restore this file.\n"; // Operator hint; no schema or credentials
}

exit; // Stop after the stream or the failure comments
