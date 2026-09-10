<?php
	include("common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$decrypted = decryptthis($inputs['value']);
	if ($decrypted === false) {
		fail_request(422, 'Unable to decrypt value.');
	}
	print $decrypted;
?>
