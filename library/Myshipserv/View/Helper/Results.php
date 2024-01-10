<?php

/**
 * Helper class for formatting results
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_View_Helper_Results extends Zend_View_Helper_Abstract
{

	/**
	 * Sets up the results helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_Results
	 */
	public function results ()
	{
		return $this;
	}

	public function init ()
	{

	}

	public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

	/**
	 * Generates an array containing formatted match reasons for a specific result
	 *
	 * @access public
	 * @param arary $document The result object
	 * @return array An array of match reasons
	 */
	public function matchReasons ($document, $searchWhat)
	{
		$matches = array();

		if ($document['nameMatch']) {
			$matches[] = 'Company Name: '.$this->view->string()->shorten($document['nameMatch'], 200);
		}

		if ($document['brandMatch']) {
			$brand = $this->view->string()->shorten($document['brandMatch'], 200);
			$brand = str_replace('(Non Authorized Reseller)', ' <span style="color: red; font-weight:bold; ">Non Authorized Reseller</span>', $brand);
			if( strstr($brand, 'Verified') === false && strstr($brand, 'Not verified') === false ){
				if( strstr( $brand, '(') !== false && strstr( $brand, ')') !== false ){
					$brand .= " <span style='color: red; font-weight:bold; '>Not verified</span>";
				}
			}
			 
			$matches[] = 'Brand: '.$brand;
		}

		if ($document['categoryMatch']) {
			$matches[] = 'Category: '.$this->view->string()->shorten($document['categoryMatch'], 200);
		}

		if ($document['descriptionMatch']) {
                        $s = strip_tags($document['descriptionMatch'], "<em>");
			$matches[] = 'Description: '.$this->view->string()->shorten(strtolower($s), 200);
		}

		if ($document['catalogueMatch']) {
			$matchLink = '<a href="/supplier/profile/s/'.$this->view->uri()->sanitise($document['name']).'-'.$document['tnid'].'?q='.urlencode($searchWhat).'#catalogue">'.$document['catalogueMatchesFound'].' matches</a>';

			$matches[] = 'Catalog: ' . $matchLink;
		}

		if ($document['attachmentMatch']) {
			$match = 'Document Match: '.$this->view->string()->shorten(strtolower($document['attachmentMatch']['highlight']), 200);

			//Condition commented with DE7029 
			//if ($document['attachmentMatch']['MA']) {
				// the id and class are used to identify the links for triggering interal and Google Analytics calls
				$match.= ' <a id="tnid-doc-'.$document['tnid'].'" class="docMatch" href="'.$document['attachmentMatch']['url'].'" target="_blank" title="Open this document">View Document</a>';
			//}

			$matches[] = $match;
		}

		if ($document['transactionMatch']) {
			//$matches[] = 'Recently Supplied: '.$this->view->string()->shorten(strtolower($document['transactionMatch']), 200);
			$tmp = explode("\n", $document['transactionMatch']);
			$matches[] = 'Recently Supplied: <br /><span style="display: block;padding-left: 5px;">'.strtolower(implode( $tmp, "<br />")).'</span>';
		}

		if ($document['modelMatch']) {
			$matches[] = 'Model: '.$this->view->string()->shorten(strtolower($document['modelMatch']), 200);
		}

 		if ($document['brandModelMatch']) {
			$matches[] = 'Brand Model: '.$this->view->string()->shorten(strtolower($document['brandModelMatch']), 200);
		}

		return $matches;
	}

	/**
	 * Returns the traderank formatted for the pixel length on the results page
	 *
	 * @access public
	 * @param int $tradeRank
	 * @return int The number of pixels for the traderank bar
	 */
	public function tradeRankBar ($tradeRank)
	{
		return Myshipserv_View_Helper_Supplier::tradeRankToPxWidth (72, 110, $tradeRank);
	}
}
