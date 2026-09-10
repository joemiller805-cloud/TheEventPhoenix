<?php
session_start();

include "{$_SERVER['DOCUMENT_ROOT']}/common_functions.php";

if (!isset($_SESSION['userid'])) {
	http_response_code(403);
	print "Unauthorized";
	exit;
}

touch_session_activity(true);

$inputs = $_REQUEST;
$resourceID = database_connect();

function in_str($inputs, $key, $default = '') {
	if (!isset($inputs[$key])) return $default;
	return trim((string)$inputs[$key]);
}

function in_int($inputs, $key, $default = 0) {
	if (!isset($inputs[$key]) || $inputs[$key] === '') return $default;
	return (int)$inputs[$key];
}

function run_stmt($resourceID, $sql, $types, $params) {
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

$op = in_str($inputs, 'op');
$ok = false;

switch ($op) {
	case 'createCourse':
		$ok = run_stmt(
			$resourceID,
			"INSERT INTO courses (name, abbreviation, description, sponsorid, excludefromschedule, excludefromdocs, archived, accountid, color)
			 VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?)",
			"ssssiiiis",
			[
				in_str($inputs, 'name'),
				in_str($inputs, 'abbreviation'),
				in_str($inputs, 'description'),
				in_str($inputs, 'sponsorid'),
				in_int($inputs, 'excludefromschedule'),
				in_int($inputs, 'excludefromdocs'),
				in_int($inputs, 'archived'),
				in_int($inputs, 'accountid'),
				in_str($inputs, 'color')
			]
		);
		break;
	case 'addCourseTrack':
		$ok = run_stmt($resourceID, "INSERT INTO course_tracks (courseid, trackid) VALUES (?, ?)", "ii", [in_int($inputs, 'courseid'), in_int($inputs, 'trackid')]);
		break;
	case 'saveColmodelInsert':
		$ok = run_stmt($resourceID, "INSERT INTO user_colmodel (userid, eventid, colmodel) VALUES (?, ?, ?)", "iis", [in_int($inputs, 'userid'), in_int($inputs, 'eventid'), in_str($inputs, 'colmodel')]);
		break;
	case 'saveColmodelUpdate':
		$ok = run_stmt($resourceID, "UPDATE user_colmodel SET colmodel = ? WHERE userid = ? AND eventid = ?", "sii", [in_str($inputs, 'colmodel'), in_int($inputs, 'userid'), in_int($inputs, 'eventid')]);
		break;
	case 'createUserColumnRecord':
		$ok = run_stmt($resourceID, "INSERT INTO user_colmodel (userid, colmodel, page) VALUES (?, ?, ?)", "iss", [in_int($inputs, 'userid'), in_str($inputs, 'colmodel'), in_str($inputs, 'page')]);
		break;
	case 'updateUserColumnRecord':
		$ok = run_stmt($resourceID, "UPDATE user_colmodel SET colmodel = ? WHERE userid = ? AND page = ?", "sis", [in_str($inputs, 'colmodel'), in_int($inputs, 'userid'), in_str($inputs, 'page')]);
		break;
	case 'deleteSignupConflictsForSession':
		$ok = run_stmt(
			$resourceID,
			"DELETE signups
			 FROM signups
			 JOIN sections ON sections.id = signups.sectionid
			 WHERE registrationid = ?
			 AND COALESCE(sections.is_virtual, false) = false
			 AND signups.sessionid = ?",
			"ii",
			[in_int($inputs, 'registrationid'), in_int($inputs, 'sessionid')]
		);
		break;
	case 'createSignup':
		$ok = run_stmt($resourceID, "INSERT INTO signups (sectionid, sessionid, registrationid) VALUES (?, ?, ?)", "iii", [in_int($inputs, 'sectionid'), in_int($inputs, 'sessionid'), in_int($inputs, 'registrationid')]);
		break;
	case 'denyRequest':
		$ok = run_stmt($resourceID, "UPDATE user_event SET request_denied = 1 WHERE eventid = ? AND userid = ?", "ii", [in_int($inputs, 'eventid'), in_int($inputs, 'userid')]);
		break;
	case 'respondToCourseProposal':
		$status = in_str($inputs, 'status');
		if ($status === 'accepted' || $status === 'rejected') {
			$ok = run_stmt($resourceID, "UPDATE course_proposals SET status = ? WHERE id = ?", "si", [$status, in_int($inputs, 'id')]);
		}
		break;
	case 'updateRoom':
		$ok = run_stmt(
			$resourceID,
			"UPDATE rooms
			 SET name = ?, capacity = ?, sortorder = ?, area = ?, subname = ?
			 WHERE id = ?",
			"sssssi",
			[
				in_str($inputs, 'name'),
				in_str($inputs, 'capacity'),
				in_str($inputs, 'sortorder'),
				in_str($inputs, 'area'),
				in_str($inputs, 'subname'),
				in_int($inputs, 'id')
			]
		);
		break;
	case 'updateSession':
		$ok = run_stmt($resourceID, "UPDATE sessions SET name = ?, starttime = ?, endtime = ? WHERE id = ?", "sssi", [in_str($inputs, 'name'), in_str($inputs, 'starttime'), in_str($inputs, 'endtime'), in_int($inputs, 'id')]);
		break;
	case 'updateRegActiveStatus':
		$ok = run_stmt($resourceID, "UPDATE registrations SET deleted = ? WHERE confirmation = ?", "is", [in_int($inputs, 'deleted'), in_str($inputs, 'confirmation')]);
		break;
	case 'deleteCourseTrack':
		$ok = run_stmt($resourceID, "DELETE FROM course_tracks WHERE courseid = ? AND trackid = ?", "ii", [in_int($inputs, 'courseid'), in_int($inputs, 'trackid')]);
		break;
	case 'updateCourse':
		$ok = run_stmt(
			$resourceID,
			"UPDATE courses
			 SET name = ?, abbreviation = ?, description = ?, sponsorid = NULLIF(?, ''),
			     archived = ?, excludefromdocs = ?, excludefromschedule = ?, color = ?
			 WHERE id = ?",
			"ssssiiisi",
			[
				in_str($inputs, 'name'),
				in_str($inputs, 'abbreviation'),
				in_str($inputs, 'description'),
				in_str($inputs, 'sponsorid'),
				in_int($inputs, 'archived'),
				in_int($inputs, 'excludefromdocs'),
				in_int($inputs, 'excludefromschedule'),
				in_str($inputs, 'color'),
				in_int($inputs, 'id')
			]
		);
		break;
	case 'updateRegistrationType':
		$ok = run_stmt(
			$resourceID,
			"UPDATE registration_types
			 SET name = ?, price = ?, sunrise = ?, sunset = ?, sortorder = ?
			 WHERE id = ?",
			"sssssi",
			[
				in_str($inputs, 'name'),
				in_str($inputs, 'price'),
				in_str($inputs, 'sunrise'),
				in_str($inputs, 'sunset'),
				in_str($inputs, 'sortorder'),
				in_int($inputs, 'id')
			]
		);
		break;
	case 'createRegistrationType':
		$ok = run_stmt(
			$resourceID,
			"INSERT INTO registration_types (name, price, sunrise, sunset, sortorder, eventid)
			 VALUES (?, ?, ?, ?, ?, ?)",
			"sssssi",
			[
				in_str($inputs, 'name'),
				in_str($inputs, 'price'),
				in_str($inputs, 'sunrise'),
				in_str($inputs, 'sunset'),
				in_str($inputs, 'sortorder'),
				in_int($inputs, 'eventid')
			]
		);
		break;
	case 'checkinAttendee':
		$ok = run_stmt($resourceID, "UPDATE registrations SET checkin_userid = ?, checkin = now() WHERE id = ?", "ii", [in_int($inputs, 'userid'), in_int($inputs, 'id')]);
		break;
	case 'checkoutAttendee':
		$ok = run_stmt($resourceID, "UPDATE registrations SET checkin_userid = NULL, checkin = NULL WHERE id = ?", "i", [in_int($inputs, 'id')]);
		break;
	case 'deleteCourse':
		$ok = run_stmt($resourceID, "DELETE FROM events_courses WHERE id = ? AND eventid = ?", "ii", [in_int($inputs, 'id'), in_int($inputs, 'eventid')]);
		break;
	case 'deleteSignup':
		$ok = run_stmt($resourceID, "DELETE FROM signups WHERE registrationid = ? AND sectionid = ?", "ii", [in_int($inputs, 'registrationid'), in_int($inputs, 'sectionid')]);
		break;
	case 'deleteSectionPresenter':
		$ok = run_stmt($resourceID, "DELETE FROM section_presenters WHERE sectionid = ? AND userid = ?", "ii", [in_int($inputs, 'sectionid'), in_int($inputs, 'userid')]);
		break;
	case 'deleteSectionSession':
		$ok = run_stmt($resourceID, "DELETE FROM section_sessions WHERE sectionid = ? AND sessionid = ?", "ii", [in_int($inputs, 'sectionid'), in_int($inputs, 'sessionid')]);
		break;
	default:
		$ok = false;
}

if ($ok) {
	print(mysqli_insert_id($resourceID));
} else {
	http_response_code(400);
	print "Query Execution Error";
}

mysqli_close($resourceID);
?>
