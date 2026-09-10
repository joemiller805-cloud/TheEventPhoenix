<?php
session_start();
include("common_functions.php");
$inputs = sanitize_inputs($_REQUEST);
$isErSupport = $_SESSION['erSupport'] ?? '';
touch_session_activity(true);
if($isErSupport != 'true'){
	header('Location: /login_er.php');
} 
$resourceID = database_connect();

$query = "
	SELECT
		users.id,
		REPLACE(users.first_name,'\"','') AS first_name,
		REPLACE(users.last_name,'\"','') AS last_name,
		users.email,
		users.master,
		users.accountid,
		accounts.type
	FROM users
	JOIN accounts ON accounts.id = users.accountid
	WHERE lower(users.email) = lower('support@easyregpro.com')
	AND users.accountid = {$inputs['accountid']}
";

$resultID = mysqli_query($resourceID, $query);
if (mysqli_num_rows($resultID)){
	session_start();
	$_SESSION['last_activity'] = time();
	$_SESSION['roles'] = array();
	while($row = mysqli_fetch_assoc($resultID))	{
		$_SESSION['accountid'] = $row['accountid'];
		if($_SESSION['accountid'] == '') print ('error acct');
		$_SESSION['acctType'] = $row['type'];
		$_SESSION['userid'] = $row['id'];
		$_SESSION['useraccount'] = $row['accountid'];
		$_SESSION['name'] = $row['first_name']. " ". $row['last_name'];
		$_SESSION['master'] = $row['master'];
		$_SESSION['pageAccess'] = "";
		$_SESSION['eventAccess'] = "";
		$_SESSION['tableAccess'] = "";
	}
	touch_session_activity(true);
	print("success");
}else{
	print("error");
}
mysqli_close($resourceID);
?>
