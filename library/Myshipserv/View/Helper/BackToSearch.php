<?php

/**
 * Provides information to form 'back to search' link on profile page. According to
 * how the user got into the profile page, the link back is formed in different ways.
 * This is implemented by pulling the last URL off a dedicated stack held in the session.
 */
class Myshipserv_View_Helper_BackToSearch extends Zend_View_Helper_Abstract
{
	
	/**
	 * Sets up the backToSearch helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_BackToSearch
	 */
	public function backToSearch()
	{
		return $this;
	}

    /**
     * Return URL for 'back to search' link, or empty string.
     * 
     * @return string
     */
    public function getUrl()
    {
	    $history = Shipserv_Helper_History_Custom::getSearchHistory();
        $searchUrl = $history->getMostRecentUrl();

		if (strlen($searchUrl)) {
			$urlArr = parse_url($searchUrl);

			if (is_array($urlArr)) {
				$paramArr = array();
				parse_str($urlArr['query'], $paramArr);
				
				// unset tracking parameter
				unset($paramArr[Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME]);
				// unset the newSearch field
				unset($paramArr['newSearch']);
				
				// Rebuild URL without removed parameters
				$urlArr['query'] = http_build_query($paramArr);
				$searchUrl = implode(
					'',
					array(
						$urlArr['path'],
						(strlen($urlArr['query']) ? '?' : ''),
						$urlArr['query']
					)
				);
			}
		} else {
		    $searchUrl = "/";   // if there is nowhere to return, go to the homepage rather than to the current one
        }

		return $searchUrl;
    }
}
