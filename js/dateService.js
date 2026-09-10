angular.module("easyRegDateModule",[])
	.service("dateService",function(){

	var dateService = this;

	//get a date in a sortable format
	//@date - date in format mm/dd/yyyy
	this.getSortDate = function(date){
		if(!date) return '';
		var dateParts = date.split('/');
		if(dateParts[0].length < 2) dateParts[0] = '0' + dateParts[0];
		if(dateParts[1].length < 2) dateParts[1] = '0' + dateParts[1];
		return dateParts[2] + dateParts[0] + dateParts[1];
	};
});