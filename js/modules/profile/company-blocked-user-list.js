/**
 * @author Elvir <eleonard@shipserv.com>
 */
define(['jquery'], function($) {
	
	function BlockUserGrid(){}
	BlockUserGrid.prototype = {
		init: function(){
			this.tnid = window.tnid;
			this.fetchData(this.tnid, null, null, 'drawGrid');
		},
		refresh: function(){
			this.fetchData(this.tnid, null, null, 'drawGrid');
		},
		drawGrid: function( response ){
			var output = '';
			if( response.data.length > 0 ) {
				this.response = response;
				
				output += '<table>';
				output += '<thead><tr>';
					output += '<th></th>';
					output += '<th>Name</th>';
					output += '<th>Company</th>';
				output += '</tr></thead>';
				
				for(var i=0; i<response.data.length; i++){
					var row = response.data[i];
					output += '<tr class="' + ( (i%2==0)?'even':'' ) + '">';
						output += '<td><input type="checkbox" value="' + row.PBL_PSU_ID + '" name="userId[]"></td>';
						if( (row.PSU_FIRSTNAME == 'N/A' || row.PSU_FIRSTNAME == 'N\/A') && (row.PSU_LASTNAME == 'N/A' || row.PSU_LASTNAME == 'N\/A') ){
							output += '<td>' + row.PSU_EMAIL + '</td>';
						}else{
							output += '<td>' + row.PSU_FIRSTNAME + ' ' + row.PSU_LASTNAME + '<br />' + row.PSU_EMAIL + '</td>';
						}
						output += '<td>' + row.PSU_COMPANY + '</td>';
					output += '</tr>';
				}
				output += '</table>';
				$("#blocked-user-not-found").hide();
				$("#removeBlockListBtn").parent().show();
			}else{
				output = "";
				$("#blocked-user-not-found").show();
				$("#removeBlockListBtn").parent().hide();
			}
			$('#blocked-user-grid').html( output );
			
			$('#removeBlockListBtn').unbind('click').bind('click', function(){
				var userIds = [];
				
                if($('input[name="userId[]"]:checked').length==0){
                        $('.msg').show();
                }
                else {
                    $('input[name="userId[]"]:checked').each(function(){
                            $('.msg').hide();
                            window.grid.fetchData( window.grid.tnid, $(this).val(), 'd');
                    });
                }
			});
		},
		fetchData: function(tnid, uid, action, cb){
			// build up url
			var url = "";
			if( tnid != null ) url += "/tnid/" + tnid;
			if( uid != null ) url += "/uid/" + uid;
			if( action != null ) url += "/a/" + action;
			
			var objGrid = this;
			$.ajax({
				url: '/enquiry/blocked-sender' + url,
				type: 'GET',
				cache: false,
			    error: function(request, textStatus, errorThrown) {
			    	response = eval('(' + request.responseText + ')');
			    	if( response.error != "User must be logged in"){
			    		alert("ERROR " + request.status + ": " + response.error);
			    	}
			    },
				success: function( response ){
					//if( cb == 'drawGrid' )
					objGrid.drawGrid( response );
				}
			});			
		},
		log: function( text ){
			// log something to firebug's console
			if( window.console ) return console.log( text );
		}
	}

	// create the grid initiallly
	
	$(function() {
		window.grid = new BlockUserGrid();
		window.grid.init();
	});
	
	
	
});