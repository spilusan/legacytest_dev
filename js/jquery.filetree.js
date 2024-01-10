if (jQuery)(function($) {
    $.extend($.fn, {
        fileTree: function(o, h) {
            if (!o) var o = {};
            if (o.root == undefined) o.root = '';
            if (o.script == undefined) o.script = '';
            if (o.folderEvent == undefined) o.folderEvent = 'click';
            if (o.expandSpeed == undefined) o.expandSpeed = 500;
            if (o.collapseSpeed == undefined) o.collapseSpeed = 500;
            if (o.expandEasing == undefined) o.expandEasing = null;
            if (o.collapseEasing == undefined) o.collapseEasing = null;
            if (o.multiFolder == undefined) o.multiFolder = true;
            if (o.loadMessage == undefined) o.loadMessage = 'Loading...';
            $(this).each(function() {
                function showTree(c, t) {
                    $(c).addClass('wait');
                    $(".jqueryFileTree.start").remove();
                    $.get(o.script, {
                        dir: t
                    }, function(data) {
                        $(c).find('.start').html('');
                        $(c).removeClass('wait').append(data);
                        if (o.root == t) $(c).find('UL:hidden').show();
                        else $(c).find('UL:hidden').slideDown({
                            duration: o.expandSpeed,
                            easing: o.expandEasing
                        });
                        bindTree(c);
                    });
                }

                function bindTree(t) {
                    $(t).find('LI A').bind(o.folderEvent, function() {
                        if ($(this).parent().hasClass('directory')) {
                            if ($(this).parent().hasClass('collapsed')) {
                                if (!o.multiFolder) {
                                    $(this).parent().parent().find('UL').slideUp({
                                        duration: o.collapseSpeed,
                                        easing: o.collapseEasing
                                    });
                                    $(this).parent().parent().find('LI.directory').removeClass('expanded').addClass('collapsed');
                                }
                                $(this).parent().find('UL').remove();
                                showTree($(this).parent(), escape($(this).attr('rel').match(/.*\//)));
                                $(this).parent().removeClass('collapsed').addClass('expanded');

                                $(this).parent().parent().find('a').each(function(){
                                    $(this).css('color', '');
                                    $(this).css('font-weight', '');
                                });

                                $(this).css('color', 'black');
                                $(this).css('font-weight', 'bold');
                                var catId = $(this).attr('rel');
                                var folderId = $(this).attr('name');
                                var tnid = $(this).attr('id');
                                var query = $('input#query').attr('value');
                                $('.window').hide();
                                $('.window2').hide();
                                $('#mask').hide();
                                if (query == '' || query == 'browse' || query == '?dir' || query == "Search Catalogue") {
                                    /*
                                    var $element = $(this);
                                    var $demo = $('.demo');
                                    */
                                    $.get('/supplier/catalogue/format/html/', {
                                        catId: catId,
                                        folderId: folderId,
                                        tnid: tnid,
                                        itemRows: 10
                                    }, function(data) {

                                        $(".content_body").html(data);
                                        /*
                                        var heightBefore = $demo.height();

                                        var contentHeight = Math.trunc($('.content_body').height());
                                        if (contentHeight < 315) {
                                            contentHeight = 315;
                                        }

                                        $demo.css('max-height', contentHeight + 'px');

                                        if (heightBefore !==  $demo.height() && !isVisible($demo, $element)) {
                                            if (heightBefore >  $demo.height()) {
                                                $(window).scrollTop(0);
                                            }
                                            $($element)[0].scrollIntoView();
                                        }
                                        */
                                    });
                                } else {
                                    $.get('/supplier/catalogue-search/format/html/', {
                                        catId: catId,
                                        folderId: folderId,
                                        tnid: tnid,
                                        itemRows: 50,
                                        query: query
                                    }, function(data) {
                                        $(".content_body").html(data);
                                    });
                                }
                            } else {
                                $('.window').hide();
                                $('.window2').hide();
                                $('#mask').hide();
                                $(this).parent().find('UL').slideUp({
                                    duration: o.collapseSpeed,
                                    easing: o.collapseEasing
                                });
                                $(this).parent().removeClass('expanded').addClass('collapsed');
                            }
                        } else {
                            h($(this).attr('rel'));
                        }
                        return false;
                    });
                    if (o.folderEvent.toLowerCase != 'click') $(t).find('LI A').bind('click', function() {
                        return false;
                    });
                }

                function isVisible($boxElement, $el) {
                    var winTop = $($boxElement).scrollTop();
                    var winBottom = winTop + $($boxElement).height();
                    var elTop = $el.offset().top;
                    var elBottom = elTop + $el.height();
                    return ((elBottom<= winBottom) && (elTop >= winTop));
                }

                $(this).html('<ul class="jqueryFileTree start"><li class="wait">' + o.loadMessage + '<li></ul>');
                showTree($(this), escape(o.root));
            });
        }
    });
})(jQuery);