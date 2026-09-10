<?php
session_start();
include("common_functions.php");
$inputs = sanitize_inputs($_REQUEST);
$sessionAccountId = $_SESSION['accountid'] ?? '';
touch_session_activity(true);
$resourceID = database_connect();

$query = "
	SELECT pages.slug
	FROM pages
	JOIN events ON pages.eventid = events.id
	WHERE events.slug = '{$inputs['slug']}'
	AND (
		events.accountid = '{$sessionAccountId}'
		OR
		events.accountid = '{$inputs['accountid']}'
		OR 
		'{$inputs['accountid']}' = ''
	)
	ORDER BY pages.home desc, pages.name asc
	limit 1
";
$resultID = mysqli_query($resourceID, $query);
$page = mysqli_fetch_assoc($resultID);
$eventPath = "/e/" . ($inputs['accountid'] ? "{$inputs['accountid']}/" : '') . $inputs['slug'];
if($inputs['accountid']){
	session_start();
	$_SESSION['accountid'] = $inputs['accountid'];
	touch_session_activity(true);
}
if(!mysqli_num_rows($resultID)){
	header("Location:{$eventPath}/register");
}else{
	header("Location:{$eventPath}/{$page['slug']}");
}
mysqli_close($resourceID);
?>
