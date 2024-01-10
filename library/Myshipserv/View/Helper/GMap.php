<?php

/**
 * Google Maps view helper
 * 
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ Ltd
 */
class Myshipserv_View_Helper_GMap extends Zend_View_Helper_Abstract
{
	/**
	 * Sets up the Google Map helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_GMap
	 */
	public function gmap ()
	{
		return $this;
	}
	
	public function init ()
	{
		
	}
	
	/**
	 * Generates a google map for a specified HTML block
	 * 
	 * Will use latitude/longitude if available, otherwise uses a Geocoder to estimate
	 * 
	 * @access public
	 * @param object $supplier A supplier object
	 * @param string $elementId The ID of the map element - also used as the name of the
	 *                          map where multiple maps might be on a page
	 * @param string $noMapHTML The HTML to show where no map can be shown
	 * @param array $options
	 * @return string The HTML to output in the view
	 */
	public function generate ($supplier, $elementId, $noMapHTML, $options = array())
	{
		$centerPoint = (isset($options['centerPoint'])) ? $options['centerPoint'] : 13; 
		
		$postOverlay = '';
		
		if ($options['mapControls'])
		{
			$postOverlay.= $elementId.'.addControl(new GMapTypeControl());'."\n";
			$postOverlay.= $elementId.'.addControl(new GLargeMapControl());'."\n";
		}
		
		if (isset($supplier->latitude) && isset($supplier->longitude))
		{
			$jsInvoke = 'var point = new GLatLng('.$supplier->latitude.', '.$supplier->longitude.');';
			
			$jsGenerate = $elementId.'.setCenter(point, '.$centerPoint.');'."\n";
			$jsGenerate.= 'var marker = new GMarker(point);'."\n";
			$jsGenerate.= $elementId.'.addOverlay(marker);'."\n";
			$jsGenerate.= $postOverlay;
		}
		else
		{
			$address = '';
			
			if ($supplier->address1)
			{
				$address.= $supplier->address1 . ',';
			}
			
			if ($supplier->address2)
			{
				$address.= $supplier->address2 . ',';
			}
			
			$address.= $supplier->city . ',';;
			if ($supplier->state && $this->view->string()->alphaStringLength($supplier->state) > 1)
			{
				$address.= $supplier->state . ',';
			}
			
			if ($supplier->zipCode)
			{
				$address.= $supplier->zipCode . ',';
			}
			
			$address.= $supplier->countryName;
			
			$jsInvoke = 'var geocoder = new GClientGeocoder();';
			
			$jsGenerate = 'geocoder.getLatLng('."\n";
			$jsGenerate.= '"'.$address.'",'."\n";
			$jsGenerate.= 'function(point) {'."\n";
			$jsGenerate.= '	if (!point) {'."\n";
			$jsGenerate.= '		$("#'.$elementId.'").html(\''.$noMapHTML.'\')'."\n";
			$jsGenerate.= '	} else {'."\n";
			$jsGenerate.= '		'.$elementId.'.setCenter(point, '.$centerPoint.');'."\n";
			$jsGenerate.= '		var marker = new GMarker(point);'."\n";
			$jsGenerate.= '		'.$elementId.'.addOverlay(marker);'."\n";
			$jsGenerate.= $postOverlay;
			
			$jsGenerate.= '	}'."\n";
			$jsGenerate.= '}'."\n";
			$jsGenerate.= ');'."\n";
		}
		
		$output = '<script type="text/javascript">'."\n";
		$output.= "<!--\n";
		
		$output.= 'var '.$elementId.' = new GMap2(document.getElementById("'.$elementId.'")';
		
		if (is_array($options['size']))
		{
			$output.= ',{size:new GSize('.$options['size']['x'].', '.$options['size']['y'].')}';
		}
		
		$output.= ');'."\n";
		
		$output.= $jsInvoke;
		$output.= $jsGenerate;
		$output.= $elementId.'.getContainer().style.overflow = "hidden";'."\n";
		$output.= '//-->'."\n";
		$output.= '</script>'."\n";
		
		return $output;
	}
	
}