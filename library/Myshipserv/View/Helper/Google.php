<?php
/**
 * This class is deprecated as Google deprecated the GAG tags in 14 Jan 2019
 * 
 * use the next class Myshipserv_View_Helper_GoogleDfp instead
 */

class Myshipserv_View_Helper_Google extends Zend_View_Helper_Abstract
{
	/**
	 * Sets up the Google helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_Google
	 */
	public function google ()
	{
		return $this;
	}
	
	public function init ()
	{
		
	}
	
	private function convertDateToTimestamp($string)
	{
		$date = new DateTime();
		$date->setDate(substr($string, 0, 4), substr($string, 4,2), substr($string, 6,2));
		return $date->format('U');
	}
	
	public function fillSlotWithCondition( $zone, $viewSearchValues )
	{
		$displayedSlot = array();
		foreach( (array) $zone['content']['ads']['slot'] as $slot )
		{
			// check the condition
		    if( !empty($zone['content']['adsConditions']) )
		    {
				foreach( $zone['content']['adsConditions']['condition'] as $rule )
				{
			    	if( $rule['slot'] == $slot)
			        {
						foreach( $rule['searchConditions'] as $condition )
						{
							if( isset( $condition['searchWhat'] ) )
							{
								if( ( ($_GET['searchWhat'] == "" && is_null($condition['searchWhat']) ) || ($_GET['searchWhat'] == $condition['searchWhat']) )  ) 
								{
									if( ($viewSearchValues['searchWhere'] == "" && is_null($condition['searchWhere'] )) || ($this->viewSearchValues['searchWhere'] == $condition['searchWhere']) ) 
									{
										// check if there's a certain period set
										if( isset($condition['startDate']) && isset($condition['endDate']) && $condition['startDate']!="" && $condition['endDate']!="")
										{
											$start = $this->convertDateToTimestamp($condition['startDate']);
											$end = $this->convertDateToTimestamp($condition['endDate']);
											$now = new DateTime();
											if( $now->format('U') >= $start && $now->format('U') <= $end )
											{
												$displayedSlot[0] = $slot;
											}
										}
										else
										{
											$displayedSlot[0] = $slot;
										}
									}
								}
								if( ( $condition['searchWhat'] == "DEFAULT" ) /*&& count($displayedSlot) == 0*/ ) 
								{
									if( ( "DEFAULT" == $condition['searchWhere']) ) 
									{
										$displayedSlot[] = $slot;
									}
								}
							}
						}
			        }
				}
		     }
		     else 
		     {
			 	return ($zone['content']['ads']['slot']);
		     }
		}
		return ($displayedSlot);
		
	}
	
	public function fillSlot ($slot)
	{
		$ad = '<script type="text/javascript">';
		$ad.= "<!--\n";
		$ad.= 'GA_googleFillSlot("'.$slot.'");';
		$ad.= " //-->\n";
		$ad.= '</script>';
		
		return $ad;
	}
	
}