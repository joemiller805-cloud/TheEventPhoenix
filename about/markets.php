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
		<H1 style="font-size:40pt">Who Uses The Event Phoenix?</H1>
		<p style="margin-bottom: 0px;">
			Due to it's amazingly simple design, The Event Phoenix is used and can be used for events of any and all types. Let The Event Phoenix work for you!
		</p>
	</section>
	<section class="lightGreen center twoCol" style="font-size:12pt;padding-top: 0px;">
		<div><img src="img/convention.jpg" style="width:100%" /></div>
		<div>
			<section style="width:25em;margin:auto;">
				<h2>Industries</h2>
				<ul style="text-align:left;list-style: disc;margin-bottom: 2em;">
					<li>Conventions</li>
					<li>Conferences</li>
					<li>Meetings</li>
					<li>Symposiums</li>
					<li>Seminars</li>
					<li>Virtual Events</li>
					<li>Religious Organizations</li>
					<li>K12 Games/Activities/Alternative & Community Ed</li>
					<li>Higher Education</li>
					<li>Corporate Events & Functions</li>
					<li>Non-Profits</li>
					<li>Associations & Agencies</li>
					<li>Where Ever People Gather in Person or Virtually</li>
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