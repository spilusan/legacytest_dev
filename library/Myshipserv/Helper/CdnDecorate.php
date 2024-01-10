<?php
/**
 * Decorator class to decorate links from cdn.
 *
 * @author attilaolbrich
 * usage Myshipserv_Helper_CdnDecorate::decorateImgUrl($url)
 */
class Myshipserv_Helper_CdnDecorate
{
    /**
     * Decorate url with CDN
     * We use this function when the image is stored in DB
     * and pointing to shipserv live, and we have to modify the URL
     * pointing to CDN.
     *
     * @param string $url
     *
     * @return string
     */
    public static function decorateImgUrl($url, $legacy = false)
    {
        $result = $url;
        $config = Zend_Registry::get('config');

        if ((int) $config->shipserv->cdn->use === 1) {
            $result = str_replace(['http://www.shipserv.com', 'https://www.shipserv.com'], $config->shipserv->cdn->image, $url);
        }

        // another hack to replace the result to legacy URL as not done in the API after transferring the page to legacy
        if ($legacy) {
            $result = str_replace('www.', 'legacy.', $result);
        }

        return $result;
    }
}
