<?php

/**
 * Controller for all autocompletes
 *
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Search_AutocompleteController extends Myshipserv_Controller_Action
{
    /**
     * Initialise the controller - set up the context helpers for AJAX calls
     * 
     * @access public
     */
    public function init()
    {
        parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('portsAndCountries', array('html', 'json'))
                    ->addActionContext('what', array('html', 'json'))
                    ->initContext();
    }
    
    /**
     * Action for ports and countries autocomplete
     *
     * Will take $_POST['value'] and search the ports and countries tables in
     * Oracle and mash the results together, and then return as JSON or a PHP
     * array to the view
     *
     * @access public
     */
    public function portsandcountriesAction ()
    {
        if ($this->getRequest()->isPost())
        {
            $params = $this->params; // $this->getRequest()->getParams();
            
            // fetch the DB resource
            $db = $this->db;
            
            // fetch the ports for the autocomplete and place in an array
            $portAdapter = new Shipserv_Oracle_Ports($db);
            
            $ports = $portAdapter->fetchNonRestrictedPortsByName($params['value'], false);
            
            $encodedPorts = array();
            foreach ((array)$ports as $port)
            {
                $portName = ($port['PRT_SYN_SYNONYM']) ? $port['PRT_SYN_SYNONYM'] : $port['PRT_NAME'];
                
                $encodedPorts[$port['PRT_PORT_CODE']] = $port['PRT_NAME'].', '.
                                                        $port['CNT_NAME'].'';
            }
            
            // fetch the countries for this autocomplete and place in an array
            $countryAdapter = new Shipserv_Oracle_Countries($db);
            
            $countries = $countryAdapter->fetchNonRestrictedCountriesByName($params['value']);
            
            $encodedCountries = array();
            foreach ((array)$countries as $country)
            {
                $name = ($country['CNT_SYN_SYNONYM']) ? ($country['CNT_SYN_SYNONYM']) : $country['CNT_NAME'];
                
                $encodedCountries[$country['CNT_COUNTRY_CODE']] = $name;
            }
            
            // Merge the two arrays and sort them (countries will appear first)
            $portsAndCountries = array_merge( (array) $encodedPorts, (array) $encodedCountries);
            asort($portsAndCountries);
            
            // Fix the array into a format
            $encodedPortsAndCountries = array();
            foreach ($portsAndCountries as $value => $display)
            {
                $encodedPortsAndCountries[] = array('value'   => $display,
                                                    'display' => $display,
                                                    'code'    => $value);
            }
            
            if( $this->params['new'] == 1)
            {
                foreach( $encodedPortsAndCountries as $r )
                {
                    $new[] = array( 'value' => $r['value'], 'data' => $r['code']);
                }

                $encodedPortsAndCountries = array('query' =>  $this->params['value'], 'suggestions' => $new);
                
                if($encodedPortsAndCountries['suggestions'] == null ) {
                    $encodedPortsAndCountries['suggestions'] = array();
                }
            }

            // Send to the view as JSON or a PHP array
            if ($params['format'] == 'json')
            {
                $this->_helper->json((array)$encodedPortsAndCountries);
            }
            else
            {
                $this->view->result = $encodedPortsAndCountries;
            }
        }   
    }
    
    
    /**
     * Action for autocomplete for searching brands, categories, and supplier names
     *
     * Will take $_POST['value'] and search the brands, categories and supplier
     * tables in Oracle and mash the results together, and then return as JSON
     * or a PHP array to the view
     *
     * @access public
     */
    public function whatAction ()
    {
        $display = array();
        if ($this->getRequest()->isPost()) {
            $params = $this->params;
            $searchTerm = $this->sanitise($params['value']);
            
            // fetch the DB resource
            $db = $this->db;

            // fetch supplier
            $supplierAdapter = new Shipserv_Oracle_Suppliers($db);
            $encodedSuppliers = array();

            $timeStart = microtime(true);
            $matchingSuppliers = $supplierAdapter->getAutoCompleteForSupplier($searchTerm);
            // print(microtime(true) - $timeStart); die;

            foreach($matchingSuppliers as $row )
            {
              	$encodedSuppliers[$row['VALUE'] . '_supplier'] = array(
	    	        'value'   => $row['VALUE'],
	                'display' => $row['VALUE'] . " (TNID: " . $row['TNID'] . ")",
	                'code'    => $row['VALUE'],
	                'type'    => 'supplier',
	                'url' 	  => '/supplier/tnid/id/' . $row['TNID']
                );
            }
            
            // fetch brands
            $catalogueAdapter = new Shipserv_Oracle_ImpaCatalogue($db);
            $impaCodes = $catalogueAdapter->search($searchTerm);

            $encodedCodes = array();
            if (is_array($impaCodes)) {
                foreach ((array) $impaCodes as $code)
                {
                    $display = ucwords(strtolower($code['PCI_DESCRIPTION'])).' IMPA '. $this->formatCode($code['PCI_PART_NO']);
                    $words   = preg_split('/[ -]/', trim($searchTerm));
                    $display = @preg_replace('/(' . (count($words)>0?implode("|",$words):$searchTerm) . ')/i', '<em>$1</em>', $display);

                    $encodedCodes[$code['PCI_PART_NO'] . '_codes'] = array('value'   => ucwords(strtolower($code['PCI_DESCRIPTION'])).' '. $code['PCI_PART_NO'],
                                                           'display' => $display,
                                                           'code'    => ucwords(strtolower($code['PCI_DESCRIPTION'])).' ' . $code['PCI_PART_NO'],
                                                           'type'    => 'product'
                    );
                }
            }


            // fetch brands
            $brandsAdapter = new Shipserv_Oracle_Brands($db);
            $brands = $brandsAdapter->search($searchTerm);
            
            $encodedBrands = array();
            if (is_array($brands)) {
                foreach ($brands as $brand) {
                    $display = $brand['NAME'];
                    $words   = explode(' ', trim($searchTerm));
                    
                    $display = preg_replace('/('.implode("|",$words).')/i', '<em>$1</em>', $display);
                    
                    $encodedBrands[$brand['NAME'] . '_brand'] = array('value'   => $brand['NAME'],
                                                           'display' => $display,
                                                           'code'    => $brand['NAME'],
                                                           'type'    => 'product'
                                                            );
                }
            }
            
            // fetch the categories
            $categoriesAdapter = new Shipserv_Oracle_Categories($db);
            $categories = $categoriesAdapter->search($searchTerm);
            
            $encodedCategories = array();
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    $display = $category['NAME'];
                    $words   = explode(' ', trim($searchTerm));
                    
                    $display = preg_replace('/('.implode("|",$words).')/i', '<em>$1</em>', $display);

                    $encodedCategories[$category['NAME'] . '_categories'] = array('value'   => $category['NAME'],
                                                                  'display' => $display,
                                                                  'code'    => $category['NAME'],
                                                                  'type'    => 'product');
                }
            }
            
            //$encodedSuppliers = array();
            
            //$allWhat = array_merge($encodedSuppliers, $encodedCategories, $encodedBrands);
            $allWhat = array_merge( (array) $encodedCategories, (array) $encodedBrands, (array) $encodedSuppliers, (array) $encodedCodes);
            
            // Obtain a list of columns
            $display = is_array($display)? $display : array(); //if preg_replace did not math, $display would not be an array and an error would be raised
            foreach ((array)$allWhat as $key => $row) {
            	$display[$key] =  strtolower(strip_tags($row['display']));
            }
            // Sort the data with display name ascending
            // Add $data as the last parameter, to sort by the common key
            //array_multisort($display, SORT_ASC, $allWhat);
                        
            // now some hackery - remove the string keys and replace them with numeric keys
            $encodedResults = array();
            foreach ((array)$allWhat as $data)
            {
                $encodedResults[] = $data;
            }
            
            if( $this->params['new'] == 1)
            {
                foreach( $encodedResults as $r )
                {
                    $new[] = array( 
                    	'value' => strip_tags($r['display']), 
                    	'data' => array(
                    			'type' => $r['type'], 
                    			'value' => $r['value'],
                    			'url' => $r['url']
                    	)
                    );
                }

                $encodedResults = array('query' =>  $this->params['value'], 'suggestions' => $new);
                if($encodedResults['suggestions'] == null ) {
                    $encodedResults['suggestions'] = array();
                }
            }

            // Send to the view as JSON or a PHP array
            if ($params['format'] == 'json')
            {
                @$this->_helper->json((array)$encodedResults);
            }
            else
            {
                $this->view->result = $encodedResults;
            }
        }   
    }

    /**
     * Action for autocomplete for searching brands
     *
     * Will take $_POST['value'] and search the brands in Oracle
     * and then return as JSON or a PHP array to the view
     *
     * @access public
     */
    public function brandsAction ()
    {
        if ($this->getRequest()->isPost())
        {
            $params = $this->params; //$this->getRequest()->getParams();
            $searchTerm = $this->sanitise($params['value']);
            
            // fetch the DB resource
            $db = $this->db;

            // fetch brands
            $brandsAdapter = new Shipserv_Oracle_Brands($db);
            $brands = $brandsAdapter->search($searchTerm);

            $encodedBrands = array();
            if (is_array($brands))
            {
                foreach ($brands as $brand)
                {
                    $display = $brand['NAME'];
                    $words   = explode(' ', trim($searchTerm));

                    $display = preg_replace('/('.implode("|",$words).')/i', '<em>$1</em>', $display);

                    $encodedBrands[$brand['NAME']] = array('value'   => $brand['NAME'],
                                                           'display' => $display,
                                                           'code'    => $brand['NAME'],
                                                           'type'    => 'product',
                                                            'id'     => $brand['ID']);
                }
            }


            //$allWhat = array_merge($encodedSuppliers, $encodedCategories, $encodedBrands);
            $allWhat = $encodedBrands;

            // Obtain a list of columns
            foreach ((array)$allWhat as $key => $row)
            {
                $display[$key]  = $row['display'];
            }

            // Sort the data with display name ascending
            // Add $data as the last parameter, to sort by the common key
            // array_multisort($display, SORT_ASC, $allWhat);

            // now some hackery - remove the string keys and replace them with numeric keys
            $encodedResults = array();
            foreach ($allWhat as $data)
            {
                $encodedResults[] = $data;
            }

            // Send to the view as JSON or a PHP array
            if ($params['format'] == 'json')
            {
                $this->_helper->json((array)$encodedResults);
            }
            else
            {
                $this->view->result = $encodedResults;
            }
        }
    }

    
    /**
     * Action for autocomplete for searching categories
     *
     * Will take $_POST['value'] and search the categories
     * tables in Oracle and then return as JSON
     * or a PHP array to the view
     *
     * @access public
     */
    public function categoriesAction ()
    {
        if ($this->getRequest()->isPost())
        {
            $params = $this->params; //$this->getRequest()->getParams();
            $searchTerm = $this->sanitise($params['value']);
            
            // fetch the DB resource
            $db = $this->db;

            // fetch the categories
            $categoriesAdapter = new Shipserv_Oracle_Categories($db);
            $categories = $categoriesAdapter->search($searchTerm);

            $encodedCategories = array();
            if (is_array($categories))
            {
                foreach ($categories as $category)
                {
                    $display = $category['NAME'];
                    $words   = explode(' ', trim($searchTerm));
                    $display = preg_replace('/('.implode("|",$words).')/i', '<em>$1</em>', $display);
                    
                    $encodedCategories[$category['NAME']] = array('value'   => $category['NAME'],
                                                                  'display' => $display,
                                                                  'code'    => $category['NAME'],
                                                                  'type'    => 'product',
                                                                  'id'      =>  $category['ID']
                                                                );
                }
            }

           
            $allWhat = $encodedCategories;

            // Obtain a list of columns
            foreach ($allWhat as $key => $row)
            {
                $display[$key]  = $row['display'];
            }

            // Sort the data with display name ascending
            // Add $data as the last parameter, to sort by the common key
            // array_multisort($display, SORT_ASC, $allWhat);

            // now some hackery - remove the string keys and replace them with numeric keys
            $encodedResults = array();
            foreach ((array)$allWhat as $data)
            {
                $encodedResults[] = $data;
            }

            // Send to the view as JSON or a PHP array
            if ($params['format'] == 'json')
            {
                $this->_helper->json((array)$encodedResults);
            }
            else
            {
                $this->view->result = $encodedResults;
            }
        }


    }

    /**
     * Action for autocomplete for searching categories
     *
     * Will take $_POST['value'] and search the categories
     * tables in Oracle and then return as JSON
     * or a PHP array to the view
     *
     * @access public
     */
    public function searchrefinementAction ()
    {
        if ($this->getRequest()->isPost())
        {
            $params = $this->params;
            $db = $this->db;
            $config = Zend_Registry::get('options');

            $searchBoxOptionsAdapter = $this->_helper->getHelper('SearchBoxOptions');

            if ($params['format'] == 'json')
            {
                $result = $searchBoxOptionsAdapter->getArrayForAutoComplete($params['autoCompleteFilter'],$params['value'],$params['optionsCacheId']);
                $this->_helper->json((array)$result);
            }

        }


    }
    
    private function formatCode ($code)
    {
        return $code[0].$code[1].'-'.$code[2].$code[3].'-'.$code[4].$code[5];
    }
    
    private function sanitise( $string )
    {
        // replacing double quotes
        $string = str_replace('"', "", $string );
        // replacing unclosed bracket
        if( strstr($string, "(") !== false )
        {
            if( strstr($string, ")") !== false )
            {
                $string = str_replace("(", "\(", $string);
                $string = str_replace(")", "\)", $string);
            }
            else 
            {
                $string = str_replace("(", "", $string);
            }
        }
        return $string;
    }
    
	public function supplierAction()
    {
    	$s = new Shipserv_Adapters_SolrSearch;
    	$results = $s->search('SPB', $this->params['value'], 1);
    	$this->_helper->json((array)$results);
    }
    
    public function buyerAction()
    {
    	$s = new Shipserv_Adapters_SolrSearch;
    	$results = $s->search('BYO', $this->params['value'], 1);
    	$this->_helper->json((array)$results);
    }
    
}
