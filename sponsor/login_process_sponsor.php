<?php
include("../common_functions.php");
session_start();
$inputs = sanitize_inputs($_REQUEST);
$sessionAccountId = $_SESSION['accountid'] ?? '';
touch_session_activity(true);
$resourceID = database_connect();
$pass = crypt($inputs['pass'], LEGACY_SALT);
$query = "
	SELECT
		sponsors.id,
		sponsors.name,
		sponsors.accountid
	FROM sponsors
	WHERE lower(sponsors.username) = lower('{$inputs['username']}')
	AND sponsors.pass = '$pass'
	AND sponsors.accountid = '{$sessionAccountId}'
";

$resultID = mysqli_query($resourceID, $query);
if (mysqli_num_rows($resultID)){
	session_unset();
	session_start();
	$_SESSION['roles'] = array();
	while($row = mysqli_fetch_assoc($resultID))	{
		$_SESSION['accountid'] = $row['accountid'];
		$_SESSION['sponsorid'] = $row['id'];
		$_SESSION['name'] = $row['name'];
	}
	touch_session_activity(true);
	print("success");
}else{
	print("error");
}
mysqli_close($resourceID);
?>
