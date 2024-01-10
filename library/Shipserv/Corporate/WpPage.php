<?php

class Shipserv_Corporate_WpPage
{
    /**
     * @var Int $id
     */
    public $id;
    
    /**
     * @var String $slug
     */
    public $slug;
    
    /**
     * @var String $link
     */
    public $link;
    
    /**
     * @var String $dateGmt
     */
    public $dateGmt;
    
    /**
     * @var String $date
     */
    public $dateModifiedGmt;
    
    /**
     * @var String $htmlTitle
     */
    public $htmlTitle;

    /**
     * @var String $htmlDescription
     */
    public $htmlDescription;
    
    /**
     * @var String $htmlContent   this is the content returned into wp api json reponse 
     */
    public $htmlContent;

    /**
     * @var String $htmlFullBody   this is the string returned by directly calling $this->link
     */
    public $htmlFullBody;    
    
    
    /**
     * Receive as input the array (json converted to array) that wp gave as output on page api call (/wp-json/wp/v2/pages/13)
     * @param array $wpApiResponseBody
     * @param String $htmlFullBody
     */
    public function __construct(array $wpApiResponseBody, $htmlFullBody)
    {
        $this->id = $wpApiResponseBody['id'];
        $this->slug = $wpApiResponseBody['slug'];
        //$this->link = $wpApiResponseBody['link'];
        $this->link = self::_wp2pubLink($wpApiResponseBody['link']);
        $this->dateGmt = $wpApiResponseBody['date_gmt'];
        $this->dateModifiedGmt = $wpApiResponseBody['modified_gmt'];
        $this->htmlTitle = $wpApiResponseBody['title']['rendered'];
        $this->htmlDescription = self::_getHtmlDescription($wpApiResponseBody);
        $this->htmlContent = self::transformHtml($wpApiResponseBody['content']['rendered']);
        $this->htmlFullBody = self::transformHtml($htmlFullBody);
    }
    
    
    /**
     * Given the $wpApiResponseBody objec, get the meta description value which was set through ACF SEO section
     * 
     * @param Array $wpApiResponseBody
     * @return String
     */
    private static function _getHtmlDescription($wpApiResponseBody)
    {
        $description = '';
        
        if (!isset($wpApiResponseBody['acf']) || !isset($wpApiResponseBody['acf']['page_content']) || !$wpApiResponseBody['acf']['page_content']) {
            return $wpApiResponseBody['title']['rendered'];
        }
        foreach ($wpApiResponseBody['acf']['page_content'] as $acfSection) {
            if (isset($acfSection['acf_fc_layout']) && $acfSection['acf_fc_layout'] === 'seo') {
                $description = $acfSection['meta_description'];
                break;
            }
        }
        return $description;
    }
    
    
    /**
     * WP and ACF use the WP host when defining links. But instead of the WP host we rather need the Pages host in all links! Let's use relative links 
     * Delete also some useless <link>
     * 
     * @param String $originalHtml
     * @return String
     */
    public static function transformHtml($originalHtml)
    {
        $config = Shipserv_Object::getConfig();
        
        //regex pattern
        $wpHttpHost = str_replace('http://', 'https://', $config->wordpress->baseurl->external);
        $wpHttpHost = str_replace('https://', 'https?://', $wpHttpHost);
        $wpHttpHost = str_replace('/', '\/', $wpHttpHost);
        $wpHttpHost = str_replace('.', '\.', $wpHttpHost);
        
        $modifiedHtml = '';
        $separator = "\r\n";
        $line = strtok($originalHtml, $separator); //tokenize string
        while ($line !== false) {
            $newLine = $line;

            //Replace the permalink host of every href link
            $newLine = preg_replace('/href="' . $wpHttpHost . '\/(?!wp-content\/)/i', 'href="/info/', $newLine);
            $newLine = preg_replace('/href=\'' . $wpHttpHost . '\/(?!wp-content\/)/i', 'href=\'/info/', $newLine);

            //Some Pages links are hardcoded as they come from WP admin. Here we replace them with correct environment 
            $newLine = preg_replace('/www.shipserv.com/i', $config->shipserv->application->hostname, $newLine);
            
            //Remove JQuery, as it will be added by Pages as well (and don't want to duplicate it): may remove this hacky solution to remove js script after removed from wordpress codebase (that's better solution of course) 
            if (strpos($newLine, '/wp-includes/js/jquery/jquery.js') !== false || strpos($newLine, '/wp-includes/js/jquery/jquery-migrate.min.js') !== false) {
            	$newLine = preg_replace('#<script(.*?)(/wp-includes/js/jquery/jquery.js|/wp-includes/js/jquery/jquery-migrate.min.js)(.*?)>(.*?)</script>#i', '', $newLine);
            }
            
            /**
             * The following code was needed to remove sme useless meta tags. Now it's not needed anymore because we changed the WP shipserv template removing such content 
             * 
            //Remove canonical (the right one is already correctly defined by Pages, while WP defines the wrong one)
            $newLine = preg_replace('/(<link rel=["\']canonical["\'] .+ href=["\'])(https?:\/\/.+\.com\/)(.+["\'](\/>| .*\/>))/i', '', $newLine);
            
            //Remove some useless <link> (not supported by our pages+wp architecture as not needed)
            $newLine = preg_replace('/(<link rel=["\'](shortlink|wlwmanifest|EditURI|api\.w\.org)["\'] .+ href=["\'])(https?:\/\/.+\.com\/)(.+["\'](\/>| .*\/>))/i', '', $newLine);

            //Delete title from html in case it was added
            $newLine = preg_replace('/<title>.*<\/title>/i', '', $newLine);

            //Delete meta desription from html in case it was added
            $newLine = preg_replace('/<meta name=["\']description["\'] +content=["\'].*["\'] +\/>/i', '', $newLine);
            */
            
            //Save the new line
            $modifiedHtml .= "\n" . $newLine;
            
            $line = strtok($separator); //Next string token
        }
        //hack to let marketing team preview pages before publishing them
        if (substr($_SERVER['REQUEST_URI'], 0, 19) === '/info/private-page/' || substr($_SERVER['REQUEST_URI'], 0, 19) === '/info/private-post/') {
            $modifiedHtml .= "<script>$('#wpadminbar').remove(); $('div.headerSpace').css('height', '28px');</script>";
        }
        
        return $modifiedHtml;
    }
 
    
    
    /**
     * Transform the wp link to be called only internally, to its public equivalent
     * @param String $wpLink   wp link to be called only internally 
     * @return String   shipserv public link
     */
    private static function _wp2pubLink($wpLink)
    {
        $config = Shipserv_Object::getConfig();
        $pattern = str_replace('/', '\/', $config->wordpress->baseurl->external);
        $pattern = str_replace('.', '\.', $pattern);
        $pattern = '/' . $pattern . '/i';
        return preg_replace($pattern, $config->shipserv->application->hostname . '/info', $wpLink);
    }
    
}
