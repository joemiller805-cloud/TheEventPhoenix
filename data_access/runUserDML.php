<?php
session_start();

include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php";

function out_json($status, $insertid = 0, $httpCode = 200) {
	http_response_code($httpCode);
	print json_encode(array("status" => $status, "insertid" => (int)$insertid));
	exit;
}

function in_str($inputs, $key, $default = '') {
	if (!isset($inputs[$key])) return $default;
	return trim((string)$inputs[$key]);
}

function in_int($inputs, $key, $default = 0) {
	if (!isset($inputs[$key]) || $inputs[$key] === '') return $default;
	return (int)$inputs[$key];
}

function exec_stmt($resourceID, $sql, $types = '', $params = array()) {
	$stmt = mysqli_prepare($resourceID, $sql);
	if (!$stmt) return false;
	if ($types !== '') {
		if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
			mysqli_stmt_close($stmt);
			return false;
		}
	}
	$ok = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	return $ok;
}

$inputs = $_REQUEST;
$query = in_str($inputs, 'query');

if ($query === '') out_json("error", 0, 400);

if (
	!isset($_SESSION['userid']) &&
	!isset($_SESSION['attendeeid']) &&
	!isset($_SESSION['registrationid']) &&
	!isset($_SESSION['accountid'])
) {
	out_json("error", 0, 403);
}

touch_session_activity(true);

$resourceID = database_connect();
$ok = false;

switch ($query) {
	case "createAttendee":
		$ok = exec_stmt(
			$resourceID,
			"INSERT INTO attendees
			 (accountid, last_name, first_name, email, business, address1, address2, city, state,
			  zip, title, phone, web_address, dietary_restrictions, ec1_email, ec1_name,
			  ec1_phone_prim, ec1_phone_alt, ec2_email, ec2_name, ec2_phone_prim, ec2_phone_alt, password)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
			"issssssssssssssssssssss",
			array(
				in_int($_SESSION, 'accountid'),
				in_str($inputs, 'last_name'),
				in_str($inputs, 'first_name'),
				in_str($inputs, 'email'),
				in_str($inputs, 'business'),
				in_str($inputs, 'address1'),
				in_str($inputs, 'address2'),
				in_str($inputs, 'city'),
				in_str($inputs, 'state'),
				in_str($inputs, 'zip'),
				in_str($inputs, 'title'),
				in_str($inputs, 'phone'),
				in_str($inputs, 'web_address'),
				in_str($inputs, 'dietary_restrictions'),
				in_str($inputs, 'ec1_email'),
				in_str($inputs, 'ec1_name'),
				in_str($inputs, 'ec1_phone_prim'),
				in_str($inputs, 'ec1_phone_alt'),
				in_str($inputs, 'ec2_email'),
				in_str($inputs, 'ec2_name'),
				in_str($inputs, 'ec2_phone_prim'),
				in_str($inputs, 'ec2_phone_alt'),
				in_str($inputs, 'password')
			)
		);
		break;

	case "updateAttendee":
		$ok = exec_stmt(
			$resourceID,
			"UPDATE attendees SET
				last_name = ?, first_name = ?, email = ?, business = ?, address1 = ?, address2 = ?,
				city = ?, state = ?, zip = ?, title = ?, phone = ?, web_address = ?,
				dietary_restrictions = ?, ec1_email = ?, ec1_name = ?, ec1_phone_prim = ?,
				ec1_phone_alt = ?, ec2_email = ?, ec2_name = ?, ec2_phone_prim = ?, ec2_phone_alt = ?
			 WHERE id = ?",
			"sssssssssssssssssssssi",
			array(
				in_str($inputs, 'last_name'),
				in_str($inputs, 'first_name'),
				in_str($inputs, 'email'),
				in_str($inputs, 'business'),
				in_str($inputs, 'address1'),
				in_str($inputs, 'address2'),
				in_str($inputs, 'city'),
				in_str($inputs, 'state'),
				in_str($inputs, 'zip'),
				in_str($inputs, 'title'),
				in_str($inputs, 'phone'),
				in_str($inputs, 'web_address'),
				in_str($inputs, 'dietary_restrictions'),
				in_str($inputs, 'ec1_email'),
				in_str($inputs, 'ec1_name'),
				in_str($inputs, 'ec1_phone_prim'),
				in_str($inputs, 'ec1_phone_alt'),
				in_str($inputs, 'ec2_email'),
				in_str($inputs, 'ec2_name'),
				in_str($inputs, 'ec2_phone_prim'),
				in_str($inputs, 'ec2_phone_alt'),
				in_int($inputs, 'id')
			)
		);
		break;

	case "setAttendeeTermsAgreed":
		$ok = exec_stmt($resourceID, "UPDATE attendees SET terms_agreed = 1 WHERE id = ?", "i", array(in_int($inputs, 'attendeeid')));
		break;
	case "updateAttendePw":
		$ok = exec_stmt($resourceID, "UPDATE attendees SET password = ? WHERE id = ?", "si", array(in_str($inputs, 'password'), in_int($inputs, 'id')));
		break;
	case "resetAttendeePw":
		$ok = exec_stmt($resourceID, "UPDATE attendees SET password = ? WHERE id = ?", "si", array(in_str($inputs, 'password'), in_int($_SESSION, 'attendeeid')));
		break;

	case "createVideoOrder":
		$ok = exec_stmt(
			$resourceID,
			"INSERT INTO video_orders (accountid, attendeeid, total, discount, discount_code, paid) VALUES (?, ?, ?, ?, ?, ?)",
			"iiddsi",
			array(
				in_int($_SESSION, 'accountid'),
				in_int($inputs, 'attendeeid'),
				(float)in_str($inputs, 'total', '0'),
				(float)in_str($inputs, 'discount', '0'),
				in_str($inputs, 'discount_code'),
				in_int($inputs, 'paid')
			)
		);
		break;
	case "createVideoOrderDetail":
		$ok = exec_stmt($resourceID, "INSERT INTO video_order_details (video_ordersid, videoid) VALUES (?, ?)", "ii", array(in_int($inputs, 'video_ordersid'), in_int($inputs, 'videoid')));
		break;
	case "createVideoPkgOrderDetail":
		$ok = exec_stmt($resourceID, "INSERT INTO video_order_details (video_ordersid, video_packagesid) VALUES (?, ?)", "ii", array(in_int($inputs, 'video_ordersid'), in_int($inputs, 'video_packagesid')));
		break;
	case "setOrderPaymentNumber":
		$ok = exec_stmt($resourceID, "UPDATE video_orders SET payment_number = ? WHERE id = ?", "si", array(in_str($inputs, 'payment_number'), in_int($inputs, 'orderid')));
		break;
	case "payVideoOrder":
		$ok = exec_stmt($resourceID, "UPDATE video_orders SET payment_number = ?, paid = ? WHERE id = ?", "sii", array(in_str($inputs, 'payment_number'), in_int($inputs, 'paid'), in_int($inputs, 'orderid')));
		break;

	case "updateRegistration":
		$ok = exec_stmt(
			$resourceID,
			"UPDATE registrations
			 SET vendor_access = ?, registration_typeid = ?, payment_method = ?, payment_number = ?, userid = ?
			 WHERE id = ?",
			"iissii",
			array(
				in_int($inputs, 'vendor_access'),
				in_int($inputs, 'registration_typeid'),
				in_str($inputs, 'payment_method'),
				in_str($inputs, 'payment_number'),
				in_int($inputs, 'userid'),
				in_int($inputs, 'registration_id')
			)
		);
		break;
	case "updateRegDiscountCode":
		$ok = exec_stmt($resourceID, "UPDATE registrations SET discount_code = ? WHERE id = ?", "si", array(in_str($inputs, 'discount_code'), in_int($inputs, 'registration_id')));
		break;
	case "createRegistration":
		$seasonPassId = in_int($inputs, 'season_pass_ordersid');
		$ok = exec_stmt(
			$resourceID,
			"INSERT INTO registrations
			 (create_date, attendeeid, vendor_access, registration_typeid, payment_method, payment_number, confirmation, userid, eventid, season_pass_ordersid, discount_code)
			 VALUES (SYSDATE(), ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?)",
			"iiisssiiis",
			array(
				in_int($inputs, 'attendeeid'),
				in_int($inputs, 'vendor_access'),
				in_int($inputs, 'registration_typeid'),
				in_str($inputs, 'payment_method'),
				in_str($inputs, 'payment_number'),
				in_str($inputs, 'confirmation'),
				in_int($inputs, 'userid'),
				in_int($inputs, 'eventid'),
				$seasonPassId,
				in_str($inputs, 'discount_code')
			)
		);
		break;

	case "insertRegExtraData":
		$ok = exec_stmt($resourceID, "INSERT INTO registration_data (registrationid, registration_fieldid, data) VALUES (?, ?, ?)", "iis", array(in_int($inputs, 'regid'), in_int($inputs, 'fieldid'), in_str($inputs, 'value')));
		break;
	case "updateExtraRegData":
		$ok = exec_stmt($resourceID, "UPDATE registration_data SET data = ? WHERE registrationid = ? AND registration_fieldid = ?", "sii", array(in_str($inputs, 'value'), in_int($inputs, 'regid'), in_int($inputs, 'fieldid')));
		break;

	case "logRegistration":
		$ok = exec_stmt(
			$resourceID,
			"INSERT INTO regLog
			 (referrer, userAgent, email, payment_method, reg_type, acctName, eventName, cc_enabled, req_cc_pymt, reg_fee, registrationid, confirmation)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
			"ssssssssssis",
			array(
				in_str($inputs, 'referrer'),
				in_str($inputs, 'userAgent'),
				in_str($inputs, 'email'),
				in_str($inputs, 'payment_method'),
				in_str($inputs, 'reg_type'),
				in_str($inputs, 'acctName'),
				in_str($inputs, 'eventName'),
				in_str($inputs, 'cc_enabled'),
				in_str($inputs, 'req_cc_pymt'),
				in_str($inputs, 'reg_fee'),
				in_int($inputs, 'registrationid'),
				in_str($inputs, 'confirmation')
			)
		);
		break;

	case "createSeasonPassPurchase":
		$ok = exec_stmt(
			$resourceID,
			"INSERT INTO season_pass_orders (accountid, attendeeid, order_details, amt_charged, cc_fee, ref_nbr)
			 VALUES (?, ?, ?, ?, ?, ?)",
			"iisdds",
			array(
				in_int($inputs, 'accountid'),
				in_int($_SESSION, 'attendeeid'),
				in_str($inputs, 'order_details'),
				(float)in_str($inputs, 'amt_charged', '0'),
				(float)in_str($inputs, 'cc_fee', '0'),
				in_str($inputs, 'ref_nbr')
			)
		);
		break;

	case "submitSurveyResponse":
		$ok = exec_stmt(
			$resourceID,
			"INSERT INTO survey_responses (registrationid, eventid, questionid, response, sectionid)
			 VALUES (?, ?, ?, ?, NULLIF(?, 0))",
			"iiisi",
			array(
				in_int($inputs, 'registrationid'),
				in_int($inputs, 'eventid'),
				in_int($inputs, 'questionid'),
				in_str($inputs, 'response'),
				in_int($inputs, 'sectionid')
			)
		);
		break;
	case "updateSurveyResponse":
		$ok = exec_stmt($resourceID, "UPDATE survey_responses SET response = ? WHERE id = ?", "si", array(in_str($inputs, 'response'), in_int($inputs, 'responseid')));
		break;

	case "userSelfUpdate":
		$ok = exec_stmt(
			$resourceID,
			"UPDATE users SET
			 last_name = ?, first_name = ?, email = ?, business = ?, address1 = ?, address2 = ?, city = ?, state = ?,
			 zip = ?, home_add1 = ?, home_add2 = ?, home_city = ?, home_state = ?, home_zip = ?, phone = ?, photo = ?,
			 dietary_restrictions = ?, courses_preferred = ?, courses_able = ?, pymt_method_pref = ?, pymt_method_notes = ?, bio = ?
			 WHERE id = ?",
			"ssssssssssssssssssssssi",
			array(
				in_str($inputs, 'last_name'),
				in_str($inputs, 'first_name'),
				in_str($inputs, 'email'),
				in_str($inputs, 'business'),
				in_str($inputs, 'address1'),
				in_str($inputs, 'address2'),
				in_str($inputs, 'city'),
				in_str($inputs, 'state'),
				in_str($inputs, 'zip'),
				in_str($inputs, 'home_add1'),
				in_str($inputs, 'home_add2'),
				in_str($inputs, 'home_city'),
				in_str($inputs, 'home_state'),
				in_str($inputs, 'home_zip'),
				in_str($inputs, 'phone'),
				in_str($inputs, 'photo'),
				in_str($inputs, 'dietary_restrictions'),
				in_str($inputs, 'courses_preferred'),
				in_str($inputs, 'courses_able'),
				in_str($inputs, 'pymt_method_pref'),
				in_str($inputs, 'pymt_method_notes'),
				in_str($inputs, 'bio'),
				in_int($inputs, 'id')
			)
		);
		break;
	case "userPasswordSelfUpdate":
		$ok = exec_stmt($resourceID, "UPDATE users SET pass = ? WHERE email = ?", "ss", array(in_str($inputs, 'pass'), in_str($inputs, 'email')));
		break;
	case "sponsorPasswordSelfUpdate":
		$ok = exec_stmt($resourceID, "UPDATE sponsors SET pass = ? WHERE email = ? AND accountid = ?", "ssi", array(in_str($inputs, 'pass'), in_str($inputs, 'email'), in_int($_SESSION, 'accountid')));
		break;

	case "userSignupUpdate":
		$ok = exec_stmt(
			$resourceID,
			"UPDATE signups
			 SET sessionid = ?, sectionid = ?, video_viewed = ?
			 WHERE id = ? AND registrationid = ?",
			"iisii",
			array(
				in_int($inputs, 'sessionid'),
				in_int($inputs, 'sectionid'),
				in_str($inputs, 'video_viewed'),
				in_int($inputs, 'recordid'),
				in_int($_SESSION, 'registrationid')
			)
		);
		break;
	case "userSignupInsert":
		$ok = exec_stmt($resourceID, "INSERT INTO signups (registrationid, sessionid, sectionid) VALUES (?, ?, ?)", "iii", array(in_int($_SESSION, 'registrationid'), in_int($inputs, 'sessionid'), in_int($inputs, 'sectionid')));
		break;
	case "userSignupDelete":
		$ok = exec_stmt($resourceID, "DELETE FROM signups WHERE id = ? AND registrationid = ?", "ii", array(in_int($inputs, 'recordid'), in_int($_SESSION, 'registrationid')));
		break;
	case "recordAttendeeVideoView":
		$ok = exec_stmt($resourceID, "UPDATE signups SET video_viewed = NOW() WHERE registrationid = ? AND sectionid = ?", "ii", array(in_int($_SESSION, 'registrationid'), in_int($inputs, 'sectionid')));
		break;

	case "insertVideoReview":
		$ok = exec_stmt($resourceID, "INSERT INTO video_reviews (videoid, rating, comment, attendeeid) VALUES (?, ?, ?, ?)", "iisi", array(in_int($inputs, 'videoid'), in_int($inputs, 'rating'), in_str($inputs, 'comment'), in_int($_SESSION, 'attendeeid')));
		break;
	case "updateVideoReview":
		$ok = exec_stmt($resourceID, "UPDATE video_reviews SET rating = ?, comment = ? WHERE id = ?", "isi", array(in_int($inputs, 'rating'), in_str($inputs, 'comment'), in_int($inputs, 'id')));
		break;
	case "insertVideoPkgReview":
		$ok = exec_stmt($resourceID, "INSERT INTO video_reviews (video_packagesid, rating, comment, attendeeid) VALUES (?, ?, ?, ?)", "iisi", array(in_int($inputs, 'pkgid'), in_int($inputs, 'rating'), in_str($inputs, 'comment'), in_int($_SESSION, 'attendeeid')));
		break;
	case "deleteVideoOrder":
		$ok = exec_stmt($resourceID, "DELETE FROM video_orders WHERE id = ?", "i", array(in_int($inputs, 'orderid')));
		break;
}

$insertId = mysqli_insert_id($resourceID);
mysqli_close($resourceID);

if (!$ok) out_json("error", 0, 400);
out_json("success", $insertId, 200);
?>
