angular.module("alertModule",[])
.service("easyRegAlertService",function($http, $q, $timeout){

	//Simple dialog alert
	//@param message = {"text":"msg text","title":"msg title"}
	//@param autoClose = boolean - close alert automatically after 1.5 seconds
	this.easyRegAlert = function(message, autoClose){
		var dialogContent = $('<div></div>');
		dialogContent.html(message.text);
		dialogContent.dialog({"modal":true,"title":message.title || '',"width":"500px"});
		$('.ui-dialog-titlebar-close').addClass('ui-icon-closethick ui-button-icon ui-icon');
		if(autoClose){
			$timeout(function(){
				$('div.ui-dialog').hide(500);
				$('div.ui-dialog').remove();
			}, 1500);
		}
	};

	//confirmation dialog
	//@param message - text of the dialog
	//@param yesVal - text for confirm button (yes if not provided)
	//@param noVal - text for cancel button (no if not provided)
	//@return boolean - true for confirm, false for cancel
	this.easyRegConfirm = function(message, yesVal, noVal){
		var response = $q.defer();
		var dialogContent = $('<div></div>');
		var buttons = $('<div class="button-row" style="margin-top:.5em"></div>');
		buttons.append('<button class="btn btn-danger"><span class="bi bi-x-circle"></span> ' + (noVal || 'No') + '</button>&nbsp;');
		buttons.append('<button class="btn btn-success"><span class="bi bi-check-lg"></span> ' + (yesVal || 'Yes') + '</button>');
		dialogContent.html(message.text);
		dialogContent.append(buttons);
		dialogContent.dialog({"modal":true,"title":message.title || '',"width":"500px"});
		$('.ui-dialog-titlebar-close').hide();
		dialogContent.find('.btn').click(function(){
			dialogContent.hide(500);
			dialogContent.remove();
			$timeout(() => response.resolve($(this).is('.btn-success')));
		});
		return response.promise;
	};

	//feedback dialog
	//@param message - text of the dialog
	//@param yesVal - text for confirm button (yes if not provided)
	//@param noVal - text for cancel button (no if not provided)
	//@return - false if cancel, else entered value
	this.easyRegFeedback = function(title, message, yesVal, noVal){
		var response = $q.defer();
		var dialogContent = $('<div><input type="text" style="margin-left:1em"/></div>');
		var buttons = $('<div class="button-row" style="margin-top:.5em"></div>');
		buttons.append('<button class="btn btn-danger"><span class="bi bi-x-circle"></span> ' + (noVal || 'No') + '</button>&nbsp;');
		buttons.append('<button class="btn btn-success"><span class="bi bi-check-lg"></span> ' + (yesVal || 'Yes') + '</button>');
		dialogContent.prepend(message);
		dialogContent.append(buttons);
		dialogContent.dialog({"modal":true,"title":title || '',"width":"500px"});
		$('.ui-dialog-titlebar-close').hide();
		dialogContent.find('.btn').click(function(){
			dialogContent.hide(500);
			dialogContent.remove();
			$timeout(() => {
				if($(this).is('.btn-danger')) response.resolve(false);
				else response.resolve(dialogContent.find('input').val());
			});
		});
		return response.promise;
	};
});
