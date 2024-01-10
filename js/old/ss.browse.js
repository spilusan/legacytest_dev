$.extend({
	getUrlVars: function(){
		var vars = [], hash;
		var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
		for(var i = 0; i < hashes.length; i++)
		{
			hash = hashes[i].split('=');
			vars.push(hash[0]);
			vars[hash[0]] = hash[1];
		}
		return vars;
	},
	getUrlVar: function(name){
		return $.getUrlVars()[name];
	}
});

$(document).ready(function(){
	// fetch the country json data and render into a list
	$.get('/ontology/country-browse/', { format: 'json' }, function(data){
		for (var con in data)
		{
			li = document.createElement("li");
			li.setAttribute('id', 'con-' + con);
			li.appendChild(document.createTextNode(data[con].name));
			
			ul = document.createElement("ul");
			ul.setAttribute('id', 'con-cnt' + con)
			for (cnt in data[con].countries)
			{
				cntLi = document.createElement("li");
				cntLi.setAttribute('id', 'cnt-' + cnt);
				cntLi.appendChild(document.createTextNode(data[con].countries[cnt]));
				
				// add a click event to each country that will load the ports for that country
				$('#cnt-' + cnt).live('click',function(e) {
					var cntId = $(this).attr('id');
					
					cntLi = $(this);
					
					// split out the country id
					tmp = cntId.split('-');
					countryId = tmp[1];
					
					portDisplay = $('#ports-' + countryId).css("display");
					
					if (portDisplay == 'block')
					{
						$('#ports-' + countryId).css('display', 'none');
					}
					else if (portDisplay == 'none')
					{
						$('#ports-' + countryId).css('display', 'block');
					}
					else
					{
						// fetch ports for this country and generate an HTML tree for it
						$.get('/ontology/port-browse/', { format: 'json', countryCode: countryId }, function(prtData){
							prtUl = document.createElement("ul");
							prtUl.setAttribute('id', 'ports-' + countryId);
							
							var params = $.getUrlVars();
							
							// loop through the ports and create an html list
							for (prt in prtData)
							{
								prtLi = document.createElement('li');
								prtLi.setAttribute('id', 'prt-' + prtData[prt].PRT_PORT_CODE);
								prtA = document.createElement('a');
								
								// url
								var url = '/search/results/?searchWhere=' + prtData[prt].PRT_PORT_CODE;
								for (var param in params)
								{
									if (param != 'searchWhere')
									{
										url += '&' + param + '=' + params[param];
									}
								}
								
								prtA.setAttribute('href', url);
								prtA.appendChild(document.createTextNode(prtData[prt].PRT_NAME));
								prtLi.appendChild(prtA);
								prtUl.appendChild(prtLi);
							}
							
							cntLi.append(prtUl);
						}, "json");
					}
					
				});
				
				ul.appendChild(cntLi);
			}
			
			li.appendChild(ul);
			
			$('#countryList').append(li);
		}
	}, "json");			
});