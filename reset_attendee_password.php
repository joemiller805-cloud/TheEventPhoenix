<?php 
    require 'phpmailer/PHPMailer.php';
    require 'phpmailer/SMTP.php';
    require 'phpmailer/Exception.php';
    session_start(); 
    include("common_functions.php");
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $pass = array(); 
    $alphaLength = strlen($alphabet) - 1; 
    for ($i = 0; $i < 8; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    $pass = implode($pass);
    $enc = crypt($pass, LEGACY_SALT);

    $resourceID = database_connect();
    $inputs = sanitize_inputs($_REQUEST);
    $query = "UPDATE attendees SET password = '{$enc}' WHERE email = '{$inputs['email']}'";
    $resultID = mysqli_query($resourceID, $query);
    if(!$resultID){
        print('{"status": "error", "query",'. $query .'}');
    }else{
        print('{"status": "success"}');
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = SMTP_HOST;            //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = SMTP_USER;            //SMTP username
    $mail->Password   = SMTP_PASS;                       //SMTP password
    $mail->SMTPSecure = SMTP_SECURE;                                  //Enable implicit TLS encryption
    $mail->Port       = SMTP_PORT;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

    $mail->setFrom('postmaster@easyregpro.com', 'EasyRegPro');
    $mail->isHTML(true);              
    $mail->addAddress($inputs['email']);                        
    $mail->Subject = "EasyRegpro.com - Password Reset";
    $body = "Your password has been reset for easyregpro.com <br/><br/>";
    $body .= "New password - {$pass}";

    $mail->Body    = $body;
    $mail->AltBody = $body;
    $mail->send();
    mysqli_close($resourceID);
?>
