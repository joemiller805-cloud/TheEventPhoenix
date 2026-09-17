<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
require_once __DIR__ . '/config/bootstrap.php'; // Secure session
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound SELECT
$inputs = sanitize_inputs($_REQUEST);
$eventId = (int)($inputs['eventid'] ?? 0); // Bound
$email = (string)($inputs['email'] ?? ''); // Bound
$tenant = tep_session_accountid(); // Session tenant — never posted accountid
if ($eventId < 1 || $email === '' || $tenant < 1) { // Incomplete or no tenant
	print('error'); // Unchanged
	exit; // Fail closed
}

try { // Bound confirmation lookup
	$pdo = tep_dml_pdo(); // utf8mb4
	$stmt = $pdo->prepare("SELECT
			registrations.confirmation,
			events.name AS event,
			CASE WHEN LENGTH(events.replytoemail) = 0 THEN 'noreply@easyregpro.com' ELSE events.replytoemail END AS replytoemail,
			attendees.email,
			attendees.first_name,
			attendees.last_name,
			attendees.business,
			registration_types.name AS registration_type,
			registration_types.price
		FROM registrations
		JOIN registration_types ON registrations.registration_typeid = registration_types.id
		JOIN events ON events.id = registrations.eventid
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		LEFT JOIN users ON users.id = registrations.userid
		WHERE registrations.eventid = :eventid
		AND events.accountid = :accountid
		AND COALESCE(users.email, attendees.email) = :email
		LIMIT 1"); // Bound event + session tenant + email
	$stmt->execute(array('eventid' => $eventId, 'accountid' => $tenant, 'email' => $email)); // No concat
	$registration = $stmt->fetch(PDO::FETCH_ASSOC); // One row or none
} catch (Throwable $confEx) { // Connect
	error_log('TEP send_confirmation lookup failed: ' . $confEx->getMessage()); // Log only
	print('error'); // Generic
	exit; // Stop
}

if (!$registration) { // Not found
	print('error'); // Unchanged
	exit; // Stop
}

try { // Mail; never echo ErrorInfo
	$mail = new PHPMailer\PHPMailer\PHPMailer(true); // Exceptions
	$mail->isSMTP(); // SMTP
	$mail->Host = SMTP_HOST; // Config
	$mail->SMTPAuth = true; // Auth
	$mail->Username = SMTP_USER; // Config
	$mail->Password = SMTP_PASS; // Config
	$mail->SMTPSecure = SMTP_SECURE; // TLS
	$mail->Port = SMTP_PORT; // Port
	$mail->setFrom(tep_mail_from_address(), $registration['event']); // SMTP mailbox; display name is the event
	$mail->addReplyTo(tep_mail_from_address(), $registration['event']);
	$mail->isHTML(true); // HTML
	$mail->Subject = 'Registration for ' . $registration['event'];
	$mail->addAddress($registration['email']);
	$body = '';
	foreach ($registration as $key => $value) {
		if ($key != 'data') $body .= "$key: $value<br/>";
	}
	$mail->Body = $body;
	$mail->AltBody = $body;
	$mail->send();
	print('success'); // Unchanged
} catch (Exception $mailEx) { // Transport
	error_log('TEP send_confirmation mail failed: ' . $mailEx->getMessage()); // Log only
	print('error'); // Generic
}
