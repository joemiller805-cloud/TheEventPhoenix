#!/usr/local/bin/php.cli
<?php
	include("../common_functions.php");
	require_once __DIR__ . '/../data_access/tep_dml_pdo.php'; // Bound identifiers from SHOW TABLES

	try { // CLI dump; table names from information_schema, never request input
		$pdo = tep_dml_pdo(); // utf8mb4
		$tables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM); // No user input
		$date = date("Ymd_His");
		$backupDir = (defined('TEP_BACKUP_DIR') && TEP_BACKUP_DIR !== '')
			? rtrim(str_replace('\\', '/', (string)TEP_BACKUP_DIR), '/')
			: '/home/easyregpro/private/backups'; // Production CLI path unless TEP_BACKUP_DIR is set
		$zip = new ZipArchive;
		$zip->open($backupDir . '/' . $date . '.zip', ZipArchive::CREATE);
		$stdout = fopen("php://output", "w");

		foreach ($tables as $tableRow) {
			$table = tep_dml_ident($tableRow[0] ?? ''); // Regex-only identifier
			if ($table === null) { // Skip junk names
				continue; // Next
			}
			ob_start();
			$stmt = $pdo->query('SELECT * FROM `' . $table . '`'); // Quoted identifier from SHOW TABLES
			$first = true; // Header row
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { // Each record
				if ($first) { // Column names
					fputcsv($stdout, array_keys($row)); // Header
					$first = false; // Data next
				}
				$data = [];
				foreach ($row as $field) {
					$datum = $field;
					$datum = str_replace("\n", "\\n", $datum);
					$datum = str_replace("\r", "\\r", $datum);
					$data[] = $datum;
				}
				fputcsv($stdout, $data);
			}
			if ($first) { // Empty table — still write headers from DESCRIBE
				$cols = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC); // Identifier quoted
				$fields = array();
				foreach ($cols as $col) {
					$fields[] = $col['Field'];
				}
				if ($fields) {
					fputcsv($stdout, $fields);
				}
			}
			$output = ob_get_clean();
			$zip->addFromString("$table.csv", $output);
		}
		fclose($stdout);
		$zip->close();
	} catch (Throwable $bakEx) { // Connect
		error_log('TEP bin/backup_db.php failed: ' . $bakEx->getMessage()); // Log only
		fwrite(STDERR, "backup failed\n"); // CLI generic
		exit(1); // Non-zero
	}
