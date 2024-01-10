<?php

// Load the CAS lib
//require_once '/var/www/libraries/CAS2/CAS.php';
require_once '../vendor/jasig/phpcas/source/CAS.php';

// Uncomment to enable debugging
phpCAS::setDebug();
$cas_host='jonah.myshipserv.com';
$cas_port=9080;
$cas_context='auth/cas/v1/tickets';

$postParameters = "username=jgo@shipserv.com&password=123456";
$response = postRequest("http://jonah.myshipserv.com:9080/auth/cas/v1/tickets", $postParameters);





//action="http://jonah.myshipserv.com:9080/auth/cas/v1/tickets/TGT-17-Q0NA9NMLobqoLxAfKWgGpnvIeWacsgfd5vXlLdmc9Ca5bQAYLz-cas";
preg_match_all(
	'/action="([^"]*)"/i',
    $response, 
    $result, 
    PREG_PATTERN_ORDER);

$result = str_replace('action="', '', $result[0]);
$result = str_replace('"', '', $result);
$url = $result[0];
$tmp = parse_url($url);
$tmp = explode("/", $tmp['path']);
$ticket = $tmp[5];

echo 'URL: ' . $url . '<br />';

$postParameters = "service=http://dev7.myshipserv.com/cas.php";
$serviceTicket = postRequest($url, $postParameters);

echo 'Service Ticket: ' . $serviceTicket. '<br />';


// http://jonah.myshipserv.com:9080/auth/cas/serviceValidate?ticket=xxx&service=xxx
$postParameters = "ticket=". $serviceTicket ."&service=http://dev7.myshipserv.com/cas.php";

$response = postRequest('http://jonah.myshipserv.com:9080/auth/cas/serviceValidate', $postParameters);

echo "Username: " . $response;

function postRequest( $url, $postParameters )
{
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
	
	// receive server response ...
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$server_output = curl_exec ($ch);
	
	curl_close ($ch);
	
	return $server_output;
	
}