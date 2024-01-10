<?php

/**
 * Class for handling zones
 *
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Zone_Old extends Shipserv_Object{

	public static $urlToZone = array(
		'bnwas' 	 => '/search/results/index/zone/bnwas?newSearch=1&searchType=product&ssrc=32&searchWhat=&searchText=&searchWhere=',
		'copenhagen' => '/port/copenhagen/DK-CPH/zone',
		//'gmdss' 	 => '/category/gmdss-radio-survey-services/170/zone',
		//'hamworthy'  => '/brand/hamworthy-pumps/297/zone',
		'klinger' 	 => '/brand/klinger/2372/zone',
		'mandiesel'  => '/brand/man-b-w-diesel-and-man-diesel-marine-engines-and-parts/479/zone',
		'rotterdam'  => '/port/rotterdam/NL-RTM/zone',
		'singapore'  => '/country/singapore/SG/zone',
		'valves' 	 => '/category/valves/82/zone',
        'oil-gas'     => '/search/results/index/zone/oil-gas?newSearch=1&searchType=product&ssrc=32&searchWhat=&searchText=&searchWhere=',
		'wencon'  => '/brand/wencon/1743/zone',
		'yanmar'  => '/brand/yanmar/991/zone',
		'chandlers'  => '/category/ship-chandlery/9/zone',
		//'frank-mohn' => '/brand/frank-mohn/246/zone',
		'hamburg' 	 => '/port/hamburg/DE-HAM/zone',
		'hms'=>	'/search/results/index/searchWhat/hms/zone/hms?ssrc=1429',
		'sinwa' 	 => '/search/results/index/zone/sinwa',
		'jpsauer' 	 => '/search/results/index/searchWhat/sauer/zone/jpsauer?ssrc=1429',//'/brand/sauer/2163/zone',
		'pumps' 	 => '/category/pumps/46/zone',
		'shanghai'=> '/port/shanghai/CN-SHA/zone',
		//'sperre' 	 => '/brand/sperre/808/zone',
		'timavo' 		 => '/search/results/index/zone/timavo',
        'rs'   => '/search/results/index/zone/rs',
        'tts'        => '/brand/tts/1925/zone',
        'iss'         => '/search/results/index/zone/iss',
        'manitowoc'         => '/search/results/index/zone/manitowoc',
        'alfalaval'         => '/search/results/index/zone/alfalaval',
        'wrist'         => '/search/results/index/zone/wrist',
        'mak'         => '/search/results/index/zone/mak',
        'ems'         => '/search/results/index/zone/ems',
		'issa' 		  => '/search/results/index/searchWhat/issa/zone/issa?ssrc=1429',
        'rms'         => '/search/results/index/zone/rms'/*,
        'gns'         => '/search/results/index/zone/gns'*/
		// 'wartsila' => '/brand/wartsila-marine-engines/948/zone'
	);

	static public $zoneData = array(
		/*
		  	'gmdss'  => array('name' => 'GMDSS',
								 'contentXml' => 'gmdss.xml'),
			'lifeboats' => array('name' => 'Lifeboats',
								 'contentXml' => 'lifeboats.xml'),
			'wartsila' 	=> array('name' 	  => 'Wartsila',
								 'contentXml' => 'wartsila.xml'),
			'hamworthy' => array('name'		  => 'Hamworthy',
			 					 'contentXml' => 'hamworthy.xml'),
			'sperre'	=> array('name'		  => 'Sperre',
								 'contentXml' => 'sperre.xml'),
//			'frankmohn'	=> array('name' 	  => 'Frank Mohn',
//								 'contentXml' => 'frank-mohn.xml'),
		*/
			'chandlers' => array('name' => 'Chandlers',
								 'contentXml' => 'chandlers.xml'),
			'issa' 		=> array('name' => 'ISSA',
								 'contentXml' => 'issa.xml'),
			'hamburg' 	=> array('name' => 'Hamburg',
								 'contentXml' => 'hamburg.xml'),
			'rotterdam' => array('name' => 'Rotterdam',
								 'contentXml' => 'rotterdam.xml'),
			'shanghai' 	=> array('name' => 'Shanghai',
								 'contentXml' => 'shanghai.xml'),
			'singapore' => array('name' => 'Singapore',
								 'contentXml' => 'singapore.xml'),
			'copenhagen'=> array('name' => 'Copenhagen',
								 'contentXml' => 'copenhagen.xml'),
			'wencon' 	=> array('name' => 'Wencon',
								 'contentXml' => 'wencon.xml'),
			'yanmar' 	=> array('name' => 'Yanmar',
								 'contentXml' => 'yanmar.xml'),
			'tts' 		=> array('name' => 'TTS',
								 'contentXml' => 'tts.xml'),
			'bnwas' 	=> array('name' => 'Bnwas',
								 'contentXml' => 'bnwas.xml'),
			'pumps' 	=> array('name' => 'Pumps',
								 'contentXml' => 'pumps.xml'),
			'valves' 	=> array('name' => 'Valves',
								 'contentXml' => 'valves.xml'),
            'oil-gas'    => array('name' => 'Oil and Gas',
                                 'contentXml' => 'oil-gas.xml'),
			'hms' 		=> array('name' => 'HMS',
								 'contentXml' => 'hms.xml'),
			'sinwa' 	=> array('name' => 'Sinwa Zone',
								 'contentXml' => 'sinwa.xml'),
			'life-saving-equipment' => array('name' => 'Life Saving Equipment',
											 'contentXml' => 'life-saving-equipment.xml'),
            'klinger' 	=> array('name' => 'Klinger',
            			    	 'contentXml' => 'klinger.xml'),
			'mandiesel' => array('name' => 'Man Diesel',
								 'contentXml' => 'mandiesel.xml'),
	        'ppg' 		=> array('name' => 'PPG',
	                			 'contentXml' => 'ppg.xml'),
			'jpsauer' 	=> array('name' => 'JP Sauer',
	                			 'contentXml' => 'jpsauer.xml'),
            'timavo' => array('name' => 'Timavo Zone',
                                 'contentXml' => 'timavo.xml'),
            'rs' => array('name' => 'RS Components Zone',
                                 'contentXml' => 'rs.xml'),
            'iss' => array('name' => 'ISS Machinery Services Zone',
                                 'contentXml' => 'iss.xml'),
            'manitowoc' => array('name' => 'Manitowoc Zone',
                                 'contentXml' => 'manitowoc.xml'),
            'alfalaval' => array('name' => 'Alfa Laval Aalborg Zone',
                                 'contentXml' => 'alfalaval.xml'),
            'wrist' => array('name' => 'Wrist Ship Supply',
                                 'contentXml' => 'wrist.xml'),
            'mak' => array('name' => 'MaK Marine Engines',
                                 'contentXml' => 'mak.xml'),
            'ems' => array('name' => 'EMS Seven Seas Spain',
                                 'contentXml' => 'ems.xml'),
            'rms' => array('name' => 'RMS Marine Service Company Ltd',
                                 'contentXml' => 'rms.xml')/*,
            'gns' => array('name' => 'Global Navigation Solutions Group',
                                 'contentXml' => 'gns.xml')*/
	);

    /**
     *  This is the array that decides what zones to display on the front page. Random ones are chosed from this array.
     *
     * @var array
     */
    static public $enabledZones = array(
//      'wartsila' => array("image" => "wartsila_smallbox_zone.jpg",
//      	"title" => "All authorised Wartsila Suppliers"),
//		'sperre'=>array("image"=>"sperre_smallbox_zone.jpg",
//			"title"=>"All authorised Sperre Suppliers"),

    	'hamburg' => array(
            "image" => "hamburg-zone.gif",
            "title" => "Search marine suppliers in the Hamburg"),
        'rotterdam' => array("image" => "rotterdam-zone.gif",
            "title" => "Search marine suppliers in the Rotterdam"),
       	'shanghai' => array("image" => "shanghai-zone.gif",
            "title" => "Search marine suppliers in the Shanghai"),
        'copenhagen' => array("image" => "copenhagen-zone.gif",
            "title" => "Search marine suppliers in the Copenhagen"),
        'singapore' => array("image" => "singapore-zone.gif",
            "title" => "Search marine suppliers in the Singapore"),
        'chandlers' => array("image" => "chandlers-zone.gif",
            "title" => "Chandlers Zone"),
        'issa' => array("image" => "issa-zone.gif",
            "title" => "ISSA Zone"),
		'tts' => array("image" => "tts_zone_invite.jpg",
            "title" => "All authorised TTS Suppliers"),
		'yanmar' => array("image" => "wencon_smallbox_zone.jpg",
            "title" => "All authorised Yanmar Suppliers"),
        'wencon' => array("image" => "wencon_smallbox_zone.jpg",
            "title" => "All authorised Wencon Suppliers"),
        'sinwa' => array("image" => "hms_smallbox_zone.jpg",
            "title" => "Sinwa Zone"),
        'hms' => array("image" => "hms_smallbox_zone.jpg",
            "title" => "All HMS group members"),
        'jpsauer' => array("image" => "jpsauer_smallbox_zone.jpg",
            "title" => "All authorised JP Sauer Suppliers"),
        'bnwas' => array("image" => "bnwas_smallbox_zone.jpg",
            "title" => "Search marine suppliers of BNWAS"),
        'pumps' => array("image" => "pumps_smallbox_zone.jpg",
            "title" => "Search marine suppliers of Pumps"),
        'valves' => array("image" => "valves_smallbox_zone.jpg",
            "title" => "Search marine suppliers of Valves"),
        'oil-gas' => array("image" => "oil-gas_smallbox_zone.jpg",
            "title" => "Search marine suppliers of Valves"),
        'mandiesel' => array("image" => "mandiesel_smallbox_zone.jpg",
            "title" => "All authorised Man Diesel Suppliers"),
        'life-saving-equipment' => array("image" => "life-saving-equipment-zone.jpg",
            "title" => "Search marine suppliers of Life Saving Equipment"),
    	'ppg' => array("image" => "ppg_smallbox_zone.jpg",
            "title" => "PPG Coatings"),
        'timavo' => array("image" => "timavo_home_banner.jpg",
            "title" => "Timavo Ship Supply & TSS d.o.o Zone"),
        'rs' => array("image" => "rs_home_banner.jpg",
            "title" => "RS Components Zone"),
        'iss' => array("image" => "iss_home_banner.jpg",
            "title" => "ISS Machinery Services Zone"),
        'manitowoc' => array("image" => "manitowoc_home_banner.jpg",
            "title" => "Manitowoc Zone"),
        'alfalaval' => array("image" => "manitowoc_home_banner.jpg",
            "title" => "Alfa Laval Zone"),
        'wrist' => array("image" => "manitowoc_home_banner.jpg",
            "title" => "Wrist Ship Supply Zone"),
        'mak' => array("image" => "mak_home_banner.jpg",
            "title" => "MaK Marine Engines Zone"),
        'ems' => array("image" => "ems_home_banner.jpg",
            "title" => "EMS Seven Seas Spain Zone"),
        'rms' => array("image" => "rms_home_banner.jpg",
            "title" => "RMS Marine Service Company Ltd")/*,
        'gns' => array("image" => "manitowoc_home_banner.jpg",
            "title" => "Global Navigation Solutions Group")*/
    );

    /**
     *  Simple map that points specific categories to their zones. Note that we are mapping the categories referenced by ResultsController,
     *  by inferring from $values['searchText']) ? $values['searchText'] : $values['searchWhat']
     *  The keys for this are easily found by visiting a zone and the text places into the Search for box is used.
     *  These are primarily used in SEO friendly URLS for mapping from Category to zone.
     * @var array string
     *
     */
    static public $mapArray = array(
        /* categories */
        'chandlery' => 'chandlers',
        'life saving equipment' => 'life-saving-equipment',
        'valves & actuators' => 'valves',
        'valves' => 'valves',
        'oil and gas' => 'oil-gas',
        'oil' => 'oil-gas',
        'gas' => 'oil-gas',
        'offshore & drilling equipment' => 'oil-gas',
        'pumps' => 'pumps',
        'bridge navigational watch alarm system (bnwas)' => 'bnwas',
        'bnwas & bridge navigational watch alarm system' => 'bnwas',

        /* brands */
        'issa' => 'issa',

		// 'wartsila' => 'wartsila',
        'wencon' => 'wencon',
        'hanseatic marine services - hms' => 'hms',
        'klinger' => 'klinger',
        'sauer' => 'jpsauer',
        //'gmdss-radio-survey-services' => 'gmdss',
        //'hamworthy-pumps' => 'hamworthy',
        'man-b-w-diesel-and-man-diesel-marine-engines-and-parts' => 'mandiesel',
        'man diesel' => 'mandiesel',
		'tts' => 'tts',
		'ppg' => 'ppg',
		'yanmar' => 'yanmar',
		'sinwa' => 'sinwa',
        'timavo' => 'timavo',
        'iss' => 'iss',
        'manitowoc' => 'manitowoc',
        'convotherm' => 'manitowoc',
        'rs' => 'rs',
        'alfa-laval-aalborg' => 'alfalaval',
		'rms' => 'rms',
		'mak' => 'mak',
		'ems' => 'ems',

    	/* ports */
        'rotterdam' => 'rotterdam',
        'hamburg' => 'hamburg',
        'copenhagen' => 'copenhagen',
        'shanghai' => 'shanghai',
        /* country */
        'singapore' => 'singapore'
    );

    static public $zoneSynonyms = array(
    	// life saving
    	'lifeboats' => 'life-saving-equipment',
    	'lifeboat' => 'life-saving-equipment',
    	'liferafts' => 'life-saving-equipment',
    	'liferaft' => 'life-saving-equipment',
    	'life saving' => 'life-saving-equipment',
    	'rescue' => 'life-saving-equipment',
    	'emergency' => 'life-saving-equipment',
    	'survival' => 'life-saving-equipment',

    	// valve
    	'valves' => 'valves',
    	'valve' => 'valves',

        //oil and gas
        'offshore' => 'oil-gas',
        'drilling' => 'oil-gas',
        'offshore & drilling' => 'oil-gas',
        'offshore and drilling' => 'oil-gas',

    	// pumps
    	'pumps' => 'pumps',
    	'pump' => 'pumps',

    	// jpsauer
    	'jpsauer' => 'jpsauer',
    	'jp sauer' => 'jpsauer',
    	'sauer' => 'jpsauer',

    	// tts
    	't t s' => 'tts',
    	'tts' => 'tts',
    	'hydralift' => 'tts',
    	'norlift' => 'tts',
    	'cranes' => 'tts',
    	'crane' => 'tts',
    	'davits' => 'tts',
    	'davit' => 'tts',
    	'winches' => 'tts',
    	'winch' => 'tts',
    	'bollards' => 'tts',
    	'bollard' => 'tts',

    	// yanmar
    	'yanmar' => 'yanmar',
    	'complete auxiliary engine systems' => 'yanmar',
    	'complete main engine systems' => 'yanmar',
    	'yanmar europe' => 'yanmar',
    	'yanmar Europe BV' => 'yanmar',
    	'yanmar Europe B.V.' => 'yanmar',

    	// wencon
    	'wencon' => 'wencon',
    	'corrosion control' => 'wencon',
    	// bnwas
    	'bnwas' => 'bnwas',
    	'alarms' => 'bnwas',
    	'alarm' => 'bnwas',
    	'bridges' => 'bnwas',
    	'bridge' => 'bnwas',

    // 'wrtsil' => 'wartsila',
    // 'wartsila' => 'wartsila',
    // 'sulzer' => 'wartsila',
    // 'nohab' => 'wartsila',
    // 'japan marine technology' => 'wartsila',
    // 'poyaud' => 'wartsila',
    // 'gmt' => 'wartsila',
    // 'bolnes' => 'wartsila',
    // 'deutz' => 'wartsila',
    // 'moteurs' => 'wartsila',
    // 'stork' => 'wartsila',
    // 'lips' => 'wartsila',
    // 'sacm' => 'wartsila',
    // 'nordberg' => 'wartsila',
    // 'deep sea seals' => 'wartsila',
    // 'whessoe' => 'wartsila',
    // 'wichmann' => 'wartsila',
    // 'gmdss'		=> 'gmdss',

    	'issa' => 'issa',
    	'hamburg' => 'hamburg',
    	'germany' => 'hamburg',
    	'rotterdam' => 'rotterdam',
    	'netherlands' => 'rotterdam',
    	'shanghai' => 'shanghai',
    	'china' => 'shanghai',
    	'singapore' => 'singapore',
    	'copenhagen' => 'copenhagen',
    	'denmark' => 'copenhagen',
    	'chandlers' => 'chandlers',
    	'chandler' => 'chandlers',
    	'shipchandlers' => 'chandlers',
    	'shipchandler' => 'chandlers',
    	'stores' => 'chandlers',
    	'store' => 'chandlers',
    	'ship_chanders' => 'chandlers',
    	'ship_chander' => 'chandlers',
    	'chandliers' => 'chandlers',
    	'chandlier' => 'chandlers',
    	'shipchandliers' => 'chandlers',
    	'shipchandlier' => 'chandlers',
    	'chandlary' => 'chandlers',
    	'chandlery' => 'chandlers',
    	'chand' => 'chandlers',
    	'chandlering' => 'chandlers',
    	'shipchandlering' => 'chandlers',
    	'chandlrey' => 'chandlers',
    	'chandlrers' => 'chandlers',
    	'chandlrer' => 'chandlers',
    	'shipchandlrey' => 'chandlers',
    	'shipchandlrers' => 'chandlers',
    	'shipchandlrer' => 'chandlers',
    	'chandles' => 'chandlers',
    	'chandle' => 'chandlers',
    	'shipchandles' => 'chandlers',
    	'shipchandle' => 'chandlers',
    	/*
    	'frankmohn'	=> 'frankmohn',
    	'frank mohn' => 'frankmohn',
    	*/
    	'mandiesel' => 'mandiesel',
    	'man diesel' => 'mandiesel',
    	'mandiesel'  => 'mandiesel',
    	'alpha diesel' => 'mandiesel',
    	'alpha lubricator' => 'mandiesel',
    	'b & w' => 'mandiesel',
    	'b and w' => 'mandiesel',
    	'burmeister & wain' => 'mandiesel',
    	'burmeister and wain' => 'mandiesel',
    	'cocos' => 'mandiesel',
    	'diesel house' => 'mandiesel',
    	'duraspindle' => 'mandiesel',
    	'holeby' => 'mandiesel',
    	'holeby diesel' => 'mandiesel',
    	'man b&w' => 'mandiesel',
    	'man turbo' => 'mandiesel',
    	'man turbocharger' => 'mandiesel',
    	'man' => 'mandiesel',
    	'mdt' => 'mandiesel',
    	'mirrlees blackstone' => 'mandiesel',
    	'paxman' => 'mandiesel',
    	'pielstick' => 'mandiesel',
    	'primeserv' => 'mandiesel',
    	'ruston' => 'mandiesel',
    	'semt' => 'mandiesel',

    	// HMS
    	'hms' => 'hms',
    	'hanseatic marine' => 'hms',
    	'schaar & niemeyer' => 'hms',
    	'schaar and niemeyer' => 'hms',
    	'schaar niemeyer' => 'hms',
    	'zerssen & citti' => 'hms',
    	'zerssen and citti' => 'hms',
    	'zerssen citti' => 'hms',
    	'wilh richers' => 'hms',
    	'wilh richer' => 'hms',
    	'pickenpack' => 'hms',
    	'louis taxt' => 'hms',
    	'hans heidorn' => 'hms',
    	'ktl ship supply' => 'hms',
    	'plurotech' => 'hms',

    	// Sinwa
    	'chandler' => 'sinwa',
    	'chandlery' => 'sinwa',
    	'ship chandler' => 'sinwa',
    	'ship chandlery' => 'sinwa',
    	'sinwa' => 'sinwa',

    	// PPG
    	'ppg' => 'ppg',
    	'ppg coatings' => 'ppg',
    	'sigma coatings' => 'ppg',
    	'amercoat' => 'ppg',
    	'paints' => 'ppg',
    	'coatings' => 'ppg',

        'timavo ship supply & tss d.o.o' => 'timavo',
        'timavo ship supply' => 'timavo',
        'tss d.o.o' => 'timavo',
        'timavo' => 'timavo',
        'tss' => 'timavo',

        'rs' => 'rs',
        'rs components' => 'rs',
        '19-inch racking accessories' => 'rs',
        '2 mm connector test leads' => 'rs',
        '2 mm test plugs & sockets' => 'rs',
        '4 mm connector test leads' => 'rs',
        '4 mm test plugs & sockets' => 'rs',
        '4 mm test probe leads' => 'rs',
        'a/v mixed cable assemblies' => 'rs',
        'ac geared motors' => 'rs',
        'ac motors' => 'rs',
        'access control accessories' => 'rs',
        'access control door magnets' => 'rs',
        'access control door strikes' => 'rs',
        'access control entry/exit buttons' => 'rs',
        'access control keypads' => 'rs',
        'actuator/sensor cable' => 'rs',
        'adaptable boxes' => 'rs',
        'adjustable spanners' => 'rs',
        'air drills' => 'rs',
        'air filter frames' => 'rs',
        'air ratchet wrenches' => 'rs',
        'analogue multimeters' => 'rs',
        'analogue panel ammeters' => 'rs',
        'analogue panel voltmeters' => 'rs',
        'analogue positive pressure gauges' => 'rs',
        'anemometers' => 'rs',
        'angle grinders' => 'rs',
        'anti corrosion vapour capsules' => 'rs',
        'anti-fatigue mats' => 'rs',
        'anti-slip flooring & mats' => 'rs',
        'aprons & tabards' => 'rs',
        'arbor sets' => 'rs',
        'arbors' => 'rs',
        'armoured cable' => 'rs',
        'attachable face shields' => 'rs',
        'audio & video connector adapters' => 'rs',
        'audio transformers' => 'rs',
        'automation & control gear' => 'rs',
        'automotive connector accessories' => 'rs',
        'automotive connectors' => 'rs',
        'automotive halogen lamps' => 'rs',
        'automotive incandescent lamps' => 'rs',
        'automotive led lamps' => 'rs',
        'automotive light fittings' => 'rs',
        'automotive wire' => 'rs',
        'autotransformers' => 'rs',
        'auxiliary contacts' => 'rs',
        'axial fans' => 'rs',
        'back support belts' => 'rs',
        'backdraught shutters' => 'rs',
        'ball catches' => 'rs',
        'ball transfer units' => 'rs',
        'ball-pein hammers' => 'rs',
        'barometers & weather stations' => 'rs',
        'bases & arms' => 'rs',
        'batteries' => 'rs',
        'beacon - sounder combinations' => 'rs',
        'beacons' => 'rs',
        'beakers' => 'rs',
        'bearing puller sets' => 'rs',
        'bells' => 'rs',
        'bench & hand vices' => 'rs',
        'bench grinders' => 'rs',
        'bench magnifier accessories' => 'rs',
        'bench magnifiers' => 'rs',
        'bench power supplies' => 'rs',
        'bin dividers' => 'rs',
        'bin storage units & holders' => 'rs',
        'binding posts' => 'rs',
        'bins' => 'rs',
        'blow guns' => 'rs',
        'blowers' => 'rs',
        'bnc connectors' => 'rs',
        'bnc test leads' => 'rs',
        'bolt cutters' => 'rs',
        'boring bars' => 'rs',
        'braided wire' => 'rs',
        'bump caps & safety caps' => 'rs',
        'bungee cords' => 'rs',
        'burr sets' => 'rs',
        'burrs' => 'rs',
        'busbars' => 'rs',
        'buzzers' => 'rs',
        'c hook wrenches' => 'rs',
        'cabinet, drawer & enclosure locks' => 'rs',
        'cabinets & cupboards' => 'rs',
        'cable clips & clamps' => 'rs',
        'cable conduit fittings' => 'rs',
        'cable conduits' => 'rs',
        'cable covers & caps' => 'rs',
        'cable crimpers' => 'rs',
        'cable cutters' => 'rs',
        'cable gland adaptors' => 'rs',
        'cable gland kits' => 'rs',
        'cable gland locknuts' => 'rs',
        'cable gland plugs' => 'rs',
        'cable glands' => 'rs',
        'cable grommet kits' => 'rs',
        'cable grommets & grommet strips' => 'rs',
        'cable joints' => 'rs',
        'cable knives' => 'rs',
        'cable label printer accessories' => 'rs',
        'cable label printers' => 'rs',
        'cable marker accessories' => 'rs',
        'cable markers' => 'rs',
        'cable marking kits' => 'rs',
        'cable moulded parts' => 'rs',
        'cable routing' => 'rs',
        'cable separators & bushing' => 'rs',
        'cable sleeves' => 'rs',
        'cable sleeving kits & refills' => 'rs',
        'cable sleeving tape' => 'rs',
        'cable sleeving tools' => 'rs',
        'cable spiral wrapping' => 'rs',
        'cable storage racks & dispensers' => 'rs',
        'cable stripper accessories' => 'rs',
        'cable tie assemblies' => 'rs',
        'cable tie mounts' => 'rs',
        'cable tie tensioning & bundling tools' => 'rs',
        'cable ties' => 'rs',
        'cable tracers & fuse finders' => 'rs',
        'cable transit sealing systems' => 'rs',
        'cable tray accessories' => 'rs',
        'cable trays & baskets' => 'rs',
        'cable trunking' => 'rs',
        'cable trunking accessories' => 'rs',
        'cables & wires' => 'rs',
        'calipers' => 'rs',
        'capacitive proximity sensors' => 'rs',
        'carrying handles' => 'rs',
        'cartridge heaters' => 'rs',
        'cat5 cable' => 'rs',
        'cat5 cable assemblies' => 'rs',
        'cat5e cable' => 'rs',
        'cat5e cable assemblies' => 'rs',
        'cat6 cable' => 'rs',
        'cat6 cable assemblies' => 'rs',
        'cat6a cable assemblies' => 'rs',
        'cat7 cable' => 'rs',
        'cat7 cable assemblies' => 'rs',
        'cctv camera housing & mounting accessories' => 'rs',
        'cctv cameras' => 'rs',
        'cctv computer components & peripherals' => 'rs',
        'cctv digital video recorders (dvr)' => 'rs',
        'cctv lenses' => 'rs',
        'cctv monitors' => 'rs',
        'cctv systems' => 'rs',
        'cctv transmission & receiving accessories' => 'rs',
        'ceiling lighting & battens' => 'rs',
        'ceiling roses' => 'rs',
        'ceramic heating elements' => 'rs',
        'chain' => 'rs',
        'chalk lines' => 'rs',
        'chassis mounting transformers' => 'rs',
        'chisels' => 'rs',
        'circlip pliers' => 'rs',
        'circuit trips' => 'rs',
        'circular connector backshells' => 'rs',
        'circular connector contacts' => 'rs',
        'circular connector dust caps' => 'rs',
        'circular connector inserts' => 'rs',
        'circular connector seals' => 'rs',
        'circular fluorescent tubes' => 'rs',
        'clampmeters' => 'rs',
        'clamps' => 'rs',
        'claw hammers' => 'rs',
        'clip connector test leads' => 'rs',
        'coaxial cable' => 'rs',
        'coaxial cable assemblies' => 'rs',
        'coiled cable' => 'rs',
        'colour coding rings' => 'rs',
        'combination pliers' => 'rs',
        'combination spanners' => 'rs',
        'combination squares & kits' => 'rs',
        'combined head, face & hearing protection kits' => 'rs',
        'communication ear defenders' => 'rs',
        'compact fluorescent floodlights' => 'rs',
        'compartment boxes' => 'rs',
        'component & ic testers' => 'rs',
        'compression springs' => 'rs',
        'computer power cable assemblies' => 'rs',
        'computer power supply' => 'rs',
        'conductivity meters' => 'rs',
        'conduit & trunking cable' => 'rs',
        'cone cutters' => 'rs',
        'connecting components' => 'rs',
        'connection & termination blocks' => 'rs',
        'connector screws & nuts' => 'rs',
        'connector tool kits' => 'rs',
        'connector wire & interface seals' => 'rs',
        'connectors' => 'rs',
        'consoles & desktop enclosures' => 'rs',
        'contactor & control relay overloads' => 'rs',
        'contactor accessories' => 'rs',
        'contactor timers' => 'rs',
        'contactors' => 'rs',
        'control & automation' => 'rs',
        'control station indicators' => 'rs',
        'control station pendants' => 'rs',
        'control station switches' => 'rs',
        'conveyor rollers' => 'rs',
        'conveyor turntables' => 'rs',
        'corded drills & drill drivers' => 'rs',
        'corded hammer & sds drills' => 'rs',
        'corded impact drills' => 'rs',
        'corded jigsaws' => 'rs',
        'cordless drills & drill drivers' => 'rs',
        'cordless hammer & sds drills' => 'rs',
        'cordless jigsaws' => 'rs',
        'cordless reciprocating saws' => 'rs',
        'cordless screwdrivers' => 'rs',
        'counter & hour meter accessories' => 'rs',
        'counters' => 'rs',
        'countersink sets' => 'rs',
        'coveralls & overalls' => 'rs',
        'covers, seals & caps' => 'rs',
        'crimp blade terminals' => 'rs',
        'crimp bootlace ferrules' => 'rs',
        'crimp bullet connectors' => 'rs',
        'crimp butt splice terminals' => 'rs',
        'crimp d-sub connectors' => 'rs',
        'crimp piggyback terminals' => 'rs',
        'crimp pin connectors' => 'rs',
        'crimp quick disconnect terminals' => 'rs',
        'crimp receptacles' => 'rs',
        'crimp ring terminals' => 'rs',
        'crimp spade connectors' => 'rs',
        'crimp tab terminals' => 'rs',
        'crimp terminal adapters' => 'rs',
        'crimp terminal covers' => 'rs',
        'crimp terminal kits' => 'rs',
        'crimp tool dies' => 'rs',
        'crocodile clips' => 'rs',
        'cross wrenches & control cabinet keys' => 'rs',
        'current & voltage calibrators' => 'rs',
        'current loop calibrators' => 'rs',
        'current probes & clamps' => 'rs',
        'current transducers' => 'rs',
        'current transformers' => 'rs',
        'cutter blades' => 'rs',
        'cutters' => 'rs',
        'cy cable' => 'rs',
        'cylinder locks' => 'rs',
        'damper actuators' => 'rs',
        'data loggers' => 'rs',
        'dc geared motors' => 'rs',
        'dc motors' => 'rs',
        'dc power plugs' => 'rs',
        'dc power sockets' => 'rs',
        'dc-ac car power inverters' => 'rs',
        'decade boxes' => 'rs',
        'desk & portable fans' => 'rs',
        'desk lights' => 'rs',
        'desktop power supply' => 'rs',
        'desoldering gun tips' => 'rs',
        'desoldering guns & pumps' => 'rs',
        'desoldering nozzles' => 'rs',
        'desoldering wicks & braids' => 'rs',
        'dial thermometers' => 'rs',
        'dichroic & reflector halogen lamps' => 'rs',
        'die nut sets' => 'rs',
        'die nuts' => 'rs',
        'differential transformers' => 'rs',
        'diffusion valves' => 'rs',
        'digital locks' => 'rs',
        'digital multimeters' => 'rs',
        'digital oscilloscopes' => 'rs',
        'digital panel ammeters' => 'rs',
        'digital panel battery meters' => 'rs',
        'digital panel multi-function meters' => 'rs',
        'digital panel voltmeters' => 'rs',
        'digital positive pressure gauges' => 'rs',
        'digital power meters' => 'rs',
        'digital pressure meters' => 'rs',
        'digital thermometers' => 'rs',
        'digital video & monitor cable assemblies' => 'rs',
        'dil sockets' => 'rs',
        'dimmer switches' => 'rs',
        'din 41612 connectors' => 'rs',
        'din 43650 solenoid connectors' => 'rs',
        'din cable assemblies' => 'rs',
        'din connectors' => 'rs',
        'din rail & panel mount power supplies' => 'rs',
        'din rail & panel mount transformers' => 'rs',
        'din rail enclosures' => 'rs',
        'din rail terminal accessories' => 'rs',
        'din rail terminal component boxes' => 'rs',
        'din rail time switches' => 'rs',
        'din rails' => 'rs',
        'direct to air heat pumps' => 'rs',
        'disposable gloves' => 'rs',
        'disposable respirators' => 'rs',
        'distribution boards' => 'rs',
        'dol & star delta starters' => 'rs',
        'dolphin clips' => 'rs',
        'door closers' => 'rs',
        'downlights & display lighting' => 'rs',
        'drawer & cabinet handles' => 'rs',
        'drawer slides' => 'rs',
        'drawer storage' => 'rs',
        'drill bit sets' => 'rs',
        'drill bit sharpeners' => 'rs',
        'drill grinding attachments' => 'rs',
        'drum transport & stands' => 'rs',
        'drywell calibrators' => 'rs',
        'd-sub connector accessories' => 'rs',
        'd-sub connector backshells' => 'rs',
        'd-sub connector contacts' => 'rs',
        'd-sub connector kits' => 'rs',
        'duct fans' => 'rs',
        'dummy cctv cameras' => 'rs',
        'ear defenders' => 'rs',
        'ear plugs' => 'rs',
        'earth & ground resistance testers' => 'rs',
        'earth blocks' => 'rs',
        'electrical installation tester accessories' => 'rs',
        'electrical installation testers' => 'rs',
        'electrical safety mats' => 'rs',
        'electronic sounders' => 'rs',
        'electronics' => 'rs',
        'embedded linear power supplies' => 'rs',
        'embedded switch mode power supplies (smps)' => 'rs',
        'emergency exit signs' => 'rs',
        'emergency eyewash kits & supplies' => 'rs',
        'emergency eyewash, facewash & shower equipment' => 'rs',
        'emergency light conversion kits' => 'rs',
        'emergency light fittings' => 'rs',
        'emergency stop foot switches' => 'rs',
        'emergency stop push buttons' => 'rs',
        'emi filters & accessories' => 'rs',
        'enclosed push button stations' => 'rs',
        'enclosure accessories' => 'rs',
        'enclosure heaters' => 'rs',
        'enclosure heating elements' => 'rs',
        'enclosure mounting & installation' => 'rs',
        'enclosure thermostats' => 'rs',
        'enclosures, storage & material handling' => 'rs',
        'end mills' => 'rs',
        'engineers squares' => 'rs',
        'engraving tools' => 'rs',
        'entrance & walkway mats' => 'rs',
        'esd grounding accessories' => 'rs',
        'esd grounding cords' => 'rs',
        'esd grounding wrist straps & cord sets' => 'rs',
        'esd static grounding clamps' => 'rs',
        'esd-safe & clean room gloves' => 'rs',
        'esd-safe & clean room over shoe covers' => 'rs',
        'esd-safe & clean room treatments, lotions & dispensers' => 'rs',
        'esd-safe bags' => 'rs',
        'esd-safe bins & boxes' => 'rs',
        'esd-safe cabinets, drawers & inserts' => 'rs',
        'esd-safe field kits' => 'rs',
        'esd-safe mats' => 'rs',
        'extension reels' => 'rs',
        'extension springs' => 'rs',
        'extraction tools' => 'rs',
        'eyebolts' => 'rs',
        'eyewear accessories' => 'rs',
        'f connectors' => 'rs',
        'fall arrest & fall recovery kits' => 'rs',
        'fall arrest equipment' => 'rs',
        'fall arrest harnesses & vests' => 'rs',
        'fan filters' => 'rs',
        'fan motors' => 'rs',
        'fan speed controllers' => 'rs',
        'fans' => 'rs',
        'feeler gauges' => 'rs',
        'fibre optic adapters' => 'rs',
        'fibre optic cable assemblies' => 'rs',
        'fibre optic connectors' => 'rs',
        'fibre optic kits' => 'rs',
        'fibre optic patch panels' => 'rs',
        'fibre optic test equipment' => 'rs',
        'field rj45 connectors' => 'rs',
        'filament indicator lamps' => 'rs',
        'file accessories' => 'rs',
        'file handles' => 'rs',
        'file sets' => 'rs',
        'filter & strap wrenches' => 'rs',
        'filter fans' => 'rs',
        'filter media' => 'rs',
        'finger guards' => 'rs',
        'fire alarm accessories' => 'rs',
        'fire alarm call points' => 'rs',
        'fire alarm detector test accessories' => 'rs',
        'fire alarm detector test kits' => 'rs',
        'fire detector & smoke alarm systems' => 'rs',
        'fire extinguisher accessories' => 'rs',
        'fire safety signs & labels' => 'rs',
        'first aid bandages, plasters & dressings' => 'rs',
        'first aid kits & burns kits' => 'rs',
        'first aid supplies' => 'rs',
        'fixed height mounts & feet' => 'rs',
        'fixed installation car power adapters' => 'rs',
        'fixed installation dc-ac power inverters' => 'rs',
        'flat nose pliers' => 'rs',
        'flat ribbon cable' => 'rs',
        'floodlight accessories' => 'rs',
        'flow controllers' => 'rs',
        'flow sensors, switches & indicators' => 'rs',
        'fly killer lamps' => 'rs',
        'fly killer light fittings' => 'rs',
        'fme connectors' => 'rs',
        'folding key sets' => 'rs',
        'function generators & counters' => 'rs',
        'funnels' => 'rs',
        'fused din rail terminals' => 'rs',
        'fused switch disconnectors' => 'rs',
        'fused terminal blocks' => 'rs',
        'gas blow torches' => 'rs',
        'gas detection' => 'rs',
        'gas springs' => 'rs',
        'gas torch refills' => 'rs',
        'gas welding torches & accessories' => 'rs',
        'gasket cutter punches' => 'rs',
        'gate catches & latches' => 'rs',
        'gearboxes' => 'rs',
        'general purpose enclosures' => 'rs',
        'gls halogen lamps' => 'rs',
        'gls incandescent light bulbs' => 'rs',
        'grab wire switches' => 'rs',
        'grabber clips' => 'rs',
        'grease gun coupling kits' => 'rs',
        'grease gun hoses' => 'rs',
        'grease gun nipple kits' => 'rs',
        'grilles & louvres' => 'rs',
        'hacksaw blades' => 'rs',
        'hacksaws' => 'rs',
        'hall effect sensors' => 'rs',
        'halogen capsule lamps' => 'rs',
        'halogen floodlights' => 'rs',
        'hand held enclosures' => 'rs',
        'hand held wired microphones' => 'rs',
        'hand saws' => 'rs',
        'hand wheels' => 'rs',
        'handheld magnifiers' => 'rs',
        'handlamps' => 'rs',
        'harsh environment wire' => 'rs',
        'hasp & staples' => 'rs',
        'hazard & warning signs & labels' => 'rs',
        'hazardous area light fittings' => 'rs',
        'hazardous area power connectors' => 'rs',
        'hazardous substance cabinets' => 'rs',
        'headband magnifiers' => 'rs',
        'hearing protection dispensers & accessories' => 'rs',
        'heat gun accessories' => 'rs',
        'heat guns' => 'rs',
        'heat lamps' => 'rs',
        'heat-shrink & cold-shrink sleeves' => 'rs',
        'heatsink mounting accessories' => 'rs',
        'heatsinks' => 'rs',
        'heavy duty power connector accessories' => 'rs',
        'heavy duty power connector kits' => 'rs',
        'heavy duty power connectors' => 'rs',
        'helmet & hard hat accessories' => 'rs',
        'helmets & hard hats' => 'rs',
        'hex bit adapter' => 'rs',
        'hex keys & sets' => 'rs',
        'hid floodlights' => 'rs',
        'hinges' => 'rs',
        'hmi accessories' => 'rs',
        'hoists' => 'rs',
        'hole saw accessories' => 'rs',
        'hole saw sets' => 'rs',
        'hole saws' => 'rs',
        'hook & loop fasteners' => 'rs',
        'hook clips' => 'rs',
        'hookup & equipment wire' => 'rs',
        'horns' => 'rs',
        'hour meters' => 'rs',
        'humidity indicating desiccators' => 'rs',
        'hvac air filters' => 'rs',
        'hvac ducting' => 'rs',
        'hvac systems' => 'rs',
        'hvac systems / hvac control systems' => 'rs',
        'hvac, fans & thermal management' => 'rs',
        'hydraulic puller sets' => 'rs',
        'hydraulic pullers' => 'rs',
        'hydrometers' => 'rs',
        'ic socket extractors' => 'rs',
        'ic test clips' => 'rs',
        'idc connectors' => 'rs',
        'idc d-sub connectors' => 'rs',
        'idc termination tools' => 'rs',
        'iec connectors' => 'rs',
        'iec filters' => 'rs',
        'impact sockets' => 'rs',
        'impact wrenches' => 'rs',
        'impulse relays' => 'rs',
        'indicator lampholders' => 'rs',
        'indicator lens & lampholder combinations' => 'rs',
        'indicator lenses' => 'rs',
        'indicators' => 'rs',
        'inductive proximity sensors' => 'rs',
        'industrial & automation circular connectors' => 'rs',
        'industrial automation cable assemblies' => 'rs',
        'industrial computers' => 'rs',
        'industrial interlocks' => 'rs',
        'industrial power connector accessories' => 'rs',
        'industrial power connector adapters' => 'rs',
        'industrial power connectors' => 'rs',
        'infrared sensors' => 'rs',
        'insertion & extraction combination tools' => 'rs',
        'insertion & extraction tools' => 'rs',
        'inspection mirror & probe accessories' => 'rs',
        'inspection mirror probes' => 'rs',
        'instrument cases' => 'rs',
        'insulation testers' => 'rs',
        'interface modules' => 'rs',
        'inverter drives' => 'rs',
        'ir (infrared) temperature sensors' => 'rs',
        'ir thermometers' => 'rs',
        'isolated dc-dc converters' => 'rs',
        'jack cable assemblies' => 'rs',
        'jack/trs connectors' => 'rs',
        'jigsaw blades' => 'rs',
        'jumpers & shunts' => 'rs',
        'junction boxes' => 'rs',
        'key & padlock cabinets' => 'rs',
        'key accessories' => 'rs',
        'key switches' => 'rs',
        'knee pads' => 'rs',
        'knife sharpeners' => 'rs',
        'knobs' => 'rs',
        'kvm mixed cable assemblies' => 'rs',
        'laboratory bottles' => 'rs',
        'ladders' => 'rs',
        'lampholders' => 'rs',
        'lamps, bulbs & tubes' => 'rs',
        'lan test equipment' => 'rs',
        'lan test equipment accessories' => 'rs',
        'lantern rechargeable batteries' => 'rs',
        'laser levels' => 'rs',
        'lathe chuck guards' => 'rs',
        'lathe parting off blades' => 'rs',
        'lathe parting off blocks' => 'rs',
        'lathe parting off insert removal keys' => 'rs',
        'lathe parting off inserts' => 'rs',
        'lathe tool bits' => 'rs',
        'lathe tool holders' => 'rs',
        'lathe tool sets' => 'rs',
        'lcr meters' => 'rs',
        'leak & flaw detector sprays' => 'rs',
        'led beacon lamps' => 'rs',
        'led cluster lamps' => 'rs',
        'led floodlights' => 'rs',
        'led indicator lamps' => 'rs',
        'led reflector lamps' => 'rs',
        'led tube lights' => 'rs',
        'lenses' => 'rs',
        'level controllers' => 'rs',
        'level sensors & switches' => 'rs',
        'lift tables' => 'rs',
        'light beams' => 'rs',
        'light meters' => 'rs',
        'light switches' => 'rs',
        'lighting' => 'rs',
        'lighting & electrical cable' => 'rs',
        'lighting ballasts' => 'rs',
        'lighting bases' => 'rs',
        'lighting bulkheads & drum lamps' => 'rs',
        'lighting connectors' => 'rs',
        'lighting controllers' => 'rs',
        'lighting ignitors' => 'rs',
        'lighting mounts' => 'rs',
        'lighting starters' => 'rs',
        'lighting time switches' => 'rs',
        'lighting transformers' => 'rs',
        'limit switch accessories' => 'rs',
        'limit switches' => 'rs',
        'linear fluorescent tubes' => 'rs',
        'linear halogen lamps' => 'rs',
        'linear transducers' => 'rs',
        'liquid in glass thermometers' => 'rs',
        'load cells' => 'rs',
        'lock cases' => 'rs',
        'lockers' => 'rs',
        'locking handles' => 'rs',
        'locking pliers' => 'rs',
        'lockout stations' => 'rs',
        'lockout tags' => 'rs',
        'lockouts' => 'rs',
        'logic modules' => 'rs',
        'long nose pliers' => 'rs',
        'loudspeaker connectors' => 'rs',
        'lump hammers' => 'rs',
        'machine & inspection lights' => 'rs',
        'machine guarding accessories' => 'rs',
        'machine presses' => 'rs',
        'machine reamer bits' => 'rs',
        'machine tool coolant systems' => 'rs',
        'magnetic catches' => 'rs',
        'magnetic pickups' => 'rs',
        'magnetisers' => 'rs',
        'mains connector accessories' => 'rs',
        'mains connector adapters & converters' => 'rs',
        'mains connectors' => 'rs',
        'mains inline connectors' => 'rs',
        'mains power cable' => 'rs',
        'mains rcd connectors' => 'rs',
        'mains socket testers' => 'rs',
        'mains test screwdrivers' => 'rs',
        'mallets' => 'rs',
        'manual grease guns' => 'rs',
        'masonry & sds drill bits' => 'rs',
        'mcbs' => 'rs',
        'mccbs' => 'rs',
        'mcx connectors' => 'rs',
        'measuring jugs' => 'rs',
        'mechanical counters' => 'rs',
        'mechanical hvac thermostats' => 'rs',
        'mechanical pullers' => 'rs',
        'memory & sim card connectors' => 'rs',
        'mercury vapour lamps' => 'rs',
        'metal halide lamps' => 'rs',
        'metal tabletop shears' => 'rs',
        'mica heating pads' => 'rs',
        'microwave & emission detectors' => 'rs',
        'mig welder accessories' => 'rs',
        'mil spec circular connectors' => 'rs',
        'milling inserts' => 'rs',
        'miniature drill bit sets' => 'rs',
        'miniature power tool chucks & collets' => 'rs',
        'miniature power tool kits' => 'rs',
        'mixed signal oscilloscopes' => 'rs',
        'modular battery contacts' => 'rs',
        'modular beacon tower accessories' => 'rs',
        'modular beacon tower components' => 'rs',
        'monitoring relays' => 'rs',
        'motor protection accessories' => 'rs',
        'motor protection circuit breakers' => 'rs',
        'mounting accessories' => 'rs',
        'multi function calibrators' => 'rs',
        'multi-angle vices' => 'rs',
        'multicore industrial cable' => 'rs',
        'multicore microphone & instrument cable' => 'rs',
        'multicore speaker cable' => 'rs',
        'multimedia tester' => 'rs',
        'multimeter accessories' => 'rs',
        'multimeter application adapters' => 'rs',
        'multimeter cases & holsters' => 'rs',
        'multimeter current clamp adapters' => 'rs',
        'multimeter fuses' => 'rs',
        'multimeter kits' => 'rs',
        'multimeter test leads' => 'rs',
        'multi-tools' => 'rs',
        'n connectors' => 'rs',
        'needle point probes' => 'rs',
        'neon bulbs' => 'rs',
        'neon indicator lamps' => 'rs',
        'networking faceplates & outlets' => 'rs',
        'non contact safety switches' => 'rs',
        'non contact voltage & magnetic field indicators' => 'rs',
        'non-fused din rail terminals' => 'rs',
        'non-fused switch disconnectors' => 'rs',
        'non-fused terminal blocks' => 'rs',
        'non-impact sockets' => 'rs',
        'non-integrated compact fluorescent lamps' => 'rs',
        'ohmmeters' => 'rs',
        'oil & fuel cans' => 'rs',
        'on / off temperature controllers' => 'rs',
        'open ended spanners' => 'rs',
        'oscilloscope adapters' => 'rs',
        'oscilloscope battery packs & chargers' => 'rs',
        'oscilloscope cases & bags' => 'rs',
        'oscilloscope kits' => 'rs',
        'oscilloscope probes' => 'rs',
        'oscilloscope software' => 'rs',
        'oven lamps' => 'rs',
        'over shoe cover' => 'rs',
        'pad saw blades' => 'rs',
        'padlocking & locking accessories' => 'rs',
        'padlocks' => 'rs',
        'pallet trucks' => 'rs',
        'panel printers' => 'rs',
        'parallel cable assemblies' => 'rs',
        'pc data acquisition accessories' => 'rs',
        'pcb connector contacts' => 'rs',
        'pcb connector housings' => 'rs',
        'pcb din connector accessories' => 'rs',
        'pcb d-sub connectors' => 'rs',
        'pcb headers' => 'rs',
        'pcb mounting enclosures' => 'rs',
        'pcb pin & socket strips' => 'rs',
        'pcb sockets' => 'rs',
        'pcb terminal blocks' => 'rs',
        'pcb transformers' => 'rs',
        'pcb vices & workholding systems' => 'rs',
        'peltier modules' => 'rs',
        'ph & water analysis calibration solutions' => 'rs',
        'ph & water analysis electrodes' => 'rs',
        'ph & water analysis kits' => 'rs',
        'ph & water analysis meters' => 'rs',
        'ph & water analysis strips' => 'rs',
        'phase rotation testers' => 'rs',
        'photoelectric sensors' => 'rs',
        'photovoltaic solar panels' => 'rs',
        'pick up tools' => 'rs',
        'pid temperature controllers' => 'rs',
        'pilot drill bits' => 'rs',
        'pipe benders' => 'rs',
        'pipe wrenches' => 'rs',
        'pipe, conduit & tube cutters' => 'rs',
        'pipes' => 'rs',
        'pipes & tubes' => 'rs',
        'plate castors' => 'rs',
        'plate fans' => 'rs',
        'platform trucks' => 'rs',
        'platinum resistance temperature sensors' => 'rs',
        'plc accessories' => 'rs',
        'plc cpus' => 'rs',
        'plc expansion modules' => 'rs',
        'plc i/o modules' => 'rs',
        'plc power supplies' => 'rs',
        'plc programming software' => 'rs',
        'plier sets' => 'rs',
        'plug in power supply' => 'rs',
        'plumbing' => 'rs',
        'plumbing & pipeline' => 'rs',
        'plumbing tools' => 'rs',
        'plunger dial indicators' => 'rs',
        'pneumatic grease guns' => 'rs',
        'portable appliance tester accessories' => 'rs',
        'portable appliance testers' => 'rs',
        'portable floodlights & leadlamps' => 'rs',
        'power cable assemblies' => 'rs',
        'power quality analysers' => 'rs',
        'power supplies' => 'rs',
        'power supplies and transformers' => 'rs',
        'power supply accessories' => 'rs',
        'power tool batteries' => 'rs',
        'power tool chargers' => 'rs',
        'power tool chucks' => 'rs',
        'precision position switches' => 'rs',
        'pre-configured beacon towers' => 'rs',
        'pressure calibrator accessories' => 'rs',
        'pressure calibrators' => 'rs',
        'pressure catches' => 'rs',
        'pressure gauge accessories' => 'rs',
        'pressure pumps' => 'rs',
        'pressure sensors' => 'rs',
        'pressure switches' => 'rs',
        'prohibition signs & labels' => 'rs',
        'property markers, uv scanners & counterfeit detectors' => 'rs',
        'protective sleeves' => 'rs',
        'pry bars' => 'rs',
        'pulse generators' => 'rs',
        'punch & die accessories' => 'rs',
        'punch & die combinations' => 'rs',
        'punch & die kits' => 'rs',
        'punch down tools & blades' => 'rs',
        'punch sets' => 'rs',
        'push button accessories' => 'rs',
        'push button enclosures' => 'rs',
        'push button pendant stations' => 'rs',
        'push buttons' => 'rs',
        'push wire terminals' => 'rs',
        'putty knives' => 'rs',
        'rack cooling' => 'rs',
        'rack fitting cases & enclosures' => 'rs',
        'rack mounting hardware' => 'rs',
        'rack panels' => 'rs',
        'rackmount enclosures' => 'rs',
        'ratchet spanners' => 'rs',
        'ratchet straps' => 'rs',
        'rca connectors' => 'rs',
        'rca phono cable assemblies' => 'rs',
        'rcd testers' => 'rs',
        'rcds' => 'rs',
        'reciprocating saw blades' => 'rs',
        'reed switches' => 'rs',
        'replacement handles & accessories' => 'rs',
        'retrofit compact fluorescent lamps' => 'rs',
        'reusable gloves' => 'rs',
        'reusable respirator accessories' => 'rs',
        'reusable respirators' => 'rs',
        'rf & coaxial adapters' => 'rs',
        'rf attenuators' => 'rs',
        'rf connector dust caps' => 'rs',
        'rf dummy loads' => 'rs',
        'rf splitters' => 'rs',
        'rf switches' => 'rs',
        'rf terminators' => 'rs',
        'ring & bush mounts' => 'rs',
        'ring spanners' => 'rs',
        'rivet guns' => 'rs',
        'rj adapters, couplers & extensions' => 'rs',
        'rj connector dust caps' => 'rs',
        'rj connector hoods & boots' => 'rs',
        'rj kits' => 'rs',
        'rj patch panel modules & accessories' => 'rs',
        'rj patch panels' => 'rs',
        'rj socket modules & blanks' => 'rs',
        'rj11 connectors' => 'rs',
        'rj12 connectors' => 'rs',
        'rj22 connectors' => 'rs',
        'rj25 connectors' => 'rs',
        'rj45 connectors' => 'rs',
        'rope' => 'rs',
        'rotary encoders' => 'rs',
        'rotary switch accessories' => 'rs',
        'rotary switches' => 'rs',
        'round nose pliers' => 'rs',
        'round ribbon cable' => 'rs',
        'rtd calibrators' => 'rs',
        'rules' => 'rs',
        'sack trucks' => 'rs',
        'safe conditions signs & labels' => 'rs',
        'safety controllers' => 'rs',
        'safety glasses & shields' => 'rs',
        'safety goggles' => 'rs',
        'safety limit switches' => 'rs',
        'safety relays' => 'rs',
        'safety shoes & boots' => 'rs',
        'safety site transformers' => 'rs',
        'safety, security, esd control & clean room' => 'rs',
        'scaffolding & work platforms' => 'rs',
        'scalpel & craft knife sets' => 'rs',
        'scalpel blades' => 'rs',
        'scalpels' => 'rs',
        'scart cable assemblies' => 'rs',
        'scissors' => 'rs',
        'scraping tools & accessories' => 'rs',
        'screw & nut starters' => 'rs',
        'screw extractors' => 'rs',
        'screw terminal d-sub connectors' => 'rs',
        'screwdriver bits & bit sets' => 'rs',
        'screwdriver sets' => 'rs',
        'screwdrivers' => 'rs',
        'scribes' => 'rs',
        'seals & brush strips' => 'rs',
        'security & alarm cable' => 'rs',
        'security alarm detectors & proximity sensors' => 'rs',
        'security alarm door & window switches' => 'rs',
        'security alarm sounders & strobes' => 'rs',
        'semiconductors' => 'rs',
        'sensor & switch cables & connectors' => 'rs',
        'sensor & switch magnets' => 'rs',
        'sensor mounting & fixing accessories' => 'rs',
        'sensor reflectors' => 'rs',
        'sensor testers' => 'rs',
        'serial cable assemblies' => 'rs',
        'shackles' => 'rs',
        'shears & nibblers' => 'rs',
        'shelves & drawers' => 'rs',
        'shelving systems' => 'rs',
        'signal conditioning' => 'rs',
        'sil sockets' => 'rs',
        'silicone heater mats' => 'rs',
        'single core control cable' => 'rs',
        'sirens' => 'rs',
        'site lights' => 'rs',
        'sledgehammers' => 'rs',
        'slide bolts' => 'rs',
        'sling points' => 'rs',
        'slings' => 'rs',
        'sma connectors' => 'rs',
        'socket accessories' => 'rs',
        'socket sets' => 'rs',
        'socket wrenches' => 'rs',
        'soft starts' => 'rs',
        'solar power regulators' => 'rs',
        'solder d-sub connectors' => 'rs',
        'solder fluxes' => 'rs',
        'solder fume extractors' => 'rs',
        'solder pin connectors' => 'rs',
        'solder sleeves' => 'rs',
        'soldering iron accessories' => 'rs',
        'soldering iron kits' => 'rs',
        'soldering iron tips' => 'rs',
        'soldering irons' => 'rs',
        'soldering station accessories' => 'rs',
        'soldering stations' => 'rs',
        'solders' => 'rs',
        'son lamps' => 'rs',
        'space heaters & radiators' => 'rs',
        'spanner sets' => 'rs',
        'spigot plates' => 'rs',
        'spill absorbents' => 'rs',
        'spill control equipment' => 'rs',
        'spill kits' => 'rs',
        'spirit levels & inclinometers' => 'rs',
        'spray guns' => 'rs',
        'spring hooks' => 'rs',
        'spring kits' => 'rs',
        'stand-alone face shields' => 'rs',
        'stand-alone fire, smoke & cigarette alarms' => 'rs',
        'stem castors' => 'rs',
        'step drill bits' => 'rs',
        'stepper motors' => 'rs',
        'steps' => 'rs',
        'stethoscopes & sonoscopes' => 'rs',
        'storage boxes' => 'rs',
        'straight die grinders' => 'rs',
        'strain relief cable guides & boots' => 'rs',
        'strain relief clips' => 'rs',
        'stud mounts' => 'rs',
        'subracks' => 'rs',
        'suction lifters' => 'rs',
        'surface mount time switches' => 'rs',
        'suspension mounts' => 'rs',
        'sweatshirts & fleeces' => 'rs',
        'switchgear & tri-rated cable' => 'rs',
        'switching regulators' => 'rs',
        'sy cable' => 'rs',
        'tachometers' => 'rs',
        'tank heaters' => 'rs',
        'tap & die wrenches' => 'rs',
        'tape measures' => 'rs',
        'tdr cable fault locator accessories' => 'rs',
        'tdr cable fault locators' => 'rs',
        'telecom cable' => 'rs',
        'telecom cable assemblies' => 'rs',
        'temperature control cables' => 'rs',
        'temperature control modules' => 'rs',
        'temperature probes' => 'rs',
        'temperature sensitive labels' => 'rs',
        'temperature sensor accessories' => 'rs',
        'temperature transmitters' => 'rs',
        'terminal block accessories' => 'rs',
        'terminal block housing' => 'rs',
        'terminal posts' => 'rs',
        'terminal strips' => 'rs',
        'test & measurement' => 'rs',
        'test connector adapters' => 'rs',
        'test lead & connector kits' => 'rs',
        'test lead racks' => 'rs',
        'test lead wire' => 'rs',
        'thermal gap pads' => 'rs',
        'thermal imaging cameras' => 'rs',
        'thermanl' => 'rs',
        'thermocouple & extension wire' => 'rs',
        'thermocouple calibrators' => 'rs',
        'thermocouples' => 'rs',
        'thermohygrometers' => 'rs',
        'thermostats' => 'rs',
        'thickness gauges & meters' => 'rs',
        'thin ethernet cable' => 'rs',
        'thread files' => 'rs',
        'thread pitch gauges' => 'rs',
        'thread repair kits' => 'rs',
        'threading tap & die sets' => 'rs',
        'threading tap & drill sets' => 'rs',
        'threading tap sets' => 'rs',
        'threading taps' => 'rs',
        'time delay relays' => 'rs',
        'time switch accessories' => 'rs',
        'timer switches' => 'rs',
        'tnc connectors' => 'rs',
        'toggle latches' => 'rs',
        'tool bags' => 'rs',
        'tool belts & tool pouches' => 'rs',
        'tool boxes' => 'rs',
        'tool cases' => 'rs',
        'tool chests & cabinets' => 'rs',
        'tool holder mounting rails' => 'rs',
        'tool kits' => 'rs',
        'tool lanyard holders' => 'rs',
        'tool trays' => 'rs',
        'tools' => 'rs',
        'torch accessories' => 'rs',
        'torch batteries' => 'rs',
        'torch bulbs' => 'rs',
        'torches' => 'rs',
        'toroidal transformers' => 'rs',
        'torque drivers' => 'rs',
        'torque wrenches' => 'rs',
        'torx keys & sets' => 'rs',
        'trace heating accessories' => 'rs',
        'trace heating cable' => 'rs',
        'trailing sockets & power distribution' => 'rs',
        'transformers' => 'rs',
        'transit cases, equipment cases & boxes' => 'rs',
        'transmission-line baluns' => 'rs',
        'trimming & safety knife blades' => 'rs',
        'trimming & safety knives' => 'rs',
        'trolleys & carts' => 'rs',
        'tv & video test equipment' => 'rs',
        'tv aerial connectors' => 'rs',
        'tweezer sets' => 'rs',
        'tweezers' => 'rs',
        'twist drill bits' => 'rs',
        'twisted & multipair industrial cable' => 'rs',
        'twisted & multipair installation cable' => 'rs',
        'type a usb connectors' => 'rs',
        'type b usb connectors' => 'rs',
        'tyre inflators' => 'rs',
        'tyre pressure gauges' => 'rs',
        'tyred wheels' => 'rs',
        'uhf connectors' => 'rs',
        'ultrasonic cleaning tanks' => 'rs',
        'ultrasonic proximity sensors' => 'rs',
        'ups accessories' => 'rs',
        'ups power supplies' => 'rs',
        'usb cable assemblies' => 'rs',
        'usb dust caps' => 'rs',
        'variacs' => 'rs',
        'vehicle joint splitters' => 'rs',
        'vehicle test kits' => 'rs',
        'vibration meters' => 'rs',
        'vice accessories' => 'rs',
        'video, data & voice wiring testers' => 'rs',
        'voice' => 'rs',
        'voltage indicators' => 'rs',
        'waistcoats' => 'rs',
        'wall & rail mount tool holders' => 'rs',
        'wall boxes' => 'rs',
        'wall mount tool cabinets' => 'rs',
        'wall mount tool panels' => 'rs',
        'water pump pliers' => 'rs',
        'weighing scales' => 'rs',
        'welding gloves' => 'rs',
        'welding helmets & shields' => 'rs',
        'wind turbines' => 'rs',
        'window & wall fans' => 'rs',
        'wire rope' => 'rs',
        'wire rope clips & grips' => 'rs',
        'work benches & work tops' => 'rs',
        'xlr cable assemblies' => 'rs',
        'xlr connectors' => 'rs',
        'yy cable' => 'rs',
        'zener & galvanic barriers' => 'rs',
        'RS'=> 'rs',
        'No Climb'=> 'rs',
        '3M PELTOR'=> 'rs',
        'Thomas & Betts'=> 'rs',
        'Philips Lighting'=> 'rs',
        'Hirschmann'=> 'rs',
        'Wolf Safety ' => 'rs',
        'COBA'=> 'rs',
        'Fluke'=> 'rs',
        'Hubbell ' => 'rs',
        'Peli'=> 'rs',
        'TE Connectivity ' => 'rs',
        'Weidmuller'=> 'rs',
        'Wago'=> 'rs',
        'Bahco'=> 'rs',
        'Plano'=> 'rs',
        'CEAG'=> 'rs',
        'Petrel'=> 'rs',
        'Telemecanique'=> 'rs',
        'Parmar'=> 'rs',
        'Osram'=> 'rs',
        'ebm-papst'=> 'rs',
        'Weller'=> 'rs',
        'Dupont Engineering Products ' => 'rs',
        'Starrett'=> 'rs',
        'Kopex'=> 'rs',
        'Block'=> 'rs',
        'Telegartner ' => 'rs',
        'BJB ' => 'rs',
        'Jo Jo'=> 'rs',
        'APC ' => 'rs',
        'Mennekes'=> 'rs',
        'Mobrey'=> 'rs',
        'Tools Of Trade'=> 'rs',
        'Carroll & Meynell'=> 'rs',
        'Petzl'=> 'rs',
        'Allen'=> 'rs',
        'Honeywell'=> 'rs',
        'Bulgin'=> 'rs',
        'Fulleon ' => 'rs',
        'Steinbach & Vollman ' => 'rs',
        'Brodersen'=> 'rs',
        'e2s ' => 'rs',
        'Staticide'=> 'rs',
        'Tridonic'=> 'rs',
        'Renaissance Mark'=> 'rs',
        'Dialight'=> 'rs',
        'Druck'=> 'rs',
        'Kopp'=> 'rs',
        'Lapp'=> 'rs',
        'Phoenix Contact ' => 'rs',
        'ABB ' => 'rs',
        'Schneider Electric'=> 'rs',
        'EAO ' => 'rs',
        'Siemens ' => 'rs',
        'JKL Components'=> 'rs',
        '3M'=> 'rs',
        'Omron'=> 'rs',
        'Belden'=> 'rs',
        'HellermannTyton ' => 'rs',
        'Schurter'=> 'rs',
        'Bosch'=> 'rs',
        'Facom'=> 'rs',
        'Crompton Lighting'=> 'rs',
        'Panasonic'=> 'rs',
        'Finder'=> 'rs',
        'Rose'=> 'rs',
        'Gunther Spelsberg'=> 'rs',
        'Duracell'=> 'rs',
        'Loctite ' => 'rs',
        'Saft'=> 'rs',
        'Vishay'=> 'rs',
        'Gedore'=> 'rs',
        'Dymo'=> 'rs',
        'Moeller ' => 'rs',
        'Saia-Burgess'=> 'rs',
        'Amphenol'=> 'rs',
        'Legris'=> 'rs',
        'Ideal Power ' => 'rs',
        'Traco'=> 'rs',
        'SKF ' => 'rs',
        'Alpha Wire'=> 'rs',
        'Yuasa'=> 'rs',
        'Mean Well'=> 'rs',
        'Advance Tapes'=> 'rs',
        'Knipex'=> 'rs',
        'Yale Hoist'=> 'rs',

        //ISS
        'iss' => 'iss',
        'iss machinery services' => 'iss',
        'iss machinery services (osaka)' => 'iss',

        //Manitowoc
        'manitowoc' => 'manitowoc',
        'manitowoc foodservice' => 'manitowoc',
        'manitowoc ice' => 'manitowoc',
        'convotherm' => 'manitowoc',

        //Alfa Laval Aalborg
        'aalborg' => 'alfalaval',
        'alfa laval' => 'alfalaval',
        'aalborg boilers' => 'alfalaval',
        'aalborg economizers' => 'alfalaval',
        'aalborg boilers and economizers' => 'alfalaval',
        'aalborg boilers & economizers' => 'alfalaval',
        'alfa laval aalborg' => 'alfalaval',
        'aalborg Industries' => 'alfalaval',
        'aalborg Industries inert gas systems' => 'alfalaval',
        'gadelius' => 'alfalaval',
        'gadelius boilers' => 'alfalaval',
        'gosfern' => 'alfalaval',
        'gosfern burners' => 'alfalaval',
        'kb' => 'alfalaval',
        'kB burners' => 'alfalaval',
        'mission' => 'alfalaval',
        'mission boilers' => 'alfalaval',
        'mission economizers' => 'alfalaval',
        'mission thermal fluid heating systems' => 'alfalaval',
        'mission control systems' => 'alfalaval',
        'smit gas' => 'alfalaval',
        'smit gas inert gas system' => 'alfalaval',
        'smit Gas nitrogen generators' => 'alfalaval',
        'smit Gas inert gas generators' => 'alfalaval',
        'sunrod' => 'alfalaval',
        'sunrod boilers' => 'alfalaval',
        'sunrod economizers' => 'alfalaval',
        'sunrod boilers and economizers' => 'alfalaval',
        'sunrod boilers & economizers' => 'alfalaval',
        'sunrod heat exchangers' => 'alfalaval',
        'sunrod ege' => 'alfalaval',
        'unex' => 'alfalaval',
        'unex boilers' => 'alfalaval',
        'unex economizers' => 'alfalaval',
        'unex boilers and economizers' => 'alfalaval',
        'unex boilers & economizers' => 'alfalaval',
        'vesta' => 'alfalaval',
        'vesta heat exchangers' => 'alfalaval',
        'vesta preheaters' => 'alfalaval',
        'vesta pre-heaters' => 'alfalaval',
        'wiesloch' => 'alfalaval',
        'Wiesloch thermal fluid systems' => 'alfalaval',

        //Wrist Ship Supply
        'wrist' => 'wrist',
        'wrist ship supply' => 'wrist',
        'wrist as' => 'wrist',
        'wrist europe' => 'wrist',
        'wrist usa' => 'wrist',
        'wrist far east' => 'wrist',
        'karlo corporation' => 'wrist',
        'west coast ship supply' => 'wrist',
        'east coast ship supply' => 'wrist',
        'world ship supply' => 'wrist',
        'triton marine supply' => 'wrist',
        'schierbeck supply services' => 'wrist',

        //MaK Marine Engines
        'mak' => 'mak',
        'mak marine engines' => 'mak',
        'mak dual fuel engines' => 'mak',
        'mak spare parts'  => 'mak',
        'mak marine'  => 'mak',
        'mak prices'  => 'mak',
        'mak engine'  => 'mak',
        'mak diesel'  => 'mak',
        'mak m 20'  => 'mak',
        'mak m 25'  => 'mak',
        'mak m 32'  => 'mak',
        'mak m 43'  => 'mak',
        'mak m 46'  => 'mak',
        'mak legacy engines'  => 'mak',
        'mak current engines'  => 'mak',
        'mak brand'  => 'mak',
        'mak common rail'  => 'mak',
        'mak injector nozzle'  => 'mak',
        'mak valves'  => 'mak',
        'mak cylinder heads'  => 'mak',
        'mak engine centre'  => 'mak',
        'mak engine training centre'  => 'mak',
        'mak reparts centre'  => 'mak',
        'special promotions mak'  => 'mak',
        'mak bundled kits/solutions'  => 'mak',
        'mak dealer'  => 'mak',
        'mak part load optimization'  => 'mak',
        'mak slow steaming solutions'  => 'mak',
        'caterpillar'  => 'mak',
        'cat'  => 'mak',
        'caterpillar marine'  => 'mak',
        'napier' => 'mak',
        'napier turbocharger' => 'mak',
        'napier cartridge' => 'mak',
        'napier lip seal' => 'mak',
        'mak deutschland' => 'mak',

        // EMS Seven Seas Spain
        'ems' => 'ems',
        'ems ship supply' => 'ems',
        'ship supply services' => 'ems',
        'global ship supply services' => 'ems',
        'international ship supply services' => 'ems',
        'ship supplies' => 'ems',
        'ship supplier' => 'ems',
        'ship supply' => 'ems',
        'general ship supplies' => 'ems',
        'general ship supply' => 'ems',
        'provisions' => 'ems',
        'ship supply group' => 'ems',
        'ship provisions' => 'ems',
        'ship stores' => 'ems',
        'global maritime services' => 'ems',
        'maritime supply' => 'ems',
        'maritime supplies' => 'ems',
        'world ship supplies' => 'ems',
        'international maritime services' => 'ems',
        'merchant marine' => 'ems',
        'world-leading ship supplier' => 'ems',
        'marine ship supply' => 'ems',
        'ship provisions supply' => 'ems',
        'stromme' => 'ems',
        'stromme marine equipment' => 'ems',
        'marine equipment' => 'ems',
        'quality marine products' => 'ems',
        'agency services' => 'ems',
        'agency ship supply services' => 'ems',
        'wave shipping' => 'ems',

        //RMS Marine Service Company Ltd
        'rms' => 'rms',
        'chandlery' => 'rms',
        'supply' => 'rms',
        'stores' => 'rms',
        'provision' => 'rms',
        'ship spare parts' => 'rms',
        'logistics' => 'rms',
        'offshore' => 'rms',
        'safety' => 'rms',
        'repair' => 'rms',
        'agency' => 'rms',
        'docking support' => 'rms',
        'custom clearance' => 'rms',
        'equipment' => 'rms',

		'chandlers' => 'rms',
    	'shipchandler' => 'rms',
    	'shipchandlers' => 'rms',
    	'shipchandlery' => 'rms',
    	'general ship supplies' => 'rms',
    	'ship chandlers' => 'rms',
    	'ship supply' => 'rms',

    	'chandler' => 'rms',
    	'chandleries' => 'rms',
    	'shipchandleries' => 'rms',
    	'ship chandleries' => 'rms',
    	'ship supplies' => 'rms',
    	'ship store' => 'rms',
    	'general ship store' => 'rms',
    	'ship suppliers' => 'rms',
    	'ship supplier' => 'rms',
    	'general store' => 'rms'

        /*,

        //Global Navigation Solutions Group
        'gns' => 'gns',
        'global navigation solutions' => 'gns',
        'global navigation solutions group' => 'gns',
        'chart' => 'gns',
        'charts' => 'gns',
        'publication' => 'gns',
        'publications' => 'gns',
        'digital navigation' => 'gns',
        'e-navigation' => 'gns',
        'enc' => 'gns',
        'encs' => 'gns',
        'chart updating' => 'gns',
        'voyager' => 'gns',
        'navigation software' => 'gns',
        'outfit management services' => 'gns',
        'weather routing' => 'gns',
        'passage planning' => 'gns',
        'nautical chart' => 'gns',
        'nautical charts' => 'gns',
        'navigation chart' => 'gns',
        'navigation charts' => 'gns'*/
    );

    /**
     * Matches a set of keywords to a zone, or a specific zone override, and
     * fetches the content
     *
     * @access public
     * @static
     * @param string $keywords Keywords to match zones to, separated by spaces
     * @param string $zone A zone override
     * @return array
     */
    public static function fetchZoneData($keywords, $zone = null) {

    	$config = parent::getConfig();
		$zoneData = self::$zoneData;
		$zoneSynonyms = self::$zoneSynonyms;

        /**
         * Check the ontology for any specific search terms.
         * This will allow us to dump someone in a zone, and also set the
         * search type correctly
         */
        $matchedZones = false;

        if ($zone && $zoneData[$zone]) {
            $matchedZones[$zone] = $zoneData[$zone];
        } else {
            // match the zones
            $stringHelper = new Myshipserv_View_Helper_String();
            $keywordsArray = $stringHelper->createKeywordPhrases($keywords, " ");

            if (is_array($keywordsArray)) {
                $matchedZones = array();
                foreach ($keywordsArray as $keyword) {
                    $keyword = trim(strtolower(str_replace(',', '', $keyword)));

                    if (strlen($keyword) >= 2) {
                        foreach ($zoneSynonyms as $zoneSynonym => $zoneId) {
                            if ($zoneSynonym == $keyword) {
                                if (!isset($matchedZones[$zoneId]) and count($matchedZones) < 3){
                                    $matchedZones[$zoneId] = $zoneData[$zoneId];
                                }
                            }
                        }
                    }
                }
            }

            //lets find phrase matches
            foreach ($zoneSynonyms as $zoneSynonym => $zoneId) {
                $keywords = strtolower($keywords);
                //phrase always has space
                if (strpos($zoneSynonym, " ") !== false) {
                    if (strpos($keywords, $zoneSynonym) !== false) {
                        if (!isset($matchedZones[$zoneId]) and count($matchedZones) < 3)
                            $matchedZones[$zoneId] = $zoneData[$zoneId];
                    }
                }
            }
        }

        /**
         * Now put the matched zones into an array for view consumption.
         * If something exists within multiple zones, we may want to put
         * some logic in here to decide which zone to actually put them in
         *
         * This should probably move into a generic Zone object
         */
        $zones = array();

        // don't forget to comment this out.
       // $matchedZones['chandlers'] = array('name' => 'Chandlers','contentXml' => 'chandlers.xml');
        if (is_array($matchedZones)) {
            foreach ($matchedZones as $zoneName => $zone) {
                //$zoneOntology = new Shipserv_Ontology_Zone($zoneUri);

                /**
                 * Fetch the content XML file for the zone and put it into
                 * an array so we don't need to process it within the
                 * controller. The view can just handle it - nice and
                 * simple
                 */
                if ($zone['contentXml']) {
                    // after integration with the app
                    $contentXML = simplexml_load_file($config->includePaths->library  . "/zones/" . $zone['contentXml']);

                    $content = Shipserv_Helper_Xml::simpleXml2Array($contentXML);
                    $zoneUrl = self::returnZoneToCategoryURL($zoneName);
                }

                $zones[$zoneName] = array('name' => $zone['name'],
                    'content' => $content,
                    'zoneUrl' => $zoneUrl
                );
            }
        }
        //echo "TOTAL_ZONE: " . count($zones);

        //TODO refactor this part to return the sinwa banner constant for sinwa zone and location Singapore

        return $zones;
    }

    /*
     * This function will return a mapping of a category to a related zone (e.g. ship-chandlery category to the chandlers zone)
     * We dont want to use sysnonyms for this as we need to keep the acessible URLS for a zone to a minimum to reduce duplication of
     * Data
     * @param  $cat string - The category/brand/port we want to translate/map to its zone./
     * @return string. return false if no match
     */

    public static function returnCategoryToZoneMapping($cat) {
        if (!empty($cat)) {
            return array_key_exists(strtolower($cat), self::$mapArray) ? self::$mapArray[strtolower($cat)] : false;
        } else {
            return false;
        }
    }

    public static function returnZoneToCategoryURL($zone) {
        $urlArray = self::$urlToZone;

        if (!empty($zone)) {

            return array_key_exists($zone, $urlArray) ? $urlArray[$zone] : false;
        } else {
            return false;
        }
    }

    public static function returnZoneToCategoryMapping($zone) {
        if (!empty($zone)) {
            $retVal = array_search(strtolower($zone), self::$mapArray);
            return $retVal !== false ? $retval : $zone;
        } else {
            return $zone;
        }
    }

    /**
     * Refactored code to produce 2 random zones with associated data.
     * @param Integer $retCount How many zones to return
     * @return type
     */
    public static function returnRandomZones($retCount = 2){
        $zoneAds = self::$enabledZones;		// select 2 random zone ads

        //Ensure we dont have too many zones
        if($retCount > count($zoneAds)){
        	$selectNumber = count($zoneAds);
        }else{
        	$selectNumber = $retCount;
        }

		$keys = array_keys($zoneAds);
		shuffle($keys);
		$selectedAdKeys = array_slice($keys, 0, $retCount);
		$selectedAds = array();

		foreach ($selectedAdKeys as $key)
		{
			$selectedAds[$key] = $zoneAds[$key];
        	$selectedAds[$key]['zoneUrl'] = self::returnZoneToCategoryURL($key);
		}

        return $selectedAds;
    }

    public static function showZoneSponsorshipBanner($categoryId)
    {
    	$db = parent::getDb();
    	$sql = "SELECT COUNT(*) FROM pages_zone_sponsorship WHERE pzs_category_id=:categoryId";
    	return ($db->fetchOne($sql, array('categoryId' => $categoryId))>0);
    }

    public static function getZoneSponsorshipBanner($refinedQuery)
    {
    	// add random to each block
    	$db = parent::getDb();
    	$params['categoryId'] = $refinedQuery['id'];

    	$sqlCheckingDate = "
					AND
    				(
    					(
    						pzs_start_date IS null
    						AND pzs_end_date IS null
    						AND pzs_is_active=1
    					)
    					OR
    					(
    						pzs_start_date IS NOT null
    						AND pzs_end_date IS NOT null
    						AND sysdate BETWEEN pzs_start_date AND pzs_end_date
    						AND pzs_is_active=1
    					)
    					OR
    					(
    						pzs_start_date IS null
    						AND pzs_end_date IS NOT null
    						AND sysdate < pzs_end_date
    						AND pzs_is_active=1
    					)
    					OR
    					(
    						pzs_start_date IS NOT null
    						AND pzs_end_date IS null
    						AND sysdate > pzs_start_date
    						AND pzs_is_active=1
    					)
    				)
    	";

    	// getting banners for category globally
    	$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 4 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code IS null
    				AND pzs_cnt_country_code IS null
    	" . $sqlCheckingDate;
    	$debug[] = "GLOBAL";
    	// this won't be executed
        if( $refinedQuery['portCode'] != "" && $refinedQuery['countryCode'] == "" )
    	{
    		$debug[] = "port and empty country";
    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 3 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code=:portCode
    				AND pzs_cnt_country_code IS null
    		" . $sqlCheckingDate;
    		$params['countryCode'] = $refinedQuery['countryCode'];
    	}

    	// country level
        else if( $refinedQuery['portCode'] == "" && $refinedQuery['countryCode'] != "" )
    	{
    		$debug[] = "country specified";
    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 2 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code IS null
    				AND pzs_cnt_country_code=:countryCode
    		" . $sqlCheckingDate;
    		$params['countryCode'] = $refinedQuery['countryCode'];
    	}

		// port level
        else if( $refinedQuery['portCode'] != "" && $refinedQuery['countryCode'] != "" )
    	{
    		$debug[] = "country and port specified, ";
    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 2 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code IS null
    				AND pzs_cnt_country_code=:countryCode
    		" . $sqlCheckingDate;
    		$params['countryCode'] = $refinedQuery['countryCode'];

    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 1 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND
    				(
    					(	pzs_prt_port_code=:portCode AND pzs_cnt_country_code=:countryCode	)
    					--OR (	pzs_prt_port_code IS null AND pzs_cnt_country_code=:countryCode	)
    				)
    		" . $sqlCheckingDate;
    		$params['portCode'] = $refinedQuery['portCode'];
    		$params['countryCode'] = $refinedQuery['countryCode'];
    	}

    	$query = "SELECT * FROM (" . implode(" UNION ALL ", $sql) . ") ORDER BY priority ASC";
    	//echo "<hr />".implode("<br />", $debug)  . "<hr />";
    	//echo ( $query );
    	$result = $db->fetchAll($query, $params);
    	return $result;
    }

    public function storeXmlToMemcache(){
    	$content = Shipserv_Helper_Xml::simpleXml2Array($contentXML);
    	foreach (self::$zoneData as $zoneName => $zone) {

    		/**
    		 * Fetch the content XML file for the zone and put it into
    		 * an array so we don't need to process it within the
    		 * controller. The view can just handle it - nice and
    		 * simple
    		 */
    		if ($zone['contentXml']) {
    			// after integration with the app
    			$contentXML = simplexml_load_file($config->includePaths->library  . "/zones/" . $zone['contentXml']);
    			$content = Shipserv_Helper_Xml::simpleXml2Array($contentXML);
    		}
    	}
    }

    public static function performCheckOnPorts( $zones, $refinedQuery )
    {
    	$debug = false;
    	if( $debug === true ) print_r($zones);
    	if( $debug === true ) echo "<br /><br /><br /><br />ZONE FOUND: " . count($zones);

    	$portCode = $refinedQuery['portCode'];
    	$countryCode = $refinedQuery['countryCode'];

    	if( $portCode == "" && $countryCode == "" )
    	{
    		return $zones;
    	}

    	foreach($zones as $name => $zone)
    	{
    		if( $zone['content']['supportForPortCheckingForEachSupplierListed'] == "1")
    		{
    			foreach( $zone['content']['search']['orFilters']['id'] as $id )
    			{
    				$supplier = Shipserv_Supplier::getInstanceById($id);

    				foreach( $supplier->ports as $r )
    				{
    					$allowedPort[$r['code']] = true;
    					// getting unique list of countrycode
    					$tmp = explode("-", $r['code']);
    					$allowedCountry[$tmp[0]] = true;
    				}
    			}

    			$allowedPort = array_keys($allowedPort);
    			$allowedCountry = array_keys($allowedCountry);

    			if( $portCode != "" && $countryCode != "" )
    			{
	    			if( in_array($portCode, $allowedPort) !== false )
	    			{
	    				if( $zone['content']['forceToShowSingleZone'] == "1" )
	    				{
	    					$newZones = array($name => $zone);

	    				}
	    				else
	    				{
	    					$newZones[][$name] = $zone;
	    				}
	    			}
    			}
    			else if( $portCode == "" && $countryCode != "" )
    			{
    				if( in_array($countryCode, $allowedCountry) !== false )
    				{
    					if( $zone['content']['forceToShowSingleZone'] == "1" )
	    				{
	    					$newZones = array($name => $zone);
	    				}
	    				else
	    				{
	    					$newZones[][$name] = $zone;
	    				}
    				}
    			}
    		}
    		else
    		{
    			$newZones[$name] = $zone;
    		}

    		if( $zone['content']['forceToShowSingleZone'] == "1" && count($newZones) == 1 )
    		{
    			break;
    		}
    	}

    	if( $debug === true ) echo "<br /><br /><br /><br />CURRENT PORT AND COUNTRY: " . $countryCode . " ______" . $portCode;

    	if( $debug === true ) echo "<br /><br /><br /><br />COUNTRY: ";
    	if( $debug === true ) print_r($allowedCountry);

    	if( $debug === true ) echo "<br /><br /><br /><br />PORT: ";
    	if( $debug === true ) print_r($allowedPort);

    	if( $debug === true ) echo "<br /><br /><br /><br />AFTER: ";
    	if( $debug === true ) print_r($newZones);

    	if( $debug === true ) echo "<br /><br /><br /><br />ZONE FOUND: " . count($newZones);
    	return $newZones;
    }

}
