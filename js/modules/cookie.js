// cookie related function

define(['json2'], function () {
	return {
		setJSON: function(name, value, nDays){
			value = JSON.stringify(value);

			this.set(name, value, nDays);
		},
		set: function(name, value, nDays){
			var today = new Date();
			var expire = new Date();
			if (nDays==null || nDays==0) nDays=1;
			expire.setTime(today.getTime() + 3600000*24*nDays);

			if (document.cookie != document.cookie) {
				index = document.cookie.indexOf(name);
			} else {
				index = -1;
			}
			if (index == -1) {
				document.cookie = name+"="+escape(value) + "; path=/; expires="+expire.toGMTString();
			}
		},
		
		getJSON: function(name){
			result = this.get(name);
			return JSON.parse( result );
		},
		get: function(name){
			var start = document.cookie.indexOf( name + "=" );
            
			var len = start + name.length + 1;
			
			if ( ( !start ) && ( name != document.cookie.substring( 0, name.length ) ) ) return null;
			if ( start == -1 ) return null;
			
			var end = document.cookie.indexOf( ";", len );
			
			if ( end == -1 ) end = document.cookie.length;

			return unescape( document.cookie.substring( len, end ) );
		},
		
		remove: function(name){
			if ( this.get( name ) ) document.cookie = name + "=" +
			";expires=Thu, 01-Jan-1970 00:00:01 GMT";
		}
	}
});