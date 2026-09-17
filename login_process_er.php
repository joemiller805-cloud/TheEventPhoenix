<?php
	include __DIR__ . '/common_functions.php'; // Cookie flags before any session_start
	start_secure_session(); // SameSite=Lax + HTTPS-aware Secure
	session_unset(); // Drop a prior identity before the super-user check
	session_destroy(); // New id after a failed/success path starts clean
	start_secure_session(); // Fresh session with HttpOnly / SameSite=Lax / Secure-on-HTTPS
	$inputs = sanitize_inputs($_REQUEST); // Trim only
	$plain = (string)($_POST['pass'] ?? ''); // Plaintext; never echo
	$user = (string)($_POST['user'] ?? ''); // Posted username

	if($user == 'admin4er' && tep_password_verify($plain, 'PSiGgn773vaeo')){ // Existing ER hash; bcrypt-ready if rotated
		tep_login_regenerate(TEP_ROLE_SUPPORT); // New session id; support role
		$_SESSION['erSupport'] = 'true'; // Super-user flag used by select_all_table_recs
		$_SESSION['erMaster'] = 'true'; // Existing accounts.php gate
		$_SESSION['master'] = '1'; // Staff master
		ensure_session_csrf_token(); // Token for later AngularJS posts
		print('success'); // Unchanged response for login_er.php
	}else{
		print('error'); // Unchanged failure body
	}
?>
