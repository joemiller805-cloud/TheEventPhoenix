<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include($_SERVER['DOCUMENT_ROOT']."/about/includes/headerContent.php"); ?>
</head>
<body>
	<?php include($root."/about/includes/nav.html"); ?>
	<section class="darkGreen center" style="padding:2.5em 0px .5em 0px;font-size:16pt">
		<div>EK12 Ticketing Made Easy!</div>
		<H1 style="font-size:40pt">K12 Schools</H1>
		<p style="margin-bottom: 0px;">
			The Event Phoenix is your one stop shop for event ticket sales, <br/>
			Community Ed and Adult Ed course scheduling.  <br/>
			Check out just some of the great feature The Event Phoenix has to offer.
		</p>
	</section>
	<section class="lightGreen center twoCol" style="font-size:12pt;padding-top: 0px;">
		<div><img src="img/K12 Page.jpg" style="width:100%" /></div>
		<div>
			<section style="width:25em;margin:auto;">
				<h2>K12 District Features</h2>
				<ul style="text-align:left;list-style: disc;margin-bottom: 2em;">
					<li>Sports / Event ticket sales</li>
					<li>Easy ticket scanning</li>
					<li>Multiple Ticket types/prices (Student, staff, parent...)</li>
					<li>School store sales processing</li>
					<li>Yearbook, class rings & more sales</li>
					<li class="bold">Community Ed Scheduling & Registration</li>
					<li class="bold">Adult Ed Scheduling & Registration</li>
					<li class="bold">Meeting room rental/reservations</li>
					<li>Inventory Management</li>
					<li>Payment processing by e~Funds for Schools</li>
					<li>& More!</li>
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