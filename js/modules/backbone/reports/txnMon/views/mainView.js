define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.uniform',
	'jqueryui/datepicker',
	'libs/jquery.tools.overlay.modified',
], function(
	$,
	_, 
	Backbone,
	Hb,
	Uniform,
	DatePicker,
	Modal
){
	var txnMonView = Backbone.View.extend({
		el: $('body'),
		events: {
		},
		initialize: function() {
            var thisView = this;
			this.monthShortNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $(document).ready(function(){
	            if (!($.browser.msie &&  $.browser.version < 8)) {
	            	$('select').uniform();
                    $('input[type="radio"]').uniform(); 
                    $('input[type="checkbox"]').uniform(); 
	            }
	            
	            //These function had to be moved here, as an IE bug fires the initalize event before the actual page was loaded, and events randomly worked or not
	            $('input.date').datepicker({ 
					autoSize: false,
					dateFormat: 'dd-M-yy'
				});

				$('from[name="formName"]').submit(function(e){
					thisView.submitForm(e);
				});
				
				$('.resend').click(function(e){
					thisView.reSendRFQ(e);
				});
				
				$('.resendBtn').click(function(e){
					thisView.sendRFQ(e);
				});
	            $('.autorem').click(function(e){
	            	thisView.showAutoRemDetails(e);
	            });
	            
				$('.attachmentDetail').click(function(e){
					thisView.attachmentDetail(e);
				});
				
				$('input.date').blur(function(e){
					thisView.datePickerBlur(e);
				});
				
				$('#searchform').submit(function(e){
					thisView.searchFormSubmit();
				});
				
				$('#changetimezone').click(function(e){
					thisView.changeTimezone(e);
				});
				
				$('#timezonesave').click(function(e){
					thisView.saveTimezone(e);
				});
				
				$('#daterange').change(function(e){
					thisView.dateRangeChange(e);
				});
				
				$('#dateto, #datefrom').change(function(e){
					thisView.resetDateRange(e);
				});
				
				$('a.customerservice').click(function(e){
					thisView.openCustomerService(e);
				});
				
				$('a.help').click(function(e){
					thisView.openHelp(e);
				});
				
				$('#buyerbranch').change(function(e){
					thisView.buyerBranchSelect(e);
				});
				
	            $('.paginate').click(function(e){
	            	thisView.paginateClick(e);
	            });
	            
	            $('.cancelRfq').click(function(e){
	            	thisView.cancelRFQShowModa(e);
	            });
	
	            $('.cancelRfqBtn').click(function(e){
	            	thisView.cancelRFQ(e);
	            });
	
	            $('.unlockBtn').click(function(e){
	            	thisView.unlock(e);
	            });
	            
	            $('.unlockerBtn').click(function(e){
	            	thisView.unlockClick(e);
	            });
			
            });
			
			this.render();

            $(window).resize(function(){
                thisView.fixHeight(); 
            });
  
            $(document).ready(function(){
            	thisView.fixHeight(); 
            	
            });
		},

		render: function(){
            var thisView = this;
			//set datePicker values to be able to rollback
		    $('input.date').each(function(){
		        $(this).data('prev-valid-date', $(this).val());
		    });
		},

		submitForm: function(e){
			e.preventDefault();
		}, 

		reSendRFQ: function(e){
			/* TODO add popup functionality here */
			e.preventDefault();
			var sender = $(e.currentTarget);
			var $resendModal = $('#resendmodal');
			
		       var data = $(sender).closest('tr').data();
		       data.atttype = data.doc_type == 'RFQ' ? 'RFQ_SUB' : 'ORD_SUB';
		       $resendModal.data({'docdata': data});
		       
		       $resendModal.find('h3').text("Resend " + data.doc_type);
		       $resendModal.find('.loading.spinner').show();
		       $resendModal.find('form, .modal-footer').hide();

				this.openDialog("#resendmodal");
				
		       $.ajax({url: '/essm/transactionhistory/getresendemail', data: data, success: function(result){
		           $resendModal.find('#email1').val(result.email);
		           $resendModal.find('.loading.spinner').hide();
		           $resendModal.find('form, .modal-footer').show();
		       }});

		},
		sendRFQ: function(e) {
				e.preventDefault();
				var $resendModal = $('#resendmodal');
			       var data = $resendModal.data('docdata');
			       $.extend(data, {email1: $resendModal.find('#email1').val(), email2: $resendModal.find('#email2').val(), mckey: $('#mckey').val()});
			       $.ajax({url: '/essm/transactionhistory/resendemail', data: data, success: function(result){
			    	   $("#resendmodal").fadeOut(1200);
			           location.reload();
			       }});
		
		}, 
		
        showAutoRemDetails: function(e)
        {
            e.preventDefault();
            var sender = $(e.currentTarget);
            $("#modalReminderTotal").html($(sender).data('total'));
            $("#modalReminderLastSent").html($(sender).data('lastsent'));
            this.openDialog("#showAutoRemDetailsModal");

        },

        openDialog: function(dialog) { 
            $(dialog).overlay({
                mask: 'black',
                left: 'center',
                fixed: false,

                onBeforeLoad: function() {
                    var windowWidth = $(window).width();
                    var modalWidth = $(dialog).width();
                    var posLeft = windowWidth/2 - modalWidth/2;

                    $(dialog).css('left', posLeft);
                }
            });

            $(dialog).overlay().load();

        },
        
        attachmentDetail: function(e) {
            e.preventDefault();
    		var sender = $(e.currentTarget);
			var $attachmentModal = $('#attachmentModal');
	        var data = $(sender).closest('tr').data();
            data.atttype = data.doc_type == 'RFQ' ? 'RFQ_SUB' : 'ORD_SUB';
            $attachmentModal.data({'docdata': data});
            $attachmentModal.find('h3').text("Attachments for " + data.doc_type);
            $attachmentModal.find('.loading.spinner').show();
            $attachmentModal.find('form, .modal-footer').hide();
            this.openDialog('#attachmentModal');
            $("#listOfAttachment").html($("#attachmentList" + $(sender).attr("attTxnId")).html());
        },
        
        validateDatepicker: function(dp) {
        	
            function dateExists(date, month, year){
                var d = new Date(year, month, date);
                return d.getDate() === parseInt(date); //parseInt makes sure it's an integer.
            }
            
            function dateInFuture(date, month, year){
                var d = new Date(year, month, date),
                    today = new Date();
                    
                today.setHours(0,0,0,0);
                
                return d > today;
            }
            
            var regexp = /^\b0*([1-9]|[12][0-9]|3[01])\b-(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-(\d{4})$/i;
            if (!regexp.test($(dp).val())) {
                //Date invalid, roll back to last valid date
                $(dp).val($(dp).data('prev-valid-date'));
                
            }else {
                
                //Make sure day exists in month
                var parts = regexp.exec($(dp).val()),
                    day = parts[1],
                    month = jQuery.inArray( parts[2].charAt(0).toUpperCase() + parts[2].substr(1,2), this.monthShortNames  ),
                    /* month = this.monthShortNames.indexOf(parts[2].charAt(0).toUpperCase() + parts[2].substr(1,2)), */
                    year = parts[3]; 
                if(!dateExists(day, month, year)) {
                    //Set to last valid day of month
                    day = new Date(year, month + 1, 0).getDate();
                    $(dp).val(day + '-' + parts[2] + '-' + year);
                }
                
                //Make sure date is not in the future
                if(dateInFuture(day, month, year)) {
                    var today = new Date();
                    $(dp).val(this.formatDate(today));
                }
            }
            
            //add leading zero if missing
            if($(dp).val().length == 10) {
                $(dp).val('0' + $(dp).val());
            }
            
            //correct case of month if necessary
            $(dp).val( $(dp).val().substr(0, 3) + $(dp).val().substr(3, 1).toUpperCase() + $(dp).val().substr(4,7).toLowerCase() );
            
            $(dp).data('prev-valid-date', $(dp).val());
        
        },

        datePickerBlur : function(e) {
            if ($(e.currentTarget).attr('name') !== 'qotdeadline' || $(e.currentTarget).val() !== '') {
                this.validateDatepicker($(e.currentTarget));
            }
        },
        
        formatDate : function(date) {
            var dateString = (date.getDate() < 9) ? '0' + String(date.getDate()) : String(date.getDate());
            dateString += '-';
            dateString += this.monthShortNames[date.getMonth()];
            dateString += '-';
            dateString += date.getFullYear();
            return dateString;
        },
        
        searchFormSubmit : function(e) {
        	//validate all date picker before we submit the form
            $('input.datep').each(function(i, dp){
                if ($(dp).attr('name') !== 'qotdeadline' || $(dp).val() !== '') {
                    validateDatepicker(dp);
                }
            });
        },
        
        changeTimezone : function(e) {
        	//in case of Timezone link is clicked
            e.preventDefault();
            this.openDialog("#timezonemodal");
        },
        
        saveTimezone : function(e) {
            e.preventDefault();
            var $form = $('#searchform');
            $form[0].reset();
            $('#timezone').val($('#timezoneselect').val());
            $form.submit();
            $("#timezonemodal").fadeOut(1200);
        	
        },
        dateRangeChange : function(e) {
        	e.preventDefault();
        	//change date in from, to if date range is selected
            var range = $(e.currentTarget).val().split(' '),
            from = new Date(),
            to = new Date();

	        //If range string ends 'month(s)', change month otherwise change day
	        switch(range[1].substr(0,1)) {
	            case 'd':
	                from.setDate(from.getDate() - range[0]); 
	                break;
	            case 'w':
	                from.setDate(from.getDate() - (range[0]*7)); 
	                break;
	            case 'm':
	                from.setMonth(from.getMonth() - range[0]);
	                break;
	        }
	        
	        $('#datefrom').val(this.formatDate(from));
	        $('#dateto').val(this.formatDate(to));
        	
        }, 
        resetDateRange : function(e) {
        	e.preventDefault();
        	
        	//reset date range, if date is set
        	$('#daterange').val(0);
        	var target = $('#daterange').prev();
        	//check if the target is span, (the uniform is applied for the select box)
        	if ( target.is( "span" ) ) {
	        	//Span before select also must be changed, skinned control
	        	target.html($('#daterange option').first().html());
        	}
        },
        openCustomerService : function(e) {
        	   e.preventDefault();
		       var newWin=open('/info/support/');
		       newWin.focus();
        },
        openHelp : function(e) {
        		e.preventDefault();
        		var options = 'directories=no,location=no,menubar=no,status=no,titlebar=no,toolbar=no,scrollbars=yes,width=510,height=400,resizable=no,top=20,left=450',
        		helpWindow = open("/ShipServ/HelpFiles/English/TxnMon.html", "TxnMonHelp", options);
        },
        buyerBranchSelect : function(e) {
        	e.preventDefault();
        	var sender = $(e.currentTarget);
        	$("#childIsSelected").val($(sender).find("option:selected").attr("child"));	
        },

        fixHeight: function()
        {
            var newHeight = ($('#content').height() > 422) ? $('#content').height()  +50 : 427;
            $('#body').height(newHeight);  
            $('#footer').show();

        },
        paginateClick: function(e) {
            var newPage =  parseInt($(e.currentTarget).data('id'));
            $('input[name="page"]').val(newPage);
            $('#searchform').submit();
        },

        unlock: function(e) {
            /* TODO develop calling unlock service */
            e.preventDefault();
            var sender = $(e.currentTarget);
            var modal = $('#managerUnlockModal');
            /* this.lastSelectedTd = sender.parent(); */

            modal.data({'id': $(sender).data('id')});
            modal.data({'doctype': $(sender).data('doctype')});
            modal.find('h1').text("Unlock " + $(sender).data('buyerref'));
            modal.find('h3').text("Unlock " + $(sender).data('buyerref'));
            this.openDialog("#managerUnlockModal");

        },

        unlockClick: function(e) {

            e.preventDefault();
            var thisView = this;
            var sender = $('#managerUnlockModal');
            var data = {
                'id': sender.data('id'),
                'doctype': sender.data('doctype')
            };

            sender.find('.loading.spinner').show();
            sender.find('.confirmText').hide();

            $.ajax({
                url: '/essm/transactionhistory/unlock-document',
                data: data,
                success: function(result){
                   /* thisView.lastSelectedTd.html(result.createdDate); */
                   $('.unlockBtn').each(function(){
                        if ($(this).data('id') == data.id) {
                            $(this).parent().html(result.createdDate);
                        } 
                   });
                   sender.find('.confirmText').show();
                   sender.find('.loading.spinner').hide();
                    $(sender).overlay().close();
                },
                error: function(error)
                {
                    alert('Could not store changes');
                    sender.find('.loading.spinner').hide();
                }
            });
        },

        cancelRFQShowModal: function(e)
        {
            e.preventDefault();
            var sender = $(e.currentTarget);
            var $cancelRfqModal = $('#cancelRfqModal');
            
               var data = $(sender).closest('tr').data();
               $cancelRfqModal.data({'docdata': data});
               
               $cancelRfqModal.find('h3').text("Cancel RFQ " + data.doc_type);
               $cancelRfqModal.find('.loading.spinner').show();
               $cancelRfqModal.find('form, .modal-footer').hide();

                this.openDialog("#cancelRfqModal");
               
               $.ajax({url: '/essm/transactionhistory/getcancelrfqemail', data: data, success: function(result){
                   $cancelRfqModal.find('#cancelemail1').val(result.email);
                   $cancelRfqModal.find('.loading.spinner').hide();
                   $cancelRfqModal.find('form, .modal-footer').show();
               }});
            
        },

        cancelRFQ: function(e)
        {
            e.preventDefault();
            var $cancelRfqModal = $('#cancelRfqModal');
               var data = $cancelRfqModal.data('docdata');
               $.extend(data, {email1: $cancelRfqModal.find('#cancelemail1').val(), email2: $cancelRfqModal.find('#cancelemail2').val(), mckey: $('#mckey').val()});
               $.ajax({url: '/essm/transactionhistory/cancelrfqemail', data: data, success: function(result){
                   $("#resendmodal").fadeOut(1200);
                   location.reload();
               }});
        }

	});

	return new txnMonView();
});
