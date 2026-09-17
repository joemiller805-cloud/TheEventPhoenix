<?php
include("common_functions.php");
start_secure_session(); // Session tenant for Basys key — never posted accountid
require_once __DIR__ . '/data_access/tep_dml_pdo.php'; // Bound Basys key + payment DML
$inputs = sanitize_inputs($_REQUEST);
$accountid = tep_session_accountid(); // Session only
if ($accountid < 1) { // No tenant bound
	error_log('TEP process_basys_pymt missing session tenant'); // Log only
	fail_request(422, 'Unable to process payment.'); // Generic
}

$api_key = '';
try { // Bound preferences lookup
	$pdo = tep_dml_pdo(); // utf8mb4
	$stmt = $pdo->prepare("SELECT value FROM preferences WHERE name = 'basysPrivateKey' AND accountid = :accountid LIMIT 1"); // Bound
	$stmt->execute(array('accountid' => $accountid)); // Session tenant
	$row = $stmt->fetch(PDO::FETCH_ASSOC); // One row
	if ($row) { // Found
		$api_key = decryptthis($row['value']);
	}
} catch (Throwable $basysEx) { // Connect
	error_log('TEP process_basys_pymt key lookup failed: ' . $basysEx->getMessage()); // Log only
}

if (empty($api_key)) {
	fail_request(422, 'Unable to decrypt Basys API key.');
}

$payload = array(
	"type" => "sale",
	"amount" => (float)$_REQUEST["amount"],
	"tax_amount" => 0,
	"shipping_amount" => 0,
	"currency" => "USD",
	"description" => $_REQUEST["description"],
	"order_id" => $_REQUEST["confirmation"],
	"email_receipt" => true,
	"email_address" => $_REQUEST["email"],
	"create_vault_record" => true,
	"billing_address" => array(
		"first_name" => $_REQUEST["first_name"],
		"last_name" => $_REQUEST["last_name"],
		"address_line_1" => $_REQUEST["address_line_1"],
		"city" => $_REQUEST["city"],
		"state" => $_REQUEST["state"],
		"postal_code" => $_REQUEST["postal_code"],
		"country" => $_REQUEST["country"],
		"email" => $_REQUEST["email"]
	),
	"payment_method" => array(
		"token" => $_REQUEST["token"],
	)
);

$payload = json_encode($payload);

if (substr($_SERVER['HTTP_HOST'], 0, 4) == "easy"){
	$basysUrl = "https://app.basysiqpro.com/api/transaction";
}
else{
	$basysUrl = "https://sandbox.basysiqpro.com/api/transaction";
} 
$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => $basysUrl,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "POST",
	CURLOPT_POSTFIELDS => $payload,
	CURLOPT_HTTPHEADER => array(
		"Authorization: ".$api_key,
		"Content-Type: application/json"
	),
));
$response = curl_exec($curl);
curl_close($curl);
$json = json_decode($response);
echo $response;
if($json->data->status == 'pending_settlement') createPaymentRecord($json->data->id);

function createPaymentRecord($confirmationNum){
	global $inputs;
	try { // Bound payment writes
		$pdo = tep_dml_pdo(); // utf8mb4
		$tenant = tep_session_accountid(); // Session tenant
		$ownReg = $pdo->prepare('SELECT registrations.id FROM registrations JOIN events ON events.id = registrations.eventid WHERE registrations.id = :rid AND events.accountid = :accountid LIMIT 1'); // IDOR
		$ownSponsor = $pdo->prepare('SELECT id FROM sponsors WHERE id = :sid AND accountid = :accountid LIMIT 1'); // IDOR
		$ownEvent = $pdo->prepare('SELECT id FROM events WHERE id = :eid AND accountid = :accountid LIMIT 1'); // IDOR
		if($inputs['sponsorid']){
			$sid = (int)$inputs['sponsorid']; // Posted sponsor
			$ownSponsor->execute(array('sid' => $sid, 'accountid' => $tenant)); // Session
			if (!$ownSponsor->fetchColumn()) { // Cross-tenant
				return; // Fail closed
			}
			if($inputs['eventid']){
				$eid = (int)$inputs['eventid']; // Posted event
				$ownEvent->execute(array('eid' => $eid, 'accountid' => $tenant)); // Session
				if (!$ownEvent->fetchColumn()) { // Cross-tenant
					return; // Fail closed
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
					'amount' => $inputs['trueAmount'],
					'cc' => $confirmationNum,
				));
			}
		}else{
			$regs = $inputs["registrations"];
			$regs = str_replace("\\","",$regs);
			$regs = json_decode($regs);
			$payStmt = $pdo->prepare('INSERT INTO registration_payments
				(registrationid, amount, payment_type, ref_nbr, entered_by, entered_date, note)
				VALUES (:registrationid, :amount, \'CC\', :ref, -1, NOW(), \'\')'); // Bound
			$regStmt = $pdo->prepare('UPDATE registrations
				SET payment_number = CONCAT(COALESCE(payment_number,\'\'), CASE WHEN COALESCE(payment_number,\'\') = \'\' THEN :ref1 ELSE CONCAT(\', \', :ref2) END)
				WHERE id = :id'); // Bound
			foreach($regs as $r=>$val) {
				$rid = (int)$val->id; // Posted registration
				$ownReg->execute(array('rid' => $rid, 'accountid' => $tenant)); // Session
				if (!$ownReg->fetchColumn()) { // Cross-tenant
					continue; // Skip foreign row
				}
				$payStmt->execute(array( // No concat
					'registrationid' => $rid,
					'amount' => $val->total,
					'ref' => (string)$confirmationNum,
				));
				$regStmt->execute(array( // Bound confirmation
					'ref1' => (string)$confirmationNum,
					'ref2' => (string)$confirmationNum,
					'id' => $rid,
				));
			}
		}
	} catch (Throwable $basysRecEx) { // Connect
		error_log('TEP process_basys_pymt record failed: ' . $basysRecEx->getMessage()); // Log only
	}
}
?>
