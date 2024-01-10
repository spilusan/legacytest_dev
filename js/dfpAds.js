// Deprecated after refactoring pages home page

var googletag = googletag || {};
googletag.cmd = googletag.cmd || [];
(function() {
var gads = document.createElement('script');
gads.async = true;
gads.type = 'text/javascript';
var useSSL = 'https:' == document.location.protocol;
gads.src = (useSSL ? 'https:' : 'http:') + 
'//www.googletagservices.com/tag/js/gpt.js';
var node = document.getElementsByTagName('script')[0];
node.parentNode.insertBefore(gads, node);
})();

//Ad slot 1
googletag.cmd.push(function() {
googletag.defineSlot('/1007555/Pages_Home_AdUnit_1', [300, 250], 'div-gpt-ad-1403774289943-0').addService(googletag.pubads());
googletag.pubads().enableSingleRequest();
googletag.enableServices();
});


//Ad slot 2
googletag.cmd.push(function() {
googletag.defineSlot('/1007555/Pages_Home_AdUnit_2', [300, 250], 'div-gpt-ad-1403774939450-0').addService(googletag.pubads());
googletag.pubads().enableSingleRequest();
googletag.enableServices();
});

//Ad slot 3
googletag.cmd.push(function() {
googletag.defineSlot('/1007555/PagesHomeBanner', [300, 250], 'div-gpt-ad-1395380186935-0').addService(googletag.pubads());
googletag.pubads().enableSingleRequest();
googletag.enableServices();
});