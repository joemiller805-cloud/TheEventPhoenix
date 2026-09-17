
angular.module("easyRegDataModule",['alertModule','easyRegDateModule'])
	.service("dataSvc",function($http, easyRegAlertService, dateService){

	var dataService = this;
	var errorString;
	this.tableDefinitions = {};
	$http.defaults.headers.common['X-CSRF-Token'] = erUtils.getCsrfToken();

	function formEncode(data){
		return new URLSearchParams(data || {}).toString();
	}

	function getSessionData(data){
		return angular.isString(data) ? JSON.parse(data) : data;
	}

	const getUrlEventAccountId = () => {
		const match = window.location.pathname.match(/^\/e\/([0-9]+)(?:\/|$)/);
		return match ? match[1] : '';
	};

	async function handleGenericGetFailure(resp){
		try{
			const sessionResp = await $http.get('/session_data.php');
			const sessionData = getSessionData(sessionResp.data);
			if(!sessionData.accountid){
				dataService.alertLoggedOut();
			}else{
				var error = "An Error has occurred: <br/><br/>";
				error += "Please report this error to the event administrator.<br/><br/>";
				error += resp.data;
				easyRegAlertService.easyRegAlert({"text":error,"title":"Error"});
			}
		}catch(e){
			dataService.alertLoggedOut();
		}
		return "error";
	}

/************************************* Generic create/update ****************************************/
	//@param record = {"table":"some_table":"record":{recordData}}
	this.createOrUpdateRecord = async function(record,eventid){
		var table = record.table.toLowerCase();
		var record = noQuotes(angular.copy(record.record));
		var updating = record.id;
		var cleanedField;
		var insertColList = [];
		var insertValues = [];
		var updateSetList = [];
		var queryData = {
			"command": updating ? "update" : "insert",
			"table":table,
			"eventid":eventid
		};
		errorString = (updating ? "update - " : "insert - ") + JSON.stringify(queryData);

		//retrieve columns in selected table and build query values with valid columns
		try{
			const columns = await dataService.getTableColumns(table);
			for (var property in record) {
			    if (record.hasOwnProperty(property) && columns[property] && property != 'id') {
			        cleanedField = cleanField(columns[property].data_type ,record[property]);
			        //handle error (cleanedField.valid)?
			        record[property] = cleanedField.value;
			        if(updating){
			        	updateSetList.push(property + " = " + "'" + record[property] + "'");
			        }else{
			        	insertColList.push(property);
			        	insertValues.push(record[property]);
			        }
			    }
			}

			if (updating){
				queryData.updateData = updateSetList.join(', ');
				queryData.whereClause = 'id = ' + record.id;
				queryData.updateData = queryData.updateData.replace("'now()'", "now()");
			}else{
				queryData.insertColumns = "(" + insertColList.join(',') + ") ";
				queryData.insertValues = "('" + insertValues.join("','") +  "')";
				queryData.insertValues = queryData.insertValues.replace("'now()'", "now()");
			}

			const response = await $http({
				"url": '/data_access/insert_or_update.php',
				"method": 'POST',
				"data": formEncode(queryData),
				"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
			});
			return response.data;
		}catch(err){
			errorNotification(err);
			return 'error';
		}
	};

	//helper function for createOrUpdateRecord. Clean data based on table data type
	function cleanField(dataType, value){
		value = value.toString();
		dataType = dataType.toLowerCase();
		var returnVal = {"valid":true,"value":value};
		if(dataType == 'int' || dataType == 'bigint'){
			returnVal.value = parseInt(returnVal.value);
			returnVal.valid = !isNaN(returnVal.value);
		}else if(dataType == 'tinyint'){
			if(returnVal.value == '0' || returnVal.value.trim() == 'false') returnVal.value = '0';
			else if(returnVal.value || returnVal.value.trim() == 'true') returnVal.value = '1';
			else returnVal.value = '0';
		}else if(dataType == 'decimal' || dataType == 'float'){
			returnVal.value = parseFloat(returnVal.value);
			returnVal.valid = !isNaN(returnVal.value);
		}else if(dataType == 'date' || dataType == 'datetime'){
			if(value.indexOf('/') > 0){
				returnVal.value = getSqlDtFromLclDt(value);
			}
		}
		return returnVal;
	};

	//@record = {"table":"some_table","id":"record id"}
	this.deleteRecord = async function(record,eventid){
		errorString = "delete - " +
			JSON.stringify({"table":record.table.toLowerCase(),"id":record.id,"eventid":eventid});
		try{
			const response = await $http({
				"url": "/data_access/delete_record.php",
				"method": "GET",
				"params": {"table":record.table.toLowerCase(),"id":record.id,"eventid":eventid}
			});
			return response.data;
		}catch(res){ // handle failure
			errorNotification(res);
			return 'error';
		}
	};

	//@ table = table name
	//@ filters = SQL content to go after "WHERE "
	//@ asObject = boolean - return results as object indexed by id
	this.getTableRecords = async function(table, filters, asObject, eventid){
		errorString =  "get - " + JSON.stringify({"table":table,"filter":filters,"eventid":eventid});
		try{
			const response = await $http({
				"url": "/data_access/select_all_table_recs.php",
				"method": "GET",
				"params": {"table":table,"filter":filters,"eventid":eventid}
				});
			return await normalizeTableRows(table, response.data.rows, asObject);
		}catch(res){  //handle failure
			errorNotification(res);
			return 'error';
		}
	};

	async function normalizeTableRows(table, results, asObject){
		results = results || [];
		const columns = await dataService.getTableColumns(table);
		var dateFields = [];
		angular.forEach(columns,function(col){
			if(col.data_type == 'date') dateFields.push(col.column_name);
		});
		angular.forEach(dateFields,function(field){
			angular.forEach(results,function(result){
				if(result[field]) result[field] = getLocalDtFromSqlDt(result[field]);
			});
		});
		if(asObject){
			var newResults = {};
			angular.forEach(results,function(result){
				newResults[result.id] = result;
			});
			results = newResults;
		}
		return results;
	}

	function runQueryOp(op, params){
		var payload = angular.copy(params || {});
		payload.op = op;
		return $http({
			"url": "/data_access/runQuery.php",
			"method": "POST",
			"data": formEncode(payload),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		});
	}

	function runReadOp(op, params){
		var payload = angular.copy(params || {});
		payload.op = op;
		return $http({
			"url": "/data_access/runRead.php",
			"method": "POST",
			"data": formEncode(payload),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		});
	}

	this.getPreferenceByName = async function(name, accountid){
		errorString = "read - " + JSON.stringify({"op":"getPreferenceByName","name":name,"accountid":accountid});
		try{
			const response = await runReadOp("getPreferenceByName", {"name":name,"accountid":accountid});
			return await normalizeTableRows('preferences', response.data.rows, false);
		}catch(res){
			errorNotification(res);
			return 'error';
		}
	};

	this.getCoursesByAccount = async function(asObject, accountid){
		errorString = "read - " + JSON.stringify({"op":"getCoursesByAccount","accountid":accountid});
		try{
			const response = await runReadOp("getCoursesByAccount", {"accountid":accountid});
			return await normalizeTableRows('courses', response.data.rows, asObject);
		}catch(res){
			errorNotification(res);
			return 'error';
		}
	};

	this.getUsersByAccount = async function(asObject, accountid){
		errorString = "read - " + JSON.stringify({"op":"getUsersByAccount","accountid":accountid});
		try{
			const response = await runReadOp("getUsersByAccount", {"accountid":accountid});
			return await normalizeTableRows('users', response.data.rows, asObject);
		}catch(res){
			errorNotification(res);
			return 'error';
		}
	};

	this.getVideosByAccount = async function(asObject, accountid){
		errorString = "read - " + JSON.stringify({"op":"getVideosByAccount","accountid":accountid});
		try{
			const response = await runReadOp("getVideosByAccount", {"accountid":accountid});
			return await normalizeTableRows('videos', response.data.rows, asObject);
		}catch(res){
			errorNotification(res);
			return 'error';
		}
	};

	this.getEventsByAccount = async function(asObject, accountid){
		errorString = "read - " + JSON.stringify({"op":"getEventsByAccount","accountid":accountid});
		try{
			const response = await runReadOp("getEventsByAccount", {"accountid":accountid});
			return await normalizeTableRows('events', response.data.rows, asObject);
		}catch(res){
			errorNotification(res);
			return 'error';
		}
	};

	this.getEventCourses = async function(eventid, asObject){
		errorString = "read - " + JSON.stringify({"op":"getEventCourses","eventid":eventid});
		try{
			const response = await runReadOp("getEventCourses", {"eventid":eventid});
			return await normalizeTableRows('events_courses', response.data.rows, asObject);
		}catch(res){
			errorNotification(res);
			return 'error';
		}
	};

	function postUserDml(params){
		return $http({
			"url": "/data_access/runUserDML.php",
			"method": "POST",
			"data": formEncode(params || {}),
			"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
		});
	}

	//in the case of a failed call to a resource, notify the user
	//@response - the http response of the failed call
	function errorNotification(response){
		$http.get('/session_data.php').then(function(sessionResp){
			try{
				let sessionData = getSessionData(sessionResp.data);
				if(!sessionData.accountid){
					dataService.alertLoggedOut();
				}else{
					response.statusText + ' - ' + response.status;
					var msg = "An error has occurred. <br/> \
					Please provide the following information to support.<br/> \
					<br/> <b>Query Execution Error</b> <br/>" + response.data;
					easyRegAlertService.easyRegAlert({"text":msg,"title":"Error"});
					var errorData = {
						"page":window.location.href,
						"error":response.status + ' - ' + response.statusText + ' : ' + response.data,
						"attempt": errorString
					};
					if(response && !response.status) errorData["error"] = response;
					$http({
						"url": '/sendErrorEmail.php',
						"method": 'POST',
						"data": formEncode(errorData),
						"headers" : {"Content-Type": "application/x-www-form-urlencoded" }
					});
				}
			}catch(e){
				dataService.alertLoggedOut();
			}
		}, function(){
			dataService.alertLoggedOut();
		});
	}

/************************************* Get Account Records *************************************/
	this.getImageList = async function(){
		const response = await $http({"url": "/getImageList.php", "method": "GET"});
		var documents = response.data.split(',');
		documents.pop();
		documents.sort();
		var docList = [];
		angular.forEach(documents,function(doc){
			docList.push({"path":doc, "name":doc.split('/').pop()});
		});
		return docList;
	};

	this.getSponsorEmailExists = async function(email){
		const resp = await dataService.getArray({'query':'checkSponsorExists','email':email});
		return resp[0].count > 0;
	};

	this.getAccountFeatures = async function(){
		const resp = await dataService.getArray({'query':'accountInfo'});
		let features = {};
		features.course_proposals = resp[0].enable_course_proposals == "1";
		features.docs = resp[0].enable_docs == "1";
		features.evt_requests = resp[0].enable_evt_requests == "1";
		features.staff_expense = resp[0].enable_staff_expense == "1";
		features.survey = resp[0].enable_survey == "1";
		return features;
	};

	this.getVideoReviews = async function(){
		let reviews = {"videos":{},"packages":{}};
		const resp = await dataService.getArray({'query':'videoReviews'});
		resp.forEach(function(r){
			if(r.videoid){
				reviews.videos[r.videoid] = reviews.videos[r.videoid] || {"reviewList":[]};
				reviews.videos[r.videoid].reviewList.push(r);
			}else if(r.video_packagesid){
				reviews.packages[r.video_packagesid] = reviews.packages[r.video_packagesid] || {"reviewList":[]};
				reviews.packages[r.video_packagesid].reviewList.push(r);
			}
		});
		angular.forEach(reviews.videos, video => video.averageRating = getAve(video));
		angular.forEach(reviews.packages, pkg => pkg.averageRating = getAve(pkg));
		return reviews;
		function getAve(item){
			let total = 0;
			item.reviewList.forEach(r => total += Number(r.rating));
			return Math.round((total/item.reviewList.length) * 10) / 10;
		}
	};

	this.getRegFields = async function(accountid, eventid){
		let fields = {
			"confirmation":{field:"confirmation",label:"Confirmation",displayField:true},
			"first_name":{field:"first_name",label:"First",displayField:true},
			"last_name":{field:"last_name",label:"Last",displayField:true},
			"email":{field:"email",label:"Email",displayField:true},
			"registration_type":{field:"registration_type",label:"Registration Type",displayField:true},
			"discount_code":{field:"discount_code",label:"Discount Code",displayField:true},
			"price":{field:"price",label:"Price",displayField:true},
			"discount":{field:"discount",label:"Discount",displayField:true},
			"cc_fees":{field:"cc_fees",label:"CC Fee",displayField:true},
			"serviceFee":{field:"serviceFee",label:"Service Fee",displayField:true},
			"payments":{field:"payments",label:"Payments",displayField:true},
			"balance":{field:"balance",label:"Balance",displayField:true},
			"payment_method":{field:"payment_method",label:"Payment Method",displayField:true},
			"payment_number":{field:"payment_number",label:"Payment Number",displayField:true},
			"create_date":{field:"create_date",label:"Date/Time",displayField:true},
			"business":{field:"business",label:"Business",displayField:true},
			"address1":{field:"address1",label:"Address 1",displayField:true},
			"address2":{field:"address2",label:"Address 2",displayField:true},
			"city":{field:"city",label:"City",displayField:true},
			"state":{field:"state",label:"State",displayField:true},
			"zip":{field:"zip",label:"Zip",displayField:true},
			"title":{field:"title",label:"Title",displayField:true},
			"web_address":{field:"web_address",label:"Web Address",displayField:true},
			"vendor_access":{field:"vendor_access",label:"Vendor Access",displayField:true},
			"phone":{field:"phone",label:"Phone",displayField:true},
			"ec1_name":{field:"ec1_name",label:"EC Name",displayField:true},
			"ec1_email":{field:"ec1_email",label:"EC Email",displayField:true},
			"ec1_phone_prim":{field:"ec1_phone_prim",label:"EC Phone",displayField:true},
			"ec1_phone_alt":{field:"ec1_phone_alt",label:"EC Phone Alt",displayField:true},
			"ec2_name":{field:"ec2_name",label:"EC 2 Name",displayField:true},
			"ec2_email":{field:"ec2_email",label:"EC 2 Email",displayField:true},
			"ec2_phone_prim":{field:"ec2_phone_prim",label:"EC 2 Phone",displayField:true},
			"ec2_phone_alt":{field:"ec2_phone_alt",label:"EC 2 Phone Alt",displayField:true},
			"checkin":{field:"checkin",label:"Checked In",displayField:true},
			"checkin_user":{field:"checkin_user",label:"Checked In By",displayField:true},
			"dietary_restrictions":{field:"dietary_restrictions",label:"Dietary Restrictions",displayField:true},
			"deleted":{field:"deleted",label:"Cancelled",displayField:true},
			"signupCount":{field:"signupCount",label:"Signup Count",displayField:true}
		};
		const xtrasRetrieved = dataService.getExtraRegFields(eventid, accountid).then(xtraFields => {
			xtraFields.sort((a,b) => a.sortorder - b.sortorder).forEach(xtra => {
				fields[xtra.field] = {
					field:xtra.field,
					label:xtra.label,
					options:xtra.options,
					displayField:true
				};
			});
		});
		const prefsRetrieved = dataService.getPreferenceByName('regScreenConfig', accountid)
		.then(function(res){
			if(res[0]){
				let settings = {};
				try{ settings = angular.fromJson(res[0].value); }
				catch{ settings = {}; }
				for(field in settings){
					if(settings[field].hide){
						if(field == 'title') delete fields.title;
						else if(field == 'business') delete fields.business;
						else if(field == 'address'){
							delete fields.address1;
							delete fields.address2;
							delete fields.city;
							delete fields.state;
							delete fields.zip;
						} 
						else if(field == 'phone') delete fields.phone;
						else if(field == 'vendor_access') delete fields.vendor_access;
						else if(field == 'web_address') delete fields.web_address;
						else if(field == 'dietary_restrictions') delete fields.dietary_restrictions;
						else if(field == 'ec1'){
							delete fields.ec1_name;
							delete fields.ec1_email;
							delete fields.ec1_phone_prim;
							delete fields.ec1_phone_alt;
						} 
						else if(field == 'ec2'){
							delete fields.ec2_name;
							delete fields.ec2_email;
							delete fields.ec2_phone_prim;
							delete fields.ec2_phone_alt;
						} 
					}
				}
			}
		});
		await Promise.all([xtrasRetrieved, prefsRetrieved]);
		return fields;
	} // End getRegFields()

/************************************* Get Document Records *************************************/

	this.getDocuments = async function(accountid, eventid){
		var documents, associations;
		var eventDocs = {};
		var response = {};
		var criteria =  'accountid = ' + accountid;
		const docsRetrieved = dataService.getTableRecords('documents', criteria, true).then(function(res){
			documents = res;
			response.documents = documents;
			angular.forEach(documents,function(doc){
				doc.classification = doc.classification || 9;
				doc.eventAssociations = {};
				doc.courseAssociations = {};
				doc.sectionAssociations = {};
			});
		});

		const assocRetrieved = dataService.getTableRecords('document_association', criteria, false).then(function(res){
			associations = res;
		});

		await Promise.all([docsRetrieved, assocRetrieved]);
		angular.forEach(associations,function(assoc){
			if(assoc.eventid && documents[assoc.documentid] && !assoc.courseid){
				documents[assoc.documentid].eventAssociations[assoc.id] = assoc;
				if(assoc.eventid == eventid){
					eventDocs[assoc.documentid] = documents[assoc.documentid];
				}
			}
			else if(assoc.courseid && documents[assoc.documentid]){
				documents[assoc.documentid].courseAssociations[assoc.id] = assoc;
			}
		});

		//if eventid was provided, get all courses and associate documents
		if(eventid){
			var courses = await dataService.getObject({'query':'eventCourses','eventid':eventid});
			angular.forEach(courses,function(crs){
				crs.documents = {};
				crs.visibleDocuments = {};
				if(crs.excludefromdocs == '1') delete courses[crs.id];
			});
			//Find the documents that are associated with a course for a given event
			angular.forEach(documents,function(doc){
				doc.sortDate = dateService.getSortDate(doc.date_uploaded);
				angular.forEach(doc.courseAssociations,function(assoc){
					if(courses[assoc.courseid]){
						courses[assoc.courseid].documents[assoc.id] = documents[assoc.documentid];
						if(assoc.eventid == eventid){
							courses[assoc.courseid].visibleDocuments[assoc.id] = doc;
						}
					}
				});
			});

			//if no documents are explicitly included in this course for this event,
			//show the most recent upload
			//if there is a presenter who authored the document, that receives priority
			angular.forEach(courses,function(crs){
				var mostRecentHasPresenter = false;
				if(!Object.keys(crs.visibleDocuments).length){
					var mostRecent = 0;
					angular.forEach(crs.documents,function(doc, idx){
						var curHasPresenter = hasCurrentPresenter(crs,doc);
						if(!mostRecent){
							mostRecent = idx;
							return;
						}
						var mostRecentDoc = crs.documents[mostRecent];
						if(curHasPresenter && !mostRecentHasPresenter){
							mostRecent = idx;
							mostRecentHasPresenter = true;
						}
						else if(doc.classification < mostRecentDoc.classification){
							if(mostRecentHasPresenter == curHasPresenter) mostRecent = idx;	
						}
						else if(doc.sortDate > mostRecentDoc.sortDate &&
							(doc.classification <= mostRecentDoc.classification)
						){
							if(mostRecentHasPresenter == curHasPresenter) mostRecent = idx;
						}
					});
					if(mostRecent) crs.visibleDocuments[mostRecent] =  crs.documents[mostRecent];
				}
			});
			response.courses = courses;
		}

		function hasCurrentPresenter(crs, doc){
			if(!crs.presenters || !doc.author) return false;
			var presenters = crs.presenters.toLowerCase().split(',');
			var author = doc.author.toLowerCase();
			var found = false;
			angular.forEach(presenters,pres => { if(author.indexOf(pres) >= 0) found = true; });
			return found;
		}

		if(eventid) response.eventDocuments = eventDocs;
		return response;
	};

/************************************* Get Attendee Records ****************************************/
	this.getAttendeeMatch = async function(email, lastName, firstName){
		var queryParams = {
			"query":"attendeeMatchQuery",
			"email":email,
			"last_name":lastName,
			"first_name":firstName,
		};
		const resp = await $http.get('/data_access/getQueryResults.php', {params:queryParams});
		return resp.data.rows;
	};

/************************************* Get Event Records ****************************************/
	//get event data including pages, registration types, and extra fields.
	this.getEventData = async function(slug){
		const eventAccountId = getUrlEventAccountId();
		const eventQuery = {'query':'eventData','slug':slug || ''};
		if(eventAccountId) eventQuery.accountid = eventAccountId;
		const resp = await this.getArray(eventQuery);
		if(!resp[0]){
			let txt = `Unable to retrieve event data - slug: ${slug} - accountid: ${eventAccountId}`;
			easyRegAlertService.easyRegAlert({"text":txt,"title":"Event Retrieval Error"});
			return;
		}
		let eventData = resp[0];
		eventData.extras_cap = Number(eventData.extras_cap) || 99999999;
		eventData.extras_sold = 0;
		eventData.extraOptions = {};
		eventData.pages = [];
		eventData.registrationTypes = [];
		eventData.extraFields = [];
		eventData.students_only = eventData.students_only == '1';
		eventData.student_restrictions = JSON.parse(eventData.student_restrictions || '{}');

		const pagesRetrieved = dataService.getArray({
			'query':'eventPagesFromSlug',
			'slug':slug,
			'accountid':eventData.accountid
		}).then(function(resp){
			angular.forEach(resp,function(page){
				if(window.location.href.indexOf(page.slug) >= 0) page.active = true;
				eventData.pages.push(page);
			});
		});

		const regtypesRetrieved = (async function(){
			eventData.registrationTypes = await dataService.getArray({"query":"registrationTypes","eventid":eventData.eventid});
			const resp = await dataService.getArray({"query":"registrationExtras","eventid":eventData.eventid});
			resp.forEach(function(xtra){
				xtra.factor_evt_cap = xtra.factor_evt_cap == '1';
				xtra.capacity_evt = Number(xtra.capacity_evt) || 9999999;
				xtra.capacity_reg = Number(xtra.capacity_reg) || 9999999;
				xtra.purchases = Number(xtra.purchases) || 0;
				xtra.price = Number(xtra.price) || 0;
				if(xtra.factor_evt_cap) eventData.extras_sold  += xtra.purchases;
				xtra.registration_types = xtra.registration_types.split(',');
				xtra.current = xtra.current == 'true';
				eventData.extraOptions[xtra.id] = xtra;
			});
			let eventSoldOut = eventData.extras_cap <= eventData.extras_sold;
			angular.forEach(eventData.extraOptions,function(xtra){
				xtra.soldOut = (
					(xtra.purchases >= xtra.capacity_evt) ||
					(xtra.factor_evt_cap && eventSoldOut)
				);
			});
		})();

		const extraFieldsRetrieved = dataService.getExtraRegFields(eventData.eventid, eventData.accountid).then(function(resp){
			eventData.extraFields = resp;
		});

		const courseCatalogRetrieved = dataService.getEventCourses(eventData.eventid, false)
		.then(function(res){
			eventData.hasCourseList = false;
			angular.forEach(res,function(crs){
				if(crs.include_course_list == '1') eventData.hasCourseList = true;
			});
		});

		await Promise.all([pagesRetrieved, regtypesRetrieved, extraFieldsRetrieved, courseCatalogRetrieved]);
		return eventData;
	}; // End getEventData()

	this.getEventDataFromId = async function(id){
		const resp = await this.getArray({'query':'eventDataRaw', 'eventid':id});
		var eventData = resp[0];
		eventData.registrationenddate = getLocalDtFromSqlDt(eventData.registrationenddate);
		eventData.registrationstartdate = getLocalDtFromSqlDt(eventData.registrationstartdate);
		eventData.checkindate = getLocalDtFromSqlDt(eventData.checkindate);
		eventData.enddate = getLocalDtFromSqlDt(eventData.enddate);
		eventData.startdate = getLocalDtFromSqlDt(eventData.startdate);
		return eventData;
	};

	this.getRegistrations = async function(eventid, confirmation, last_name, first_name, 
			business, range, bal, paymentNumber, noArchived) {
		let registrations= {};
		let extraRegData = [];
		let queryParams = {
			"query":"eventRegistrations",
			"eventid":eventid,
			"last_name": last_name || "",
			"first_name": first_name || "",
			"business": business || "",
			"confirmation": confirmation || "",
			"range": range || "",
			"balance": bal || "",
			"paymentNumber": paymentNumber || "",
			"noArchived": noArchived
		};
		let regs = await this.getArray(queryParams);
		let events = new Set();
		angular.forEach(regs, r => { 
			if(r.balance == '-0.00') r.balance = '0.00';
			registrations[r.id] = r;
			events.add(r.eventid);
		});

		let extrasParams = {"query":"extraRegData"};
		if(eventid != '') extrasParams.eventid = eventid;
		else extrasParams.eventids = Array.from(events).toString();
		extraRegData = await this.getArray(extrasParams)

		extraRegData.forEach(function(r){
			if(registrations[r.registrationId]) registrations[r.registrationId][r.label] = r.data;
		});
		return registrations;
	};

	this.getRegistrationData = async function(confirmation, eventid){
		let regQuery = {
			"query":"registrationData",
			"confirmation": confirmation,
			"eventid":eventid
		};
		let extraRegQuery = {
			"query":"extraRegDataByConfirmation",
			"confirmation": confirmation,
			"eventid":eventid
		};
		let extraRegOrders = {
			"query":"regExtraOrders",
			"confirmation": confirmation
		};
		const [regResp, extraData, extraOrders] = await Promise.all([
			this.getArray(regQuery),
			this.getArray(extraRegQuery),
			this.getArray(extraRegOrders)
		]);
		let reg = regResp[0];
		extraOrders.forEach(o => {
			o.price = parseFloat(o.price);
			o.quantity = parseFloat(o.quantity);
			o.capacity_reg = parseFloat(o.capacity_reg) || 99999999;
			o.capacity_evt = parseFloat(o.capacity_evt) || 99999999;
		});
		angular.forEach(extraData, field => reg[field.label] = field.data );
		if(reg) reg.extraOrders = extraOrders;
		return reg;
	};

	this.getExtraRegFields = async function(eventid, accountid) {
		const extraFields = await this.getArray({'query':'extraRegFields','eventid':eventid,'accountid':accountid});
		angular.forEach(extraFields,function(field){
			field.options = field.options.split(";")
		});
		return extraFields;
	};

	this.getEventScheduleInfo = async function(eventid, sectionsAsObject){
		const [sections, sessions] = await Promise.all([
			this.getArray({'query':'eventSectionsAggregated','eventid':eventid}),
			this.getTableRecords('sessions', 'eventid = ' + eventid, true)
		]);
		angular.forEach(sessions,function(session){
			if(sectionsAsObject) session.sections = {};
			else session.sections = [];
		});

		var cap, clone;
		sections.forEach(function(sec){
			if (sec.capacity == -1) cap = sec.roomcapacity;
			else if (sec.capacity == 0) cap = 100000;
			else cap = sec.capacity;
			sec.full = Number(sec.registrations) >= cap;
			sec.presenterphotos = sec.presenterphotos || '';
			let sessionName = '';
			if(sessions[sec.sessionid]){
				sessionName = sessions[sec.sessionid].name;
				if(sectionsAsObject) sessions[sec.sessionid].sections[sec.sectionid] = sec;
				else sessions[sec.sessionid].sections.push(sec);
			}
			if(sec.additionalsessions){
				clone = angular.copy(sec);
				clone.additionalsessions = '';
				clone.additionalsessionnames = '';
				clone.extensionOf = sessionName;
				sec.additionalsessions.split(',').forEach(function(session){
					if(sessions[session]){
						if(sectionsAsObject) sessions[session].sections[sec.sectionid] = clone;
						else sessions[session].sections.push(clone);
					}
				});
			}
		});
		return sessions;
	};//end getEventScheduleInfo()

	this.getRegTypeInfoFromConfirmation = async function(confirmation){
		var queryParams = {
			"query":"regTypeInfoFromConfirmation",
			"confirmation":confirmation
		};
		const resp = await $http.get('/data_access/getQueryResults.php', {params:queryParams});
		return resp.data.rows[0];
	}

	/************************************* Create Account Records ****************************************/
	this.createCourse = async function(course, accountid){
		var acctId = accountid || this.accountid;
		course.excludefromdocs = course.excludefromdocs || '0';
		course.sponsorid = course.sponsorid || '';
		const resp = await runQueryOp('createCourse', {
			"name": (course.name || ''),
			"abbreviation": (course.abbreviation || ''),
			"description": (course.description || ''),
			"sponsorid": course.sponsorid,
			"excludefromschedule": (course.excludefromschedule || 0),
			"excludefromdocs": (course.excludefromdocs || 0),
			"archived": (course.archived || 0),
			"accountid": acctId,
			"color": (course.color || '')
		});
		var newCourseId = resp.data;
		if(course.tracks && course.tracks.length){
			await Promise.all(course.tracks.map(track =>
				runQueryOp('addCourseTrack', {"courseid":newCourseId, "trackid":track})
			));
		}
		return newCourseId;
	};

	/************************************* User Management Records ****************************************/
	this.userSelfUpdate = async function(user){
		dmlData = {
			"query":"userSelfUpdate",
			"id": user.id,
			"last_name": user.last_name,
			"first_name": user.first_name,
			"email": (user.email || ""),
			"business": user.business,
			"address1": user.address1,
			"address2": user.address2,
			"city": user.city,
			"state": user.state,
			"zip": (user.zip || ""),
			"home_add1": user.home_add1,
			"home_add2": user.home_add2,
			"home_city": user.home_city,
			"home_state": user.home_state,
			"home_zip": (user.home_zip || ""),
			"phone": (user.phone || ""),
			"dietary_restrictions": (user.dietary_restrictions || ""),
			"photo": (user.photo || ""),
			"courses_able": user.courses_able,
			"courses_preferred": user.courses_preferred,
			"pymt_method_pref": user.pymt_method_pref,
			"pymt_method_notes": user.pymt_method_notes,
			"bio":user.bio
		};
		const res = await postUserDml(dmlData);
		if(res.data.status == 'error'){
			return 'error';
		}else{
			return res;
		}
	};

	this.userPasswordReset = async function(email, pass, sponsor){
		let query = sponsor ? "sponsorPasswordSelfUpdate" : "userPasswordSelfUpdate";
		dmlData = { "query": query, "email": email, "pass": pass};
		const res = await postUserDml(dmlData);
		if(res.data.status == 'error') return 'error';
		return res;
	};

	this.saveColmodel = async function(userid, eventid, colmodel){
		const resp = await this.getArray({"query":"userColmodelCount","eventid":eventid, "userid":userid});
		if(resp[0].count == 0){
			const insertResp = await runQueryOp('saveColmodelInsert', {"userid":userid,"eventid":eventid,"colmodel":colmodel});
			return insertResp.data;
		}else{
			const updateResp = await runQueryOp('saveColmodelUpdate', {"userid":userid,"eventid":eventid,"colmodel":colmodel});
			return updateResp.data;
		}
	};

	//return array of user columns or empty array if no record
	this.getUserColumns = async function(userid, eventid){
		const resp = await this.getArray({'query':'userColumns', 'userid':userid, 'eventid':eventid});
		return resp[0] ? resp[0].colmodel.split(',') : [];
	};

	this.createUserColumnRecord = async function(userid, colmodel){
		var page = window.location.hash || window.location.pathname;
		return await runQueryOp('createUserColumnRecord', {"userid":userid,"colmodel":colmodel,"page":page});
	};

	this.updateUserColumnRecord = async function(userid, colmodel){
		var page = window.location.hash || window.location.pathname;
		return await runQueryOp('updateUserColumnRecord', {"userid":userid,"colmodel":colmodel,"page":page});
	};

	/******************************** Create Attendee Signup Records ************************************/
	this.createSignup = async function(eventid, registrationid, sectionid, is_virtual){
		var service = this;
		var sessions = [];
		const response = await this.getObject({"query":"sectionData","sectionid":sectionid});
		sessions.push(response[sectionid].sessionid);
		const extraSessions = await service.getArray({"query":"sectionSessions","sectionid":sectionid});
		angular.forEach(extraSessions,function(result){ sessions.push(result.sessionid);	});

		if(!is_virtual){
			await Promise.all(sessions.map(session =>
				runQueryOp('deleteSignupConflictsForSession', {"registrationid":registrationid,"sessionid":session})
			));
		}

		await Promise.all(sessions.map(session =>
			runQueryOp('createSignup', {"sectionid":sectionid,"sessionid":session,"registrationid":registrationid})
		));
	};

	/************************************* Update Records ****************************************/

	this.denyRequest = async function(eventid, userid){
		return await runQueryOp('denyRequest', {"eventid":eventid,"userid":userid});
	};

	//accepted = 'accepted' or 'rejected'
	this.respondToCourseProposal = async function(id, accepted){
		return await runQueryOp('respondToCourseProposal', {"id":id,"status":accepted});
	};

	this.updateRoom = async function(eventid, room){
		return await runQueryOp('updateRoom', {
			"id":room.id,
			"name":(room.name || ''),
			"capacity":(room.capacity || ''),
			"sortorder":(room.sortorder || ''),
			"area":(room.area || ''),
			"subname":(room.subname || '')
		});
	};//end updateRoom

	this.updateSession = async function(eventid, session){
		return await runQueryOp('updateSession', {
			"id":session.id,
			"name":(session.name || ''),
			"starttime":(session.starttime || ''),
			"endtime":(session.endtime || '')
		});
	};//end updateRoom

	//Set registration to deleted or not deleted (registrations are marked deleted but not deleted)
	//@param confirmation - registration confirmation field
	//@param deleted - 1 = delete, 0 = undelete
	this.updateRegActiveStatus = async function(eventid, confirmation, del){
		return await runQueryOp('updateRegActiveStatus', {"eventid":eventid,"confirmation":confirmation,"deleted":del});
	};//end updateRoom

	this.updateCourse = async function(course){
		var currentTracks = [];
		var tracksToAdd = [];
		var tracksToDelete = [];
		const tracks = await this.getArray({'query':'courseTracks','courseId':course.id});
		angular.forEach(tracks,function(track){
			currentTracks.push(track.trackid);
		});

		//delete necessary tracks
		angular.forEach(currentTracks,function(track){
			if(course.tracks.indexOf(track) < 0){
				tracksToDelete.push(track);
			}
		});
		if(tracksToDelete.length){
			await Promise.all(tracksToDelete.map(track =>
				runQueryOp('deleteCourseTrack', {"courseid":course.id,"trackid":track})
			));
		}

		//add necessary tracks
		angular.forEach(course.tracks,function(track){
			if(currentTracks.indexOf(track) < 0){
				tracksToAdd.push(track);
			}
		});
		if(tracksToAdd.length){
			await Promise.all(tracksToAdd.map(track =>
				runQueryOp('addCourseTrack', {"courseid":course.id,"trackid":track})
			));
		}

		return await runQueryOp('updateCourse', {
			"id":course.id,
			"name":(course.name || ''),
			"abbreviation":(course.abbreviation || ''),
			"description":(course.description || ''),
			"sponsorid":(course.sponsorid || ''),
			"archived":(course.archived || 0),
			"excludefromdocs":(course.excludefromdocs || 0),
			"excludefromschedule":(course.excludefromschedule || 0),
			"color":(course.color || '')
		});
	};//end update course

	this.insertOrUpdateRegistrationType = async function(type){
		if(type.id != 0){
			return await runQueryOp('updateRegistrationType', {
				"id":type.id,
				"name":(type.name || ''),
				"price":(type.price || ''),
				"sunrise":(type.sunrise || ''),
				"sunset":(type.sunset || ''),
				"sortorder":(type.sortorder || '')
			});
		}else{
			return await runQueryOp('createRegistrationType', {
				"name":(type.name || ''),
				"price":(type.price || ''),
				"sunrise":(type.sunrise || ''),
				"sunset":(type.sunset || ''),
				"sortorder":(type.sortorder || ''),
				"eventid":type.eventid
			});
		}
	};

	this.checkinAttendee = async function(id, user, eventid){
		return await runQueryOp('checkinAttendee', {"id":id,"userid":user,"eventid":eventid});
	};

	this.checkoutAttendee = async function(id, eventid){
		return await runQueryOp('checkoutAttendee', {"id":id,"eventid":eventid});
	};

	this.createVideoAttendee = async function(attendee){
		let attendeeData = {
			"query": "createAttendee",
			"last_name": attendee.last_name || '', 
			"first_name": attendee.first_name || '',
			"email": attendee.email || '',
			"password": attendee.password || '',
			"business": attendee.business || '',
			"address1": attendee.address1 || '',
			"address2": attendee.address2 || '',
			"city": attendee.city || '',
			"state": attendee.state || '',
			"zip": attendee.zip || '',
			"title": attendee.title || ''
		};	
		const res = await postUserDml(attendeeData);
		return res.data;
	};

	async function createOrUpdateAttendee(attendee){
		let attendeeData = {
			"query": (attendee.attendeeid && attendee.attendeeid != -1) ? "updateAttendee" : "createAttendee",
			"last_name": attendee.last_name || '', 
			"first_name": attendee.first_name || '',
			"email": attendee.email || '',
			"business": attendee.business || '',
			"address1": attendee.address1 || '',
			"address2": attendee.address2 || '',
			"city": attendee.city || '',
			"state": attendee.state || '',
			"zip": attendee.zip || '',
			"title": attendee.title || '',
			"phone": attendee.phone || '',
			"web_address": attendee.web_address || '',
			"dietary_restrictions": attendee.dietary_restrictions || '',
			"ec1_email": attendee.ec1_email || '',
			"ec1_name": attendee.ec1_name || '',
			"ec1_phone_prim": attendee.ec1_phone_prim || '',
			"ec1_phone_alt": attendee.ec1_phone_alt || '',
			"ec2_email": attendee.ec2_email || '',
			"ec2_name": attendee.ec2_name || '',
			"ec2_phone_prim": attendee.ec2_phone_prim || '',
			"ec2_phone_alt": attendee.ec2_phone_alt || '',
			"id": attendee.attendeeid
		};	

		const res = await postUserDml(attendeeData);
		return res.data;
	}

	this.createOrUpdateRegistration = async function(registration, eventid, evtData){
		if(registration.attendeeid == '-1')	delete registration.attendeeid;
		//ensure the student number, if provided, has not already been used
		if(!registration.id && registration.student_number){
			let params = {
				student_number:registration.student_number, 
				eventid:eventid,
				query:'studentNumUsed'
			};
			let numUsed = await $http.get('/data_access/getQueryResults.php', {params:params})
			if(numUsed.data.rows[0].count > 0){
				return {error:'Student Number already used for this event.'};
			}
		}
		
		attendeeInfo = {"insertid":""};
		if(!Number(registration.userid)) attendeeInfo = await createOrUpdateAttendee(registration);
		let attendeeid = registration.attendeeid;
		if(!attendeeid && !Number(registration.userid)) attendeeid = attendeeInfo.insertid;
		let pymtMethod = window.location.pathname == '/seasonPasses.php' ? 'sp' : registration.payment_method;
		let regData = {
			"attendeeid": attendeeid,
			"vendor_access": (registration.vendor_access || "0"),
			"registration_typeid": (registration.registration_typeid || "0"),
			"payment_method": pymtMethod || '',
			"payment_number": (registration.payment_number || ""),
			"userid": (registration.userid || "")
		};
		if(registration.id){
			regData.query = "updateRegistration";
			regData.registration_id = registration.id;
		}else{
			registration.confirmation = await this.getConfirmation(registration, eventid);
			regData.confirmation = (registration.confirmation || "");
			regData.query = "createRegistration";
			regData.eventid = eventid;
			regData.season_pass_ordersid = (registration.season_pass_ordersid || "null");
			regData.discount_code = registration.discount_code || '';
			regData.student_number = registration.student_number || "";
		}
		
		const res = await postUserDml(regData);
		if(res.data.status == 'error'){
			return 'error';
		}
		if(!registration.id) registration.id = res.data.insertid;

		//log new registrations to regLog table
		if(evtData && regData.query == "createRegistration"){
			let regLog = {
				"query":"logRegistration",
				"referrer":document.referrer,
				"userAgent":navigator.userAgent,
				"email":registration.email,
				"payment_method":registration.payment_method,
				"reg_type":registration.registration_typeid,
				"acctName":evtData.acctName || 'NA',
				"eventName":evtData.eventName || 'NA',
				"cc_enabled":evtData.cc_enabled || 'NA',
				"req_cc_pymt":evtData.req_cc_pymt || 'NA',
				"reg_fee":evtData.reg_fee || 'NA'
			}
			regLog.registrationid = registration.id;
			regLog.confirmation = registration.confirmation;
			for(prop in regLog){
				if(typeof regLog[prop] == 'string') regLog[prop] = regLog[prop].replace("'","");
			}
			postUserDml(regLog);
		}
		
		const response = await dataService.getExtraRegFields(eventid);
		let writePromises = [];
		angular.forEach(response,function(field){
			angular.forEach(registration, function(value, key){
				if(key == field.field){
					writePromises.push(dataService.insertOrUpdateExtraRegValue(registration.id, field.id, value));
				}
			});
		});
		if(writePromises.length) await Promise.all(writePromises);
		return registration;
	}; //END createOrUpdateRegistration()

	this.updateDiscountCode = function(reg){
		if(reg.id && reg.discount_code){
			let discParams = {
				query:'updateRegDiscountCode',
				discount_code:reg.discount_code,
				registration_id:reg.id
			}
			postUserDml(discParams);
		}
	}

	this.insertOrUpdateExtraRegValue = async function(regid, fieldid, value){
		var countParams = {"query":"extraRegFieldExists","regid":regid,"fieldid":fieldid};
		const resp = await $http.get('/data_access/getQueryResults.php', {params:countParams});
		var count = resp.data.rows[0].count;
		if (typeof value == 'object') value = JSON.stringify(value);
		var queryParams = {
			"query":"insertRegExtraData",
			"regid":regid,
			"fieldid":fieldid,
			"value": value || ''
		};
		if(count > 0) queryParams.query = "updateExtraRegData";
		return await postUserDml(queryParams);
	};

	this.createOrUpdateSignup = async function(sessionid, sectionid, video_viewed, recordid){
		let signupData = {
			"query": recordid ? "userSignupUpdate" : "userSignupInsert",
			"sessionid":sessionid,
			"sectionid":sectionid,
			"recordid":recordid,
			"video_viewed":video_viewed
		}
		return await postUserDml(signupData);
	};

	this.deleteAttendeeSignup = async function(id){
		let signupData = {
			"query": "userSignupDelete",
			"recordid":id
		}
		return await postUserDml(signupData);
	};

	/************************************* Delete Records ****************************************/
	this.deleteCourse = async function(eventid, course){
		return await runQueryOp('deleteCourse', {"eventid":eventid,"id":course.events_courses_id});
	};

	this.deleteSignup = async function(eventid, registrationid, sectionid){
		return await runQueryOp('deleteSignup', {"eventid":eventid,"registrationid":registrationid,"sectionid":sectionid});
	};

	this.deleteSectionPresenter = async function(eventid, sectionid, userid){
		return await runQueryOp('deleteSectionPresenter', {"eventid":eventid,"sectionid":sectionid,"userid":userid});
	};

	this.deleteSectionSession = async function(eventid, sectionid, sessionid){
		return await runQueryOp('deleteSectionSession', {"eventid":eventid,"sectionid":sectionid,"sessionid":sessionid});
	};

	/************************************* Generic Gets ****************************************/
	this.getObject = async function(parameters, indexField){
		var obj = {};
		const resp = await $http.get('/data_access/getQueryResults.php', {params:parameters});
		if(resp.data.rows){
			angular.forEach(resp.data.rows,function(record){
				//if an index field is passed, index the object by that field
				if(indexField) obj[record[indexField]] = record;
				else obj[record.id] = record;
			});
			return obj;
		}else{
			return await handleGenericGetFailure(resp);
		}
	}

	this.getArray = async function(parameters, indexField){
		var arry = [];
		try{
			const resp = await $http.get('/data_access/getQueryResults.php', {params:parameters});
			if(resp.data.rows){
				angular.forEach(resp.data.rows,function(record){
					//if an index field is passed, index the array by that field
					if(indexField) arry[record[indexField]] = record;
					else arry.push(record);
				});
				return arry;
			}else{
				return await handleGenericGetFailure(resp);
			}
		}catch(err){
			return "error";
		}
	}

	this.postArray = async function(parameters, indexField){ // POST + X-CSRF-Token for getQueryResults writes
		var arry = []; // Same row shape as getArray so $scope bindings stay unchanged
		try{
			const resp = await $http({ // POST; common X-CSRF-Token header already set
				"url": '/data_access/getQueryResults.php', // Same PDO query endpoint
				"method": 'POST', // Mutations are GET-blocked server-side
				"data": formEncode(parameters || {}), // query + bound fields
				"headers": {"Content-Type": "application/x-www-form-urlencoded"} // Match insert_or_update
			});
			if(resp.data.rows){ // HTTP 200 {"rows":[...]}
				angular.forEach(resp.data.rows,function(record){ // Preserve getArray indexing
					if(indexField) arry[record[indexField]] = record; // Optional id map
					else arry.push(record); // Default list
				});
				return arry; // Caller reads rows[0].ok
			}else{
				return await handleGenericGetFailure(resp); // Same fail-soft as GET
			}
		}catch(err){ // 401/403/405
			return "error"; // Existing dashboard .then checks ok; .catch also used
		}
	}

	//replace single quote with two single quotes for sql
	//@param val - string or object to be cleaned
	function noQuotes(val){
		if(val === 0) return val;
		val = val || '';
		if(typeof val == 'string'){
			return val.replace(/'/g,"''");
		}else if(typeof val == 'object'){
			var obj = angular.copy(val);
			for (var property in obj) {
			    if (obj.hasOwnProperty(property)) {
			    	if(obj[property] === 0) continue;
			    	obj[property] = obj[property] || '';
			    	if(typeof obj[property] == 'string'){
			        	obj[property] = obj[property].replace(/'/g,"''");
			        }
			    }
			}
			return obj;
		}else{
			return val;
		}
	}

	this.getConfirmation = async function (registration, eventid){
		var vals = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9'];
		var confirmation;
		var parameters = {query:'confirmationNumberPrefix','eventid':eventid};
		const resp = await $http.get('/data_access/getQueryResults.php', {params:parameters});
		confirmation = '' + resp.data.rows[0].prefix.toUpperCase() + resp.data.rows[0].yr;
		confirmation += registration.first_name.substr(0,1).toUpperCase() + registration.last_name.substr(0,1).toUpperCase();
		for(var i = 0; i < 5; i++){
			confirmation += vals[getRandomInt(0,35)];
		}
		const matches = await $http.get('/data_access/getQueryResults.php', {params:{query:'confirmationCount',"confirmation":confirmation}});
		if(matches.data.rows[0].rowCount > 0){
			return await this.getConfirmation(registration, eventid);
		}
		return confirmation;
	}

	this.getTableColumns = async function(tableName){
		if(dataService.tableDefinitions[tableName]){
			return dataService.tableDefinitions[tableName];
		}else{
			var queryParams = {
				"query":"tableColumns",
				"table":tableName
			};
			const res = await this.getObject(queryParams, 'column_name');
			dataService.tableDefinitions[tableName] = res;
			return res;
		}
	}

	this.alertLoggedOut = function(){
		let message = "Your session has ended due to inactivity.";
		easyRegAlertService.easyRegConfirm({"text":message,"title":"Session Expired"}, "Login","Exit").then(function(res){
			if(res) window.location.href = '/login.php'
			else window.location.href = '/index.php'
		});
	}

	//yyyy-mm-dd to mm/dd/yyyy
	function getLocalDtFromSqlDt(dateString){
		if(!dateString) return '';
		if(dateString.indexOf('-') < 0) return dateString;
		var dateParts = dateString.split('-');
		return dateParts[1] + '/' + dateParts[2] + '/' + dateParts[0];
	}

	//mm/dd/yyyy to yyyy-mm-dd
	function getSqlDtFromLclDt(dateString){
		if(!dateString) return '';
		var dateParts = dateString.split('/');
		return dateParts[2] + '-' + dateParts[0] + '-' + dateParts[1];
	}

	function getRandomInt(min, max) {
	    return Math.floor(Math.random() * (max - min + 1)) + min;
	}
});
