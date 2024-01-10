<?php

/**
 * Represent a test node
 *
 * Class Myshipserv_Image_TextNode
 */
class Myshipserv_Image_TextNode
{

    const
        AL_LEFT = 0,
        AL_RIGHT = 1,
        AL_CENTER = 3;

    protected $text;
    protected $rect;
    protected $horizontalAlign;
    protected $font;
    protected $fontSize;
    protected $color;


    /**
     * Myshipserv_Image_TextNode constructor.
     * @param string $text
     * @param int $top
     * @param int $left
     * @param int $right
     * @param int $bottom
     */
    public function __construct($text, $top = 0, $left = 0, $right = 0, $bottom = 0)
    {
        $this->text = $text;
        $this->rect = new Myshipserv_Image_Rect($top, $left, $right, $bottom);

        // default values
        $this->horizontalAlign = self::AL_LEFT;
        $this->font = '/Lato2OFL/Lato-Bold.ttf';
        $this->fontSize = 12;
        $this->color = '#FFFFFF';
    }

    /**
     * Get the actual text
     *
     * @return mixed
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Return rectangle
     *
     * @return Myshipserv_Image_Rect
     */
    public function getRect()
    {
        return $this->rect;
    }

    /**
     * Set alignment
     *
     * @param int $align
     * @throws Myshipserv_Exception_MessagedException
     */
    public function setHorizontalAlign($align)
    {
        switch ($align) {
            case self::AL_LEFT;
            case self::AL_CENTER;
            case self::AL_RIGHT;
                $this->horizontalAlign = $align;
                break;
            default:
                throw new Myshipserv_Exception_MessagedException('Invalid alignment set in Myshipserv_Image_TextNode:setHorizontalAlign', 500);
        }
    }

    /**
     * Return horizontal align
     *
     * @return int
     */
    public function getHorizontalAlign()
    {
        return $this->horizontalAlign;
    }

    /**
     * Set the font
     *
     * @param string $fontName
     */
    public function setFont($fontName)
    {
        $this->font = $fontName;
    }


    /**
     * Setting the font size
     *
     * @param int $fontSize
     */
    public function setFontSize($fontSize)
    {
        $this->fontSize = (int)$fontSize;
    }


    /**
     * Set the color
     * @param string $color
     */
    public function setColor($color)
    {
        $this->color = $color;
    }

    /**
     * Get the color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Get the actual font name
     *
     * @return string
     */
    public function getFont()
    {
        return $this->font;
    }

    /**
     * Getting the actual font size
     *
     * @return int
     */
    public function getFontSize()
    {
        return $this->fontSize;
    }


}