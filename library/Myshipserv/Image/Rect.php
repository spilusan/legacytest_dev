<?php

/**
 * Represents a rectangle area
 * Class Mysipserv_Image_Rect
 */
class Myshipserv_Image_Rect
{

    protected $top;
    protected $left;
    protected $right;
    protected $bottom;

    /**
     * Mysipserv_Image_Rect constructor.
     * @param int $top
     * @param int $left
     * @param int $right
     * @param int $bottom
     */
    public function __construct($top = 0, $left = 0, $right = 0, $bottom = 0)
    {
        $this->top = $top;
        $this->left = $left;
        $this->right = $right;
        $this->bottom = $bottom;
    }

    /**
     * Get top
     * @return int
     */
    public function top()
    {
        return $this->top;
    }

    /**
     * Get left
     * @return int
     */
    public function left()
    {
        return $this->left;
    }

    /**
     * Get right
     * @return int
     */
    public function right()
    {
        return $this->right;
    }

    /**
     * Get bottom
     * @return int
     */
    public function bottom()
    {
        return $this->bottom;
    }

}