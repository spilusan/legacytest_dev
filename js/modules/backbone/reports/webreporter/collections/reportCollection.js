define([
 	'jquery',
 	'underscore',
 	'Backbone',
 	'../models/reportModel'
], function(
	$, 
	_, 
	Backbone, 
	reportModel
){
	var reportCollection = Backbone.Collection.extend({
		model: reportModel,
		parse: function(response){
				if (response.rows !== undefined) {
				if(response.rows.length == 0){
					response.rows[0] = [];
					//response.rows[0]
				}
				
				response.rows[0].rowCount = response.rows_count;
				response.rows[0].totals = response.rows_total;
				if(response.suppliers){
					response.rows[0].suppliers = response.suppliers;
				}
				if(response.vessels){
					response.rows[0].vessels = response.vessels;
				}
				if(response.contacts){
					response.rows[0].contacts = response.contacts;
				}
				for (var i = 0, length = response.rows.length; i < length; i++) {
					this.push(response.rows[i]);
				}
			}
			
			return this.models;	
		}
	});

	return reportCollection;
});