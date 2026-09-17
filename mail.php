<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
require_once __DIR__ . '/config/bootstrap.php'; // Secure session
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound lookups
$inputs = sanitize_inputs($_REQUEST);
$master = $_SESSION['master'] ?? '';
$userid = (int)($_SESSION['userid'] ?? 0); // Bound
if ($master != '1' && $userid < 1) { // Existing staff gate
	exit; // Stop
}

try { // PDO user + confirmation lookups
	$pdo = tep_dml_pdo(); // utf8mb4
	$userStmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = :id AND accountid = :accountid LIMIT 1'); // Bound + tenant
	$userStmt->execute(array('id' => $userid, 'accountid' => tep_session_accountid())); // Session user + tenant
	$user = $userStmt->fetch(PDO::FETCH_ASSOC); // Maybe empty
	$mail = new PHPMailer\PHPMailer\PHPMailer(true); // Exceptions
	$mail->isSMTP(); // SMTP
	$mail->Host = SMTP_HOST; // Config
	$mail->SMTPAuth = true; // Auth
	$mail->Username = SMTP_USER; // Config
	$mail->Password = SMTP_PASS; // Config
	$mail->SMTPSecure = SMTP_SECURE; // TLS
	$mail->Port = SMTP_PORT; // Port
	$mail->setFrom($inputs['replytoemail'], 'Mailer'); // Existing from
	$mail->addReplyTo($inputs['replytoemail'], 'Information'); // Reply-to
	$mail->isHTML(true); // HTML
	$mail->Subject = $_REQUEST['subject'] ?? ''; // Existing subject
	if (($inputs['bcc'] ?? '') == '1' && !empty($user['email'])) { // Optional BCC
		$mail->addBCC($user['email']); // Staff copy
	}
	$confStmt = $pdo->prepare('SELECT
			COALESCE(attendees.email, users.email) AS email,
			events.slug AS slug,
			events.accountid AS accountid
		FROM registrations
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		LEFT JOIN users ON users.id = registrations.userid
		JOIN events ON events.id = registrations.eventid
		WHERE registrations.confirmation = :confirmation
		AND events.accountid = :accountid
		LIMIT 1'); // Bound ticket + session tenant
	$mailTenant = tep_session_accountid(); // Session only
	if ($mailTenant < 1) { // No tenant
		exit; // Fail closed
	}
	foreach (explode(',', (string)($inputs['confirmations'] ?? '')) as $confirmation) { // Each ticket
		$confirmation = trim($confirmation); // One code
		if ($confirmation === '') { // Skip blanks
			continue; // Next
		}
		$confStmt->execute(array('confirmation' => $confirmation, 'accountid' => $mailTenant)); // Bound
		$registration = $confStmt->fetch(PDO::FETCH_ASSOC); // One row
		if (!$registration || empty($registration['email'])) { // Missing
			continue; // Next ticket
		}
		$mail->clearAddresses(); // One send each
		$mail->addAddress($registration['email']); // Recipient
		$host = $_SERVER['HTTP_HOST'] ?? ''; // Host
		$acct = $registration['accountid']; // Tenant
		$slug = $registration['slug']; // Event
		$body = $_REQUEST['body'] ?? ''; // Template
		$eventUrl = $host . '/e/' . $acct . '/' . $slug; // Existing placeholder
		$body = str_replace('[eventurl]', '<a href="' . $eventUrl . '">' . $eventUrl . '</a>', $body); // Placeholder
		$regUrl = $eventUrl . '/register/' . $confirmation; // Register
		$body = str_replace('[registrationurl]', '<a href="' . $regUrl . '">' . $regUrl . '</a>', $body); // Placeholder
		$signUrl = $eventUrl . '/signup/' . $confirmation; // Signup
		$body = str_replace('[sessionsignupurl]', '<a href="' . $signUrl . '">' . $signUrl . '</a>', $body); // Placeholder
		$invUrl = $host . '/events/invoice.php?confirmation=' . $confirmation; // Invoice
		$body = str_replace('[invoiceurl]', '<a href="' . $invUrl . '">' . $host . '/invoice.php?confirmation=' . $confirmation . '</a>', $body); // Placeholder
		$body = str_replace('[confirmation]', $confirmation, $body); // Ticket
		$mail->Body = $body; // HTML
		$mail->AltBody = $body; // Text
		$mail->send(); // Send
		echo 'Message has been sent ' . $registration['email']; // Existing success echo
	}
} catch (Exception $e) { // PHPMailer or PDO
	error_log('TEP mail.php failed: ' . $e->getMessage()); // Log only — never ErrorInfo on HTTP
	echo 'Message could not be sent.'; // Generic
}
