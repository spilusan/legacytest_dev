cd js;
rm combined.js; rm compressed.js;
cat jquery.js json2.js jquery.add-review.js company-people.js my-companies.js jquery.urlparse.js jquery.auto-complete.js jquery.search-home.js jquery.cookie.js jquery.jsoncookie.js jquery.searchvariables.js jquery.easing.js jquery.metadata.js jquery.supplierdetail.js profile-privacy.js jquery.eventmap.js jquery.microprofile.js jquery.textarea.js jquery.filetree.js jquery.modalwindows.js jquery.tooltip.js jquery.footer.js jquery.more-options.js jquery.urlencode.js > combined.js;
java -jar /var/www/yuicompressor-2.4.2/build/yuicompressor-2.4.2.jar combined.js -o compressed.js;
cd ../css
rm compressed.css;
java -jar /var/www/yuicompressor-2.4.2/build/yuicompressor-2.4.2.jar myshipserv.css -o compressed.css;
