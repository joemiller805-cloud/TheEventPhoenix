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
		<p style="margin-bottom: 0px;">
			stuff
		</p>
	</section>
	<section class="lightGreen center twoCol" style="font-size:12pt;padding-top: 0px;">
		<div><img src="img/Handheld Mobile phone.webp" style="width:100%" /></div>
		<div>
			<section style="width:25em;margin:auto;">
				<h2> EasyRegPro Mobil App</h2>
				<ul style="text-align:left;list-style: disc;margin-bottom: 2em;">
					
				</ul>
				<H4>Made "BY" Event Planners "FOR" Event Planners</H4>
				<p style="margin-top:1.5em"><a href="/freeTrial.php" class="button">Try EasyRegPro for Free!</a></p>
			</section>
		</div>
	</section>
	<?php 
		include($root."/about/includes/vision.html"); 
		include($root."/about/includes/footer.html");
	?>
</body>
</html>