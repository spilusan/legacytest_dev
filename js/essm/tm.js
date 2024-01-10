$(function(){
   //It looks like this JS is not used anymore     
   //Set up datepicker functionality on appropriate fields
   /*
   $('.datepicker').datepicker({'format' : 'yyyy-mm-dd'}).on('changeDate', function(e){
       $(this).datepicker('hide');
   });
   */
   
   var datepickerFrom = $('#datefrom').Zebra_DatePicker({
           format: 'd-M-Y',
           direction: false,
           readonly_element: false
       }),
       
       datepickerTo = $('#dateto').Zebra_DatePicker({
           format: 'd-M-Y',
           direction: false,
           readonly_element: false
       });
   
   var $resendModal = $('#resendmodal');
   var $attachmentModal = $('#attachmentModal');
   var $timezoneModal = $("#timezonemodal");
   
   var monthShortNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
   
   $resendModal.modal({show: false});
   $attachmentModal.modal({show: false});
   $timezoneModal.modal({show: false});
   
   $resendModal.find('a.close').click(function(e) {
       e.preventDefault();
       $resendModal.modal('hide');
   })
   
   $attachmentModal.find('a.close').click(function(e) {
       e.preventDefault();
       $attachmentModal.modal('hide');
   })
   
   $resendModal.find('a.send').click(function(e) {
       e.preventDefault();
       var data = $resendModal.data('docdata');
       $.extend(data, {email1: $resendModal.find('#email1').val(), email2: $resendModal.find('#email2').val(), mckey: $('#mckey').val()});
       $.ajax({url: '/essm/transactionhistory/resendemail', data: data, success: function(result){
           $resendModal.modal('hide');
           location.reload();
       }});
   });
   
   //Resend dialogue
   $('a.resend').click(function(e){
       e.preventDefault();
       var data = $(this).closest('tr').data();
       data.atttype = data.doc_type == 'RFQ' ? 'RFQ_SUB' : 'ORD_SUB';
       $resendModal.data({'docdata': data});
       
       $resendModal.find('h3').text("Resend " + data.doc_type);
       $resendModal.find('.loading.spinner').show();
       $resendModal.find('form, .modal-footer').hide();
       $resendModal.modal('show');
       $.ajax({url: '/essm/transactionhistory/getresendemail', data: data, success: function(result){
           $resendModal.find('#email1').val(result.email);
           $resendModal.find('.loading.spinner').hide();
           $resendModal.find('form, .modal-footer').show();
       }});
   });
   
   //Attachment dialogue
   $('a.attachmentDetail').click(function(e){
       e.preventDefault();
       var data = $(this).closest('tr').data();
       data.atttype = data.doc_type == 'RFQ' ? 'RFQ_SUB' : 'ORD_SUB';
       $attachmentModal.data({'docdata': data});
       
       $attachmentModal.find('h3').text("Attachments for " + data.doc_type);
       $attachmentModal.find('.loading.spinner').show();
       $attachmentModal.find('form, .modal-footer').hide();
       $attachmentModal.modal('show');
       $("#listOfAttachment").html($("#attachmentList" + $(this).attr("attTxnId")).html());
   });   
   
   function formatDate(date) {
       var dateString = (date.getDate() < 9) ? '0' + String(date.getDate()) : String(date.getDate());
       dateString += '-';
       dateString += monthShortNames[date.getMonth()];
       dateString += '-';
       dateString += date.getFullYear();
       return dateString;
   }
   
   //Validate date onBlur and return to previous valid value if it's invalid
   function validateDatepicker(dp) {
       
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
       
       var regexp = /^\b0*([1-9]|[12][0-9]|3[01])\b-(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-(\d{4})$/i
       if (!regexp.test($(dp).val())) {
           
           //Date invalid, roll back to last valid date
           $(dp).val($(dp).data('prev-valid-date'));
           
       }else {
           
           //Make sure day exists in month
           var parts = regexp.exec($(dp).val()),
               day = parts[1],
               month = monthShortNames.indexOf(parts[2].charAt(0).toUpperCase() + parts[2].substr(1,2)),
               year = parts[3];
           if(!dateExists(day, month, year)) {
              
               //Set to last valid day of month
               day = new Date(year, month + 1, 0).getDate();
               $(dp).val(day + '-' + parts[2] + '-' + year);
           }
           
           //Make sure date is not in the future
           if(dateInFuture(day, month, year)) {
               var today = new Date();
               $(dp).val(formatDate(today));
           }
       }
       
       //add leading zero if missing
       if($(dp).val().length == 10) {
           $(dp).val('0' + $(dp).val());
       }
       
       //correct case of month if necessary
       $(dp).val( $(dp).val().substr(0, 3) + $(dp).val().substr(3, 1).toUpperCase() + $(dp).val().substr(4,7).toLowerCase() );
       
       $(dp).data('prev-valid-date', $(dp).val());
   }
   
   $('input.datepicker').blur(function(){validateDatepicker(this)});
   $('#searchform').submit(function(){
       $('input.datepicker').each(function(i, dp){
           validateDatepicker(dp);
       });
   });
   
   //Set default prev-valid-date on datepicker inputs, so we can 'roll back' on erroneous input
   $('input.datepicker').each(function(){
       $(this).data('prev-valid-date', $(this).val());
   });
   
   //Change timezone click
   $('#changetimezone').click(function(e){
       e.preventDefault();
       $timezoneModal.modal('show');
   });
   
   $timezoneModal.find('a.close').click(function(e){
       e.preventDefault();
       $timezoneModal.modal('hide');
   })
   
   $timezoneModal.find('a.change').click(function(e) {
       e.preventDefault();
       var $form = $('#searchform');
       $form[0].reset();
       $('#timezone').val($('#timezoneselect').val());
       $form.submit();
       $timezoneModal.modal('hide');
   });
   
   //When date range is changed, update the to and from date selectors
   $('#daterange').change(function(e){
       var range = $(this).val().split(' '),
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
       
       $('#datefrom').val(formatDate(from));
       $('#dateto').val(formatDate(to));
   });
   
   //When from or to are changed, deselect date range
   $('#dateto, #datefrom').change(function(e){ $('#daterange').val(0) });
   
   //When datepicker change event fires, trigged input change event
   //$('#dateto').datepicker().on('changeDate', function(){ $('#dateto').trigger('change') });
   //$('#datefrom').datepicker().on('changeDate', function(){ $('#datefrom').trigger('change') });
   
   
   //ESSM nav link behaviours
   $('a.customerservice').click(function(){
    var newWin=open('/info/support/');
    newWin.focus();
   });
   
   $('a.help').click(function(){
       var options = 'directories=no,location=no,menubar=no,status=no,titlebar=no,toolbar=no,scrollbars=yes,width=510,height=400,resizable=no,top=20,left=450',
           helpWindow = open("/ShipServ/HelpFiles/English/TxnMon.html", "TxnMonHelp", options);
           
       helpWindow.focus();
   });
   
   $("#buyerbranch").change(function(){
	   $("#childIsSelected").val($(this).find("option:selected").attr("child"));
   });
   
});
