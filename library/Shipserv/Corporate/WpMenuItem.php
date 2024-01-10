<?php

class Shipserv_Corporate_WpMenuItem
{
    /**
     * @var Int $id
     */
    public $id;
    
    /**
     * @var Int $parent
     */
    public $parent;
    
    /**
     * @var Int $order
     */
    public $order;
    
    /**
     * @var String $title
     */
    public $title;
    
    /**
     * @var String $url
     */
    public $url;
     
    /**
     * @var Array of Shipserv_Corporate_WpMenuItem
     */
    public $children;
    
    
    /**
     * Receive as input the array (json converted to array) corresponding to ONE item of the menu
     * Ex: {"id":4,"order":1,"parent":0,"title":"Sample Page","url":"http:\/\/wp.myshipserv.com\/sample-page","attr":"","target":"","classes":"","xfn":"","description":"","object_id":2,"object":"page","type":"post_type","type_label":"Page"}
     * @param array $item
     */
    public function __construct(array $item)
    {
        $this->id = $item['id'];
        $this->parent = $item['parent'];
        $this->order = $item['order'];
        $this->title = $item['title'];
        $this->url = $item['url'];
        if ($item['type'] !== 'custom') {
            $this->url = self::_transformUrl($item['url']);
        }
        
        $this->children = array();
        if (isset($item['children']) && is_array($item['children']) && count($item['children'])) {
            foreach ($item['children'] as $children) {
                $this->children[] = new Shipserv_Corporate_WpMenuItem($children);
            }
        }
    }


    /**
     * Receive as input the array (json converted to array) that wp gave as output on menu api call (/wp-json/wp-api-menus/v2/menus/2)
     * @param array $wpApiResponse
     * @return Shipserv_Corporate_WpMenuItem[]
     */    
    public static function wpApiResponse2WpPageList($wpApiResponse)
    {
        $wpMenu = array();
        foreach ($wpApiResponse['items'] as $item) {
            $wpMenu[] = new Shipserv_Corporate_WpMenuItem($item);
        }
        return $wpMenu;
    }
    

    /**
     * Transform the worpress menu item url to our SS Pages url
     * @param String $wpUrl
     * @return String
     */
    private static function _transformUrl($wpUrl)
    {
        $path = parse_url($wpUrl, PHP_URL_PATH);
        $query = parse_url($wpUrl, PHP_URL_QUERY);
        if ($path === '/blog/') {
            $href = $path;
        } else {
            $href = '/info' . $path;
        }
        $href = rtrim($href, '/');
        if ($query) {
            $href =     '?' . $query;
        }

        return $href;
    }    
}
