<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include($_SERVER['DOCUMENT_ROOT']."/about/includes/headerContent.php"); ?>
</head>
<body>
	<?php include($root."/about/includes/nav.html"); ?>
	<section class="darkGreen center" style="padding:2.5em 0px .5em 0px;font-size:16pt">
		<div>Event Management Made Easy!</div>
		<H1 style="font-size:40pt">Mobile App</H1>
	</section>
	<section class="lightGreen center twoCol" style="font-size:12pt;padding-top: 0px;">
		<div style="background:#2e2d2d"><img src="img/mobile2.webp" style="width:100%" /></div>
		<div>
			<section style="width:25em;margin:auto;">
				<h2> The Event Phoenix Mobil App</h2>
				<ul style="text-align:left;list-style: disc;margin-bottom: 2em;">
					<li>Seamless Integration w/ Event App</li>
					<li>Android & iOS</li>
					<li>Sponsor Branding Options</li>
					<li>Event Schedule Options</li>
					<li>Maps</li>
					<li>Document Management</li>
					<li>Video Management</li>
					<li>Attendee Schedule Builder</li>
					<li>Socialization</li>
					<li>Gamification</li>
					<li>Surveys (Session, Overall & Others)</li>
					<li>Live Polling</li>
					<li>Appointment/Meeting setting</li>
					<li>Lead Retrieval</li>
					<li>Notifications & Much MORE!</li>
				</ul>
				<H4>Made "BY" Event Planners "FOR" Event Planners</H4>
				<p style="margin-top:1.5em"><a href="/freeTrial.php" class="button">Try The Event Phoenix for Free!</a></p>
			</section>
		</div>
	</section>
	<?php 
		include($root."/about/includes/vision.html"); 
		include($root."/about/includes/footer.html");
	?>
</body>
</html>