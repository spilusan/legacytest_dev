<?php
/**
 * Forward specsheet image
 */

class Myshipserv_Image_ResizeImage
{

    /**
     * Forward the URL to the resize image service and retun the converted image data
     * 
     * @param string  $imageUrl The URL of the image
     * @param integer $width    The with of image
     * @param integer $height   The height of image
     * 
     * @return string
     */
    public static function forward($imageUrl, $width = null, $height = null)
    {
        $parameters = [];
 
        if ($width) {
            $parameters['width'] = (int)$width;
        }

        if ($height) {
            $parameters['height'] = (int)$height;
        }

        if (count($parameters) === 0) {
            throw new Myshipserv_Exception_MessagedException(
                "With or Height or both parameters are required.",
                500
            );
        }

        $config = Zend_Registry::get('config');
        $resizeUrl = $config->shipserv->imageresize->url;
        $forwardUrl = $resizeUrl . urlencode($imageUrl) .
         '&' . http_build_query($parameters);

        return file_get_contents($forwardUrl);
    }

}