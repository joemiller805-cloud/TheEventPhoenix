<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
require_once __DIR__ . '/config/bootstrap.php'; // Secure session before rotate
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound UPDATE
$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
$pass = array();
$alphaLength = strlen($alphabet) - 1;
for ($i = 0; $i < 8; $i++) {
	$n = rand(0, $alphaLength);
	$pass[] = $alphabet[$n];
}
$pass = implode($pass);
$enc = tep_password_hash($pass); // bcrypt for new attendee resets
$inputs = sanitize_inputs($_REQUEST);
$email = (string)($inputs['email'] ?? ''); // Bound
$accountId = tep_session_accountid(); // Session tenant when bound
if ($email === '') { // No target
	print('{"status": "error"}'); // Generic JSON; never SQL
	exit; // Stop
}

try { // Bound password update; tenant when known
	$pdo = tep_dml_pdo(); // utf8mb4
	if ($accountId > 0) { // Logged-in / public event tenant
		$stmt = $pdo->prepare('UPDATE attendees SET password = :pass WHERE email = :email AND accountid = :accountid'); // Tenant-bound
		$stmt->execute(array('pass' => $enc, 'email' => $email, 'accountid' => $accountId)); // No cross-tenant reset
	} else { // No tenant in session — fail closed
		print('{"status": "error"}'); // Do not UPDATE every matching email globally
		exit; // Stop
	}
} catch (Throwable $rstEx) { // Connect / execute
	error_log('TEP attendee password reset failed: ' . $rstEx->getMessage()); // Log only
	print('{"status": "error"}'); // Never echo SQL
	exit; // Stop
}

tep_session_rotate(); // Kill fixation after a successful reset
print('{"status": "success"}'); // Unchanged client contract

try { // Mail the temp password; transport errors stay off HTTP
	$mail = new PHPMailer\PHPMailer\PHPMailer(true); // Exceptions on
	$mail->isSMTP(); // SMTP
	$mail->Host = SMTP_HOST; // Config
	$mail->SMTPAuth = true; // Auth
	$mail->Username = SMTP_USER; // Config
	$mail->Password = SMTP_PASS; // Config
	$mail->SMTPSecure = SMTP_SECURE; // TLS
	$mail->Port = SMTP_PORT; // Port
	$mail->setFrom(tep_mail_from_address(), tep_mail_from_name()); // Product display name
	$mail->isHTML(true); // HTML
	$mail->addAddress($email); // Bound recipient
	$mail->Subject = tep_mail_from_name() . ' - Password Reset'; // Product subject
	$body = 'Your password has been reset for ' . tep_mail_from_name() . ' <br/><br/>'; // Product copy
	$body .= 'New password - ' . $pass; // Temp password in mail only
	$mail->Body = $body; // HTML
	$mail->AltBody = $body; // Text
	$mail->send(); // Send
} catch (Exception $mailEx) { // Transport
	error_log('TEP attendee password reset mail failed: ' . $mailEx->getMessage()); // Log only; HTTP already success
}
