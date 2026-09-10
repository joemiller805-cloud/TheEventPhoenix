<?php
include("common_functions.php");
$inputs = sanitize_inputs($_REQUEST);

// get api key
$resource = database_connect();
$query = "SELECT value FROM preferences WHERE name = 'basysPrivateKey' AND accountid = {$inputs['accountid']}";
$result = mysqli_query($resource, $query);
while ($row = mysqli_fetch_assoc($result)){
	$api_key = decryptthis($row['value']);
}
mysqli_close($resource);

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
	$resourceID = database_connect();
	if($inputs['sponsorid']){
		$query = "
			INSERT INTO vendor_payments
				(vendor_orders_id, amount, method, cc_confirmation_number, entered_by, payment_date, note)
			VALUES
				(".$inputs['orderid'].",".$inputs["trueAmount"].",'CC','".$confirmationNum."',-1,now(),'')
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
					(registrationid, amount, payment_type, ref_nbr, entered_by, entered_date, note)
				VALUES
					(".$val->id.",".$val->total.",'CC','".$confirmationNum."',-1,now(),'')
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
	mysqli_close($resourceID);
}
?>
