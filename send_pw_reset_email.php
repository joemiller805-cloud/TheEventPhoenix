<?php
	require 'phpmailer/PHPMailer.php';
	require 'phpmailer/SMTP.php';
	require 'phpmailer/Exception.php';
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);

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
	$mail->Subject = "Easyregpro Password Reset"; 
	$mail->addAddress($inputs['email']); 

	$body = "Your password for easyregpro has been reset.<br/><br/>";
	$body .= "If you did not request a reset of your password, please contact the site administrator.<br/><br/>";
	$body .= "Your temporary password is {$inputs['password']}";
	if(isset($_REQUEST['link'])) {
		$body .= "<br/><br/>Log in at http://" . $_SERVER['HTTP_HOST'] . "/sponsor/login.php";
	}
	$mail->Body    = $body;
	$mail->AltBody = $body;
	$mail->send();
?>
