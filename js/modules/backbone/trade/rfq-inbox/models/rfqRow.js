define([
 	'underscore',
 	'Backbone'
], function(
	_, 
	Backbone
){
	var rfqRow = Backbone.Model.extend({
		hash: require('trade/rfq-inbox/hash'),
		tnid: require('trade/rfq-inbox/tnid'),
		userId: require('trade/rfq-inbox/userId'),

		url: function(){
      		return "/trade/rfq-data?type=rfq&id=" + this.get("pinId") + "&hash=" + this.hash + "&tnid=" + this.tnid + "&userId=" + this.userId;
    	}
	});

	return rfqRow;
});