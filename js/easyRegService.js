angular.module("erSvc",['alertModule','easyRegDataModule','gridWidget'])
.service("erSvc",function($http, $q, $rootScope, $location, $timeout, $interval, easyRegAlertService, dataSvc){
	var erService = this;
	$http.defaults.headers.common['X-CSRF-Token'] = erUtils.getCsrfToken();

	function postForm(url, data){
		return $http({
			"url": url,
			"method": "POST",
			"data": $.param(erUtils.withCsrf(data)),
			"headers": {"Content-Type": "application/x-www-form-urlencoded"}
		});
	}

	//Eric,  Look closer - this can probably go away.  Legacy code
	// session storage
	this.sessionData = {};
	let storedData = localStorage.getItem('erSessionData');
	if(storedData) this.sessionData = JSON.parse(storedData);

	//ensure inability to land on admin page for wrong account
	if(window.location.href.indexOf('/events/') > 0 || window.location.href.indexOf('/account/') > 0 ){
		$http({"url": "/session_data.php","method": "GET"}).then(function(response){
			if(!window.location.href.includes('invoice.php') && response.data.accountid != erSessionData.userData.accountid){
				window.location.href = "/index.php?accountid=" + erService.sessionData.userData.accountid;
			}
		});
	}

	/***** Session Management *****/
	
	let idleTime = 0;
	$(document).mousemove(() => idleTime = 0);
	$(document).keypress(() => idleTime = 0);
	$interval(() => idleTime += 1, 60000);

	//check activity every 60 seconds and log out after 30 minutes of inactivity
	function checkSession(){
		postForm('/sessionCheck.php').then(function(response){
			const data = response.data;
			//if the browser has detected activity in the last two minutes
			//but the server has not detected activity for over 100 seconds, 
			//reset the clock for server activity
			if(Number(data) > 100 && idleTime < 2) postForm('/session_recordActivity.php');
			if(data.indexOf('expired') >= 0){
				erSessionData = null;
				erService.easyRegConfirm({"text":"Your session has expired. Please login again.","title":"Session Expired"},"Login","Exit")
				.then(function(res){
					postForm('/endSession.php').finally(() => window.location.href = '/login.php');
				});
			}
			else{
				//recheck after 1 minute
				$timeout(checkSession, 60000);
			}
		});
	} 
	checkSession();

	//for troubleshooting, log session and scope when double-click on element
	$(document).on('dblclick','.copyright',function(e){
		console.log("SESSION");
		console.log(erSessionData);
		console.log("$scope");
		console.log(angular.element(this).scope());
		$('div').dblclick(function(e){
			console.log(angular.element(this).scope());
			e.stopPropagation();
		});
		e.stopPropagation();
	});

	//ensure user has access to the page they've landed on 
	//if not, redirect to an accessible page
	this.checkAccess = function(){
		let resp = $q.defer();
		let location = $location.$$path.split('/')[1] || $location.$$url.split('/')[1].replace('#','');
		if(location == 'acctReports' || location == 'evt_reports') return resp.resolve(true);
		if(location.indexOf('manage_page') == 0 && erSessionData.pageAccess.split(',').includes('manage_page'))
			return;
		let evtPages = ['evt_dashboard','event_details','registration_form','registration_types',
			'registration_extras','registration_messages','sponsor_types','manage_schedule',
			'sessions','rooms','event_users','course_catalog','import_event_data',
			'master_schedule','evt_document_management','survey_questions','registrations',
			'redeem_extras','station','manage_page','evt_expense_rpt','evt_staff_pymt_rpt',
			'payments','rpt_presenters','rpt_rooms','rpt_sessions','rpt_signups',
			'rpt_staff','order_summary'];
		let accessibleEvtPages = (erSessionData.pageAccess).split(',').filter(p => evtPages.includes(p));
		
		$http({"url": `/confirmAccess.php?loc=${location}`,"method": "GET"}).then(function(res){
			if(!['true'].includes(res.data)){
				//if desired page is event page and no access but user has access to another event page, 
				//send them to another event page
				if(evtPages.includes(location) && accessibleEvtPages.length){
					$location.path('/' + accessibleEvtPages[0]);
				}else{
					//everyone can get to event management to see available events
					if(location == 'event_management') return resp.resolve(false);
					$location.path('/event_management');
				}
			}
			resp.resolve(res.data == 'true');
		});
		return resp.promise;
	};

	this.getUserRoles = function(){
		var deferredResponse = $q.defer();
		var eventid = '';
		var urlParts = window.location.href.split('/');
		var slug = urlParts[urlParts.indexOf('e') + 1];
		var params = { "query":"getEventIdFromUrl", "slug":slug };
		$http({"url": "/data_access/getQueryResults.php","method": "GET","params": params})
		.then(function(response){
			if(response.data.rows[0]) eventid = response.data.rows[0].id;
			else eventid = new URL(location).searchParams.get('eventid');
			$http({"url": "/session_data.php","method": "GET"}).then(function(response){
				if(response.data.accountid){
					var sessionData = response.data;
					deferredResponse.resolve({
						"master": sessionData.master == '1',
						//"presenter": presenter,
						"securityAccess": (sessionData.pageAccess || '').split(',').length>3,
						"eventAccess": erUtils.hasId((sessionData.eventAccess || '').split(','), eventid),
						"accountid": sessionData.accountid,
						"sponsor_staff": sessionData.sponsor_staff,
						"sections_allowed": sessionData.sections_allowed,
						"confirmation": sessionData.confirmation,
						"attendee_first": sessionData.attendee_first,
						"attendee_last": sessionData.attendee_last,
						"has_extras":sessionData.has_extras == '1'
					});
				}else{
					deferredResponse.resolve({
						"master": false,
						"presenter": false,
						"admin": false,
						"clerk": false,
						"accountid": 0,
						"sponsor_staff": response.data.sponsor_staff,
						"confirmation": response.data.confirmation
					});
				}
			});
		});
		return deferredResponse.promise;
	};

	this.loadingDialog = function(message){
		var dialogContent = $('<div class="loadingDialog"></div>');
		dialogContent.html((message || 'Loading') + '<div class="pctComplete"></div> <span class="loader"></span>');
		dialogContent.dialog({"modal":true});
		$('.loader').closest('.ui-dialog').find(".ui-dialog-titlebar").hide();
		$('div.loadingDialog').css('min-height','auto');
	};

	this.hideLoading = function(){ // Always drop the "Loading Event Data" modal and leftover blockUI
		try {
			$('.loadingDialog').dialog('close'); // jQuery UI dialog opened by loadingDialog()
		} catch (dialogErr) { // Dialog may already be gone
		} // Ignore; the overlay must not trap the dashboard
		try {
			if (window.jQuery && typeof jQuery.unblockUI === 'function') { // Legacy blockUI plugin
				jQuery.unblockUI(); // Clear a stuck full-page spinner if a handler used $.blockUI
			}
		} catch (blockErr) { // Plugin not loaded on this page
		} // Ignore
	};

	this.closeLoading = function(){
		erService.hideLoading(); // Legacy name now also dismisses blockUI so old callers cannot leave a stuck overlay
	};

	this.easyRegAlert = function(message, autoClose){
		easyRegAlertService.easyRegAlert(message, autoClose);
	};

	this.easyRegConfirm = function(message, yesVal, noVal){
		var response = $q.defer();
		easyRegAlertService.easyRegConfirm(message, yesVal, noVal).then(function(res){
			response.resolve(res);
		});
		return response.promise;
	};

	this.easyRegFeedback = function(title, message, yesVal, noVal){
		var response = $q.defer();
		easyRegAlertService.easyRegFeedback(title, message, yesVal, noVal).then(function(res){
			response.resolve(res);
		});
		return response.promise;
	};

	this.setSelectedEvent = function(eventid){
		var response = $q.defer();
		dataSvc.getArray({'query':'eventDataRaw','eventid':eventid}).then(function(resp){
			let evt = resp[0];
			if(!evt){
				response.resolve(null);
				return;
			} 
			$rootScope.curEventName = evt.name;
			delete evt.categories;
			erSessionData.curEvent = evt;
			angular.forEach(evt,function(prop, key){
				evt[key] = (prop || '').toString().replace(/\"/g,'');
			});
			$http({
				"url": '/setUserData.php',
				"method": 'POST',
				"data": $.param({"curEvent":evt}),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
			}).then(function(){
				dataSvc.getArray({'query':'eventPagesFromId','eventid':evt.id})
				.then(function(resp){
					$rootScope.navPages = resp;
					$http({
						"url": '/setUserData.php',
						"method": 'POST',
						"data": $.param({"navPages":$rootScope.navPages}),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded"}
					}).then(function(){
						response.resolve();
					});
				});
			});
		});
		return response.promise;
	}; // End setSelectedEvent()

	//get id of account from account slug (e.g. /easyregpro.com/psugevents = "psugevents")
	this.getAccountIdFromURL = function(){
		var response = $q.defer();
		let accountid = 0;
		let path = window.location.pathname;
		path = path.split('/');
		if(path.length < 2){
			response.resolve(accountid);
		}else{
			let slug = path[path.length -1];
			dataSvc.getArray({'query':'accountList'}).then(function(acctList){
				acctList.forEach(function(acct){
					if(acct.slug && acct.slug.toLowerCase() == slug.toLowerCase()){
						accountid = acct.id;
						let postData = {
							"accountid":accountid,
							"accountLogo":acct.web_logo,
							"sponsors_enabled":acct.sponsors_enabled == '1'
						};
						postForm('/set_session_account.php', postData).then(() => {
							response.resolve(accountid);
							document.dispatchEvent(new Event('acctChange'));
						});
					}
				});
				if(accountid == 0){
					let evtSlug = path[path.length - 2];
					dataSvc.getArray({'query':'eventData','slug':evtSlug}).then(function(evtRes){
						if(evtRes[0] && evtRes[0].accountid){
							accountid = evtRes[0].accountid;
							response.resolve(evtRes[0].accountid);
							acctList.forEach(function(acct){
								if(acct.id == accountid){
									let postData = {
										"accountid":accountid,
										"accountLogo":acct.web_logo,
										"sponsors_enabled":acct.sponsors_enabled == '1'
									};
									postForm('/set_session_account.php', postData).then(() => {
										response.resolve(accountid);
										document.dispatchEvent(new Event('acctChange'));
									});
								}
							});
						}else{
							response.resolve(accountid);
						}
					});
				} 
			});
		}
		return response.promise;
	};

	this.scrollToElement = function(element){
		$([document.documentElement, document.body]).animate({
			scrollTop: $(element).offset().top - 50
		}, 600);
	};

	//contcatenate property values of an object into a String for searching purposes
	this.getObjectSearchString = function(obj, propList){
		var searchString = '';
		for (let key in obj) {
			if(propList && !propList.includes(key)) continue;
			if(obj.hasOwnProperty(key) && obj[key]) searchString += obj[key].toString();
		}
		return searchString.toLowerCase();
	};

	/***** DATE FUNCTIONS ******/
	this.localToMySqlDate = function(dateString){
		if(!dateString) return '';
		if(dateString){
			var dateParts = dateString.split('/');
			if(dateParts.length != 3) return "";
			if(dateParts[0].length < 2) dateParts[0] = '0' + dateParts[0];
			if(dateParts[1].length < 2) dateParts[1] = '0' + dateParts[1];
			return dateParts[2] + '-' + dateParts[0] + '-' + dateParts[1];
		}else{
			return "";
		}

	};

	this.mySqlToLocalDate = function(dateString){
		if(!dateString) return '';
		if(dateString.indexOf('/') > 0) return dateString;
		if(dateString){
			var dateParts = dateString.split('-');
			return dateParts[1] + '/' + dateParts[2].substring(0,2) + '/' + dateParts[0];
		}else{
			return "";
		}
	};

	this.today = function(){
		var curDt = new Date();
		var dd = curDt.getDate();
		var mm = curDt.getMonth() + 1; //January is 0!
		var yyyy = curDt.getFullYear();
		if(dd < 10) dd = '0' + dd;
		if(mm < 10) mm = '0' + mm;
		return mm + '/' + dd + '/' + yyyy;
	};

	erService.monthArray = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
	//parse data from mySql datetime value (YYYY-MM-DD HH24:MI:SS.0)
	//@ datestring - YYYY-MM-DD HH:MI:SS.0
	//@return {string} - desired return information (date - Mon day, time - HH:MI AM, datetime - both)
	this.getDateTimeParts = function(datestring, returnFormat){
		try{
			var returnVal;
			var parts = datestring.split(' ');
			var dt = parts[0];
			var time = parts[1];
			var returnDate, returnTime;

			returnDate = erService.monthArray[Number(dt.split('-')[1]) -1];
			returnDate += ' ' + dt.split('-')[2];

			var hr = Number(time.split(':')[0]);
			var min = time.split(':')[1];
			var am = 'AM';
			if(hr > 12){
				am = 'PM';
				hr -= 12;
			}else if(hr == 12){
				am = 'PM';
			}
			returnTime = hr + ':' + min + ' ' + am;

			if(returnFormat == 'date') return returnDate;
			else if(returnFormat == 'time') return returnTime;
			else return returnDate + ' ' + returnTime;
		}catch(err){
			return '';
		}
	}

	//create datetime string for MySQL insert
	//@ datestring - MM/DD/YYYY
	//@timestring - HH:MI AM
	//@return {string} YYYY-MM-DD HH24:MI:SS.0
	this.createDatetimeString = function(datestring, timestring){
		try{
			var returnVal;
			var dateParts = datestring.split('/');
			var retDate = dateParts[2] + '-' + dateParts[0] + '-' + dateParts[1];
			var timeParts = timestring.split(' ');
			var am = timeParts[1] == 'AM';
			var hr = Number(timeParts[0].split(':')[0]);
			var min = Number(timeParts[0].split(':')[1]);
			if(min < 10) min = '0' + min;

			if(!am && hr != 12) hr = hr + 12;
			if(hr < 10) hr = '0' + hr;
			if(hr == 12 && am) hr = '00';

			return retDate + ' ' + hr + ':' + min + ':00.0';
		}catch(err){
			return '';
		}
	}

	//provided a date range (startDate - endDate) MM/DD/YYYY, is the current date within the range?
	this.dateRangeIsCurrent = function(startDate, endDate){
		try{
			if(startDate.indexOf('-') > 0) startDate = this.mySqlToLocalDate(startDate);
			if(endDate.indexOf('-') > 0) endDate = this.mySqlToLocalDate(endDate);
			var startDateParts = startDate.split('/');
			var endDateParts = endDate.split('/');
			var curDate = new Date();
			var inputStart = new Date(startDateParts[2], Number(startDateParts[0]) - 1, startDateParts[1]);
			var inputEnd = new Date(endDateParts[2], Number(endDateParts[0]) - 1, endDateParts[1]);
			return curDate >= inputStart && curDate <= inputEnd.setDate(inputEnd.getDate() + 1);
		}catch(err){
			return false;
		}
	};

	//add days to string representation of date
	//@date - date in format mm/dd/yyyy
	this.addDays = function(date, days){
		if(!date) return '';
		days = Number(days);
		var dateParts = date.split('/');
		let dateVal = new Date(dateParts[2], Number(dateParts[0]) - 1, dateParts[1]);
		dateVal.setDate(dateVal.getDate() + days);
		return (dateVal.getMonth() + 1) + '/' + dateVal.getDate() + '/' + dateVal.getFullYear();
	};

	/************* Email ************/
	//recipient can be single address or comma-delimited list
	this.sendEmail = function(recipient, subject, body, replytoemail){
		if(!replytoemail) replytoemail = "postmaster@easyregpro.com";
		body = body.replace(/(?:\r\n|\r|\n)/g, '<br>');
		var emailSent = $q.defer();
		var emailData = {
			"address":recipient,
			"subject":subject,
			"body":body,
			"replytoemail":replytoemail
		}
		$http({
			"url": '/send_email_simple.php',
			"method": 'POST',
			"data": $.param(emailData),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		}).then(function(){
			emailSent.resolve();
		});
		return emailSent.promise;
	};

	/************* Document Upload ************/
	//upload document to web_root
	//@fileElement - jQuery input type="file"
	//@location - full path from web_root
	this.uploadDocument = function(fileElement, location, fileName){
		var response = $q.defer();
		var data = new FormData();
		data.append('document', fileElement[0].files[0]);
		data.append('location',location);
		if(fileName) data.append('fileName',fileName);
		$http({
			url: '/saveDocument.php',
			method: 'POST',
			data: data,
			transformRequest: angular.identity,
			headers: {'Content-Type': undefined},
			uploadEventHandlers: {
				progress: function(evt){
					if(evt.lengthComputable) {
						var percentComplete = (evt.loaded / evt.total) * 100;
						$('.loadingDialog .pctComplete').text(percentComplete.toFixed(2) + ' % ');
					}
				}
			}
		}).then(function(resp){
			response.resolve(resp.data == 'success' ? 'success' : 'error');
		}, function(){
			response.resolve('error');
		});
		return response.promise;
	}

	this.getStateOptions = function(){
		return ['','AA','AB','AE','AK','AL','AP','AR','AS','AZ','BC','CA','CO','CT','DC','DE','FL','FM','GA','GU','HI','IA','ID','IL','IN','KS','KY','LA','MA','MB','MD','ME','MH','MI','MN','MO','MP','MS','MT','NB','NC','ND','NE','NH','NJ','NL','NM','NS','NT','NU','NV','NY','OH','OK','ON','OR','PA','PE','PR','PW','QC','RI','SC','SD','SK','TN','TX','UT','VA','VI','VT','WA','WI','WV','WY','YT'];
	};

	this.getTimezoneOptions = function(){
		return["America/New_York","America/Chicago","America/Denver","America/Phoenix","America/Los_Angeles","America/Anchorage","America/Adak","Pacific/Honolulu"];
	};

	this.getDietaryRestrictions = function(){
		return [{"value":"","label":"None"},{"value":"gf","label":"Gluten Free"},{"value":"veg","label":"Vegetarian"}];
	};

	this.validatePassword = function(pass){
		let valid;
		if(!pass) valid = false;
		else valid = (pass.match( /[a-z]/g) && pass.match(/[A-Z]/g) && pass.match(/[0-9]/g) && pass.length >= 8);
		if(valid){
			return true;
		}else{
			let msg = "Password must meet the following requirements: <ul><li>Minimum 8 characters</li>";
			msg += "<li>At least one lower-case letter</li><li>At least one upper-case letter</li>";
			msg += "<li>At least one number</li></ul>";
			easyRegAlertService.easyRegAlert({"text":msg,"title":"Invalid Password"});
			return false;
		}
	};

	this.encrypt = function(str){
		var deferredResponse = $q.defer();
		$http({
			"url": '/er_encrypt.php',
			"method": 'POST',
			"data": $.param({"value":str}),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		}).then( res => deferredResponse.resolve(res.data) );
		
		return deferredResponse.promise;
	};

	/******* Table Functions ******/

	//function to filter columns displayed in a table based on the column headers/indexes
	erService.hasColumnDef = false;
	erService.userColumns = [];
	erService.userColPg = '';
	this.initializeColumns = function(table){
		let resp = $q.defer();
		let hdrs = table[0].querySelectorAll('th');
		let useDataAttr = Array.from(hdrs).every(th => th.hasAttribute("data-data"));
		let location = window.location.hash || window.location.pathname;
		if(!erService.userColumns.length && location != erService.userColPg){
			dataSvc.getArray({"query":"userPageColumns","page":location}).then(function(res){
				if(res.length){
					erService.userColumns = res[0].colmodel.split(',');
					if(useDataAttr){
						erService.hasColumnDef = true;
						return resp.resolve(erService.userColumns);
					} 
					var colFound = false;
					angular.forEach(erService.userColumns,function(col){
						if(col) colFound = true;
					});
					if(colFound){
						erService.hasColumnDef = true;
						displayColumns(table);
					}
				}else if(useDataAttr){
					return resp.resolve(Array.from(hdrs).map(th => th.getAttribute("data-data")));
				}
			});
		}else{
			if(useDataAttr){
				if(erService.userColumns) resp.resolve(erService.userColumns);
				else resp.resolve(Array.from(hdrs).map(th => th.getAttribute("data-data")));
			}else{
				displayColumns(table);
			}
		}
		erService.userColPg = location;
		return resp.promise;
	};

	function displayColumns(table){
		erService.loadingDialog();
		//setTimeout to give angular a minute to render table rows
		setTimeout(function(){
			table.find('td, th').hide();
			var noneFound = true;
			$.each(erService.userColumns, function(){
				if(isNaN(this)) return;
				noneFound = false;
				table.find('td:nth-child(' + this.toString() + ')').show();
				table.find('th:nth-child(' + this.toString() + ')').show();
			});
			if(noneFound) table.find('td, th').show();
			erService.closeLoading();
		}, 500);	
	}

	this.columnFilter = function(table, columns){
		erService.getUserRoles();
		var displayDiv = $('<div>');
		var colDiv, checkbox;
		var counter = 1;
		let hdrs = table[0].querySelectorAll('th');
		let useDataAttr = Array.from(hdrs).every(th => th.hasAttribute("data-data"));
		table.find('th').each(function(){
			colDiv = $('<div>');
			checkbox = $('<input type="checkbox" class="columnFilterChk" />');
			if(useDataAttr) checkbox.attr('data-idx', $(this).attr('data-data'));
			else checkbox.attr('data-idx', counter++);
			checkbox.prop('checked', $(this).is(':visible'));
			colDiv.append(checkbox);
			colDiv.append(' ' + $(this).text().replace(/[\W_]+/g," "));
			displayDiv.append(colDiv);
		});
		displayDiv.append('<button style="float:right" class="btn btn-primary saveColModel" ng-click="">Save</button>');
		displayDiv.dialog({
			modal:true,
			title:"Select Columns",
			close: () => displayDiv.remove()
		});
		$('.ui-dialog-titlebar-close').addClass('ui-icon-closethick ui-button-icon ui-icon');

		$('.columnFilterChk').change(function(){
			var show = $(this).is(':checked');
			var idx = $(this).attr('data-idx');
			if(!useDataAttr){
				table.find('td:nth-child(' + idx + '), th:nth-child(' + idx + ')').toggle(show);
			}
			if(show){
				erService.userColumns.push(idx);
				// if(columns) columns.push(idx);
				//ERIC why isn't this updating on check?
				console.log(columns)
			}else{
				erService.userColumns.splice(erService.userColumns.indexOf(idx),1);
				if(columns) columns.splice(columns.indexOf(idx),1);
			} 
		});

		$('.saveColModel').click(function(){
			erService.loadingDialog();
			let columns = [];
			$('.columnFilterChk:checked').each(function(){
				columns.push($(this).attr('data-idx'));
			});
			$http({
				"url": "/session_data.php",
				"method": "GET"
			}).then(function(response){
				let colFunction = erService.hasColumnDef ? dataSvc.updateUserColumnRecord
					: dataSvc.createUserColumnRecord;
				colFunction(response.data.userid, columns).then(function(res){
					$('.ui-dialog-titlebar-close').click();
					erService.closeLoading();
				});
			});
		});
	};//End columnFilter()

	this.attendeeLogin = function(confirmation, eventid){
		erService.loadingDialog();
		var response = $q.defer();
		if(!confirmation){
			response.resolve('No Confirmation');
			erService.closeLoading();
		}
		else{
			dataSvc.getRegistrationData(confirmation, eventid).then(function(resp){
				if(resp){
					//this call sets session variables
					postForm('/lookup_confirmation.php?confirmation=' + confirmation).then(function(responseData){
						erService.closeLoading();
						try{
							var attendee = responseData.data;
							var attData = angular.isString(attendee) ? JSON.parse(attendee) : attendee;
							response.resolve(attData);
						}catch(e){
							response.resolve(false);
						}
					});
				}else{
					erService.closeLoading();
					response.resolve(false);
				}
			});
		}
		return response.promise;
	};

	//tinyMce WYSIWYG initialization
	this.initTinyMce = function(elementId, showRegButtons){
		let toolbarBtns = "code preview | undo redo | formatselect | fontselect | fontsizeselect | ";
		toolbarBtns +=" bold italic underline strikethrough forecolor backcolor | subscript superscript | ";
		toolbarBtns += "subscript superscript | numlist bullist | alignleft aligncenter alignright alignjustify | ";
		toolbarBtns += "outdent indent | paste searchreplace | ";
		toolbarBtns += "link image media charmap insertdatetime emoticons hr | table tabledelete | ";
		toolbarBtns += "tableprops tablerowprops tablecellprops | ";
		toolbarBtns += "tableinsertrowbefore tableinsertrowafter tabledeleterow | ";
		toolbarBtns += "tableinsertcolbefore tableinsertcolafter tabledeletecol | removeformat";
		if(showRegButtons) toolbarBtns += ' | evtBtn regBtn SessBtn | invoiceBtn confBtn';
		$timeout(function(){
			tinymce.remove(`#${elementId}`);
			tinymce.init({
				selector: '#' + elementId,
				height: 350,
				plugins: ['code', 'anchor', 'autolink', 'link', 'image', 'lists', 'media','textcolor','colorpicker','image code'],
				toolbar: toolbarBtns,
				relative_urls:false,
				setup: function (editor) {
					editor.addButton('evtBtn', {
					  text: 'Event URL',
					  icon: false,
					  onclick: function () {
						editor.insertContent('[eventurl]');
					  }
					});
					editor.addButton('regBtn', {
					  text: 'Registration URL',
					  icon: false,
					  onclick: function () {
						editor.insertContent('[registrationurl]');
					  }
					});
					editor.addButton('SessBtn', {
					  text: 'Session Signup URL',
					  icon: false,
					  onclick: function () {
						editor.insertContent('[sessionsignupurl]');
					  }
					});
					editor.addButton('invoiceBtn', {
					  text: 'Invoice URL',
					  icon: false,
					  onclick: function () {
						editor.insertContent('[invoiceurl]');
					  }
					});
					editor.addButton('confBtn', {
					  text: 'Confirmation Number',
					  icon: false,
					  onclick: function () {
						editor.insertContent('[confirmation]');
					  }
					});
				},
				image_title: true,
				automatic_uploads: true,
				file_picker_types: 'image',
				file_picker_callback: function (cb, value, meta) {
					var input = document.createElement('input');
					input.setAttribute('type', 'file');
					input.setAttribute('accept', 'image/*');
					input.onchange = function () {
						var file = this.files[0];
						var reader = new FileReader();
						reader.onload = function () {
							var id = 'blobid' + (new Date()).getTime();
							var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
							var base64 = reader.result.split(',')[1];
							var blobInfo = blobCache.create(id, file, base64);
							blobCache.add(blobInfo);
							cb(blobInfo.blobUri(), { title: file.name });
						};
						reader.readAsDataURL(file);
					};
					input.click();
				},
				content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
				insertdatetime_element: true,
				media_scripts: [
					{filter: 'platform.twitter.com'},
					{filter: 's.imgur.com'},
					{filter: 'instagram.com'},
					{filter: 'https://platform.twitter.com/widgets.js'}
				],
				browser_spellcheck: true,
				contextmenu: false
			});
		}, 10);
	}; //End initTinyMce()
}).filter('orderObjectBy', function() {
	return function(items, field, reverse) {
		var filtered = [];
		angular.forEach(items, function(item) {
			filtered.push(item);
		});
		filtered.sort(function (a, b) {
			if(isFinite(a[field]) && isFinite(b[field])){
				return (parseFloat(a[field]) > parseFloat(b[field]) ? 1 : -1);
			}
			a[field] = a[field] || '';
			b[field] = b[field] || '';
			return (a[field].trim().toLowerCase() > b[field].trim().toLowerCase() ? 1 : -1);
		});
		if(reverse) filtered.reverse();
		return filtered;
	};
}).filter('notLabel', function(){
	//filter out label items when displaying registration fields
	return function(items){
		var filtered = [];
		angular.forEach(items,function(item){
			if(item.type != 'l') filtered.push(item);
		});
		return filtered;
	}
}).filter('mySqlToLocalDate', function(){
	return function(dateString){
		if(dateString){
			if(dateString.indexOf('0000') >= 0) return "";
			var dateParts = dateString.split('-');
			if(dateParts.length < 3) return dateString;
			return dateParts[1] + '/' + dateParts[2].substring(0,2) + '/' + dateParts[0];
		}else{
			return "";
		}
	}
}).filter('dateToMySqlDate', function(){
	return function(dateObj){
		if(dateObj){
			const year = dateObj.getFullYear();
			const month = String(dateObj.getMonth() + 1).padStart(2, '0'); // Month is 0-indexed
			const day = String(dateObj.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		}else{
			return "";
		}
	}
}).filter('mySqlToLocalTime', function(){
	return function(dateString){
		if(dateString){
			let time = dateString.split(' ')[1];
			if(!time) return dateString;
			time = time.split(':');
			let hr = Number(time[0]);
			let am = hr < 12;
			if(hr == '00') hr = 12;
			else if(hr > 12) hr = hr - 12;
			if(hr < 10) hr = '0' + hr;
			return hr  + ':' + time[1] + ' ' + (am ? 'AM' : 'PM');
		}else{
			return "";
		}
	}
}).filter('nameFromFilepath', function(){
	return function(filepath){
		if(filepath){
			var parts = filepath.split('/');
			return parts[parts.length - 1];
		}else{
			return "";
		}
	}
}).filter('fileHref', function(){
	return filepath => {
		if(!filepath) return "";
		return '/' + filepath.split('/').map(part => encodeURIComponent(part)).join('/');
	}
}).filter('truncate', function(){
	return function(val,length){
		if(!val) return '';
		length = length || 35;
		if(val.length > length)	return val.substring(0,length) + '...';
		else return val;
	}
}).filter('trustHtml',function($sce){
  return function(html){
	return $sce.trustAsHtml((html || '').toString());
  }
}).filter('paymentMethod',function(){
  return function(method){
	if(method == 'CC') return "Credit Card";
	if(method == 'po') return "Purchase Order";
	if(method == 'check') return "Check";
	if(method == 'credit') return "Credit";
	return method;
  }
}).directive("sortArrows", function (){
	return {
		restrict: 'AEC',
		template: '<div class="noExport" style="float:left;line-height:.5;margin-top:3px">&#9653;<br/>&#9663;</div>'
	}
}).directive('datepicker', function () {
	return {
		restrict: 'AEC',
		require: 'ngModel',
		link: function (scope, element, attrs, ngModelCtrl) {
			$(element).datepicker({
				dateFormat: 'mm/dd/yy',
				onClose: function (date) {
					ngModelCtrl.$setViewValue(date);
				},
				beforeShow: function() {
					setTimeout(function(){
						$('.ui-datepicker').css('z-index', 9999);
					}, 0);
				}
			});
		}
	};
}).directive('eventSidebar',function(){
	return {
		restrict: 'AEC',
		templateUrl: '/directives/event_sidebar.html?_=' + Math.random()
	};
}).directive('topNav',function(){
	return {
		restrict: 'AEC',
		templateUrl: '/directives/top_nav.html?_=' + Math.random()
	};
}).directive('passwordRequirements',function(){
	return {
		restrict: 'AEC',
		templateUrl: '/directives/passwordRequirements.html?_=' + Math.random()
	};
}).directive('adminHeader', function(){
	return{
		restrict: 'AEC',
		templateUrl: '/directives/admin_header.html?_=' + Math.random()
	};
}).directive('adminHeader2', function(){
	return{
		restrict: 'AEC',
		templateUrl: '/directives/admin_header2.html?_=' + Math.random()
	};
}).directive('loginForm',function(){
	return {
		restrict: 'AEC',
		templateUrl: '/directives/login.html?_=' + Math.random()
	};
}).directive('attendeeLogin',function(){
	return {
		restrict: 'AEC',
		templateUrl: '/directives/attendee_login.html'
	};
}).directive('accountConfigTabs',function(){
	return {
	   restrict: 'AEC',
	   templateUrl: '/directives/account_config_tabs.html?_=' + Math.random()
   };
}).directive('vendorManagementTabs',function(){
	return {
	   restrict: 'AEC',
	   templateUrl: '/directives/vendor_mgmt_tabs.html'
   };
}).directive('schedMgmtTabs',function(){
	return {
	   restrict: 'AEC',
	   templateUrl: '/directives/sched_mgmt_tabs.html?_=' + Math.random()
   };
}).directive('eventRegTabs',function(){
	return {
	   restrict: 'AEC',
	   templateUrl: '/directives/event_reg_tabs.html?_=' + Math.random()
   };
}).directive('accountExpenseTabs',function(){
	return {
	   restrict: 'AEC',
	   templateUrl: '/directives/account_expense_mgmt_tabs.html?_=' + Math.random()
   };
}).directive('erFooter',function(){
	var yr = new Date().getFullYear();
	let logo = 'ERP-no-tag.png';
	if(erSessionData?.accountType == 'ticketing') logo = 'K12-Logo.png';
	var footer = `<div class="noPrint" style="clear:both;width:95%;margin:auto;padding:1em"><hr/>
		<div class="row"><div class="col-lg-12 copyright">
		<p style="float:left">Copyright &copy; 2015 - ${yr} Easy Reg Pro, LLC. All rights reserved.</p>
		<div style="float:right;margin-right:3em">
		<span style="color:black;margin-right:2em;vertical-align:top">Powered By </span>\
		<a href="/about/home.php">
		<img src="/img/${logo}" style="width:10em"/></a>
		</div></div></div>`;
	return {
		restrict: 'AEC',
		template: footer
	}
}).directive('reviewList',function(){
	return {
	   restrict: 'AEC',
	   templateUrl: '/directives/reviewList.html?_=' + Math.random()
   };
}).directive('timeinput', function(){
	//time input with validation for time format HH:MI AM
	return {
		require: 'ngModel',
		link: function(scope, elm, attrs, ctrl) {
			ctrl.$validators.timeformat = function(modelValue, viewValue) {
				if(!viewValue) return true;
				var vt = viewValue.split(' ');
				if(vt.length != 2) return false;
				if(vt[1].toUpperCase() != 'AM' && vt[1].toUpperCase() != 'PM') return false;
				var hm = vt[0].split(':');
				if(hm.length != 2) return false;
				if(Number(hm[0]) <= 0 || Number(hm[0]) > 12) return false;
				if(Number(hm[1]) < 0 || Number(hm[0]) > 59) return false;
				return true;
			};

			elm.on("change", function () {
				elm.val(elm.val().toUpperCase());
			});
		}
	};
}).directive('dateinput', function(){
	//date input with validation for date format (MM/DD/YYYY)
	return {
		require: 'ngModel',
		link: function(scope, elm, attrs, ctrl) {
			ctrl.$validators.dateformat = function(modelValue, viewValue) {
				if(!viewValue) return false;
				// First check for the pattern
				if(!/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(viewValue)) return false;
				// Parse the date parts to integers
				var parts = viewValue.split("/");
				var day = parseInt(parts[1], 10);
				var month = parseInt(parts[0], 10);
				var year = parseInt(parts[2], 10);
				// Check the ranges of month and year
				if(year < 1000 || year > 3000 || month == 0 || month > 12) return false;
				var monthLength = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
				// Adjust for leap years
				if(year % 400 == 0 || (year % 100 != 0 && year % 4 == 0)) monthLength[1] = 29;
				// Check the range of the day
				return day > 0 && day <= monthLength[month - 1];
			};
		}
	};
}).directive('scrollable', function(){
	return{
		restrict: "AEC",
		link: function(scope, elem, attrs, ctrl) {
			var e = $(elem);
			e.find('th').each(function(){
				if($(this).css('background-color') == 'rgba(0, 0, 0, 0)')
					$(this).css('background-color',$('body').css('background-color'));
			});
			e.find('th').css({"position":"sticky","top":"-1px","z-index":"900"});
			var wrapperDiv = $('<div class="scrollableDiv">');
			e.wrap(wrapperDiv);
		}
	}
}).directive('stringToNumber', function() {
	//Helpful if using number inputs but the model holds the numbers as strings
	// <input type="number" string-to-number ng-model="fee.amount" />
	return {
		require: 'ngModel',
		link: function(scope, element, attrs, ngModel) {
			ngModel.$parsers.push(function(value) {
				return '' + value;
			});
			ngModel.$formatters.push(function(value) {
				return parseFloat(value, 10);
			});
		}
	};
}).directive('customOnChange', function() {
	//used to listen for change on input type="file"
	//<input type="file" custom-on-change="functionName" />
	return {
		restrict: 'A',
		link: function (scope, element, attrs) {
			var onChangeHandler = scope.$eval(attrs.customOnChange);
			element.on('change', onChangeHandler);
			element.on('$destroy', function() {
				element.off();
			});
		}
	};
});
