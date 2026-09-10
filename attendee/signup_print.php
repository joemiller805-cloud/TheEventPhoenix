<?php
	session_start();
	$root = $_SERVER['DOCUMENT_ROOT'];
	include($root."/common_functions.php");
	$inputs = sanitize_inputs($_REQUEST);
	$resourceID = database_connect();
?>
<html>
	<head>
		<!-- Bootstrap Core CSS -->
		<link href="/css/bootstrap.css" rel="stylesheet">

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
			$query =
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
					confirmation = '{$inputs['confirmation']}'
    		";

			$resultID = mysqli_query($resourceID, $query);

			if (mysqli_num_rows($resultID))
			{
				$row = mysqli_fetch_assoc($resultID);
			}
			else
			{
				die("The confirmation number {$inputs['confirmation']} could not be found.");
			}
    	?>

		<p><b>Event: </b><?=$row['event']?><br/>
		<b>Confirmation: </b><?=$row['confirmation']?><br/>
		<b>Registrant: </b><?=$row['first_name']. ' '. $row['last_name']?></p>

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
						registrations.confirmation = '{$inputs['confirmation']}'
					order by
						sessions.starttime asc,
						sessions.endtime desc
				";

				$resultID = mysqli_query($resourceID, $query);

				if (mysqli_num_rows($resultID))
				{
					while($row = mysqli_fetch_assoc($resultID))
					{
						print (	"	<tr>
										<td>{$row['session']}</td>
										<td>{$row['date']}</td>
										<td>{$row['room']}</td>
										<td>{$row['course']}</td>
									</tr>
								");
					}
				}

				mysqli_close($resourceID);
			?>
			</tbody>
		</table>
	</body>
</html>