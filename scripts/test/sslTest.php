<?php
/**
 * A script that tells which security protocols are supported by server's PHP setup
 *
 * @author  Yuriy Akopov
 * @date    2017-01-16
 * @story   S19057
 */
$ch = curl_init('https://www.howsmyssl.com/a/check');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$data = curl_exec($ch);
curl_close($ch);

$json = json_decode($data);
echo $json->tls_version;