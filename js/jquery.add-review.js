$(document).ready(function(){
	$('.radiobutton').mouseover(function(){
		var imageName = this.id + ((isButtonSelected(this.id))?'_selected':'')+'_hover.gif';
		$(this).attr("src", "/images/layout_v2/endorsement/"+imageName);
	})
	.mouseout(function(){
		var imageName = this.id +((isButtonSelected(this.id))?'_selected':'_normal')+'.gif';
		$(this).attr("src", "/images/layout_v2/endorsement/"+imageName);
	})
	.click(function(){

		var imageName = valueToButton($('input[name=overallImpression]').val()) +'_normal.gif';
		$("#"+valueToButton($('input[name=overallImpression]').val())).attr("src", "/images/layout_v2/endorsement/"+imageName);

		$('input[name=overallImpression]').val(buttonToValue(this.id));
		imageName = this.id +'_selected_hover.gif';
		$(this).attr("src", "/images/layout_v2/endorsement/"+imageName);
	});

	$('.star').mouseover(function(){
		var idArray = this.id.split('_');
		var id = parseInt(idArray[1]);
		var category = idArray[0];
		for (i=1;i<=id;i++){
			$('#'+category+'_'+i).attr("src", "/images/layout_v2/endorsement/star.gif");
		}
		for (i=id+1;i<=5;i++){
			$('#'+category+'_'+i).attr("src", "/images/layout_v2/endorsement/star_passive.gif");
		}

		switch (id){
			case 1:
				hintText = "Very bad";
				break;
			case 2:
				hintText = "Bad";
				break;
			case 3:
				hintText = "Acceptable";
				break;
			case 4:
				hintText = "Good";
				break;
			case 5:
				hintText = "Very good";
				break;
			default:
				hintText = "";
		}
		$('#'+category+'_hint').text(hintText);
	})
	.mouseout(function(){
		var idArray = this.id.split('_');
		var category = idArray[0];
		initCategoryRaiting(category);
	})
	.click(function(){
		var idArray = this.id.split('_');
		$('input[name='+idArray[0]+']').val(parseInt(idArray[1]));
	});

	$('#submitReview').mouseover(function(){
		$(this).attr("src", "/images/layout_v2/endorsement/submit_review_hover.gif");
	})
	.mouseout(function(){
		$(this).attr("src", "/images/layout_v2/endorsement/submit_review.gif");
	})
	.click (function(){
		if ($('input[name=overallImpression]').val()=="")
		{
			alert ("Please, select your overall impression about this company");
			return false;
		}
		if ($('input[name=did]').val()=="0")
		{
			alert ("Please rate supplier for 'Delivered items as described?'");
			return false;
		}
		if ($('input[name=otd]').val()=="0")
		{
			alert ("Please rate supplier for 'On time delivery?'");
			return false;
		}
		if ($('input[name=cs]').val()=="0")
		{
			alert ("Please rate supplier for 'Customer Service'");
			return false;
		}
		if ($('textarea[name=reviewComment]').val().length>1999)
		{
			alert ("Maximum detailed feedback length is 2000 symbols");
			return false;
		}
		if ($.trim($('textarea[name=reviewComment]').val()).length==0)
		{
			alert ("Please provide detailed feedback");
			return false;
		}
		
		$('form[name=addReview]').submit();
	});

	$('input[name=category1]').autoComplete({
		backwardsCompatible: true,
		postData: {format:'json'},
		ajax:"/search/autocomplete/categories/format/json/",
		useCache: false,
		minChars: 0,
		width: 480,
		leftAdjustment: 0,
		preventEnterSubmit: true,
		onRollover: function(data, $li){
			$('input[name=category1]').val(data.value);
			$('input[name=category1Id]').val(data.id);
		}
	});

	initOverallRaiting();
	initCategoryRaiting("did");
	initCategoryRaiting("otd");
	initCategoryRaiting("cs");

});

function isButtonSelected (id)
{
	var inputValue = parseInt($('input[name=overallImpression]').val());
	if (inputValue == buttonToValue(id)) {
		return true;
	}
	return false;
}

function buttonToValue (id)
{
	switch (id){
		case "positive":
			return 1;
			break;
		case "neutral":
			return 0;
			break;
		case "negative":
			return -1;
			break;
		default:
			return 0;
			break;
	}
}

function valueToButton (value)
{
	switch (parseInt(value)){
		case 1:
			return "positive";
			break;
		case 0:
			return "neutral";
			break;
		case -1:
			return"negative";
			break;
		default:
			return "neutral";
			break;
	}
}

function initCategoryRaiting(category) {
	var selectedValue = parseInt($('input[name='+category+']').val());
		for (i=1;i<=selectedValue;i++){
			$('#'+category+'_'+i).attr("src", "/images/layout_v2/endorsement/star.gif");
		}
		for (i=selectedValue+1;i<=5;i++){
			$('#'+category+'_'+i).attr("src", "/images/layout_v2/endorsement/star_passive.gif");
		}
		switch (selectedValue){
			case 1:
				hintText = "Very bad";
				break;
			case 2:
				hintText = "Bad";
				break;
			case 3:
				hintText = "Acceptable";
				break;
			case 4:
				hintText = "Good";
				break;
			case 5:
				hintText = "Very good";
				break;
			default:
				hintText = "";
		}
		$('#'+category+'_hint').text(hintText);
}
function initOverallRaiting() {
	if ($('input[name=overallImpression]').val()!="")
	{
		var imageName = valueToButton($('input[name=overallImpression]').val()) +'_selected.gif';
		$("#"+valueToButton($('input[name=overallImpression]').val())).attr("src", "/images/layout_v2/endorsement/"+imageName);
	}
}

