/**
 * Functions for supplier profile page
 * and support adding hover over helpers
 */
define([
	'jquery',
	'Backbone',
    'libs/jquery.shipserv-tooltip'
], function(
	$, 
	Backbone,
    shTooltip
){
	var companyPeopleView = Backbone.View.extend({
		initialize: function(){
            $(document).ready(function(){
                $('.veritasInfo').shTooltip({
                    displayType : 'bottom'
                });
            });
		}
	});

	return new companyPeopleView();
});