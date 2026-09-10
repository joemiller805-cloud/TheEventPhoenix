<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">
<link rel="stylesheet" href="/css/style.css">

<title>PSUGevents.com</title> 
<?php include("common_functions.php");?> 
<?php include("commonStyles.php");?> 
<?php include("commonJs.php");?> 
<script type="text/javascript">
	let app = angular.module('regApp', ['easyRegDataModule', 'erSvc', 'navMod']);
	app.controller('regController', function($scope, $http, $filter, $q, dataSvc, erSvc){
		erSvc.getAccountIdFromURL().then(function(res){
			dataSvc.getArray({'query': 'accountInfo'}).then(function(resp){
				$scope.accountLogo = resp[0].web_logo;
				accountid = resp[0].id;
				trialAcct = resp[0].trial == '1';
				prepPageData();
			});
		});
	}); // End controller
</script>
</head>
<body ng-app="regApp" style="padding-top: 10px;">
<section class=" container-fluid" ng-controller="regController">
	<div class="row">
		<div class="col-lg-12">
			<div class="learning-head d-flex justify-content-between f-wrap align-items-center"
				style="padding-bottom:1rem; border-bottom:1px solid black">
				<div class="d-flex f-wrap">
					<img ng-src="{{accountLogo}}" height="50" />
				</div>
				<div class="d-flex f-wrap"></div>
			</div>
		</div>
	</div>

	<!-- Privacy Policy Dialog -->
	<div id="privacyPolicyDiv">
		
		<H2>PREMIER SOFTWARE USER GROUP EVENTS, LLC	PRIVACY POLICY</H2>
		<H3>Our Commitment to Privacy</H3>

		Your privacy is very important to us. Part of our operation of this website involves the
		collection and use of information about you. This privacy policy explains what type of
		information we collect and what we do with that information to allow you to make
		choices about the way your information is collected and used. This privacy policy may
		change from time to time, so please check it often.

		<H3>What Information Do We Collect?</H3>

		In general, you can visit this website without identifying who you are or revealing any
		information about yourself. Information collected online can generally be categorized as
		anonymous or personally identifiable. Anonymous information is information that cannot
		be connected to the identity of a specific individual. Personally identifiable information is
		information that specifically identifies a particular user, such as name, address, or phone
		number. An example of anonymous information is the fact that, while this website may
		record the number of visits to a particular page that occur in a given period of time, it
		does not necessarily tell us the names or other identifying information of every visitor.
		Many users of this website will choose not to provide any personally identifiable
		information; therefore, those individuals are anonymous to us, and any data collected
		about their use of this website is anonymous information.
		Automatic Anonymous Information

		When you visit our site, we collect certain technical and routing information about your
		computer. For example, we log environmental variables such as browser type, operating
		system and CPU speed, and the Internet Protocol (IP) address of your originating Internet
		Service Provider, to try to bring you the best possible service. We also record search
		requests and results to try to ensure the accuracy and efficiency of our search engine. We
		use your IP address to track your use of the site, including pages visited and the time
		spent on each page. We collect this information and use it to measure the use of this
		website and to improve its content and performance. All of the information that is
		automatically submitted to us by your browser is considered anonymous information. To
		the extent we share such information with third parties, it is not traceable to any particular
		user and will not be used to contact you.

		<H3>Information You Provide To Us</H3>

		When you create an account, you provide us with personal information that includes your
		name and a password. You can also choose to add a phone number or payment
		information to your account. Even if you aren’t signed in to an account, you might
		choose to provide us with information — like an email address to communicate with us
		or receive updates about our services.

		We also collect the content you create, upload, or receive from others when using our
		services. This includes things like email, comments, or posts you write and receive, and
		photos and videos you save.

		<H3>Cookies</H3>

		This site uses cookies. Cookies are small data files, typically made up of a string of text
		and numbers, that assign you a unique identifier. This information enables your computer
		to have a “dialogue” with our site and permits us to administer our site more efficiently
		and to provide a more tailored and user-friendly service to you. You may set your
		browser to notify you when you receive a cookie or to prevent cookies from being sent; if
		you do so, this may limit the functionality we can provide you when you visit our site.
		Third parties that link on this site may use cookies or collect other information when you
		go to their site. We do not control the collection or use of your information by these
		companies. You should contact these companies directly if you have any questions about
		their collection or use of information about you.

		<H3>How Do We Use Information We Collect?</H3>

		We collect personally identifiable information for providing the services you request,
		generating statistical studies, conducting marketing research, improving products and
		services, sending you surveys, and notifying you of new products and any other changes
		to our site or services that may affect you. When you submit personally identifiable
		information to us, you understand that you are agreeing to allow us to access, store, and
		use that information for those purposes.
		We may in the future provide your contact information to our affiliated third parties who
		can provide useful and complimentary products or services to you. You can always opt
		out of our third party affiliates form receiving your information by emailing us at
		joe.miller@psugevents.com.
		We may be required by law enforcement or judicial authorities to provide personally
		identifiable information to the appropriate governmental authorities. If requested by law
		enforcement or judicial authorities, we will provide this information on receipt of the
		appropriate documentation. We may also release information to law enforcement
		agencies or other third parties if we feel it is necessary to protect the safety and welfare of
		our personnel or to enforce our terms of use.
		Opt-Out Policy

		If at any time you do not wish to receive offers and emails from us, we ask that you tell
		us. You may remove your name from our mailing list by sending us an email addressed
		to joe.miller@psugevents.com and indicating in the subject line “No Offers or Email.”

		<H3>Security</H3>

		We operate secure data networks protected by industry standard firewall and password
		protection systems. Our security and privacy policies are periodically reviewed and
		enhanced as necessary, and only authorized individuals have access to the personally
		identifiable information provided by our users. We do not, however, guarantee that
		unauthorized, inadvertent disclosure will never occur.

		<H3>Transfer of Customer Information</H3>

		Customer lists and information are properly considered assets of a business. Accordingly,
		if we merge with another entity or if we sell our assets to another entity, our customer
		lists and information, including personally identifiable information you have provided us,
		would be included among the assets that would be transferred.
		<br/>
	</div>
</section>
</body>
</html>