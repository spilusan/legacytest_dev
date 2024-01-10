define([], function () {
    $('document').ready(function(){
        var textBodyElement = $('.wide_zone_block .zone_block_content p.body');
        var links = $('.wide_zone_block .zone_block_content ul');

        if (textBodyElement.length) {
            var excerpt = $('<p>');
            excerpt.html(textBodyElement.text().substring(1, 200) + '...');
            excerpt.addClass('zoneExcerpt');
            textBodyElement.after(excerpt);
            excerpt.show();
        }

        $('#zone-content-read-more').click(function(){
            if ($(this).html() === 'more &gt;') {
                $(this).html('less &lt;');
                excerpt.hide();
                textBodyElement.show();
                links.show();
                $('.wide_zone_block').css('height', 'auto');
            } else {
                $(this).html('more &gt;');
                textBodyElement.hide();
                links.hide();
                excerpt.show();
                $('.wide_zone_block').css('height', '65px');
            }
        });
    });
});
