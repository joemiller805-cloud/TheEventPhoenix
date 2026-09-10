<?php
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$resourceID = database_connect();
	$query = "
		SELECT 
			registrations.confirmation, 
			registrations.id registrationid, 
			registrations.userid, 
			COALESCE(attendees.first_name,users.first_name) AS first_name, 
			COALESCE(attendees.last_name,users.last_name) AS last_name, 
			users.sponsorid, 
			events.accountid, 
			COALESCE(registration_types.sections_allowed, 0) sections_allowed, 
			CASE WHEN 
				(
					SELECT count(*) AS totalExtras
					FROM registration_extra_orders
					WHERE registration_extra_orders.registrationid = registrations.id
				) > 0 THEN true 
				ELSE false
			END AS has_extras
		FROM registrations
		JOIN events ON registrations.eventid = events.id
		LEFT JOIN registration_types ON registration_types.id = registrations.registration_typeid
		LEFT JOIN users ON users.id = registrations.userid
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		WHERE confirmation = '{$inputs['confirmation']}'
		AND (events.slug = '{$inputs['slug']}' OR '{$inputs['slug']}' =  '')
	";

	$resultID = mysqli_query($resourceID, $query);
	$alert = "";
	if (mysqli_num_rows($resultID)){
		session_start();
		$_SESSION['confirmation'] = $inputs['confirmation'];
		$attendee = mysqli_fetch_assoc($resultID);
		$_SESSION['registrationid'] = $attendee['registrationid'];
		$_SESSION['userid'] = $attendee['userid'];
		$_SESSION['accountid'] = $attendee['accountid'];
		$_SESSION['attendee_first'] = str_replace('"', "", $attendee['first_name']);
		$_SESSION['attendee_last'] = str_replace('"', "",$attendee['last_name']);
		$_SESSION['sponsor_staff'] = $attendee['sponsorid'] != '';
		$_SESSION['sections_allowed'] = $attendee['sections_allowed'];
		$_SESSION['has_extras'] = $attendee['has_extras'];
		touch_session_activity(true);
		print("{\"registrationid\":{$attendee['registrationid']}, \"attendee_first\":\"{$_SESSION['attendee_first']}\",\"attendee_last\":\"{$_SESSION['attendee_last']}\"}");
	}else{
		$alert = "&alert-danger=The confirmation number {$inputs['confirmation']} could not be found.";
	}
	if($inputs['r']) header("Location:{$inputs['r']}?$alert");
	mysqli_close($resourceID);
?>
