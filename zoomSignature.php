<?php
date_default_timezone_set('UTC');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	function generate_signature($meeting_number, $role) {
		$api_key = "Yp7WjcuWSA69gjQj-dEcuA";
		$api_secret = "PcEBMkVkGf9WR0UdTDWatxVWIRfYqTSoKl4F";
		$time = time() * 1000 - 30000; //time in milliseconds (or close enough)
		$data = base64_encode($api_key . $meeting_number . $time . $role);
		$hash = hash_hmac('sha256', $data, $api_secret, true);
		$_sig = $api_key . "." . $meeting_number . "." . $time . "." . $role . "." . base64_encode($hash);
		//return signature, url safe base64 encoded
		return rtrim(strtr(base64_encode($_sig), '+/', '-_'), '=');
	}
	$response = generate_signature($_POST['meeting_number'], $_POST['role']);
	header('Content-type: application/json');
	exit(json_encode($response));
}
?>