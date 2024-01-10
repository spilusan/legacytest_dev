$(document).ready(function(){
	$('#loginSubmit').live('click',function(){
		$.post('/user/perform-login/format/json/', $("#loginForm").serialize(), function(data, textStatus){
		
		var new_data = eval(data);  
		
		if(new_data.loginSuccess != true){
				
			alert(JSON.stringify(data));
			
			$("#form_error").empty();
			$("#form_error").show();
			$("#form_error").append("<h2>This form has errors</h2>");
			
			if(new_data.errors.loginUsername =="isEmpty")
			{$("#form_error").append("<p>The Username field is empty</p>");}
			
			if(new_data.errors.loginPassword =="isEmpty")
			{$("#form_error").append("<p>The Password field is empty</p>");}
			
			if(new_data.errors == "Sorry, your credentials are incorrect"){$("#form_error").append("<p>Your Username or Password is incorrect</p>");}
		
		} else {
			
			if(new_data.redirect != null) {
				var url = new_data.redirect;
				window.location = url;
				//window.location = '/';
		}else{
			window.location = '/';
		}}

		}, "json");
		
		return false;
	});
	
	$('#registerSubmit').live('click',function(){
		$.post('/user/perform-register/format/json/', $("#registerForm").serialize(), function(data, textStatus){
			
			//alert(JSON.stringify(data));
			
            $("#form_error_2").empty();
			$("#form_error_2").show();
			$("#form_error_2").append("<h2>This form has errors</h2>");
			
			var new_data = eval(data);
			
			if(new_data.errors.registerEmail =="isEmpty")
			{$("#form_error_2").append("<p>The email field is empty</p>");}
			else if(new_data.errors.registerEmail =="emailAddressInvalidFormat")
			{$("#form_error_2").append("<p>The email is invalid</p>");}
			
			if(new_data.errors.registerPassword =="isEmpty")
			{$("#form_error_2").append("<p>The Password Field is empty</p>");}
			else if(new_data.errors.registerPassword =="notMatch")
			{$("#form_error_2").append("<p>The Passwords don't match</p>");}
			else if(new_data.errors.registerPassword == "stringLengthTooShort")
			{$("#form_error_2").append("<p>The Password is too short</p>");} 
			
			if(new_data.errors.registerFirstName =="isEmpty")
			{$("#form_error_2").append("<p>The First Name field is empty</p>");}
			
			if(new_data.errors.registerLastName =="isEmpty")
			{$("#form_error_2").append("<p>The Last Name field is empty</p>");}
			
			if(new_data.errors.registerCompany =="isEmpty")
			{$("#form_error_2").append("<p>The Company Name field is empty</p>");}
			
			if(new_data.errors.registerCompanyType =="isEmpty")
			{$("#form_error_2").append("<p>The Company Type field is empty</p>");}
			
			if(new_data.errors.registerJob =="isEmpty")
			{$("#form_error_2").append("<p>The Job field is empty</p>");}

			if(new_data.errors.registerJobFunction =="isEmpty")
			{$("#form_error_2").append("<p>The Job Function field is empty</p>");}		
	
		}, "json");
		
		return false;
	});
});