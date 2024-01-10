<?php
/**
 * This function is to implement some legacy functins where too many error vas encounered during PHP 8 migration
 */

if (!function_exists('lg_count')) {
    function lg_count(Mixed $countable): int
    {
        if (!is_countable($countable)) {
            return 0;
        }
    
        return count($countable);
    }
}

if (!function_exists('xmlrpc_encode_request')) {
    function xmlrpc_encode_request($method, $params, $output)
    {
        $method = 'sum';  // Method name
        $params = [2, 3]; // Parameters for the method call
        
        $client = new \Zend\XmlRpc\Client('http://example.com/xmlrpc-server'); // Replace with your XML-RPC server URL
        
        return $client->call($method, $params);
    }
}







