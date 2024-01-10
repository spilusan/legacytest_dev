window.metrics;
window.mpq=[];
window.mpq.push(["init", require('mixpanel/apikey')]);
(
	function(){
		var e,d,c;
		e=function(f){
			return function(){
				window.mpq.push([f].concat(Array.prototype.slice.call(arguments,0)))
			}
		};
		d=["init","track","track_links","track_forms","register","register_once","identify","name_tag","set_config"];
		for(c=0;c<d.length;c++){
			window.mpq[d[c]]=e(d[c])
		}
	}
	
)();


function Mixpanel(){}
Mixpanel.prototype = {
	getSessionId: function(){
		return document.cookie.match(/PHPSESSID=[^;]+/)[0].replace("PHPSESSID=",'');
	},
	identify: function( id ){
		return window.mpq.identify( id );
	},
	nameTag: function(id){
		return window.mpq.name_tag(id);
	},
	register: function(data){
		return window.mpq.register(data);
	},
	track: function(data, opt, callback){
		return window.mpq.track(data, opt, callback);
	},
	log: function( text ){
		// log something to firebug's console
		if( window.console ) return console.log( text );
	}
}

define(['http://api.mixpanel.com/site_media/js/api/mixpanel.js'], function () {
	return mpq;
});