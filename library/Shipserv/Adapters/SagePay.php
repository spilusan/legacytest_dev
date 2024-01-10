<?php

class Shipserv_Adapters_SagePay
{
	
	const CLEAN_INPUT_FILTER_ALPHABETIC = "alpha";
	const CLEAN_INPUT_FILTER_ALPHABETIC_AND_ACCENTED = "alpha and accented";
	const CLEAN_INPUT_FILTER_ALPHANUMERIC = "alphaNumeric";
	const CLEAN_INPUT_FILTER_ALPHANUMERIC_AND_ACCENTED = "alphaNumeric and accented";
	const CLEAN_INPUT_FILTER_NUMERIC = "numeric";
	const CLEAN_INPUT_FILTER_TEXT = "text";
	const CLEAN_INPUT_FILTER_WIDEST_ALLOWABLE_CHARACTER_RANGE = "text";

	// Defines a set of values used as outcomes in field validation functions such as isValidAddressField.
	const FIELD_VALID = "valid";
	const FIELD_INVALID = "invalid";
	const FIELD_INVALID_BAD_CHARACTERS = "bad characters";
	const FIELD_INVALID_BAD_FORMAT= "bad format";
	const FIELD_INVALID_MAXIMUM_LENGTH_EXCEEDED = "maximum exceeded";
	const FIELD_INVALID_MINIMUM_LENGTH_NOT_MET = "minimum not met";
	const FIELD_INVALID_REQUIRED_INPUT_VALUE_MISSING = "missing required value";
	const FIELD_INVALID_REQUIRED_INPUT_VALUE_NOT_SELECTED = "required value not selected";
	
	var $strConnectTo = "LIVE";    //Set to SIMULATOR for the Simulator expert system, TEST for the Test Server and LIVE in the live environment
	var $strVirtualDir="SagePayFormKit";    //Change if you have created a Virtual Directory in IIS with a different name

	/** IMPORTANT.  Set the strYourSiteFQDN value to the Fully Qualified Domain Name of your server. **
	** This should start http:// or https:// and should be the name by which our servers can call back to yours **
	** i.e. it MUST be resolvable externally, and have access granted to the Sage Pay servers **
	** examples would be https://www.mysite.com or http://212.111.32.22/ **
	** NOTE: You should leave the final / in place. **/

	var $strYourSiteFQDN="https://www.shipserv.com/";  
	var $strVendorName="shipserv";    /** Set this value to the Vendor Name assigned to you by Sage Pay or chosen when you applied **/
	var $strEncryptionPassword="mzSW32vh7Avt32Vq";    /** Set this value to the XOR Encryption password assigned to you by Sage Pay **/
	//var $strEncryptionPassword="huejQNqyO1FfFWIZ";    /** SIMULATOR Password **/
	var $strCurrency="GBP"; /** Set this to indicate the currency in which you wish to trade. You will need a merchant number in this currency **/
	var $strTransactionType="PAYMENT"; /** This can be DEFERRED or AUTHENTICATE if your Sage Pay account supports those payment types **/
	var $strPartnerID=""; /** Optional setting. If you are a Sage Pay Partner and wish to flag the transactions with your unique partner id set it here. **/

	/* Optional setting. 
	** 0 = Do not send either customer or vendor e-mails, 
	** 1 = Send customer and vendor e-mails if address(es) are provided(DEFAULT). 
	** 2 = Send Vendor Email but not Customer Email. If you do not supply this field, 1 is assumed and e-mails are sent if addresses are provided. **/
	var $bSendEMail=1; 
	var $strVendorEMail="dhardy@shipserv.com";    /** Optional setting. Set this to the mail address which will receive order confirmations and failures **/

	//** Encryption type should be left set to AES unless you are experiencing problems and have been told by SagePay support to change it - XOR is the only other acceptable value **
	var $strEncryptionType="XOR";

	/**************************************************************************************************
	* Global Definitions for this site
	**************************************************************************************************/

	//var $strProtocol="2.23";
	var $strProtocol="3.00";
	
	var $arrPurchaseURLs = array (
		"LIVE" => "https://live.sagepay.com/gateway/service/vspform-register.vsp",
		"TEST" => "https://test.sagepay.com/gateway/service/vspform-register.vsp",
		"SIMULATOR" => "https://test.sagepay.com/simulator/vspformgateway.asp"
	);
	
	var $params = array();
	
	var $paramDefinitions = array(
		"CustomerName",
		"BillingFirstnames",
		"BillingSurname",
		"BillingAddress1",
		"BillingAddress2",
		"BillingCity",
		"BillingPostCode",
		"BillingCountry",
		"BillingState",
		"BillingPhone", 
		"CustomerEMail"
	);
	
	public function runWithoutInvoice()
	{
		$this->mode = 'without-invoice';
	}
	
	function getCrypt ()
	{
		// Now to build the Form crypt field.  For more details see the Form Protocol 2.23 
		$strPost="VendorTxCode=" . $this->getVendorTxCode();

		$strPost=$strPost . "&Amount=" . number_format($this->params["Amount"],2); // Formatted to 2 decimal places with leading digit
		$strPost=$strPost . "&Currency=" . $this->params["Currency"];
		// Up to 100 chars of free format description
		$strPost=$strPost . "&Description= Payment for ShipServ Invoice " . $this->params["InvoiceId"];

		/* The SuccessURL is the page to which Form returns the customer if the transaction is successful 
		** You can change this for each transaction, perhaps passing a session ID or state flag if you wish */
		$strPost=$strPost . "&SuccessURL=" . 'https://' . $_SERVER['HTTP_HOST'] . "/payment/success";

		/* The FailureURL is the page to which Form returns the customer if the transaction is unsuccessful
		** You can change this for each transaction, perhaps passing a session ID or state flag if you wish */
		if( $this->mode == 'without-invoice' )
		{
			$strPost=$strPost . "&FailureURL=" . 'https://' . $_SERVER['HTTP_HOST'] . "/payment/";
		}
		else 
		{
			$strPost=$strPost . "&FailureURL=" . 'https://' . $_SERVER['HTTP_HOST'] . "/payment/failure/sfiid".$this->params["sfiid"];
		}
		// This is an Optional setting. Here we are just using the Billing names given.
		$strPost=$strPost . "&CustomerName=" . $this->params["CustomerName"];

		/* Email settings:
		** Flag 'SendEMail' is an Optional setting. 
		** 0 = Do not send either customer or vendor e-mails, 
		** 1 = Send customer and vendor e-mails if address(es) are provided(DEFAULT). 
		** 2 = Send Vendor Email but not Customer Email. If you do not supply this field, 1 is assumed and e-mails are sent if addresses are provided. **/
		if ($this->bSendEMail == 0)
			$strPost=$strPost . "&SendEMail=0";
		else {

			if ($this->bSendEMail == 1) {
				$strPost=$strPost . "&SendEMail=1";
			} else {
				$strPost=$strPost . "&SendEMail=2";
			}

			if (strlen($this->params["CustomerEMail"]) > 0)
				$strPost=$strPost . "&CustomerEMail=" . $this->params["CustomerEMail"];  // This is an Optional setting

			if ($this->strVendorEMail <> "")
				$strPost=$strPost . "&VendorEMail=" . $this->strVendorEMail;  // This is an Optional setting

			// You can specify any custom message to send to your customers in their confirmation e-mail here
			// The field can contain HTML if you wish, and be different for each order.  This field is optional
			$strPost=$strPost . "&eMailMessage=Thank you so very much for your order.";
		}

		// Billing Details:
		$strPost=$strPost . "&BillingFirstnames=" . $this->params["BillingFirstnames"];
		$strPost=$strPost . "&BillingSurname=" . $this->params["BillingSurname"];
		$strPost=$strPost . "&BillingAddress1=" . $this->params["BillingAddress1"];
		if (strlen($this->params["BillingAddress2"]) > 0) $strPost=$strPost . "&BillingAddress2=" . $this->params["BillingAddress2"];
		$strPost=$strPost . "&BillingCity=" . $this->params["BillingCity"];
		$strPost=$strPost . "&BillingPostCode=" . $this->params["BillingPostCode"];
		$strPost=$strPost . "&BillingCountry=" . $this->params["BillingCountry"];
		if (strlen($this->params["BillingState"]) > 0) $strPost=$strPost . "&BillingState=" . strtoupper ($this->params["BillingState"]);
		if (strlen($this->params["BillingPhone"]) > 0) $strPost=$strPost . "&BillingPhone=" . $this->params["BillingPhone"];

		// Delivery Details:
		$strPost=$strPost . "&DeliveryFirstnames=" . $this->params["BillingFirstnames"];
		$strPost=$strPost . "&DeliverySurname=" . $this->params["BillingSurname"];
		$strPost=$strPost . "&DeliveryAddress1=" . $this->params["BillingAddress1"];
		if (strlen($this->params["BillingAddress2"]) > 0) $strPost=$strPost . "&DeliveryAddress2=" . $this->params["BillingAddress2"];
		$strPost=$strPost . "&DeliveryCity=" . $this->params["BillingCity"];
		$strPost=$strPost . "&DeliveryPostCode=" . $this->params["BillingPostCode"];
		$strPost=$strPost . "&DeliveryCountry=" . $this->params["BillingCountry"];
		if (strlen($this->params["BillingState"]) > 0) $strPost=$strPost . "&DeliveryState=" . strtoupper ($this->params["BillingState"]);
		if (strlen($this->params["BillingPhone"]) > 0) $strPost=$strPost . "&DeliveryPhone=" . $this->params["BillingPhone"];


		// For charities registered for Gift Aid, set to 1 to display the Gift Aid check box on the payment pages
		$strPost=$strPost . "&AllowGiftAid=0";
	
		/* Allow fine control over AVS/CV2 checks and rules by changing this value. 0 is Default 
		** It can be changed dynamically, per transaction, if you wish.  See the Server Protocol document */
		if ($this->strTransactionType!=="AUTHENTICATE")
			$strPost=$strPost . "&ApplyAVSCV2=1";
	
		/* Allow fine control over 3D-Secure checks and rules by changing this value. 0 is Default 
		** It can be changed dynamically, per transaction, if you wish.  See the Form Protocol document */
		$strPost=$strPost . "&Apply3DSecure=1";
//echo $strPost;
		// Encrypt the plaintext string for inclusion in the hidden field
		$strCrypt = $this->encryptAndEncode($strPost);
		
		return $strCrypt;
	}
	
	function getVendorTxCode()
	{
		return "ShipServ-Invoice-". $this->params["InvoiceId"];
	}
	
	function setParams($params)
	{
		foreach ($params as $key=>$value) {
			$this->params[$key] = $value;
		}
	}
	
	function validateParams ()
	{
		foreach ($this->params as $name=>$param)
		{
			$this->params[$name] = $this->cleaninput($param,"text");
		}
		
		$validationResult = ""; //returned reference to a validation result
		$errors = array ();

		if (!$this->isValidNameField($this->params["BillingFirstnames"], $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "Billing First Name(s)");
		}
		if (!$this->isValidNameField($this->params["BillingSurname"], $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "Billing Surname");
		}
		if (!$this->isValidAddressField($this->params["CustomerName"], TRUE, $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "Company name");
		}
		if (!$this->isValidAddressField($this->params["BillingAddress1"], TRUE, $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "Billing Address Line 1");
		}
		if (!$this->isValidAddressField($this->params["BillingAddress2"], FALSE, $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "Billing Address Line 2");
		}
		if (!$this->isValidCityField($this->params["BillingCity"], $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "Billing City");
		}
		if (!$this->isValidPostcodeField($this->params["BillingPostCode"], $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "Billing Post/Zip Code");
		}
		if (strlen($this->params["BillingCountry"]) == 0) {
			$errors[] = "Please select your Billing Country where requested below.";
		}
		if ((strlen($this->params["BillingState"]) == 0) && ($this->params["BillingCountry"] == "US")) {
			$errors[] = "Please select your State code as you have selected United States for billing country.";
		}
		if (!$this->isValidEmailField($this->params["CustomerEMail"], $validationResult)) {
			$errors[] = $this->getValidationMessage($validationResult, "e-mail Address");
		}
		if (!preg_match('/^.{6,10}$/', $this->params["InvoiceId"])) {
			$errors[] = "Invoice field accepts between 6 and 10 digits.";
		}
		if( $this->mode == 'without-invoice' )
		{
			if (!$this->isValidCurrencyField($this->params["Amount"], $validationResult)) {
				$errors[] = $this->getValidationMessage($validationResult, "Transaction amount");
			}

			if (!$this->isValidNameField($this->params["BillingCurrency"], $validationResult)) {
				$errors[] = $this->getValidationMessage($validationResult, "Currency");
			}
				
			if (!$this->isValidInvoiceNumberField($this->params["Invoice"], $validationResult)) {
				$errors[] = $this->getValidationMessage($validationResult, "Invoice number");
			}

			if ( (float) $this->params["Amount"] > 200000 ) {
				$errors[] = "Total value cannot exceed 200,000.00";
			} else if( (float) $this->params["Amount"] <= 0 && $this->params["Amount"] != '') {
				$errors[] = "Please enter a value for Transaction amount";
			}

		}
		
		return $errors;
		
	}
	
	function getPurchaseURL ()
	{
		return $this->arrPurchaseURLs[$this->strConnectTo];
	}

	//Function to redirect browser to a specific page
	function redirect($url) {
	   if (!headers_sent())
		   header('Location: '.$url);
	   else {
		   echo '<script type="text/javascript">';
		   echo 'window.location.href="'.$url.'";';
		   echo '</script>';
		   echo '<noscript>';
		   echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
		   echo '</noscript>';
	   }
	}

	
	/* The getToken function.                                                                                         **
	** NOTE: A function of convenience that extracts the value from the "name=value&name2=value2..." reply string **
	** Works even if one of the values is a URL containing the & or = signs.                                      	  */
	function getToken($thisString) {

	  // List the possible tokens
	  $Tokens = array(
		"Status",
		"StatusDetail",
		"VendorTxCode",
		"VPSTxId",
		"TxAuthNo",
		"Amount",
		"AVSCV2", 
		"AddressResult", 
		"PostCodeResult", 
		"CV2Result", 
		"GiftAid", 
		"3DSecureStatus", 
		"CAVV",
		"AddressStatus",
		"CardType",
		"Last4Digits",
		"PayerStatus");

	  // Initialise arrays
	  $output = array();
	  $resultArray = array();

	  // Get the next token in the sequence
	  for ($i = count($Tokens)-1; $i >= 0 ; $i--){
		// Find the position in the string
		$start = strpos($thisString, $Tokens[$i]);
		// If it's present
		if ($start !== false){
		  // Record position and token name
		  $resultArray[$i]->start = $start;
		  $resultArray[$i]->token = $Tokens[$i];
		}
	  }

	  // Sort in order of position
	  sort($resultArray);
		// Go through the result array, getting the token values
	  for ($i = 0; $i<count($resultArray); $i++){
		// Get the start point of the value
		$valueStart = $resultArray[$i]->start + strlen($resultArray[$i]->token) + 1;
		// Get the length of the value
		if ($i==(count($resultArray)-1)) {
		  $output[$resultArray[$i]->token] = substr($thisString, $valueStart);
		} else {
		  $valueLength = $resultArray[$i+1]->start - $resultArray[$i]->start - strlen($resultArray[$i]->token) - 2;
		  $output[$resultArray[$i]->token] = substr($thisString, $valueStart, $valueLength);
		}      

	  }

	  // Return the ouput array
	  return $output;
	}


	// Filters unwanted characters out of an input string based on type.  Useful for tidying up FORM field inputs
	//   Parameter strRawText is a value to clean.
	//   Parameter filterType is a value from one of the CLEAN_INPUT_FILTER_ constants.
	function cleanInput($strRawText, $filterType)
	{
		$strAllowableChars = "";
		$blnAllowAccentedChars = FALSE;
		$strCleaned = "";
		$filterType = strtolower($filterType); //ensures filterType matches constant values

		if ($filterType == "text")
		{ 
			$strAllowableChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 .,'/\\{}@():?-_&£$=%~*+\"\n\r";
			$strCleaned = $this->cleanInput2($strRawText, $strAllowableChars, TRUE);
		}
		elseif ($filterType == self::CLEAN_INPUT_FILTER_NUMERIC) 
		{
			$strAllowableChars = "0123456789 .,";
			$strCleaned = cleanInput2($strRawText, $strAllowableChars, FALSE);
		}   
		elseif ($filterType == self::CLEAN_INPUT_FILTER_ALPHABETIC || $filterType == self::CLEAN_INPUT_FILTER_ALPHABETIC_AND_ACCENTED)
		{
			$strAllowableChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz";
			if ($filterType == self::CLEAN_INPUT_FILTER_ALPHABETIC_AND_ACCENTED) $blnAllowAccentedChars = TRUE;
			$strCleaned = cleanInput2($strRawText, $strAllowableChars, $blnAllowAccentedChars);
		}
		elseif ($filterType == self::CLEAN_INPUT_FILTER_ALPHANUMERIC || $filterType == self::CLEAN_INPUT_FILTER_ALPHANUMERIC_AND_ACCENTED)
		{
			$strAllowableChars = "0123456789 ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
			if ($filterType == self::CLEAN_INPUT_FILTER_ALPHANUMERIC_AND_ACCENTED) $blnAllowAccentedChars = TRUE;
			$strCleaned = cleanInput2($strRawText, $strAllowableChars, $blnAllowAccentedChars);
		}
		else // Widest Allowable Character Range
		{
			$strAllowableChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 .,'/\\{}@():?-_&£$=%~*+\"\n\r";
			$strCleaned = cleanInput2($strRawText, $strAllowableChars, TRUE);
		}

		return $strCleaned;
	}


	// Filters unwanted characters out of an input string based on an allowable character set.  Useful for tidying up FORM field inputs
	//   Parameter strRawText is a value to clean.
	//   Parameter "strAllowableChars" is a string of characters allowable in "strRawText" if its to be deemed valid.
	//   Parameter "blnAllowAccentedChars" accepts a boolean value which determines if "strRawText" can contain Accented or High-order characters.
	function cleanInput2($strRawText, $strAllowableChars, $blnAllowAccentedChars)
	{
		$iCharPos = 0;
		$chrThisChar = "";
		$strCleanedText = "";

		//Compare each character based on list of acceptable characters
		while ($iCharPos < strlen($strRawText))
		{
			// Only include valid characters **
			$chrThisChar = substr($strRawText, $iCharPos, 1);
			if (strpos($strAllowableChars, $chrThisChar) !== FALSE)
			{
				$strCleanedText = $strCleanedText . $chrThisChar;
			}
			elseIf ($blnAllowAccentedChars == TRUE)
			{
				// Allow accented characters and most high order bit chars which are harmless **
				if (ord($chrThisChar) >= 191)
				{
					$strCleanedText = $strCleanedText . $chrThisChar;
				}
			}

			$iCharPos = $iCharPos + 1;
		}

		return $strCleanedText;
	}

	/* Base 64 Encoding function **
	** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/
	function base64Encode($plain) {
	  // Initialise output variable
	  $output = "";

	  // Do encoding
	  $output = base64_encode($plain);

	  // Return the result
	  return $output;
	}

	/* Base 64 decoding function **
	** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/
	function base64Decode($scrambled) {
	  // Initialise output variable
	  $output = "";

	  // Fix plus to space conversion issue
	  $scrambled = str_replace(" ","+",$scrambled);

	  // Do encoding
	  $output = base64_decode($scrambled);

	  // Return the result
	  return $output;
	}


	/*  The SimpleXor encryption algorithm                                                                                **
	**  NOTE: This is a placeholder really.  Future releases of Form will use AES or TwoFish.  Proper encryption      **
	**  This simple function and the Base64 will deter script kiddies and prevent the "View Source" type tampering        **
	**  It won't stop a half decent hacker though, but the most they could do is change the amount field to something     **
	**  else, so provided the vendor checks the reports and compares amounts, there is no harm done.  It's still          **
	**  more secure than the other PSPs who don't both encrypting their forms at all                                      */

	function simpleXor($InString, $Key) {
	  // Initialise key array
	  $KeyList = array();
	  // Initialise out variable
	  $output = "";

	  // Convert $Key into array of ASCII values
	  for($i = 0; $i < strlen($Key); $i++){
		$KeyList[$i] = ord(substr($Key, $i, 1));
	  }

	  // Step through string a character at a time
	  for($i = 0; $i < strlen($InString); $i++) {
		// Get ASCII code from string, get ASCII code from key (loop through with MOD), XOR the two, get the character from the result
		// % is MOD (modulus), ^ is XOR
		$output.= chr(ord(substr($InString, $i, 1)) ^ ($KeyList[$i % strlen($Key)]));
	  }

	  // Return the result
	  return $output;
	}

	function encryptAndEncode($string) {
		// AES encryption, CBC blocking with PKCS5 padding then HEX encoding.
		// Add PKCS5 padding to the text to be encypted.
		$string = self::addPKCS5Padding($string);
		$key = $this->strEncryptionPassword;
		// Perform encryption with PHP's MCRYPT module.
		$crypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $string, MCRYPT_MODE_CBC, $key);
		
		// Perform hex encoding and return.
		return "@" . strtoupper(bin2hex($crypt));
		
	}
	
	//** Wrapper function do encrypt an encode based on strEncryptionType setting **
	function _encryptAndEncode($strIn) {
	

		if ($this->strEncryptionType=="XOR") 
		{
			//** XOR encryption with Base64 encoding **
			return $this->base64Encode($this->simpleXor($strIn,$this->strEncryptionPassword));
		} 
		else 
		{
			//** AES encryption, CBC blocking with PKCS5 padding then HEX encoding - DEFAULT **

			//** use initialization vector (IV) set from $strEncryptionPassword
			$strIV = $this->strEncryptionPassword;

			//** add PKCS5 padding to the text to be encypted
			$strIn = $this->addPKCS5Padding($strIn);

			//** perform encryption with PHP's MCRYPT module
			$strCrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->strEncryptionPassword, $strIn, MCRYPT_MODE_CBC, $strIV);

			//** perform hex encoding and return
			return "@" . bin2hex($strCrypt);
		}
	}


	//** Wrapper function do decode then decrypt based on header of the encrypted field **
	function decodeAndDecrypt($strIn) {

		if (substr($strIn,0,1)=="@") 
		{
			//** HEX decoding then AES decryption, CBC blocking with PKCS5 padding - DEFAULT **

			//** use initialization vector (IV) set from $strEncryptionPassword
			$strIV = $this->strEncryptionPassword;

			//** remove the first char which is @ to flag this is AES encrypted
			$strIn = substr($strIn,1); 

			//** HEX decoding
			$strIn = pack('H*', $strIn);

			//** perform decryption with PHP's MCRYPT module
			return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->strEncryptionPassword, $strIn, MCRYPT_MODE_CBC, $strIV); 
		} 
		else 
		{
			//** Base 64 decoding plus XOR decryption **
			return $this->simpleXor(base64Decode($strIn),$this->strEncryptionPassword);
		}
	}


	//** PHP's mcrypt does not have built in PKCS5 Padding, so we use this
	function addPKCS5Padding($input)
	{
        $blockSize = 16;
        $padd = "";

        // Pad input to an even block size boundary.
        $length = $blockSize - (strlen($input) % $blockSize);
        for ($i = 1; $i <= $length; $i++)
        {
            $padd .= chr($length);
        }

        return $input . $padd;
	}

	// Inspects and validates user input for a name field. Returns TRUE if input value is valid as a name field.
	//   Parameter "strInputValue" is the field value to validate.
	//   Parameter "returnedResult" sets a result to a value from the list of field validation constants beginning with "FIELD_".
	function isValidNameField($strInputValue, &$returnedResult)
	{
		$strAllowableChars = " ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-.'&\\";
		$strInputValue = trim($strInputValue);
		$returnedResult = $this->validateString($strInputValue, $strAllowableChars, TRUE, TRUE, 20, -1);
		if ($returnedResult == self::FIELD_VALID) {
			return TRUE;
		} else {
			return FALSE;
		}
	}


	// Inspects and validates user input for a name field. Returns TRUE if input value is valid as a name field.
	//   Parameter "strInputValue" is the field value to validate.
	//   Parameter "returnedResult" sets a result to a value from the list of field validation constants beginning with "FIELD_".
	function isValidDigit($strInputValue, &$returnedResult)
	{
		$strAllowableChars = " ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-.'&\\";
		$strInputValue = trim($strInputValue);
		$returnedResult = $this->validateString($strInputValue, $strAllowableChars, TRUE, TRUE, 20, -1);
		if ($returnedResult == self::FIELD_VALID) {
			return TRUE;
		} else {
			return FALSE;
		}
	}


	// Inspects and validates user input for an Address field.
	//   Parameter "blnIsRequired" specifies whether "strInputValue" must have a non-null and non-empty value.
	function isValidAddressField($strInputValue, $blnIsRequired, &$returnedResult )
	{
		$strAllowableChars = " 0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-.',/\\()&:+\n\r";
		$strInputValue = trim($strInputValue);
		$returnedResult = $this->validateString($strInputValue, $strAllowableChars, TRUE, $blnIsRequired, 100, -1);

		if ($returnedResult == self::FIELD_VALID) {
			return TRUE;
		} else {
			return FALSE;
		}
	}


// Inspects and validates user input for a City field.
function isValidCityField($strInputValue, &$returnedResult)
{
    $strAllowableChars = " 0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-.',/\\()&:+\n\r";
    $strInputValue = trim($strInputValue);
    $returnedResult = $this->validateString($strInputValue, $strAllowableChars, TRUE, TRUE, 40, -1);

    if ($returnedResult == self::FIELD_VALID) {
        return TRUE;
    } else {
        return FALSE;
    }
}


// Inspects and validates user input for a Postcode/zip field. 
function isValidPostcodeField($strInputValue, &$returnedResult)
{
    $strAllowableChars = " 0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-";
    $strInputValue = trim($strInputValue);
    $returnedResult = $this->validateString($strInputValue, $strAllowableChars, FALSE, TRUE, 10, -1);

    if ($returnedResult == self::FIELD_VALID) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// Inspects and validates user input for a Postcode/zip field.
function isValidCurrencyField($strInputValue, &$returnedResult)
{
	$strAllowableChars = "0123456789.,";
	$strInputValue = trim($strInputValue);
	$returnedResult = $this->validateString($strInputValue, $strAllowableChars, FALSE, TRUE, 12, -1);

	if ($returnedResult == self::FIELD_VALID) {
		return TRUE;
	} else {
		return FALSE;
	}
}

function isValidInvoiceNumberField($strInputValue, &$returnedResult)
{
	$strAllowableChars = " 0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-()+";
	$strInputValue = trim($strInputValue);
	$returnedResult = $this->validateString($strInputValue, $strAllowableChars, FALSE, FALSE, 20, -1);

	if ($returnedResult == self::FIELD_VALID) {
		return TRUE;
	} else{
		return FALSE;
	}
}

// Inspects and validates user input for an email field. 
function isValidEmailField($strInputValue, &$returnedResult)
{
    // The allowable e-mail address format accepted by the SagePay gateway must be RFC 5321/5322 compliant (see RFC 3696) 
	$sEmailRegExpPattern = '/^[a-z0-9\xC0-\xFF\!#$%&amp;\'*+\/=?^_`{|}~\*-]+(?:\.[a-z0-9\xC0-\xFF\!#$%&amp;\'*+\/=?^_`{|}~*-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[a-z]{2,3}|com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|at|coop|travel)$/';
    $strInputValue = trim($strInputValue);
    $returnedResult = $this->validateStringWithRegExp($strInputValue, $sEmailRegExpPattern, TRUE);
    
    if ($returnedResult == self::FIELD_VALID) {
        return TRUE;
    } else{
        return FALSE;
    }
}


// Inspects and validates user input for a phone field. 
function isValidPhoneField($strInputValue, &$returnedResult)
{
    $strAllowableChars = " 0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-()+";
    $strInputValue = trim($strInputValue);
    $returnedResult = $this->validateString($strInputValue, $strAllowableChars, FALSE, FALSE, 20, -1);

    if ($returnedResult == self::FIELD_VALID) {
        return TRUE;
    } else{
        return FALSE;
    }
}


	// A generic function used to inspect and validate a string from user input.
	//   Parameter "strInputValue" is the value to perform validation on.
	//   Parameter "strAllowableChars" is a string of characters allowable in "strInputValue" if its to be deemed valid.
	//   Parameter "blnAllowAccentedChars" accepts a boolean value which determines if "strInputValue" can contain Accented or High-order characters.
	//   Parameter "blnIsRequired" accepts a boolean value which specifies whether "strInputValue" must have a non-null and non-empty value.
	//   Parameter "intMaxLength" accepts an integer which specifies the maximum allowable length of "strInputValue". Set to -1 for this to be ignored.
	//   Parameter "intMinLength" specifies the miniumum allowable length of "strInputValue". Set to -1 for this to be ignored.
	//   Returns a result from one of the field validation constants that begin with "FIELD_" 
	function validateString($strInputValue, $strAllowableChars, $blnAllowAccentedChars, $blnIsRequired, $intMaxLength, $intMinLength)
	{
		if ($blnIsRequired == TRUE && strlen($strInputValue) == 0) {
			return self::FIELD_INVALID_REQUIRED_INPUT_VALUE_MISSING;
		} elseif (($intMaxLength != -1) && (strlen($strInputValue) > $intMaxLength)) {
			return self::FIELD_INVALID_MAXIMUM_LENGTH_EXCEEDED;
		} elseif ($strInputValue != $this->cleanInput2($strInputValue, $strAllowableChars, $blnAllowAccentedChars)) {
			return self::FIELD_INVALID_BAD_CHARACTERS;
		} elseif (($blnIsRequired == TRUE) && (strlen($strInputValue) < $intMinLength)) {
			return self::FIELD_INVALID_MINIMUM_LENGTH_NOT_MET;
		} elseif (($blnIsRequired == FALSE) && (strlen($strInputValue) > 0) && (strlen($strInputValue) < $intMinLength)) {
			return self::FIELD_INVALID_MINIMUM_LENGTH_NOT_MET;
		} else {
			return self::FIELD_VALID;
		}
	}

	// A generic function to inspect and validate a string from user input based on a Regular Expression pattern.
	//   Parameter "strInputValue" is the value to perform validation on.
	//   Parameter "strRegExPattern" is a Regular Expression string pattern used to validate against "strInputValue".
	//   Parameter "blnIsRequired" accepts a boolean value which specifies whether "strInputValue" must have a non-null and non-empty value.
	//   Returns a result from one of the field validation constants that begin with "FIELD_" 
	function validateStringWithRegExp($strInputValue, $strRegExPattern, $blnIsRequired)
	{
		if ($blnIsRequired == TRUE && strlen($strInputValue) == 0) 
		{
			return self::FIELD_INVALID_REQUIRED_INPUT_VALUE_MISSING;
		}
		elseif (strlen($strInputValue) > 0)
		{    
			if (preg_match($strRegExPattern, $strInputValue)) {
				return self::FIELD_VALID;
			} else {
				return self::FIELD_INVALID_BAD_FORMAT;
			}
		}
		else 
		{
			return self::FIELD_VALID;
		}
	}


	// Maps a Field Validation constant value to a string representing a user friendly validation error message.
	//   Parameter "strFieldLabelName" is the display name of the form field to use in the returned message.
	function getValidationMessage($fieldValidationCode, $strFieldLabelName)
	{
		$strReturn = "";

		switch ($fieldValidationCode)
		{
			case self::FIELD_INVALID_BAD_CHARACTERS:
				$strReturn = "Please correct " . $strFieldLabelName . " as it contains disallowed characters.";
				break;
			case self::FIELD_INVALID_BAD_FORMAT:
				$strReturn = "Please correct " . $strFieldLabelName . " as the format is invalid.";
				break;
			case self::FIELD_INVALID_MINIMUM_LENGTH_NOT_MET:
				$strReturn = "Please correct " . $strFieldLabelName . " as the value is not long enough.";
				break;
			case self::FIELD_INVALID_MAXIMUM_LENGTH_EXCEEDED:
				$strReturn = "Please correct " . $strFieldLabelName . " as the value is too long.";
				break;
			case self::FIELD_INVALID_REQUIRED_INPUT_VALUE_MISSING:
				$strReturn = "Please enter a value for " . $strFieldLabelName . "";
				break;
			case self::FIELD_INVALID_REQUIRED_INPUT_VALUE_NOT_SELECTED:
				$strReturn = "Please select a value for " . $strFieldLabelName . "";
				break;
		}

		return $strReturn;
	}

}
?>
