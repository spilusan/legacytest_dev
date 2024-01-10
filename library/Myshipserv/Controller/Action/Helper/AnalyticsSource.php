<?php

/**
 * Abstract action helper used to aid tracking of source parameters for analytics,
 * i.e. from where on the site a search, or a supplier profile view is triggered.
 *
 * @author Anthony Powell <apowell@shipserv.com>
 */
abstract class Myshipserv_Controller_Action_Helper_AnalyticsSource extends Zend_Controller_Action_Helper_Abstract 
{    
	/**
	 * Fetches analytics source label from parameter of current request.
	 *
     * @access public
	 * @return string Analytics source label,
	 *      or 'DIRECT' if request is referred from an external site,
	 *      or '' if not present or recognised.
	 */
    public function getPlainKeyFromRequest ($paramName)
    {
        if (self::isRequestDirect())
		{
            return 'DIRECT';
        }
        
        $request = $this->getRequest();
        $searchSourceParam = $request->getParam($paramName, null);
		
		if (!is_numeric($searchSourceParam))
		{
			return $searchSourceParam;
		}
		
        return (string) $this->getKeyObscurer()->getPlainKey($searchSourceParam);
    }
	    
	/**
	 * Converts search source label into obscured key that may be passed publicly.
	 * Throws exception if key not recognised.
	 * 
	 * @access public
	 * @param string $plainKey search source label
	 * @return string a numeric string representing search source.
	 */
    public function getObscuredKey ($plainKey)
    {
        $res = $this->getKeyObscurer()->getObscuredKey($plainKey);
        if ($res == '')
		{
			throw new Exception("Unrecognised plain key: $plainKey");
		}
		
        return $res;
    }
    
    /**
     * Allows implementing classes to manage own list of source labels,
     * e.g. for search source, or profile page source.
     *
     * @return Myshipserv_KeyObscurer
     */
    abstract protected function getKeyObscurer ();
    
    /**
     * @return bool Indicates whether current request is
     *      direct (referred from external host),
     *      or referred from site itself.
     */
    public static function isRequestDirect ()
    {
        $ref = self::getReferrer();
        return (
            //$ref != '' &&
            $_SERVER['HTTP_HOST'] != $ref
        );
    }
    
    /**
     * @return string Referrer host name, or ''.
     */
    private static function getReferrer ()
    {
        $refUrl = (string) @$_SERVER['HTTP_REFERER'];
        $refUrlParsed = @parse_url($refUrl);
        if (is_array($refUrlParsed)) {
            return (string) @$refUrlParsed['host'];
        }
        return '';
    }
}
