<?php
/**
* Return the aditionl buyer related vessel info for a row
*/

class Shipserv_Oracle_Targetcustomers_Vessel  {

	/**
    * @var Singleton The reference to *Singleton* instance of this class
    */
    private static $instance;
    protected $db;
    protected $dao;
    protected $hierarchy;

    /**
    * Returns the *Singleton* instance of this class.
    *
    * @return Shipserv_Oracle_Targetcustomers_Vessel
    */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    /**
    * Protected what we have to hide
    */
    protected function __construct() {
    	$this->db = Shipserv_Helper_Database::getSsreport2Db();
    	$this->dao = new Shipserv_Oracle_Targetcustomers_Dao();
    }
    private function __clone()  {}

    public function setHierarchy( $hierarchy )
    {
    	$this->hierarchy = $hierarchy;
    	return static::$instance;
    }

	/**
	* Returns an array, with the vessel count, and vessel type list
	* This function will be deprecated, should use contracted list
	*/
	/*
	public function getVesselInfoOld( $bybBranchCode, $spbBranchCode, $filterDate = null, $includeChildren = true )
	{

		//Use recursive query if icludeChildren is On
		if ($includeChildren === true) {
			$buyerListSql = "
				SELECT
					b.byb_branch_code
				FROM
					buyer_branch@livedb_link b
					START WITH byb_branch_code = :bybBranchCode
					CONNECT BY NOCYCLE PRIOR byb_branch_code = byb_under_contract
				";
		} else {
			$buyerListSql = "
				SELECT
					TO_NUMBER(:bybBranchCode) byb_branch_code 
				FROM
					dual
			";
		}

		$sql = "
			WITH buyer_data AS
			(".$includeChildren."), vessel_data AS
			(
			SELECT
				vslh1.byb_branch_code
				,(
						DECODE(
						vslh1.vslh_id_grouped_to,
						0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
						nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
							from vessel_history vslh2
							where vslh2.vslh_id = vslh1.vslh_id_grouped_to
							and rownum = 1 ),
						'INVALID IMO~NO VESSEL NAME'
					   )
					)
				) vessel_name
				, ih.shiptypelevel5
			FROM
				ord_traded_gmv tg JOIN ord ro ON (tg.ord_original=ro.ord_internal_ref_no)
				JOIN vessel_history vslh1 ON (
					vslh1.byb_branch_code = ro.byb_branch_code
					AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ro.ord_imo_no )))
					AND vslh1.vslh_imo = nvl( ro.ord_imo_no, '-' )
					AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ro.ord_vessel_name ))), '-' )
				)
				LEFT JOIN ihs_ship ih ON (
					vslh1.vslh_name = ih.shipname
				)
				JOIN buyer_data ab ON (ab.byb_branch_code=ro.byb_branch_code)
  			WHERE
				tg.spb_branch_code = :spbBranchCode\n";
        
			if ( $filterDate ) {
			  	$sql .= "AND tg.ord_orig_submitted_date BETWEEN TO_DATE('".$filterDate."','YYYY-MM-DD') AND  SYSDATE\n";
			  } else {
			  	$sql .= "AND tg.ord_orig_submitted_date BETWEEN add_months(SYSDATE,-12) AND  SYSDATE\n";
			  }

		$sql .= "), vessel_groups AS
				(
					SELECT
			    		  shiptypelevel5
			    		, COUNT(DISTINCT vessel_name) vessel_count
					FROM
						vessel_data
					WHERE 
						shiptypelevel5 is not null
					GROUP BY
						shiptypelevel5
					ORDER BY 
						vessel_count DESC
					
				)

		    SELECT
		    	TO_CHAR(COUNT(distinct vessel_name)) vessel_info
			FROM
				vessel_data 
		    UNION ALL
		    SELECT 
		    	shiptypelevel5 vessel_info
		    FROM
		    	vessel_groups
		";


	$params = array(
			 'bybBranchCode' => $bybBranchCode
			,'spbBranchCode' => $spbBranchCode
		);

	$result =  $this->dao->fetchQuery ($sql, $params, __METHOD__);
	$vesselCount = 0;
	$vesselNameList = array();

	for ($i = 0 ; $i<count($result) ;$i++) {
		if ($i == 0) {
			$vesselCount = $result[$i]['VESSEL_INFO'];
		} else {
			array_push($vesselNameList, $result[$i]['VESSEL_INFO']);
		}
	}

	return array(
			  'vesselCount' => $vesselCount
			, 'vesselTypeList' => $vesselNameList
		);

	}
	*/

	public function getVesselInfo( $bybBranchCode, $spbBranchCode, $filterDate = null, $includeChildren = true )
	{
		$filterDate = $this->roundFilterDate($filterDate);

		if ($includeChildren === true) {
			$branchList = $this->getBranchList($bybBranchCode);
		} else {
			$branchList = $bybBranchCode;
		}

		$sql = "
			WITH vessel_base AS
			(
				SELECT 
					 shiptypelevel5
	        		, COUNT(DISTINCT vslh_name) vessel_count
				FROM
					vessel_history vslh1
				LEFT JOIN ihs_ship ih ON (
					vslh1.vslh_imo = ih.lrimoshipno
					)
				WHERE
					byb_branch_code IN (".$branchList.")
	        		AND shiptypelevel5 is not null
		 	    GROUP BY
				    shiptypelevel5
	      		ORDER BY
	        		vessel_count DESC
			)

			SELECT 
			  TO_CHAR(SUM(byb_no_of_registered_ships)) vessel_info
			FROM 
			  buyer_branch@livedb_link
			WHERE
			  byb_branch_code IN (".$branchList.")
			UNION ALL
				SELECT
					shiptypelevel5 vessel_info
				FROM
					vessel_base
				";

	$params = array();

	//print($sql); print_r($params); die();

	$result =  $this->dao->fetchQuery ($sql, $params, __METHOD__);
	$vesselCount = 0;
	$vesselNameList = array();

	for ($i = 0 ; $i<count($result) ;$i++) {
			if ($i == 0) {
				$vesselCount = $result[$i]['VESSEL_INFO'];
			} else {
				array_push($vesselNameList, $result[$i]['VESSEL_INFO']);
			}
		}

	return array(
		  'vesselCount' => $vesselCount
		, 'vesselTypeList' => $vesselNameList
	);

	}

	protected function getBranchList( $parentTnid )
	{
		if ($this->hierarchy) {
			if (array_key_exists((int)$parentTnid,$this->hierarchy)) {
	 		   return implode(',',$this->hierarchy[(int)$parentTnid]);
			} else 
			{
 				return $parentTnid;
			}
		} else {
			return $this->getBranchListIfNoHierarchy( $parentTnid  );
		}
	}

	protected function getBranchListIfNoHierarchy( $parentTnid )
	{
		$memcache = Shipserv_Memcache::getMemcache();
		$key = 'activePromoGetBranchList_'.$parentTnid;

		if ($memcache)
		{
			$result = $memcache->get($key);
			if ($result !== false) return $result;
		}

		 $buyer =  Shipserv_Buyer_Branch::getInstanceById( $parentTnid );

		 if ($buyer->isTopLevelBranch()) {
		 	$result = implode(",",$buyer->getAllBranchesInTheHierarchy());
		 } else {
		 	$result = $parentTnid;
		 }
		 
		 if ($memcache) $memcache->set($key, $result, false, Shipserv_Oracle::MEMCACHE_TTL);

		 return $result;

	}

	/**
	* Round up date to next hour, to keep cacheing
	*/
	protected function roundFilterDate( $filterDate )
	{
		if ($filterDate) {
			if ($filterDate instanceof DateTime) {
			return $filterDate->format('Y-m-d H:') . '59:59';
			} else {
				return null;
			}
		} else {
			return null;
		}

	}

}