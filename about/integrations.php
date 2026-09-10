<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include($_SERVER['DOCUMENT_ROOT']."/about/includes/headerContent.php"); ?>
</head>
<body>
	<?php include($root."/about/includes/nav.html"); ?>
	<section class="darkGreen center twoCol" style="padding-top:2.5em;font-size:16pt">
		<div>
			<img src="img/online payment.jpg" style="width:100%"/>
		</div>
		<div>
			<H1 style="font-size:40pt">Payment Processing</H1>
			<p style="margin-top:1em;width:15em;margin:auto">
				Check out our list of preferred vendors we can partner with so you can accept credit card payments.  Money goes directly into your account. EasyRegPro does NOT handle your money. You work directly with these vendors to set up your account.<br/>
				<p style="font-size:12pt;margin-top: 1em;">*Setup fees may apply.</p>
			</p>
		</div>
	</section>
	<section class="lightGreen center twoCol" style="font-size:12pt;padding-top: 0px;">
		<div>
			<H3>e~Funds for Schools</H3>
			<img style="height:130px;margin-bottom:1em" src="img/efunds.webp"/>
			<p style="width:25em;margin:auto;text-align:left">
				Payment Solutions for K12 Schools.<br/>
				Learn why EFS Solutions are considered to be "Conveniently the best!"<br/><br/>
				Trusted by more than 10,000 Schools, Institutions and Organizations.<br/><br/>
				A True, Single-Source K12 Payment Processor with Best-in-Class Integrations
			</p>
		</div>
		<div>
			<H3>MagicWrighter</H3>
			<img style="height:130px;margin-bottom:1em" src="img/MWlogo.webp"/>
			<p style="width:25em;margin:auto;text-align:left">
				Magic-Wrighter, Inc. is a national leader in the financial institution, business and school payment markets. Nearly 40 years of experience has given us a unique understanding of what financial institutions need and an eye for trends within the industry.<br/><br/>


				We offer a partnership that your institution can truly grow with, and help you meet the ever-changing demands of your target market. Magic-Wrighter continually strives to be the innovative leader in the FI industry.
			<p style="width:25em;margin:auto;text-align:left">
		</div>
		
	</section>
	<?php 
		include($root."/about/includes/vision.html"); 
		include($root."/about/includes/footer.html");
	?>
</body>
</html>