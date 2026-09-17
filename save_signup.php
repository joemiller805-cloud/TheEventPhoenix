<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound signup DML
$inputs = sanitize_inputs($_REQUEST);
$confirmation = (string)($inputs['confirmation'] ?? ''); // Bound ticket
$eventId = (int)($inputs['eventid'] ?? 0); // Bound event
$slug = (string)($inputs['slug'] ?? ''); // Redirect slug
$saved_sessions = array(); // Session ids kept
$alerts = array();

try { // PDO; never interpolate confirmation/section/session
	$pdo = tep_dml_pdo(); // utf8mb4
	$regStmt = $pdo->prepare('SELECT registrations.id FROM registrations
		JOIN events ON events.id = registrations.eventid
		WHERE registrations.eventid = :eventid AND registrations.confirmation = :confirmation
		AND events.accountid = :accountid
		LIMIT 1'); // Bound ticket + session tenant
	$tenant = tep_session_accountid(); // Session only
	if ($tenant < 1) { // No tenant bound
		header('Location:/e/' . rawurlencode($slug) . '/signup/' . rawurlencode($confirmation) . '?alert-danger=Registration not found'); // Fail closed
		exit; // Stop
	}
	$regStmt->execute(array('eventid' => $eventId, 'confirmation' => $confirmation, 'accountid' => $tenant)); // Lookup
	$registrationId = (int)$regStmt->fetchColumn(); // 0 if missing
	if ($registrationId < 1) { // Unknown ticket
		header('Location:/e/' . rawurlencode($slug) . '/signup/' . rawurlencode($confirmation) . '?alert-danger=Registration not found'); // Existing redirect style
		exit; // Stop
	}

	foreach ($inputs as $key => $sectionid) { // Posted session_* fields
		if (substr($key, 0, 8) !== 'session_') { // Not a session pick
			continue; // Next
		}
		$sessionid = (int)substr($key, 8); // Session id from field name
		$sectionid = (int)$sectionid; // Posted section
		if ($sessionid < 1 || $sectionid < 1) { // Junk
			continue; // Skip
		}
		$saved_sessions[] = $sessionid; // Keep for DELETE NOT IN

		$capStmt = $pdo->prepare('SELECT
				COUNT(DISTINCT registrationid) AS registrations,
				sections.capacity,
				rooms.capacity AS roomcapacity,
				courses.name AS coursename,
				sessions.name AS sessionname
			FROM signups
			JOIN sections ON signups.sectionid = sections.id
			JOIN rooms ON sections.roomid = rooms.id
			JOIN courses ON sections.courseid = courses.id
			JOIN sessions ON sections.sessionid = sessions.id
			JOIN registrations ON signups.registrationid = registrations.id
			WHERE sectionid = :sectionid
			AND registrations.confirmation != :confirmation
			GROUP BY signups.sectionid, sections.capacity, rooms.capacity, courses.name, sessions.name'); // Bound section + ticket
		$capStmt->execute(array('sectionid' => $sectionid, 'confirmation' => $confirmation)); // Capacity
		$row = $capStmt->fetch(PDO::FETCH_ASSOC); // Maybe empty (no other signups)
		$capacity = isset($row['capacity']) ? (int)$row['capacity'] : 0; // Section cap
		if (isset($row['capacity']) && (int)$row['capacity'] === -1) { // Room fallback
			$capacity = (int)$row['roomcapacity']; // Room cap
		}
		$taken = isset($row['registrations']) ? (int)$row['registrations'] : 0; // Others in section
		if ($capacity === 0 || $taken < $capacity || $row === false) { // Room in the original 0==capacity OR under cap; empty row means no others
			$upStmt = $pdo->prepare('UPDATE signups SET sectionid = :sectionid
				WHERE registrationid = :registrationid AND sessionid = :sessionid'); // Bound update
			$upStmt->execute(array( // No concat
				'sectionid' => $sectionid, // New section
				'registrationid' => $registrationId, // Ticket row
				'sessionid' => $sessionid, // Session
			));
			$chkStmt = $pdo->prepare('SELECT id FROM signups WHERE registrationid = :registrationid AND sessionid = :sessionid LIMIT 1'); // Bound exists
			$chkStmt->execute(array('registrationid' => $registrationId, 'sessionid' => $sessionid)); // Lookup
			if (!$chkStmt->fetchColumn()) { // No row yet
				$insStmt = $pdo->prepare('INSERT INTO signups (registrationid, sessionid, sectionid) VALUES (:registrationid, :sessionid, :sectionid)'); // Bound insert
				$insStmt->execute(array( // No subquery concat
					'registrationid' => $registrationId, // Ticket
					'sessionid' => $sessionid, // Session
					'sectionid' => $sectionid, // Section
				));
			}
		} else {
			$alerts[] = 'alert-danger=' . rawurlencode($row['coursename'] . ' during ' . $row['sessionname'] . ' is already at capacity.'); // Existing alert
		}
	}

	if (count($saved_sessions) > 0) { // Keep selected sessions
		$in = tep_pdo_in_list($saved_sessions); // Bound IN list
		$delSql = 'DELETE FROM signups WHERE registrationid = ? AND sessionid NOT IN (' . $in['sql'] . ')'; // Bound
		$delStmt = $pdo->prepare($delSql); // Prepared
		$delStmt->execute(array_merge(array($registrationId), $in['params'])); // registrationid then session ids
	} else { // Nothing selected — clear remaining signups for this ticket
		$delAll = $pdo->prepare('DELETE FROM signups WHERE registrationid = :registrationid'); // Bound
		$delAll->execute(array('registrationid' => $registrationId)); // Clear
	}
} catch (Throwable $signEx) { // Connect / execute
	error_log('TEP save_signup failed: ' . $signEx->getMessage()); // Log only — never mysqli_error
	$alerts[] = 'alert-danger=Your session selections could not be saved.'; // Generic
}

if (0 == sizeof($alerts)) { // Success copy
	$alerts[] = 'alert-success=Your session selections have been saved'; // Unchanged
}
$alerts = implode('&', $alerts); // Query string
header('Location:/e/' . rawurlencode($slug) . '/signup/' . rawurlencode($confirmation) . '?' . $alerts); // Existing redirect
