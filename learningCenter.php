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
<style>
	.videoDiv .header-bg, .videoDiv .a-name, div.videoDiv H4{
		background-color: #346a6a;
	}
	.messageDiv{ white-space: pre-wrap;	}
</style>
<title>PSUGevents.com</title> 
<?php include("common_functions.php");?> 
<?php include("commonStyles.php");?> 
<?php include("commonJs.php");?> 
<script type="text/javascript">
	let app = angular.module('regApp', ['easyRegDataModule', 'erSvc', 'navMod']);
	app.controller('regController', function($scope, $http, $filter, $q, $timeout, dataSvc, erSvc){
		$(document).dblclick(() => console.log($scope));
		$scope.view = 'browse';
		$scope.categories = [];
		$scope.cart = [];
		$scope.searchValue = '';
		$scope.videoReviews = {};
		$scope.packageReviews = {};
		$scope.discount = 0;
		$scope.states = erSvc.getStateOptions();
		$scope.months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
		let cardYr = new Date().getFullYear();
		$scope.years = [cardYr];
		let videoMap = {};
		for (let i = 1; i < 10; i++){
			$scope.years.push(cardYr + i);
		}
		let videosRetrieved = $q.defer();
		let packagesRetrieved = $q.defer();
		let reviewsRetrieved = $q.defer();
		let accountid;
		let trialAcct;

		erSvc.getAccountIdFromURL().then(function(res){
			dataSvc.getArray({'query': 'accountInfo'}).then(function(resp){
				$scope.acctName = resp[0].name;
				$scope.accountLogo = resp[0].web_logo;
				accountid = resp[0].id;
				$scope.accountid = accountid;
				trialAcct = resp[0].trial == '1';
				prepPageData();
			});
		});
		let reviewData;		

		function prepPageData(){
			dataSvc.getArray({'query': 'account_videos_on_demand'}).then(function(videos){
				$scope.videos = videos;
				let usedCategories = [];
				$scope.videos.forEach(function(v){
					videoMap[v.id] = v;
					v.searchString = erSvc.getObjectSearchString(v);
					v.display_name = v.display_name || v.name;
					v.name = v.name.replace('.mp4', '');
					v.documents = [];
					if (!usedCategories.includes(v.category)) usedCategories.push(v.category);
				});
				videosRetrieved.resolve();
				dataSvc.getArray({'query': 'video_categories'}).then(function(resp){
					try {
						angular.fromJson(resp[0].value).forEach(function(cat){
							if (usedCategories.includes(cat.val)) $scope.categories.push(cat.val);
						});
					} catch {
						$scope.categories = usedCategories;
					}
					$scope.categories = $scope.categories.reduce((acc, cur) => {
						let lastIdx = acc.length - 1;
						if (!acc[lastIdx]) acc.push([]);
						lastIdx = acc.length - 1;
						if (acc[lastIdx].length < 10) acc[lastIdx].push(cur);
						else acc.push([cur])
						return acc;
					}, [])
				});
				dataSvc.getArray({'query': 'videos_on_demand_documents'}).then(function(docs){
					docs.forEach(function(doc){
						doc.name = $filter('nameFromFilepath')(doc.filepath);
						$scope.videos.forEach(function(video){
							if (video.id == doc.videoid) video.documents.push(doc);
						});
					});
				});
			});
			dataSvc.getArray({'query': 'account_video_packages'}).then(function(packages){
				$scope.packages = packages;
				packagesRetrieved.resolve();
			});
			dataSvc.getVideoReviews().then(function(reviews){
				reviewData = reviews;
				reviewsRetrieved.resolve();
			});

			dataSvc.getPreferenceByName('learningCenterMsg', accountid).then(function(res){
				if(res[0]) $scope.learningCenterMsg = res[0].value;
			});

			$scope.discounts = [];
			dataSvc.getPreferenceByName('learningCenterDiscounts', accountid).then(function(res){
				if(res[0]){
					try{
						$scope.discounts = angular.fromJson(res[0].value);
					}catch(e){
						$scope.discounts = [];
					}
				}
			});

			$scope.paymentMethod = 'cc';
			dataSvc.getPreferenceByName('learningCenterPymts', accountid).then(function(res){
				if(res[0]){
					try{
						let settings = angular.fromJson(res[0].value);
						$scope.poEnabled = settings.poEnabled;
						$scope.checkEnabled = settings.checkEnabled;
						$scope.poInstructions = settings.poInstructions;
						$scope.checkInstructions = settings.checkInstructions;
					}catch(e){}
				}
				$scope.ccEnabled = false;
				dataSvc.getArray({'query':'ccEnabled'}).then(function(resp){
					if(resp[0] && resp[0].cc_enabled == '1') $scope.ccEnabled = true;
					else if($scope.poEnabled) $scope.paymentMethod = 'po';
					else if($scope.checkEnabled) $scope.paymentMethod = 'check';
				});
			});

			dataSvc.getPreferenceByName('learningCenterInvoiceMsg', accountid)
			.then(function(res){
				if(res[0]) $scope.invoiceMessage = res[0].value;
			});
		} // end prepPageData()

		$q.all([videosRetrieved.promise, packagesRetrieved.promise, reviewsRetrieved.promise]).then(function(){
			//integrate reviews with packages and videos
			$scope.packages.forEach(function(pkg){
				pkg.reviewData = reviewData.packages[pkg.id] || {
					"averageRating": 0,
					"reviewList": []
				};
			});
			$scope.videos.forEach(function(vid){
				vid.reviewData = reviewData.videos[vid.id] || {
					"averageRating": 0,
					"reviewList": []
				};
			});
			//integrate videos with packages
			dataSvc.getArray({'query': 'account_video_package_details'}).then(function(details){
				let pkgSortMap = {};
				details.forEach(function(detail){
					pkgSortMap[detail.video_packagesid + ',' + detail.videoid] = Number(detail.sortorder);
				});
				$scope.packages.forEach(function(pkg){
					pkg.totalLength = pkg.totalLength || 0;
					pkg.videos = [];
					(pkg.videoids || '').split(',').forEach(function(id){
						if (videoMap[id]){
							pkg.videos.push({
								...videoMap[id],
								sortorder: pkgSortMap[pkg.id + ',' + id]
							});
							pkg.totalLength += parseFloat(videoMap[id].length);
						}
					});
					pkg.totalLength = $scope.minutesToHours(pkg.totalLength);
				});
				$scope.selectCategory('all');
			});
		}); //End videos and packages retrieved

		//get hours and minutes total from minutes
		$scope.minutesToHours = function(min){
			let result = "";
			let hours = Math.floor(min / 60);
			let minutes = min % 60;
			result = hours ? hours : '';
			if (hours == 1) result += ' Hour '
			else if (hours > 1) result += ' Hours ';
			if (minutes == 1) result += '1 Minute'
			else if (minutes > 1) result += (minutes + ' Minutes');
			return result;
		};

		//get days and hours total from hours
		$scope.hoursToDays = function(hrs){
			if (hrs == 0) return 'Permanent';
			if (hrs == -1) return 'Never';
			let result = "";
			let days = Math.floor(hrs / 24);
			let hours = hrs % 24;
			result = days ? days : '';
			if (days == 1) result += ' Day '
			else if (days > 1) result += ' Days ';
			if (hours == 1) result += '1 Hour'
			else if (hours > 1) result += (hours + ' Hours');
			return result;
		};

		$scope.$watch('view',function(){
			if($scope.view != 'browse'){
				if(!$scope.account){
					let msg = "Please log in or create an account to continue.";
					erSvc.easyRegAlert({"text":msg,"title":"Please Log In"});
					$scope.view = 'browse';
					return;
				}
				if(!$scope.termsAgreed){
					showTerms();
					$scope.view = 'browse';
					return;
				}
				$scope.calculateDiscount();
			} 
		$scope.paymentSuccess = false
		});

		function postUserDml(params){
			return $http({
				"url": '/data_access/runUserDML.php',
				"method": 'POST',
				"data": $.param(params || {}),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
			});
		}

		$scope.selectCategory = function(cat){
			$scope.searchValue = '';
			$scope.selectedCategory = cat;
			$scope.visibleVideos = $scope.videos.filter(v => (v.category == cat) || cat == 'all');
		};

		$scope.purchaseVideo = function(video){
			$scope.selectedVideo = video;
			setTimeout(function(){
				$('#selectedVideoDiv').dialog({
					modal: true,
					title: video.display_name,
					width: "40%",
					height: 'auto',
					show: 'fade'
				});
			}, 30);
		};

		$scope.addToCart = function(){
			if (!$scope.cart.includes($scope.selectedVideo)) $scope.cart.push($scope.selectedVideo);
			$('#selectedVideoDiv').dialog('close')
		};

		$scope.removeFromCart = function(video){
			video = video || $scope.selectedVideo;
			if ($scope.cart.includes(video)) $scope.cart.splice($scope.cart.indexOf(video), 1);
			$('#selectedVideoDiv').dialog('close');
			if ($scope.cart.length == 0) $scope.view = 'browse';
			$scope.calculateDiscount();
		};

		$scope.addPkgToCart = function(pkg){
			if (!$scope.cart.includes(pkg)) $scope.cart.push(pkg);
		};

		$scope.showLogin = () => $('#loginDiv').dialog({modal: true,title: "Log In"});

		$scope.termsAgreed = false;
		$scope.submitLogin = function(){
			checkCredentials($scope.login.email, $scope.login.password).then(function(att){
				if (!att){
					$scope.loginError = true;
				}else{
					$scope.termsAgreed = att.terms_agreed == '1' || accountid != '1000';
					$scope.account = att;
					$(".ui-dialog-content").dialog("close");
					if(!$scope.termsAgreed)	showTerms();
				}
			});
		};

		$scope.termsAgree = function(){
			let data = {"query":"setAttendeeTermsAgreed", "attendeeid":$scope.account.id};
			postUserDml(data);
			$('#termsDiv').dialog('close');
		};

		checkCredentials = function(email, pw){
			if (!pw) return;
			let attendee = $.Deferred();
			dataSvc.getArray({ // Plaintext; getQueryResults runs tep_password_verify
					'query': 'checkAttendeeCredentials',
					'email': email,
					'password': pw
				}).then(function(resp){
					let att = resp[0];
					attendee.resolve(att);
					if (att){
						$http({
							"url": '/login_attendee.php',
							"method": 'POST',
							"data": $.param({"email": email,"pass": pw}),
							"headers": {"Content-Type": "application/x-www-form-urlencoded"}
						});
						if($scope.termsAgreed){
							erSvc.easyRegAlert({
								"text": "Login Successful",
								"title": `Welcome ${att.first_name}`
							});
						}else{
							showTerms();
						}
					}
				});
			return attendee;
		};	

		function showTerms(){
			$('#termsDiv').dialog({
				modal:true,
				title:"Terms of Use & Privacy Policy",
				width:'auto'
			});
		}	

		$scope.calculateDiscount = function(){
			$scope.discount = 0;
			let total = 0;
			$scope.cart.forEach((video) => total += Number(video.price));
			if($scope.discountCode){
				let cd = $scope.discountCode;
				let discount = 0;
				if(cd.method == 'percent'){
					discount = (Number(cd.discount)/100) * total;
					discount = Math.round(discount * 100) / 100;
				}else{
					discount = Number(cd.discount);
					if(discount > total) discount = total;
				}
				$scope.discount = discount;
				return;
			}
			$scope.discounts.forEach(function(threshold){
				if(threshold.val < total){
					let discount = 0;
					if(threshold.method == 'percent'){
						discount = (threshold.discount/100) * total;
						discount = Math.round(discount * 100) / 100;
					}else{
						discount = Number(threshold.discount);
					}
					if(discount > $scope.discount) $scope.discount = discount;
				}
			});			
		};

		$scope.cancelLogin = () => $('#loginDiv').dialog('close');

		$scope.showSignup = function(){
			$('#signupDiv').dialog({modal: true,title: "Create Account",width:"auto"});
			$timeout(() => $scope.signup = {}, 500);
		};

		$scope.verifyEmail = function(){
			$scope.emailNotFound = false;
			dataSvc.getArray({'query': 'checkAttendeeCredentials','email': $scope.login.email}).then(function(res){
				if (res[0]) $scope.emailVerified = true;
				else $scope.emailNotFound = true;
			});
		};

		$scope.cancelSignup = () => $('#signupDiv').dialog('close');

		$scope.checkAttendees = function(){
			let acct = $scope.signup;
			dataSvc.getAttendeeMatch(acct.email, acct.last_name, acct.first_name).then(function(res){
				if (res.length){
					$scope.attendeeMatches = res;
					setTimeout(function(){
						$('#attendeeSelector').dialog({
							modal: true,
							title: "Potential Match Found",
							width: "50%"
						});
					}, 20);
				}
			});
		};

		$scope.logOut = () => {
			$scope.account = null;
			$scope.cart = [];
			$scope.view = 'browse';
			erSvc.easyRegAlert({
				"text": "You have been logged out",
				"title": "Logged Out"
			});
		};

		$scope.selectAttendee = function(attendee){
			if (attendee.password){
				$('#signupDiv').dialog('close');
				let msg = `Password for ${attendee.email}`
				erSvc.easyRegFeedback("Password", msg, 'Submit', 'Cancel').then(function(res){
					checkCredentials(attendee.email, res).then(function(attendee){
						if (!attendee){
							$scope.loginError = true;
						}else{
							$scope.account = attendee;
							$('#attendeeSelector').dialog('close');
						}
					});
				});
			}else{
				$('#attendeeSelector, #signupDiv').dialog('close');
				let title = `Create Password - ${attendee.email}`;
				let msg = `
					You already have an account with us but haven't used this 
					account to access videos on-demand. <br/><br/>
					Please enter a password for future on-demand video access.<br/>
				`;
				erSvc.easyRegFeedback(title, msg, 'Submit', 'Cancel').then(function(res){
					erSvc.encrypt(res).then(function(encrypted){
						var rec = {
							"query": "updateAttendePw",
							"id": attendee.id,
							"password": encrypted
						};
						postUserDml(rec);
						$scope.account = attendee;
						setTimeout(() => { $('#attendeeSelector').dialog('close'); }, 30);
					});
				});
			}
		}; //End selectAttendee()

		$scope.attendeeNotFound = () => $('#attendeeSelector').dialog('close');

		$scope.enterDiscountCd = function(){
			erSvc.easyRegFeedback("Discount Code", "Code", "Submit", "Cancel").then(function(res){
				if(!res) return;
				let params = {
					'query':'discountCodeDetails',
					'code':res,
					'attendeeid':$scope.account.id,
					'accountid': accountid
				};
				dataSvc.getArray(params).then(function(code){
					if(!code[0]){
						erSvc.easyRegAlert({"text":"Code Not Found","title":"Invalid Code"});
						return;
					}else{
						$scope.discountCode = {
							'discount':Number(code[0].discount),
							'method':code[0].method,
							'code':res
						};
						$scope.calculateDiscount();
					}
				});
			});
		}

		$scope.cartTotal = function(){
			let total = 0;
			$scope.cart.forEach((video) => total += Number(video.price));
			return total - $scope.discount;
		};

		$scope.createAcct = function(){
			if (!$scope.signupForm.$valid){
				$('form[name="signupForm"]').addClass('submitted');
				return;
			}
			erSvc.encrypt($scope.signup.password).then(function(pw){
				let newAttendee = angular.copy($scope.signup);
				newAttendee.password = pw;
				dataSvc.createVideoAttendee(newAttendee).then(function(res){
					if (res.status == 'success'){
						newAttendee.id = res.insertid;
						$scope.account = newAttendee;
						$http({
							"url": '/login_attendee.php',
							"method": 'POST',
							"data": $.param({"email": $scope.signup.email,"pass": $scope.signup.password}),
							"headers": {"Content-Type": "application/x-www-form-urlencoded"}
						});
						showTerms();
					}else{
						let txt = "Error creating account"
						erSvc.easyRegAlert({
							"text": txt,
							"title": "Error"
						});
					}
					$('#signupDiv').dialog('close');
				});
			});
		};

		let order = {};
		$scope.submitOrder = function(){
			if(trialAcct){
				erSvc.easyRegAlert({
					"text":"Video orders may not be completed for trial accounts",
					"title":"Trial Account"
				});
				return;
			}
			$('form[name="paymentForm"]').addClass('submitted');
			if (!$scope.paymentForm.$valid && $scope.paymentMethod == 'cc') return;
			$scope.purchasedAmt =  $scope.cartTotal();
			let cd = ($scope.discountCode && $scope.discountCode.code) ? $scope.discountCode.code : '';
			order = {
				"attendeeid": $scope.account.id,
				"total": $scope.cartTotal(),
				"paid": $scope.paymentMethod == 'cc' ? $scope.cartTotal() : '0',
				"discount": $scope.discount,
				"discount_code": cd
			};
			postUserDml({"query": "createVideoOrder", ...order}).then(function(res){
				if (res.data.status != 'success'){
					erSvc.easyRegAlert({
						"text": "An error was encountered while attempting to create the order.",
						"title": "Error Creating Order"
					});
					return;
				}
				order.id = res.data.insertid;
				if($scope.paymentMethod == 'cc'){
					$scope.submitPayment();
				}else{
					$scope.discount = 0;
					$scope.paymentSuccess = true;
					createOrderDetails();
					let orderUpdate = {
						"query": "setOrderPaymentNumber",
						"payment_number": $scope.pymtNumber,
						"orderid": order.id
					}
					postUserDml(orderUpdate).then(function(){
						updateAttendeeInfo();
						erSvc.closeLoading();
					});
					$scope.cart = [];
					$scope.discount = 0;
				}
			});
		};

		$scope.submitPayment = function(){
			erSvc.loadingDialog("Processing Payment");
			var paymentInfo = {
				"name": $scope.card.name,
				"email": $scope.card.email,
				"street": $scope.card.street,
				"city": $scope.card.city,
				"state": $scope.card.state,
				"zip": $scope.card.zip,
				"amount": Number($scope.cartTotal()).toFixed(2),
				"cardNumber": $scope.card.number,
				"cvv": $scope.card.cvv,
				"expiration": $scope.card.expMonth + $scope.card.expYear.toString().substr(-2),
				"accountid": accountid,
				"regId": $scope.account.id,
				"regConfirmation": order.id
			};
			$http({
				"url": '/process_payment.php',
				"method": 'POST',
				"data": $.param(paymentInfo),
				"headers": {"Content-Type": "application/x-www-form-urlencoded"}
			}).then(function(response){
				try {
					var paymentInfo = response.data.paymentDetails.PaymentInfo;
					var authInfo = response.data.paymentDetails.AuthInfo;
					if (paymentInfo['@attributes'].ErrorCode == 0){
						$scope.paymentSuccess = true;
						$scope.paymentConfirmation = paymentInfo['@attributes'].ConfirmationNumber;
						createOrderDetails();
						let orderUpdate = {
							"query": "setOrderPaymentNumber",
							"payment_number": $scope.paymentConfirmation,
							"orderid": order.id
						}
						postUserDml(orderUpdate);
						updateAttendeeInfo();
						$scope.cart = [];
						erSvc.closeLoading();
						$scope.discount = 0;
					}else{
						$scope.paymentError = true;
						deleteOrder();
						erSvc.closeLoading();
						$scope.authMessage = authInfo['@attributes'].Message;
						$scope.paymentMessage = paymentInfo['@attributes'].PaymentMessage;
					}
				}catch(e){
					console.error(e)
					$scope.paymentError = true;
					deleteOrder();
					erSvc.closeLoading();
				}
			});
		}; // End submitPayment()

		$scope.submitInvoicePayment = function(){
			erSvc.loadingDialog("Processing Payment");
			var paymentInfo = {
				"name": $scope.card.name,
				"email": $scope.card.email,
				"street": $scope.card.street,
				"city": $scope.card.city,
				"state": $scope.card.state,
				"zip": $scope.card.zip,
				"amount": Number($scope.invoice.due).toFixed(2),
				"cardNumber": $scope.card.number,
				"cvv": $scope.card.cvv,
				"expiration": $scope.card.expMonth + $scope.card.expYear.toString().substr(-2),
				"accountid": accountid,
				"regId": $scope.account.id,
				"regConfirmation": $scope.account.id
			};

			$http({
				"url": '/process_payment.php',
				"method": 'POST',
				"data": $.param(paymentInfo),
				"headers": {"Content-Type": "application/x-www-form-urlencoded"}
			}).then(function(response){
				try {
					var paymentInfo = response.data.paymentDetails.PaymentInfo;
					var authInfo = response.data.paymentDetails.AuthInfo;
					if (paymentInfo['@attributes'].ErrorCode == 0){
						$scope.paymentSuccess = true;
						$scope.paymentConfirmation = paymentInfo['@attributes'].ConfirmationNumber;
						let ordersToUpdate = 0;
						let ordersUpdated = 0;
						$scope.attendeeOrders.forEach(function(order){
							if(order.due <= 0) return;
							ordersToUpdate++;
							let orderUpdate = {
								"query": "payVideoOrder",
								"payment_number": $scope.paymentConfirmation,
								"orderid": order.id,
								"paid": order.due
							}
							postUserDml(orderUpdate).then(function(r){
								if(++ordersUpdated == ordersToUpdate){
									updateAttendeeInfo();
									erSvc.closeLoading();
								}
							});
						});
					}else{
						$scope.paymentError = true;
						erSvc.closeLoading();
						$scope.authMessage = authInfo['@attributes'].Message;
						$scope.paymentMessage = paymentInfo['@attributes'].PaymentMessage;
					}
				}catch(e){
					console.error(e)
					$scope.paymentError = true;
					erSvc.closeLoading();
				}
			});
		}; // end submitInvoicePayment()

		function createOrderDetails(){
			let recordsWritten = 0;
			$scope.cart.forEach(function(item){
				let orderDetail = {	"video_ordersid": order.id };
				if (item.instructor){
					orderDetail.query = "createVideoOrderDetail";
					orderDetail.videoid = item.id;
				}else{
					orderDetail.query = "createVideoPkgOrderDetail";
					orderDetail.video_packagesid = item.id;
				}
				postUserDml(orderDetail).then(function(res){
					if (++recordsWritten == $scope.cart.length) erSvc.closeLoading();
				});
			});
		}

		$scope.searchVideos = function(){
			if (!$scope.searchValue){
				$scope.selectCategory($scope.selectedCategory);
				return;
			}
			let searchVal = $scope.searchValue.toLowerCase()
			$scope.visibleVideos = $scope.videos.filter(v => v.searchString.indexOf(searchVal) >= 0);
			$scope.searchValue = '';
		};

		//Reset forgotten password
		$scope.resetPassword = function(){
			let text = `If you choose to continue, an email containing a new password 
				will be sent to ${$scope.login.email}`;
			let title = "Confirm Password Reset";
			erSvc.easyRegConfirm({
				"text": text,
				"title": title
			}, "Reset Password", "Cancel").then(function(res){
				if (!res) return;
				$http({
					"url": '/reset_attendee_password.php',
					"method": 'POST',
					"data": $.param({"email": $scope.login.email}),
					"headers": {
						"Content-Type": "application/x-www-form-urlencoded"
					}
				}).then(function(response){
					$('#loginDiv').dialog('close');
					let txt = `Your password has been reset. Please check your inbox for your new password.`
					erSvc.easyRegAlert({
						"text": txt,
						"title": "Password Reset Successful"
					});
				});
			});
		};

		function deleteOrder(){
			let submitData = {
				"query": "deleteVideoOrder",
				"orderid": order.id
			};
			postUserDml(submitData);
		}

		$scope.accountInitials = function(){
			if(!$scope.account) return;
			if (!$scope.account.first_name || !$scope.account.last_name) return;
			return $scope.account.first_name.substr(0, 1) + $scope.account.last_name.substr(0, 1);
		};

		$scope.launchVideo = function(video){
			$scope.launchedVideo = video;
			$('#videoFrame').attr('src', "/" + video.filepath);
		};

		$scope.closeVideo = function(){
			$scope.launchedVideo = null;
			$('#videoFrame').attr('src', '');
		}
		$scope.$watch('account', updateAttendeeInfo);

		function updateAttendeeInfo(){
			if (!$scope.account){
				$scope.purchasedVideos = [];
				$scope.purchasedPackages = [];
				$scope.attendeeOrders = [];
				return;
			}
			let videosDone = false;
			let packagesDone = false;
			dataSvc.getArray({'query': 'attendeeAvailableVideos'}).then(function(resp){
				$scope.purchasedVideos = resp;
				$scope.purchasedVideos.forEach(v => v.display_name = v.display_name || v.name);
				videosDone = true;
				if (packagesDone) associateVideoDocuments();
			});
			dataSvc.getArray({'query': 'attendeeAvailablePackages'}).then(function(resp){
				resp.forEach(function(pkg){
					$scope.packages.forEach(p => {
						if (p.id == pkg.id){
							$scope.purchasedPackages.push(p);
							p.hoursRemaining = pkg.hoursRemaining;
						}
					});
				});
				packagesDone = true;
				if (videosDone) associateVideoDocuments();
			});
			dataSvc.getArray({'query': 'attendeeVideoOrders'}).then(function(resp){
				$scope.invoice = {"total":0,"discount":0,"paid":0,"due":0};
				$scope.attendeeOrders = resp;
				$scope.attendeeOrders.forEach(function(order){
					order.orderDate = erSvc.mySqlToLocalDate(order.created_time);
					order.total = Number(order.total) + Number(order.discount);
					order.discount = Number(order.discount);
					order.paid = Number(order.paid);
					order.due = order.total - order.discount - order.paid;

					$scope.invoice.total += order.total;
					$scope.invoice.discount += order.discount;
					$scope.invoice.paid += order.paid;
					$scope.invoice.due += order.due;
				});
			});

			getAttendeeReviews();

			let params = {
				'query':'discountCodesAvailable',
				'attendeeid':$scope.account.id,
				'accountid':accountid
			};
			dataSvc.getArray(params).then(r => $scope.discountCodesAvailable = r[0].count > 0);
		}

		function getAttendeeReviews(){
			dataSvc.getArray({'query': 'attendeeReviews'}).then(function(res){
				res.forEach(function(r){
					if (r.videoid) $scope.videoReviews[r.videoid] = r;
					else if (r.video_packagesid) $scope.packageReviews[r.video_packagesid] = r;
				});
				setTimeout(() => {$scope.$apply();}, 399);
			});
		}

		function associateVideoDocuments(){
			$scope.hasVideoDocs = false;
			$scope.purchasedVideos.forEach(function(video){
				let matchingVideo = $scope.videos.filter(v => v.id == video.id)[0];
				if (matchingVideo){
					video.documents = matchingVideo.documents;
					if (matchingVideo.documents.length) $scope.hasVideoDocs = true;
				}
			});
		}

		$scope.openProfileMgmt = function(){
			$scope.profile = angular.copy($scope.account);
			$('#manageProfileDialog').show(500);
		};

		$scope.updateProfile = function(){
			if (!$scope.profileForm.$valid){
				$('form[name="profileForm"]').addClass('submitted');
				return;
			}
			let rec = {
				"query":"updateAttendee",
				"id":$scope.profile.id,
				"email":$scope.profile.email,
				"first_name":$scope.profile.first_name,
				"last_name":$scope.profile.last_name,
				"business":$scope.profile.business,
				"title":$scope.profile.title,
				"address1":$scope.profile.address1,
				"address2":$scope.profile.address2,
				"city":$scope.profile.city,
				"state":$scope.profile.state,
				"zip":$scope.profile.zip
			};
			postUserDml(rec).then(function(res){
				if(res.data.status == 'success'){
					$scope.closeRightDialog();
					$scope.account = angular.copy($scope.profile);
					erSvc.easyRegAlert({"text":"Your profile has been Updated","title":"Profile Updated"});
				}
			});
		};

		$scope.changePassword = function(){
			$scope.newPw = $scope.currentPw = $scope.confirmPw = '';
			$scope.invalidCurrentPw = false;
			$('#changePwDiv').dialog({
				modal: true,
				title: "Change Password",
				width: "30em",
				height: 'auto',
				show: 'fade'
			});
		};

		$scope.cancelPwChange = () => $('#changePwDiv').dialog('close');
		$scope.enablePwChange = () => $scope.currentPw && $scope.newPw && $scope.confirmPw;

		$scope.submitPwChange = function(){
			if ($scope.newPw != $scope.confirmPw){
				$scope.passwordMismatch = true;
				return;
			}
			erSvc.loadingDialog();
			dataSvc.getArray({ // Plaintext; getQueryResults runs tep_password_verify
					'query': 'checkAttendeeCredentials',
					'email': $scope.account.email,
					'password': $scope.currentPw
				}).then(function(resp){
					if (resp.length == 0){
						$scope.invalidCurrentPw = true;
						erSvc.closeLoading();
						return;
					}
					erSvc.encrypt($scope.newPw).then(function(newEncrypted){
						var rec = {
							"query": "resetAttendeePw",
							"password": newEncrypted
						};
						postUserDml(rec);
						setTimeout(function(){
							erSvc.closeLoading();
							$('#changePwDiv').dialog('close');
							let txt = "Your password has been updated."
							erSvc.easyRegAlert({
								"text": txt,
								"title": "Password Updated"
							});
						}, 30);
					});
				});
		}; // End submitPwChange()

		$scope.closeRightDialog = () => $('.dialogRight').hide(500);

		/******* Survey Functions *******/
		$scope.provideVidoeFeedback = function(video, package){
			$scope.reviewItem = {};
			if (video && $scope.videoReviews[video.id]){
				$scope.reviewItem.rating = $scope.videoReviews[video.id].rating;
				$scope.reviewItem.comment = $scope.videoReviews[video.id].comment;
				$scope.reviewItem.reviewid = $scope.videoReviews[video.id].id;
			} else if (package && $scope.packageReviews[package.id]){
				$scope.reviewItem.rating = $scope.packageReviews[package.id].rating;
				$scope.reviewItem.comment = $scope.packageReviews[package.id].comment;
				$scope.reviewItem.reviewid = $scope.packageReviews[package.id].id;
			}
			$scope.reviewVideo = video;
			$scope.reviewPackage = package;
			$('#videowReviewDiv').show(500);
		};

		$scope.getStarType = function(val, rating){
			let retClass = "star_border";
			if (rating >= val) retClass = "star";
			if (rating < val && rating > (val - 1)){
				if (rating % 1 >= 0.7) retClass = "star";
				else if (rating % 1 >= 0.3) retClass = "star_half";
			}
			return retClass;
		};

		$scope.submitVideoFeedback = function(){
			if (!$scope.reviewItem.rating){
				erSvc.easyRegAlert({
					"text": "Please select a rating (1-5 stars)",
					"title": "Rating Required"
				});
				return;
			}
			let rec = {
				"comment": $scope.reviewItem.comment?.replaceAll("'", "''"),
				"rating": $scope.reviewItem.rating
			}
			if ($scope.reviewItem.reviewid){
				rec.id = $scope.reviewItem.reviewid;
				rec.query = "updateVideoReview";
			} else if ($scope.reviewVideo){
				rec.query = "insertVideoReview";
				rec.videoid = $scope.reviewVideo.id;
			} else if ($scope.reviewPackage){
				rec.query = "insertVideoPkgReview";
				rec.pkgid = $scope.reviewPackage.id;
			}
			postUserDml(rec).then(function(){
				setTimeout(function(){
					$scope.closeRightDialog();
					getAttendeeReviews();
				}, 200);
			});
		};

		$scope.showReviews = function(resource){
			$scope.reviewList = resource.reviewData.reviewList;
			$scope.dialogTitle = (resource.display_name || resource.name) + " Reviews";
			$('#reviewListDiv').show(500);
		};

		$scope.print = () => window.print();

		setTimeout(function(){
			$('#searchTxt').val('');
		}, 1500);
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
					<div class="pointer text-black fs-24 fw-600 me-2 self-align-center" 
						ng-click="searchValue=''; selectCategory('all'); view='browse'"> 
						{{acctName}} Learning Center <span ng-show="view == 'invoice'">Invoice</span> 
					</div>
					<div ng-show="selectedCategory == 'all' && view == 'browse'" style="white-space: nowrap;">
						<input type="text" placeholder="Search Courses" 
							ng-show="view == 'browse'" ng-model="searchValue" id="searchTxt" 
							autocomplete="off" class="form-control" style="display:inline;" />
						<span class="btn text-white bg-white btn-sm" style="display:inline"
							ng-click="searchVideos()" ng-show="view == 'browse'">
							<i class="bi bi-search"></i>
						</span>
					</div>
				</div>
				<div class="create-nav d-flex f-wrap noPrint">
					<div>
						<button class="btn btn-create fw-600 pe-1" ng-click="enterDiscountCd()"
							ng-show="account && discountCodesAvailable && view != 'myCourses'"> 
							Enter Discount Code
						</button>
						<button class="btn btn-create fw-600 pe-1" ng-click="showSignup()"
							ng-show="!account"> 
							Create Account 
						</button>
						<span ng-show="!account">
							<button class="btn save-btn mx-2" style="margin-right: 1em" 
								ng-click="showLogin()"> 
								Log In 
							</button>
						</span>
						<span ng-show="cart.length" 
							class="cart-count primary-bg text-white vr-middle">
							{{cart.length}}
						</span>
						<i class="bi bi-cart4 shp-cart primary-text" 
							title=" View Cart"  ng-click="view = 'cart'"></i>
					</div>
					<br/>
					<div ng-show="account">
						<div class="nav-item dropdown d-inline-block position-relative" 
							style="margin-right: 8em">
							<button class="btn btn-primary dropdown-toggle" style="border-radius: 60px;padding: 6px;font-weight: bold;" data-bs-toggle="dropdown">
								{{accountInitials()}}
							</button>
							<ul class="dropdown-menu" role="menu">
								<li role="presentation" class="dropdown-header">
									{{account.first_name}} {{account.last_name}}
								</li>
								<li ng-click="openProfileMgmt()">
									<a href="#" class="dropdown-item">Manage Profile</a>
								</li>
								<li ng-click="view = 'myCourses'" ng-show="view != 'myCourses'">
									<a href="#" class="dropdown-item">My Courses</a>
								</li>
								<li ng-click="view = 'invoice'" ng-show="attendeeOrders.length">
									<a href="#" class="dropdown-item">Invoice</a>
								</li>
								<li ng-click="view = 'browse'" ng-show="view != 'browse'">
									<a href="#" class="dropdown-item">Browse Courses</a>
								</li>
								<li ng-click="logOut()">
									<a href="#" class="dropdown-item">Log Out</a>
								</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
			<div class='alert alert-info' role='alert' 
				ng-if="learningCenterMsg && view == 'browse'">
				{{learningCenterMsg}}
			</div>
			<div id="vendorLogos" ng-show="accountid == 1000">
				<a href="https://www.leveldata.com/" target="_blank" />
					<img src="/img/account1000/vendorLogos/15/ld_logo.webp" />
				</a>
				<a href="https://mba-link.com" target="_blank"/>
					<img src="/img/account1000/vendorLogos/12/mba-powerschool-plugins-logo-tagline-vert.png" />
				</a>
				<a href="https://www.bullvalleysoftware.com/" target="_blank"/>
					<img src="/img/account1000/vendorLogos/33/DocumentLOK Logo - HIGH RES.jpg" />
				</a>
				<a href="https://k12ticketing.com/" target="_blank"/>
					<img src="/img/account1000/vendorLogos/81/K12_ERP_logo.png" />
				</a>
				<a href="https://efundsforschools.com/" target="_blank"/>
					<img src="/img/account1000/vendorLogos/8/eFunds_for_Schools_Horizontal_Logo-3.png" />
				</a>
				<a href="https://www.powerschool.com/" target="_blank"/>
					<img src="/img/account1000/vendorLogos/14/PS logo 2.png" />
				</a>
				<a href="https://www.schoolmessenger.com/" target="_blank"/>
					<img src="/img/account1000/vendorLogos/9/HorizontalLockup_fullcolor.png" />
				</a>
				<a href="https://identakid.com/" target="_blank"/>
					<img src="/img/account1000/vendorLogos/7/Ident-A-Kid-Blue-Carbon-Logo.jpg" />
				</a>
			</div>
			<div class="mt-1" ng-show="view == 'browse'">
				<span class="categorySpan mx-1 fw-600" 
					ng-class="{selected : selectedCategory == 'all'}" 
					ng-click="selectCategory('all')"> 
					All Courses 
				</span>
				<div class="dropdown" style="margin-left:1em;margin-right:1em">
					<span class="dropbtn categorySpan fw-600">
						{{selectedCategory==='packages' || selectedCategory==='all' ?"Select category" : selectedCategory}}
						<i class="bx bx-chevron-down fw-700 fs-18 vr-middle"></i>
						<i class="bx bx-chevron-up fw-700 fs-18 vr-middle d-none"></i>
					</span>
					<div class="dropdown-content bg-white">
						<div class="d-flex">
							<div ng-repeat="catgs in categories">
								<div class="pt-1 dropli categorySpan" value="{{category}}"
									ng-repeat="category in catgs" 
									ng-class="{selected : selectedCategory == category}" 
									ng-click="selectCategory(category)">
									{{category}}
								</div>
							</div>
						</div>
					</div>
				</div>
				<span class="categorySpan fw-600"
					ng-class="{selected : selectedCategory == 'packages'}" 
					ng-click="selectCategory('packages')" ng-show="packages.length"> 
					Packages 
				</span>
			</div>
		</div>
	</div>
	<div class="row" ng-show="view == 'browse'">
		<div ng-show="selectedCategory=='packages'">
			<div class="ps-1">
				<div ng-repeat="pkg in packages" class="packageDiv">
					<h3 class="mt-2">{{pkg.name}}</h3>
					<p class="mb-1 fw-200">
						{{pkg.description}}
					</p>
					<div class="videoDiv package-div mt-1 me-1 d-inline-block" 
						ng-repeat="video in pkg.videos | orderBy:'sortorder'">
						<div class="header-bg" style="padding: 10px; border-radius: 5px 5px 0px 0px">
							<H4 style='font-size:15px'>{{video.display_name}}</H4>
							<div>
								<span class="p-name">{{video.instructor | truncate:70}}</span>
							</div>
						</div>
						<div class="pt-1 ps-1 pe-1">
							<div style="text-align:left;height:5em;padding:0px .5em">
								<div class="pt-1" style="color: #707070">
									{{video.description | truncate:100}}
								</div>
							</div>
							<div class="d-flex justify-content-between f-wrap pb-2 align-items-center">
								<div>
									<span>
										{{video.length}} Minutes </span>
								</div>
								<div ng-show="video.reviewData.reviewList.length" ng-click="showReviews(video); $event.stopPropagation();">
									<span ng-repeat="val in [1,2,3,4,5]" class='smallStars'>
										<span class="material-icons rating-star">
											{{getStarType(val, video.reviewData.averageRating)}}
										</span>
									</span>
									{{video.reviewData.reviewList.length}}
								</div>
							</div>
						</div>
					</div>
					<div class="my-2">
						<div class="d-flex justify-content-between align-items-center f-wrap">
							<p class="fw-600"> 
								Rental Duration - {{hoursToDays(pkg.rental_duration)}}
							</p>
							<div>
								<div class="pe-1">
									<p class="pb-0 pt-0 text-end" ng-show="pkg.reviewData.reviewList.length" ng-click="showReviews(pkg)">
										<span ng-repeat="val in [1,2,3,4,5]" class='mediumStars'>
											<span class="material-icons rating-star">
												{{getStarType(val, pkg.reviewData.averageRating)}}
											</span>
										</span>
										{{pkg.reviewData.reviewList.length}}
									</p>
								</div>
								<div class="">
									<span class="me-1">{{pkg.totalLength}} Total</span>
									<button class="btn primary-bg text-white ms-1 py-1 px-1" ng-click="addPkgToCart(pkg)" ng-show="!cart.includes(pkg)"> Add to Cart &nbsp;&nbsp; <b>{{pkg.price | currency}}</b>
									</button>
									<button class="btn btn-danger px-1 py-1" ng-click="cart.splice(cart.indexOf(pkg),1)" style="margin-left: 1em" ng-show="cart.includes(pkg)">
										<span class="bi bi-cart4"></span> Remove From Cart </button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- End Packages Div -->

		<div class="learning-all">
			<div ng-repeat="video in visibleVideos | orderBy:'display_name'" 
				class="col-lg-3 col-md-4 col-sm-6 col-12 video-card px-1" style="min-height:25rem">
				<div class="videoDiv" ng-class="{ inCart : cart.includes(video)}" ng-click="purchaseVideo(video)">
					<div class="header-bg" style="padding: 10px; border-radius: 5px 5px 0px 0px">
						<div class="a-name text-white">{{video.category}}</div>
						<H4 style='font-size:15px'>{{video.display_name}}</H4>
						<div>
							<span class="p-name">{{video.instructor | truncate:70}}</span>
						</div>
					</div>
					<div>
						<div class="m-1">
							{{video.description | truncate:100}}
						</div>
					</div>
					<div class="d-flex justify-content-between f-wrap p-1">
						<div>
							<span class="fs-13">
								{{video.length}} Minutes </span>
							<div class="fs-13"> Rental Duration - {{hoursToDays(video.rental_duration)}}
							</div>
						</div>
						<div>
							<span class="fs-16 fw-700 primary-text">
								{{video.price | currency}}
							</span>
							<div style="" ng-show="video.reviewData.reviewList.length" ng-click="showReviews(video); $event.stopPropagation();">
								<span ng-repeat="val in [1,2,3,4,5]" class='smallStars'>
									<span class="material-icons rating-star">
										{{getStarType(val, video.reviewData.averageRating)}}
									</span>
								</span>
								{{video.reviewData.reviewList.length}}
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="text-center" ng-show="cart.length" style="clear:both;margin-top:1em">
				<button class="btn save-btn" ng-click="view='cart'">
					<span class="bi bi-cart4"></span> Proceed to Cart </button>
			</div>
		</div>
	</div>
	<!-- End Browse Div -->
	<!-- Cart View -->
	<div class="row mb-5 learning-cart" ng-show="view=='cart'">
		<div class="col-lg-12">
			<div class='alert alert-warning' role='alert' ng-show="!cart.length" style="width:50%;margin:auto"> 	
				Your cart is empty 
			</div>
			<table class="table striped" ng-show="cart.length" style="width:100em;margin:auto;margin-top: 1em;">
				<thead>
					<tr>
						<th class="border-top-left-round">Product</th>
						<th>Description</th>
						<th class="right">Price</th>
						<th style="width:10em" class="border-top-right-round"></th>
					</tr>
				</thead>
				<tbody style="border:1px solid silver">
					<tr ng-repeat="item in cart">
						<td>
							<b>{{item.name}}</b>
							<br/>
							<span ng-show="item.instructor">
								<!-- If Video -->
								<i style="margin-right:2em">{{item.instructor}}</i>
								<span ng-show="item.length">{{item.length}} Minutes</span>
							</span>
							<span ng-show="item.videos">
								<!-- If Package -->
								<i style="margin-right:2em">{{item.videos.length}} Videos</i>
								{{item.totalLength}}
							</span>
						</td>
						<td>{{item.description | truncate:100}}</td>
						<td class="right">{{item.price | currency}}</td>
						<td class="center">
							<button class="btn primary-btn text-white" 
								ng-click="removeFromCart(item)">
								<span class="bi bi-trash"></span>
							</button>
						</td>
					</tr>
				</tbody>
				<tfoot>
					<tr ng-show="discount">
						<td colspan="3" class="right bold" style="padding:0px 10px;border-top: none;">
							Orders:
							<span style="display: inline-block;width: 8rem;">
								{{cartTotal() + discount | currency}}
							</span>
						</td>
						<td style="border-top:none"></td>
					</tr>
					<tr ng-show="discount">
						<td colspan="3" class="right bold" style="padding:0px 10px;border-top: none;">
							Discount:
							<span style="display: inline-block;width: 8rem;">
								 {{discount | currency}}
							</span>
						</td>
						<td style="border-top:none"></td>
					</tr>
					<tr>
						<td colspan="3" class="right bold" style="padding:0px 10px;border-top: none;">
							Total:
							<span style="display: inline-block;width: 8rem;">
								 {{cartTotal() | currency}}
							</span>
						</td>
						<td style="border-top:none"></td>
					</tr>
				</tfoot>
			</table>
			<div class="button-row" style="margin-right: 20px; margin: auto; margin-top: 2em">
				<button class="btn cancel-btn mt-1" ng-click="view = 'browse'"> Return to Browsing </button>
				<button class="btn save-btn mt-1" ng-click="view = 'checkout'" 
					ng-show="cart.length"> Proceed to Checkout 
				</button>
			</div>
		</div>
	</div>
	<!-- End Cart Div -->

	<!-- Checkout View -->
	<div class="row" ng-show="view == 'checkout'" style="margin: auto mb-5">
		<div class="col-lg-12">
			<form name="paymentForm" class="m-auto w-50 px-2 py-2" style="margin-top:1em">
				<div id="cardTable">
					<div class="my-1">
						<span class="bold fs-24">Order Total</span>
						<span class="bold ms-4 fs-24">{{(cartTotal() || purchasedAmt) | currency}}</span>
					</div>
					<div class="my-1" ng-if="poEnabled || checkEnabled">
						<label>Payment Method</label>
						<select ng-model="$parent.paymentMethod" class="form-select">
							<option ng-if="ccEnabled" value="cc">Credit Card</option>
							<option ng-if="poEnabled" value="po">Purchase Order</option>
							<option ng-if="checkEnabled" value="check">Check</option>
						</select>
					</div>
					<div class="my-1" ng-show="['po','check'].includes(paymentMethod)">
						<label>Payment Number</label>
						<input type="text" class="form-control" ng-model="pymtNumber" />
					</div>
					<div ng-show="paymentMethod == 'po'" class="messageDiv"
						ng-bind-html="poInstructions | trustHtml">
					</div>
					<div ng-show="paymentMethod == 'check'" class="messageDiv" 
						ng-bind="checkInstructions">
					</div>
					<div class="my-1" ng-show="paymentMethod == 'cc'">
						<label>Card Holder Name</label>
						<input type="text" ng-model="card.name" class="form-control input-field" required />
					</div>
					<div class="my-1" ng-show="paymentMethod == 'cc'">
						<label>Email</label>
						<input type="email" ng-model="card.email" class="form-control input-field" size="50" required />
					</div>
					<div class="my-1" ng-show="paymentMethod == 'cc'">
						<label>Street</label>
						<input type="text" ng-model="card.street" class="form-control input-field" required />
					</div>
					<div class="my-1" ng-show="paymentMethod == 'cc'">
						<label>City, State Zip</label>
						<br/>
						<input type="text" ng-model="card.city" class="form-control input-field" required style="display: inline; max-width: 15em" />
						<select ng-model="card.state" class="form-select" required style="display: inline; max-width: 5em; margin: 0px 5px" ng-options="state as state for state in states" />
						<input type="text" ng-model="card.zip" class="form-control input-field" required style="display: inline; max-width: 5em" />
					</div>
					<div class="my-1" ng-show="paymentMethod == 'cc'">
						<label>Card Number</label>
						<input type="text" ng-model="card.number" class="form-control input-field" required />
					</div>
					<div class="my-1" ng-show="paymentMethod == 'cc'">
						<label>Expiration</label>
						<div class="d-flex">
							<div class="d-flex">
								<select ng-model="card.expMonth" class="form-select input-field" ng-options="month for month in months" required></select>
								<span class="ms-1 self-align-center">Month</span>
							</div>
							<div class="d-flex">
								<select class="form-select" ng-model="card.expYear" required ng-options="year for year in years"></select>
								<span class="ms-1 self-align-center"> Year</span>
								<br/>
							</div>
						</div>
						<div class="d-flex my-1">
							<span class="self-align-center">Cvv</span>
							<input type="text" ng-model="card.cvv" class="form-control input-field ms-1 w-30" required />
						</div>
					</div>
				</div>
			</form>
			<div class="m-auto mb-1 text-center pt-1 pb-2">
				<button class="btn cancel-btn" ng-click="view = 'cart'" ng-show="!paymentSuccess"> 
					Return to Cart 
				</button>
				<button class="btn save-btn" ng-click="submitOrder()" ng-show="!paymentSuccess"> 
					Submit Payment 
				</button>
				<span ng-if="paymentSuccess && paymentMethod != 'cc'" class="alert alert-success"
					style="margin-right: 2em;">
					Thank you for your order.
				</span>
				<button class="btn btn-primary" ng-click="view = 'myCourses'" ng-show="paymentSuccess">View Purchased Items 
				</button>
			</div>
			<div ng-if="paymentSuccess && paymentMethod == 'cc'" class="alert alert-success">
				<h2 style="text-decoration: underline"> Thank you for your payment. </h2>
				<br/> Your payment of {{paymentAmount | currency}} has been successfully processed. <br/> Your confirmation number is: <b>{{paymentConfirmation}}</b>
				<br/> An email with this information will be sent to {{card.email}}
			</div>
			<div ng-show="paymentError" class="alert alert-danger" role="alert">
				<h4>Error Processing Credit Card Payment</h4>
				{{authMessage}}	<br/>
				{{paymentMessage}} <br/>
				Please check the credit/debit card details you've submitted for accuracy.
			</div>
		</div>
	</div>
	<!-- End Checkout Div -->

	<!-- Pay Outstanding Balance -->
	<div class="row" ng-show="view == 'payBal'" style="margin: auto mb-5">
		<div class="col-lg-12">
			<form name="paymentForm2" class="m-auto w-50 px-2 py-2" style="margin-top:1em">
				<div>
					<div class="my-1">
						<span class="bold fs-24">Total Due</span>
						<span class="bold ms-4 fs-24">{{invoice.due | currency}}</span>
					</div>
					<div class="my-1">
						<label>Card Holder Name</label>
						<input type="text" ng-model="card.name" class="form-control input-field" required />
					</div>
					<div class="my-1">
						<label>Email</label>
						<input type="email" ng-model="card.email" class="form-control input-field" size="50" required />
					</div>
					<div class="my-1">
						<label>Street</label>
						<input type="text" ng-model="card.street" class="form-control input-field" required />
					</div>
					<div class="my-1">
						<label>City, State Zip</label>
						<br/>
						<input type="text" ng-model="card.city" class="form-control input-field" required style="display: inline; max-width: 15em" />
						<select ng-model="card.state" class="form-select" required style="display: inline; max-width: 5em; margin: 0px 5px" ng-options="state as state for state in states" />
						<input type="text" ng-model="card.zip" class="form-control input-field" required style="display: inline; max-width: 5em" />
					</div>
					<div class="my-1">
						<label>Card Number</label>
						<input type="text" ng-model="card.number" class="form-control input-field" required />
					</div>
					<div class="my-1">
						<label>Expiration</label>
						<div class="d-flex">
							<div class="d-flex">
								<select ng-model="card.expMonth" class="form-select input-field" ng-options="month for month in months" required></select>
								<span class="ms-1 self-align-center">Month</span>
							</div>
							<div class="d-flex">
								<select class="form-select" ng-model="card.expYear" required ng-options="year for year in years"></select>
								<span class="ms-1 self-align-center"> Year</span>
								<br/>
							</div>
						</div>
						<div class="d-flex my-1">
							<span class="self-align-center">Cvv</span>
							<input type="text" ng-model="card.cvv" class="form-control input-field ms-1 w-30" required />
						</div>
					</div>
				</div>
			</form>
			<div class="m-auto mb-1 text-center pt-1 pb-2">
				<button class="btn save-btn" ng-click="submitInvoicePayment()" ng-show="!paymentSuccess"> 
					Submit Payment 
				</button>
				<button class="btn btn-primary" ng-click="view = 'myCourses'" ng-show="paymentSuccess">View Purchased Items 
				</button>
			</div>
			<div ng-if="paymentSuccess" class="alert alert-success">
				<h2 style="text-decoration: underline"> Thank you for your payment. </h2>
				<br/> Your payment of {{paymentAmount | currency}} has been successfully processed. <br/> Your confirmation number is: <b>{{paymentConfirmation}}</b>
				<br/> An email with this information will be sent to {{card.email}}
			</div>
			<div ng-show="paymentError" class="alert alert-danger" role="alert">
				<h4>Error Processing Credit Card Payment</h4>
				{{authMessage}}	<br/>
				{{paymentMessage}} <br/>
				Please check the credit/debit card details you've submitted for accuracy.
			</div>
		</div>
	</div>
	<!-- End Pay Outstanding Balance -->

	<!-- Available Courses View -->
	<div class="row" ng-show="view == 'myCourses'">
		<div class="col-lg-12">
			<div class="alert alert-info" role="alert" 
				ng-show="!purchasedVideos.length && !purchasedPackages.length"> 
				No Courses Currently Available 
			</div>
			<div ng-repeat="pkg in purchasedPackages track by $index">
				<h3 style="margin-left: 0.5em">
					{{pkg.name}}
					<span style="float: right; margin-right: 2em" ng-click="provideVidoeFeedback(null, pkg)">
						<span style="margin-right: 3em; font-size: 12pt"> Expires - {{hoursToDays(pkg.hoursRemaining)}}
						</span>
						<span title="Rate This Package" class="mediumStars">
							<span ng-repeat="val in [1,2,3,4,5]">
								<span class="material-icons rating-star">
									{{getStarType(val, packageReviews[pkg.id].rating)}}
								</span>
							</span>
						</span>
					</span>
				</h3>
				<table class="table scrollable striped">
					<tr>
						<th>Course</th>
						<th>Instructor</th>
						<th>Length</th>
						<th>Description</th>
						<th ng-if="hasVideoDocs">Resources</th>
						<th style="width: 7em">Launch</th>
						<th style="width: 9em">Rate</th>
					</tr>
					<tr ng-repeat="video in pkg.videos | orderBy:'sortorder'">
						<td>{{video.display_name}}</td>
						<td>{{video.instructor}}</td>
						<td>{{video.length}} Minutes</td>
						<td>{{video.description | truncate:75}}</td>
						<td ng-if="hasVideoDocs">
							<div ng-repeat="doc in video.documents">
								<a href="/{{doc.filepath}}" target="_blank">
									{{doc.name}}
								</a>
							</div>
						</td>
						<td>
							<!-- <button class="btn btn-success" ng-click="launchVideo(video)" 
								title="Launch Course" style="padding: 3px 5px">
								<span class="bi bi-play-circle"></span> 
								Launch Course 
							</button> -->
							<a class="btn btn-success" href="/{{video.filepath}}" target="_blank"
								title="Launch Course" style="padding: 3px 5px">
								<span class="bi bi-play-circle"></span> 
								Launch Course 
							</a>
						</td>
						<td>
							<span class="smallStars" title="Rate This Course" 
								ng-click="provideVidoeFeedback(video)">
								<span ng-repeat="val in [1,2,3,4,5]">
									<span class="material-icons rating-star">
										{{getStarType(val, videoReviews[video.id].rating)}}
									</span>
								</span>
							</span>
						</td>
					</tr>
				</table>
			</div>
			<table class="table scrollable striped" ng-show="purchasedVideos.length">
				<tr>
					<th>Course</th>
					<th>Category</th>
					<th>Instructor</th>
					<th>Length</th>
					<th>Description</th>
					<th>Expires</th>
					<th ng-if="hasVideoDocs">Resources</th>
					<th style="width: 7em">Launch</th>
					<th style="width: 9em">Rate</th>
				</tr>
				<tr ng-repeat="video in purchasedVideos">
					<td>{{video.display_name}}</td>
					<td>{{video.category}}</td>
					<td>{{video.instructor}}</td>
					<td>{{video.length}} Minutes</td>
					<td>{{video.description | truncate:75}}</td>
					<td>{{hoursToDays(video.hoursRemaining)}}</td>
					<td ng-if="hasVideoDocs">
						<div ng-repeat="doc in video.documents">
							<a href="/{{doc.filepath}}" target="_blank"> {{doc.name}} </a>
						</div>
					</td>
					<td>
						<!-- <button class="btn btn-success" ng-click="launchVideo(video)" title="Launch Course" 
							style="padding: 3px 5px">
							<span class="bi bi-play-circle"></span> Launch Course 
						</button> -->
						<a class="btn btn-success" href="/{{video.filepath}}" target="_blank"
							title="Launch Course" style="padding: 3px 5px">
							<span class="bi bi-play-circle"></span> 
							Launch Course 
						</a>
					</td>
					<td>
						<span title="Rate This Course" ng-click="provideVidoeFeedback(video)" class="smallStars">
							<span ng-repeat="val in [1,2,3,4,5]">
								<span class="material-icons rating-star">
									{{getStarType(val, videoReviews[video.id].rating)}}
								</span>
							</span>
						</span>
					</td>
				</tr>
			</table>
		</div>
		<!-- End Column -->
	</div>
	<!-- End Available Courses View -->

	<!-- Invoice View -->
	<div class="row" ng-show="view == 'invoice'">
		<div class="col-lg-12">
			<div class="button-row noPrint" style="margin:.5em">
				<button class="btn btn-primary" ng-click="print()" 
					style="background-color: var(--primary-color)">
					<span class="bi bi-printer"></span> Print
				</button>
			</div>
			<div class="threeCol">
				<div>
					<section>{{account.last_name}}, {{account.first_name}}</section>
					<section ng-if="account.address1">{{account.address1}}</section>
					<section ng-if="account.address2">{{account.address2}}</section>
					<section ng-if="account.city">
						{{account.city}}, {{account.state}} {{account.zip}}
					</section>
				</div>
				<div ng-if="account.business" class="center">{{account.business}}</div>
				<div class="right">{{account.email}}</div>
			</div>
			<table class="table striped">
				<thead>
					<tr>
						<th style="white-space: nowrap;">Order Date</th>
						<th>Item(s)</th>
						<th class="right">Amount</th>
						<th class="right" ng-show="invoice.discount">Discount</th>
						<th class="right">Paid</th>
						<th class="right">Due</th>
					</tr>
				</thead>
				<tbody>
					<tr ng-repeat="order in attendeeOrders">
						<td>{{order.orderDate}}</td>
						<td style='white-space: pre-wrap;'>{{order.item}}</td>
						<td class="right">{{order.total | currency}}</td>
						<td class="right" ng-show="invoice.discount">{{order.discount | currency}}</td>
						<td class="right">{{order.paid | currency}}</td>
						<td class="right">{{order.due | currency}}</td>
					</tr>
				</tbody>
				<tfoot>
					<tr class="bold">
						<td colspan="2"></td>
						<td class="right">{{invoice.total | currency}}</td>
						<td class="right" ng-show="invoice.discount">{{invoice.discount | currency}}</td>
						<td class="right">{{invoice.paid | currency}}</td>
						<td class="right">{{invoice.due | currency}}</td>
					</tr>
				</tfoot>
			</table>
			<div class="messageDiv" ng-bind="invoiceMessage"></div>
			<div class="button-row" ng-show="invoice.due > 0">
				<button class="btn btn-primary" ng-click="view='payBal'">Pay With Credit/Debit Card</button>
			</div>
		</div>
	</div>

	<div id="manageProfileDialog" class="dialogRight" style="min-width:50em">
		<div class="dialogTitle">Profile Details</div>
		<div class="dialogContents">
			<form name="profileForm">
			<table>
				<tr>
					<td>Email*</td>
					<td>
						<input type="email" ng-model="profile.email" placeholder="Email Address" 
							class="form-control input-field" required />
					</td>
				</tr>
				<tr>
					<td>Last Name*</td>
					<td>
						<input type="text" ng-model="profile.last_name" placeholder="Last Name" 
							class="form-control input-field" required />
					</td>
				</tr>
				<tr>
					<td>First Name*</td>
					<td>
						<input type="text" ng-model="profile.first_name" placeholder="First Name" 
							class="form-control input-field" required />
					</td>
				</tr>
				<tr>
					<td>Business/Company</td>
					<td>
						<input type="text" ng-model="profile.business" placeholder="Business/Company" 
							class="form-control input-field" />
					</td>
				</tr>
				<tr>
					<td>Title/Position</td>
					<td>
						<input type="text" ng-model="profile.title" placeholder="Title/Position" 
							class="form-control input-field" />
					</td>
				</tr>
				<tr>
					<td>Address</td>
					<td>
						<input type="text" ng-model="profile.address1" placeholder="Address" 
							class="form-control input-field" />
					<td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="text" ng-model="profile.address2" placeholder="Address Line 2" 
							class="form-control input-field" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="text" ng-model="profile.city" placeholder="City" class="form-control input-field" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="text" ng-model="profile.state" placeholder="State" class="form-control input-field" />
					</td>
				</tr>
				<tr>
				<td></td>
					<td>
						<input type="text" ng-model="profile.zip" placeholder="Zip" class="form-control input-field" />
					</td>
				</tr>
				<tr>
					<td>Password*</td>
					<td>
						<input type="password" value="password" disabled />
						<button class="btn btn-primary btn-xs" ng-click="changePassword()">Reset Password</button>
					</td>
				</tr>
			</table>
			<div class="button-row" style="margin-top:1em">
				<button class="btn btn-primary" ng-click="closeRightDialog()">Cancel</button>
				<button class="btn btn-primary" ng-click="updateProfile()">Update Profile</button>
			</div>
			</form>
		</div>
	</div>
	<!-- End Manage Profile Dialog -->

	<review-list></review-list>
	<div style="display: none">
		<!-- Selected Video Dialog -->
		<div id="selectedVideoDiv">
			<div>
				<span style="font-style: italic; font-weight: bold">
					{{selectedVideo.instructor}}
				</span>
				<span style="margin-left: 3em">
					{{selectedVideo.length}} Minutes </span>
			</div>
			<div ng-bind="selectedVideo.description"></div>
			<div class="text-center">
				<button ng-click="addToCart()" class="btn save-btn bold" ng-show="!cart.includes(selectedVideo)">
					<span class="bi bi-cart4 text-white"></span> Add To Cart <b>{{selectedVideo.price | currency}}</b>
				</button>
				<button ng-click="removeFromCart()" class="btn btn-danger" ng-show="cart.includes(selectedVideo)">
					<span class="bi bi-cart4"></span> Remove From Cart </button>
			</div>
		</div>
		<!-- End Selected Video Dialog -->
		<!-- Login Dialog -->
		<div id="loginDiv">
			<input type="email" ng-model="login.email" placeholder="Email Address" class="form-control input-field" />
			<br/>
			<input type="password" ng-model="login.password" placeholder="Password" class="form-control input-field" />
			<br/>
			<div class="alert alert-danger" role="alert" ng-show="emailNotFound"> 
				This email address was not found in our records 
			</div>
			<div class="alert alert-danger" role="alert" ng-show="loginError"> Invalid credentials </div>
			<a style="color: var(--link-color)" class="pointer" ng-click="resetPassword()"> I forgot my password </a>
			<div class="text-center">
				<button class="btn bold" ng-click="cancelLogin()">Cancel</button>
				<button class="btn primary-btn bold text-white" ng-click="submitLogin()"> Log In </button>
			</div>
		</div>
		<!-- End Login Dialog -->
		<!-- Signup Dialog -->
		<div id="signupDiv" class="">
			<form name="signupForm" class="">
				<table>
					<tr>
						<td>Email*</td>
						<td>
							<input type="email" ng-model="signup.email" placeholder="Email Address" 
								class="form-control input-field" required ng-blur="checkAttendees()" />
						</td>
					</tr>
					<tr>
						<td>Last Name*</td>
						<td>
							<input type="text" ng-model="signup.last_name" placeholder="Last Name" 
								class="form-control input-field" required ng-blur="checkAttendees()" />
						</td>
					</tr>
					<tr>
						<td>First Name*</td>
						<td>
							<input type="text" ng-model="signup.first_name" placeholder="First Name" class="form-control input-field" 
							required ng-blur="checkAttendees()" />
						</td>
					</tr>
					<tr>
						<td>Business/Company</td>
						<td>
							<input type="text" ng-model="signup.business" placeholder="Business/Company" class="form-control input-field" />
						</td>
					</tr>
					<tr>
						<td>Title/Position</td>
						<td>
							<input type="text" ng-model="signup.title" placeholder="Title/Position" class="form-control input-field" />
						</td>
					</tr>
					<tr>
						<td>Address</td>
						<td>
							<input type="text" ng-model="signup.address1" placeholder="Address" 
								class="form-control input-field" />
						<td>
					</tr>
					<tr>
						<td></td>
						<td>
							<input type="text" ng-model="signup.address2" placeholder="Address Line 2" class="form-control input-field" />
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<input type="text" ng-model="signup.city" placeholder="City" class="form-control input-field" />
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<input type="text" ng-model="signup.state" placeholder="State" class="form-control input-field" />
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<input type="text" ng-model="signup.zip" placeholder="Zip" class="form-control input-field" />
						</td>
					</tr>
					<tr>
						<td>Password*</td>
						<td>
							<input type="password" ng-model="signup.password" placeholder="Password" 
							class="form-control input-field" required />
						</td>
					</td>
				</table>
			</form>
			<div class="text-center mt-1">
				<button class="btn bold" ng-click="cancelSignup()">Cancel</button>
				<button class="btn primary-btn text-white bold" ng-click="createAcct()"> Create Account </button>
			</div>
		</div>
		<!-- Attendee Match Dialog -->
		<div id="attendeeSelector">
			<div ng-repeat="attendee in attendeeMatches" class="attendeeDiv">
				<h3>{{attendee.first_name}} {{attendee.last_name}}</h3>
				<div>{{attendee.email}}</div>
				<div ng-show="attendee.business">{{attendee.business}}</div>
				<div ng-show="attendee.state">{{attendee.state}}</div>
				<div class="button-row">
					<button class="btn btn-success" ng-click="selectAttendee(attendee)"> This Is Me </button>
					<button class="btn btn-primary" ng-click="attendeeNotFound()" ng-show="attendeeMatches.length == 1"> Not Me </button>
				</div>
			</div>
			<div class="button-row" ng-show="attendeeMatches.length > 1" style="margin-top: 1em">
				<button class="btn btn-primary" ng-click="attendeeNotFound()"> Not Me </button>
			</div>
		</div>
		<!-- End Attendee Match Dialog -->

		<!-- Change Password Dialog -->
		<div id="changePwDiv">
			<table style="width: 99%">
				<tr>
					<td class="bold">Current Password</td>
					<td style="padding: 0.5em">
						<input class="form-control" ng-model="currentPw" type="password" />
					</td>
				</tr>
				<tr>
					<td class="bold">New Password</td>
					<td style="padding: 0.5em">
						<input class="form-control" ng-model="newPw" type="password" />
					</td>
				</tr>
				<tr>
					<td class="bold">Confirm Password</td>
					<td style="padding: 0.5em">
						<input class="form-control" ng-model="confirmPw" type="password" />
					</td>
				</tr>
			</table>
			<div class="alert alert-danger" role="alert" ng-show="invalidCurrentPw"> Current Password Not Valid </div>
			<div class="alert alert-danger" role="alert" ng-show="passwordMismatch"> Passwords Do Not Match </div>
			<div class="button-row">
				<button class="btn btn-danger" ng-click="cancelPwChange()"> Cancel </button>
				<button class="btn btn-success" ng-click="submitPwChange()" ng-disabled="!enablePwChange()"> Submit </button>
			</div>
		</div>
		<!-- End Change Password Dialog -->

	</div>
	<!-- End hidden div -->

	<!-- Video Review Dialog -->
	<div id="videowReviewDiv" class="dialogRight bg-td">
		<div class="dialogTitle bg-td">
			<span ng-if="reviewVideo">Course Review - {{reviewVideo.display_name}}</span>
			<span ng-if="reviewPackage">Package Review - {{reviewPackage.name}}</span>
		</div>
		<div class="dialogContents">
			<div style="font-weight: bold; font-size: large; margin-bottom: 0.5em"> Course Rating </div>
			<span ng-repeat="val in [1,2,3,4,5]">
				<span class="material-icons rating-star" ng-click="reviewItem.rating = val">
					{{getStarType(val, reviewItem.rating)}}
				</span>
			</span>
			<div style="font-weight: bold;font-size: large;margin-bottom: 0.5em;margin-top: 1.5em;"> Comments </div>
			<textarea class="form-control" ng-model="reviewItem.comment" rows="8"></textarea>
			<div class="button-row" style="margin-top: 1em">
				<button class="btn btn-primary" ng-click="closeRightDialog()">
					<span class="bi bi-chevron-left"></span> Cancel </button>
				<button class="btn btn-success" ng-click="submitVideoFeedback()">
					<span class="bi bi-check-lg"></span> Submit Review </button>
			</div>
		</div>
	</div>
	<!-- End Video Review Dialog -->
	<div id="videoPlayer" ng-show="launchedVideo">
		<div id="videoHeader">
			<b>{{launchedVideo.displayName || launchedVideo.name}}</b>
			<button class="btn btn-danger btn-xs" title="Close Video" ng-click="closeVideo()" 
				style="float: right; margin-right: 5px"> x </button>
		</div>
		<iframe id="videoFrame" frameborder="0" allow="autoplay; fullscreen" allowfullscreen=""></iframe>
	</div>
	<div style="display:none">
		<div id="termsDiv">
			Please review our <a href="/learningCenterPrivacyPolicy.php" target="_blank">Privacy Policy</a>
			and <a href="/learningCenterTerms.php" target="_blank">Terms of Use</a> 
			prior to continuing.
			<br/><br/>
			<input type="checkbox" ng-model="termsAgreed" /> I agree to the PSUG privacy policy and terms of use
			<br/>
			<div class="button-row">
				<button class="btn btn-primary" ng-disabled="!termsAgreed" ng-click="termsAgree()">Continue</button>
			</div>
		</div>
	</div>	
</section>
</body>
<style>
	#termsDiv a{
		color: var(--link-color);
		text-decoration:underline;
	} 
	#signupDiv input{ 
		width:23em; 
		margin-bottom: 5px;
	}
	#signupDiv td:first-child, #manageProfileDialog td:first-child{ 
		text-align: right; 
		padding-right:1em; 
		font-weight: bold;
	}
	#vendorLogos{
		display:flex;
		align-items: center;
		justify-content: space-between;
		flex-wrap: wrap;
	}
	#vendorLogos img{
		max-height: 50px;
		max-width: 150px;
	}
</style>
</html>
