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
				<div class="d-flex f-wrap">
					
				</div>
			</div>
		</div>
	</div>

	<div id="termsPolicyDiv">
		<H2>WEBSITE TERMS-OF-USE AGREEMENT</H2>
		<p class="bold">
			PREMIER SOFTWARE USER GROUP EVENTS, LLC (“PSUG“)
			WELCOMES YOU TO
			https://easyregpro.com/learningCenter.php/psugevents. WE ASK
			THAT YOU READ THE FOLLOWING TERMS OF USE, WHICH
			CONSTITUTE A LICENSE THAT COVERS YOUR USE OF THIS SITE
			AND ANY TRANSACTIONS THAT YOU ENGAGE IN THROUGH THIS
			SITE (AGREEMENT). BY ACCESSING, VIEWING, OR USING THIS SITE
			OR ANY MATERIALS ON THIS SITE, YOU ACKNOWLEDGE THAT YOU
			HAVE READ, UNDERSTAND, AND AGREE WITH THESE TERMS. IF
			YOU DO NOT WISH TO BE BOUND BY THESE TERMS, PLEASE DO
			NOT USE THIS SITE.
		</p>

		<H3>USE OF SITE</H3>

		This website is provided solely for the use of current and future customers
		of PSUG Events to provide you with information about our company, to
		permit you to place orders for our products and services, view products and
		services on our Website, including educational videos, and to enable you to
		contact us with any questions or comments that you may have. Any other
		use of this site is prohibited. By way of example, you should not use any
		features of this site that permit communications or postings to post,
		transmit, display, or otherwise communicate
		i. any defamatory, threatening, obscene, harassing, or otherwise
		unlawful information;
		ii. any advertisement, solicitation, spam, chain letter, or other similar
		type of information;
		iii. any encouragement of illegal activity;
		iv. unauthorized use or disclosure of private, personally identifiable
		information of others; or
		v. any materials subject to trademark, copyright, or other laws protecting
		any materials or data of others in the absence of a valid license or
		other right to do so.

		<H3>RESTRICTIONS</H3>

		You will not (i) license, sub-license, sell, resell, use as a time-share or
		service bureau, or otherwise use or provide the Materials or access to the
		Materials for a third party’s benefit unless such use has been authorized by

		PSUG Events; (ii) transfer, assign, distribute or otherwise commercially
		exploit or make the Materials available to any third party not authorized by
		PSUG Events; (iii) modify or make derivative works based upon the Service
		or the Content; (iv) create Internet “links” to the Service or “frame” or
		“mirror” any Content on any other server or wireless or Internet-based
		device; (v) reverse engineer or decompile the Materials.

		<H3>SITE CONTENTS AND OWNERSHIP</H3>

		The information contained on this site, including all images, designs,
		photographs, writings, graphs, data, videos, and other materials (Materials)
		are the property of PSUG Events or its providers, and are protected by
		copyrights, trademarks, trade secrets, or other proprietary rights.
		Permission is granted to display, copy, distribute, download, and print
		portions of this site solely for the purposes of using this site for the
		authorized uses described above. You must retain all copyright and other
		proprietary notices on all copies of the Contents. You shall comply with all
		copyright laws worldwide in your use of this website and prevent
		unauthorized copying of the Contents. Except as provided in this Notice,
		PSUG Events does not grant you any express or implied right in or under
		any patents, trademarks, copyrights, or trade secret information.

		<H3>DISCLAIMER OF WARRANTY</H3>

		You expressly agree that use of this website and its Materials is at your
		sole risk. Neither PSUG Events, its affiliates, nor any of their officers,
		directors, employees, agents, third-party content providers, or licensors
		(collectively, “Providers”), or the like, warrant that this site or its content or
		Materials will be uninterrupted or error-free; nor do they make any warranty
		as to the results that may be obtained from the use of this site or its content
		or Materials, or as to the accuracy, completeness, reliability, security, or
		currency of the Materials. The use by you of any and all materials on this
		site for any purpose is expressly at your own risk.
		The Materials may contain errors, omissions, inaccuracies, or outdated
		information. Further, PSUG Events does not warrant reliability of any
		statement or other information displayed or distributed through the site.
		PSUG Events reserves the right, in its sole discretion, to correct any errors
		or omissions in any portion of the site. PSUG Events may make any other
		changes to this site, the Materials and the products, programs, services, or
		prices (if any) described in this site at any time without notice.

		THIS SITE AND THE INFORMATION, CONTENT, AND MATERIALS ON
		THIS SITE ARE PROVIDED ON AN “AS IS,” “WHERE IS,” AND “WHERE
		AVAILABLE” BASIS. PSUG EVENTS MAKES NO REPRESENTATIONS
		OR WARRANTIES OF ANY KIND, EXPRESS OR IMPLIED, AS TO THE
		OPERATION OF THE SITE, THE CONTENT, INFORMATION, OR THE
		MATERIALS ON THIS SITE. TO THE FULLEST EXTENT PERMISSIBLE
		UNDER APPLICABLE LAW, PSUG EVENTS EXPRESSLY DISCLAIMS
		ALL WARRANTIES, EXPRESS OR IMPLIED, OF ANY KIND, WITH
		RESPECT TO ANY OF THE MATERIALS, CONTENT, OR INFORMATION
		ON THIS SITE OR ANY GOODS OR OTHER PRODUCTS OR SERVICES
		OFFERED, SOLD, OR DISPLAYED ON THIS SITE OR YOUR USE OF
		THIS SITE GENERALLY, INCLUDING WARRANTIES OF
		MERCHANTABILITY, ACCURACY OF INFORMATION, QUALITY, TITLE,
		FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT.
		SOME JURISDICTIONS LIMIT OR DO NOT ALLOW THE DISCLAIMER
		OF IMPLIED OR OTHER WARRANTIES, SO THE ABOVE DISCLAIMER
		MAY NOT APPLY TO THE EXTENT SUCH JURISDICTION’S LAW
		APPLIES TO THIS AGREEMENT.

		<H3>LIMITATION OF LIABILITIES</H3>

		YOU AGREE THAT PSUG AND ITS PROVIDERS SHALL NOT BE
		LIABLE FOR ANY DAMAGE, LOSS, OR EXPENSE OF ANY KIND
		ARISING OUT OF OR RESULTING FROM YOUR POSSESSION OR USE
		OF THE MATERIALS, CONTENT, OR INFORMATION ON THIS SITE
		REGARDLESS OF WHETHER SUCH LIABILITY IS BASED IN TORT,
		CONTRACT, OR OTHERWISE. IN NO EVENT, INCLUDING, WITHOUT
		LIMITATION, A NEGLIGENT ACT, SHALL PSUG EVENTS OR ANY OF
		ITS PROVIDERS BE LIABLE TO YOU FOR ANY DIRECT, INDIRECT,
		SPECIAL, INCIDENTAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES
		(INCLUDING, WITHOUT LIMITATION, LOSS OF PROFITS, LOSS OR
		CORRUPTION OF DATA, LOSS OF GOODWILL, WORK STOPPAGE,
		COMPUTER FAILURE OR MALFUNCTION, OR INTERRUPTION OF
		BUSINESS), ARISING OUT OF OR IN ANY WAY RELATED TO THE
		MATERIALS, CONTENT, OR INFORMATION ON THIS SITE OR ANY
		OTHER PRODUCTS, SERVICES, OR INFORMATION OFFERED, SOLD,
		OR DISPLAYED ON THIS SITE, YOUR USE OF, OR INABILITY TO USE,
		THIS SITE GENERALLY, OR OTHERWISE IN CONNECTION WITH THIS
		AGREEMENT, REGARDLESS OF WHETHER PSUG EVENTS OR ANY
		OF ITS PROVIDERS HAVE BEEN ADVISED OF THE POSSIBILITY OF

		SUCH DAMAGES. BECAUSE SOME STATES DO NOT ALLOW THE
		LIMITATION OF LIABILITY FOR CONSEQUENTIAL OR INCIDENTAL
		DAMAGES, THE ABOVE LIMITATION MAY NOT APPLY TO YOU.

		<H3>INDEMNIFICATION</H3>

		You agree to indemnify, defend, and hold harmless PSUG EVENTS, its
		affiliates, agents, employees, and licensors from and against any and all
		claims and expenses, including reasonable attorney fees, arising out of or
		related in any way to your use of the site, your use of the Materials,
		violation of this Agreement, violation of any law or regulation, or violation of
		any proprietary or privacy right.

		<H3>PRIVACY POLICY</H3>

		Click here to access PSUG EVENTS’s Privacy Policy governing the use of
		information that PSUG EVENTS obtains from you through your use of this
		website.

		<H3>LIMITATIONS ON CLAIM</H3>

		Any cause of action you may have with respect to your use of this site must
		be commenced within one year after the claim or cause of action arises.

		<H3>TERM AND TERMINATION</H3>

		Without limiting its other remedies, PSUG EVENTS may immediately
		discontinue, suspend, terminate, or block your and any user’s access to
		this site at any time in our sole discretion.

		<H3>HYPERLINK DISCLAIMERS</H3>

		As a convenience to you, we may provide on this site links to websites
		operated by other entities (collectively the “Linked Sites”). If you use any
		Linked Sites, you will leave this site. If you decide to visit any Linked Site,
		you do so at your own risk and it is your responsibility to take all protective
		measures to guard against viruses or other destructive elements. Linked
		Sites, regardless of the linking form (e.g., hotlinks, hypertext links, IMG
		links) are not maintained, controlled, or otherwise governed by PSUG
		EVENTS. The content, accuracy, opinions expressed, and other links
		provided by Linked Sites are not investigated, verified, monitored, or

		endorsed by PSUG EVENTS. PSUG EVENTS does not endorse, make
		any representations regarding, or warrant any information, goods, and/or
		services appearing and/or offered on any Linked Site, other than linked
		information authored by PSUG EVENTS. Links do not imply that PSUG
		EVENTS or this site sponsors, endorses, is affiliated or associated with, or
		is legally authorized to use any trademark, trade name, logo, or copyright
		symbol displayed in or accessible through the links, or that any Linked Site
		is authorized to use any trademark, trade name, logo or copyright symbol
		of PSUG EVENTS or any of its affiliates or subsidiaries. Except for links to
		information authored by PSUG EVENTS, PSUG EVENTS is neither
		responsible for nor will it be liable under any theory based on (i) any Linked
		Site; (ii) any information and/or content found on any Linked Site; or (iii) any
		site(s) linked to or from any Linked Site. If you decide to visit any Linked
		Sites and/or transact any business on them, you do so at your own risk.
		PSUG EVENTS reserves the right to discontinue any Linked Site at any
		time without prior notice. Please contact the webmasters of any Linked
		Sites concerning any information, goods, and/or services appearing on
		them.

		<H3>CONTROLLING LAW, JURISDICTION, AND INTERNATIONAL USERS</H3>
		This Agreement is governed by and shall be construed in accordance with
		the laws of the State of Michigan, U.S.A., without reference to its conflict-of-
		law provisions. PSUG EVENTS makes no representation that the materials
		are appropriate or available for use outside the United States. If you access
		this site from outside the United States, you will be responsible for
		compliance with all local laws. You agree to comply with all laws and
		regulations applicable to your use of this site. You agree to submit to the
		personal and exclusive jurisdiction of the state or federal courts located
		within Oakland County, Michigan for any and all disputes with PSUG
		EVENTS arising out of your use of this site.

		<H3>ENTIRE AGREEMENT</H3>

		This Agreement constitutes the entire agreement between PSUG EVENTS
		and you with respect to this website, and it supersedes all prior or
		contemporaneous communications and proposals, whether electronic, oral,
		or written, between you and PSUG EVENTS with respect to this website. A
		printed version of this Agreement and of any notice given in electronic form
		shall be admissible in judicial or administrative proceedings based on or

		relating to this Agreement to the same extent and subject to the same
		conditions as other business documents and records originally generated
		and maintained in printed form. If for any reason a court of competent
		jurisdiction finds any provision of this Agreement or portion of it to be
		unenforceable, that provision shall be enforced to the maximum extent
		permissible so as to effect the intent of this Agreement, and the remainder
		of this Agreement shall continue in full force and effect. No waiver by either
		party of any breach or default hereunder shall be deemed to be a waiver of
		any preceding or subsequent breach or default.

		<H3>MODIFICATIONS TO AGREEMENT</H3>

		We may revise this Agreement at any time and you agree to be bound by
		the revised Agreement. Any such modifications will become effective on the
		date they are first posted to this site. It is your responsibility to return to this
		Agreement from time to time to review the most current terms and
		conditions. PSUG EVENTS does not and will not assume any obligation to
		notify you of changes to this Agreement. Your continued used of the site
		confirms your acceptance and agreement with the revised Agreement.

		<H3>ELECTRONIC COMMUNICATIONS AND ELECTRONIC SIGNATURES</H3>
		You agree to be bound by any affirmation, assent, or agreement you
		transmit through this website, including but not limited to any consent you
		give to receive communications from PSUG EVENTS solely through
		electronic transmission. You agree that when in the future you click on an “I
		agree,” “I consent,” or other similarly worded “button” or entry field with your
		mouse, touchpad, keystroke, or other computer device, your agreement or
		consent will be legally binding and enforceable and the legal equivalent of
		your handwritten signature.
	</div>
</section>
</body>
</html>