function string_to_slug(str) {
	str = str.replace(/^\s+|\s+$/g, ''); // trim
	str = str.toLowerCase();

	// remove accents, swap ñ for n, etc
	var from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
	var to   = "aaaaeeeeiiiioooouuuunc------";
	for (var i=0, l=from.length ; i<l ; i++) {
		str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
	}

	str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
	.replace(/\s+/g, '-') // collapse whitespace and replace by -
	.replace(/-+/g, '-'); // collapse dashes
	return str;
}

function exportData(table, filename){
	var data = [];
	var clone;
	var rows;

	if($('.exportHeader').length){
		data.push($('.exportHeader').text().trim());
	}

	//some tables will only need to have rows with class "selected" exported
	//if no rows have this class, all rows will be exported
	if(table.find('tr.selected').length){
		rows = table.find('tr.selected, tr.headerRow');
	}else{
		rows = table.find('tr:visible');
	}
	rows.each(function(){
		var currentRow = [];
		var colspan, commas;
		$(this).find('td:visible, th:visible').not('.noExport').each(function(){
			clone = $(this).clone();
			clone.find('.noExport').remove();
			//add commas if cell has a rowspan
			colspan = Number(clone.attr('colspan'));
			commas = '';
			if(!isNaN(colspan)){
				for (var i = 1; i < colspan; i++) {
					commas += ',';
				};
			}
			if(clone.find(':checkbox:checked').length) clone.text(clone.text() + ' yes');
			else if(clone.find(':checkbox').length) clone.text(clone.text() + ' no');
			currentRow.push('"' + $.trim(clone.text()).replace(/#/g,'').replace(/"/g,"'") + '"' + commas);
		});
		data.push(currentRow.toString());
	});

	arrayToCsv(data, filename);
}

function arrayToCsv(data, filename){
	//set the csv data
	csvData = {"name": filename + ".csv", "data":data.join('\n')};

	//download
	if(window.navigator.msSaveBlob){ //IE version
		var blob = new Blob([csvData.data],{ type: "application/csv;charset=utf-8;"});
		navigator.msSaveBlob(blob, csvData.name);
	}else{ //Chrome & FF
		var encodedUri = encodeURI("data:attachment/csv," + csvData.data);
		var link = document.createElement('a');
		link.setAttribute("href", encodedUri);
		link.setAttribute("download", csvData.name);
		document.body.appendChild(link);
		link.click();
		$(link).remove();
	}
}

window.erUtils = window.erUtils || {};
const erUtils = window.erUtils;

erUtils.sameId = (a, b) => String(a) === String(b);
erUtils.getCsrfToken = () => window.erGetCsrfToken ? window.erGetCsrfToken() : '';
erUtils.withCsrf = data => Object.assign({}, data || {}, {csrf_token: erUtils.getCsrfToken()});
erUtils.downloadFiles = (files, zipname = 'files.zip') => {
	if(!Array.isArray(files) || !files.length) return;

	const target = 'hiddenFrame';
	const $frame = $('#' + target);
	if($frame.length) $frame.attr('name', target);
	else $('<iframe>', {id: target, name: target}).hide().appendTo('body');

	const $form = $('<form>', {
		method: 'post',
		action: '/downloadFiles.php',
		target: target
	}).hide().appendTo('body');

	files.forEach((file) => {
		$('<input>', {
			type: 'hidden',
			name: 'files[]',
			value: file
		}).appendTo($form);
	});

	$('<input>', {
		type: 'hidden',
		name: 'zipname',
		value: zipname
	}).appendTo($form);

	$form[0].submit();
	setTimeout(() => $form.remove(), 1000);
};
erUtils.postRedirect = (url, data) => window.erPostRedirect(url, data);
erUtils.logout = redirectPath => window.erLogout(redirectPath);

erUtils.hasId = (list, id) =>
	Array.isArray(list) && list.some(item => erUtils.sameId(item, id));

erUtils.hasIdKey = (record, id) =>
	Object.keys(record || {}).some(key => erUtils.sameId(key, id));

$.ajaxSetup({
	headers: {
		'X-CSRF-Token': erUtils.getCsrfToken()
	}
});

$(document).ready(function(){
	if (-1 != window.location.href.indexOf("dev.")){
		$("body").css("background-color", "#e4ddd7");
	}

	//make sure sticky headers are not transparent when scrolling
	$('.sticky th').each(function(){
		if($(this).css('background-color') == 'rgba(0, 0, 0, 0)'){
			$(this).css('background-color',$('body').css('background-color'));
		}
	});

	$(document).on('click','.exportResultsBtn', function(e){
		e.preventDefault();
		var tableId = $(e.target).attr('data-table-id');
		var filename = $(e.target).attr('data-file-name');
		exportData($('#' + tableId), filename);
	});

	$("div").on( "dialogopen", function( event, ui ) {
		$('.ui-dialog-titlebar-close').addClass('ui-icon-closethick ui-button-icon ui-icon');
	});

	$(document).on('click','#leftNavToggle', function(){
		if($(this).hasClass('hideNav')){
			$(this).html('&#9658;').attr('title','Show Left Navigation');
		}else{
			$(this).html('&#9664;').attr('title','Hide Left Navigation');
		}
		$('#leftNavBar').toggle();
		$(this).toggleClass('hideNav');
	});
});
