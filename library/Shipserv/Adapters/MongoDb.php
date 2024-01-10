<?php
class Shipserv_Adapters_MongoDb extends Shipserv_Object
{
	const SERVER = '10.59.19.50';	
	const DB_NAME = 'shipserv';
	public function __construct()
	{
		try
		{
			$conn = new Mongo(self::SERVER);
			
			try
			{
				$this->db = $conn->selectDB(self::DB_NAME);
			}
			catch( Exception $e)
			{
				// create shipserv coll
				$this->db = $conn->shipserv;
				throw new Exception("Cannot select shipserv database");
			}
		}
		catch(Exception $ex)
		{
			throw new Exception("Cannot connect to mongo db");
		}
	}
	
	public function importSearchStatistic()
	{
		$start = '21-FEB-07';
		//Myship
		$this->db->dropCollection("searches");
		$collection = $this->db->createCollection("searches");
		// remove all 
		$collection->remove();
		$collection->ensureIndex(array("keyword" => 1));
		
		do
		{
			$sql = "SELECT TO_CHAR(TO_DATE('$start')+7, 'DD-MON-YY') as NEXT_START FROM DUAL";
			$nextStart = $this->getDb()->fetchAll($sql);
			$nextStart = $nextStart[0]['NEXT_START'];
			
			echo "--------------------------------------------------------------\n";
			echo "--- Importing data from $start to $nextStart\n";
			echo "--------------------------------------------------------------\n";
			
			$sql = "
				SELECT 
					PST_SEARCH_TEXT, 
					PST_SEARCH_DATE_TIME
				FROM 
					pages_statistics 
				WHERE 
					pst_search_date_time BETWEEN TO_DATE('$start') AND TO_DATE('$start')+7 
					AND pst_search_text IS NOT null
					  AND LOWER(pst_browser) NOT LIKE '%bot%'
					  AND LOWER(pst_browser) NOT LIKE '%crawler%'
					  AND LOWER(pst_browser) NOT LIKE '%slurp%'
					  AND LOWER(pst_browser) NOT LIKE '%libwww-perl%'
					  AND LOWER(pst_browser) NOT LIKE '%webcorp%'
					
										
			";
			$rows =$this->getDb()->fetchAll($sql);

			foreach( $rows as $row )
			{
				$count++;
				$collection->insert(array("keyword" => $row['PST_SEARCH_TEXT'], "date" => $row['PST_SEARCH_DATE_TIME']));
				if( $count % 1000 == 0 )
				{
					echo $count . " inserted \n";
				}		
			}
			
			// move to next month
			$start = $nextStart;
		}
		while ( strstr($start, "JUL-12") === false );
		
	}

	public function importPurchaseOrder()
	{
		$start = '05-AUG-11';
		$start = '01-AUG-11';
		$periodInDays = 1;
		
		$this->db->dropCollection("purchaseOrders");
		$collection = $this->db->createCollection("purchaseOrders");
		// remove all 
		$collection->remove();
		$collection->ensureIndex(array("poId" => 1));
		
		//Use local standby
		$db = $this->getLocalStandByDb();
		
		do
		{
			$sql = "SELECT TO_CHAR(TO_DATE('$start')+$periodInDays, 'DD-MON-YY') as NEXT_START FROM DUAL";
			
			$nextStart = $db->fetchAll($sql);
			$nextStart = $nextStart[0]['NEXT_START'];
			
			echo "---------------------------------------------------------------------------------------------------------------\n";
			echo "--- Get all purchase orders from $start to $nextStart";
			
			echo "...";
			$sql = "
				SELECT
					ORD_INTERNAL_REF_NO,
					ORD_CURRENCY,
					ORD_VESSEL_NAME,
					ORD_TOTAL_COST,
					ORD_DATE_TIME
				FROM
					purchase_order
				WHERE
					ORD_UPDATED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:startDate) +1
			";
			$pos = $db->fetchAll($sql, array("startDate" => $start));
			echo " found " . count($pos) . " PURCHASE ORDERS(s)\n";
			echo "---------------------------------------------------------------------------------------------------------------\n\n";
			$poCount = 1;
			foreach( $pos as $po)
			{
				$count++;
				$collection->insert(array(	"poId" => $row['ORD_INTERNAL_REF_NO'],
											"currency" => $row['ORD_CURRENCY'],
											"vesselName" => $row['ORD_VESSEL_NAME'],
											"totalCost" => $row['ORD_TOTAL_COST'],
										  	"date" => $row['ORD_DATE_TIME']));
				
			}
			echo $count . " inserted \n\n";
			$start = $nextStart;
		}
		while ( strstr($start, "29-JUL-12") === false );		
	}
	
	public function importPurchaseOrderLineItems()
	{
		$start = '20-SEP-09';
		//$start = '05-SEP-11';
		//$start = '05-AUG-11';
		//$start = '01-MAY-12';
		
		$start = '20-OCT-11';
		
		$periodInDays = 1;
		
		$this->db->dropCollection("lineItems");
		$collection = $this->db->createCollection("lineItems");
		// remove all 
		$collection->remove();
		
		$collection->ensureIndex(array("poId" => 1));
		
		$db = $this->getLocalStandByDb();

		do
		{
			$sql = "SELECT TO_CHAR(TO_DATE('$start')+$periodInDays, 'DD-MON-YY') as NEXT_START FROM DUAL";
			$nextStart = $db->fetchAll($sql);
			$nextStart = $nextStart[0]['NEXT_START'];
			
			echo "---------------------------------------------------------------------------------------------------------------\n";
			echo "--- Get all purchase orders from $start to $nextStart";
			
			echo "...";
			$sql = "
				SELECT
					ORD_INTERNAL_REF_NO
				FROM
					purchase_order
				WHERE
					ORD_UPDATED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:startDate) +1
			";
			$pos = $db->fetchAll($sql, array("startDate" => $start));
			echo " found " . count($pos) . " po(s)\n";
			echo "---------------------------------------------------------------------------------------------------------------\n\n";
			$poCount = 1;
			foreach( $pos as $po)
			{
				echo $poCount++ . " of " . count($pos) . " -- Importing line-items for PO id: " . $po['ORD_INTERNAL_REF_NO'] . "\n";
				$sql = "
					SELECT 
						oli_quantity,
						oli_order_internal_ref_no,
						oli_id_code,
						oli_unit_cost,
						OLI_TOTAL_LINE_ITEM_COST,
						oli_desc,
						oli_created_date 
					FROM 
						order_line_item 
					WHERE 
						oli_order_internal_ref_no = :poId
						";
				
				$rows =$db->fetchAll($sql, array("poId" => $po['ORD_INTERNAL_REF_NO']));
				$count = 0;
				foreach( $rows as $row )
				{
					$count++;
					$collection->insert(array(	"poId" => $row['OLI_ORDER_INTERNAL_REF_NO'],
												"quantity" => $row['OLI_QUANTITY'],
												"unitCost" => $row['OLI_UNIT_COST'],
												"totalCost" => $row['OLI_TOTAL_LINE_ITEM_CODE'],
												"productCode" => $row['OLI_ID_CODE'],
												"description" => $row['OLI_DESC'],
											  	"date" => $row['OLI_CREATED_DATE']));
				}
				echo " ... " . $count . " line items inserted \n\n";
			}
			$start = $nextStart;
		}
		while ( strstr($start, "21-NOV-12") === false );
		
	}
        
        
        
        public function importRFQLineItems()
	{
		$start = '20-MAR-09';
		//$start = '05-SEP-11';
		//$start = '05-AUG-11';
		//$start = '01-MAY-12';
		
		$start = '20-MAR-12';
		
		$periodInDays = 1;
		
		$this->db->dropCollection("lineItems");
		$collection = $this->db->createCollection("rfqLineItems");
		// remove all 
		$collection->remove();
		
		$collection->ensureIndex(array("rfqId" => 1));
		
		$db = $this->getLocalStandByDb();

		do
		{
			$sql = "SELECT TO_CHAR(TO_DATE('$start')+$periodInDays, 'DD-MON-YY') as NEXT_START FROM DUAL";
			$nextStart = $db->fetchAll($sql);
			$nextStart = $nextStart[0]['NEXT_START'];
			
			echo "---------------------------------------------------------------------------------------------------------------\n";
			echo "--- Get all purchase orders from $start to $nextStart";
			
			echo "...";
			$sql = "
				SELECT
					RQR_RFQ_INTERNAL_REF_NO
				FROM
					RFQ_QUOTE_RELATION
				WHERE
					RQR_SUBMITTED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:startDate) +1
			";
			$rfqs = $db->fetchAll($sql, array("startDate" => $start));
			echo " found " . count($rfqs) . " rfq(s)\n";
			echo "---------------------------------------------------------------------------------------------------------------\n\n";
			$rfqCount = 1;
			foreach( $rfqs as $rfq)
			{
				echo $rfqCount++ . " of " . count($rfqs) . " -- Importing line-items for RFQ id: " . $rfq['RQR_RFQ_INTERNAL_REF_NO'] . "\n";
				$sql = "
					SELECT 
						rfl_quantity,
						rfl_rfq_internal_ref_no,
						rfl_confg_desc,
                                                rfl_product_desc
					FROM 
						rfq_line_item 
					WHERE 
						rfl_rfq_internal_ref_no = :rfqId
						";
				
				$rows =$db->fetchAll($sql, array("rfqId" => $rfq['RQR_RFQ_INTERNAL_REF_NO']));
				$count = 0;
				foreach( $rows as $row )
				{
					$count++;
					$collection->insert(array(	"rfqId" => $row['RFL_RFQ_INTERNAL_REF_NO'],
												"quantity" => $row['RFL_QUANTITY'],
												"description" => $row['RFL_CONFG_DESC'],
											  	"Description2" => $row['RFL_PRODUCT_DESC']));
				}
				echo " ... " . $count . " line items inserted \n\n";
			}
			$start = $nextStart;
		}
		while ( strstr($start, "21-NOV-12") === false );
		
	}

        
        
        

	public function exportSearchStatisticData()
	{
		$mapSyntax = <<<EOT
function(){
	if( !this.description ) return;
	
	emit(this.keyword, 1);
}
EOT;

		$reduceSyntax = <<<EOT
function(previous, current){
	var count = 0;
	
	for (index in current ){
		count += current[index];
	}
	
	return count;
}
EOT;
		
		$map = new MongoCode($mapSyntax);
		$reduce = new MongoCode($reduceSyntax);
		
		$searchStat = $this->db->command(
			array(
				"mapreduce" => "searches",
				"map" => $map,
				"reduce" => $reduce,
				"query" => array("type" => "searchStat"),
				"out" => array("merge" => "eventCounts")
			)
		);
		
		///$keywords = $this->db->selectCollection($searchStat['result'])->find();
		
		var_dump( $searchStat );
	}
	
	private static function getLocalStandByDb()
   	{
   		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
   		return $resource->getDb('standbydblocal');
   	}
	
   

}