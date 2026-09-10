<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
include("common_functions.php");
$inputs = sanitize_inputs($_REQUEST);
$resourceID = database_connect();

$query = "
	SELECT
		registrations.confirmation,
		events.name AS event,
		CASE WHEN LENGTH (events.replytoemail) = 0 then 'noreply@easyregpro.com' ELSE events.replytoemail END AS replytoemail,
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
	WHERE registrations.eventid = {$inputs['eventid']}
	AND COALESCE(users.email, attendees.email) = '{$inputs['email']}'
";

$resultID = mysqli_query($resourceID, $query);

if (mysqli_num_rows($resultID)){
	$registration = mysqli_fetch_assoc($resultID);

	$mail = new PHPMailer\PHPMailer\PHPMailer(true);
	$mail->isSMTP();                                            //Send using SMTP
	$mail->Host       = SMTP_HOST;            //Set the SMTP server to send through
	$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
	$mail->Username   = SMTP_USER;              //SMTP username
	$mail->Password   = SMTP_PASS;                            //SMTP password
	$mail->SMTPSecure = SMTP_SECURE;            						//Enable implicit TLS encryption
	$mail->Port       = SMTP_PORT;  
	$mail->setFrom('postmaster@easyregpro.com', $registration['event']);
	$mail->addReplyTo('postmaster@easyregpro.com', $registration['event']);
	$mail->isHTML(true);                                  		
	$mail->Subject = "Registration for {$registration['event']}"; 
	$mail->addAddress($registration['email']); 

	$body = "";
	foreach ($registration as $key => $value){
		if ($key != "data") $body .= "$key: $value<br/>";
	}
	$mail->Body    = $body;
	$mail->AltBody = $body;
	$mail->send();
	mysqli_close($resourceID);
	print "success";
}else{
	mysqli_close($resourceID);
	print "error";
}
?>
