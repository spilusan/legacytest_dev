<?php

/**
 * General supplier helper
 * 
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ Ltd
 */
class Myshipserv_View_Helper_Supplier extends Zend_View_Helper_Abstract
{
	/**
	 * Sets up the Supplier helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_GMap
	 */
	public function supplier ()
	{
		return $this;
	}
	
	public function init ()
	{
		
	}
	
	/**
	 * Calculates width of graphic used to display Traderank bar.
	 * Traderank <= 3 results in 1/3 of a 'person' on the bar.
	 * Traderank >= 18 results in 4 & 2/3 of a 'person' on the bar. (UPDATE 2010-10-04 DS - should now show full 5 stars if TR = 20)
	 * 
	 * @param int $zeroOffset Pixel width for 0 position (i.e. at $zeroOffset + 1, bar is just visible)
	 * @param int $fullOffset Pixel width for max position (i.e. at $fullOffset, bar is at maximum extension)
	 * @param int $tradeRank
	 * @return int Width of bar in pixels.
	 */
	public static function tradeRankToPxWidth ($zeroOffset, $fullOffset, $tradeRank)
	{
        if ($tradeRank < 3) $tradeRank = 3;
        $trNormed = ($tradeRank - 3) / 15;

        $adjMinOffset = 1.2 * ($fullOffset - $zeroOffset) / 20 + $zeroOffset;
		$adjMaxOffset = 20 * ($fullOffset - $zeroOffset) / 20 + $zeroOffset;
		
		return round($trNormed * ($adjMaxOffset - $adjMinOffset) + $adjMinOffset);
	}
	
	public function traderank ($supplier)
	{
		return self::tradeRankToPxWidth (26, 89, $supplier->tradeRank);
	}
	
	public function traderankCompetitor ($supplier)
	{
		return self::tradeRankToPxWidth (0, 65, $supplier->tradeRank);
	}
	
	public function traderankSmall ($supplier)
	{
		return self::tradeRankToPxWidth (70, 112, $supplier->tradeRank);
	}
	public function traderankSmallButton ($supplier)
	{
		return self::tradeRankToPxWidth (96, 130, $supplier->tradeRank);
	}

	public function traderankHugeStars ($supplier)
	{
		return self::tradeRankToPxWidth (0, 133, $supplier->tradeRank);
	}
	
	/**
	 *
	 *
	 *
	 *
	 */
	public function overallRating ($ratings)
	{
		$totalRatings = 0;
		$rating = 0;
		
		// positive
		$totalRatings += $ratings['countPositive'];
		$rating       += $ratings['countPositive'];
		
		// neutral
		$totalRatings += $ratings['countNeutral'];
		
		// negative
		$totalRatings += $ratings['countNegative'];
		$rating       -= $ratings['countNegative'];
		
		if ($totalRatings == 0)
		{
			return 'null';
		}
		
		$overallRating = $rating / $totalRatings;
		
		if ($overallRating > 1/3)
		{
			return 'positive';
		}
		
		if ($overallRating < -1/3)
		{
			return 'negative';
		}
		
		return 'neutral';
	}
	
	/**
	 * Helper to preprocess brands into OEM blocks, etc.
	 *
	 * @access public
	 * @param object $brands
	 */
	public function brands ($brands)
	{
		$authorisedBrands = array();
		$nonVerifiedBrands = array();
		$listedBrands      = array();
		
		foreach ($brands as $brand)
		{
			switch ($brand["authLevel"]){
				case "OEM":
				case "AGT":
				case "REP":
					if ($brand["ownersCount"]>0 and $brand["isAuthorised"]!="N")
					{
						if (!$authorisedBrands[$brand["id"]])
						{
							$authorisedBrands[$brand["id"]] = array (
								"id" => $brand["id"],
								"name" => $brand["name"],
								"logoFileName" => $brand["logoFileName"],
								"authLevels" => array( Shipserv_BrandAuthorisation::$displayAuthNames[$brand["authLevel"]])
							);
						}
						else
						{
							$authorisedBrands[$brand["id"]]["authLevels"][]= Shipserv_BrandAuthorisation::$displayAuthNames[$brand["authLevel"]];
						}
					}
					else
					{
						if (!$nonVerifiedBrands[$brand["id"]])
						{
							$nonVerifiedBrands[$brand["id"]] = array (
								"id" => $brand["id"],
								"name" => $brand["name"],
								"authLevels" => array( Shipserv_BrandAuthorisation::$displayAuthNames[$brand["authLevel"]]),
								"pending"	=> ($brand["ownersCount"]>0)
							);
						}
						else
						{
							$nonVerifiedBrands[$brand["id"]]["authLevels"][]= Shipserv_BrandAuthorisation::$displayAuthNames[$brand["authLevel"]];
						}
					}
					break;
			}

		}

		foreach ($brands as $brand)
		{
			switch ($brand["authLevel"]){
				case "LST":
					if (!isset($listedBrands[$brand["id"]]) and !isset($nonVerifiedBrands[$brand["id"]]) and !isset($authorisedBrands[$brand["id"]])){
						$listedBrands[$brand["id"]] = array (
							"id" => $brand["id"],
							"name" => $brand["name"]
						);
					}
					break;
			}

		}
		
		$brands = array (
			"authorisedBrands" => $authorisedBrands,
			"nonVerifiedBrands"	=> $nonVerifiedBrands,
			"listedBrands" => $listedBrands
		);
		
		return $brands;
	}

	/**
	 * Helper to preprocess membership data
	 *
	 * @access public
	 * @param object $memberships
	 */
	public function memberships ($memberships)
	{
		$verifiedMemberships = array();
		$nonVerifiedMemberships = array();

		foreach ($memberships as $membership)
		{
			if ($membership["ownersCount"]==0 or $membership["is_authorised"]=="N")
			{
				$nonVerifiedMemberships[] = $membership;
			}
			else
			{
				$verifiedMemberships[] = $membership;
			}
		}

		$memberships = array (
			"verifiedMemberships" => $verifiedMemberships,
			"nonVerifiedMemberships"	=> $nonVerifiedMemberships
		);

		return $memberships;
	}

	/**
	 * Generates an address for a supplier
	 * 
	 * @access public
	 * @param object $supplier a Shipserv_Supplier object
	 * @param string $lineBreak
	 * @param array $options
	 * @return string 
	 */
	public function address ($supplier, $lineBreak = "<br />\n", $options = array())
	{
		$address = '';
		
		if (!$options['hideName'])
		{
			$address.= $supplier->name . $lineBreak;
		}
		
		if ($supplier->address1)
		{
			$address.= $supplier->address1 . $lineBreak;
		}
		
		if ($supplier->address2)
		{
			$address.= $supplier->address2 . $lineBreak;
		}
		
		$address.= $supplier->city;
		if ($supplier->state && $this->view->string()->alphaStringLength($supplier->state) > 1)
		{
			$address.= ', ' . $supplier->state;
		}
		$address.= $lineBreak;
		
		if ($supplier->zipCode)
		{
			$address.= $supplier->zipCode . $lineBreak;
		}
		
		$address.= $supplier->countryName . $lineBreak . $lineBreak;
		
		if(!$options['withholdContactDetails'] == true){
			
		
			if (!empty($options['url']) && $supplier->premiumListing == 1)
			{
				$address.= 'URL : ';
				$uri = '';
				if (!stristr($supplier->homePageUrl, 'http://'))
				{
					$uri = 'http://';
				}
				$uri.= $supplier->homePageUrl;
                                $displayURL = $supplier->homePageUrl;
                                if(strlen($displayURL) > 30){
                                    $displayURL = substr($displayURL, 0, 28) . "...";
                                }
                                
				$address.= '<a href="'.$uri.'" target="_blank" alt="' . $supplier->homePageUrl . '">'.$displayURL.'</a>' . $lineBreak;
			}
		
			if ($options['phone'])
			{
				if( strtolower( $supplier->countryCode ) == "us" || strtolower( $supplier->countryCode ) == "ca" )
				{
					$address.= 'Phone : '. $this->fixUsandCanadaNumber( $supplier->phoneNo ) . $lineBreak;
					$address.= 'After hours : ' . $this->fixUsandCanadaNumber($supplier->afterHoursNo ) . $lineBreak;
					$address.= 'Fax : ' . $this->fixUsandCanadaNumber( $supplier->faxNo ) . $lineBreak;
				}
				else 
				{
					$address.= 'Phone : '.$supplier->phoneNo . $lineBreak;
					$address.= 'After hours : '.$supplier->afterHoursNo . $lineBreak;
					$address.= 'Fax : '.$supplier->faxNo . $lineBreak;
				}
			}
			
		}

		return $address;
	}
	
	public function isUSAndCanadaNumber( $number )
	{
		return preg_match('/\+1 ([0-9]{3}) ([0-9]{3}) ([0-9]{2,4}).*/', $number );
	}
	
	public function fixUSAndCanadaNumber( $number )
	{
		//$number = "+44 191 456 6396";
		$extension = "";
		
		$old = $number;

		$number = trim( $number );
		
		if( ( $number[0] == "+" && $number[1] != "1" ) )
		{
			$countryCodes = array(93,355,213,376,244,267,54,374,61,43,994,241,973,880,245,375,32,501,229,975,591,387,267,55,673,359,226,257,855,237,1,238,236,235,56,86,57,269,243,242,506,225,385,53,357,420,45,253,766,593,20,503,240,291,372,251,679,358,33,241,220,995,49,233,30,472,502,224,245,592,509,504,36,354,91,62,98,964,353,972,39,875,81,962,7,254,686,850,82,965,996,856,371,961,266,231,218,423,370,352,389,261,265,60,960,223,356,692,222,230,52,691,373,377,976,382,212,258,95,264,674,977,31,64,505,227,234,47,968,92,680,507,675,595,51,63,48,351,974,40,7,250,868,757,783,685,378,239,966,221,381,248,232,65,421,386,677,252,27,34,94,249,597,268,46,41,963,992,255,66,670,228,676,867,216,90,993,688,256,380,971,44,1,598,998,678,379,58,84,967,260,263,995,886,277,302,160,252,995,61,61,672,687,689,262,590,590,508,681,682,683,690,44,44,44,263,440,246,357,283,344,500,350,663,290,648,669,683,670,339,852,853,298,299,594,590,596,262,340,297,599,47,247,290,381,970,212);
			$tmp = explode(" ", $number);
			if( count( $tmp ) > 1)
			{
				$cc = $tmp[0];
				
				if( in_array(str_replace("+","", $cc), $countryCodes) !== false )
				{
					return $number;
				}
			}
		}
		
		// strip 1 from the beginning
		if( $number[0] == "'" )
		{
			$number = substr( $number, 1, strlen( $number ) );
		}
		
		// if it starts with 00
		if( $number[0] == '0' && $number[1] == '0' )
		{
			$number = substr( $number, 2, strlen( $number ) );
			$number = trim( $number );
		}

		// if it starts with ++1
		if( $number[0] == '+' && $number[1] == '+' && $number[2] == '1' )
		{
			$number = substr( $number, 3, strlen( $number ) );
		}
		
		// if it starts with (1) OR [1]
		if( ( $number[0] == '(' && $number[1] == '1' && $number[2] == ')' ) || ( $number[0] == '[' && $number[1] == '1' && $number[2] == ']' ) )
		{
			$number = substr( $number, 3, strlen( $number ) );
			$number = trim( $number );
		}
		
		// if it starts with (+1)
		if( $number[0] == '(' && $number[1] == '+' && $number[2] == '1' && $number[3] == ')' )
		{
			$number = substr( $number, 4, strlen( $number ) );
		}

		// if it starts with +(1)
		if( $number[0] == '+' && $number[1] == '(' && $number[2] == '1' && $number[3] == ')' )
		{
			$number = substr( $number, 4, strlen( $number ) );
		}
		
		// if it starts with + 1
		if( $number[0] == '+' && $number[1] == ' ' && $number[2] == '1' )
		{
			$number = substr( $number, 3, strlen( $number ) );
		}
		
		// strip the + (if any) from the beginning
		if( $number[0] == '+' || $number[0] == '-' )
		{
			$number = substr( $number, 1, strlen( $number ) );
		}
		
		// strip 1 from the beginning
		if( $number[0] == '1' )
		{
			$number = substr( $number, 1, strlen( $number ) );
		}
		
		$number = trim( $number );
		
		$tmp = $number;
		
		// find out (24hrs) or ( 24 hrs) or ( string 24 )
		$result = preg_match('/\(([\s]{0,})([0-9]+)([\s]{0,})([A-Za-z]+)([\s]{0,})\)|\(([\s]{0,})([A-Za-z]+)([\s]{0,})([0-9]+)([\s]{0,})\)/', $number, $matches);
		if( $result === 1 )
		{
			$number = str_replace( $matches[0], "", $number);
			$string = $matches[0];
		}

		// find out where is the first character so we can split it
		if( strlen( $number ) > 10 )
		{
			for( $i=0; $i<strlen($number); $i++)
			{
				if( $i > 9 )
				{
					//if( is_numeric( $number[$i] ) === false )
					if( preg_match('/([A-Za-z\/])+/', $number[$i] ) )
					{
						$string = substr( $number, $i, strlen( $number ) );
						$strPos = $i;
						break;
					}
				}
				
			}
		}
		
		// extract only the number
		if( $strPos != "" )
		$number = substr( $number, 0, $strPos );
		
		// strip spaces
		$number = str_replace(" ", "", $number );
		
		// strip non numeric characters
		$number = preg_replace("/([^0-9])+/i", "", $number );
		
		// 
		if( strlen( $number ) > 10 && $strPos != "" )
		{
			$number = substr( $tmp, 0, 10 );
			$string = substr( $tmp, 10);
		}

		//check if number is empty or not
		if( strlen( $number ) > 3 )
		{
			if( strlen( $number ) === 9 )
			{
				return '+1 ' . preg_replace('/([0-9]{3})([0-9]{3})([0-9]{3})/', '$1 $2 $3', $number ) . ' ' . $string;
			}
			else if( strlen( $number ) > 9 )
			{
				return '+1 ' . preg_replace('/([0-9]{3})([0-9]{3})([0-9]{4})(.*)/', '$1 $2 $3 $4', $number ) . ' ' . $string;
			}
		}
		else
		{
			return '';
		}
	}
	
	
	public function smallAffiliateLogo($originalFileName)
	{
		$config  = Zend_Registry::get('config');
		
		$originalFormat = substr(strrchr($originalFileName, "."), 1);
		$originalName = substr($originalFileName,0,strpos($originalFileName, "."));
		
		return $config->shipserv->affManagement->images->urlPrefix . $originalName . "_23x23." . $originalFormat;
	}
	
	public function lineItemHasBeenChanged($changes, $lineItemNo, $column, $returnBoolean = false)
	{
		foreach( $changes as $change )
		{
			if( $change['RQLC_LINE_ITEM_NO'] == $lineItemNo )
			{
				if( $column == '' )
				{
					if( $change['RQLC_LINE_ITEM_STATUS'] == 'MOD')
					{
						if( $returnBoolean ) return true;
						return '<td style="color: #008b00; text-align: center;">changed</td>';
					}
					else if( $change['RQLC_LINE_ITEM_STATUS'] == 'DEC')
					{
						if( $returnBoolean ) return false;
						return '<td style="text-align: center; color: #e81e24; font-weight:bold;">declined</td>';
					}
					else if( $change['RQLC_LINE_ITEM_STATUS'] == 'NEW')
					{
						if( $returnBoolean ) return false;
						return '<td style="text-align: center; color: #00386e; font-weight:bold;">new line</td>';
					}
					else
					{
						if( $returnBoolean ) return false;
						return '<td>&nbsp;</td>';
					}
				}
				else 
				{
					if( $change[ $column ] != null )
					{
						if( $returnBoolean ) return true;
						return 'style="background-color: #83cc4e; font-weight:bold; font-style:italic;"';
					}
					else 
					{
						if( $returnBoolean ) return false;
						return '';
					}
				}
			}
		}
	}
	
	/**
	 * Returns true if the specified ID is managed brand
	 * @param array $brands Array of brands 
	 * @param int $id		ID is the brand id
	 * @return boolean
	 */
	public function isManagedBrand($brands, $id) {
		foreach ($brands as $brand) {
			if ($brand['ownersCount'] > 0 && (int)$brand['id'] === (int)$id) {
				return true;
			}
		}

		return false;
	}
}