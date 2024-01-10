<?php
/**
 * 2 Way encrypt for this old PHP 5 with hash
 */

class Shipserv_Encrypt
{

    const ENC_KEY = 'Y>[G-y2Q9?AqeW2T';

    /**
     * Encrypt string with hash
     * 
     * @param string $data
     * 
     * @return string
     * 
     */
    public static function encrypt($data)
    {
        $key = self::ENC_KEY;

        $l = strlen($key);

        if ($l < 16) {
            $key = str_repeat($key, ceil(16 / $l));
        }

        if ($m = strlen($data)%8) {
            $data .= str_repeat("\x00",  8 - $m);
        }

        if (function_exists('mcrypt_encrypt')) {
            $val = mcrypt_encrypt(MCRYPT_BLOWFISH, $key, $data, MCRYPT_MODE_ECB);
        } else {
            $val = openssl_encrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        }

        return base64_encode($val);
    }

    /**
     * Decrypt string with custom hash
     * 
     * @param string $data
     * 
     * @return string
     */
    public static function decrypt($data)
    {
        $data = base64_decode($data);
        $key = self::ENC_KEY;

        $l = strlen($key);

        if ($l < 16) {
            $key = str_repeat($key, ceil(16 / $l));
        }

        if (function_exists('mcrypt_encrypt')) {
            $val = mcrypt_decrypt(MCRYPT_BLOWFISH, $key, $data, MCRYPT_MODE_ECB);
        } else {
            $val = openssl_decrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        }

        return $val;
    }
}
