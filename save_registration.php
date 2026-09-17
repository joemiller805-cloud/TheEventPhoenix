<?php
	require 'phpmailer/PHPMailer.php';
	require 'phpmailer/SMTP.php';
	require 'phpmailer/Exception.php';
	include("common_functions.php");
	start_secure_session(); // Session tenant for the event row
	require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound event lookup
	$inputs = sanitize_inputs($_REQUEST);
	$confirmation = (string)$inputs['confirmation'];
	$eventId = (int)($inputs['eventid'] ?? 0); // Bound
	$tenant = tep_session_accountid(); // Session only
	if ($tenant < 1 || $eventId < 1) { // Missing tenant or event
		exit; // Fail closed
	}
	try { // PDO event row; never interpolate eventid
		$pdo = tep_dml_pdo(); // utf8mb4
		$stmt = $pdo->prepare('SELECT events.name AS event, slug, replytoemail, registration_message FROM events WHERE id = :eventid AND accountid = :accountid LIMIT 1'); // Bound + tenant
		$stmt->execute(array('eventid' => $eventId, 'accountid' => $tenant)); // Session tenant
		$event = $stmt->fetch(PDO::FETCH_ASSOC); // One row
	} catch (Throwable $regEx) { // Connect
		error_log('TEP save_registration event lookup failed: ' . $regEx->getMessage()); // Log only
		$event = false; // Fall through
	}
	if (!$event) { // Missing event
		exit; // Stop without sending
	}

	$sender = tep_mail_from_name(); // Sweep B legal display name
	if(!empty($inputs['sender'])){
		$sender = (string)$inputs['sender']; // Event override
	}
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);
	$mail->isSMTP();                                            //Send using SMTP
	$mail->Host       = SMTP_HOST;            //Set the SMTP server to send through
	$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
	$mail->Username   = SMTP_USER;              //SMTP username
	$mail->Password   = SMTP_PASS;                            //SMTP password
	$mail->SMTPSecure = SMTP_SECURE;            						//Enable implicit TLS encryption
	$mail->Port       = SMTP_PORT;  
	$mail->setFrom(tep_mail_from_address(), $sender); // SMTP mailbox; display name is TEP or event
	$mail->addReplyTo(tep_mail_from_address(), $sender);
	$mail->isHTML(true);                                  		
	$mail->Subject = "{$event['event']} Registration"; 
	$mail->addAddress($inputs['email']);    

	$body  = "<p>{$inputs['first_name']} {$inputs['last_name']},</p>";
	$body .= "<p>Your registration for {$event['event']} has been received.</p>";
	$body .= "<p>Registration information can be updated at the following URL: <a href=\"https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/register/$confirmation\">https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/register/$confirmation</a></p>";
	if($inputs['schedAvailable'] != 'false'){
		$body .= "<p>Your schedule (for printing invoices, session schedules, and certificates of participation) is available at the following URL: <a href=\"https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/attendee_schedule/$confirmation\">https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/attendee_schedule/$confirmation</a></p>";
	}
	if($inputs['includeOrderSummary'] = 'true'){
		$body .= "<p>Your summary of ordered items including redemption tickets is available at the following URL: <a href=\"https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/order_summary\">https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/order_summary</a></p>";
		$body .= "<p>When visiting the order summary, you will be prompted to enter your confirmation number <b>$confirmation</b>";
	}
	$body .= "<p>{$event['registration_message']}</p>";
	$mail->Body    = $body;
	$mail->AltBody = $body;
	session_start();
	$_SESSION['confirmation'] = $confirmation;
	touch_session_activity(true);
	$mail->send();
	// mysqli handle removed; PDO is request-scoped
?>
