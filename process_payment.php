<?php
	include("common_functions.php");
	start_secure_session(); // Session tenant for gateway keys — never posted accountid
	require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound MagicWrighter + payment DML
	$inputs = sanitize_inputs($_REQUEST);
	$accountid = tep_session_accountid(); // Session only
	if ($accountid < 1) { // No tenant bound
		error_log('TEP process_payment missing session tenant'); // Log only
		print '{"status": 0}'; // Fail closed
		exit; // Stop
	}
	$ccNumber = $inputs["cardNumber"];
	$cvv = $inputs["cvv"];
	$expDate = $inputs["expiration"];
	$regConfirmation = $inputs["regConfirmation"];

	$customer_name = $access_number = $hashkey = $security_key = $password = $client_id = $client_secret = $access_code = $username = $api_id = $fi_number = $bc_number = '';
	try { // Bound account gateway row
		$pdo = tep_dml_pdo(); // utf8mb4
		$stmt = $pdo->prepare('SELECT * FROM account_magicwrighter_info WHERE accountid = :accountid LIMIT 1'); // Bound
		$stmt->execute(array('accountid' => $accountid)); // Session tenant
		$row = $stmt->fetch(PDO::FETCH_ASSOC); // One row
		if ($row) { // Found
			$customer_name = $row['customer_name'];
			$access_number = $row['access_number'];
			$hashkey = $row['hashkey'];
			$security_key = $row['security_key'];
			$password = $row['password'];
			$client_id = $row['client_id'];
			$client_secret = $row['client_secret'];
			$access_code = $row['access_code'];
			$username = $row['username'];
			$api_id = $row['api_id'];
			$fi_number = $row['fi_number'];
			$bc_number = $row['bc_number'];
		}
	} catch (Throwable $mwEx) { // Connect
		error_log('TEP process_payment gateway lookup failed: ' . $mwEx->getMessage()); // Log only
	}
	//Get Access Token
	$auth = base64_encode($client_id . ':'. $client_secret);
	$apiUser = "lbso\\". $access_code ."\\". $username ."\\". $security_key;
	$postfields = "grant_type=password&username=". rawurlencode($apiUser) . "&password=".
		rawurlencode($password) . "&scope=tkn";

	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => "https://identity.magicwrighter.com/connect/token",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => $postfields,
		CURLOPT_HTTPHEADER => array(
			"Cache-Control: no-cache",
			"Content-Type: application/x-www-form-urlencoded",
			"authorization: Basic ". $auth
		),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);
	if ($err) {
		error_log('TEP process_payment token curl failed'); // Log only — never curl_error to the client
		print '{"status": 0}'; // Fail closed
	} else {
		$token = json_decode($response)->access_token;
		//echo "<b>Access Token:</b> ".$token.'<br/><br/>';
		tokenizationAPI($token);
	}

	//Get Payment Token
	function tokenizationAPI($access_token){
		global $fi_number, $bc_number, $ccNumber, $cvv, $expDate;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://token.magicwrighter.com/api/v1/fis/".$fi_number ."/bcs/".$bc_number ."/tokens",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => "{\"id\":\"test\",\"value\":\"". $ccNumber . "\",\"type\":\"cc\",\"transactionId\":\"". date('YmdGis') ."\",\"cvv\":\"". $cvv ."\",\"expDate\":\"". $expDate ."\"}",
			CURLOPT_HTTPHEADER => array(
				"Cache-Control: no-cache",
				"Content-Type: application/json",
				"authorization: Bearer ". $access_token
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
			error_log('TEP process_payment tokenize curl failed'); // Log only — never curl_error to the client
			print '{"status": 0}'; // Fail closed
		} else {
			$token = json_decode($response)->token;
			//echo "<b>Token from tokenization API:</b><br/> ".$token . '<br/><br/>';
			submitPayment($token, $access_token);
		}
	}

	function submitPayment($token){
		global $security_key, $hashkey, $access_number, $password, $inputs;
		$curDate = date('m-d-Y');
		// securityKey|PaymentFunction|PaymentAmount|PaymentIndicator|AccessNumber|BillingId|Password
		$verification = $security_key."|3|".$inputs["amount"]."|3|".$access_number."|".$inputs["regId"]."|".$password;
		$vHash = hash_hmac("sha256",$verification, $hashkey);

		$postfields = '<PaymentRequest><AuthInfo AccessNumber="'.$access_number.'"';
		$postfields = $postfields.' SecurityKey="'.$security_key.'" Password="'.$password.'" PaymentFunction="3"';
		$postfields = $postfields.' PaymentIndicator="3" PostDate="'.$curDate.'" CustName="'.$inputs["name"].'"';
		$postfields = $postfields.' CustEmail="'.$inputs["email"].'" CustStreet="'.$inputs["street"].'" CustCity="'.$inputs["city"].'"';
		$postfields = $postfields.' CustState="'.$inputs["state"].'" CustZip="'.$inputs["zip"].'"';
		$postfields = $postfields.' CustCcToken="'.$token.'" CustCcExp="'.$inputs["expiration"].'" CustCcCvv="'.$inputs["cvv"].'" />';
		$postfields = $postfields.'<PaymentInfo BillingID="'.$inputs["regId"].'" PaymentAmount="'.$inputs["amount"].'" ';
		$postfields = $postfields.'VerificationHash="'.$vHash.'" ';
		$postfields = $postfields.'InternalID="'.$regConfirmation.'" NotesText="'.$inputs["notes"].'" /></PaymentRequest>';

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://eps.mvpbanking.com/cgi-bin/paycap2.pl",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => 'PaymentRequest='.rawurlencode($postfields),
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/x-www-form-urlencoded"
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			error_log('TEP process_payment submit curl failed'); // Log only — never curl_error to the client
			print '{"status": 0}'; // Fail closed
		} else {
			$xml=simplexml_load_string($response);
			if ($xml === false) { // Bad gateway XML
				error_log('TEP process_payment XML parse failed'); // Log only
				print '{"status": 0}'; // Fail closed
				return; // Stop
			}
			$confirmationNum = $xml->PaymentInfo['ConfirmationNumber'][0];
			if($confirmationNum > 0){
				createPaymentRecord($confirmationNum, $xml);
			}else{
				print "{\"status\": 0, \"paymentDetails\":".json_encode($xml)."}";
			}
		}
	}

	function createPaymentRecord($confirmationNum, $xml){
		global $inputs;
		try { // Bound payment writes; never interpolate ids/amounts
			$pdo = tep_dml_pdo(); // utf8mb4
			$tenant = tep_session_accountid(); // Session tenant
			$ownReg = $pdo->prepare('SELECT registrations.id FROM registrations JOIN events ON events.id = registrations.eventid WHERE registrations.id = :rid AND events.accountid = :accountid LIMIT 1'); // IDOR
			$ownSponsor = $pdo->prepare('SELECT id FROM sponsors WHERE id = :sid AND accountid = :accountid LIMIT 1'); // IDOR
			$ownEvent = $pdo->prepare('SELECT id FROM events WHERE id = :eid AND accountid = :accountid LIMIT 1'); // IDOR
			if($inputs['sponsorid']){
				$sid = (int)$inputs['sponsorid']; // Posted sponsor
				$ownSponsor->execute(array('sid' => $sid, 'accountid' => $tenant)); // Session
				if (!$ownSponsor->fetchColumn()) { // Cross-tenant
					print "{\"status\": 0}"; // Fail closed
					return; // Stop
				}
				if($inputs['eventid']){
					$eid = (int)$inputs['eventid']; // Posted event
					$ownEvent->execute(array('eid' => $eid, 'accountid' => $tenant)); // Session
					if (!$ownEvent->fetchColumn()) { // Cross-tenant
						print "{\"status\": 0}"; // Fail closed
						return; // Stop
					}
					$stmt = $pdo->prepare('INSERT INTO vendor_payments
						(sponsorid, eventid, amount, method, cc_confirmation_number, entered_by, payment_date, note)
						VALUES (:sponsorid, :eventid, :amount, \'CC\', :cc, -1, NOW(), \'\')'); // Bound
					$stmt->execute(array( // No concat
						'sponsorid' => $sid,
						'eventid' => $eid,
						'amount' => $inputs['amount'],
						'cc' => $confirmationNum,
					));
				} else {
					$stmt = $pdo->prepare('INSERT INTO vendor_payments
						(vendor_orders_id, amount, method, cc_confirmation_number, entered_by, payment_date, note)
						VALUES (:orderid, :amount, \'CC\', :cc, -1, NOW(), \'\')'); // Bound
					$stmt->execute(array( // No concat
						'orderid' => (int)$inputs['orderid'],
						'amount' => $inputs['amount'],
						'cc' => $confirmationNum,
					));
				}
			}else{
				$regs = $inputs["registrations"];
				$regs = str_replace("\\","",$regs);
				$regs = json_decode($regs);
				$payStmt = $pdo->prepare('INSERT INTO registration_payments
					(registrationid, amount, cc_fee, payment_type, ref_nbr, entered_by, entered_date, note)
					VALUES (:registrationid, :amount, :cc_fee, \'CC\', :ref, -1, NOW(), \'\')'); // Bound
				$regStmt = $pdo->prepare('UPDATE registrations
					SET payment_number = CONCAT(COALESCE(payment_number,\'\'), CASE WHEN COALESCE(payment_number,\'\') = \'\' THEN :ref1 ELSE CONCAT(\', \', :ref2) END)
					WHERE id = :id'); // Bound confirmation number
				foreach($regs as $r=>$val) {
					$rid = (int)$val->id; // Posted registration
					$ownReg->execute(array('rid' => $rid, 'accountid' => $tenant)); // Session
					if (!$ownReg->fetchColumn()) { // Cross-tenant
						continue; // Skip foreign row
					}
					$payStmt->execute(array( // No concat
						'registrationid' => $rid,
						'amount' => $val->total,
						'cc_fee' => $val->cc_fee,
						'ref' => (string)$confirmationNum,
					));
					$regStmt->execute(array( // Same confirmation, bound twice for native prepares
						'ref1' => (string)$confirmationNum,
						'ref2' => (string)$confirmationNum,
						'id' => $rid,
					));
				}
			}
			print "{\"status\": 1, \"paymentDetails\":".json_encode($xml)."}";
		} catch (Throwable $payRecEx) { // Connect / constraint
			error_log('TEP process_payment record failed: ' . $payRecEx->getMessage()); // Log only
			print "{\"status\": 0}"; // Generic
		}
	}
?>
