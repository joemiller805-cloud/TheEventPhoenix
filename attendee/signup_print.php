<?php
	$root = $_SERVER['DOCUMENT_ROOT'];
	include($root."/common_functions.php"); // Helpers before any session cookie
	start_secure_session(); // SameSite=Lax instead of raw session_start
	$inputs = sanitize_inputs($_REQUEST);
	$resourceID = database_connect();
	$confirmation = (string)($inputs['confirmation'] ?? ''); // Bound lookup key
	$safeConfirmation = tep_h($confirmation); // HTML-safe for die() and markup
?>
<html>
	<head>
		<!-- Bootstrap Core CSS -->
		<link href="/css/bootstrap/css/bootstrap.css" rel="stylesheet"> <!-- Sweep A: real Bootstrap path; /css/bootstrap.css 404 -->

		<!-- Custom CSS -->
		<link href="/css/modern-business.css" rel="stylesheet">

		<!-- Custom Fonts -->
		<link href="/font-awesome-4.1.0/css/font-awesome.min.css" rel="stylesheet" type="text/css">

		<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		<!--[if lt IE 9]>
			<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
			<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
		<![endif]-->

	</head>

	<body>

		<?php
			$query = // Confirmation is bound; not interpolated
			"
				select
					first_name,
					last_name,
					confirmation,
					events.name event
				from
					registrations
					join events on registrations.eventid = events.id
				where
					confirmation = ?
    		";

			$stmt = mysqli_prepare($resourceID, $query); // Parameterized lookup
			if (!$stmt) { // Prepare failed
				die('The confirmation number ' . $safeConfirmation . ' could not be found.'); // Escaped output
			}
			mysqli_stmt_bind_param($stmt, 's', $confirmation); // Bound confirmation
			mysqli_stmt_execute($stmt); // Run lookup
			$resultID = mysqli_stmt_get_result($stmt); // mysqlnd result set

			if ($resultID && mysqli_num_rows($resultID))
			{
				$row = mysqli_fetch_assoc($resultID);
				mysqli_free_result($resultID); // Free header query
			}
			else
			{
				mysqli_stmt_close($stmt); // Close before die
				die('The confirmation number ' . $safeConfirmation . ' could not be found.'); // Escaped confirmation
			}
			mysqli_stmt_close($stmt); // Done with header query
    	?>

		<p><b>Event: </b><?= tep_h($row['event']) ?><br/>
		<b>Confirmation: </b><?= tep_h($row['confirmation']) ?><br/>
		<b>Registrant: </b><?= tep_h($row['first_name'] . ' ' . $row['last_name']) ?></p>

		<table class="table table-bordered table-condensed" style="font-size:.75em;">
			<thead>
				<th>Session</th>
				<th>Date and Time</th>
                <th>Room</th>
                <th>Course</th>
			</thead>
			<tbody>
			<?php
				$query = "
					select
						sessions.name session,
						concat(date_format(sessions.starttime, '%a, %b %e %l:%i %p'), ' to ',  date_format(sessions.endtime, '%l:%i %p')) date,
						courses.name course,
						rooms.name room
					from
						sessions
						join events on sessions.eventid = events.id
						join registrations on events.id = registrations.eventid
						left outer join signups on
							registrations.id = signups.registrationid
							and sessions.id = signups.sessionid
						left outer join sections on signups.sectionid = sections.id
						left outer join courses on sections.courseid = courses.id
						left outer join rooms on sections.roomid = rooms.id
					where
						registrations.confirmation = ?
					order by
						sessions.starttime asc,
						sessions.endtime desc
				";

				$stmt = mysqli_prepare($resourceID, $query); // Parameterized schedule rows
				if ($stmt) { // Prepare ok
					mysqli_stmt_bind_param($stmt, 's', $confirmation); // Bound confirmation
					mysqli_stmt_execute($stmt); // Run schedule query
					$resultID = mysqli_stmt_get_result($stmt); // mysqlnd result set
					if ($resultID && mysqli_num_rows($resultID))
					{
						while($row = mysqli_fetch_assoc($resultID))
						{
							print (	"	<tr>
										<td>" . tep_h($row['session']) . "</td>
										<td>" . tep_h($row['date']) . "</td>
										<td>" . tep_h($row['room']) . "</td>
										<td>" . tep_h($row['course']) . "</td>
									</tr>
								");
						}
						mysqli_free_result($resultID); // Free schedule rows
					}
					mysqli_stmt_close($stmt); // Done with schedule query
				}

				mysqli_close($resourceID);
			?>
			</tbody>
		</table>
	</body>
</html>