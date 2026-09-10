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
		<H1 style="font-size:40pt">FEATURES</H1>
		<p style="margin-bottom: 0px;">
			EasyRegPro is your one stop shop for all your event planning needs. <br/>
			Whether it is an in person event, virtual or hybrid, EasyRegPro is what you need.  <br/>
			Check out just some of the great feature EasyRegPro has to offer.
		</p>
	</section>
	<section class="lightGreen center twoCol" style="font-size:12pt;padding-top: 0px;">
		<div><img src="img/evt.jpg" style="width:100%" /></div>
		<div>
			<section style="width:25em;margin:auto;">
				<h2>Event Management</h2>
				<ul style="text-align:left;list-style: disc;margin-bottom: 2em;">
					<li>Live Events</li>
					<li>Virtual Events</li>
					<li>Hybrid Events</li>
					<li>Mobile App</li>
					<li>Ticket Sales (Event & K12)</li>
					<li>Fully Customizable Event Pages (unlimited)</li>
					<li>Brand Integration</li>
					<li>Socialization</li>
					<li>Gamification</li>
					<li>Staff Management</li>
					<li>Vendor Management</li>
					<li>Document Management</li>
					<li>Video Management</li>
					<li>Expense Management</li>
					<li>Inventory Management</li>
					<li>Robust Scheduling Tool</li>
					<li>Messaging</li>
					<li>Expedited On-site Check-in</li>
					<li>Unlimited Registration Types</li>
					<li>& More!</li>
				</ul>
				<H4>Made "BY" Event Planners "FOR" Event Planners</H4>
				<p><a href="/freeTrial.php" class="button">Try EasyRegPro for Free!</a></p>
			</section>
		</div>
	</section>
	<?php 
		include($root."/about/includes/vision.html"); 
		include($root."/about/includes/footer.html");
	?>
</body>
</html>