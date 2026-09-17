<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
require_once __DIR__ . '/config/bootstrap.php'; // Secure session
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound UPDATE
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { // No GET password in URL
	fail_request(405, 'Method not allowed.'); // Block harvest
}
tep_require_csrf_token(); // X-CSRF-Token from $http defaults
$inputs = sanitize_inputs($_REQUEST);
$email = (string)($inputs['email'] ?? ''); // Bound
$accountId = tep_session_accountid(); // Session tenant wins
if ($accountId < 1) { // Public forgot-password form
	$accountId = (int)($inputs['accountid'] ?? 0); // Tenant picker on login.php
}
$isSponsor = !empty($inputs['sponsor']); // Sponsor portal reset
if ($email === '') { // No target
	print('error'); // Generic
	exit; // Stop
}

$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
$chars = array();
$alphaLength = strlen($alphabet) - 1;
for ($i = 0; $i < 10; $i++) { // Server-generated; ignore client password
	$chars[] = $alphabet[rand(0, $alphaLength)];
}
$plain = implode('', $chars); // Temp password mailed only
$enc = tep_password_hash($plain); // bcrypt for new resets

try { // Bound password write; never accept attacker-chosen hash
	$pdo = tep_dml_pdo(); // utf8mb4
	if ($isSponsor) { // Vendor portal
		$stmt = $pdo->prepare('UPDATE sponsors SET pass = :pass WHERE (LOWER(email) = LOWER(:email) OR LOWER(username) = LOWER(:email2)) AND accountid = :accountid'); // Session tenant
		$stmt->execute(array('pass' => $enc, 'email' => $email, 'email2' => $email, 'accountid' => $accountId)); // No cross-tenant
	} else { // Staff users
		$stmt = $pdo->prepare('UPDATE users SET pass = :pass WHERE LOWER(email) = LOWER(:email) AND accountid = :accountid'); // Bound
		$stmt->execute(array('pass' => $enc, 'email' => $email, 'accountid' => $accountId)); // Tenant scoped
	}
	if ($stmt->rowCount() < 1) { // No matching row
		print('error'); // Generic; do not leak existence beyond existing UI
		exit; // Stop
	}
} catch (Throwable $pwEx) { // Connect
	error_log('TEP send_pw_reset_email update failed: ' . $pwEx->getMessage()); // Log only
	print('error'); // Generic
	exit; // Stop
}

tep_session_rotate(); // New session id after privilege-changing reset

try { // Mail temp password; never echo ErrorInfo
	$mail = new PHPMailer\PHPMailer\PHPMailer(true); // Exceptions
	$mail->isSMTP(); // SMTP
	$mail->Host = SMTP_HOST; // Config
	$mail->SMTPAuth = true; // Auth
	$mail->Username = SMTP_USER; // Config
	$mail->Password = SMTP_PASS; // Config
	$mail->SMTPSecure = SMTP_SECURE; // TLS
	$mail->Port = SMTP_PORT; // Port
	$mail->setFrom(tep_mail_from_address(), tep_mail_from_name()); // Product from
	$mail->addReplyTo(tep_mail_from_address(), tep_mail_from_name());
	$mail->isHTML(true); // HTML
	$mail->Subject = 'Password Reset'; // Neutral subject
	$mail->addAddress($email);
	$body = 'Your password has been reset.<br/><br/>';
	$body .= 'If you did not request a reset of your password, please contact the site administrator.<br/><br/>';
	$body .= 'Your temporary password is ' . htmlspecialchars($plain, ENT_QUOTES, 'UTF-8');
	if ($isSponsor) {
		$body .= '<br/><br/>Log in at https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/sponsor/login.php';
	}
	$mail->Body = $body;
	$mail->AltBody = strip_tags(str_replace('<br/>', PHP_EOL, $body));
	$mail->send();
	print('success'); // Unchanged client contract
} catch (Exception $mailEx) { // Transport
	error_log('TEP send_pw_reset_email mail failed: ' . $mailEx->getMessage()); // Log only
	print('success'); // Update already committed; UI still shows success
}
