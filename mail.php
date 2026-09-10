<?php
	require 'phpmailer/PHPMailer.php';
	require 'phpmailer/SMTP.php';
	require 'phpmailer/Exception.php';
	session_start();
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	if($_SESSION['master'] == '1' OR $_SESSION['userid'] != ''){
		$resourceID = database_connect();
		$query = "
			SELECT first_name, last_name, email
			FROM users
			WHERE id = {$_SESSION['userid']}
		";
		$resultID = mysqli_query($resourceID, $query);
		$user = mysqli_fetch_assoc($resultID);

		//Create an instance; passing `true` enables exceptions
		$mail = new PHPMailer\PHPMailer\PHPMailer(true);

		try {
			//Server settings
			$mail->isSMTP();                                            //Send using SMTP
			$mail->Host       = SMTP_HOST;            //Set the SMTP server to send through
			$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
			$mail->Username   = SMTP_USER;              //SMTP username
			$mail->Password   = SMTP_PASS;                            //SMTP password
			$mail->SMTPSecure = SMTP_SECURE;            						//Enable implicit TLS encryption
			$mail->Port       = SMTP_PORT;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

			$mail->setFrom($inputs['replytoemail'], 'Mailer');
			$mail->addReplyTo($inputs['replytoemail'], 'Information');
			$mail->isHTML(true);                                  		//Set email format to HTML
			$mail->Subject = $_REQUEST['subject'];
			if ($inputs['bcc'] == "1"){	$mail->addBCC($user['email']); }

			foreach(explode(",", $inputs['confirmations']) as $confirmation){
				$query = "
					SELECT 
						COALESCE(attendees.email,users.email) AS email, 
						events.slug AS slug,
						events.accountid AS accountid
					FROM registrations
					LEFT JOIN attendees ON attendees.id = registrations.attendeeid
					LEFT JOIN users ON users.id = registrations.userid
					JOIN events ON events.id = registrations.eventid
					WHERE registrations.confirmation = '$confirmation'
				";
				$resultID = mysqli_query($resourceID, $query);
				$registration = mysqli_fetch_assoc($resultID);
				$mail->clearAddresses();
				$mail->addAddress($registration['email']); //Add a recipient
				
				$body = $_REQUEST['body'];
				$body = str_replace("[eventurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/e/{$registrations['accountid']}/{$registration['slug']}\">{$_SERVER['HTTP_HOST']}/e/{$registrations['accountid']}/{$registration['slug']}</a>", $body);
				$body = str_replace("[registrationurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/e/{$registrations['accountid']}/{$registration['slug']}/register/$confirmation\">{$_SERVER['HTTP_HOST']}/e/{$registrations['accountid']}/{$registration['slug']}/register/$confirmation</a>", $body);
				$body = str_replace("[sessionsignupurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/e/{$registrations['accountid']}/{$registration['slug']}/signup/$confirmation\">{$_SERVER['HTTP_HOST']}/e/{$registrations['accountid']}/{$registration['slug']}/signup/$confirmation</a>", $body);
				$body = str_replace("[invoiceurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/events/invoice.php?confirmation=$confirmation\">{$_SERVER['HTTP_HOST']}/invoice.php?confirmation=$confirmation</a>", $body);
				$body = str_replace("[confirmation]", "$confirmation", $body);
				$mail->Body    = $body;
				$mail->AltBody = $body;
				$mail->send();
				echo 'Message has been sent ' . $registration['email'];
			}
			mysqli_close($resourceID);
			

			// //Attachments
			// $mail->addAttachment('/var/tmp/file.tar.gz');         		//Add attachments
			// $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    		//Optional name			
		} catch (Exception $e) {
			echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		}
	}
	else{}
?>