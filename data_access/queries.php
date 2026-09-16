<?php session_start(); ?>
<?php
	$inputs = sanitize_inputs($_REQUEST);
	if (!isset($_SESSION['accountid'])) { // PHP 8.2 warns when bound query params read a missing session key
		$_SESSION['accountid'] = ''; // Empty id matches existing public-account SQL branches (accounts.id = 1000)
	}
	$queries = array();

	function query_definition($sql, $types = '', $params = array(), $rows = null){ // Optional $rows skips mysqli when a safe JSON payload is already known
		$definition = array( // Existing prepared-query shape used by getQueryResults.php
			"sql" => $sql, // SQL string; unused when $rows is an array
			"types" => $types, // bind_param types; unused when $rows is an array
			"params" => $params // Bound values; unused when $rows is an array
		);
		if (is_array($rows)) { // Local/empty-slug eventData returns HTTP 200 without hitting missing tables
			$definition['rows'] = $rows; // Prebuilt rows array encoded as {"rows":[...]}
		}
		return $definition; // Unchanged for every existing three-argument caller
	}

	function append_query_condition(&$query, $sql, $types = '', $params = array()){
		if(!is_array($query)){
			$query = query_definition($query);
		}
		$query["sql"] .= $sql;
		$query["types"] .= $types;
		$query["params"] = array_merge($query["params"], $params);
	}

	function int_list_placeholders($csv){
		$values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), 'strlen'));
		$values = array_map('intval', $values);
		if(empty($values)){
			return array("placeholders" => "NULL", "types" => "", "params" => array());
		}
		return array(
			"placeholders" => implode(',', array_fill(0, count($values), '?')),
			"types" => str_repeat('i', count($values)),
			"params" => $values
		);
	}

	$queries["accountList"] = query_definition("
		SELECT name, id, slug, web_logo, sponsors_enabled FROM accounts WHERE disabled != 1
	");

	$queries["attendees_count"] = query_definition(
		"SELECT COUNT(*) as attendee_count FROM attendees where accountid = ?",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["sponsors_count"] = query_definition(
		"SELECT COUNT(*) as sponsors_count FROM sponsors where accountid = ?",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["registrations_count"] = query_definition(
		"SELECT COUNT(*) as registrations_count FROM registrations where checkin_userid = ?",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["events_count"] = query_definition(
		"SELECT * FROM events where accountid = ? ORDER BY startdate DESC",
		"s",
		array($_SESSION['accountid'])
	);

	if (substr($_SERVER['HTTP_HOST'], 0, 4) == "easy") $database = defined('DB_NAME_PROD') ? DB_NAME_PROD : 'tep_local'; // PHP 8.2: do not fatal when tep_config is missing on XAMPP
	else $database = defined('DB_NAME_DEV') ? DB_NAME_DEV : 'tep_local'; // Local fallback used only when private tep_config.php did not define DB_NAME_DEV

	$queries["tableColumns"] = query_definition("
		SELECT column_name, is_nullable, data_type
		FROM information_schema.columns
		WHERE table_name = ?
		AND table_schema = ?
	",
		"ss",
		array($inputs['table'], $database)
	);

	$queries["allEventSummary"] = query_definition("
		SELECT events.name, events.city, events.state, accounts.name AS act, events.startdate,
			events.slug AS eventSlug, accounts.slug AS accountSlug
		FROM events
		JOIN accounts ON events.accountid = accounts.id
		WHERE events.startdate > DATE_SUB(NOW(), INTERVAL 565 DAY)
		AND events.visible = 1
		ORDER BY accounts.name, events.startdate desc
	");

	$queries["acmeCheck"] = query_definition("
		SELECT count(*) total
		FROM acme_access_requests
		WHERE email = ?
		AND approved = 1
	",
		"s",
		array($inputs['email'])
	);

	$queries["usedAcctSlugs"] = query_definition(
		"SELECT slug FROM accounts WHERE id != ?",
		"s",
		array($_SESSION['accountid'])
	);

	/** --------------------------  Account Queries  -------------------------------**/

	$queries["ccEnabled"] = query_definition("
		SELECT COALESCE(enable_online_pymts,0) AS cc_enabled
		FROM account_magicwrighter_info  
		WHERE accountid = ? 
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["ccProvider"] = query_definition("
		SELECT 
			CASE 
				WHEN COALESCE(preferences.value,0) = 1 THEN 'basys'
				WHEN COALESCE(account_magicwrighter_info.enable_online_pymts,0) = '1' THEN 'magicWrighter'
				ELSE 'none'
			END AS ccProvider,
			apiKey.value AS apiKey
		FROM accounts 
		LEFT JOIN account_magicwrighter_info ON account_magicwrighter_info.accountid = accounts.id 
		LEFT JOIN preferences ON preferences.accountid = accounts.id 	
			AND preferences.name = 'basysEnabled'
		LEFT JOIN preferences apiKey ON apiKey.accountid = accounts.id 	
			AND apiKey.name = 'basysPublicKey'
		WHERE accounts.id = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["ccChargeRt"] = query_definition("
		SELECT cc_charge_rt
		FROM accounts 
		WHERE accounts.id = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["accountEvents"] = query_definition("
		SELECT
			events.*,
			CASE
				WHEN events.startdate IS NULL THEN 'future'
				WHEN events.startdate = '0000-00-00 00:00:00' THEN 'future'
				WHEN events.enddate > curdate() THEN 'future'
				WHEN DATE_SUB(curdate(), INTERVAL 365 DAY) < events.startdate THEN 'recent'
				ELSE 'past'
			END status,
			date_format(startdate, '%b %D') formattedStart,
			CASE
				WHEN date_format(startdate, '%c') = date_format(enddate, '%c') THEN date_format(enddate, '%D')
				ELSE date_format(enddate, '%b %D')
			END formattedEnd,
			(
				SELECT count(*)
				FROM registrations
				WHERE eventid = events.id
				AND COALESCE(deleted, 0) = 0
			) registrationCount,
			(
				SELECT max(slug) FROM pages WHERE eventid = events.id AND home = 1
			) homePg
		FROM events
		WHERE accountid = ? OR (
			? = '' AND accountid IN (1000, 1001)
		)
	",
		"ss",
		array($_SESSION['accountid'], $_SESSION['accountid'])
	);

	$queries["outstandingBalances"] = query_definition("
		SELECT
			registrations.id AS registrationid,
			attendees.last_name,
			attendees.first_name,
			attendees.business,
			attendees.address1,
			attendees.address2,
			attendees.city,
			attendees.state,
			attendees.zip,
			attendees.phone,
			registrations.confirmation,
			attendees.email,
			CASE WHEN checkin IS NULL THEN 'Yes' ELSE 'No' END AS checkedIn,
			events.name AS event,
			COALESCE (payments.total, 0) payments,
			COALESCE (registration_types.price, 0) + COALESCE(extraOrders.total,0) - COALESCE (payments.total, 0) balance,
			registration_types.name AS registration_type,
			COALESCE (registration_types.price,0) + COALESCE(extraOrders.total,0) AS price,
			users.id AS userid
		FROM registrations
		JOIN events on registrations.eventid = events.id
		JOIN registration_types ON registrations.registration_typeid = registration_types.id
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		LEFT JOIN users on registrations.checkin_userid = users.id
		LEFT JOIN (
			SELECT
				registrationid,
				sum(amount) total
			FROM registration_payments
			GROUP BY registrationid
		) payments ON registrations.id = payments.registrationid
		LEFT JOIN (
			SELECT
				registrationid,
				sum(registration_extras.price) total
			FROM registration_extra_orders
			JOIN registration_extras ON registration_extras.id = registration_extra_orders.registration_extras_id
			GROUP BY registration_extra_orders.registrationid
		) extraOrders ON extraOrders.registrationid = registrations.id
		WHERE events.accountid = ?
		AND registrations.payment_method != 'sp'
		AND COALESCE (registration_types.price, 0) + COALESCE(extraOrders.total,0) - COALESCE (payments.total, 0) > 0
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["checkUserExists"] = query_definition("
		SELECT last_name, first_name FROM users
		WHERE accountid = ?
		AND email = ?
	",
		"ss",
		array($inputs['accountid'], $inputs['email'])
	);

	$queries["checkSponsorExists"] = query_definition("
		SELECT count(*) AS count
		FROM sponsors
		WHERE accountid = ?
		AND email = ?
		AND sponsors.archived != 1
		AND sponsors.pass != 'tempRequest'
	",
		"ss",
		array($_SESSION['accountid'], $inputs['email'])
	);

	$queries["accountTracks"] = query_definition("
		SELECT * FROM tracks
		WHERE tracks.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["expenseCategories"] = query_definition("
		SELECT * FROM expense_categories
		WHERE accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);
	
	$queries["courseProposals"] = query_definition("
		SELECT 
			course_proposals.*,
			users.first_name,
			users.last_name,
			users.courses_preferred,
			users.courses_able,
			COALESCE(users.email,sponsors.email) email,
			sponsors.name,
			CASE 
				WHEN course_proposals.sponsorid IS NOT NULL THEN true 
				ELSE false 
			END AS sponsorProposal 
		FROM course_proposals
		LEFT JOIN users ON users.id = course_proposals.userid
		LEFT JOIN sponsors ON sponsors.id = course_proposals.sponsorid
		WHERE (
			users.accountid = ?
			OR 
			sponsors.accountid = ?
		)
	",
		"ss",
		array($_SESSION['accountid'], $_SESSION['accountid'])
	);

	$queries["courseHistory"] = query_definition("
		SELECT
			courses.name course,
			courses.id,
			courses.archived,
			events.name event,
			sessions.name session,
			sessions.starttime,
			CASE
				WHEN sections.capacity = 0 THEN 'Unlimited'
				WHEN sections.capacity = -1 THEN rooms.capacity
				ELSE sections.capacity
			END AS capacity,
			group_concat(concat(users.last_name, ', ', users.first_name) separator ',') presenters,
			(
				SELECT count(distinct signups.registrationid)
				FROM signups
				WHERE signups.sectionid = sections.id
				AND signups.registrationid is not null
			) signupcount
		FROM sections
		JOIN courses ON sections.courseid = courses.id
		JOIN sessions ON sessions.id = sections.sessionid
		JOIN events ON sessions.eventid = events.id
		LEFT JOIN rooms ON sections.roomid = rooms.id
		LEFT JOIN section_presenters ON section_presenters.sectionid = sections.id
		LEFT JOIN users ON users.id = section_presenters.userid
		WHERE events.accountid = ?
		AND events.startdate <= curdate()
		GROUP BY courses.id, sections.id
		ORDER BY courses.name

	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["courseList"] = query_definition("
		SELECT
			courses.id,
			courses.name,
			COALESCE(courses.abbreviation,courses.name) AS abbreviation,
			courses.description,
			courses.accountid,
			COALESCE(courses.excludefromdocs,0) as excludefromdocs,
			COALESCE(courses.excludefromschedule,0) AS excludefromschedule,
			COALESCE(courses.archived,0) AS archived,
			courses.sponsorid,
			courses.color,
			courses.created_time,
			(
				SELECT events.startdate
				FROM events_courses
				JOIN events ON events.id = events_courses.eventid
				WHERE events_courses.courseid = courses.id
				AND events.accountid = courses.accountid
				ORDER BY events.startdate DESC, events.id DESC
				LIMIT 1
			) AS last_event_date,
			(
				SELECT events.name
				FROM events_courses
				JOIN events ON events.id = events_courses.eventid
				WHERE events_courses.courseid = courses.id
				AND events.accountid = courses.accountid
				ORDER BY events.startdate DESC, events.id DESC
				LIMIT 1
			) AS last_event_name,
			group_concat(course_tracks.trackid) AS tracks
		FROM courses
		LEFT JOIN course_tracks ON course_tracks.courseid = courses.id
		LEFT JOIN tracks ON tracks.id = course_tracks.trackid
		WHERE courses.accountid = ?
		GROUP BY courses.id, courses.name, courses.abbreviation, courses.description, courses.accountid,
			courses.excludefromdocs, courses.excludefromschedule, courses.archived, courses.sponsorid,
			courses.color, courses.created_time
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["videos"] = query_definition("
		SELECT videos.id, videos.name, videos.filepath, videos.display_name, 
			group_concat(video_association.courseid) AS courses
		FROM videos 
		LEFT JOIN video_association ON video_association.videoid  = videos.id
		WHERE videos.accountid = ?
		GROUP BY videos.id
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["videoOrders"] = query_definition("
		SELECT 
			video_orders.id, 
			video_orders.attendeeid, 
			video_orders.total, 
			video_orders.discount, 
			video_orders.discount_code, 
			video_orders.paid, 
			video_orders.payment_number, 
			video_orders.created_time,
			attendees.last_name,
			attendees.first_name,
			attendees.email
		FROM video_orders 
		JOIN attendees ON attendees.id = video_orders.attendeeid
		WHERE video_orders.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	if(($inputs['startDate'] ?? '') != ''){
		append_query_condition(
			$queries["videoOrders"],
			" 
			AND video_orders.created_time >= str_to_date(?,'%m/%d/%Y')",
			"s",
			array($inputs['startDate'])
		);
	}
	if(($inputs['endDate'] ?? '') != ''){
		append_query_condition(
			$queries["videoOrders"],
			" 
			AND DATE(video_orders.created_time) <= str_to_date(?,'%m/%d/%Y')",
			"s",
			array($inputs['endDate'])
		);
	}

	$queries["videoOrderDetails"] = query_definition("
		SELECT 
			video_orders.id AS orderid, 
			CASE 
				WHEN length(videos.display_name) > 0 THEN videos.display_name
				WHEN length(videos.name) > 0 THEN videos.name
				ELSE video_packages.name 
			END AS name,
			CASE WHEN videos.id IS NOT NULL THEN 'video' ELSE 'package' END AS type
		FROM video_orders 
		JOIN video_order_details ON video_order_details.video_ordersid  = video_orders.id 
		LEFT JOIN videos ON videos.id = video_order_details.videoid 
		LEFT JOIN video_packages ON video_packages.id = video_order_details.video_packagesid 
		WHERE video_orders.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	if(($inputs['startDate'] ?? '') != ''){
		append_query_condition(
			$queries["videoOrderDetails"],
			" 
			AND video_orders.created_time >= str_to_date(?,'%m/%d/%Y')",
			"s",
			array($inputs['startDate'])
		);
	}
	if(($inputs['endDate'] ?? '') != ''){
		append_query_condition(
			$queries["videoOrderDetails"],
			" 
			AND DATE(video_orders.created_time) <= str_to_date(?,'%m/%d/%Y')",
			"s",
			array($inputs['endDate'])
		);
	}

	$queries["courseTracks"] = query_definition("
		SELECT trackid
		FROM course_tracks
		WHERE courseid = ?
	",
		"s",
		array($inputs['courseId'])
	);

	$queries["getUsers"] = query_definition("
		SELECT * FROM users 
		WHERE accountid = ? 
		AND COALESCE(sponsorid,0) = 0
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["getUsersWithSponsors"] = query_definition(
		"SELECT * FROM users WHERE accountid = ?",
		"s",
		array($_SESSION['accountid'])
	);	

	$queries["getCurrentUserData"] = query_definition(
		"SELECT * FROM users WHERE id = ?",
		"s",
		array($_SESSION['userid'])
	);

	$queries["getMagicwrighterInfo"] = query_definition("
		SELECT account_magicwrighter_info.*
		FROM accounts
		LEFT JOIN  account_magicwrighter_info ON account_magicwrighter_info.accountid = accounts.id
		WHERE accounts.id = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["accountInfo"] = query_definition("
		SELECT accounts.*, 
			CASE 
				WHEN COALESCE(preferences.value,0) = 1 THEN 'basys'
				WHEN COALESCE(account_magicwrighter_info.enable_online_pymts,0) = '1' THEN 'magicWrighter'
				ELSE 'none'
			END AS ccProvider,
			apiKey.value AS apiKey
		FROM accounts
		LEFT JOIN account_magicwrighter_info ON account_magicwrighter_info.accountid = accounts.id 
		LEFT JOIN preferences ON preferences.accountid = accounts.id 	
			AND preferences.name = 'basysEnabled'
		LEFT JOIN preferences apiKey ON apiKey.accountid = accounts.id 	
			AND apiKey.name = 'basysPublicKey'
		WHERE accounts.id = ?
		OR (? = '' AND accounts.id = 1000) 
	",
		"ss",
		array($_SESSION['accountid'], $_SESSION['accountid'])
	);

	$queries["accountContactInfo"] = query_definition("
		SELECT name, contact_name, contact_email, contact_add1, contact_add2,
			contact_city, contact_state, contact_zip, contact_phone, contact_fax, sponsor_invoice_msg
		FROM accounts
		WHERE id = ?
	",
		"s",
		array($inputs['accountid'])
	);

	$queries["accountWebLogo"] = query_definition(
		"SELECT accounts.web_logo FROM accounts WHERE id = ?",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["videoReviews"] = query_definition("
		SELECT video_reviews.*
		FROM video_reviews
		JOIN attendees ON attendees.id = video_reviews.attendeeid 
		WHERE attendees.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["usedSlugs"] = query_definition(
		"SELECT slug FROM events WHERE accountid = ?",
		"s",
		array($_SESSION['accountid'])
	);

	$queries['unanswered_user_requests'] = query_definition("
		SELECT
			users.*,
			events.id eventid,
			user_event.id AS user_eventid,
			user_event.request_participation,
			user_event.request_notes
		FROM user_event
		JOIN events ON events.id = user_event.eventid
		JOIN users ON users.id = user_event.userid
		LEFT JOIN registrations ON registrations.userid = users.id
			AND registrations.eventid = user_event.eventid
		WHERE events.startdate > curdate()
		AND COALESCE(user_event.request_denied,0) != 1
		AND registrations.id IS NULL
		AND user_event.request_participation = 1 
		AND events.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries['event_capacity_check'] = query_definition("
		SELECT events.name, events.capacity, count(*) total
		FROM events
		JOIN registrations ON registrations.eventid = events.id
		WHERE events.capacity is not null
		AND registrations.deleted != 1
		AND events.accountid = ?
		AND events.startdate > curdate()
		GROUP BY events.name, events.capacity
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries['section_capacity_check'] = query_definition("
		SELECT
			events.name event,
			courses.name course,
			sections.id,
			rooms.name room,
			sessions.name session,
			CASE WHEN sections.capacity > 0 THEN sections.capacity ELSE rooms.capacity END AS capacity,
			count(distinct signups.registrationid) signups
		FROM sections
		JOIN sessions ON sections.sessionid = sessions.id
		JOIN courses ON courses.id = sections.courseid
		JOIN events ON sessions.eventid = events.id
		JOIN signups ON signups.sectionid = sections.id
			AND signups.sessionid = sessions.id
		LEFT JOIN rooms ON rooms.id = sections.roomid
		WHERE events.accountid = ?
		AND sections.capacity NOT IN (0,-2,1)
		AND events.startdate > DATE_SUB(curdate(), INTERVAL 3 DAY)
		GROUP BY events.name, sections.capacity, courses.name, sections.id;
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries['account_user_events'] = query_definition("
		SELECT user_event.*
		FROM user_event
		JOIN events ON events.id = user_event.eventid
		WHERE events.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["sponsors_enabled"] = query_definition("
		SELECT sponsors_enabled
		FROM accounts
		WHERE id = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["master_email_alerts"] = query_definition("
		SELECT email, alert_evt_req, alert_itinerary_chg, alert_course_proposal, alert_sched_signoff
		FROM users
		WHERE accountid = ?
		AND ( alert_evt_req = true OR alert_itinerary_chg = true OR  alert_course_proposal = true)
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["sponsor_orders"] = query_definition("
		SELECT 
			sponsors.name as vendor,
			vendor_orders.id AS orderid, 
			vendor_orders.created_time, 
			vendor_orders.total, 
			vendor_orders.discount, 
			vendor_orders.payment_method,
			vendor_orders.payment_number,
			vendor_orders.cancelled,
			sum(vendor_payments.amount) AS payments
		FROM vendor_orders
		JOIN sponsors ON sponsors.id = vendor_orders.sponsorid 
		LEFT JOIN vendor_payments ON vendor_payments.vendor_orders_id = vendor_orders.id
		WHERE sponsors.accountid = ?
		AND DATE(vendor_orders.created_time) BETWEEN ? AND ?
		GROUP BY vendor_orders.id
	",
		"sss",
		array($_SESSION['accountid'], $inputs['startDt'], $inputs['endDt'])
	);

	$queries["sponsor_order_deatails"] = query_definition("
		SELECT 
			vendor_orders.id AS orderid,
			events.name AS event, 
			event_sponsor_options.name, 
			vendor_order_details.quantity, 
			vendor_order_details.unit_price, 
			event_sponsor_options.type 
		FROM vendor_order_details 
		JOIN vendor_orders ON vendor_orders.id = vendor_order_details.vendor_orders_id 
		JOIN event_sponsor_options ON event_sponsor_options.id = vendor_order_details.event_sponsor_options_id 
		JOIN events ON events.id = event_sponsor_options.eventid 
		WHERE events.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	if(($inputs['startDt'] ?? '') != '') append_query_condition(
		$queries["sponsor_order_deatails"],
		" 
		AND DATE(vendor_orders.created_time) >= ?",
		"s",
		array($inputs['startDt'])
	);

	if(($inputs['endDt'] ?? '') != '') append_query_condition(
		$queries["sponsor_order_deatails"],
		" 
			AND DATE(vendor_orders.created_time) <= ?",
		"s",
		array($inputs['endDt'])
	);

	append_query_condition(
		$queries["sponsor_order_deatails"],
		" 
		ORDER BY events.name, vendor_order_details.unit_price DESC"
	);
	
	$queries['eventSponsorAttendance'] = query_definition("
		SELECT 
			vendor_orders.sponsorid, 
			vendor_orders.payment_method, 
			vendor_orders.payment_number, 
			vendor_orders.cancelled, 
			event_sponsor_options.name,
			event_sponsor_options.eventid 
		FROM vendor_order_details 
		JOIN vendor_orders ON vendor_orders.id = vendor_order_details.vendor_orders_id
		JOIN event_sponsor_options ON event_sponsor_options.id = vendor_order_details.event_sponsor_options_id 
		JOIN sponsors ON sponsors.id = vendor_orders.sponsorid 
		WHERE event_sponsor_options.type = 'event vendor'
		AND sponsors.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["eventSponsorStaffAttendance"] = query_definition("
		SELECT
		    orders.sponsorid,
		    registrations.eventid,
		    users.first_name,
		    users.last_name,
		    users.email
		FROM vendor_orders orders
		JOIN sponsors ON sponsors.id = orders.sponsorid
		JOIN users ON users.sponsorid = sponsors.id
		JOIN vendor_order_details ON vendor_order_details.vendor_orders_id = orders.id
		JOIN event_sponsor_options options ON options.id = vendor_order_details.event_sponsor_options_id
			AND options.type = 'event vendor'
		JOIN registrations ON registrations.userid = users.id
			AND registrations.eventid = options.eventid
		WHERE sponsors.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$expensesQeury = query_definition("
		SELECT 
			staff_expenses.*, 
			expense_categories.name AS category,
			users.last_name,
			users.first_name,
			users.id AS userid,
			events.name AS event_name
		FROM staff_expenses
		JOIN events ON staff_expenses.eventid = events.id
		JOIN expense_categories ON expense_categories.id = staff_expenses.expense_category_id
		JOIN users ON users.id = staff_expenses.userid
		WHERE events.accountid = ?
		AND COALESCE(events.archived, 0) != 1
	",
		"s",
		array($_SESSION['accountid'])
	);

	if($inputs['range'] == 'recent') append_query_condition($expensesQeury, "AND events.startdate BETWEEN DATE_SUB(curdate(), INTERVAL 1 YEAR) AND curdate() ");
	if($inputs['range'] == 'upcoming') append_query_condition($expensesQeury, "AND events.startdate >= curdate() ");
	if($inputs['range'] == 'upcomingAndRecent') append_query_condition($expensesQeury, "AND events.startdate >= DATE_SUB(curdate(), INTERVAL 1 YEAR) ");
	if(($inputs['eventid'] ?? '') != '') append_query_condition($expensesQeury, "AND events.id = ? ", "s", array($inputs['eventid']));

	$queries["expenses"] = $expensesQeury;

	$pyMtQuery = query_definition("
		SELECT 
			staff_payments.*,
			staff_expenses.description,
			expense_categories.name category,
			users.last_name, 
			users.first_name, 
			events.name eventName
		FROM staff_payments
		JOIN staff_expenses ON staff_payments.staff_expense_id = staff_expenses.id
		JOIN events ON staff_expenses.eventid = events.id
		JOIN expense_categories ON expense_categories.id = staff_expenses.expense_category_id 
		JOIN users ON staff_expenses.userid = users.id
		WHERE events.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	if($inputs['range'] == 'recent') append_query_condition($pyMtQuery, "AND events.startdate BETWEEN DATE_SUB(curdate(), INTERVAL 1 YEAR) AND curdate() ");
	if($inputs['range'] == 'upcoming') append_query_condition($pyMtQuery, "AND events.startdate >= curdate() ");
	if($inputs['range'] == 'upcomingAndRecent') append_query_condition($pyMtQuery, "AND events.startdate >= DATE_SUB(curdate(), INTERVAL 1 YEAR) ");
	if(($inputs['eventid'] ?? '') != '') append_query_condition($pyMtQuery, "AND events.id = ? ", "s", array($inputs['eventid']));

	$queries["staff_payments"] = $pyMtQuery;

	$queries["unapproved_acme_requests"] = query_definition("
		SELECT count(*) AS count
		FROM acme_access_requests 
		WHERE approved = 0
	");

	$queries["account_videos_on_demand"] = query_definition("
		SELECT *
		FROM videos 
		WHERE accountid = ?
		AND published_lc = 1
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["account_video_packages"] = query_definition("
		SELECT video_packages.*,
			group_concat(video_package_details.videoid) AS videoids
		FROM video_packages
		JOIN video_package_details ON video_package_details.video_packagesid = video_packages.id
		WHERE video_packages.accountid = ?
		GROUP BY video_packages.id
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["account_video_package_details"] = query_definition("
		SELECT video_package_details.*
		FROM video_packages
		JOIN video_package_details ON video_package_details.video_packagesid = video_packages.id
		WHERE video_packages.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["videos_on_demand_documents"] = query_definition("
		SELECT videos.id AS videoid, documents.filepath
		FROM videos 
		JOIN document_association ON document_association.videoid = videos.id
		JOIN documents ON documents.id = document_association.documentid
		WHERE videos.accountid = ?
		AND COALESCE(videos.price,0) > 0
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["video_packages"] = query_definition("
		SELECT 
			video_packages.*,
			(
				SELECT group_concat(distinct video_package_details.videoid  separator ',')
				FROM video_package_details 
				WHERE video_package_details.video_packagesid  = video_packages.id 
			) AS videos
		FROM video_packages
		WHERE video_packages.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["video_categories"] = query_definition("
		SELECT value 
		FROM preferences
		WHERE preferences.accountid = ?
		AND preferences.name = 'videoCategories'
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["account_preferences"] = query_definition("
		SELECT id, name, value 
		FROM preferences
		WHERE accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["video_package_details"] = query_definition("
		SELECT video_package_details.*
		FROM video_packages
		JOIN video_package_details ON video_package_details.video_packagesid  = video_packages.id 
		WHERE video_packages.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["currentSeasonPassCount"] = query_definition("
		SELECT count(*) AS curPassCount
		FROM season_passes 
		WHERE curdate() BETWEEN sunrise AND sunset 
		AND accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["currentSeasonPassDetails"] = query_definition("
		SELECT *
		FROM season_passes 
		WHERE curdate() BETWEEN sunrise AND sunset
		AND accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$idList = int_list_placeholders($inputs['ids']);
	$queries["registrationExtrasDetails"] = query_definition("
		SELECT *
		FROM registration_extras 
		WHERE id IN ({$idList['placeholders']})
	",
		$idList['types'],
		$idList['params']
	);

	$queries["seasonPassOrders"] = query_definition("
		SELECT season_pass_orders.*, attendees.first_name, attendees.last_name, attendees.email 
		FROM season_pass_orders
		JOIN attendees ON attendees.id = season_pass_orders.attendeeid 
		WHERE season_pass_orders. accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);	

	if($inputs['seasonPassId'] != ''){
		append_query_condition(
			$queries["seasonPassOrders"],
			" AND season_pass_orders.order_details LIKE ?",
			"s",
			array('%"passId":"'.$inputs['seasonPassId'].'"%')
		);
	}

	$queries["extrasOrdersBySsnPass"] = query_definition("
		SELECT 
			registration_extra_orders.id  AS extra_order_id, 
			registration_extra_orders.registration_extras_id , 
			registration_extra_orders.quantity , 
			season_pass_orders.id AS pass_order_id,
			registrations.registration_typeid
		FROM registration_extra_orders 
		JOIN registrations ON registrations.id = registration_extra_orders.registrationid 
		JOIN season_pass_orders  ON season_pass_orders.id = registrations.season_pass_ordersid 
		WHERE season_pass_orders.accountid = ?
		AND season_pass_orders.order_details LIKE ?
	",
		"ss",
		array($_SESSION['accountid'], '%"passId":"'.$inputs['seasonPassId'].'"%')
	);

	$queries["registrationsBySsnPass"] = query_definition("
		SELECT registrations.*
		FROM registrations 
		JOIN season_pass_orders  ON season_pass_orders.id = registrations.season_pass_ordersid 
		WHERE season_pass_orders.accountid = ?
		AND season_pass_orders.order_details LIKE ?
	",
		"ss",
		array($_SESSION['accountid'], '%"passId":"'.$inputs['seasonPassId'].'"%')
	);

	/** --------------------------  Attendee Queries  -------------------------------**/

	$queries["checkAttendeeExists"] = query_definition("
		SELECT *
		FROM attendees 
		WHERE email = ?
		AND accountid = ?
	",
		"ss",
		array($inputs['email'], $_SESSION['accountid'])
	);

	$queries["currentAttendeeInfo"] = query_definition("
		SELECT *
		FROM attendees 
		WHERE id = ?
	",
		"s",
		array($_SESSION['attendeeid'])
	);

	$queries["studentsByAccount"] = query_definition("
		SELECT *
		FROM students 
		WHERE accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["studentByNumber"] = query_definition("
		SELECT *
		FROM students 
		WHERE accountid = ?
		AND student_number = ?
	",
		"ss",
		array($_SESSION['accountid'], $inputs['student_number'])
	);

	$queries["checkAttendeeCredentials"] = query_definition("
		SELECT *
		FROM attendees 
		WHERE email = ?
		AND (
			password = ? OR 
			? = '' OR 
			(? = 'PSwAQwBDOr3hY' AND password IS NOT NULL AND password != '')
		)
		AND accountid = ?
	",
		"sssss",
		array($inputs['email'], $inputs['password'], $inputs['password'], $inputs['password'], $_SESSION['accountid'])
	);

	$queries["attendeeMatchQuery"] = query_definition("
		SELECT *
		FROM attendees 
		WHERE accountid = ?
		AND email IS NOT NULL
		AND email != ''
		AND(
			email = ?
			OR ( last_name = ? AND first_name = ? )
		)
	",
		"ssss",
		array($_SESSION['accountid'], $inputs['email'], $inputs['last_name'], $inputs['first_name'])
	);

	$queries["attendeeAvailableVideos"] = query_definition("
		SELECT 
			videos.*,
			CASE 
				WHEN rental_duration = 0 THEN -1
				ELSE rental_duration - timestampdiff(HOUR,video_orders.created_time,CURRENT_TIMESTAMP()) 
			END AS hoursRemaining
		FROM video_orders 
		JOIN video_order_details ON video_order_details.video_ordersid = video_orders.id
		JOIN videos ON videos.id = video_order_details.videoid
		WHERE video_orders.attendeeid = ?
		AND (
			timestampdiff(HOUR,video_orders.created_time,CURRENT_TIMESTAMP()) < videos.rental_duration 
			OR 
			videos.rental_duration = 0
		)
	",
		"s",
		array($_SESSION['attendeeid'])
	);

	$queries["attendeeVideoOrders"] = query_definition("
		SELECT 
			video_orders.id, video_orders.created_time, video_orders.total,
			video_orders.discount, video_orders.paid, 
			group_concat(COALESCE(NULLIF(videos.display_name,''), NULLIF(videos.name,''), video_packages.name) separator '\n') AS item
		FROM video_orders 
		LEFT JOIN video_order_details ON video_order_details.video_ordersid = video_orders.id
		LEFT JOIN video_packages ON video_packages.id = video_order_details.video_packagesid 
		LEFT JOIN videos ON videos.id = video_order_details.videoid  
		WHERE attendeeid = ?
		GROUP BY video_orders.id
	",
		"s",
		array($_SESSION['attendeeid'])
	);

	$queries["attendeeAvailablePackages"] = query_definition("
		SELECT 
			pkg.*,
			CASE 
				WHEN pkg.rental_duration = 0 THEN -1
				ELSE pkg.rental_duration - timestampdiff(HOUR,video_orders.created_time,CURRENT_TIMESTAMP()) 
			END AS hoursRemaining
		FROM video_orders 
		JOIN video_order_details ON video_order_details.video_ordersid = video_orders.id
		JOIN video_packages pkg ON pkg.id = video_order_details.video_packagesid 
		WHERE video_orders.attendeeid = ?
		AND (
			timestampdiff(HOUR,video_orders.created_time,CURRENT_TIMESTAMP()) < pkg.rental_duration 
			OR 
			pkg.rental_duration = 0
		)
	",
		"s",
		array($_SESSION['attendeeid'])
	);

	$queries["attendeeReviews"] = query_definition("
		SELECT *
		FROM video_reviews
		WHERE attendeeid = ?
	",
		"s",
		array($_SESSION['attendeeid'])
	);

	$queries["registrationExtrasRedeemed"] = query_definition("
		SELECT redeemed
		FROM registration_extra_orders 
		WHERE id = ?
	",
		"s",
		array($inputs['orderid'])
	);

	$queries["seasonPassByAttendee"] = query_definition("
		SELECT 
			reg.id regid,  reo.id AS orderId, reo.quantity, reg.confirmation, re.label, reg.eventid, evt.name, 
			evt.startdate, att.last_name , att.first_name, registration_types.name AS regType,
			CASE
				WHEN evt.startdate >= curdate() THEN 'future'
				ELSE 'past'
			END AS curStatus
		FROM registration_extra_orders reo
		JOIN registrations reg ON reg.id = reo.registrationid 
		LEFT JOIN registration_types ON registration_types.id = reg.registration_typeid
		JOIN registration_extras re ON re.id = reo.registration_extras_id 
		JOIN attendees att ON att.id = reg.attendeeid 
		JOIN events evt ON evt.id = reg.eventid
		WHERE reg.attendeeid = ?
		AND att.accountid = ?
		AND reg.payment_method = 'sp'
		AND evt.startdate >= DATE_SUB(CURDATE(), INTERVAL 1 DAY);
	",
		"ss",
		array($_SESSION['attendeeid'], $_SESSION['accountid'])
	);

	/** --------------------------  Learning Center Queries  -------------------------------**/

	$queries['discountCodesAvailable'] = query_definition("
		SELECT count(*) AS count
		FROM discount_codes
		WHERE CURRENT_DATE() BETWEEN sunrise AND sunset
		AND type = 'learningCenter'
		AND accountid = ?
		AND NOT EXISTS(
			SELECT id
			FROM video_orders 
			WHERE discount_code = discount_codes.code
			AND discount_codes.frequency = 'oneTime'
		)
		AND NOT EXISTS(
			SELECT id
			FROM video_orders 
			WHERE discount_code = discount_codes.code
			AND video_orders.attendeeid = ?
		)
	",
		"ss",
		array($inputs['accountid'], $inputs['attendeeid'])
	);

	$queries['discountCodeDetails'] = query_definition("
		SELECT *
		FROM discount_codes
		WHERE CURRENT_DATE() BETWEEN sunrise AND sunset
		AND code = ?
		AND type = 'learningCenter'
		AND accountid = ?
		AND NOT EXISTS(
			SELECT id
			FROM video_orders 
			WHERE discount_code = discount_codes.code
			AND discount_codes.frequency = 'oneTime'
		)
		AND NOT EXISTS(
			SELECT id
			FROM video_orders 
			WHERE discount_code = discount_codes.code
			AND video_orders.attendeeid = ?
		)
	",
		"sss",
		array($inputs['code'], $inputs['accountid'], $inputs['attendeeid'])
	);

	/** --------------------------  Sponsor Queries  -------------------------------**/

	$queries["sponsorUsernames"] = query_definition("
		SELECT username, email, name, web_address
		FROM sponsors
		WHERE accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["currentSponsorData"] = query_definition("
		SELECT * 
		FROM sponsors 
		WHERE id = ? 
		OR(
			id = ? AND 
			accountid = ?
		)
	",
		"sss",
		array($_SESSION['sponsorid'], $inputs['sponsorid'], $_SESSION['accountid'])
	);

	$queries["currentSponsorStaff"] = query_definition(
		"SELECT * FROM users WHERE sponsorid = ?",
		"s",
		array($_SESSION['sponsorid'])
	);

	$queries["sponsorRegistrations"] = query_definition("
		SELECT registrations.*
		FROM registrations
		JOIN users ON users.id = registrations.userid
		WHERE users.sponsorid = ?
	",
		"s",
		array($_SESSION['sponsorid'])
	);

	$queries["event_sponsor_options"] = query_definition("
		SELECT
			event_sponsor_options.*,
			events.name eventName,
			events.startdate eventStart
		FROM event_sponsor_options
		JOIN events ON events.id = event_sponsor_options.eventid
		WHERE events.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries['eventAttendeeList'] = query_definition("
		SELECT 
			attendees.last_name,
			attendees.first_name,
			attendees.email,
			attendees.title,
			attendees.business,
			attendees.address1,
			attendees.address2,
			attendees.city,
			attendees.state,
			attendees.zip,
			attendees.phone
		FROM registrations
		JOIN attendees ON attendees.id = registrations.attendeeid
		WHERE registrations.eventid = ?
		AND registrations.vendor_access = true
		AND registrations.deleted != 1
	",
		"s",
		array($inputs['eventid'])
	);

	$queries['eventOptions'] = query_definition("
		SELECT event_sponsor_options.*,
		(
			SELECT count(*)
		    FROM vendor_order_details
		    JOIN vendor_orders ON vendor_orders.id = vendor_order_details.vendor_orders_id
		    WHERE vendor_order_details.event_sponsor_options_id = event_sponsor_options.id
		    AND vendor_orders.cancelled != 1
		) orderCount
		FROM event_sponsor_options
		WHERE eventid = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$queries['vendorOrderItems'] = query_definition("
		SELECT 
			vendor_order_details.*, 
			event_sponsor_options.eventid,
			event_sponsor_options.name,
			event_sponsor_options.type,
			event_sponsor_options.staff_allowance,
			event_sponsor_options.registration_type_id
		FROM vendor_order_details
		JOIN vendor_orders ON vendor_orders.id = vendor_order_details.vendor_orders_id 
		LEFT JOIN event_sponsor_options ON event_sponsor_options.id = vendor_order_details.event_sponsor_options_id
		WHERE vendor_orders.sponsorid = ?
	",
		"s",
		array($inputs['sponsorid'])
	);

	$queries['vendorPayments'] = query_definition("
		SELECT 
			vendor_payments.*
		FROM vendor_payments 
		JOIN vendor_orders ON vendor_orders.id = vendor_payments.vendor_orders_id 
		WHERE vendor_orders.sponsorid = ?
	",
		"s",
		array($inputs['sponsorid'])
	);

	$queries['vendorDiscountCodesAvailable'] = query_definition("
		SELECT count(*) AS count
		FROM discount_codes
		WHERE CURRENT_DATE() BETWEEN sunrise AND sunset
		AND type = 'vendor'
		AND NOT EXISTS(
			SELECT *
			FROM vendor_orders 
			WHERE discount_code = discount_codes.code
			AND discount_codes.frequency = 'oneTime'
		)
		AND NOT EXISTS(
			SELECT *
			FROM vendor_orders 
			WHERE discount_code = discount_codes.code
			AND vendor_orders.sponsorid = ?
		)
	",
		"s",
		array($_SESSION['sponsorid'])
	);

	$queries['vendorDiscountCodeDetails'] = query_definition("
		SELECT *
		FROM discount_codes
		WHERE CURRENT_DATE() BETWEEN sunrise AND sunset
		AND code = ?
		AND type = 'vendor'
		AND NOT EXISTS(
			SELECT *
			FROM vendor_orders 
			WHERE discount_code = discount_codes.code
			AND discount_codes.frequency = 'oneTime'
		)
		AND NOT EXISTS(
			SELECT *
			FROM vendor_orders 
			WHERE discount_code = discount_codes.code
			AND vendor_orders.sponsorid = ?
		)
	",
		"ss",
		array($inputs['code'], $_SESSION['sponsorid'])
	);

	/** --------------------------  User Queries  -------------------------------**/

	$queries["userColmodelCount"] = query_definition("
		SELECT count(*) AS count
		FROM user_colmodel
		WHERE userid = ?
		AND eventid = ?
	",
		"ss",
		array($inputs['userid'], $inputs['eventid'])
	);

	$queries["userColumns"] = query_definition("
		SELECT colmodel
		FROM user_colmodel
		WHERE userid = ?
		AND eventid = ?
	",
		"ss",
		array($inputs['userid'], $inputs['eventid'])
	);

	$queries["userPageColumns"] = query_definition("
		SELECT colmodel
		FROM user_colmodel
		WHERE userid = ?
		AND page = ?
	",
		"ss",
		array($_SESSION['userid'], $inputs['page'])
	);

	$queries["eventUsers"] = query_definition("
		SELECT
			users.id userid,
			users.last_name,
			users.first_name,
			users.email,
			users.web_address,
			users.business,
			users.address1,
			users.address2,
			users.city,
			users.state,
			users.zip,
			users.phone,
			users.presenter,
			users.photo,
			users.courses_able,
			users.courses_preferred,
			users.security_groups,
			users.sponsorid,
			COALESCE(user_event.request_participation,0) request_participation,
			COALESCE(user_event.request_denied,0) request_denied,
			user_event.id user_eventid,
			(
				SELECT max(id)
				FROM registrations
				WHERE eventid = ?
				AND userid = users.id
			) registrationid,
			(
				SELECT max(registrations.id)
				FROM registrations
				JOIN attendees ON attendees.id = registrations.attendeeid
				WHERE eventid = ?
				AND attendees.email = users.email
			) nonStaffReg
		FROM users
		LEFT JOIN user_event ON user_event.userid = users.id AND user_event.eventid = ?
		WHERE COALESCE(users.archived,0) != 1
		AND users.accountid = ?
		GROUP BY users.id
		ORDER BY users.last_name, users.first_name
	",
		"ssss",
		array($inputs['eventid'], $inputs['eventid'], $inputs['eventid'], $_SESSION['accountid'])
	);

	$eventUserDataEventId = $inputs['eventid'] ?? '';
	$eventUserDataUserId = $inputs['userid'] ?? '';
	$queries["eventUserData"] = query_definition("
		SELECT
			users.id AS user_id,
			users.last_name,
			users.first_name,
			users.photo,
			users.phone,
			users.courses_able,
			users.courses_preferred,
			(
				SELECT count(id)
				FROM registrations
				WHERE email = users.email
				AND eventid = ?
			) registered,
			user_event.*
		FROM user_event
		JOIN users ON users.id = user_event.userid
		WHERE (user_event.userid = ? OR ? = '')
		AND (user_event.eventid = ? OR ? = '')
	",
		"sssss",
		array($eventUserDataEventId, $eventUserDataUserId, $eventUserDataUserId, $eventUserDataEventId, $eventUserDataEventId)
	);

	$queries["eventUserExpenses"] = query_definition("
		SELECT staff_expenses.*, SUM(staff_payments.amount) paid
		FROM staff_expenses
		LEFT JOIN staff_payments ON staff_payments.staff_expense_id = staff_expenses.id
		WHERE userid = ?
		AND eventid = ?
		GROUP BY staff_expenses.id
	",
		"ss",
		array($inputs['userid'], $inputs['eventid'])
	);

	/** --------------------------  Event Queries  -------------------------------**/

	$eventDataAccountId = $inputs['accountid'] ?? ''; // Optional account filter; empty means any account
	$eventDataSlug = (string)($inputs['slug'] ?? ''); // PHP 8.2: missing slug must not be read as an undefined array key

	if ($eventDataSlug === '') { // No slug → do not prepare eventData SQL (avoids null bind + missing local tables)
		$queries["eventData"] = query_definition('', '', array(), array()); // Safe local-dev JSON: {"rows":[]} HTTP 200
	} else {
	$queries["eventData"] = query_definition("
		SELECT
			events.accountid,
			accounts.name acctName,
			accounts.type acctType,
			COALESCE(account_fee_structure.reg_fee, 0) reg_fee,
			COALESCE(account_fee_structure.reg_fee_method, 'flat') reg_fee_method,
			COALESCE(account_fee_structure.reg_charge_to, 'attendee') reg_charge_to,
			COALESCE(account_fee_structure.reg_charge_zero_items, 0) reg_charge_zero_items,
			COALESCE(account_fee_structure.ticket_fee, 0) ticket_fee,
			COALESCE(account_fee_structure.ticket_fee_method, 'flat') ticket_fee_method,
			COALESCE(account_fee_structure.ticket_charge_to, 'attendee') ticket_charge_to,
			COALESCE(account_fee_structure.ticket_charge_zero_items, 0) ticket_charge_zero_items,
			events.startdate,
			events.enddate,
			events.start_time,
			events.site,
			events.visible,
			events.id eventid,
			events.slug,
			events.name eventName,
			events.replytoemail,
			events.showschedule,
			events.showMasterSched,
			events.showdocumentation,
			events.kioskinvoice,
			events.kioskcertificate,
			events.courseCatalogMsg,
			events.extras_cap,
			events.allow_dup_attendee,
			events.apply_cc_fee,
			events.students_only,
			events.student_restrictions,
			logo,
			CASE WHEN curdate() >= events.survey_date THEN 1 ELSE 0 END surveyVisible,
			CASE WHEN curdate() >= registrationstartdate THEN 1 ELSE 0 END registrationvisible,
			CASE WHEN curdate() BETWEEN registrationstartdate AND registrationenddate THEN 1 ELSE 0 END registrationavailable,
			events.allowsignups,
			events.requireponumber,
			events.registration_message,
			events.registration_message_primary,
			COALESCE(NULLIF(events.pymt_cc_msg,''), accounts.pymt_cc_msg) pymt_cc_msg,
			COALESCE(NULLIF(events.pymt_po_msg,''), accounts.pymt_po_msg) pymt_po_msg,
			COALESCE(NULLIF(events.pymt_chk_msg,''), accounts.pymt_chk_msg) pymt_chk_msg,
			COALESCE(account_magicwrighter_info.enable_online_pymts,0) cc_enabled,
			events.req_cc_pymt,
			events.allow_partial_pymt,
			events.sched_after_reg,
			CASE 
				WHEN
				(
					SELECT count(id) 
					FROM registrations 
					WHERE eventid = events.id AND COALESCE(deleted,0) != 1
				) >= events.capacity THEN 'true'
				WHEN
				(
					SELECT count(id) 
					FROM registrations 
					WHERE eventid = events.id AND COALESCE(deleted,0) != 1
				) >= 5 AND COALESCE(accounts.trial, 0) = 1 THEN 'true'
				ELSE 'false'
			END soldOut
		FROM events
		JOIN accounts ON events.accountid = accounts.id
			AND (accounts.id = ? OR ? = '')
		LEFT JOIN account_magicwrighter_info ON account_magicwrighter_info.accountid = accounts.id
		LEFT JOIN account_fee_structure ON account_fee_structure.accountid = accounts.id
			AND CURDATE() BETWEEN account_fee_structure.start_date AND account_fee_structure.end_date
		WHERE events.slug = ?
	",
		"sss",
		array($eventDataAccountId, $eventDataAccountId, $eventDataSlug) // Bound slug; never a missing $inputs['slug']
	);
	} // End empty-slug eventData short-circuit

	$queries["studentNumUsed"] = query_definition("
		SELECT count(id) AS count
		FROM registrations 
		WHERE eventid = ?
		AND student_number = ?
	",
		"ss",
		array($inputs['eventid'], $inputs['student_number'])
	);

	$queries["verifyEventDiscountCode"] = query_definition("
		SELECT *
		FROM discount_codes
		WHERE eventid = ?
		AND BINARY code = ?
		AND type = 'attendee'
		AND current_date() BETWEEN discount_codes.sunrise AND discount_codes.sunset
		AND(
			frequency = 'oncePerUser' OR 
			(
				SELECT count(*)
				FROM registrations 
				WHERE eventid = ?
				AND discount_code = ?
				AND confirmation != ?
			) = 0 OR 
			(
				SELECT count(*)
				FROM registrations 
				WHERE eventid = ?
				AND discount_code = ?
				AND confirmation = ?
			) = 1
		) 
	",
		"ssssssss",
		array(
			$inputs['eventid'],
			$inputs['code'],
			$inputs['eventid'],
			$inputs['code'],
			$inputs['confirmation'],
			$inputs['eventid'],
			$inputs['code'],
			$inputs['confirmation']
		)
	);

	$queries["eventDiscountsAvailable"] = query_definition("
		SELECT count(*) activeCodes
		FROM discount_codes
		WHERE eventid = ?
		AND type = 'attendee'
		AND current_date() BETWEEN discount_codes.sunrise AND discount_codes.sunset
		AND(
			frequency = 'oncePerUser' OR 
			(
				SELECT count(*)
				FROM registrations 
				WHERE eventid = ?
				AND registrations.discount_code = discount_codes.code
			) = 0 
		) 
	",
		"ss",
		array($inputs['eventid'], $inputs['eventid'])
	);

	$queries["eventDataRaw"] = query_definition("
		SELECT *
		FROM events
		WHERE events.id = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["daysUntilEvent"] = query_definition("
		SELECT dateDiff(startdate,CURDATE()) days
		FROM events
		WHERE events.id = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$eventPagesAccountId = $inputs['accountid'] ?? ($_SESSION['accountid'] ?? '');

	$queries["eventPagesFromSlug"] = query_definition("
		SELECT pages.name, pages.home, pages.slug, pages.show_registrants
		FROM pages
		JOIN events ON events.id = pages.eventid
		WHERE events.slug = ?
		AND (events.accountid = ? OR ? = '')
	",
		"sss",
		array($inputs['slug'], $eventPagesAccountId, $eventPagesAccountId)
	);

	$queries["eventPagesFromId"] = query_definition("
		SELECT pages.id, pages.name, pages.home, pages.slug
		FROM pages
		JOIN events ON events.id = pages.eventid
		WHERE events.id = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["accountidFromConfirmation"] = query_definition("
		SELECT events.accountid
		FROM registrations 
		JOIN events ON registrations.eventid = events.id 
		WHERE registrations.confirmation = ?
	",
		"s",
		array($inputs['confirmation'])
	);

	$eventRegQuery =  query_definition("
		SELECT
			events.name AS event,
			registrations.*,
			COALESCE(attendees.email, users.email) email,
			COALESCE(attendees.first_name, users.first_name) first_name,
			COALESCE(attendees.last_name, users.last_name) last_name,
			COALESCE(NULLIF(attendees.business,''), NULLIF(users.business,''), sponsors.name) business,
			COALESCE(attendees.address1, users.address1) address1,
			COALESCE(attendees.address2, users.address2) address2,
			COALESCE(attendees.city, users.city) city,
			COALESCE(attendees.state, users.state) state,
			COALESCE(attendees.zip, users.zip) zip,
			COALESCE(attendees.phone, users.phone) phone,
			COALESCE(attendees.dietary_restrictions, users.dietary_restrictions) dietary_restrictions,
			COALESCE(attendees.web_address, users.web_address) web_address,
			attendees.title,
			attendees.ec1_name,
			attendees.ec1_email,
			attendees.ec1_phone_prim,
			attendees.ec1_phone_alt,
			attendees.ec2_name,
			attendees.ec2_email,
			attendees.ec2_phone_prim,
			attendees.ec2_phone_alt,
			ROUND(
				CASE 
					WHEN discount_codes.discount IS NULL then 0 
					WHEN discount_codes.method = 'amount' THEN discount_codes.discount
					WHEN discount_codes.method = 'percent' 	THEN 
						(discount_codes.discount / 100) *  (	COALESCE (registration_types.price, 0) + COALESCE(extraOrders.total,0)	)
					ELSE 0 
				END, 2
			) discount,
			COALESCE(regFees.fee,0) serviceFee,
			CASE 
				WHEN registrations.payment_method = 'sp' THEN 0
				WHEN registration_types.vendor_reg = 1 THEN 0
				ELSE ROUND(COALESCE (payments.total, 0),2) 
			END payments,
			CASE 
				WHEN registrations.payment_method = 'sp' THEN 0
				WHEN registration_types.vendor_reg = 1 THEN 0
				ELSE ROUND(COALESCE (payments.cc_fee, 0),2) 
			END cc_fees,
			CASE 
				WHEN registrations.payment_method = 'sp' THEN 0
				WHEN registration_types.vendor_reg = 1 THEN 0
				ELSE ROUND(
						COALESCE (registration_types.price, 0) + 
						COALESCE (regFees.fee, 0) + 
						COALESCE (payments.cc_fee, 0) + 
						COALESCE(extraOrders.total,0) - 
						COALESCE (payments.total, 0) -
						(
							CASE 
								WHEN discount_codes.discount IS NULL then 0 
								WHEN discount_codes.method = 'amount' THEN discount_codes.discount
								WHEN discount_codes.method = 'percent' 	THEN 
									(discount_codes.discount / 100) *  (	COALESCE (registration_types.price, 0) + COALESCE(extraOrders.total,0)	)
								ELSE 0 
							END
						),
					2) 
			END balance,
			Concat(checkinUser.last_name,	', ', checkinUser.first_name) AS checkin_user,
			registration_types.name AS registration_type,
			COALESCE(registration_types.price,0) AS reg_type_price,
			CASE 
				WHEN registrations.payment_method = 'sp' THEN 0
				WHEN registration_types.vendor_reg = 1 THEN 0
				ELSE ROUND(COALESCE (registration_types.price,0) + COALESCE(extraOrders.total,0),2) 
			END price,
			(SELECT count(*) FROM signups WHERE registrationid = registrations.id) AS signupCount
		FROM registrations
		JOIN events ON registrations.eventid = events.id
		JOIN registration_types ON registrations.registration_typeid = registration_types.id
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		LEFT JOIN users checkinUser ON registrations.checkin_userid = checkinUser.id
		LEFT JOIN users ON registrations.userid = users.id
		LEFT JOIN sponsors ON sponsors.id = users.sponsorid
		LEFT JOIN (
			SELECT  
				registrations.id AS regId, 
				ROUND((
					CASE 
						WHEN COALESCE(fees.reg_charge_to,'account') != 'attendee' THEN 0
						WHEN registration_types.price = 0 AND fees.reg_charge_zero_items = 0 THEN 0
						WHEN fees.reg_meth = 'flat' THEN fees.reg_fee
						ELSE ROUND(registration_types.price * (reg_fee/100),2)
					END
				) + (
					CASE 
						WHEN COALESCE(fees.tix_charge_to,'account') != 'attendee' THEN 0
						WHEN fees.tix_fee = 0 AND fees.tix_charge_zero_items = 0 THEN 0
						WHEN fees.tix_meth = 'flat' THEN fees.tix_fee * COALESCE(extraOrders.qty,0)
						ELSE ROUND(extraOrders.total * (tix_fee/100),2)
					END 
				),2) AS fee
			FROM registrations
			JOIN events ON registrations.eventid = events.id
			LEFT JOIN (
				SELECT 
					accountid,
					COALESCE(reg_fee,0) reg_fee,
					COALESCE(reg_fee_method,'flat') reg_meth,
					COALESCE(account_fee_structure.reg_charge_zero_items, 0) reg_charge_zero_items,
					COALESCE(account_fee_structure.reg_charge_to, 'attendee') reg_charge_to,
					COALESCE(ticket_fee,0) tix_fee,
					COALESCE(ticket_fee_method,'flat') tix_meth,
					COALESCE(account_fee_structure.ticket_charge_zero_items, 0) tix_charge_zero_items,
					COALESCE(account_fee_structure.ticket_charge_to, 'attendee') tix_charge_to,
					account_fee_structure.start_date,
					account_fee_structure.end_date
				FROM account_fee_structure 
				WHERE accountid = ?
			) fees ON events.accountid = fees.accountid
				AND registrations.create_date BETWEEN fees.start_date AND fees.end_date
			LEFT JOIN registration_types  on registration_types.id = registrations.registration_typeid 
			LEFT JOIN (
				SELECT 
					registrationid, 
					sum(registration_extras.price * COALESCE(registration_extra_orders.quantity,0)) total,
					sum(COALESCE(registration_extra_orders.quantity,0)) qty
				FROM registration_extra_orders
				JOIN registration_extras 
					ON registration_extras.id = registration_extra_orders.registration_extras_id
					AND registration_extras.price > 0
				GROUP BY registration_extra_orders.registrationid
			) extraOrders ON extraOrders.registrationid = registrations.id
			WHERE events.accountid = ?
		) regFees ON regFees.regId = registrations.id
		LEFT JOIN (
			SELECT registrationid, sum(amount) total, sum(cc_fee) cc_fee
			FROM registration_payments
			GROUP BY registrationid
		) payments ON registrations.id = payments.registrationid
		LEFT JOIN (
			SELECT registrationid, 
				sum(registration_extras.price * COALESCE(registration_extra_orders.quantity,0)) total
			FROM registration_extra_orders
			JOIN registration_extras ON registration_extras.id = registration_extra_orders.registration_extras_id
				AND registration_extras.price > 0
			GROUP BY registration_extra_orders.registrationid
		) extraOrders ON extraOrders.registrationid = registrations.id
		LEFT JOIN discount_codes ON discount_codes.eventid = events.id
			AND discount_codes.code = registrations.discount_code
		WHERE events.accountid = ?
	",
		"sss",
		array($_SESSION['accountid'], $_SESSION['accountid'], $_SESSION['accountid'])
	);

	if(($inputs['eventid'] ?? '') != '') append_query_condition($eventRegQuery, "AND registrations.eventid = ? ", "s", array($inputs['eventid']));
	if(($inputs['last_name'] ?? '') != '') append_query_condition($eventRegQuery, "AND attendees.last_name = ? ", "s", array($inputs['last_name']));
	if(($inputs['first_name'] ?? '') != '') append_query_condition($eventRegQuery, "AND attendees.first_name = ? ", "s", array($inputs['first_name']));
	if(($inputs['business'] ?? '') != '') append_query_condition($eventRegQuery, "AND trim(attendees.business) = ? ", "s", array($inputs['business']));
	if(($inputs['confirmation'] ?? '') != '') append_query_condition($eventRegQuery, "AND trim(registrations.confirmation) = ? ", "s", array($inputs['confirmation']));
	if($inputs['range'] == 'recent') append_query_condition($eventRegQuery, "AND events.startdate BETWEEN DATE_SUB(curdate(), INTERVAL 1 YEAR) AND curdate() ");
	if($inputs['range'] == 'upcoming') append_query_condition($eventRegQuery, "AND events.startdate >= curdate() ");
	if($inputs['range'] == 'upcomingAndRecent') append_query_condition($eventRegQuery, "AND events.startdate >= DATE_SUB(curdate(), INTERVAL 1 YEAR) ");
	if($inputs['balance'] == 'true') append_query_condition($eventRegQuery, "AND COALESCE(payments.total,0) < registration_types.price ");
	if(($inputs['paymentNumber'] ?? '') != '') append_query_condition($eventRegQuery, "AND registrations.payment_number = ? ", "s", array($inputs['paymentNumber']));
	if($inputs['noArchived'] == 'true') append_query_condition($eventRegQuery, "AND events.archived != 1 ");

	append_query_condition($eventRegQuery, "GROUP BY registrations.id
		ORDER BY attendees.last_name, attendees.first_name, registrations.id
	");

	$queries["eventRegistrations"] = $eventRegQuery;

	$registrationEventId = $inputs['eventid'] ?? '';
	$queries["registrationData"] = query_definition("
		SELECT
			COALESCE(attendees.accountid,users.accountid) AS accountid,
			COALESCE(attendees.address1,users.address1) AS address1,
			COALESCE(attendees.address2,users.address2) AS address2,
			COALESCE(attendees.city,users.city) AS city,
			COALESCE(attendees.state,users.state) AS state,
			COALESCE(attendees.zip,users.zip) AS zip,
			COALESCE(attendees.business,users.business) AS business,
			attendees.dietary_restrictions,
			attendees.ec1_email,
			attendees.ec1_name,
			attendees.ec1_phone_prim,
			attendees.ec1_phone_alt,
			attendees.ec2_email,
			attendees.ec2_name,
			attendees.ec2_phone_prim,
			attendees.ec2_phone_alt,
			COALESCE(attendees.email,users.email) AS email,
			COALESCE(attendees.first_name,users.first_name) AS first_name,
			COALESCE(attendees.last_name,users.last_name) AS last_name,
			COALESCE(attendees.phone,users.phone) AS phone,
			COALESCE(attendees.web_address,users.web_address) AS web_address,
			registrations.vendor_access,
			registrations.payment_method,
			registrations.payment_number,
			registrations.id,
			registrations.attendeeid,
			registrations.eventid,
			registrations.confirmation,
			registrations.registration_typeid,
			registrations.checkin,
			registrations.checkin_userid,
			registrations.create_date,
			registrations.modify_date,
			registrations.payment_method,
			registrations.payment_number,
			registrations.userid,
			registrations.vendor_access,
			registrations.deleted,
			registration_types.price,
			registrations.discount_code,
			registration_types.name registration_type,
			events.name event_name,
			COALESCE(
				(
					SELECT sum(amount)
					FROM registration_payments
					WHERE registration_payments.registrationid = registrations.id
				),0) payments,
			COALESCE(
				(
					SELECT sum(cc_fee)
					FROM registration_payments
					WHERE registration_payments.registrationid = registrations.id
				),0) ccFeesPaid
		FROM registrations
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		LEFT JOIN users ON users.id = registrations.userid
		LEFT JOIN registration_types ON registration_types.id = registrations.registration_typeid
		LEFT JOIN events ON events.id = registrations.eventid
		WHERE registrations.confirmation = ?
		AND (registrations.eventid = ? OR ? = '');
	",
		"sss",
		array($inputs['confirmation'], $registrationEventId, $registrationEventId)
	);

	$queries["eventPresenterList"] = query_definition("
		SELECT users.* FROM users
		LEFT JOIN user_event ON user_event.userid = users.id
		WHERE user_event.eventid = ?
		AND COALESCE(users.presenter,0) = 1
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["eventSponsorStaff"] = query_definition("
		SELECT users.id, users.first_name, users.last_name, sponsors.name AS sponsor
		FROM registrations
		JOIN users ON registrations.userid = users.id
		JOIN sponsors ON users.sponsorid = sponsors.id
		WHERE registrations.eventid = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["sectionSessions"] = query_definition("
		SELECT * FROM section_sessions
		WHERE sectionid = ?
	",
		"s",
		array($inputs['sectionid'])
	);

	$queries["eventSectionSessions"] = query_definition("
		SELECT
			sections.id sectionid,
			sessions.id sessionid,
			courses.id courseid,
			rooms.id roomid,
			sessions.name sessionName,
			rooms.name roomName,
			courses.name courseName,
			group_concat(concat(users.first_name, ' ', users.last_name) 
				order by users.last_name separator ', ') presenters
		FROM section_sessions
		JOIN sessions ON sessions.id = section_sessions.sessionid
		JOIN sections ON sections.id = section_sessions.sectionid
		JOIN courses ON courses.id = sections.courseid
		JOIN rooms ON rooms.id = sections.roomid
		LEFT JOIN section_presenters ON sections.id = section_presenters.sectionid
		LEFT JOIN users ON section_presenters.userid = users.id
		WHERE sessions.eventid = ?
		GROUP BY sections.id, sessions.id, courses.id, rooms.id
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["sectionData"] = query_definition(
		"SELECT * FROM sections WHERE id = ?",
		"s",
		array($inputs['sectionid'])
	);

	$queries["eventCourses"] = query_definition("
		SELECT
			events_courses.id events_courses_id,
			courses.*,
			course_presenters.presenters,
			group_concat(tracks.name order by tracks.name separator ',') tracks
		FROM events_courses
		JOIN courses ON events_courses.courseid = courses.id
		LEFT JOIN course_tracks ON  course_tracks.courseid = courses.id
		LEFT JOIN tracks ON tracks.id = course_tracks.trackid
		LEFT JOIN (
			SELECT 
				sections.courseid, 
				group_concat(distinct concat(users.last_name) order by users.last_name separator ',') presenters
			FROM sections 
			JOIN sessions ON sections.sessionid  = sessions.id
			JOIN courses ON courses.id = sections.courseid 
			JOIN section_presenters ON section_presenters.sectionid = sections.id
			JOIN users ON users.id = section_presenters.userid 
			WHERE sessions.eventid = ?
			GROUP by sections.courseid 
		) course_presenters ON course_presenters.courseid = courses.id
		WHERE events_courses.eventid = ?
		GROUP BY events_courses.id
	",
		"ss",
		array($inputs['eventid'], $inputs['eventid'])
	);

	$queries["eventRooms"] = query_definition(
		"SELECT * FROM rooms WHERE eventid = ? ORDER BY sortorder",
		"s",
		array($inputs['eventid'])
	);

	$queries["eventSessions"] = query_definition("
		SELECT *,
			DATE_FORMAT(sessions.starttime, '%m/%d/%y') sessionDate,
			DATE_FORMAT(sessions.starttime, '%a') weekDay,
			DATE_FORMAT(sessions.starttime, '%h:%i %p') displayStart,
			DATE_FORMAT(sessions.starttime, '%a') dayName,
			DATE_FORMAT(sessions.starttime, '%b') month,
			DATE_FORMAT(sessions.starttime, '%D') displayDay,
			DATE_FORMAT(sessions.endtime, '%h:%i %p') displayEnd
		FROM sessions
		WHERE eventid = ?
		ORDER BY starttime
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["eventSessionsExpanded"] = query_definition("
		SELECT
			sections.id sectionid,
			sections.is_virtual,
			sections.web_link,
			sections.web_link2,
			sections.web_pass,
			sections.exclude_att_limit,
			sections.courseid,
			courses.name course,
			sessions.name session,
			date_format(sessions.starttime, '%Y%m%d%H%i') dtTime,
			date_format(sessions.starttime, '%c/%e/%Y') date,
			date_format(sessions.starttime, '%h:%i %p') starttime,
			date_format(sessions.endtime, '%h:%i %p') endtime,
			CASE WHEN sections.capacity = 0 THEN 'Unlimited'
				WHEN sections.capacity = -1 THEN rooms.capacity
				WHEN sections.capacity = -2 THEN 0
				ELSE sections.capacity
			END capacity,
			(
				SELECT count(distinct signups.registrationid)
				FROM signups
				JOIN registrations ON registrations.id = signups.registrationid 
				WHERE signups.sectionid = sections.id
				AND registrations.deleted != 1
			) signupcount,
			rooms.name room,
			courses.description,
			group_concat(distinct concat(users.first_name, ' ', users.last_name) order by users.last_name separator ', ') presenter,
			group_concat(distinct users.email order by users.last_name separator ', ') presenterEmails,
			group_concat(tracks.name) AS tracks
		FROM sections
		JOIN courses ON sections.courseid = courses.id
		JOIN sessions ON sections.sessionid = sessions.id
		LEFT JOIN rooms ON sections.roomid = rooms.id
		LEFT JOIN section_presenters ON sections.id = section_presenters.sectionid
		LEFT JOIN users ON section_presenters.userid = users.id
		LEFT JOIN course_tracks ON course_tracks.courseid = courses.id
		LEFT JOIN tracks ON tracks.id = course_tracks.trackid
		WHERE sessions.eventid = ?
		GROUP BY sessions.id, sections.id, courses.id, rooms.id
		UNION
		SELECT
			sections.id sectionid,
			sections.is_virtual,
			sections.web_link,
			sections.web_link2,
			sections.web_pass,
			sections.exclude_att_limit,
			sections.courseid,
			courses.name course,
			sessions.name session,
			date_format(sessions.starttime, '%Y%m%d%H%i') dtTime,
			date_format(sessions.starttime, '%c/%e/%Y') date,
			date_format(sessions.starttime, '%h:%i %p') starttime,
			date_format(sessions.endtime, '%h:%i %p') endtime,
			CASE WHEN sections.capacity = 0 THEN 'Unlimited'
				WHEN sections.capacity = -1 THEN rooms.capacity
				WHEN sections.capacity = -2 THEN 0
				ELSE sections.capacity
			END capacity,
			(
				SELECT count(distinct signups.registrationid)
				FROM signups
				JOIN registrations ON registrations.id = signups.registrationid 
				WHERE signups.sectionid = sections.id
				AND registrations.deleted != 1
			) signupcount,
			rooms.name room,
			courses.description,
			group_concat(distinct concat(users.first_name, ' ', users.last_name) order by users.last_name separator ', ') presenter,
			group_concat(distinct users.email order by users.last_name separator ', ') presenterEmails,
			group_concat(tracks.name) AS tracks
		FROM section_sessions
		JOIN sections ON section_sessions.sectionid = sections.id
		JOIN courses ON sections.courseid = courses.id
		JOIN sessions ON section_sessions.sessionid = sessions.id
		LEFT JOIN rooms ON sections.roomid = rooms.id
		LEFT JOIN section_presenters ON sections.id = section_presenters.sectionid
		LEFT JOIN users ON section_presenters.userid = users.id
		LEFT JOIN course_tracks ON course_tracks.courseid = courses.id
		LEFT JOIN tracks ON tracks.id = course_tracks.trackid
		WHERE sessions.eventid = ?
		GROUP BY sessions.id, sections.id, courses.id, rooms.id
		ORDER BY date, starttime asc, endtime desc, course, presenter
	",
		"ss",
		array($inputs['eventid'], $inputs['eventid'])
	);

	$queries["eventSections"] = query_definition("
		SELECT
			sections.id,
			courses.name course,
			sections.capacity,
			sections.attendance,
			sessions.name session,
			sessions.starttime,
			rooms.name room,
			CASE WHEN sections.capacity = 0 THEN 'Unlimited'
				WHEN sections.capacity = -1 THEN rooms.capacity
				WHEN sections.capacity = -2 THEN 0
				ELSE sections.capacity
			END capacityRm,
			sections.roomid,
			sections.courseid,
			COALESCE(sections.color,courses.color) color,
			sections.sessionid,
			sections.excludefromschedule,
			sections.is_virtual,
			sections.web_link,
			sections.web_link2,
			sections.exclude_att_limit,
			users.id userid,
			users.last_name,
			users.first_name,
			sections.is_virtual,
			group_concat(distinct otherSessions.name separator ',') additionalSessions,
			(
				SELECT count(distinct signups.registrationid)
				FROM signups
				JOIN registrations ON registrations.id = signups.registrationid 
				WHERE signups.sectionid = sections.id
				AND registrations.deleted != 1
			) signupcount
		FROM sections
		JOIN sessions ON sessions.id = sections.sessionid
		JOIN courses ON courses.id = sections.courseid
		JOIN rooms ON rooms.id = sections.roomid
		LEFT JOIN section_presenters ON section_presenters.sectionid = sections.id
		LEFT JOIN users ON users.id = section_presenters.userid
		LEFT JOIN section_sessions ON section_sessions.sectionid = sections.id
		LEFT JOIN sessions otherSessions ON otherSessions.id = section_sessions.sessionid
		WHERE sessions.eventid = ?
		GROUP BY sections.id
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["eventSectionsAggregated"] = query_definition("
		SELECT
			sections.id sectionid,
			sections.courseid courseid,
			COALESCE(sections.color,courses.color) color,
			sections.sessionid sessionid,
			courses.name coursename,
			courses.abbreviation,
			sections.roomid,
			sections.is_virtual,
			sections.web_link,
			sections.web_link_external,
			sections.web_link2,
			sections.web_pass,
			sections.exclude_att_limit,
			COALESCE(sections.excludefromschedule, courses.excludefromschedule, 0) excludeFromSched,
			case when sections.capacity is null then 0 else sections.capacity end capacity,
			case when rooms.capacity is null then 0 else rooms.capacity end roomcapacity,
			group_concat(distinct course_tracks.trackid order by tracks.name separator ',') trackids,
			group_concat(distinct tracks.name separator ', ') tracknames,
			group_concat(distinct section_presenters.userid order by users.last_name separator ',') presenterids,
			group_concat(distinct concat(users.first_name, ' ', users.last_name) separator ', ') presenternames,
			group_concat(distinct concat(users.photo) separator ',') presenterphotos,
			group_concat(distinct section_sessions.sessionid order by section_sessions.sessionid separator ',') additionalsessions,
			group_concat(distinct additionalsessions.name order by additionalsessions.id separator ', ') additionalsessionnames,
			count(distinct signups.registrationid) registrations
		FROM sections
			JOIN sessions ON sections.sessionid = sessions.id
			LEFT JOIN section_presenters ON sections.id = section_presenters.sectionid
			LEFT JOIN users ON section_presenters.userid = users.id
			JOIN rooms ON rooms.id = sections.roomid
			LEFT JOIN section_sessions ON section_sessions.sectionid = sections.id
			LEFT JOIN sessions additionalsessions ON section_sessions.sessionid = additionalsessions.id
			JOIN events ON sessions.eventid = events.id
			JOIN courses ON sections.courseid = courses.id
			LEFT JOIN course_tracks ON courses.id = course_tracks.courseid
			LEFT JOIN tracks ON course_tracks.trackid = tracks.id
			LEFT JOIN signups ON signups.sectionid = sections.id
		WHERE events.id = ?
		GROUP BY sections.id
		ORDER BY courses.name
	",
		"s",
		array($inputs['eventid'])
	);

	$eventPresenterQuery =  query_definition("
		SELECT distinct
			sections.id sectionid,
			u.last_name last_name,
			COALESCE(u.first_name, 'No Presenter') AS first_name,
			u.email,
			sessions.name session,
			date_format(sessions.starttime, '%Y%m%d%H%i') dtTime,
			date_format(sessions.starttime, '%c/%e/%Y') date,
			date_format(sessions.starttime, '%h:%i %p') starttime,
			date_format(sessions.endtime, '%h:%i %p') endtime,
			rooms.name room,
			rooms.sortorder AS roomsort,
			CASE WHEN sections.capacity = 0 THEN 'Unlimited'
				WHEN sections.capacity = -1 THEN rooms.capacity
				WHEN sections.capacity = -2 THEN 0
				ELSE sections.capacity
			END capacity,
			sections.attendance,
			courses.id courseid,
			courses.name course,
			courses.description,
			(SELECT count(distinct registrationid) FROM signups WHERE sectionid = sections.id) AS signups
		FROM sessions
		LEFT JOIN sections ON sections.sessionid = sessions.id
		LEFT JOIN section_presenters ON section_presenters.sectionid = sections.id
		LEFT JOIN section_sessions ON section_sessions.sectionid = sections.id
        LEFT JOIN users u ON u.id = section_presenters.userid
		LEFT JOIN rooms ON sections.roomid = rooms.id
		LEFT JOIN courses ON sections.courseid = courses.id
		WHERE sessions.eventid = ?
	",
		"s",
		array($inputs['eventid'])
	);

	if(($inputs['userid'] ?? '') != '') append_query_condition($eventPresenterQuery, "AND u.id = ? ", "s", array($inputs['userid']));
	append_query_condition($eventPresenterQuery, " ORDER BY u.last_name, u.first_name, sessions.starttime");

	$queries["eventPresenters"] = $eventPresenterQuery;

	$queries["eventSignups"] = query_definition("
		SELECT
			registrations.confirmation,
			registrations.id registrationid,
			COALESCE(attendees.last_name, userAtt.last_name) last_name,
			COALESCE(attendees.first_name, userAtt.first_name) first_name,
			attendees.title,
			COALESCE(attendees.email, userAtt.email) email,
			COALESCE(attendees.business, userAtt.business) business,
			COALESCE(attendees.state, userAtt.state) state,
			registration_types.name AS registration_type,
			sessions.name session,
			concat(date_format(sessions.starttime, '%a, %h:%i'), '-', date_format(sessions.endtime, '%h:%i')) date,
			courses.id courseid,
			courses.name course,
			rooms.name room,
			group_concat(distinct concat(users.first_name, ' ', users.last_name) order by users.last_name separator ', ') presenter,
			sections.id sectionid,
			signups.id signupsid
		FROM sections
		JOIN courses ON sections.courseid = courses.id
		JOIN rooms ON sections.roomid = rooms.id
		JOIN sessions ON sections.sessionid = sessions.id
		LEFT JOIN section_presenters ON sections.id = section_presenters.sectionid
		LEFT JOIN users ON section_presenters.userid = users.id
		LEFT JOIN signups ON sections.id = signups.sectionid
		JOIN registrations ON signups.registrationid = registrations.id
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		LEFT JOIN users userAtt ON userAtt.id = registrations.userid
		LEFT JOIN registration_types ON registration_types.id = registrations.registration_typeid
		WHERE sessions.eventid = ?
		GROUP BY signups.id
		ORDER BY attendees.last_name, attendees.first_name, sessions.starttime
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["sectionSignups"] = query_definition("
		SELECT DISTINCT 
			signups.sectionid, 
			attendees.last_name, 
			attendees.first_name, 
			attendees.email,
			registrations.confirmation
		FROM signups
		JOIN registrations ON registrations.id = signups.registrationid
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		JOIN sessions ON sessions.id = signups.sessionid
		WHERE sessions.eventid = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$extraRegEventId = $inputs['eventid'] ?? '';
	$extraRegAccountId = $inputs['accountid'] ?? '';
	$extraRegSessionAccountId = $_SESSION['accountid'] ?? '';
	$queries["extraRegFields"] = query_definition("
		SELECT
			id,
			eventid,
			type,
			name,
			label,
			size,
			replace(label, ' ', '_') AS field,
			required,
			sortorder,
			replace(replace(options,'\r\n',';'),'\n',';') AS options,
			'false' AS accountField
		FROM registration_fields
		WHERE eventid = ? OR '' = ?
		UNION 
		SELECT
			registration_fields.id,
			registration_fields.eventid,
			registration_fields.type,
			registration_fields.name,
			registration_fields.label,
			registration_fields.size,
			replace(registration_fields.label, ' ', '_') AS field,
			registration_fields.required,
			registration_fields.sortorder,
			replace(replace(registration_fields.options,'\r\n',';'),'\n',';') AS options,
			'true' AS accountField
		FROM registration_fields
		LEFT JOIN registration_field_exclusions 
			ON registration_field_exclusions.registration_fields_id = registration_fields.id
			AND registration_field_exclusions.eventid = ?
		WHERE accountid = COALESCE(NULLIF(?,''),?)
		AND registration_fields.archived != 1
		AND registration_field_exclusions.id IS NULL
	",
		"sssss",
		array($extraRegEventId, $extraRegEventId, $extraRegEventId, $extraRegAccountId, $extraRegSessionAccountId)
	);

	$queries["extraRegData"] = query_definition("
		SELECT
			registrations.id AS registrationId,
			REPLACE(registration_fields.label,' ', '_') AS label,
			registration_fields.id AS fieldId,
			registration_fields.required,
			registration_data.data AS data
		FROM registration_fields
		JOIN registrations ON registrations.eventid = registration_fields.eventid
			OR registration_fields.accountid = ?
		JOIN registration_data ON registration_data.registrationid = registrations.id
			AND registration_data.registration_fieldid = registration_fields.id
		WHERE (
			registrations.eventid = ? 
			OR '' = ?
		)
	",
		"sss",
		array($_SESSION['accountid'], $extraRegEventId, $extraRegEventId)
	);
	if(($inputs['eventids'] ?? '') != ''){
		$eventIdList = int_list_placeholders($inputs['eventids']);
		append_query_condition(
			$queries['extraRegData'],
			"AND(
			registrations.eventid IN ({$eventIdList['placeholders']})
			OR '' = ?
		)",
			$eventIdList['types'] . "s",
			array_merge($eventIdList['params'], array($inputs['eventids']))
		);
	}

	$queries["extraRegDataByConfirmation"] = query_definition("
		SELECT registration_data.data, REPLACE(registration_fields.label,' ', '_') AS label
		FROM registration_data
		JOIN registration_fields ON registration_fields.id = registration_data.registration_fieldid
		JOIN registrations ON registrations.id = registration_data.registrationid
		WHERE registrations.confirmation = ?
		AND registrations.eventid = ?;
	",
		"ss",
		array($inputs['confirmation'], $inputs['eventid'])
	);

	$queries["regExtraOrders"] = query_definition("
		SELECT 
			registration_extra_orders.id orderId, 
			COALESCE(registration_extra_orders.quantity,1) quantity, 
			registration_extras.*
		FROM registration_extra_orders
		JOIN registrations ON registrations.id = registration_extra_orders.registrationId
		JOIN registration_extras ON registration_extras.id = registration_extra_orders.registration_extras_id
		WHERE registrations.confirmation = ?
	",
		"s",
		array($inputs['confirmation'])
	);

	$queries["regExtraOrdersByEvent"] = query_definition("
		SELECT 
			registrations.id registrationid,
			registration_extra_orders.id orderId, 
			COALESCE(registration_extra_orders.quantity,1) quantity,
			registration_extra_orders.redeemed,
			registration_extras.id extraId,
			registration_extras.label,
			registration_extras.description,
			registration_extras.price,
			registration_extras.sunrise,
			registration_extras.sunset,
			registration_extras.sortorder,
			registration_extras.registration_types
		FROM registration_extra_orders
		JOIN registrations ON registrations.id = registration_extra_orders.registrationId
		JOIN registration_extras ON registration_extras.id = registration_extra_orders.registration_extras_id
		WHERE registrations.eventid = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["registrationTypes"] = query_definition("
		SELECT
			id,
			name,
			price,
			vendor_reg,
			date_format(sunrise, '%m/%d/%Y') sunrise,
			date_format(sunset, '%m/%d/%Y') sunset,
			sortorder,
			target_regs,
			CASE WHEN current_date() BETWEEN sunrise AND sunset THEN '1' ELSE '0' END AS current,
			CASE WHEN current_date() BETWEEN reg_start_dt AND reg_end_dt THEN '1' ELSE '0' END AS schedAvailable
		FROM registration_types
		WHERE eventid= ?
		ORDER BY sortorder, name
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["registrationExtras"] = query_definition("
		SELECT
			registration_extras.*,
			CASE WHEN curdate() BETWEEN sunrise AND sunset THEN 'true' ELSE 'false' END AS current,
			(
				SELECT sum(quantity) 
				FROM registration_extra_orders 
				JOIN registrations ON registrations.id = registration_extra_orders.registrationid
				WHERE registration_extras_id  = registration_extras.id
					AND COALESCE(registrations.deleted, 0) != 1
			) AS purchases
		FROM registration_extras
		WHERE eventid = ?",
		"s",
		array($inputs['eventid'])
	);

	$queries["getRegByEmail"] = query_definition("
		SELECT *
		FROM registrations
		JOIN attendees ON attendees.id = registrations.attendeeid
		WHERE registrations.eventid = ?
		AND attendees.email = ?
	",
		"ss",
		array($inputs['eventid'], $inputs['email'])
	);

	$queries["getEventIdFromUrl"] = query_definition("
		SELECT id FROM events
		WHERE slug = ?
		AND slug NOT LIKE '%easyreg%';
	",
		"s",
		array($inputs['slug'])
	);

	$queries["regTypeInfoFromConfirmation"] = query_definition("
		SELECT registration_types.*
		FROM registrations
		JOIN registration_types ON registrations.registration_typeid = registration_types.id
		WHERE registrations.confirmation = ?
	",
		"s",
		array($inputs['confirmation'])
	);

	$queries["sectionSeatsAvailable"] = query_definition("
		SELECT 
			(CASE sections.capacity
				WHEN -2 THEN 0
				WHEN -1 THEN COALESCE(rooms.capacity,0)
				WHEN 0 THEN 99999
				ELSE COALESCE(sections.capacity,0)
			END) - 
			(
				SELECT count(distinct registrationid)
				FROM signups 
				WHERE signups.sectionid  = sections.id
			) available
		FROM sections 
		JOIN sessions ON sections.sessionid = sessions.id
		LEFT JOIN rooms ON sections.roomid = rooms.id
		WHERE sections.id = ?
	",
		"s",
		array($inputs['sectionid'])
	);

	$queries["eventVendorOrderSummary"] = query_definition("
		SELECT 
			event_sponsor_options.name, 
			vendor_orders.sponsorid, 
			event_sponsor_options.price, 
			vendor_orders.total, 
			event_sponsor_options.eventid,
			SUM(COALESCE(vendor_payments.amount,0)) paid
		FROM vendor_orders 
		JOIN vendor_order_details ON vendor_order_details.vendor_orders_id = vendor_orders.id
		JOIN event_sponsor_options  ON event_sponsor_options.id = vendor_order_details.event_sponsor_options_id 
		LEFT JOIN vendor_payments ON vendor_payments.vendor_orders_id = vendor_orders.id
		WHERE event_sponsor_options.eventid = ?
		AND event_sponsor_options.type = 'event vendor'
		AND vendor_orders.cancelled  != '1'
		GROUP BY 
			event_sponsor_options.name, 
			vendor_orders.sponsorid, 
			event_sponsor_options.price, 
			vendor_orders.total
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["eventRegSummary"] = query_definition("
		SELECT registration_types.id, registration_types.name, registration_types.price, 
			count(registrations.id) orderCount
		FROM registration_types 
		LEFT JOIN registrations ON registrations.registration_typeid = registration_types.id
			AND registrations.deleted != 1
			AND registrations.eventid = registration_types.eventid
		WHERE registration_types.eventid = ?
		GROUP BY registration_types.id
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["eventExtrasSummary"] = query_definition("
		SELECT registration_extras.id, registration_extras.label, registration_extras.price,  
			COALESCE(SUM(registration_extra_orders.quantity),0) orderCount
		FROM registration_extras
		LEFT JOIN registration_extra_orders ON registration_extra_orders.registration_extras_id  = registration_extras.id
		LEFT JOIN registrations ON registrations.id = registration_extra_orders.registrationid 
			AND registrations.eventid = registration_extras.eventid
		WHERE registration_extras.eventid = ?
		AND (registrations.id IS NULL OR registrations.deleted != 1)
		GROUP BY registration_extras.id
	",
		"s",
		array($inputs['eventid'])
	);

	/** --------------------------  Payment Queries  -------------------------------**/

	$queries["payments"] = query_definition("
		SELECT
			registration_payments.id,
			registration_payments.amount,
			registration_payments.payment_type,
			registration_payments.ref_nbr,
			registration_payments.note,
			DATE_FORMAT(registration_payments.entered_date,'%m/%d/%Y') entered_date,
			DATE_FORMAT(registration_payments.entered_date,'%Y%m%d%T') sort_date,
			CASE WHEN users.id IS NULL THEN 'Online Payment'
				ELSE CONCAT(users.last_name, ', ' , users.first_name)
			END entered_by,
			registrations.confirmation,
			attendees.email,
			attendees.last_name,
			attendees.first_name,
			attendees.business,
			registration_types.name AS regType
		FROM registration_payments
		JOIN registrations ON registrations.id = registration_payments.registrationid
		LEFT JOIN registration_types ON registration_types.id = registrations.registration_typeid
		LEFT JOIN users ON users.id = registration_payments.entered_by
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid 
		WHERE registrations.id IS NOT NULL
		AND (
			users.accountid = ? OR 
			attendees.accountid = ? OR 
			attendees.accountid = 0
		)
	",
		"ss",
		array($_SESSION['accountid'], $_SESSION['accountid'])
	);

	if(($inputs['eventid'] ?? '') != '') append_query_condition($queries['payments'], " AND registrations.eventid = ? ", "s", array($inputs['eventid']));
	if(($inputs['confirmation'] ?? '') != '') append_query_condition($queries['payments'], " AND registrations.confirmation = ? ", "s", array($inputs['confirmation']));

	$queries["accountAttendeePayments"] = query_definition("
		SELECT
			events.name event,
			events.acct_code,
			registration_payments.amount,
			registration_payments.payment_type,
			registration_payments.ref_nbr,
			registration_payments.note,
			DATE_FORMAT(registration_payments.entered_date,'%m/%d/%Y') entered_date,
			DATE_FORMAT(registration_payments.entered_date,'%Y%m%d%T') sort_date,
			CASE WHEN users.id IS NULL THEN 'Online Payment'
				ELSE CONCAT(users.last_name, ', ' , users.first_name)
			END entered_by,
			registrations.confirmation,
			attendees.email,
			attendees.last_name,
			attendees.first_name,
			attendees.business,
			registration_types.name AS regType
		FROM registration_payments
		JOIN registrations ON registrations.id = registration_payments.registrationid
		JOIN events ON events.id = registrations.eventid
		LEFT JOIN registration_types ON registration_types.id = registrations.registration_typeid
		LEFT JOIN users ON users.id = registration_payments.entered_by
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid 
		WHERE registrations.id IS NOT NULL
		AND (users.accountid = ? OR attendees.accountid = ?)
	",
		"ss",
		array($_SESSION['accountid'], $_SESSION['accountid'])
	);

	$queries["accountVendorPayments"] = query_definition("
		SELECT 
			sponsors.name business,
			sponsors.contact_first_name first_name,
			sponsors.contact_last_name last_name,
			sponsors.email,
			DATE_FORMAT(vendor_payments.payment_date,'%m/%d/%Y') entered_date,
			DATE_FORMAT(vendor_payments.payment_date,'%Y%m%d%T') sort_date,
			vendor_payments.amount,
			vendor_payments.method payment_type,
			COALESCE(vendor_payments.check_po_number, vendor_payments.cc_confirmation_number) ref_nbr, 
			vendor_payments.note
		FROM vendor_payments 
		JOIN vendor_orders ON vendor_orders.id = vendor_payments.vendor_orders_id
		JOIN sponsors ON sponsors.id = vendor_orders.sponsorid
		WHERE sponsors.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	$queries["accountVideoPayments"] = query_definition("
		SELECT
			attendees.first_name, 
			attendees.last_name, 
			attendees.email , 
			attendees.business,
			video_orders.total amount, 
			video_orders.payment_number ref_nbr,
			DATE_FORMAT(video_orders.created_time,'%m/%d/%Y') entered_date,
			DATE_FORMAT(video_orders.created_time,'%Y%m%d%T') sort_date
		FROM video_orders 
		JOIN attendees ON attendees.id = video_orders.attendeeid 
		WHERE video_orders.accountid = ?
	",
		"s",
		array($_SESSION['accountid'])
	);

	/** --------------------------  Registration Queries  -------------------------------**/
	//list of signups for registration by confirmation
	$queries["registrationSignups"] = query_definition("
		SELECT
			sections.id sectionid,
			courses.name course,
			sessions.name session,
			sessions.id sessionid,
			CASE
				WHEN signups.video_viewed = '0000-00-00 00:00:00' THEN false
				WHEN signups.video_viewed  IS NULL THEN false
				ELSE true
			END AS video_viewed,
			signups.id signupid
		FROM signups
		JOIN registrations ON registrations.id = signups.registrationid
		JOIN sessions ON sessions.id = signups.sessionid
		JOIN sections ON sections.id = signups.sectionid
		JOIN courses ON courses.id = sections.courseid
		WHERE registrations.confirmation = ?
	",
		"s",
		array($inputs['confirmation'])
	);

	$queries["attendeeSignups"] = query_definition("
		SELECT
			sections.id AS sectionid,
			sections.web_link,
			sections.web_link_external,
			sections.web_link2,
			sections.web_pass,
			sections.exclude_att_limit,
			courses.name AS course,
			courses.id AS courseid,
			sessions.name AS session,
			sessions.starttime,
			rooms.name AS room,
			CASE WHEN signups.video_viewed IS NOT NULL THEN 1 ELSE 0 END video_viewed,
			CASE
				WHEN  CONVERT_TZ(now(),'SYSTEM',events.timezone)
					BETWEEN (sessions.starttime - INTERVAL 15 MINUTE) AND sessions.endtime
				THEN 1 ELSE 0 END
			AS inProgress,
			CASE
				WHEN  CONVERT_TZ(now(),'SYSTEM',events.timezone)
					> (sessions.endtime - INTERVAL 15 MINUTE)
				THEN 1 ELSE 0 END
			AS isOver,
			sessions.endtime,
		    group_concat(distinct tracks.name separator ', ') tracknames,
		    group_concat(distinct section_presenters.userid order by users.last_name separator ',') presenterids,
		    group_concat(distinct concat(users.first_name, ' ', users.last_name) separator ', ') presenternames,
		    group_concat(distinct concat(users.photo) separator ',') presenterphotos,
		    group_concat(distinct section_sessions.sessionid separator ', ') additionalsessions
		FROM signups
		JOIN sections ON sections.id = signups.sectionid
		JOIN courses ON courses.id = sections.courseid
		JOIN registrations ON registrations.id = signups.registrationid
		LEFT JOIN rooms ON rooms.id = sections.roomid
		LEFT JOIN sessions ON sessions.id = sections.sessionid
		LEFT JOIN events ON events.id = sessions.eventid
		LEFT JOIN section_presenters ON section_presenters.sectionid = sections.id
		LEFT JOIN users ON users.id = section_presenters.userid
		LEFT JOIN course_tracks ON courses.id = course_tracks.courseid
		LEFT JOIN tracks ON course_tracks.trackid = tracks.id
		LEFT JOIN section_sessions ON section_sessions.sectionid = sections.id
		WHERE signups.registrationid = ?
		AND registrations.eventid = events.id
		GROUP BY sections.id
		ORDER BY sessions.starttime, courses.name
	",
		"s",
		array($inputs['registrationid'])
	);

	$queries["confirmationNumberPrefix"] = query_definition("
		SELECT COALESCE(prefix, 'ZZ') prefix, DATE_FORMAT(startdate,'%y') yr
		FROM events
		WHERE id = ?
	",
		"s",
		array($inputs['eventid'])
	);

	$queries["confirmationCount"] = query_definition("
		SELECT count(*) rowCount
		FROM registrations
		WHERE confirmation = ?
	",
		"s",
		array($inputs['confirmation'])
	);

	$queries["extraRegFieldExists"] = query_definition("
		SELECT count(*) AS count
		FROM registration_data
		WHERE registrationid = ?
		AND registration_fieldid = ?
	",
		"ss",
		array($inputs['regid'], $inputs['fieldid'])
	);

	/** --------------------------  Survey Queries  -------------------------------**/
	$queries["surveyResponses"] = query_definition("
		SELECT
			events.name event,
			attendees.last_name AS attendeeLast,
			attendees.first_name AS attendeeFirst,
			attendees.email,
			registrations.confirmation,
			survey_questions.id,
			courses.name AS course,
			survey_questions.text AS question,
			survey_questions.type AS questionType,
			survey_questions.assoc AS questionAssoc,
			survey_responses.response,
			survey_responses.registrationid,
			group_concat(distinct section_presenters.userid order by users.last_name separator ',') presenterids,
			group_concat(distinct concat(users.first_name, ' ', users.last_name) separator ', ') presenternames,
			sessions.name AS session_name
		FROM survey_responses
		JOIN events ON events.id = survey_responses.eventid
		LEFT JOIN registrations ON registrations.id =survey_responses.registrationid
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		JOIN survey_questions ON survey_questions.id = survey_responses.questionid
		LEFT JOIN sections ON sections.id = survey_responses.sectionid
		LEFT JOIN sessions ON sessions.id = sections.sessionid
		LEFT JOIN courses ON courses.id = sections.courseid
		LEFT JOIN section_presenters ON sections.id = section_presenters.sectionid
		LEFT JOIN users ON section_presenters.userid = users.id
		WHERE events.accountid = ?
		AND(
			CASE
				WHEN events.startdate > curdate() THEN 'future'
				WHEN DATE_SUB(curdate(), INTERVAL 365 DAY) < events.startdate THEN 'recent'
				ELSE 'past'
			END  = ?
			OR events.id = ?
		)
		GROUP BY survey_responses.id
	",
		"sss",
		array($_SESSION['accountid'], $inputs['eventStatus'], $inputs['eventid'])
	);

	$queries["minSurveyRegId"] = query_definition("SELECT min(registrationid) regid FROM survey_responses");

	$queries["surveyResponsesPresenter"] = query_definition("
		SELECT
			events.name event,
			survey_questions.id,
			courses.name AS course,
			survey_questions.text AS question,
			survey_questions.type AS questionType,
			survey_questions.assoc AS questionAssoc,
			survey_responses.response,
			sessions.name AS session_name
		FROM survey_responses
		JOIN events ON events.id = survey_responses.eventid
		JOIN survey_questions ON survey_questions.id = survey_responses.questionid
		LEFT JOIN sections ON sections.id = survey_responses.sectionid
		LEFT JOIN sessions ON sessions.id = sections.sessionid
		LEFT JOIN courses ON courses.id = sections.courseid
		LEFT JOIN section_presenters ON sections.id = section_presenters.sectionid
		LEFT JOIN users ON section_presenters.userid = users.id
		WHERE events.id = ?
		AND users.id = ?
	",
		"ss",
		array($inputs['eventid'], $_SESSION['userid'])
	);

	/** --------------------------  Registration Queries  -------------------------------**/
	$queries["registrationsRpt"] = query_definition("
		SELECT
			registrations.id,
			COALESCE(attendees.email, users.email) email,
			COALESCE(attendees.first_name, users.first_name) first_name, 
			COALESCE(attendees.last_name, users.last_name) last_name,
			COALESCE(attendees.business, users.business) business,
			COALESCE(attendees.address1, users.address1) address1,
			COALESCE(attendees.address2, users.address2) address2,
			COALESCE(attendees.city, users.city) city,
			COALESCE(attendees.state, users.state) state,
			COALESCE(attendees.zip, users.zip) zip,
			COALESCE(attendees.phone, users.phone) phone,
			registrations.confirmation,
			registrations.deleted,
			registrations.payment_method,
			registrations.payment_number,
			attendees.web_address,
			attendees.title,
			events.name event,
			events.startdate,
			DATE_FORMAT(events.startdate, '%Y') eventYear,
			events.id eventid,
			registration_types.name registrationType
		FROM registrations
		JOIN events ON events.id = registrations.eventid
		LEFT JOIN attendees ON attendees.id = registrations.attendeeid
		LEFT JOIN users ON users.id = registrations.userid
		LEFT JOIN registration_types ON registration_types.id = registrations.registration_typeid
		WHERE events.accountid = ?
		AND COALESCE(users.id, attendees.id) IS NOT NULL
	",
		"s",
		array($_SESSION['accountid'])
	);

	/** --------------------------  Live poll queries  -------------------------------**/
	function tep_poll_pdo() { // PDO for new poll SQL only; existing queries stay on mysqli
		$host = defined('DB_HOST') ? DB_HOST : 'localhost'; // Same host as database_connect()
		$port = defined('DB_PORT') ? (int)DB_PORT : 3306; // XAMPP default
		$httpHost = str_replace('www.', '', (string)($_SERVER['HTTP_HOST'] ?? '')); // Match database_connect prod vs dev
		if (substr($httpHost, 0, 4) == 'easy') { // Production hostname
			$dbname = defined('DB_NAME_PROD') ? DB_NAME_PROD : 'tep_local'; // Schema name from tep_config
			$user = defined('DB_USER_PROD') ? DB_USER_PROD : 'root'; // Prod user
			$pass = defined('DB_PASS') ? DB_PASS : ''; // Prod password
		} else {
			$dbname = defined('DB_NAME_DEV') ? DB_NAME_DEV : 'tep_local'; // Local tep_local schema
			$user = defined('DB_USER_LOCAL') ? DB_USER_LOCAL : 'root'; // XAMPP user
			$pass = defined('DB_PASS_LOCAL') ? DB_PASS_LOCAL : ''; // XAMPP empty root password
		}
		$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=utf8mb4'; // Constants only — never request data
		try { // PHP 8.2 PDO throws PDOException when MySQL is down or the schema is missing
			return new PDO($dsn, $user, $pass, array( // Exceptions so callers can still emit HTTP 200 rows
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Catch and convert to {"rows":[]}
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // dataSvc-friendly associative rows
				PDO::ATTR_EMULATE_PREPARES => false, // Real server-side prepares
			));
		} catch (Throwable $pdoConnectEx) { // Connection refused / unknown database
			error_log('TEP poll PDO connect failed: ' . $pdoConnectEx->getMessage()); // Log only
			throw $pdoConnectEx; // Re-throw so the outer query try/catch can emit empty rows
		}
	}

	function tep_poll_ensure_schema($pdo) { // CREATE IF NOT EXISTS so tep_local can serve polls without a manual import
		$pdo->exec('CREATE TABLE IF NOT EXISTS tep_polls (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			accountid INT UNSIGNED NOT NULL DEFAULT 0,
			eventid INT UNSIGNED NULL DEFAULT NULL,
			question VARCHAR(500) NOT NULL,
			options_json TEXT NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_tep_polls_active (accountid, is_active, eventid)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); // Lightweight poll header
		$pdo->exec('CREATE TABLE IF NOT EXISTS tep_poll_votes (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			pollid INT UNSIGNED NOT NULL,
			option_index TINYINT UNSIGNED NOT NULL,
			voter_key VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uk_tep_poll_votes_voter (pollid, voter_key),
			KEY idx_tep_poll_votes_poll (pollid)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); // One vote per voter_key per poll
		if (function_exists('tep_is_local_host') && tep_is_local_host()) { // Demo row only on XAMPP
			$n = (int)$pdo->query('SELECT COUNT(*) FROM tep_polls')->fetchColumn(); // No user input
			if ($n === 0) { // First local request only
				$pdo->exec("INSERT INTO tep_polls (accountid, eventid, question, options_json, is_active) VALUES (1000, NULL, 'Is this session useful?', '[\"Yes\",\"No\"]', 1)"); // Matches TEP_LOCAL_DEV_ACCOUNT_ID
			}
		}
	}

	function tep_push_ensure_schema($pdo) { // CREATE IF NOT EXISTS so tep_local can store PushSubscription keys
		$pdo->exec('CREATE TABLE IF NOT EXISTS tep_push_subscriptions (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			accountid INT UNSIGNED NOT NULL DEFAULT 0,
			userid INT UNSIGNED NULL DEFAULT NULL,
			endpoint VARCHAR(512) NOT NULL,
			p256dh VARCHAR(255) NOT NULL,
			auth VARCHAR(255) NOT NULL,
			user_agent VARCHAR(255) NOT NULL DEFAULT "",
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uk_tep_push_endpoint (endpoint),
			KEY idx_tep_push_account (accountid)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); // One row per browser endpoint
	}

	function tep_checkin_ensure_local_demo($pdo) { // Local-only attendees stub + two demo tickets so XAMPP can search without production data
		if (!function_exists('tep_is_local_host') || !tep_is_local_host()) { // Never DDL/seed on production hosts
			return; // Production already has attendees/registrations
		}
		try { // M3: extra production NOT NULL columns must not abort check-in / Event Pulse
		$pdo->exec('CREATE TABLE IF NOT EXISTS attendees (
			id INT NOT NULL AUTO_INCREMENT,
			accountid INT NOT NULL DEFAULT 0,
			first_name VARCHAR(255) NOT NULL DEFAULT "",
			last_name VARCHAR(255) NOT NULL DEFAULT "",
			email VARCHAR(255) NOT NULL DEFAULT "",
			PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); // Minimal JOIN target for name search on tep_local
		$evtStmt = $pdo->query("SELECT id FROM events WHERE slug = 'local-checkin-demo' AND accountid = 1000 LIMIT 1"); // Bound-free constants only
		$evtId = $evtStmt ? (int)$evtStmt->fetchColumn() : 0; // Demo event id
		if ($evtId < 1) { // First local request
			$pdo->exec("INSERT INTO events (accountid, name, slug, city, state, logo, visible, archived, hide_after_start) VALUES (1000, 'Local Check-In Demo', 'local-checkin-demo', 'Local', 'NY', '', '0', '0', '0')"); // visible=0 keeps it off Upcoming Events
			$evtId = (int)$pdo->lastInsertId(); // New event id
		}
		$attCount = (int)$pdo->query('SELECT COUNT(*) FROM attendees')->fetchColumn(); // No user input
		if ($attCount === 0) { // Seed two searchable people
			$pdo->exec("INSERT INTO attendees (accountid, first_name, last_name, email) VALUES (1000, 'Jane', 'Demo', 'jane@localhost')"); // Search: Jane
			$pdo->exec("INSERT INTO attendees (accountid, first_name, last_name, email) VALUES (1000, 'John', 'Ticket', 'john@localhost')"); // Search: John or Ticket
		}
		$regCount = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn(); // No user input
		if ($regCount === 0 && $evtId > 0) { // Pair tickets to the hidden demo event
			$janeId = (int)$pdo->query("SELECT id FROM attendees WHERE email = 'jane@localhost' LIMIT 1")->fetchColumn(); // Demo attendee
			$johnId = (int)$pdo->query("SELECT id FROM attendees WHERE email = 'john@localhost' LIMIT 1")->fetchColumn(); // Demo attendee
			$colStmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tname'); // Reflect live registrations columns
			$colStmt->bindValue(':tname', 'registrations', PDO::PARAM_STR); // Table name as data, not SQL
			$colStmt->execute(); // Column list
			$regCols = $colStmt->fetchAll(PDO::FETCH_COLUMN); // Existing names
			$seedPairs = array(); // Only insert columns that exist (skip extra production NOT NULL we cannot fill)
			foreach (array('eventid', 'deleted', 'attendeeid', 'confirmation', 'checkin', 'checkin_userid', 'registration_typeid', 'userid') as $seedCol) { // Whitelist; never request data
				if (in_array($seedCol, $regCols, true)) { // Column present on this schema
					$seedPairs[] = $seedCol; // Include in INSERT
				}
			}
			if (!empty($seedPairs) && in_array('eventid', $seedPairs, true) && in_array('attendeeid', $seedPairs, true) && in_array('confirmation', $seedPairs, true)) { // Need ticket JOIN keys
				$colSql = array(); // Backticked names
				$phSql = array(); // Named placeholders
				foreach ($seedPairs as $seedCol) { // Safe identifiers from whitelist ∩ reflection
					$colSql[] = '`' . $seedCol . '`'; // Quoted column
					$phSql[] = ':' . $seedCol; // Bound value
				}
				$regIns = $pdo->prepare('INSERT INTO registrations (' . implode(',', $colSql) . ') VALUES (' . implode(',', $phSql) . ')'); // Bound seed; columns from information_schema only
				foreach (array($janeId => 'TEPJANE1', $johnId => 'TEPJOHN2') as $attId => $confCode) { // Two demo tickets
					if ((int)$attId < 1) { // Missing attendee
						continue; // Skip
					}
					try { // Extra NOT NULL columns without defaults must not abort check-in / Pulse
						foreach ($seedPairs as $seedCol) { // Bind each present column
							if ($seedCol === 'eventid') { // Hidden demo event
								$regIns->bindValue(':eventid', $evtId, PDO::PARAM_INT); // Event
							} elseif ($seedCol === 'attendeeid') { // Name JOIN
								$regIns->bindValue(':attendeeid', (int)$attId, PDO::PARAM_INT); // Attendee
							} elseif ($seedCol === 'confirmation') { // Ticket code
								$regIns->bindValue(':confirmation', $confCode, PDO::PARAM_STR); // TEPJANE1 / TEPJOHN2
							} elseif ($seedCol === 'checkin') { // Not checked in yet
								$regIns->bindValue(':checkin', null, PDO::PARAM_NULL); // NULL timestamp
							} elseif ($seedCol === 'deleted' || $seedCol === 'checkin_userid' || $seedCol === 'registration_typeid' || $seedCol === 'userid') { // Integer defaults
								$regIns->bindValue(':' . $seedCol, 0, PDO::PARAM_INT); // 0
							}
						}
						$regIns->execute(); // Insert one demo registration
					} catch (Throwable $seedEx) { // Production NOT NULL leftover
						error_log('TEP local check-in seed skipped: ' . $seedEx->getMessage()); // Log only; continue the query
					}
				}
			}
		}
		} catch (Throwable $demoEx) { // Events/attendees schema mismatch or information_schema failure
			error_log('TEP local check-in demo skipped: ' . $demoEx->getMessage()); // Log only; Pulse/check-in still run
		}
	}

	function tep_vendor_ensure_schema($pdo) { // CREATE IF NOT EXISTS booth assignments + captured leads
		$pdo->exec('CREATE TABLE IF NOT EXISTS tep_vendor_booths (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			accountid INT UNSIGNED NOT NULL DEFAULT 0,
			sponsorid INT UNSIGNED NOT NULL DEFAULT 0,
			eventid INT UNSIGNED NULL DEFAULT NULL,
			vendor_name VARCHAR(255) NOT NULL DEFAULT "",
			booth VARCHAR(64) NOT NULL DEFAULT "",
			hall VARCHAR(128) NOT NULL DEFAULT "",
			notes VARCHAR(255) NOT NULL DEFAULT "",
			PRIMARY KEY (id),
			KEY idx_tep_vendor_booth_acct (accountid, sponsorid)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); // One booth row per vendor/event
		$pdo->exec('CREATE TABLE IF NOT EXISTS tep_vendor_leads (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			accountid INT UNSIGNED NOT NULL DEFAULT 0,
			sponsorid INT UNSIGNED NOT NULL DEFAULT 0,
			eventid INT UNSIGNED NULL DEFAULT NULL,
			attendee_name VARCHAR(255) NOT NULL DEFAULT "",
			email VARCHAR(255) NOT NULL DEFAULT "",
			company VARCHAR(255) NOT NULL DEFAULT "",
			ticket VARCHAR(64) NOT NULL DEFAULT "",
			notes VARCHAR(500) NOT NULL DEFAULT "",
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_tep_vendor_leads_acct (accountid, sponsorid)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); // Floor-captured attendee leads
		if (function_exists('tep_is_local_host') && tep_is_local_host()) { // Demo booth only on XAMPP
			$n = (int)$pdo->query('SELECT COUNT(*) FROM tep_vendor_booths')->fetchColumn(); // No user input
			if ($n === 0) { // First local request
				$evtId = 0; // Optional event link
				$evtStmt = $pdo->query("SELECT id FROM events WHERE slug = 'local-checkin-demo' AND accountid = 1000 LIMIT 1"); // Reuse hidden demo event
				if ($evtStmt) { // Query ran
					$evtId = (int)$evtStmt->fetchColumn(); // May be 0
				}
				$boothIns = $pdo->prepare('INSERT INTO tep_vendor_booths (accountid, sponsorid, eventid, vendor_name, booth, hall, notes) VALUES (1000, 1, :eventid, :vendor_name, :booth, :hall, :notes)'); // Bound seed
				if ($evtId > 0) { // Link to demo event
					$boothIns->bindValue(':eventid', $evtId, PDO::PARAM_INT); // Hidden local event
				} else {
					$boothIns->bindValue(':eventid', null, PDO::PARAM_NULL); // Booth still valid without an event
				}
				$boothIns->bindValue(':vendor_name', 'Phoenix Exhibits', PDO::PARAM_STR); // Demo vendor
				$boothIns->bindValue(':booth', 'A-12', PDO::PARAM_STR); // Demo booth number
				$boothIns->bindValue(':hall', 'Hall 2', PDO::PARAM_STR); // Demo hall
				$boothIns->bindValue(':notes', 'Corner booth, power on the left.', PDO::PARAM_STR); // Assignment note
				$boothIns->execute(); // Insert
			}
		}
	}

	$tepPollQueryName = (string)($inputs['query'] ?? ''); // Only open PDO for poll, push-save, check-in, vendor-ops, and Event Pulse endpoints
	if ($tepPollQueryName === 'getActivePoll' || $tepPollQueryName === 'submitPollVote' || $tepPollQueryName === 'savePushSubscription' || $tepPollQueryName === 'getAttendeeCheckInStatus' || $tepPollQueryName === 'checkInAttendee' || $tepPollQueryName === 'getVendorStatus' || $tepPollQueryName === 'saveVendorLead' || $tepPollQueryName === 'getEventAnalytics') { // Skip extra connect on every other query
		try {
			$pollPdo = tep_poll_pdo(); // Separate PDO handle; mysqli path unused when rows are prebuilt
			if ($tepPollQueryName === 'savePushSubscription') { // Push save does not need poll tables
				tep_push_ensure_schema($pollPdo); // Idempotent DDL for tep_push_subscriptions
			} elseif ($tepPollQueryName === 'getAttendeeCheckInStatus' || $tepPollQueryName === 'checkInAttendee') { // Staff check-in uses registrations
				tep_checkin_ensure_local_demo($pollPdo); // Local attendees stub + demo tickets only
			} elseif ($tepPollQueryName === 'getVendorStatus' || $tepPollQueryName === 'saveVendorLead') { // Vendor booth + leads
				tep_vendor_ensure_schema($pollPdo); // Idempotent DDL for tep_vendor_booths / tep_vendor_leads
			} elseif ($tepPollQueryName === 'getEventAnalytics') { // Pulse needs registrations, leads, and poll votes
				tep_checkin_ensure_local_demo($pollPdo); // Local attendees stub so check-in % is not empty on XAMPP
				tep_vendor_ensure_schema($pollPdo); // tep_vendor_leads for the lead COUNT
				tep_poll_ensure_schema($pollPdo); // tep_polls / tep_poll_votes for live breakdowns
			} else {
				tep_poll_ensure_schema($pollPdo); // Idempotent DDL for Instant Polling
			}
			$pollAccountId = (int)($_SESSION['accountid'] ?? ($inputs['accountid'] ?? 0)); // Session first; optional request override
			$pollEventId = (int)($inputs['eventid'] ?? 0); // 0 means any event for this account
			if ($tepPollQueryName === 'getActivePoll') { // Fetch active polls for AngularJS dataSvc.getArray
				if ($pollAccountId < 1) { // Do not leak other accounts when session is empty
					$queries['getActivePoll'] = query_definition('', '', array(), array()); // HTTP 200 empty rows
				} else {
					$pollSql = 'SELECT p.id, p.accountid, p.eventid, p.question, p.options_json, p.is_active,
						(SELECT COUNT(*) FROM tep_poll_votes v WHERE v.pollid = p.id) AS total_votes
						FROM tep_polls p
						WHERE p.is_active = 1
						AND p.accountid = :accountid
						AND (:eventid = 0 OR p.eventid = :eventid_match)
						ORDER BY p.id DESC'; // Bound filters only
					$pollStmt = $pollPdo->prepare($pollSql); // PDO prepared statement
					$pollStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Account scope
					$pollStmt->bindValue(':eventid', $pollEventId, PDO::PARAM_INT); // 0 = no event filter
					$pollStmt->bindValue(':eventid_match', $pollEventId, PDO::PARAM_INT); // Native prepares cannot reuse one name
					$pollStmt->execute(); // Run the select
					$pollRows = $pollStmt->fetchAll(); // Active polls for this account
					$voteCountStmt = $pollPdo->prepare('SELECT option_index, COUNT(*) AS vote_count FROM tep_poll_votes WHERE pollid = :pollid GROUP BY option_index'); // Per-option tallies for the widget
					foreach ($pollRows as $pollIdx => $pollRow) { // Attach counts without a second round-trip from AngularJS
						$voteCountStmt->bindValue(':pollid', (int)$pollRow['id'], PDO::PARAM_INT); // Bound poll id
						$voteCountStmt->execute(); // Grouped counts
						$countMap = array(); // option_index => votes
						foreach ($voteCountStmt->fetchAll() as $countRow) { // Build a JSON-friendly map
							$countMap[(string)$countRow['option_index']] = (int)$countRow['vote_count']; // String keys survive json_encode
						}
						$pollRows[$pollIdx]['vote_counts_json'] = json_encode($countMap); // Dashboard widget parses this on $scope
					}
					$queries['getActivePoll'] = query_definition('', '', array(), $pollRows); // Prebuilt rows → HTTP 200
				}
			}
			if ($tepPollQueryName === 'submitPollVote') { // Insert a vote; always return rows JSON
				$pollId = (int)($inputs['pollid'] ?? 0); // Required poll id
				$optionIndex = (int)($inputs['option_index'] ?? -1); // 0-based index into options_json
				$voterKey = (string)($inputs['voter'] ?? ($_SESSION['userid'] ?? ($_SESSION['attendeeid'] ?? ''))); // Explicit voter, else session
				if ($voterKey === '' && session_id()) { // Last resort: PHP session id
					$voterKey = session_id(); // Still bound; never concatenated into SQL
				}
				$voterKey = substr($voterKey, 0, 64); // Match tep_poll_votes.voter_key
				if ($pollId < 1 || $optionIndex < 0 || $optionIndex > 20 || $voterKey === '') { // Fail closed without throwing
					$queries['submitPollVote'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'invalid'))); // HTTP 200
				} else {
					$check = $pollPdo->prepare('SELECT id FROM tep_polls WHERE id = :id AND is_active = 1 LIMIT 1'); // Only active polls accept votes
					$check->bindValue(':id', $pollId, PDO::PARAM_INT); // Bound poll id
					$check->execute(); // Lookup
					if (!$check->fetch()) { // Closed or missing poll
						$queries['submitPollVote'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'inactive'))); // HTTP 200
					} else {
						try {
							$ins = $pollPdo->prepare('INSERT INTO tep_poll_votes (pollid, option_index, voter_key) VALUES (:pollid, :option_index, :voter_key)'); // Unique (pollid, voter_key)
							$ins->bindValue(':pollid', $pollId, PDO::PARAM_INT); // Poll
							$ins->bindValue(':option_index', $optionIndex, PDO::PARAM_INT); // Choice
							$ins->bindValue(':voter_key', $voterKey, PDO::PARAM_STR); // Voter
							$ins->execute(); // Insert
							$queries['submitPollVote'] = query_definition('', '', array(), array(array('ok' => '1', 'pollid' => (string)$pollId))); // HTTP 200 success
						} catch (PDOException $dup) { // Duplicate unique key or other write error
							$reason = ((string)$dup->getCode() === '23000') ? 'already_voted' : 'unavailable'; // 23000 = unique violation
							$queries['submitPollVote'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => $reason))); // HTTP 200
						}
					}
				}
			}
			if ($tepPollQueryName === 'savePushSubscription') { // Persist Web Push endpoint + keys for this session
				$endpoint = trim((string)($inputs['endpoint'] ?? '')); // PushSubscription.endpoint
				$p256dh = trim((string)($inputs['p256dh'] ?? '')); // PushSubscription.keys.p256dh
				$auth = trim((string)($inputs['auth'] ?? '')); // PushSubscription.keys.auth
				if (($p256dh === '' || $auth === '') && isset($inputs['keys'])) { // Optional JSON keys blob from the browser
					$keysDecoded = json_decode((string)$inputs['keys'], true); // Fail-soft if not JSON
					if (is_array($keysDecoded)) { // Standard PushSubscription.toJSON().keys
						if ($p256dh === '') { // Fill missing p256dh
							$p256dh = trim((string)($keysDecoded['p256dh'] ?? '')); // Bound later
						}
						if ($auth === '') { // Fill missing auth
							$auth = trim((string)($keysDecoded['auth'] ?? '')); // Bound later
						}
					}
				}
				$endpoint = substr($endpoint, 0, 512); // Match tep_push_subscriptions.endpoint
				$p256dh = substr($p256dh, 0, 255); // Match tep_push_subscriptions.p256dh
				$auth = substr($auth, 0, 255); // Match tep_push_subscriptions.auth
				$pushUserId = (int)($_SESSION['userid'] ?? 0); // Optional user from session
				$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255); // Truncate UA; not SQL
				$endpointIsHttps = (strpos($endpoint, 'https://') === 0); // Web Push endpoints are always HTTPS
				if ($pollAccountId < 1 || !$endpointIsHttps || $p256dh === '' || $auth === '') { // Fail closed without throwing
					$queries['savePushSubscription'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'invalid'))); // HTTP 200
				} else {
					$pushSql = 'INSERT INTO tep_push_subscriptions (accountid, userid, endpoint, p256dh, auth, user_agent)
						VALUES (:accountid, :userid, :endpoint, :p256dh, :auth, :user_agent)
						ON DUPLICATE KEY UPDATE
							p256dh = VALUES(p256dh),
							auth = VALUES(auth),
							accountid = VALUES(accountid),
							userid = VALUES(userid),
							user_agent = VALUES(user_agent),
							updated_at = CURRENT_TIMESTAMP'; // Unique endpoint upsert
					$pushStmt = $pollPdo->prepare($pushSql); // PDO prepared statement
					$pushStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Session account
					if ($pushUserId > 0) { // Bound integer when logged in
						$pushStmt->bindValue(':userid', $pushUserId, PDO::PARAM_INT); // users.id
					} else {
						$pushStmt->bindValue(':userid', null, PDO::PARAM_NULL); // Anonymous / missing session user
					}
					$pushStmt->bindValue(':endpoint', $endpoint, PDO::PARAM_STR); // HTTPS push endpoint
					$pushStmt->bindValue(':p256dh', $p256dh, PDO::PARAM_STR); // Client public key
					$pushStmt->bindValue(':auth', $auth, PDO::PARAM_STR); // Auth secret
					$pushStmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR); // Debug / device hint
					$pushStmt->execute(); // Insert or refresh keys
					$queries['savePushSubscription'] = query_definition('', '', array(), array(array('ok' => '1'))); // HTTP 200 success
				}
			}
			if ($tepPollQueryName === 'getAttendeeCheckInStatus') { // Fast name/ticket search for the dashboard check-in card
				$searchRaw = trim((string)($inputs['q'] ?? ($inputs['search'] ?? ''))); // Dashboard search box
				$searchRaw = substr($searchRaw, 0, 64); // Cap length
				$searchSafe = str_replace(array('%', '_'), '', $searchRaw); // Strip LIKE wildcards; bind the rest
				if ($pollAccountId < 1 || strlen($searchSafe) < 2) { // Fail closed: no account leak, no full roster dump
					$queries['getAttendeeCheckInStatus'] = query_definition('', '', array(), array()); // HTTP 200 empty
				} else {
					$like = '%' . $searchSafe . '%'; // Bound pattern
					$statusSql = 'SELECT r.id, r.confirmation, r.checkin, r.checkin_userid, r.eventid,
						COALESCE(attendees.first_name, \'\') AS first_name,
						COALESCE(attendees.last_name, \'\') AS last_name,
						COALESCE(e.name, \'\') AS event_name,
						CASE WHEN r.checkin IS NULL OR r.checkin = \'0000-00-00 00:00:00\' THEN \'0\' ELSE \'1\' END AS checked_in
						FROM registrations r
						JOIN events e ON e.id = r.eventid
						LEFT JOIN attendees ON attendees.id = r.attendeeid
						WHERE e.accountid = :accountid
						AND COALESCE(r.deleted, 0) = 0
						AND (
							r.confirmation LIKE :like_conf
							OR attendees.first_name LIKE :like_fn
							OR attendees.last_name LIKE :like_ln
							OR CONCAT(COALESCE(attendees.first_name, \'\'), \' \', COALESCE(attendees.last_name, \'\')) LIKE :like_full
						)
						ORDER BY attendees.last_name, attendees.first_name, r.id
						LIMIT 25'; // Bound filters only; cap rows for touch UI
					$statusStmt = $pollPdo->prepare($statusSql); // PDO prepared statement
					$statusStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Session account
					$statusStmt->bindValue(':like_conf', $like, PDO::PARAM_STR); // Ticket / confirmation
					$statusStmt->bindValue(':like_fn', $like, PDO::PARAM_STR); // First name
					$statusStmt->bindValue(':like_ln', $like, PDO::PARAM_STR); // Last name
					$statusStmt->bindValue(':like_full', $like, PDO::PARAM_STR); // Full name
					$statusStmt->execute(); // Run the select
					$queries['getAttendeeCheckInStatus'] = query_definition('', '', array(), $statusStmt->fetchAll()); // Prebuilt rows → HTTP 200
				}
			}
			if ($tepPollQueryName === 'checkInAttendee') { // Toggle checkin timestamp on this account's registration
				$regId = (int)($inputs['id'] ?? ($inputs['registrationid'] ?? 0)); // registrations.id
				$staffUserId = (int)($_SESSION['userid'] ?? 0); // Existing station uses session staff id
				if ($pollAccountId < 1 || $regId < 1) { // Fail closed
					$queries['checkInAttendee'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'invalid'))); // HTTP 200
				} else {
					$lookup = $pollPdo->prepare('SELECT r.id, r.confirmation, r.checkin, r.checkin_userid,
						COALESCE(attendees.first_name, \'\') AS first_name,
						COALESCE(attendees.last_name, \'\') AS last_name,
						CASE WHEN r.checkin IS NULL OR r.checkin = \'0000-00-00 00:00:00\' THEN \'0\' ELSE \'1\' END AS checked_in
						FROM registrations r
						JOIN events e ON e.id = r.eventid
						LEFT JOIN attendees ON attendees.id = r.attendeeid
						WHERE r.id = :id AND e.accountid = :accountid AND COALESCE(r.deleted, 0) = 0
						LIMIT 1'); // Account-scoped; never update another tenant
					$lookup->bindValue(':id', $regId, PDO::PARAM_INT); // Registration
					$lookup->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Session account
					$lookup->execute(); // Lookup
					$current = $lookup->fetch(); // Row or false
					if (!$current) { // Missing or wrong account
						$queries['checkInAttendee'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'not_found'))); // HTTP 200
					} else {
						$goingIn = ($current['checked_in'] !== '1'); // Toggle: in if currently out
						if ($goingIn) { // Check in — same NOW() as runQuery.php checkinAttendee
							$upd = $pollPdo->prepare('UPDATE registrations r
								JOIN events e ON e.id = r.eventid
								SET r.checkin = NOW(), r.checkin_userid = :userid
								WHERE r.id = :id AND e.accountid = :accountid AND COALESCE(r.deleted, 0) = 0'); // Bound write
							$upd->bindValue(':userid', $staffUserId, PDO::PARAM_INT); // Staff user; 0 is allowed on tep_local NOT NULL
						} else { // Check out
							$upd = $pollPdo->prepare('UPDATE registrations r
								JOIN events e ON e.id = r.eventid
								SET r.checkin = NULL, r.checkin_userid = 0
								WHERE r.id = :id AND e.accountid = :accountid AND COALESCE(r.deleted, 0) = 0'); // Bound write; userid 0 because tep_local is NOT NULL
						}
						$upd->bindValue(':id', $regId, PDO::PARAM_INT); // Registration
						$upd->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Tenant
						$upd->execute(); // Toggle
						$queries['checkInAttendee'] = query_definition('', '', array(), array(array( // HTTP 200 JSON for the card
							'ok' => '1',
							'checked_in' => $goingIn ? '1' : '0',
							'id' => (string)$regId,
							'confirmation' => (string)$current['confirmation'],
							'first_name' => (string)$current['first_name'],
							'last_name' => (string)$current['last_name']
						)));
					}
				}
			}
			$tepVendorSponsorId = (int)($_SESSION['sponsorid'] ?? 0); // Vendor login; 0 on staff dashboard
			if ($tepVendorSponsorId < 1 && function_exists('tep_is_local_host') && tep_is_local_host() && $pollAccountId === 1000) { // Local staff can still try the card
				$tepVendorSponsorId = 1; // Matches tep_vendor_ensure_schema demo booth
			}
			if ($tepPollQueryName === 'getVendorStatus') { // Booth assignment + recent leads for this vendor
				if ($pollAccountId < 1 || $tepVendorSponsorId < 1) { // No tenant or vendor
					$queries['getVendorStatus'] = query_definition('', '', array(), array()); // HTTP 200 empty
				} else {
					$boothSql = 'SELECT b.id, b.accountid, b.sponsorid, b.eventid, b.vendor_name, b.booth, b.hall, b.notes,
						COALESCE(e.name, \'\') AS event_name,
						(SELECT COUNT(*) FROM tep_vendor_leads l WHERE l.accountid = b.accountid AND l.sponsorid = b.sponsorid) AS lead_count
						FROM tep_vendor_booths b
						LEFT JOIN events e ON e.id = b.eventid
						WHERE b.accountid = :accountid
						AND b.sponsorid = :sponsorid
						ORDER BY b.id ASC'; // Bound tenant + vendor
					$boothStmt = $pollPdo->prepare($boothSql); // PDO prepared statement
					$boothStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Session account
					$boothStmt->bindValue(':sponsorid', $tepVendorSponsorId, PDO::PARAM_INT); // Session or local demo vendor
					$boothStmt->execute(); // Run the select
					$boothRows = $boothStmt->fetchAll(); // Assignment rows
					$leadStmt = $pollPdo->prepare('SELECT id, attendee_name, email, company, ticket, notes, created_at FROM tep_vendor_leads WHERE accountid = :accountid AND sponsorid = :sponsorid ORDER BY id DESC LIMIT 8'); // Latest leads for the card
					$leadStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Tenant
					$leadStmt->bindValue(':sponsorid', $tepVendorSponsorId, PDO::PARAM_INT); // Vendor
					$leadStmt->execute(); // Run the select
					$recentLeads = $leadStmt->fetchAll(); // Newest first
					foreach ($boothRows as $boothIdx => $boothRow) { // Attach JSON so one getArray payload is enough
						$boothRows[$boothIdx]['recent_leads_json'] = json_encode($recentLeads); // Dashboard parses on $scope
					}
					$queries['getVendorStatus'] = query_definition('', '', array(), $boothRows); // Prebuilt rows → HTTP 200
				}
			}
			if ($tepPollQueryName === 'saveVendorLead') { // Persist a floor-captured attendee lead
				$leadName = substr(trim((string)($inputs['attendee_name'] ?? ($inputs['name'] ?? ''))), 0, 255); // Required
				$leadEmail = substr(trim((string)($inputs['email'] ?? '')), 0, 255); // Optional
				$leadCompany = substr(trim((string)($inputs['company'] ?? '')), 0, 255); // Optional
				$leadTicket = substr(str_replace(array('%', '_'), '', trim((string)($inputs['ticket'] ?? ''))), 0, 64); // Optional confirmation
				$leadNotes = substr(trim((string)($inputs['notes'] ?? '')), 0, 500); // Optional
				$leadEventId = (int)($inputs['eventid'] ?? $pollEventId); // Optional event
				$emailOk = ($leadEmail === '' || filter_var($leadEmail, FILTER_VALIDATE_EMAIL)); // Empty or RFC-ish
				if (!$emailOk && $leadEmail !== '' && function_exists('tep_is_local_host') && tep_is_local_host()) { // XAMPP test addresses like name@localhost
					$emailOk = (strpos($leadEmail, '@') > 0 && strpos($leadEmail, ' ') === false); // Minimal local check
				}
				if ($pollAccountId < 1 || $tepVendorSponsorId < 1 || strlen($leadName) < 2 || !$emailOk) { // Fail closed
					$queries['saveVendorLead'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'invalid'))); // HTTP 200
				} else {
					$leadIns = $pollPdo->prepare('INSERT INTO tep_vendor_leads (accountid, sponsorid, eventid, attendee_name, email, company, ticket, notes) VALUES (:accountid, :sponsorid, :eventid, :attendee_name, :email, :company, :ticket, :notes)'); // Bound insert
					$leadIns->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Tenant
					$leadIns->bindValue(':sponsorid', $tepVendorSponsorId, PDO::PARAM_INT); // Vendor
					if ($leadEventId > 0) { // Optional event
						$leadIns->bindValue(':eventid', $leadEventId, PDO::PARAM_INT); // Event
					} else {
						$leadIns->bindValue(':eventid', null, PDO::PARAM_NULL); // Booth-only lead
					}
					$leadIns->bindValue(':attendee_name', $leadName, PDO::PARAM_STR); // Name
					$leadIns->bindValue(':email', $leadEmail, PDO::PARAM_STR); // Email
					$leadIns->bindValue(':company', $leadCompany, PDO::PARAM_STR); // Company
					$leadIns->bindValue(':ticket', $leadTicket, PDO::PARAM_STR); // Ticket
					$leadIns->bindValue(':notes', $leadNotes, PDO::PARAM_STR); // Notes
					$leadIns->execute(); // Insert
					$queries['saveVendorLead'] = query_definition('', '', array(), array(array( // HTTP 200 JSON for the card
						'ok' => '1',
						'id' => (string)$pollPdo->lastInsertId(),
						'attendee_name' => $leadName
					)));
				}
			}
			if ($tepPollQueryName === 'getEventAnalytics') { // Account-scoped check-in %, vendor lead COUNT, live poll breakdowns
				$emptyPulse = array(array( // HTTP 200 zero row when session/account is missing
					'ok' => '0',
					'checkin_total' => '0',
					'checkin_in' => '0',
					'checkin_percent' => '0',
					'lead_count' => '0',
					'polls_json' => '[]'
				)); // Event Pulse binds these keys even when empty
				if ($pollAccountId < 1) { // Do not leak other accounts
					$queries['getEventAnalytics'] = query_definition('', '', array(), $emptyPulse); // HTTP 200 zeros
				} else {
					$checkSql = 'SELECT COUNT(*) AS checkin_total,
						COALESCE(SUM(CASE WHEN r.checkin IS NULL OR r.checkin = \'0000-00-00 00:00:00\' THEN 0 ELSE 1 END), 0) AS checkin_in
						FROM registrations r
						JOIN events e ON e.id = r.eventid
						WHERE e.accountid = :accountid
						AND COALESCE(r.deleted, 0) = 0
						AND (:eventid = 0 OR r.eventid = :eventid_match)'; // Bound tenant + optional event
					$checkStmt = $pollPdo->prepare($checkSql); // PDO prepared aggregate
					$checkStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Session account
					$checkStmt->bindValue(':eventid', $pollEventId, PDO::PARAM_INT); // 0 = all events
					$checkStmt->bindValue(':eventid_match', $pollEventId, PDO::PARAM_INT); // Native prepares cannot reuse one name
					$checkStmt->execute(); // Run the COUNT
					$checkRow = $checkStmt->fetch() ?: array(); // Assoc or empty
					$checkinTotal = (int)($checkRow['checkin_total'] ?? 0); // Registrations in scope
					$checkinIn = (int)($checkRow['checkin_in'] ?? 0); // Checked-in subset
					$checkinPercent = ($checkinTotal > 0) ? (int)round(($checkinIn / $checkinTotal) * 100) : 0; // 0–100; 0 when no regs
					$leadSql = 'SELECT COUNT(*) FROM tep_vendor_leads
						WHERE accountid = :accountid
						AND (:eventid = 0 OR eventid = :eventid_match)'; // Bound tenant + optional event
					$leadStmt = $pollPdo->prepare($leadSql); // PDO prepared COUNT
					$leadStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Session account
					$leadStmt->bindValue(':eventid', $pollEventId, PDO::PARAM_INT); // 0 = all leads
					$leadStmt->bindValue(':eventid_match', $pollEventId, PDO::PARAM_INT); // Native prepares cannot reuse one name
					$leadStmt->execute(); // Run the COUNT
					$leadCount = (int)$leadStmt->fetchColumn(); // Total vendor leads logged
					$pulsePollSql = 'SELECT p.id, p.question, p.options_json,
						(SELECT COUNT(*) FROM tep_poll_votes v WHERE v.pollid = p.id) AS total_votes
						FROM tep_polls p
						WHERE p.is_active = 1
						AND p.accountid = :accountid
						AND (:eventid = 0 OR p.eventid = :eventid_match OR p.eventid IS NULL)
						ORDER BY p.id DESC'; // Live polls for this account (account-wide polls have NULL eventid)
					$pulsePollStmt = $pollPdo->prepare($pulsePollSql); // PDO prepared select
					$pulsePollStmt->bindValue(':accountid', $pollAccountId, PDO::PARAM_INT); // Session account
					$pulsePollStmt->bindValue(':eventid', $pollEventId, PDO::PARAM_INT); // 0 = all
					$pulsePollStmt->bindValue(':eventid_match', $pollEventId, PDO::PARAM_INT); // Native prepares cannot reuse one name
					$pulsePollStmt->execute(); // Run the select
					$pulsePollRows = $pulsePollStmt->fetchAll(); // Active polls
					$pulseVoteStmt = $pollPdo->prepare('SELECT option_index, COUNT(*) AS vote_count FROM tep_poll_votes WHERE pollid = :pollid GROUP BY option_index'); // Per-option tallies
					$pulsePollsOut = array(); // JSON-friendly breakdown list
					foreach ($pulsePollRows as $pulsePollRow) { // Attach option labels + votes
						$pulseLabels = json_decode((string)$pulsePollRow['options_json'], true); // ["Yes","No"]
						if (!is_array($pulseLabels)) { // Bad JSON
							$pulseLabels = array(); // Skip broken options
						}
						$pulseVoteStmt->bindValue(':pollid', (int)$pulsePollRow['id'], PDO::PARAM_INT); // Bound poll id
						$pulseVoteStmt->execute(); // Grouped counts
						$pulseCountMap = array(); // option_index => votes
						foreach ($pulseVoteStmt->fetchAll() as $pulseCountRow) { // Build map
							$pulseCountMap[(int)$pulseCountRow['option_index']] = (int)$pulseCountRow['vote_count']; // Integer keys in PHP
						}
						$pulseOptions = array(); // AngularJS ng-repeat source
						foreach ($pulseLabels as $pulseIdx => $pulseLabel) { // One row per option
							$pulseOptions[] = array( // Breakdown cell
								'index' => (string)(int)$pulseIdx, // 0-based
								'label' => (string)$pulseLabel, // Option text
								'votes' => (string)(int)($pulseCountMap[(int)$pulseIdx] ?? 0) // Votes for this choice
							);
						}
						$pulsePollsOut[] = array( // One live poll
							'id' => (string)(int)$pulsePollRow['id'], // Poll id
							'question' => (string)$pulsePollRow['question'], // Headline
							'total_votes' => (string)(int)$pulsePollRow['total_votes'], // Sum
							'options' => $pulseOptions // Vote breakdown
						);
					}
					$queries['getEventAnalytics'] = query_definition('', '', array(), array(array( // Single HTTP 200 row for Event Pulse
						'ok' => '1',
						'checkin_total' => (string)$checkinTotal, // Registrations
						'checkin_in' => (string)$checkinIn, // Checked in
						'checkin_percent' => (string)$checkinPercent, // 0–100
						'lead_count' => (string)$leadCount, // Vendor leads
						'polls_json' => json_encode($pulsePollsOut) // Live poll vote breakdowns
					)));
				}
			}
		} catch (Throwable $pollEx) { // Missing schema, PDO down, missing table, or SQL error
			error_log('TEP poll query failed: ' . $pollEx->getMessage()); // Log only — no HTML error page
			if ($tepPollQueryName === 'getActivePoll') { // Safe empty list
				$queries['getActivePoll'] = query_definition('', '', array(), array()); // HTTP 200
			} elseif ($tepPollQueryName === 'savePushSubscription') { // Push save still returns JSON rows
				$queries['savePushSubscription'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'unavailable'))); // HTTP 200
			} elseif ($tepPollQueryName === 'getAttendeeCheckInStatus') { // Search still returns JSON rows
				$queries['getAttendeeCheckInStatus'] = query_definition('', '', array(), array()); // HTTP 200
			} elseif ($tepPollQueryName === 'checkInAttendee') { // Toggle still returns JSON rows
				$queries['checkInAttendee'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'unavailable'))); // HTTP 200
			} elseif ($tepPollQueryName === 'getVendorStatus') { // Booth lookup still returns JSON rows
				$queries['getVendorStatus'] = query_definition('', '', array(), array()); // HTTP 200
			} elseif ($tepPollQueryName === 'saveVendorLead') { // Lead save still returns JSON rows
				$queries['saveVendorLead'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'unavailable'))); // HTTP 200
			} elseif ($tepPollQueryName === 'getEventAnalytics') { // Pulse still returns JSON zeros
				$queries['getEventAnalytics'] = query_definition('', '', array(), array(array( // HTTP 200 empty metrics
					'ok' => '0',
					'checkin_total' => '0',
					'checkin_in' => '0',
					'checkin_percent' => '0',
					'lead_count' => '0',
					'polls_json' => '[]'
				)));
			} else {
				$queries['submitPollVote'] = query_definition('', '', array(), array(array('ok' => '0', 'reason' => 'unavailable'))); // HTTP 200
			}
			if ($tepPollQueryName !== '' && (!isset($queries[$tepPollQueryName]) || !isset($queries[$tepPollQueryName]['rows']))) { // Any PDO query that missed a named branch
				$queries[$tepPollQueryName] = query_definition('', '', array(), array()); // HTTP 200 empty rows — never fall through to mysqli 500
			}
		}
	}
?>
