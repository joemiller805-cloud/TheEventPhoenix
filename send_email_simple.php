<?php
	require 'phpmailer/PHPMailer.php';
	require 'phpmailer/SMTP.php';
	require 'phpmailer/Exception.php';
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);
	try {
		$mail->isSMTP();                                            //Send using SMTP
		$mail->Host       = SMTP_HOST;            //Set the SMTP server to send through
		$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
		$mail->Username   = SMTP_USER;              //SMTP username
		$mail->Password   = SMTP_PASS;                            //SMTP password
		$mail->SMTPSecure = SMTP_SECURE;            						//Enable implicit TLS encryption
		$mail->Port       = SMTP_PORT;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

		$mail->setFrom('postmaster@easyregpro.com', 'EasyRegPro');
		if(!empty($inputs['replytoemail'])){
			$mail->addReplyTo($inputs['replytoemail'], $inputs['replytoemail']);
		}
		$mail->isHTML(true);                                  		//Set email format to HTML
		$mail->Subject = $inputs['subject'];
		$mail->Body    = $inputs['body'];
		$mail->AltBody = strip_tags(str_replace("<br>", PHP_EOL, $inputs['body']));

		foreach(explode(",", $inputs['address']) as $addr){
			$addr = trim($addr);
			if($addr === '') continue;
			$mail->clearAddresses();
			$mail->addAddress($addr); //Add a recipient
			$mail->send();
		}
	} catch (Exception $e) {
		http_response_code(500);
		echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
	}
?>
