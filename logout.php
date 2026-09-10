<?php
	include("common_functions.php");
	require_csrf_request();
	$resourceID = database_connect();
	$query = "
		SELECT slug
		FROM accounts 
		WHERE id = '{$_SESSION['accountid']}'
	";
	$resultID = mysqli_query($resourceID, $query);
	$slug = mysqli_fetch_assoc($resultID);
	session_unset();
	session_destroy();
	$redirectTarget = "/login.php/{$slug['slug']}";
	if (!empty($_POST['r']) && substr((string)$_POST['r'], 0, 1) === '/') {
		$redirectTarget .= '?r=' . urlencode((string)$_POST['r']);
	}
	header("Location:{$redirectTarget}");
	mysqli_close($resourceID);
?>
