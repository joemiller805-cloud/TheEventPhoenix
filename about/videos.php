<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include($_SERVER['DOCUMENT_ROOT']."/about/includes/headerContent.php"); ?>
</head>
<body>
	<?php include($root."/about/includes/nav.html"); ?>
	<section class="darkGreen center" style="padding:2.5em 0px .5em 0px;font-size:16pt">
		<H1 style="font-size:40pt">Get Started Videos</H1>
		<p style="margin-bottom: 0px;">
			See what everyone is talking about!
		</p>
		<H1 style="font-size:40pt">Coming Soon!!</H1>
	</section>
	<section class="lightGreen center twoCol" style="font-size:12pt;padding: 20px 0px;">
		<div>
			<span style="min-height: 60px;display: block">
				<video width="400" autoplay muted loop>
				  <source src="img/conf2.mp4" type="video/mp4">
				</video>
			</span>
			<H2>EasyRegPro Demo</H2>
			<div style="font-size:13pt;width:30em;margin:auto;text-align:left">
				See for yourself just how easy our EasyRegPro Event Management System really is.  More features than you may ever use but you get them ALL with ALL of our subscription plans.  Just use what you want and grow into the other features and functionality at your own pace.  EasyRegPro truly is an event planner's best friend!
			</div>
		</div>
		<div>
			<span style="min-height: 60px;display: block">
				<video width="400" autoplay muted loop>
				  <source src="img/conf2.mp4" type="video/mp4">
				</video>
			</span>
			<H2>Mobile App Demo</H2>
			<div style="font-size:13pt;width:30em;margin:auto;text-align:left">
				See why everyone is talking about our NEW Mobile App. EasyRegPro was designed by event planners so we made this so easy to use.  Use our built in templates to import data from other Event Management Systems or use our own EasyRegPro software to build your app. You can even import all of your data from files of your own! So easy to do!
			</div>
		</div>
	</section>
	<section class="darkGreen center" style="padding-bottom:3em">
		<H2>TRY IT YOURSELF FOR FREE!!!</H2>
		<p style="width:20em;margin:auto">
			Click the button below to get started on creating your next event! We are so confident you will love EasyRegPro that we will let you try it for free.  Then, when ready, choose the plan that is best for you to publish your events!
		</p>
		<div style="margin:2em"><a href="/freeTrial.php" class="button">Try EasyRegPro for Free!</a></div>
	</section>
	<?php 
		include($root."/about/includes/footer.html");
	?>
</body>
</html>