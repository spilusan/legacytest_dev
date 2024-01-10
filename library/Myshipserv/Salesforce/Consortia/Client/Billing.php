<?php
/**
 * A client managing Consortia bill calculation and pushing to Salesforce
 *
 * @author  Yuriy Akopov
 * @date    2018-01-08
 * @story   DEV-1172
 */
class Myshipserv_Salesforce_Consortia_Client_Billing extends Myshipserv_Salesforce_Consortia_Client_Abstract
{
    /**
     * Number of days after which we will still be charging / discounting when an older order is replaced in
     * the billing period
     */
    const REFUND_PERIOD_DAYS = 180;

    /**
     * ID of the consortia to bill
     *
     * @var int
     */
    protected $consortiaId = null;

    /**
     * IDs of suppliers to bill
     *
     * @var array
     */
    protected $supplierIds = array();

    /**
     * Start date of the billing period, inclusive
     *
     * @var DateTime|null
     */
    protected $dateStart = null;

    /**
     * End date of the billing period, exclusive
     *
     * @var DateTime|null
     */
    protected $dateEnd = null;

    /**
     * Returns SSREPORT2 database as this is where the billing ETL operates
     *
     * @return Zend_Db_Adapter_Oracle
     */
    public function getDb()
    {
        return Shipserv_Helper_Database::getSsreport2Db();
    }

    /**
     * Returns the month this object was created to bill for
     *
     * @return string
     */
    public function getBilledMonth()
    {
        return $this->dateStart->format('Y-m');
    }

    /**
     * Packs bindings for queries returning billing results
     *
     * @param   array   $mergeWith
     *
     * @return array
     */
    protected function getBillingQueryParams(array $mergeWith = array())
    {
        return array_merge(
            $mergeWith,
            array(
                'dateStart' => $this->getDateStart()->format('Y-m-d H:i:s'),
                'dateEnd'   => $this->getDateEnd()->format('Y-m-d H:i:s')
            )
        );
    }

    /**
     * @return DateTime
     */
    public function getDateStart()
    {
        return $this->dateStart;
    }

    /**
     * @return DateTime
     */
    public function getDateEnd()
    {
        return $this->dateEnd;
    }


    /**
     * Initialises the synchronisation session
     *
     * @param   Myshipserv_Logger_File  $logger
     * @param   string                  $month              YYYY-MM format or NULL for the current month
     * @param   int                     $consortiaId
     * @param   int|array               $supplierIds
     * @param   Shipserv_User           $user
     *
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    public function __construct(Myshipserv_Logger_File $logger, $month = null, $consortiaId = null, $supplierIds = null, Shipserv_User $user = null)
    {
        if (is_null($consortiaId)) {
            $logger->log("Consortia billing initialised for all consortia");
        } else {
            $this->consortiaId = $consortiaId;
            $logger->log("Consortia billing initialised for consortia " . $consortiaId);
        }

        if (is_null($supplierIds) or empty($supplierIds)) {
            $this->supplierIds = array();
            $logger->log("Consortia billing initialised to include all suppliers");

        } else if (!is_array($supplierIds)) {
            $this->supplierIds = array($supplierIds);
            $logger->log("Consortia billing initialised for supplier " . $supplierIds);

        } else {
            $logger->log("Consortia billing initialised for " . count($supplierIds) . " suppliers");
            $this->supplierIds = $supplierIds;
        }

        if (is_null($month)) {
            // default is ongoing month
            $now = new DateTime();
            $this->dateStart = new DateTime($now->format('Y-m') . '-01');

        } else if (preg_match('/^\d\d\d\d\-\d\d$/', $month)) {
            // if the billing period is provided, both dates are expected
            $this->dateStart = new DateTime($month . '-01');

        } else {
            throw new Myshipserv_Consortia_Validation_Exception(
                "Incomplete billing period provided for consortia billing"
            );
        }

        // set the end date of the interval to cover the whole month
        $this->dateEnd = clone($this->dateStart);
        $this->dateEnd->modify('+1 month');

        $logger->log(
            "Consortia billing initialised for the period " .
            "from " . $this->dateStart->format('Y-m-d H:i:s') .
            " to " . $this->dateEnd->format('Y-m-d H:i:s')
        );

        parent::__construct($logger, $user);
    }

    /**
     * Returns adjusted consortia bill items (original value minus the compensation for earlier billed for and replaced
     * orders)
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    public function getGroupedBill()
    {
        $billable = $this->getGroupedBilledPeriodOrders();
        // var_dump($billable);
        $valueRep = $this->getGroupedReplacedInBilledPeriodOrders();
        // var_dump($valueRep);
        // die;

        // this list needs to be aligned with the results of the queries in the methods called above
        $fieldsToMatch = array(
            'CONSORTIA_ID',
            'SPB_TNID',
            'RATE_SALESFORCE_ID',
            'RATE_TYPE',
            'RATE_VALUE_ORDER'
        );

        // loop through the orders that were replaced and so which value needs to be deducted from the bill
        foreach ($valueRep as $rowRep) {
            $foundRow = false;
            // loop through the bill to find a line with a matching supplier ID and rate values
            foreach ($billable as $indexBill => $rowBill) {
                $matched = true;
                // compare the fields in the replacement and the bill lines
                foreach ($fieldsToMatch as $field) {
                    if ($rowBill[$field] !== $rowRep[$field]) {
                        // it's enough for at least one comparison to fail, so we can stop here straight away
                        $matched = false;
                        break;
                    }
                }

                if ($matched) {
                    // deduct the value of the replacement order from the found matched bill line
                    $foundRow = true;

                    $billable[$indexBill]['BILL'] -= $rowRep['BILL'];

                    switch ($rowRep['RATE_TYPE']) {
                        case Shipserv_Oracle_Consortia_Supplier::RATE_PO:
                            $billable[$indexBill]['GMV'] -= $rowRep['GMV'];
                            break;

                        case Shipserv_Oracle_Consortia_Supplier::RATE_UNIT:
                            $billable[$indexBill]['TOTAL_UNIT_COUNT'] -= $rowRep['TOTAL_UNIT_COUNT'];
                            break;

                        default:
                            throw new Myshipserv_Consortia_Validation_Exception(
                                "Unknown rate type in the bill row " . implode("~", $rowRep)
                            );
                    }

                    break;
                }
            }

            if (!$foundRow) {
                // haven't found a corresponding row in the bill, so need to add a new line to it
                // since in Consortia model replacement orders are inheriting rates of their original
                // there is always expected to be a matching rate row (unlike in VBP)
                throw new Myshipserv_Consortia_Validation_Exception(
                    "Error processing replaced Consortia order: " . implode("~", $rowRep)
                );
            }
        }

        return $billable;
    }

    /**
     * Returns billing query constraint for suppliers
     *
     * @param   string  $prefix
     *
     * @return string
     */
    protected function getSupplierConstraint($prefix = 'ord')
    {
        if (empty($this->supplierIds)) {
            return '';
        } else {
            return $this->getDb()->quoteInto(' AND ' . $prefix . '.spb_branch_code IN (?)', $this->supplierIds);
        }
    }

    /**
     * Returns billing query constraint for consortia
     *
     * @param   string  $prefix
     *
     * @return string
     */
    protected function getConsortiaConstraint($prefix = 'ord')
    {
        if (is_null($this->consortiaId)) {
            return '';
        } else {
            return $this->getDb()->quoteInto(' AND ' . $prefix . '.ord_con_id = ?', $this->consortiaId);
        }
    }

    /**
     * Returns the query for individual orders placed in the billed period
     *
     * @return string
     */
    protected function getBilledPeriodOrdersQuery()
    {
        return "
			SELECT * FROM(
			WITH xtable AS(
			SELECT *
				FROM
				(
					-- Combines all Data from Order Submission and Order Decline
					-- Calculates for the estimated credits earned by multiplying adjusted cost with corresponding rate
					Select EVENT_STS AS EVENT_STATUS, ORD_GROUP_ID AS TRANSACTION_GROUP_ID, ORD_INTERNAL_REF_NO AS SHIPSERV_REF_NO, to_char(ORD_SUBMITTED_DATE, 'yyyy-mm-dd') AS SUBMITTED_DATE, to_char(OLD_SUBMITTED_DATE, 'yyyy-mm-dd') AS PREVIOUS_DOC_SUBMITTED_DATE, to_char(ORIGINAL_SUBMITTED_DATE, 'yyyy-mm-dd') AS ORDER_INITIAL_SUBMITTED_DATE, ORD_DECLINED_DATE AS DECLINED_DATE, NEW_DOC_TYPE AS Doc_Type, 
						    SPB_BRANCH_CODE AS SUPPLIER_TNID, spb_name AS SUPPLIER_NAME, PARENT_BRANCH_CODE, PARENT_NAME, BYB_BRANCH_CODE AS BUYER_TNID, byb_name AS BUYER_NAME, UPPER(ORD_VESSEL_NAME) AS VESSEL_NAME, ORD_IMO_NO AS IMO_NO, ORD_REF_NO AS PO_REF_NO, POC_REF_NO, ORD_TOTAL_COST_DISCOUNTED AS TOTAL_COST,
							ORD_CURRENCY AS ORDER_CURRENCY,
							CURRENCY_RATE AS CURRENCY_RATE,
							NEW_VALUE AS TOTAL_COST_IN_USD, OLD_VALUE AS TOTAL_PREVIOUS_COST, ADJUSTED_COST AS ADJUSTED_COST, (ADJUSTED_COST *(TRANSACTION_RATE/100)) AS CREDITS, TRANSACTION_TYPE AS TRANSACTION_TYPE, TRANSACTION_RATE AS RATE, ord_con_csb_id
					From 
					(
						-- Identifies category of transaction based on different transaction type (i.e. Free, VBP, AP, Consortia and BC)
						-- Identifies applicable rate for the transaction
						Select EVENT_STS, NEW_VALUE, OLD_VALUE, (NEW_VALUE-OLD_VALUE) AS ADJUSTED_COST, NEW_DOC_TYPE, OLD_DOC_TYPE, ORD_SUBMITTED_DATE, ORD_DECLINED_DATE, OLD_SUBMITTED_DATE,
						ORD_INTERNAL_REF_NO, spb_interface,
						parent_type,
						spb_is_paying,SPB_BRANCH_CODE, spb_name, PARENT_BRANCH_CODE, PARENT_NAME, BYB_BRANCH_CODE, byb_name, ORD_GROUP_ID, ORD_VESSEL_NAME, ORD_IMO_NO, ORD_REF_NO, POC_REF_NO, ORD_TOTAL_COST,
						ORD_TOTAL_COST_DISCOUNTED,
						ORD_CURRENCY,
						CURRENCY_RATE,
						CURRENCY_DATE,
						ORIGINAL_SUBMITTED_DATE,ORD_SBR_ID,
						CASE ORD_IS_CONSORTIA
							WHEN    1
							THEN    'CONSORTIA'
							ELSE
							CASE    ORD_IS_BUYERCONNECT
								WHEN    1
								THEN    'BUYERCONNECT'
								ELSE     
								CASE    
									WHEN    ORD_SBR_RATE_VALUE > 0
									THEN    
									CASE ORD_SBR_RATE_STD
										WHEN    1
										THEN    'STANDARD'
										ELSE    'TARGET'
									END
									ELSE    'FREE GMV'
								END    
							END
						END AS TRANSACTION_TYPE,
						CASE ORD_IS_CONSORTIA
							WHEN    1
							THEN    ORD_CON_RATE_VALUE
							ELSE
							CASE    ORD_IS_BUYERCONNECT
								WHEN    1
								THEN    0
								ELSE    
								CASE    ORD_SBR_RATE_STD
									WHEN    1
									THEN    ORD_SBR_RATE_VALUE
									WHEN    0
									THEN    ORD_SBR_RATE_VALUE
									ELSE    0
								END    
							END
						END AS TRANSACTION_RATE,
						ORD_INVALID_TXN,
						ord_con_csb_id,
						ZERO_PO_COUNT,
						TOTAL_PO_COUNT, rn
					FROM 
					(
						-- Gets all Orders based on Submission Date
						-- Identifies if document is original or replacement
						Select 
								CASE
								  WHEN ord.ORD_DECLINED_DATE IS NOT NULL AND ord.ORD_DECLINED_DATE=ord.ORD_SUBMITTED_DATE
								  THEN 0
								  ELSE ord.ORD_TOTAL_COST_DISCOUNTED_USD 
								END AS NEW_VALUE, 
								NVL(prOrd.ORD_TOTAL_COST_DISCOUNTED_USD, 0) AS OLD_VALUE,
								ord.DOC_TYPE AS NEW_DOC_TYPE, prOrd.DOC_TYPE AS OLD_DOC_TYPE,
								CASE
									WHEN ord.ORD_DECLINED_DATE IS NOT NULL AND ord.ORD_STATUS!='CAN' AND ord.ORD_DECLINED_DATE=ord.ORD_SUBMITTED_DATE
									THEN 'DECLINED'
									WHEN ord.ORD_STATUS='CAN' AND ord.ORD_DECLINED_DATE=ord.ORD_SUBMITTED_DATE
									THEN 'CANCELLED'
									WHEN ord.ORD_INTERNAL_REF_NO=ord.ORD_GROUP_ID AND ord.DOC_TYPE='PO'
									THEN 'ORIGINAL'
									WHEN ord.DOC_TYPE='POC'
									THEN 'CONFIRMED'
									ELSE 'REPLACEMENT'
								END AS EVENT_STS,
								ord.ORD_SUBMITTED_DATE,
								prOrd.ord_submitted_date AS OLD_SUBMITTED_DATE, 
								ord.ORD_DECLINED_DATE,
								ord.ORD_INTERNAL_REF_NO, prOrd.ORD_INTERNAL_REF_NO OLD_INTERNAL_REF_NO, 
								spb.spb_interface,
								spb.parent_type,
								spb.spb_is_paying,
								ord.SPB_BRANCH_CODE, 
								spb.SPB_NAME,
								ord.BYB_BRANCH_CODE, 
								byb.byb_name,
								btr1.PARENT_BRANCH_CODE, btr1.PARENT_NAME, 
								ord.ORD_GROUP_ID,
								ORD.ORD_VESSEL_NAME,
								ORD.ORD_IMO_NO,
								CASE ORD.DOC_TYPE
									WHEN 'POC'
									THEN initialPO.ORD_REF_NO
									ELSE ORD.ORD_REF_NO
								END AS ORD_REF_NO,
								CASE ORD.DOC_TYPE
									WHEN 'POC'
									THEN ORD.ORD_REF_NO
									ELSE ''
								END AS POC_REF_NO,
								ORD.ORD_TOTAL_COST,
								ORD.ORD_TOTAL_COST_DISCOUNTED,
								ORD.ORD_CURRENCY,
								ORD.CURRENCY_RATE,
								ORD.CURRENCY_DATE,
								poOrder.ORD_SBR_ID,
								poOrder.ORD_SBR_RATE_VALUE,
								poOrder.ORD_SBR_RATE_STD,
								poOrder.ORD_CON_RATE_VALUE,
								poOrder.ORD_SUBMITTED_DATE AS ORIGINAL_SUBMITTED_DATE,
								ORD.ORD_IS_CONSORTIA,
								ORD.ORD_IS_BUYERCONNECT,
								ROW_NUMBER() OVER (PARTITION BY ord.SPB_BRANCH_CODE, ord.ORD_INTERNAL_REF_NO, ord.ord_submitted_date ORDER BY prOrd.ord_submitted_date DESC) AS rn,
								ord.ORD_INVALID_TXN,
								ord.ord_con_csb_id,
								CASE
								  WHEN ord.DOC_TYPE='PO' AND (ord.ORD_TOTAL_COST is null OR ord.ORD_TOTAL_COST=0)
								  THEN 1
								  ELSE 0
								END AS ZERO_PO_COUNT,
								CASE
								  WHEN ord.DOC_TYPE='PO' AND (ord.ORD_INTERNAL_REF_NO!=ord.ORD_ORIGINAL_NO OR ord.ORD_ORIGINAL_NO is NULL)
								  THEN 1
								  ELSE 0
								END AS TOTAL_PO_COUNT
						from billable_po ord
						inner join buyer byb on byb.byb_branch_code=ord.byb_branch_code
						inner join buyer btr on byb.parent_branch_code=btr.byb_branch_code
						inner join buyer btr1 on btr.parent_branch_code=btr1.byb_branch_code
						inner join supplier spb on spb.spb_branch_code=ord.spb_branch_code
						inner join ord poOrder on poOrder.ORD_INTERNAL_REF_NO=ord.ORD_INTERNAL_REF_NO
						inner join billable_po initialPO on initialPO.ORD_INTERNAL_REF_NO=ord.ORD_GROUP_ID and initialPO.DOC_TYPE='PO'
						FULL Outer Join billable_po prOrd 
						on (prOrd.ord_group_id=ord.ord_group_id) and (((prOrd.ord_submitted_date<ord.ord_submitted_date OR (prOrd.ORD_INTERNAL_REF_NO<ord.ORD_INTERNAL_REF_NO AND prOrd.ord_submitted_date=ord.ord_submitted_date)) and (prOrd.ord_submitted_date>=(ord.ord_submitted_date-365))))
						where ord.ord_submitted_date >= TO_DATE(:dateStart, 'YYYY-MM-DD HH24:MI:SS')
                        AND ord.ord_submitted_date < TO_DATE(:dateEnd, 'YYYY-MM-DD HH24:MI:SS') and (ord.ord_group_id is not null) AND (ord.ORD_INVALID_TXN=0) and ord.ord_is_consortia=1
						" . $this->getSupplierConstraint() . "
						" . $this->getConsortiaConstraint() . "
					) q
					WHERE rn=1
					)
			) 
			) 
			SELECT ord.*, csb.csb_con_internal_ref_no as CONSORTIA_TNID, csb.csb_sf_source_id as SUPPLIER_AGREEMENT from xtable ord
			INNER JOIN consortia_supplier@livedb_link.shipserv.com csb on ord.ord_con_csb_id=csb.csb_internal_ref_no
			WHERE TRANSACTION_TYPE='CONSORTIA')
        "
        ;
    }
	
	/**
     * Returns the query for grouped orders by supplier in the billed period
     *
     * @return string
     */
    protected function getGroupedBilledPeriodQuery()
    {
        return "
			SELECT * FROM(
			WITH xtable AS(
			SELECT *
				FROM
				(
					-- Combines all Data from Order Submission and Order Decline
					-- Calculates for the estimated credits earned by multiplying adjusted cost with corresponding rate
					Select EVENT_STS AS Event_Status, ORD_GROUP_ID AS Transaction_Group_Id, ORD_INTERNAL_REF_NO AS ShipServ_Ref_No, ORD_SUBMITTED_DATE AS Submitted_Date, ORD_DECLINED_DATE AS Declined_Date, OLD_SUBMITTED_DATE AS Previous_Doc_Submitted_Date, NEW_DOC_TYPE AS Doc_Type, 
							spb_interface AS Supplier_Interface, parent_type,
						spb_is_paying, SPB_BRANCH_CODE AS Supplier_TNID, spb_name AS Supplier_Name, PARENT_BRANCH_CODE, PARENT_NAME, BYB_BRANCH_CODE AS Buyer_TNID, byb_name AS Buyer_Name, UPPER(ORD_VESSEL_NAME) AS Vessel_Name, ORD_IMO_NO AS IMO_No, ORD_REF_NO AS PO_Ref_No, POC_REF_NO AS POC_Ref_No, ORD_TOTAL_COST_DISCOUNTED AS Total_Cost,
							ORD_CURRENCY AS Order_Currency,
							CURRENCY_RATE AS Currency_Rate,ORD_SBR_ID,
							ORIGINAL_SUBMITTED_DATE AS Order_Initial_Submitted_Date,
							NEW_VALUE AS Total_Cost_in_USD, OLD_VALUE AS Total_Previous_Cost, ADJUSTED_COST AS Adjusted_Cost, (ADJUSTED_COST *(TRANSACTION_RATE/100)) AS Credits, TRANSACTION_TYPE AS Transaction_Type, TRANSACTION_RATE AS Rate, ORD_INVALID_TXN,ord_con_csb_id,
							ZERO_PO_COUNT,
							TOTAL_PO_COUNT, rn
					From 
					(
						-- Identifies category of transaction based on different transaction type (i.e. Free, VBP, AP, Consortia and BC)
						-- Identifies applicable rate for the transaction
						Select EVENT_STS, NEW_VALUE, OLD_VALUE, (NEW_VALUE-OLD_VALUE) AS ADJUSTED_COST, NEW_DOC_TYPE, OLD_DOC_TYPE, ORD_SUBMITTED_DATE, ORD_DECLINED_DATE, OLD_SUBMITTED_DATE,
						ORD_INTERNAL_REF_NO, spb_interface,
						parent_type,
						spb_is_paying,SPB_BRANCH_CODE, spb_name, PARENT_BRANCH_CODE, PARENT_NAME, BYB_BRANCH_CODE, byb_name, ORD_GROUP_ID, ORD_VESSEL_NAME, ORD_IMO_NO, ORD_REF_NO, POC_REF_NO, ORD_TOTAL_COST,
						ORD_TOTAL_COST_DISCOUNTED,
						ORD_CURRENCY,
						CURRENCY_RATE,
						CURRENCY_DATE,
						ORIGINAL_SUBMITTED_DATE,ORD_SBR_ID,
						CASE ORD_IS_CONSORTIA
							WHEN    1
							THEN    'CONSORTIA'
							ELSE
							CASE    ORD_IS_BUYERCONNECT
								WHEN    1
								THEN    'BUYERCONNECT'
								ELSE     
								CASE    
									WHEN    ORD_SBR_RATE_VALUE > 0
									THEN    
									CASE ORD_SBR_RATE_STD
										WHEN    1
										THEN    'STANDARD'
										ELSE    'TARGET'
									END
									ELSE    'FREE GMV'
								END    
							END
						END AS TRANSACTION_TYPE,
						CASE ORD_IS_CONSORTIA
							WHEN    1
							THEN    ORD_CON_RATE_VALUE
							ELSE
							CASE    ORD_IS_BUYERCONNECT
								WHEN    1
								THEN    0
								ELSE    
								CASE    ORD_SBR_RATE_STD
									WHEN    1
									THEN    ORD_SBR_RATE_VALUE
									WHEN    0
									THEN    ORD_SBR_RATE_VALUE
									ELSE    0
								END    
							END
						END AS TRANSACTION_RATE,
						ORD_INVALID_TXN,
						ord_con_csb_id,
						ZERO_PO_COUNT,
						TOTAL_PO_COUNT, rn
					FROM 
					(
						-- Gets all Orders based on Submission Date
						-- Identifies if document is original or replacement
						Select 
								CASE
								  WHEN ord.ORD_DECLINED_DATE IS NOT NULL AND ord.ORD_DECLINED_DATE=ord.ORD_SUBMITTED_DATE
								  THEN 0
								  ELSE ord.ORD_TOTAL_COST_DISCOUNTED_USD 
								END AS NEW_VALUE, 
								NVL(prOrd.ORD_TOTAL_COST_DISCOUNTED_USD, 0) AS OLD_VALUE,
								ord.DOC_TYPE AS NEW_DOC_TYPE, prOrd.DOC_TYPE AS OLD_DOC_TYPE,
								CASE
									WHEN ord.ORD_DECLINED_DATE IS NOT NULL AND ord.ORD_STATUS!='CAN' AND ord.ORD_DECLINED_DATE=ord.ORD_SUBMITTED_DATE
									THEN 'DECLINED'
									WHEN ord.ORD_STATUS='CAN' AND ord.ORD_DECLINED_DATE=ord.ORD_SUBMITTED_DATE
									THEN 'CANCELLED'
									WHEN ord.ORD_INTERNAL_REF_NO=ord.ORD_GROUP_ID AND ord.DOC_TYPE='PO'
									THEN 'ORIGINAL'
									WHEN ord.DOC_TYPE='POC'
									THEN 'CONFIRMED'
									ELSE 'REPLACEMENT'
								END AS EVENT_STS,
								ord.ORD_SUBMITTED_DATE,
								prOrd.ord_submitted_date AS OLD_SUBMITTED_DATE, 
								ord.ORD_DECLINED_DATE,
								ord.ORD_INTERNAL_REF_NO, prOrd.ORD_INTERNAL_REF_NO OLD_INTERNAL_REF_NO, 
								spb.spb_interface,
								spb.parent_type,
								spb.spb_is_paying,
								ord.SPB_BRANCH_CODE, 
								spb.SPB_NAME,
								ord.BYB_BRANCH_CODE, 
								byb.byb_name,
								btr1.PARENT_BRANCH_CODE, btr1.PARENT_NAME, 
								ord.ORD_GROUP_ID,
								ORD.ORD_VESSEL_NAME,
								ORD.ORD_IMO_NO,
								CASE ORD.DOC_TYPE
									WHEN 'POC'
									THEN initialPO.ORD_REF_NO
									ELSE ORD.ORD_REF_NO
								END AS ORD_REF_NO,
								CASE ORD.DOC_TYPE
									WHEN 'POC'
									THEN ORD.ORD_REF_NO
									ELSE ''
								END AS POC_REF_NO,
								ORD.ORD_TOTAL_COST,
								ORD.ORD_TOTAL_COST_DISCOUNTED,
								ORD.ORD_CURRENCY,
								ORD.CURRENCY_RATE,
								ORD.CURRENCY_DATE,
								poOrder.ORD_SBR_ID,
								poOrder.ORD_SBR_RATE_VALUE,
								poOrder.ORD_SBR_RATE_STD,
								poOrder.ORD_CON_RATE_VALUE,
								poOrder.ORD_SUBMITTED_DATE AS ORIGINAL_SUBMITTED_DATE,
								ORD.ORD_IS_CONSORTIA,
								ORD.ORD_IS_BUYERCONNECT,
								ROW_NUMBER() OVER (PARTITION BY ord.SPB_BRANCH_CODE, ord.ORD_INTERNAL_REF_NO, ord.ord_submitted_date ORDER BY prOrd.ord_submitted_date DESC) AS rn,
								ord.ORD_INVALID_TXN,
								ord.ord_con_csb_id,
								CASE
								  WHEN ord.DOC_TYPE='PO' AND (ord.ORD_TOTAL_COST is null OR ord.ORD_TOTAL_COST=0)
								  THEN 1
								  ELSE 0
								END AS ZERO_PO_COUNT,
								CASE
								  WHEN ord.DOC_TYPE='PO' AND (ord.ORD_INTERNAL_REF_NO!=ord.ORD_ORIGINAL_NO OR ord.ORD_ORIGINAL_NO is NULL)
								  THEN 1
								  ELSE 0
								END AS TOTAL_PO_COUNT
						from billable_po ord
						inner join buyer byb on byb.byb_branch_code=ord.byb_branch_code
						inner join buyer btr on byb.parent_branch_code=btr.byb_branch_code
						inner join buyer btr1 on btr.parent_branch_code=btr1.byb_branch_code
						inner join supplier spb on spb.spb_branch_code=ord.spb_branch_code
						inner join ord poOrder on poOrder.ORD_INTERNAL_REF_NO=ord.ORD_INTERNAL_REF_NO
						inner join billable_po initialPO on initialPO.ORD_INTERNAL_REF_NO=ord.ORD_GROUP_ID and initialPO.DOC_TYPE='PO'
						FULL Outer Join billable_po prOrd 
						on (prOrd.ord_group_id=ord.ord_group_id) and (((prOrd.ord_submitted_date<ord.ord_submitted_date OR (prOrd.ORD_INTERNAL_REF_NO<ord.ORD_INTERNAL_REF_NO AND prOrd.ord_submitted_date=ord.ord_submitted_date)) and (prOrd.ord_submitted_date>=(ord.ord_submitted_date-365))))
						where ord.ord_submitted_date >= TO_DATE(:dateStart, 'YYYY-MM-DD HH24:MI:SS')
                        AND ord.ord_submitted_date < TO_DATE(:dateEnd, 'YYYY-MM-DD HH24:MI:SS') and (ord.ord_group_id is not null) AND (ord.ORD_INVALID_TXN=0) and ord.ord_is_consortia=1
						" . $this->getSupplierConstraint() . "
						" . $this->getConsortiaConstraint() . "
					) q
					WHERE rn=1
					)
			) 
			) 
			SELECT SUPPLIER_TNID, csb_con_internal_ref_no as CONSORTIA_TNID, csb_rate_type as RATE_TYPE, RATE, csb_sf_source_id as SUPPLIER_AGREEMENT, SUM(ADJUSTED_COST) AS GMV, 0 as TOTAL_UNIT_COUNT, (SUM(ADJUSTED_COST)*RATE) AS BILL
			from xtable ord
			INNER JOIN consortia_supplier@livedb_link.shipserv.com csb on ord.ord_con_csb_id=csb.csb_internal_ref_no
			WHERE TRANSACTION_TYPE='CONSORTIA'
			GROUP BY SUPPLIER_TNID, csb.csb_con_internal_ref_no, csb_rate_type, Rate, csb_sf_source_id)
        "
        ;
    }


    /**
     * Returns orders placed in the billed period in question under Consortia model
     *
     * @return array
     */
    public function getBilledPeriodOrders()
    {
        $sql = $this->getBilledPeriodOrdersQuery();

        $db = $this->getDb();
        $rows = $db->fetchAll($sql, $this->getBillingQueryParams());

        return $rows;
    }

    /**
     * Returns total bill for the orders placed in the billed period
     *
     * @return array
     */
    protected function getGroupedBilledPeriodOrders()
    {
        $sql = $this->getGroupedBilledPeriodQuery();

        $db = $this->getDb();
        $rows = $db->fetchAll($sql, $this->getBillingQueryParams());

        return $rows;
    }

    /**
     * @throws  Myshipserv_Salesforce_Exception
     */
    public function sync()
    {
        throw new Myshipserv_Salesforce_Exception(
            "This method is not supposed to be called directly as the billing client does not put data directly " .
            " in Salesforce"
        );
    }
}