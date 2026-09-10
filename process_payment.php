<?php
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$accountid = $inputs["accountid"];
	$ccNumber = $inputs["cardNumber"];
	$cvv = $inputs["cvv"];
	$expDate = $inputs["expiration"];
	$regConfirmation = $inputs["regConfirmation"];

	$resource = database_connect();
	$query = "SELECT * FROM account_magicwrighter_info WHERE accountid = {$inputs['accountid']}";
	$result = mysqli_query($resource, $query);
	while ($row = mysqli_fetch_assoc($result)){
		 $customer_name =  $row['customer_name'];
		 $access_number =  $row['access_number'];
		 $hashkey =  $row['hashkey'];
		 $security_key =  $row['security_key'];
		 $password =  $row['password'];
		 $client_id =  $row['client_id'];
		 $client_secret =  $row['client_secret'];
		 $access_code =  $row['access_code'];
		 $username =  $row['username'];
		 $api_id =  $row['api_id'];
		 $fi_number =  $row['fi_number'];
		 $bc_number =  $row['bc_number'];
	}
	mysqli_close($resource);
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
		echo "cURL Error #:". $err;
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
			echo "cURL Error #:". $err;
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
			echo "cURL Error #:". $err;
		} else {
			$xml=simplexml_load_string($response) or die("Error: Cannot create object");
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
		$resourceID = database_connect();
		if($inputs['sponsorid']){
			$query = "
				INSERT INTO vendor_payments
					(vendor_orders_id, amount, method, cc_confirmation_number, entered_by, payment_date, note)
				VALUES
					(".$inputs['orderid'].",".$inputs["amount"].",'CC',".$confirmationNum.",-1,now(),'')
			";

			if($inputs['eventid']){
				$query = "
					INSERT INTO vendor_payments
						(sponsorid, eventid, amount, method, cc_confirmation_number, entered_by, payment_date, note)
					VALUES
						(".$inputs['sponsorid'].",".$inputs['eventid'].",".$inputs["amount"].",'CC',".$confirmationNum.",-1,now(),'')
				";
			}
			mysqli_query($resourceID, $query);
		}else{
			$regs = $inputs["registrations"];
			$regs = str_replace("\\","",$regs);
			$regs = json_decode($regs);
			foreach($regs as $r=>$val) {
			    $query = "
			    	INSERT INTO registration_payments
			    		(registrationid, amount, cc_fee, payment_type, ref_nbr, entered_by, entered_date, note)
			    	VALUES
			    		(".$val->id.",".$val->total.",".$val->cc_fee.",'CC','".$confirmationNum."',-1,now(),'')
			    ";
			    mysqli_query($resourceID, $query);
			    $regQuery = "
			    	UPDATE registrations
			    	SET payment_number = CONCAT(COALESCE(payment_number,''), CASE WHEN COALESCE(payment_number,'') = '' THEN '".$confirmationNum."' ELSE ', ".$confirmationNum."' END)
			    	WHERE id =". $val->id
			    ;
			    mysqli_query($resourceID, $regQuery);
			}
		}
		print "{\"status\": 1, \"paymentDetails\":".json_encode($xml)."}";
		mysqli_close($resourceID);
	}
?>
