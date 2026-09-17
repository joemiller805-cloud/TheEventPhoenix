<?php
require_once __DIR__ . '/common_functions.php';
start_secure_session(); // Session tenant for certificate rows
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound IN list
$inputs = sanitize_inputs($_REQUEST);
$in = tep_pdo_in_list(explode(',', (string)($inputs['confirmations'] ?? ''))); // Bound tickets
$attendees = [];
$tenant = tep_session_accountid(); // Session only — never GET accountid
if ($in && $tenant > 0) { // At least one confirmation and a bound tenant
	try { // PDO certificates; never interpolate CSV
		$pdo = tep_dml_pdo(); // utf8mb4
		$stmt = $pdo->prepare('SELECT
				events.name AS event,
				attendees.first_name,
				attendees.last_name,
				registrations.confirmation,
				events.city,
				events.state,
				events.startdate,
				events.enddate,
				events.logo,
				events.certificatemessage,
				events.accountid
			FROM registrations
			JOIN events ON registrations.eventid = events.id
			LEFT JOIN attendees ON attendees.id = registrations.attendeeid
			WHERE registrations.confirmation IN (' . $in['sql'] . ')
			AND events.accountid = ?
			ORDER BY last_name, first_name'); // Bound IN + session tenant
		$params = $in['params']; // Ticket codes
		$params[] = $tenant; // Session accountid as last bound value
		$stmt->execute($params); // Ticket codes as data
		$attendees = $stmt->fetchAll(PDO::FETCH_ASSOC); // Rows
	} catch (Throwable $certEx) { // Connect
		error_log('TEP certificateData failed: ' . $certEx->getMessage()); // Log only
		$attendees = []; // Empty JSON
	}
}
print_r(json_encode($attendees)); // Unchanged client contract
