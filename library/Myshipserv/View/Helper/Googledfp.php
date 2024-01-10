<?php

/**
 * Helper for adding google DFP ads to page
 * 
 * @category Class
 * 
 */
class Myshipserv_View_Helper_Googledfp extends Zend_View_Helper_Abstract
{
    protected $advertisments = array();
    protected $targetings = array();

    /**
     * Set the view for the helper object, required by Zend
     * 
     * @param Zend_View_Interface $view 
     * 
     * @return null
     */
    public function setView(Zend_View_Interface $view) 
    {
        $this->_view = $view;
    }

    /**
     * Return the view itself for the view rendered
     * 
     * @return Myshipserv_View_Helper_GoogleDfp
     */
    public function googledfp() 
    {
        return $this;
    }

    /**
     * Add an advert
     * 
     * @param string $adv     Advertisment dfp tag
     * @param string $tagName Name of element ID
     * @param int    $width   Advertisment width, default 180
     * @param int    $height  Advertisment width, default 300
     * 
     * @return Myshipserv_View_Helper_GoogleDfp
     */
    public function add($adv, $tagName = null, $width = 180, $height = 300)
    {
        $this->advertisments[] = array(
            'adv' => $adv,
            'tagName' => ($tagName === null) ? $adv : $tagName,
            'width' => $width,
            'height' => $height
        );
        return $this;
    }

    /**
     * This one is for replacing the old GA_googleAddSlot
     * 
     * @param string $key
     * @param string $value
     * 
     * @return Myshipserv_View_Helper_GoogleDfp
     */
    public function addTargeting($key, $value)
    {
        $this->targetings[] = array(
            'key' => $key,
            'value' => $value
        );
    }
    /**
     * Renders the hader for google ads into the page
     * 
     * @param bool $debug Turn on consol debugging
     * 
     * @return Myshipserv_View_Helper_GoogleDfp
     */
    public function render($debug = false)
    {

        if (count($this->advertisments) > 0) {
            $config = $GLOBALS['application']->getBootstrap()->getOptions();
            $networkCode = $config['google']['dfp']['network']['code'];

            $fixedHeader = '
                var googletag = googletag || {};
                googletag.cmd = googletag.cmd || [];
            ';

            $adList = 'googletag.cmd.push(function() {' . PHP_EOL;

            if ($debug === true) {
                $adList .= 'googletag.openConsole();' . PHP_EOL;
            }
           
            // SPC-1384 targetings must be grouped
            $targetGroups = $this->groupAttrsByKey($this->targetings);
            
            foreach ($targetGroups as $key => $targeting) {
                $targetValue = $this->convertAttrGroupToSingleLine($targeting);
                $adList .= 'googletag.pubads().setTargeting(\'' . 
                $key . '\', ' . 
                $targetValue . ');' .
                PHP_EOL;
            }
 
            foreach ($this->advertisments as $advertisment) {
                $adList .= 'googletag.defineSlot(\'/' .
                $networkCode .
                '/' . $advertisment['adv'] . '\'' .
                ', [' . $advertisment['width'] . 
                ', ' . $advertisment['height'] . '],' .
                '\'' . $advertisment['tagName'] .'\').addService(googletag.pubads());' .
                PHP_EOL;
            }

            $adList .= 'googletag.pubads().enableSingleRequest();' . PHP_EOL;
            $adList .= 'googletag.enableServices();' . PHP_EOL;


            $adList .= '});' . PHP_EOL;
            
            $headscript = $this->_view->headScript();
            $headscript->setAllowArbitraryAttributes(true); 

            $headscript = $this->_view->headScript();
            $headscript->prependFile(
                'https://www.googletagservices.com/tag/js/gpt.js',
                'text/javascript',
                array('async' => 'async')
            );
   
            $headscript->appendScript($fixedHeader);
            $headscript->appendScript($adList);
        }

        return $this;
    }

    /**
     * Convert Dae to timeStamp
     * 
     * @param string $string Date String
     * @return DateTime
     */
    protected function convertDateToTimestamp($string)
	{
		$date = new DateTime();
		$date->setDate(substr($string, 0, 4), substr($string, 4, 2), substr($string, 6, 2));
		return $date->format('U');
	}
    
    /**
     * return the list of slots for conditon
     * @param array $zone
     * @param array $viewSearchValues
     * 
     * @return array
     */
	public function fillSlotWithCondition($zone, $viewSearchValues)
	{
        $displayedSlot = array();
        foreach ((array) $zone['content']['ads']['slot'] as $slot) {
            // check the condition
            if (!empty($zone['content']['adsConditions'])) {
                foreach ($zone['content']['adsConditions']['condition'] as $rule) {
                    if ($rule['slot'] == $slot) {
                        foreach ($rule['searchConditions'] as $condition) {
                            if (isset($condition['searchWhat'])) {
                                if ((($_GET['searchWhat'] == "" && is_null($condition['searchWhat'])) || ($_GET['searchWhat'] == $condition['searchWhat']))) {
                                    if (($viewSearchValues['searchWhere'] == "" && is_null($condition['searchWhere'])) || ($this->viewSearchValues['searchWhere'] == $condition['searchWhere'])) {
                                        // check if there's a certain period set
                                        if (isset($condition['startDate']) && isset($condition['endDate']) && $condition['startDate']!="" && $condition['endDate']!="") {
                                            $start = $this->convertDateToTimestamp($condition['startDate']);
                                            $end = $this->convertDateToTimestamp($condition['endDate']);
                                            $now = new DateTime();
                                            if ($now->format('U') >= $start && $now->format('U') <= $end) {
                                                $displayedSlot[0] = $slot;
                                            }
                                        } else {
                                            $displayedSlot[0] = $slot;
                                        }
                                    }
                                }
                                if (($condition['searchWhat'] == "DEFAULT" ) /*&& count($displayedSlot) == 0*/ ) {
                                    if (( "DEFAULT" == $condition['searchWhere'])) {
                                        $displayedSlot[] = $slot;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                return ($zone['content']['ads']['slot']);
            }
        }

        return $displayedSlot;
    }
    
    /**
     * Return DIV with slot ID, and Name
     * 
     * @param string $slotName
     * @param string $className
     * @return string
     */
    public function fillSlot($slotName, $className = null)
    {
        $class = ($className === null) ? '' : ' class="' . _htmlspecialchars($className) . '"';
        $slotName = _htmlspecialchars($slotName);

        return 
            '<div id="' . $slotName . '"' . $class . '>
                <script>
                    googletag.cmd.push(function() {
                        googletag.display("' . $slotName . '");
                    });
                </script>
            </div>';
    }

    /**
     * Convert an array of attr list to a single line, If one line, just a quoted value else an array definition (JavaScript)
     * 
     * @param array $value
     * @return string
     */
    protected function convertAttrGroupToSingleLine($value)
    {
        if (count($value) === 1) {
            return '\'' . $value[0] . '\'';
        } else {
            return'[' .
            implode(
                ' ,',
                array_map(
                    function ($value) {
                        return '\'' . $value . '\'';
                    },
                    $value
                )
            ) .
            ']';
        }
    }

    /**
     * Regroup array by aggregating keys
     * 
     * @param array $attributeArray
     * @return array
     */
    protected function groupAttrsByKey($attributeArray)
    {
        $targetGroups = array();
        foreach ($attributeArray as $targeting) {
            $targetGroups[$targeting['key']][] = $targeting['value'];
        }
        return $targetGroups;
    }
}
