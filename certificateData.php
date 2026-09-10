<?php

include("common_functions.php");
$resourceID = database_connect();
$inputs = sanitize_inputs($_REQUEST);
$confirmations = array();
foreach (explode(",", $inputs['confirmations']) as $confirmation){
	$confirmations[] = "'$confirmation'";
}

$confirmations = implode(",", $confirmations);

$query = "
	SELECT
		events.name event,
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
	WHERE registrations.confirmation in ($confirmations)
	ORDER BY last_name, first_name
";

$resultID = mysqli_query($resourceID, $query);
$attendees = [];
if (mysqli_num_rows($resultID)){
	while ($registration = mysqli_fetch_assoc($resultID)){
		array_push($attendees, $registration);
	}		
}
print_r(json_encode($attendees));
mysqli_close($resourceID);
