<?php
	require 'phpmailer/PHPMailer.php';
	require 'phpmailer/SMTP.php';
	require 'phpmailer/Exception.php';
	require_once __DIR__ . '/config/bootstrap.php'; // Secure session; no raw session_start
	tep_require_csrf_token(); // X-CSRF-Token; replaces the public send_email_simple relay
	if (!tep_session_has_principal()) { // Anonymous browser mail is closed
		http_response_code(401); // Unauthorized
		print('error'); // erSvc.sendEmail fail-softs
		exit; // Stop
	}
	$inputs = sanitize_inputs($_REQUEST);
	$isMaster = $_SESSION['master'] ?? '';
	$userid = $_SESSION['userid'] ?? '';
	touch_session_activity(true);
	if (!empty($inputs['address'])) { // Former send_email_simple payload (staff/sponsor UI)
		try { // PHPMailer only; no SQL
			$mail = new PHPMailer\PHPMailer\PHPMailer(true); // Same transport
			$mail->isSMTP(); // SMTP
			$mail->Host = SMTP_HOST; // Config
			$mail->SMTPAuth = true; // Auth
			$mail->Username = SMTP_USER; // Config
			$mail->Password = SMTP_PASS; // Config
			$mail->SMTPSecure = SMTP_SECURE; // TLS
			$mail->Port = SMTP_PORT; // Port
			$mail->setFrom(tep_mail_from_address(), tep_mail_from_name()); // Dynamic envelope; display name is The Event Phoenix
			if (!empty($inputs['replytoemail'])) { // Optional reply-to
				$mail->addReplyTo($inputs['replytoemail'], $inputs['replytoemail']); // Posted reply-to
			}
			$mail->isHTML(true); // HTML body
			$mail->Subject = (string)$inputs['subject']; // Posted subject
			$mail->Body = (string)$inputs['body']; // Posted body
			$mail->AltBody = strip_tags(str_replace('<br>', PHP_EOL, (string)$inputs['body'])); // Text
			foreach (explode(',', (string)$inputs['address']) as $addr) { // Recipient list
				$addr = trim($addr); // One address
				if ($addr === '') { // Skip blanks
					continue; // Next
				}
				$mail->clearAddresses(); // One send each
				$mail->addAddress($addr); // Bound by PHPMailer, not SQL
				$mail->send(); // Send
			}
			print('success'); // AngularJS / jQuery callers
		} catch (Exception $e) { // Transport
			error_log('TEP send_email browser path failed: ' . $e->getMessage()); // Log only — never ErrorInfo
			http_response_code(500); // Fail
			print('error'); // Do not echo ErrorInfo
		}
		exit; // Do not fall through to confirmation SQL
	}
	if($isMaster == '1' OR $userid != ''){
		require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound confirmation lookup
		try { // PDO user + tickets; never interpolate confirmation
			$pdo = tep_dml_pdo(); // utf8mb4
			$userStmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = :id AND accountid = :accountid LIMIT 1'); // Bound + tenant
			$userStmt->execute(array('id' => (int)$userid, 'accountid' => tep_session_accountid())); // Session user + tenant
			$user = $userStmt->fetch(PDO::FETCH_ASSOC); // Maybe empty
			$mail = new PHPMailer\PHPMailer\PHPMailer(true); // Exceptions
			$sender = tep_mail_from_name(); // The Event Phoenix unless a posted event sender
			if(!is_null($inputs['sender']) && (string)$inputs['sender'] !== ''){
				$sender = (string)$inputs['sender']; // Event display name
			}
			$mail->isSMTP(); // SMTP
			$mail->Host = SMTP_HOST; // Config
			$mail->SMTPAuth = true; // Auth
			$mail->Username = SMTP_USER; // Config
			$mail->Password = SMTP_PASS; // Config
			$mail->SMTPSecure = SMTP_SECURE; // TLS
			$mail->Port = SMTP_PORT; // Port
			$mail->setFrom(tep_mail_from_address(), $sender); // SMTP mailbox; display name is TEP or event
			$mail->addReplyTo($inputs['replytoemail'], $sender);
			$mail->isHTML(true); // HTML
			$mail->Subject = $_REQUEST['subject'];
			if ($inputs['bcc'] == "1" && !empty($user['email'])) { $mail->addBCC($user['email']); }
			$confStmt = $pdo->prepare('SELECT
					COALESCE(attendees.email, users.email) AS email,
					events.slug AS slug,
					events.id AS eventid,
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
				print('error'); // Fail closed
				exit; // Stop
			}
			foreach (explode(',', $inputs['confirmations']) as $confirmation) {
				$confirmation = trim($confirmation); // One code
				if ($confirmation === '') { continue; } // Skip blanks
				$confStmt->execute(array('confirmation' => $confirmation, 'accountid' => $mailTenant)); // Bound
				$registration = $confStmt->fetch(PDO::FETCH_ASSOC); // One row
				if (!$registration || empty($registration['email'])) { continue; } // Missing
				$mail->clearAddresses();
				$mail->addAddress($registration['email']); // Recipient
				$body = $_REQUEST['body'];
				$body = str_replace("[eventurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/e/{$registration['accountid']}/{$registration['slug']}\">{$_SERVER['HTTP_HOST']}/e/{$registration['accountid']}/{$registration['slug']}</a>", $body);
				$body = str_replace("[registrationurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/e/{$registration['accountid']}/{$registration['slug']}/register/$confirmation\">{$_SERVER['HTTP_HOST']}/e/{$registration['accountid']}/{$registration['slug']}/register/$confirmation</a>", $body);
				$body = str_replace("[sessionsignupurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/e/{$registration['accountid']}/{$registration['slug']}/signup/$confirmation\">{$_SERVER['HTTP_HOST']}/e/{$registration['accountid']}/{$registration['slug']}/signup/$confirmation</a>", $body);
				$body = str_replace("[invoiceurl]", "<a href=\"{$_SERVER['HTTP_HOST']}/events/invoice.php?eventid={$registration['eventid']}&confirmation=$confirmation\">{$_SERVER['HTTP_HOST']}/invoice.php?eventid={$registration['eventid']}&confirmation=$confirmation</a>", $body);
				$body = str_replace("[confirmation]", "$confirmation", $body);
				$mail->Body    = $body;
				$mail->AltBody = $body;
				$mail->send();
				echo 'Sent ' . $registration['email'] . '<br/>';
			}
		} catch (Exception $e) { // PHPMailer or PDO
			error_log('TEP send_email confirmation path failed: ' . $e->getMessage()); // Log only — never ErrorInfo
			echo 'Message could not be sent.'; // Generic
		}
	}else{}
?>
