<?php
/**
 * Forward specsheet image
 */

class Myshipserv_Image_ForwardSpecsheetImage
{
    
    /**
     * Forward the URL to the image service and retun the converted image data
     * 
     * @param string $pdfUrl
     * 
     * @return string
     */
    public static function forward($pdfUrl)
    {
        $config = Zend_Registry::get('config');
        $pdfBoxUrl = $config->shipserv->PDFbox->url;
        $forwardUrl = $pdfBoxUrl . urlencode($pdfUrl) . '&format=jpg&page=0&dpi=70';

        return file_get_contents($forwardUrl);
    }

}