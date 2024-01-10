<?php

/**
 * Helper to form URLs for supplier profiles in a standardised way.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_View_Helper_SupplierProfileUrl extends Zend_View_Helper_Abstract
{
    /**
     * @param array $supplier Standard supplier array as returned from service.
     * @param string $source Plain key indicating source of profile view
     *      (e.g. 'SEARCH', or 'BROWSE_AZ'). This is converted into an
     *      obscured token and appended as a query string parameter.
     * @param array $params Additional parameters as associative array.
     *
     * @return string URL for supplier profile
     */
    public function supplierProfileUrl ($supplier, $source = null, array $params = array())
    {
		if (is_object($supplier))
		{
			$supplier = array('name' => $supplier->name,
							  'tnid' => $supplier->tnid);
		}
		
        $url = '/supplier/profile/s/' . self::formatStringForURI($supplier['name']) . '-' . $supplier['tnid'];
        
        if (array_key_exists(Myshipserv_Controller_Action_Helper_ProfileSource::PARAM_NAME, $params))
            throw new Exception('Parameter clash');
        
        if ($source != '') {
            $sourceHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('ProfileSource');
            $params[Myshipserv_Controller_Action_Helper_ProfileSource::PARAM_NAME] = $sourceHelper->getObscuredKey($source);
        }
        
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
    
	/**
	 * Replaces spaces with -s and cleans up the name
	 * 
	 * @access public
	 * @static
	 * @return string Formatted, URL-safe string
	 */
	private static function formatStringForURI ($string)
	{
		$temp   = preg_replace('/(\W){1,}/', '-', $string);
		$string = strtolower(preg_replace('/-$/', '', $temp));
		
		return $string;
	}
}
