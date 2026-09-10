<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include($_SERVER['DOCUMENT_ROOT']."/about/includes/headerContent.php"); ?>
	<script>
		let regApp = angular.module('regApp', ['easyRegDataModule','erSvc']);
		regApp.controller('regController', function($scope, $http, dataSvc, erSvc){
			$scope.sendMessage  = function(){
				if(!$scope.msgForm.$valid){
					erSvc.easyRegAlert({"text":"Please enter a valid email address","title":"Email Required"});
					return;
				}
				let subject = 'EasyReg Contact Request';
				let body = `
					${$scope.first_name} ${$scope.last_name} has completed the EasyRegPro contact form.
					\n
					Email - ${$scope.email} \n
					${$scope.message || ''} 
				`;
				erSvc.sendEmail('Joe.Miller@psugevents.com', subject, body).then(function(){
					$scope.messageSent = true;
					let txt = `Thank you for your interest in EasyRegPro.  We will be in contact soon.`;
					erSvc.easyRegAlert({"text":txt,"title":"Message Submitted"});
				});
			};
		});
	</script>
</head>
<body ng-app="regApp" ng-controller="regController">
	<?php include($root."/about/includes/nav.html"); ?>
	<section class="darkGreen center" style="padding:2.5em 0px .5em 0px;font-size:16pt">
		<div>Event Management Made Easy!</div>
		<H1 style="font-size:40pt">Contact Us</H1>
		<p style="margin-bottom: 0px;">
			Please fill out the form below if you would like more info or pricing on our EasyRegPro Event Management & Ticketing Management System.
		</p>
	</section>
	<section class="lightGreen center" style="font-size:12pt;padding-top: 10px;">
		<H3 style="margin:0px 0px 1em 0px">Let's Chat</H3>
		<section class="twoCol" style="display: flex;margin-bottom:2em">
			<div>
				Phone<br/>
				810-588-0183
			</div>
			<div>
				Email<br/>
				Joe.Miller@psugevents.com
			</div>
		</section>
		<form name="msgForm">
			<table style="text-align:left;width:80em;margin:auto">
				<tr>
					<td>First Name</td>
					<td>Last Name</td>
					<td>Email *</td>
				</tr>
				<tr>
					<td>
						<input ng-model="first_name" type="text" class="form-control" style="width:20em" />
					</td>
					<td>
						<input ng-model="last_name" type="text" class="form-control" style="width:20em" />
					</td>
					<td>
						<input ng-model="email" type="email" class="form-control" required style="width:20em" />
					</td>
				</tr>
				<tr>
					<td colspan="3">
						Message<br/>
						<textarea ng-model="message" rows="8" cols="70" class="form-control" style="width:70em">
						</textarea>
						<div style="width:70em;margin:1.5em 0px" class="right" ng-hide="messageSent">
							<a class="button" href="#" ng-click="sendMessage()">Send</a>
						</div>
					</td>
				</tr>
			</table>
		</form>
	</section>
	<?php 
		include($root."/about/includes/vision.html"); 
		include($root."/about/includes/footer.html");
	?>
</body>
</html>