define([
    'jquery',
    'modal',
    'help',
    'libs/fileSaver',
    'libs/jquery.validity.min',
    'libs/jquery.validity.custom.output',
    'libs/jquery-ui-1.10.3/datepicker',
    'libs/jquery-ui-1.10.3/accordion',
    'libs/waypoints.min',
    'libs/waypoints-sticky.min'
], 
function(
    $,
    modal,
    help,
    saveAs,
    validity,
    validityCustom,
    Datepicker,
    Accordion,
    Waypoints,
    Sticky
) {
    
    $(function(){
        $('.actions').waypoint('sticky', {offset: 60});
        //Set up datepicker
        $('.datepicker').datepicker({dateFormat: 'yy-mm-dd'});
    
        //Set up 'accordion' (allow multiple selection)
        $('table.gmv.report .accordion-body').hide();
        //$('table.gmv.report .accordion-head:lt(1)').addClass('ui-state-active');
    
        $('table.gmv.report .accordion-head').click(function(e){
            if(!$(e.target).hasClass('groupExpand')){
                $(this).toggleClass('ui-state-active');
                $(this).next('.accordion-body').toggle();
            }
        });
    
    
        //Handle CSV - either saveAs if available, otherwise show in modal textarea
        $('a.view.csv').click(function() {
            var isFileSaverSupported = false;
            try { isFileSaverSupported = !!new Blob(); } catch(e){}
        
            if (isFileSaverSupported) {
                var blob = new Blob([$('textarea.csv').val()]);
                saveAs(blob, "GMV Report.csv");
            } else {
                $('textarea.csv').ssmodal({});
            }
        });

        $('a.expandAll').bind('click', function(e){
            e.preventDefault();
            $('table.gmv.report .accordion-head').each(function() {
                if($(this).hasClass('ui-state-active') === false){
                    $(this).trigger('click');
                }
            });
        });

        $('a.collapseAll').bind('click', function(e){
            e.preventDefault();
            $('table.gmv.report .accordion-head').each(function() {
                if($(this).hasClass('ui-state-active') === true){
                    $(this).trigger('click');
                }
            });

        });

        $('a.expandSel').bind('click', function(e){
            e.preventDefault();
            $('table.gmv.report .accordion-head').each(function() {
                var el = $(this).find('input.groupExpand');
                if($(el).prop('checked')){
                    if($(this).hasClass('ui-state-active') === false){
                        $(this).trigger('click');
                    }
                }
            });
        });

        $('a.collapseSel').bind('click', function(e){
            e.preventDefault();
            $('table.gmv.report .accordion-head').each(function() {
                var el = $(this).find('input.groupExpand');
                if($(el).prop('checked')){
                    if($(this).hasClass('ui-state-active') === true){
                        $(this).trigger('click');
                    }
                }
            });
        });
        
        $('#waiting').hide();

        //Change the Validity date format
        $.extend($.validity.patterns, {
            date:/^\d{4}[-]\d{2}[-]\d{2}$/ 
        });
    
    	$.validity.setup({ outputMode:"custom" });
    
        $('form.report.options').validity(function(){
            $('input[name="tnid"]').require().match('integer');
            $('input[name="datefrom"], input[name="dateto"]').require().match('date');
        });
        
        // Start validation:
        $.validity.start();
        
        $('.groupId').each(function(){
        	var total = 0;
        	var groupId =  $(this).attr("groupId");
        	//$("#totalForGroup_" + groupId).html(parseFloat(totalPerGroup[groupId]));
        	$("#totalForGroup_" + groupId).html(totalPerGroup[groupId]);
        });
    })
});