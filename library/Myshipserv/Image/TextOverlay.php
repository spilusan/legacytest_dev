<?php

/**
 * Add a text overlay to an image from the file structure
 *
 * Class Myshipserv_Image_Textoverlay
 */
class Myshipserv_Image_TextOverlay
{

    protected $imageResource;
    protected $fontPath;

    /**
     * Myshipserv_Image_TextOverlay constructor.
     * @param string $imageFile
     * @throws Myshipserv_Exception_MessagedException
     */
    public function __construct($imageFile)
    {
        // Create Image From Existing File
        if (!file_exists($imageFile)) {
            throw new Myshipserv_Exception_MessagedException('Image file "' . $imageFile . '" does not exists"', 500);
        }

        try {
            $this->imageResource = imagecreatefromjpeg($imageFile);

        } catch (Exception $e) {
            throw new Myshipserv_Exception_MessagedException('Cannot create image: ' . $e->getMessage(), 500);
        }

        $this->fontPath =  realpath(APPLICATION_PATH . '/../resources/fonts');
    }

    /**
     * @param Myshipserv_Image_TextNode $textNode
     * @throws Myshipserv_Exception_MessagedException
     */
    public function addTextNode(Myshipserv_Image_TextNode $textNode)
    {
        $text = $textNode->getText();
        $rect = $textNode->getRect();
        $fontSize = $textNode->getFontSize();
        $fontColor = $textNode->getColor();
        $alignment = $textNode->getHorizontalAlign();

        list($r, $g, $b) = sscanf($fontColor, "#%02x%02x%02x");

        $angle = 0; //The angle in degrees, with 0 degrees being left-to-right reading text. Higher values represent a counter-clockwise rotation. For example, a value of 90 would result in bottom-to-top reading text.
        $leftPos = $rect->left();
        $topPos = $rect->top();
        $font = $this->fontPath . $textNode->getFont();

        if (!file_exists($font)) {
            throw new Myshipserv_Exception_MessagedException('Font ' . $font . ' does not exits');
        }

        // Allocate A Color For The Text
        $color = imagecolorallocate($this->imageResource, $r, $g, $b);

        $typeSpace = imagettfbbox($fontSize, $angle, $font, $text);

        $textWidth = abs($typeSpace[4] - $typeSpace[0]) + 10;
        $correctedLeftPos = $this->getLeftPosition($alignment, $textWidth, $leftPos);

        // Print Text On Image
        imagettftext($this->imageResource, $fontSize, $angle, $correctedLeftPos, $topPos, $color, $font, $text);
    }

    /**
     * Render the actual download
     */
    public function render()
    {
        header('Content-type: image/jpeg');
        header('Cache-Control: no-store, no-cache');

        // Send Image to Browser and clear memory
        imagejpeg($this->imageResource);
        imagedestroy($this->imageResource);
    }

    /**
     * Output the file with download file name header
     *
     * @param string $donwloadFilename
     */
    public function renderAndDownload($donwloadFilename)
    {
        header('Content-type: image/jpeg');
        header('Cache-Control: no-store, no-cache');
        header('Content-Disposition: attachment; filename="' . $donwloadFilename . '"');

        // Send Image to Browser and clear memory
        imagejpeg($this->imageResource);
        imagedestroy($this->imageResource);
    }

    /**
     * @param int $align
     * @param int $textWidth
     * @param int $leftPos
     * @return int
     */
    public function getLeftPosition($align, $textWidth, $leftPos)
    {
        switch ($align)
        {
            case Myshipserv_Image_TextNode::AL_RIGHT:
                return $leftPos - $textWidth;
            case Myshipserv_Image_TextNode::AL_CENTER:
                return $leftPos - round($textWidth / 2, 0);
            default:
                return $leftPos;
        }
    }
}
