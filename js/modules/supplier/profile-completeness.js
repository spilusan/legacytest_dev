
/**
 * Profile completeness modal window
 * @author Elvir <eleonard@shipserv.com>
 */
define(['cookie', 'modal'], 
	function(cookie, $) {
		function init(){
			$('html .profile-completeness').each(function(){
				
				$(this).find(".completionTipsBtn, .reasonBtn").fadeIn();
				$(this).find('.reasonBtn').click(function(){
					$(this).find('#pc-info')
					.ssmodal({title: 'Why do this?'});
				});
				
				$('.completionTipsBtn').click(function(e){
					e.preventDefault();
					if( $(this).closest('.pc').length > 0 ){
						var parent = $(this).closest('.pc');
						var adjustLayout = true;
					}else{
						var parent = $(this).closest('.company-icons').find('.pc');
						var adjustLayout = false;
					}
                   
					// create tha pagination link
					createPagination(parent);

					if( adjustLayout ){
						parent.find(".profile-completeness-inner")
						.css({
								'width': '420px',
								'padding': '0px',
								'border': '0px'
						})
						.removeClass('gradient');
						parent.find(".profile-completeness-inner .indicator").css({'width': '355px'});
						parent.find(".profile-completeness table").css({'width': '350px'});
						parent.find(".pcs-alert").css({
							'margin-top':'0px',
							'margin-right':'20px'
						});
					}
					parent.find(".contextual-pcs").hide();
					parent.find('.completionTipsBtn, .reasonBtn').hide();
					parent.find('.profile-completeness .todo').show();

					parent.ssmodal({title: 'Completion tips'});

					$('.ssModal.close').bind('click', function () {
						var p = $("html .profile-completeness").closest('.pc');
						closeModal(p, adjustLayout);
					});

					$('body').bind('keyup', function (e) {
						if (e.keyCode == 27) {
							e.preventDefault();
							var p = $("html .profile-completeness").closest('.pc');
							closeModal(p, adjustLayout);
						}
					});
				});
                
                $('.completionTipsBtnP').click(function(e){
                	e.preventDefault();
                    var parent = $(".pc");
                    var adjustLayout = false;
					
					// create tha pagination link
					createPagination(parent);

					if( adjustLayout ){
						parent.find(".profile-completeness-inner")
						.css({
								'width': '420px',
								'padding': '0px',
								'border': '0px'
						})
						.removeClass('gradient');
						parent.find(".profile-completeness-inner .indicator").css({'width': '355px'});
						parent.find(".profile-completeness table").css({'width': '350px'});
						parent.find(".pcs-alert").css({
							'margin-top':'0px',
							'margin-right':'20px'
						});
					}
					parent.find(".contextual-pcs").hide();
					parent.find('.completionTipsBtn, .reasonBtn').hide();
					parent.find('.profile-completeness .todo').show();

					parent.ssmodal({title: 'Completion tips'});
	
					$('.ssModal.close').bind('click', function () {
						var p = $("html .profile-completeness").closest('.pc');
						closeModal(p, adjustLayout);
					});
				});
			});
		}$(init); //Exec on document ready
		return {};
});

function closeModal(parent, adjustLayout){
	var object = parent;
	if( adjustLayout ){
		object.find(".contextual-pcs").show();
		object.find(".profile-completeness-inner")
			.css({
					'width': '410px',
					'padding': '10px',
					'border': '1px solid #CDD2DE'
					
			})
			.addClass('gradient');
		object.find(".profile-completeness-inner .indicator").css({'width': '350px'});
		object.find(".profile-completeness table").css({'width': '345px'});

		object.find(".pcs-alert").css({
			'margin-top':'-5px',
			'margin-right':'10px'
		});				

	}
	
	object.find('.completionTipsBtn, .reasonBtn').show();
	object.find('.profile-completeness .todo').hide();		
}

function createPagination(parent){
	if( parent.find(".todo-list li").length > 5 ){
		
		var totalItem = parent.find(".todo-list li").length;
		var totalPage = Math.ceil(totalItem/5);
		parent.find(".todo-pagination").show();
		
		output = '';
		output += '<a href="#" class="page prev-page hidden" page="' + 1 + '">Prev</a>';
		for(var i=1; i<=totalPage; i++){
			output += '<a href="#" class="page ' + ((i==1)?'page-selected':'') + '" page="' + i + '" totalPage="' + totalPage + '">' + i + '</a>';
		}
		output += '<a href="#" class="page next-page" page="2">Next</a>';
		
		parent.find(".todo-list li").hide();

		start=0;
		end=5;

		for (var i=start;i<end;i++)
		{
			var toFind = '.todo-list li:eq('+i+')';
			parent.find(toFind).show();
		}
		
		//parent.find(".todo-list li").filter(':gt(' + start + '),:lt(' + end +')').show();
		
		parent.find(".todo-pagination").html(output);

		parent.find(".todo-pagination .page").unbind('click').bind('click', function(e){
			e.preventDefault();
			var page = $(this).attr("page");
			page = parseInt(page);
			parent.find(".todo-list li").hide();
			parent.find(".page").removeClass("page-selected");
			parent.find(".page[page=" + page + "]").addClass("page-selected");
			parent.find(".next-page,.prev-page").removeClass("page-selected");

			if(page==1){
				start 	= 0; 
				end 	= 5;
				parent.find(".todo-list li").filter(':lt(' + end +')').show();
				parent.find(".prev-page").hide();
				parent.find(".next-page").attr('page', page+1).show();
			}else{
				start 	= (parseInt(page) * 5 ) - 5;
				end 	= (parseInt(page) * 5 ) - 1;
				for( var i=start; i<=end; i++){
					parent.find(".todo-list li").filter(':eq(' + i + ')').show();
				}
				
				parent.find(".prev-page").attr('page', page-1).show();
				if( totalPage == page ){
					parent.find(".next-page").attr('page', page+1).hide();
				}else{
					parent.find(".next-page").attr('page', page+1).show();
				}
			}
		});
	}
}