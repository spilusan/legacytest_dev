/*
 * jQuery history plugin
 * Wrapped as require module & compressed - kbennett@shipserv.com
 * http://tkyk.github.com/jquery-history-plugin/
 */
define(["libs/jquery-1.7.1"],function(a){function d(c){function e(b){var c=new RegExp(a.map(b,encodeURIComponent).join("|"),"ig");return function(a){return a.replace(c,decodeURIComponent)}}function d(a){if(a===true){return function(a){return a}}if(typeof a=="string"&&(a=e(a.split("")))||typeof a=="function"){return function(b){return a(encodeURIComponent(b))}}return encodeURIComponent}c=a.extend({unescape:false},c||{});b.encoder=d(c.unescape)}var b={put:function(a,b){(b||window).location.hash=this.encoder(a)},get:function(b){var c=(b||window).location.hash.replace(/^#/,"");try{return a.browser.mozilla?c:decodeURIComponent(c)}catch(d){return c}},encoder:encodeURIComponent};var c={id:"__jQuery_history",init:function(){var b='<iframe id="'+this.id+'" style="display:none" src="javascript:false;" />';a("body").prepend(b);return this},_document:function(){return a("#"+this.id)[0].contentWindow.document},put:function(a){var c=this._document();c.open();c.close();b.put(a,c)},get:function(){return b.get(this._document())}};var e={};e.base={callback:undefined,type:undefined,check:function(){},load:function(a){},init:function(a,b){d(b);f.callback=a;f._options=b;f._init()},_init:function(){},_options:{}};e.timer={_appState:undefined,_init:function(){var a=b.get();f._appState=a;f.callback(a);setInterval(f.check,100)},check:function(){var a=b.get();if(a!=f._appState){f._appState=a;f.callback(a)}},load:function(a){if(a!=f._appState){b.put(a);f._appState=a;f.callback(a)}}};e.iframeTimer={_appState:undefined,_init:function(){var a=b.get();f._appState=a;c.init().put(a);f.callback(a);setInterval(f.check,100)},check:function(){var a=c.get(),d=b.get();if(d!=a){if(d==f._appState){f._appState=a;b.put(a);f.callback(a)}else{f._appState=d;c.put(d);f.callback(d)}}},load:function(a){if(a!=f._appState){b.put(a);c.put(a);f._appState=a;f.callback(a)}}};e.hashchangeEvent={_init:function(){f.callback(b.get());a(window).bind("hashchange",f.check)},check:function(){f.callback(b.get())},load:function(a){b.put(a)}};var f=a.extend({},e.base);if(a.browser.msie&&(a.browser.version<8||document.documentMode<8)){f.type="iframeTimer"}else if("onhashchange"in window){f.type="hashchangeEvent"}else{f.type="timer"}a.extend(f,e[f.type]);a.history=f;return a})