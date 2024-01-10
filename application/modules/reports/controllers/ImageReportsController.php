<?php

/**
 * This controller is for forwarding images to local network enpoints
 */
class Reports_ImageReportsController extends Myshipserv_Controller_Action
{
 
    /**
      * Forward image to image service
      * This solution itself is not good as this should not be done here but the API shold provide the image itself
      * and should do caching like add a pull CDN on top, but this was requested
      *
      * @return void
     */
    public function catalogueSpecheetImageAction()
    {
        $url = $this->getRequest()->getParam('url');
        $result =  Myshipserv_Image_ForwardSpecsheetImage::forward($url);

        if ($result) {
            $this->echoImage($result);
        }

        // @todo we could display some default image if the conversation is not successfull
        throw new Myshipserv_Exception_MessagedException(
            'Image conversion error.',
            500
        );
        exit;
    }

     /**
      * Forward image to resizer image service
      * This solution itself is not good as this should not be done here but the API shold provide the image itself
      * and should do caching like add a pull CDN on top, but this was requested
      * 
      * @return void
     */
    public function resizeImageAction()
    {
        $url = $this->getRequest()->getParam('url');
        $width = $this->getRequest()->getParam('width');
        $height= $this->getRequest()->getParam('height');
        
        
        $result =  Myshipserv_Image_ResizeImage::forward($url, $width, $height);

        if ($result) {
            $mimeType = $this->getImageMimeType($url);   
            $this->echoImage($result, $mimeType);
        }

        // @todo we could display some default image if the conversation is not successfull
        throw new Myshipserv_Exception_MessagedException(
            'Image conversion error.',
            500
        );
        exit;
    }

    /**
     * Return HTML Mime type for an image URL
     * 
     * @param string $imageName Image path
     * 
     * @return string
     */
    protected function getImageMimeType($imageName)
    {
        $path_parts = pathinfo($imageName);
        $ext = (isset($path_parts['extension'])) ? strtolower($path_parts['extension']) : null;
 
        switch($ext) {
            case 'jpg': 
            case 'jpeg': 
                $mime = 'image/jpeg';
                break;
            case 'gif': 
                $mime = 'image/gif';
                break;
            case 'png': 
                $mime = 'image/png';
                break;
            case 'svg': 
                $mime = 'image/svg+xml';
                break;                
            default:
                $mime = 'image/jpeg';
                break;
        }

        return $mime;
    }

    /**
     * Echo the actual image data with the content lenght header
     * 
     * @param string $image    The image data
     * @param string $mimeType The mime type
     * 
     * @return void
     */
    protected function echoImage(&$image, $mimeType)
    {
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' .(string)(strlen($image)));
        echo $image;
        exit;
    }
}
