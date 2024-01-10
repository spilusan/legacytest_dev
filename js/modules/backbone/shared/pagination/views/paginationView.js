define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/shared/pagination/tpl/pagination.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	paginationTpl
){
	var paginationView = Backbone.View.extend({
		
		el: $('table.rfqList tbody'),

		paginationTemplate: Handlebars.compile(paginationTpl),

		initialize: function () {
			_.bindAll(this, 'render');
		},

		render: function(lastPage) {
			if(lastPage){
				lastPage = Math.ceil(lastPage/this.paginationLimit);

				html = this.paginationTemplate({
					page: this.page, 
					lastPage: lastPage
				});

				$('.pagination').html(html);

				$('.pagination ul li').removeClass('current');

	  			if(this.page === lastPage){
	  				$('.pagination ul li.next').addClass('inactive');
	  				$('.pagination ul li.next img').attr('src', '/img/pagination/arrow-right-inactive.png');
	  			}
	  			else {
	  				$('.pagination ul li.next').removeClass('inactive');
	  				$('.pagination ul li.next img').attr('src', '/img/pagination/arrow-right-active.png');
	  			}

	  			if(this.page === 1){
	  				$('.pagination ul li.prev').addClass('inactive');
	  				$('.pagination ul li.prev img').attr('src', '/img/pagination/arrow-left-inactive.png');
	  			}
	  			else {
	  				$('.pagination ul li.prev').removeClass('inactive');
	  				$('.pagination ul li.prev img').attr('src', '/img/pagination/arrow-left-active.png');
	  			}

	  			if(this.page > 3 && this.page < lastPage - 2 ) {
	  				$('.ellipsis.start').show();
					$('.first').show();
					$('.ellipsis.end').show();
					$('.last').show();

					for(i = 4; i < 9; i++){
						var j = i - 4;
						var element = '.pagination ul li:nth-child('+i+')';

						$(element).text(this.page + j - 2);
					}

					var ele = '.pagination ul li:nth-child(6)';
				}
				else if(this.page <= 3) {
					$('.pagination ul .ellipsis.start').hide();
					$('.pagination ul .first').hide();

					var idx = this.page + 3;
					var ele = '.pagination ul li:nth-child('+idx+')';
				}

				else if (this.page >= lastPage - 2){
					$('.pagination ul .ellipsis.end').hide();
					$('.pagination ul .last').hide();

					var j = lastPage - 4;

					if (j === 0){
						j=1;
					}

					for(i = 4; i < 9; i++){
						var element = '.pagination ul li:nth-child('+i+')';
						$(element).text(j);
						j++;
					}

					var idx = this.page - lastPage + 8;

					var ele = '.pagination ul li:nth-child('+idx+')';
				}

				if(lastPage <= 5) {
					$('.pagination ul .last').hide();
					$('.pagination ul .ellipsis.end').hide();
					$('.pagination ul .first').hide();
					$('.pagination ul .ellipsis.start').hide();

					for(i = 0; i <= 7; i++) {
						if(i > lastPage + 2){
							thePage = '.pagination ul li:eq('+i+')';
							$(thePage).hide();
						}
					}
					if(this.page === 4 && this.page === lastPage) {
						var idx = 7;
						var ele = '.pagination ul li:nth-child('+idx+')';
					}
				}
				
				$(ele).addClass('current');	

				$('.pagination ul li').unbind().bind('click', {context: this}, function(e) {
					var element = $(e.target);

					if($(element).hasClass('next') || $(element).parent().hasClass('next'))
					{
						if(e.data.context.page !== lastPage){
			  				var to = e.data.context.page + 1;
			  			}

			  			else{
			  				return;
			  			}
						
					}
					else if($(element).hasClass('prev') || $(element).parent().hasClass('prev'))
					{
						if(e.data.context.page !== 1){
			  				var to = e.data.context.page - 1;
			  			}

			  			else{
			  				return;
			  			}
					}
					else if($(element).hasClass('ellipsis')){
						return;
					}
					else {
						var to = $(e.target).text();
					}
	   				e.data.context.jumpToPage(to);
	  			});
			}
		},

		jumpToPage: function(to) {	
			this.page = parseFloat(to);
			this.parent.page = this.page;
			if(history.pushState){
				var url = "?page=" + this.page;
	    		history.pushState("", "", url);
	    	}
			this.parent.getData();
			$('div.rfqDisplay').hide();
			$('div.buttons').hide();
		}
	});

	return new paginationView;
});
