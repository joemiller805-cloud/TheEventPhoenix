<?php
	require 'phpmailer/PHPMailer.php';
	require 'phpmailer/SMTP.php';
	require 'phpmailer/Exception.php';
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$confirmation = (string)$inputs['confirmation'];
	$resourceID = database_connect();
	$query = "
		SELECT
			events.name event,
			slug,
			replytoemail, 
			registration_message
		FROM events
		WHERE id = {$inputs['eventid']}
	";
	$resultID = mysqli_query($resourceID, $query);
	$event = mysqli_fetch_assoc($resultID);

	$sender = 'EasyRegPro';
	if(!is_null($inputs['sender'])){
		$sender = $inputs['sender'];
	}
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);
	$mail->isSMTP();                                            //Send using SMTP
	$mail->Host       = SMTP_HOST;            //Set the SMTP server to send through
	$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
	$mail->Username   = SMTP_USER;              //SMTP username
	$mail->Password   = SMTP_PASS;                            //SMTP password
	$mail->SMTPSecure = SMTP_SECURE;            						//Enable implicit TLS encryption
	$mail->Port       = SMTP_PORT;  
	$mail->setFrom('postmaster@easyregpro.com', $sender);
	$mail->addReplyTo('postmaster@easyregpro.com', $sender);
	$mail->isHTML(true);                                  		
	$mail->Subject = "{$event['event']} Registration"; 
	$mail->addAddress($inputs['email']); 

	$body  = "<p>{$inputs['first_name']} {$inputs['last_name']},</p>";
	$body .= "<p>Your order for {$event['event']} has been received.</p>";
	$body .= "<p>Your information can be updated at the following URL: <a href=\"https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/register/$confirmation\">https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/register/$confirmation</a></p>";
	if($inputs['includeOrderSummary'] = 'true'){
		$body .= "<p>Your summary of ordered tickets is available at the following URL: <a href=\"https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/order_summary\">https://{$_SERVER['HTTP_HOST']}/e/{$event['slug']}/order_summary</a></p>";
		$body .= "<p>When visiting the order summary, you will be prompted to enter your confirmation number <b>$confirmation</b>";
	}
	$body .= "<p>{$event['registration_message']}</p>";
	$mail->Body    = $body;
	$mail->AltBody = $body;
	session_start();
	$_SESSION['confirmation'] = $confirmation;
	touch_session_activity(true);
	$mail->send();
	mysqli_close($resourceID);
?>
