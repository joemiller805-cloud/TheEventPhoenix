<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include($_SERVER['DOCUMENT_ROOT']."/about/includes/headerContent.php"); ?>
</head>
<body>
	<?php include($root."/about/includes/nav.html"); ?>
	<section class="darkGreen center" style="padding:2.5em 0px .5em 0px;font-size:16pt">
		<div style="font-style: italic;">"How can all of this be so affordable?"</div>
		<H1 style="font-size:40pt">Pricing</H1>
		<p style="margin-bottom: 0px;">
			We have been doing conferences, workshops, trade shows, and other events ourselves
			for quite some time. <br/>
			We know how every purchase affects your overall budget. <br/>
			The Event Phoenix is priced in a way to make planners HAPPY!
		</p>
	</section>
	<section class="lightGreen" style="font-size:12pt;padding-top: 0px;">
		<section class="fourCol center">
			<div>
				<div class="optionDiv">
					MOBILE ONLY *<br/>
					<b>Single Event-Mobile App</b>
				</div>
			</div>
			<div>
				<div class="optionDiv">
					BASIC<br/>
					<b>Single Event-Full Event System</b>
				</div>
			</div>
			<div>
			<div class="optionDiv">
				PRO<br/>
				<b>Two to Five Events</b>
			</div>
			</div>
			<div>
				<div class="optionDiv">
					ENTERPRISE<br/>
					<b>Six or More Events</b>
				</div>
			</div>
			<div >
				<ul style="margin:auto;list-style:disc;text-align: left;">
					<li>Mobile App -Full App Usage Included</li>
					<li>Import you data from other systems with our easy to use templates</li>
					<li>Cancel at Any Time</li>
					<li>Contact Us for Pricing!</li>
				</ul>
			</div>
			<div >
				<ul style="margin:auto;list-style:disc;text-align: left;">
					<li>Full Event Management System</li>
					<li>Mobile App Included</li>
					<li>Single Event</li>
					<li>Cancel at Any Time</li>
					<li>Contact Us for Pricing!</li>
				</ul>
			</div>
			<div >
				<ul style="margin:auto;list-style:disc;text-align: left;">
					<li>Full Event Management System</li>
					<li>Mobile App Included</li>
					<li>Two to Five Events</li>
					<li>Cancel at Any Time</li>
					<li>Contact Us for Pricing!</li>
				</ul>
			</div>
			<div >
				<ul style="margin:auto;list-style:disc;text-align: left;">
					<li>Full Event Management System</li>
					<li>Mobile App Included</li>
					<li>Six or More Events</li>
					<li>Cancel at Any Time</li>
					<li>Contact Us for Pricing!</li>
				</ul>
			</div>
		</section>
		<p style="margin:2.5em;text-align: center;width:100%;">
			<a href="/freeTrial.php" class="button">Try The Event Phoenix for Free!</a>
		</p>
		<section style="width:60em;margin:auto">
			<div class="optionDiv center">
				<b>INCLUDED WITH BASIC, PRO, & ENTERPRIS SUBSCRIPTIONS</b><br/>
				<b>*Excludes</b> Mobile App Only Purchases-(See Mobile App Features)
			</div>
			<ul style="display:inline-block;vertical-align:top;list-style: disc;">
				<li>Full Version of The Event Phoenix</li>
				<li>All Features and Functionality</li>
				<li>Mobile App</li>
				<li>Event Management</li>
				<li>Staff Management</li>
				<li>Vendor Management</li>
				<li>Fully Customizable Event Pages</li>
				<li>Your Own Hosted Event Site</li>
			</ul>
			<ul style="display:inline-block;list-style: disc;margin-left:3em">
				<li>Ticket & Add-on Sales</li>
				<li>The Event Phoenix Schedule Builder</li>
				<li>Reports</li>
				<li>Document & Video Management</li>
				<li>Name Badge Functions</li>
				<li>On-Site or Manual Check-in/Ticket Redemption</li>
				<li>Unlimited Registration Types</li>
				<li>& The rest of The Event Phoenix's Amazing Features</li>
				<li>Full Built In Product Support In The App</li>
			</ul>
		</section>
	</section>
	<?php 
		include($root."/about/includes/vision.html"); 
		include($root."/about/includes/footer.html");
	?>
</body>
<style>
	.optionDiv{
		width:95%;
		border-radius: 5px;
		background: white;
		margin:auto;
		margin-top:.5em;
		margin-bottom:.5em;
	}
</style>
</html>