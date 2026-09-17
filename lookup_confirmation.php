<?php
require_once __DIR__ . '/config/bootstrap.php'; // Secure session before privilege grant
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound confirmation lookup
$inputs = sanitize_inputs($_REQUEST);
$confirmation = (string)($inputs['confirmation'] ?? ''); // Bound ticket
$slug = (string)($inputs['slug'] ?? ''); // Bound event slug
$alert = ''; // Redirect query

try { // PDO ticket login; never interpolate confirmation/slug
	$pdo = tep_dml_pdo(); // utf8mb4
	$stmt = $pdo->prepare("SELECT
			registrations.confirmation,
			registrations.id AS registrationid,
			registrations.userid,
			COALESCE(attendees.first_name, users.first_name) AS first_name,
			COALESCE(attendees.last_name, users.last_name) AS last_name,
			users.sponsorid,
			events.accountid,
			COALESCE(registration_types.sections_allowed, 0) AS sections_allowed,
			CASE WHEN (
				SELECT COUNT(*) AS totalExtras
				FROM registration_extra_orders
				WHERE registration_extra_orders.registrationid = registrations.id
			) > 0 THEN true ELSE false END AS has_extras
		FROM registrations
		JOIN events ON registrations.eventid = events.id
		LEFT JOIN registration_types ON registration_types.id = registrations.registration_typeid
		LEFT JOIN users ON users.id = registrations.userid
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		WHERE confirmation = :confirmation
		AND (events.slug = :slug OR :slug_empty = '')
		LIMIT 1"); // Bound ticket + slug
	$stmt->execute(array( // No concatenated WHERE
		'confirmation' => $confirmation, // Posted ticket
		'slug' => $slug, // Posted slug
		'slug_empty' => $slug, // Same value for empty-slug branch
	));
	$attendee = $stmt->fetch(PDO::FETCH_ASSOC); // One row or none
} catch (Throwable $confEx) { // Connect / missing joins
	error_log('TEP confirmation lookup failed: ' . $confEx->getMessage()); // Log only
	$attendee = false; // Treat as not found
}

if ($attendee) { // Valid ticket
	tep_login_regenerate(TEP_ROLE_ATTENDEE); // New session id; attendee, not staff
	$_SESSION['confirmation'] = $confirmation; // Ticket
	$_SESSION['registrationid'] = $attendee['registrationid']; // Principal
	$_SESSION['userid'] = $attendee['userid']; // May be empty for attendee-only regs
	$_SESSION['accountid'] = $attendee['accountid']; // Tenant from events join
	$_SESSION['attendee_first'] = str_replace('"', '', (string)$attendee['first_name']); // Display
	$_SESSION['attendee_last'] = str_replace('"', '', (string)$attendee['last_name']); // Display
	$_SESSION['sponsor_staff'] = $attendee['sponsorid'] != ''; // Existing flag
	$_SESSION['sections_allowed'] = $attendee['sections_allowed']; // Existing
	$_SESSION['has_extras'] = $attendee['has_extras']; // Existing
	touch_session_activity(true); // Idle timer
	$first = $_SESSION['attendee_first']; // JSON body
	$last = $_SESSION['attendee_last']; // JSON body
	print('{"registrationid":' . (int)$attendee['registrationid'] . ', "attendee_first":' . json_encode($first) . ',"attendee_last":' . json_encode($last) . '}'); // Safer JSON than interpolated quotes
} else {
	$alert = '&alert-danger=The confirmation number ' . rawurlencode($confirmation) . ' could not be found.'; // Redirect message
}
if (!empty($inputs['r'])) { // Existing redirect contract
	header('Location:' . $inputs['r'] . '?' . $alert); // Unchanged Location behavior
}
