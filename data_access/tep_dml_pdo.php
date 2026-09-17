<?php
// Phase 9: bound DML helpers for insert_or_update.php and delete_record.php (no request data in SQL)

function tep_dml_pdo() { // Same host/schema rules as tep_poll_pdo / database_connect; constants only
	require_once __DIR__ . '/db.php'; // tep_mysql_host() → 127.0.0.1 on XAMPP
	$host = tep_mysql_host(); // Skip Windows IPv6 localhost lookup
	$port = defined('DB_PORT') ? (int)DB_PORT : 3306; // Env may be a string
	if ($port < 1) { // Invalid TEP_DB_PORT
		$port = 3306; // Default MySQL
	}
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
	$tepPdoOpts = tep_pdo_options(); // Shared ERRMODE / FETCH_ASSOC / native prepares / 2s timeout from db.php
	$tepPdoOpts[PDO::ATTR_TIMEOUT] = 2; // Cap insert/update/delete waits at 2 seconds when MySQL is down
	return new PDO($dsn, $user, $pass, $tepPdoOpts); // Callers catch Throwable; no credentials in the HTTP body
}

function tep_dml_ident($name) { // Table or column name from the existing AngularJS payload
	$ident = trim((string)$name, " \t\n\r\0\x0B`"); // Strip backticks/whitespace; never use raw request in SQL
	if ($ident === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $ident)) { // Reject dots, spaces, comments, injections
		return null; // Caller aborts the statement
	}
	return $ident; // Unquoted identifier; caller wraps in backticks
}

function tep_dml_table_columns($pdo, $table) { // Live column names for this schema only
	$colStmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tname'); // Bound table name; identifiers already regex-checked
	$colStmt->bindValue(':tname', $table, PDO::PARAM_STR); // Existing table parameter, as data
	$colStmt->execute(); // Column list
	$cols = $colStmt->fetchAll(PDO::FETCH_COLUMN); // Names only
	$out = array(); // Lookup map
	foreach ($cols as $col) { // Lower-case keys match dataSvc.toLowerCase table names
		$out[strtolower((string)$col)] = (string)$col; // Preserve actual column spelling
	}
	return $out; // Empty if the table does not exist
}

function tep_dml_parse_columns($raw) { // insertColumns: "(col1,col2,col3) " from dataAccess.js
	$s = trim((string)$raw); // Request fragment
	$s = trim($s, " \t()"); // Drop wrapping parens
	if ($s === '') { // Nothing to insert
		return array(); // Empty list
	}
	$parts = explode(',', $s); // JS joins with commas, no spaces required
	$cols = array(); // Validated names
	foreach ($parts as $part) { // Each requested column
		$id = tep_dml_ident($part); // Regex only
		if ($id === null) { // Injection / junk
			return null; // Reject the whole INSERT
		}
		$cols[] = $id; // Safe identifier
	}
	return $cols; // Ordered list matching VALUES
}

function tep_dml_parse_one_value($s, &$i) { // One token from insertValues / updateData; advances $i
	$len = strlen($s); // Subject length
	while ($i < $len && ctype_space($s[$i])) { // Skip spaces
		$i++; // Next char
	}
	if ($i >= $len) { // Trailing comma
		return null; // Invalid
	}
	$rest = substr($s, $i); // Slice from current index (PHP \G + offset is unreliable)
	if (preg_match('/^now\(\)/i', $rest, $m)) { // dataAccess.js replaces "'now()'" with now()
		$i += strlen($m[0]); // Consume the function
		return array('sql' => 'NOW()'); // Keyword in SQL; not a bound string
	}
	if (preg_match('/^null\b/i', $rest, $m)) { // Optional NULL token
		$i += strlen($m[0]); // Consume
		return array('null' => true); // PDO::PARAM_NULL
	}
	if ($s[$i] === "'" || $s[$i] === '"') { // JS wraps every value in single quotes
		$q = $s[$i]; // Quote char
		$i++; // Inside the string
		$buf = ''; // Unescaped value
		while ($i < $len) { // Scan to closing quote
			if ($s[$i] === '\\' && ($i + 1) < $len) { // Backslash escape from str_replace("\\'","'")
				$buf .= $s[$i + 1]; // Literal next char
				$i += 2; // Skip pair
				continue; // Keep scanning
			}
			if ($s[$i] === $q) { // Possible closer
				if (($i + 1) < $len && $s[$i + 1] === $q) { // SQL '' escape
					$buf .= $q; // One quote
					$i += 2; // Skip both
					continue; // Keep scanning
				}
				$i++; // Closing quote
				return array('val' => $buf); // Bound parameter
			}
			$buf .= $s[$i]; // Literal
			$i++; // Next
		}
		return null; // Unclosed quote
	}
	$start = $i; // Unquoted token (legacy numbers)
	while ($i < $len && $s[$i] !== ',' && !ctype_space($s[$i])) { // Until comma or space
		$i++; // Next
	}
	$tok = substr($s, $start, $i - $start); // Raw token
	if ($tok === '') { // Empty
		return null; // Invalid
	}
	return array('val' => $tok); // Bind as string; MySQL will coerce
}

function tep_dml_parse_value_list($raw) { // insertValues: "('a','b',now())" from dataAccess.js
	$s = trim((string)$raw); // Request fragment
	if ($s !== '' && $s[0] === '(' && substr($s, -1) === ')') { // Wrapping parens
		$s = substr($s, 1, -1); // Inner list
	}
	$s = trim($s); // Spaces
	if ($s === '') { // No values
		return array(); // Empty
	}
	$values = array(); // Parsed tokens
	$i = 0; // Cursor
	$len = strlen($s); // Length
	while ($i < $len) { // Each value
		while ($i < $len && ($s[$i] === ',' || ctype_space($s[$i]))) { // Separators
			$i++; // Next
		}
		if ($i >= $len) { // Done
			break; // Exit
		}
		$one = tep_dml_parse_one_value($s, $i); // One token
		if ($one === null) { // Bad fragment
			return null; // Reject INSERT
		}
		$values[] = $one; // Keep order
	}
	return $values; // Parallel to columns
}

function tep_dml_parse_assignments($raw) { // updateData: "col = 'v', other = now()" from dataAccess.js
	$s = str_replace("\\'", "'", (string)$raw); // Same unescape the old mysqli path used
	$s = trim($s); // Spaces
	if ($s === '') { // Nothing to set
		return array(); // Empty
	}
	$pairs = array(); // col => token
	$i = 0; // Cursor
	$len = strlen($s); // Length
	while ($i < $len) { // Each assignment
		while ($i < $len && ($s[$i] === ',' || ctype_space($s[$i]))) { // Separators
			$i++; // Next
		}
		if ($i >= $len) { // Done
			break; // Exit
		}
		$rest = substr($s, $i); // Remaining
		if (!preg_match('/^`?([A-Za-z_][A-Za-z0-9_]*)`?/', $rest, $m)) { // Column name
			return null; // Reject UPDATE
		}
		$col = $m[1]; // Identifier
		$i += strlen($m[0]); // Consume name
		while ($i < $len && ctype_space($s[$i])) { // Spaces around =
			$i++; // Next
		}
		if ($i >= $len || $s[$i] !== '=') { // Must be SET col = value
			return null; // Reject
		}
		$i++; // Past =
		$one = tep_dml_parse_one_value($s, $i); // Value token
		if ($one === null) { // Bad value
			return null; // Reject
		}
		$pairs[] = array('col' => $col, 'value' => $one); // Ordered SET list
	}
	return $pairs; // For placeholders
}

function tep_dml_parse_where_id($raw) { // whereClause is always "id = " + record.id from dataAccess.js
	$s = trim((string)$raw); // Request fragment
	if (!preg_match('/^id\s*=\s*(?:\'([0-9]+)\'|"([0-9]+)"|([0-9]+))\s*$/i', $s, $m)) { // Only numeric id; no extra AND/OR
		return null; // Reject UPDATE/DELETE
	}
	$id = ($m[1] ?? '') !== '' ? $m[1] : ((($m[2] ?? '') !== '') ? $m[2] : ($m[3] ?? '')); // Quoted or bare digits
	return $id; // Bind as string/int later
}

function tep_dml_bind_token($stmt, $ph, $token) { // Bind one parsed value; NOW() is not bound
	if (!empty($token['sql'])) { // SQL keyword already in the statement
		return; // Nothing to bind
	}
	if (!empty($token['null'])) { // NULL
		$stmt->bindValue($ph, null, PDO::PARAM_NULL); // Typed null
		return; // Done
	}
	$stmt->bindValue($ph, (string)($token['val'] ?? ''), PDO::PARAM_STR); // All other values as bound strings
}

function tep_pdo_in_list($values) { // Build bound IN (...) lists; never interpolate CSV
	$clean = array(); // Bound values
	foreach ((array)$values as $v) { // Each token
		$s = trim((string)$v); // Drop empties
		if ($s !== '') { // Keep
			$clean[] = $s; // As data
		}
	}
	if (!$clean) { // Nothing to bind
		return null; // Caller skips the statement
	}
	return array( // Placeholders + parallel params
		'sql' => implode(',', array_fill(0, count($clean), '?')), // ?,?,?
		'params' => $clean, // Bound in order
	);
}

function tep_dml_fail($code, $message) { // Structured JSON; never echo SQL, stack traces, or credentials
	header('Content-Type: application/json'); // AngularJS / dataSvc can parse the body
	$code = (int)$code; // 401 / 403 vs connect/parse/execute
	if ($code === 401) { // No logged-in principal
		http_response_code(401); // Unauthenticated API
	} elseif ($code === 403) { // Authorization still fails closed for $http interceptors
		http_response_code(403); // Keep Not Authorized as 403
	} else { // Connect, parse, or execute failure (was plain-text 400/500)
		http_response_code(200); // Fail-soft so a down MySQL does not reject AngularJS $http
	}
	print json_encode(array('ok' => false, 'error' => (string)$message)); // {"ok":false,"error":"Query Execution Error"}
	exit; // Stop
}
