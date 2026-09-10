<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PSUGevents.com</title>
	<?php include("common_functions.php");  ?>
	<?php include("commonStyles.php");?>
	<?php include("commonJs.php");?>
	<script type="text/javascript">
		var app = angular.module('regApp', ['easyRegDataModule','erSvc','navMod']);
		app.controller('regController', function($scope, $http, dataSvc, erSvc) {
			$scope.showReg = function(){
				$("#regDiv").dialog({
					title:"Request ACME Plugin Access",
					modal:true,
					width:"700"
				});
			};

			$scope.showLogin = () => {
				$("#loginDiv").dialog({
					title:"Verify Email",
					modal:true,
					width:"700"
				});
			}

			let email = new URL(location).searchParams.get('auth');
			if(email){
				dataSvc.getArray({'query':'acmeCheck','email':email}).then(function(resp){
					$scope.approved = resp[0].total > 0;
				});
			}
			
			$scope.cancelReg = () => $("#regDiv").dialog('close');

			$scope.submitReg = function(){
				erSvc.loadingDialog();
				if(!$scope.regForm.$valid) return;
				dataSvc.createOrUpdateRecord({"table":"acme_access_requests","record":$scope.reg}).then(function(res){
					let body = `${$scope.reg.first_name}  ${$scope.reg.last_name} has requested access to ACME plugins.
						District: ${$scope.reg.district}
						Email: ${$scope.reg.email}`;
					erSvc.sendEmail('joemiller805@gmail.com, emschaitel@gmail.com ', "New ACME Request", body).then(function(){
						$("#regDiv").dialog('close');
						erSvc.closeLoading();
						$scope.showConfirmation = true;
					});
				});
			};

			$scope.cancelLogin = () => $("#loginDiv").dialog('close');
			$scope.submitLogin = () => {
				dataSvc.getArray({'query':'acmeCheck','email':$scope.reg.email})
				.then(resp => {
					$scope.approved = resp[0].total > 0;
					if(!$scope.approved){
						let msg = `An approval for this email address could not be found.
							If you have not previously requested access, please do so now.`;
						erSvc.easyRegAlert({"text":msg,"title":"Email Not Found"});
					}else{
						$("#loginDiv").dialog('close');
						let msg = `Email found. <br/>
							Plugin download enabled.
						`;
						erSvc.easyRegAlert({"text":msg,"title":"Success"});
						erSvc.closeLoading();
					}
					$scope.$applyAsync();
				});
			}
		});// End Controller
	</script>
	<style>
		.row img{
			-webkit-box-shadow: 3px 2px 15px 0px rgba(0,0,0,0.59); 
			box-shadow: 3px 2px 15px 0px rgba(0,0,0,0.59);
			display:inline-block;
			width:auto;
			margin-top: 1em;
		}
		#regDiv input{ margin:4px; }
		.copyright img{ box-shadow: none; }
	</style>
</head>
<body ng-app="regApp" ng-controller="regController">
	<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
		<div class="container" style="width:98%">
			<div class="navbar-header">
				<table>
					<tr>
						<td>
							<a class="navbar-brand" href="/" style="display:inline-block;">
								<img src="/img/account1000//logo.png" 
									style="height:40px; margin-top:-15px; border:none;" 
									alt="Easy Reg Logo">
							</a>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</nav>
	<div class="container-fluid">
		<div class="row" style="padding-left:2em">
			<H1 style="float:left">ACME Plugins for PowerSchool SIS</H1>
			<div ng-show="!approved && !showConfirmation" style="float:right;padding:1em">
				<button class="btn btn-success btn-lg" ng-click="showReg()">
					Request ACME Plugin Access
				</button>
				<button class="btn btn-primary btn-lg" ng-click="showLogin()">
					Sign In With Email
				</button>
			</div>
			<p style="clear:both">
				Every year, a group of the nerdiest PowerSchool nerds from across the globe get together to learn from each other and collaborate to create customizations for the PowerSchool community. Here's what they've done.
			</p>
			<div class='alert alert-success' role='alert' ng-show="showConfirmation"
				style="margin:1em">
				Your request has been submitted to our staff. <br/>
				Please watch your inbox for a message with a link to download the ACME PowerSchool plugins.
			</div>

			<h3>Test Score Display</h3>
			<p>
				Test Score Display enhances the stock "Test Results" screen by showing much more test score detail in one view, without having to drill down into each test.<br/>
				Test results can also be displayed in the teachers and parent/student portals.<br/>
				It replaces the existing "Test Results" link in the student left navigation with a link to a new screen, and includes a tab named "Test Score Entry" that links to the stock Test Results screen.<br/>
				Any	existing stock functionality and customizations for testlist.html prior to installing this plugin should still remain.

			</p>
			<p class="center">
				<img src="/acme/TestScore/Test_results_after.png" style="width:48%"/>
				<img src="/acme/TestScore/TestScoreDisplaySetup.png" style="width:48%"/>
			</p>
			
			<p style="margin-top:1em">
				<a class="btn btn-primary" target="_blank" href="/acme/TestScore/ACME_Test_Score_Display_23.11.10_ReadMe.pdf">
					Documentation
				</a>
				<a class="btn btn-primary" ng-show="approved"
					href="/acme/TestScore/ACME Test Score Display 25.7.31.zip">
					<span class="bi bi-download"></span>
					Download Test Score Display Plugin
				</a>
				<p>Current Release - 25.7.31 </p>
			</p>

			<hr/>
			<h3>ACME Tools</h3>
			<p>
				PowerSchool administrators often struggle with understanding complex system configurations and analyzing detailed administrative data across their environment. ACME Tools provides comprehensive visibility into security configurations, user permissions, plugin management, and system analysis that are otherwise difficult to access or analyze through standard PowerSchool interfaces.
			</p>
			<p class="center">
				<img src="/acme/ACMETools/ACMEToolsFieldSecuritybyRole.png" style="width:48%"/>
				<img src="/acme/ACMETools/ACMEToolsFieldSecurityGrid.png" style="width:48%"/><br/>
				<img src="/acme/ACMETools/ACMEToolsFieldSecuritybyRole.png" style="width:48%"/>
				<img src="/acme/ACMETools/ACMEToolsPluginSecurityReport.png" style="width:48%"/><br/>
				<img src="/acme/ACMETools/ACMEToolsUserRoleAudit.png" style="width:48%"/>
			</p>

			<p style="margin-top:1em">
				<a class="btn btn-primary" target="_blank" href="/acme/ACMETools/doc.html">
					Documentation
				</a>
				<a class="btn btn-primary" href="/acme/ACMETools/ACME Tools 26.5.3.1.zip" 
					ng-show="approved">
					<span class="bi bi-download"></span>
					Download ACME Tools
				</a>
				<p>Current Release - 26.5.3.1</p>
			</p>

			<!-- <hr/>
			<h3>ACME Custom Selections</h3>
			<p>
				ACME Custom Selections adds reusable custom student selection queries to the PowerSchool admin home page. Staff can use these queries to replace the current student selection or add matching students to it.
			</p>

			<p style="margin-top:1em">
				<a class="btn btn-primary" target="_blank" href="/acme/customSelection/filename.pdf">
					Documentation
				</a>
				<a class="btn btn-primary" href="/acme/customSelection/filename.zip" 
					ng-show="approved">
					<span class="bi bi-download"></span>
					Download Custom Selections
				</a>
			</p> -->

			<hr/>
			<h3>PTP Assignment Recover Tool</h3>
			<p>
				<p>Recover assignment score data from temporary database snapshots.</p>

				<strong>What This Tool Can Recover</strong>

				<p>Assignment Recovery can compare current gradebook data against historical snapshots and restore available student-level assignment data, including:</p>
				<ul style="margin-left:2em">
					<li>Scores</li>
					<li>Score flags, including Missing, Late, Incomplete, and Absent</li>
					<li>Status values, including Collected and Exempt</li>
					<li>Score comments</li>
				</ul>
				<br/>
				
				<p>This tool does not recreate deleted assignments. Deleted assignment details can be reviewed from the historical snapshot, but the assignment itself must exist before student score data can be restored.</p></br>

				<strong>Common Questions & Limitations</strong><br><br>

				<strong>How far back can I see recovery points?</strong>

				<p>Recovery history depends on database flashback retention, database activity, and storage limits. More active gradebooks may have shorter usable recovery windows.</p>
				<hr>

				<strong>Why do some records appear at one timeline point but not another?</strong>

				<p>Flashback snapshots are time-sensitive. A score, flag, status, comment, or assignment record may only exist in certain snapshots. If the expected data is missing, check nearby timeline points before and after the selected time.</p>
				<hr>

				<strong>Can I restore an entire deleted assignment?</strong>

				<p>No. Deleted assignment details can be reviewed from the historical snapshot, but this tool restores student-level score data only when the assignment exists in the current gradebook.</p>
				<hr>

				<strong>Can I restore scores, flags, status, and comments?</strong>

				<p>Yes, when the historical record is available and the assignment exists in the current gradebook. Review the historical and current values carefully before selecting Restore.</p>
				<hr>

				<strong>What if I do not see any recovery points?</strong>

				<p>No matching database snapshots may be available for the selected section, or the recovery window may have expired. Try another section or contact your system administrator.</p>
				<hr>

				<strong>Important:</strong> Recovery depends on temporary database flashback data. Records are not guaranteed to exist at every timeline point.
			</p>
			<p class="center">
				<img src="/acme/PTPrecovery/ptp1.png" style="width:60%"/>
			</p>

			<p style="margin-top:1em">
				<a class="btn btn-primary" target="_blank" href="/acme/PTPrecovery/filename.pdf">
					Documentation
				</a>
				<a class="btn btn-primary" href="/acme/PTPrecovery/ACME PTP Assignment Recovery Tool_26.05.03.0_260503135223.zip"
					ng-show="approved">
					<span class="bi bi-download"></span>
					Download PTP Assignment Recovery Tool
				</a>
				<p>Current Release - 26.05.03.0</p>
			</p>

			<hr/>
			<h3>Python PowerSchool Module</h3>
			<p>
				The ACME PowerSchool module provides two interfaces for interacting with a PowerSchool instance: the REST API and an Oracle ODBC connection.
			</p>

			<p style="margin-top:1em">
				<a class="btn btn-primary" target="_blank" href="/acme/pythonAPI/README.html">
					Documentation
				</a>
				<a class="btn btn-primary" href="/acme/pythonAPI/acme_powerschool-python.zip" 
					ng-show="approved">
					<span class="bi bi-download"></span>
					Download Python PowerSchool Module
				</a>
			</p>

			<hr/>
			<h3>Extracurricular Activities</h3>
			<p>
				The Extracurricular Activities Plugin provides school districts with a way to track student activities and keep a history of activity participation. 
			</p>
			<p class="center">
				<img src="/acme/activities/acme1.png" style="width:48%"/>
				<img src="/acme/activities/acme2.png" style="width:48%"/>
			</p>
			<p style="margin-top:1em">
				<a class="btn btn-primary" target="_blank" href="/acme/activities/Activities User Guide.pdf">
					Documentation
				</a>
				<a class="btn btn-primary" href="/acme/activities/ACME Activities Schema (1).zip" 
					ng-show="approved">
					<span class="bi bi-download"></span>
					Extracurricular Activities Plugin 1 (Schema)
				</a>
				<a class="btn btn-primary" href="/acme/activities/ACME Activities 25.7.23.zip"
					ng-show="approved">
					<span class="bi bi-download"></span>
					Extracurricular Activities Plugin 2 (Core)
				</a>
				<p>Current Release - 25.7.23</p>
			</p>
		</div>
	</div>
	<div style="display:none">
		<div id="regDiv">
			<p>
				<b>We would like ensure that those who download our plugins are 
				legitimate users from school districts or those involved with organizations
				that support school districts. </b><br/><br/>
				Please provide your information below. <br/> 
				Your information will be reviewed by a member of our team.  </br>
				If approved, you will receive an email with a link to download the ACME plugin files.
			</p>
			<form name="regForm">
				<table>
					<tr>
						<td class="bold">Name</td>
						<td>
							<input type="text" class="form-control" ng-model="reg.last_name" 
								style="display:inline-block;width:15em" placeholder="Last Name" 
								required />
							<input type="text" class="form-control" ng-model="reg.first_name" 
								style="display:inline-block;width:15em" placeholder="First Name" 
								required />
						</td>
					</tr>
					<tr>
						<td class="bold">School District</td>
						<td>
							<input type="text" class="form-control" ng-model="reg.district" 
								placeholder="District / Organization" required/>
						</td>
					</tr>
					<tr>
						<td class="bold">Email</td>
						<td>
							<input type="email" class="form-control" ng-model="reg.email" placeholder="Email" 
								required />
						</td>
					</tr>
				</table>
				<div class="button-row">
					<button class="btn btn-danger" ng-click="cancelReg()">Cancel</button>
					<button class="btn btn-primary" ng-click="submitReg()">Submit</button>
				</div>
			</form>
		</div>
	</div>

	<div style="display:none">
		<div id="loginDiv">
			<form name="loginForm">
				<table>
					<tr>
						<td class="bold">Email</td>
						<td>
							<input type="email" class="form-control" ng-model="reg.email" placeholder="Email" required />
						</td>
					</tr>
				</table>
				<div class="button-row">
					<button class="btn btn-danger" ng-click="cancelLogin()">Cancel</button>
					<button class="btn btn-primary" ng-click="submitLogin()">Submit</button>
				</div>
			</form>
		</div>
	</div>
	<er-Footer />
</body>
</html>
