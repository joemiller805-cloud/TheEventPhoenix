<?php 
	include("common_functions.php");
		
	$inputs = sanitize_inputs($_REQUEST);	

	$resourceID = database_connect();

	$saved_sessions = array();
	$alerts = array();
	foreach($inputs as $key => $sectionid)
	{
		if ("session_" == substr($key, 0, 8))
		{
			$sessionid = substr($key, 8);
			$saved_sessions[] = $sessionid;
			
			$query = "
				select
					count(distinct registrationid) registrations,
					sections.capacity,
					rooms.capacity roomcapacity,
					courses.name coursename,
					sessions.name sessionname
				from
					signups
					join sections on signups.sectionid = sections.id
					join rooms on sections.roomid = rooms.id
					join courses on sections.courseid = courses.id
					join sessions on sections.sessionid = sessions.id
					join registrations on signups.registrationid = registrations.id
				where
					sectionid = $sectionid
					and registrations.confirmation != '{$inputs['confirmation']}'
				group by
					signups.sectionid
			";
			
			$resultID = mysqli_query($resourceID, $query);
			
			$row = mysqli_fetch_assoc($resultID);
			$capacity = $row['capacity'];
			if ($row['capacity'] == -1) $capacity = $row['roomcapacity'];
			
			if (0 == $capacity || $row['registrations'] < $capacity)
			{			
				$query = "
					update
						signups
					set
						sectionid = $sectionid
					where
						registrationid = (select id from registrations where eventid = {$inputs['eventid']} and confirmation = '{$inputs['confirmation']}')
						and sessionid = $sessionid;			
				";
	
				$resultID = mysqli_query($resourceID, $query);
				if(!$resultID) print "Error: " . mysqli_error($resourceID);
				
				$query = "
					select 
						id 
					from signups 
						where registrationid = 
						(select id from registrations where eventid =  {$inputs['eventid']} and confirmation = '{$inputs['confirmation']}') 
						and sessionid = $sessionid;
				";				
				
				$resultID = mysqli_query($resourceID, $query);
				
				if (!mysqli_affected_rows($resourceID))
				{
					$query = "
						insert into 
							signups
								(
									registrationid,
									sessionid,
									sectionid
								)
							values
								(
									(select id from registrations where confirmation = '{$inputs['confirmation']}'),
									$sessionid,
									$sectionid
								)	
					";
					$resultID = mysqli_query($resourceID, $query);	
				}	
			}
			else
			{
				$alerts[] = "alert-danger={$row['coursename']} during {$row['sessionname']} is already at capacity.";
			}
		}		
	}	
	$saved_sessions = implode($saved_sessions, ',');
	
	$query = "
		delete
		from
			signups
		where
			registrationid = (select id from registrations where eventid = {$inputs['eventid']} and confirmation = '{$inputs['confirmation']}')
			and sessionid not in ($saved_sessions)
	";
	$resultID = mysqli_query($resourceID, $query);	
	
	if (0 == sizeof($alerts)) $alerts[] = "alert-success=Your session selections have been saved";
	
	$alerts = implode($alerts, "&");
	
	header("Location:/e/{$inputs['slug']}/signup/{$inputs['confirmation']}?$alerts");
	mysqli_close($resourceID);			
?>
