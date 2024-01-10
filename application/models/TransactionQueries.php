<?php

/**
 * Class for returning query variation for Transaction Monitor
 *
 * Class Application_Model_TransactionQueries
 */
class Application_Model_TransactionQueries
{

    protected $requiredParams;
    protected $subQuery;
    protected $sqlEmbeddedParams;

    /**
     * Application_Model_TransactionQueries constructor.
     *
     * @param string $type
     * @param array $sqlEmbeddedParams
     * @throws Myshipserv_Exception_MessagedException
     */
    public function __construct($type, array $sqlEmbeddedParams)
    {

        $includeChildren = $buyerbranch = null;
        $this->sqlEmbeddedParams = $sqlEmbeddedParams;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        if ($includeChildren === null || $buyerbranch === null) {
            throw new Myshipserv_Exception_MessagedException('Not all variables set in sqlEmpeddeParams. Require $includeChildren, $buyerbranch');
        }

        switch($type) {
            case 'all-req':
                $this->requiredParams = array(':fromdate', ':todate', ':supplierbranch', ':suppliertype', ':status', ':mtmlbuyer', ':vessel', ':reference', ':pagestart', ':pageend', ':buyercontact', ':qotdeadline', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery =
                    $this->_getRfqQuery() . ' UNION ALL ' .
                    $this->_getPoQuery()  . ' UNION ALL ' .
                    $this->_getQotQuery() . ' UNION ALL ' .
                    $this->_getPocQuery() . ' UNION ALL ' .
                    $this->_getInvQuery() . ' UNION ALL ' .
                    $this->_getReqQuery();
                break;
            case 'all':
                $this->requiredParams = array(':fromdate', ':todate', ':supplierbranch', ':suppliertype', ':status', ':mtmlbuyer', ':vessel', ':reference', ':pagestart', ':pageend', ':buyercontact', ':qotdeadline', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery =
                    $this->_getRfqQuery() . ' UNION ALL ' .
                    $this->_getPoQuery()  . ' UNION ALL ' .
                    $this->_getQotQuery() . ' UNION ALL ' .
                    $this->_getPocQuery() . ' UNION ALL ' .
                    $this->_getInvQuery();
                break;
            case 'req':
                $this->requiredParams = array(':mtmlbuyer', ':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':buyercontact', ':urgent', ':variance',  ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery = $this->_getReqQuery();
                break;
            case 'rfq':
                $this->requiredParams = array(':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':supplierbranch', ':suppliertype', ':status', ':buyercontact', ':qotdeadline', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery = $this->_getRfqQuery();
                break;
            case 'po':
                $this->requiredParams = array(':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':supplierbranch', ':status', ':suppliertype', ':buyercontact', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery = $this->_getPoQuery();
                break;
            case 'sent':
                $this->requiredParams = array(':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':supplierbranch', ':status', ':suppliertype', ':buyercontact', ':qotdeadline', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery =
                    $this->_getRfqQuery() . ' UNION ALL ' .
                    $this->_getPoQuery();
                break;
            case 'qot':
                $this->requiredParams = array(':mtmlbuyer', ':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':supplierbranch', ':suppliertype', ':buyercontact', ':qotdeadline', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery = $this->_getQotQuery();
                break;
            case 'poc':
                $this->requiredParams = array(':mtmlbuyer', ':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':supplierbranch', ':suppliertype', ':buyercontact', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery = $this->_getPocQuery();
                break;
            case 'inv':
                $this->requiredParams = array(':mtmlbuyer', ':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':supplierbranch', ':suppliertype', ':buyercontact', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery = $this->_getInvQuery();
                break;
            case 'recv':
                $this->requiredParams = array(':mtmlbuyer', ':buyerbranch', ':fromdate', ':todate', ':vessel', ':reference', ':pagestart', ':pageend', ':supplierbranch', ':suppliertype', ':buyercontact', ':qotdeadline', ':urgent', ':variance', ':attachment', ':noturgent', ':novariance', ':noattachment');
                $this->subQuery =
                    $this->_getQotQuery() . ' UNION ALL ' .
                    $this->_getPocQuery();
                break;
            default:
                throw new Myshipserv_Exception_MessagedException('Invalid document type definition', 500);
                break;
        }
    }

    /**
     * Get the defined query for the specific type set in constructor
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->_getBaseQuery($this->subQuery);
    }

    /**
     * Get the defined count query for the specific type set in constructor
     *
     * @return string
     */
    public function getCountQuery()
    {
        return $this->_getBaseQuery($this->subQuery, true);
    }

    /**
     * Get the query for the vessel drop down list
     *
     * @return string
     */
    public function getVesselQuery()
    {
        $sql = 'WITH
                    basequery as (' . $this->_getBaseQuery($this->subQuery, false, false) . ")
                SELECT DISTINCT
                    UPPER( CLEAN_VESSEL_NAME( NVL( vessel_name, 'NO VESSEL NAME' ))) AS vessel_name_full,
                    UPPER( CLEAN_VESSEL_NAME( NVL( vessel_name, 'NO VESSEL NAME' ))) AS vessel_name
                FROM
                    basequery
                ORDER BY 
                    vessel_name";

        return $sql;
    }

    /**
     * Get the query for the supplier drop down list
     *
     * @return string
     */
    public function getSupplierQuery()
    {
        $sql = 'WITH
                    basequery as (' . $this->_getBaseQuery($this->subQuery, false, false) . ')
                SELECT DISTINCT
                    spb_branch_code,
                    spb_name
                FROM
                    basequery
                ORDER BY 
                    spb_name';
        return $sql;
    }

    /**
     * Get the list of required parameters for the specific type set in constructor
     *
     * @return array
     */
    public function getRequiredParams()
    {
        return $this->requiredParams;
    }

    /**
     * Get the list of required parameters for the count query for the  specific type set in constructor
     *
     * @return array
     */
    public function getRequiredCountParams()
    {
        $params = $this->requiredParams;
        unset($params[array_search(':pagestart', $params)]);
        unset($params[array_search(':pageend', $params)]);
        return $params;
    }

    /**
     * Get the base query
     * need injecting the sub queries for the selected types
     *
     * @param string $injectQuery
     * @param bool $count
     * @param bool $limit
     * @return string
     */
    protected function _getBaseQuery($injectQuery, $count = false, $limit = true)
    {
        $sortfield = $sortdirection = null;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        $sql = "
            SELECT" . PHP_EOL;
        if ($count === true) {
            $sql .= 'COUNT( 1 ) ALL_CNT' . PHP_EOL;
        } else {
            $sql .= '
                    t.*,
                    (
                        SELECT
                            byb_name
                        FROM
                            buyer
                        WHERE
                            byb_branch_code = t.byb_branch_code
                    ) byb_name ' . PHP_EOL;
        }
        $sql .= "
            FROM
              (
                SELECT ROWNUM AS rn,
                 t.* 
                 FROM (
            
                    SELECT
                        TO_CHAR(submitted_date, 'DD-MON-RRRR HH24:MI:SS') AS ch_submitted_date,
                        doc_type,
                        supplier_reference,
                        buyer_reference,
                        spb_name,
                        spb_branch_code,
                        vessel_name,
                        status,
                        internal_ref_no,
                        spb_connect_type,
                        alert_last_sent,
                        cancellation_last_sent,
                        attTxnId,
                        attType,
                        byb_branch_code,
                        clean_vessel_name,
                        reminder_completed,
                        reminder_total,
                        reminder_last_sent,
                        rfq_advice_before_date,
                        rfq_deadline_allow_mgr_unlock,
                        rfq_deadline_mgr_unlocked_date,
                        rfq_quoted_date,
                        priority,
                        variance
                    FROM (" . $injectQuery . ") rec
                        WHERE
                            clean_vessel_name = UPPER( TRIM( :vessel ))
                            AND ( UPPER( TRIM( :reference )) IS NULL OR ( UPPER( TRIM( :reference )) IS NOT NULL AND UPPER( NVL( TRIM( buyer_reference ), 'NUL' )) LIKE UPPER( NVL( TRIM( :reference ), 'NUL' ))))
                            AND ( UPPER( TRIM( :buyercontact )) = 'ALL' OR
                            ( UPPER( TRIM( :buyercontact )) != 'ALL' AND
                            EXISTS (
                                SELECT /*+ FIRST_ROWS(10) */
                                    NULL
                                FROM
                                 contact cntc
                                WHERE
                                    cntc.cntc_doc_type  = DECODE( rec.doc_type, 'PO', 'ORD', rec.doc_type )
                                    AND cntc.cntc_branch_qualifier = 'BY'
                                    AND cntc.byb_branch_code = rec.byb_branch_code
                                    AND cntc.cntc_created_date BETWEEN rec.submitted_date - 1 AND rec.submitted_date + 1
                                    AND cntc.cntc_doc_internal_ref_no = rec.internal_ref_no
                                    AND UPPER( TRIM( REPLACE( REPLACE( cntc.cntc_person_name, '   ', ' ' ), '  ', ' ' ))) = UPPER( TRIM( :buyercontact ))
                            )
                        )
                )" . PHP_EOL;

        if ($count  === true || $limit === false) {
            $sql .= ') t) t';
        } else {
            $sql .= 'ORDER BY ' . $sortfield . ' ' . $sortdirection . ') t WHERE ROWNUM <= :pageend ) t WHERE rn >= :pagestart';
        }

        return $sql;
    }


    /**
     * Get query partial for RFQ list
     *
     * @return string
     */
    protected function _getRfqQuery()
    {

        $includeChildren = $buyerbranch = null;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        $sql = "
          SELECT
            submitted_date,
            doc_type,
            supplier_reference,
            buyer_reference,
            spb_name,
            spb_branch_code,
            vessel_name,
            status,
            internal_ref_no,
            spb_connect_type,
            alert_last_sent,
            cancellation_last_sent,
            attTxnId,
            attType,
            byb_branch_code,
            clean_vessel_name,
            reminder_completed,
            reminder_total,
            reminder_last_sent,
            /* S16126 */
            rfq_advice_before_date,
            rfq_deadline_allow_mgr_unlock,
            rfq_deadline_mgr_unlocked_date,
            rfq_quoted_date,
            priority,
            variance
          FROM (
              SELECT
                 rfq.rfq_submitted_date                   AS submitted_date,
                 'RFQ'                                    AS doc_type,
                 NULL                                     AS supplier_reference,
                 rfq.rfq_ref_no                           AS buyer_reference,
                 spb.spb_name,
                 spb.spb_branch_code,
                 DECODE( SIGN( LENGTH( rfq.rfq_vessel_name ) - 28 ), 1, SUBSTR( rfq.rfq_vessel_name, 0, 28 ) || '...', rfq.rfq_vessel_name ) AS vessel_name,
                 NVL((SELECT rfq_resp.rfq_resp_sts
                        FROM rfq_resp
                       WHERE rfq_resp.rfq_internal_ref_no = rfq.rfq_internal_ref_no
                         AND rfq_resp.rfq_resp_date > rfq.rfq_submitted_date
                         AND rfq_resp.spb_branch_code = rfq.spb_branch_code
                         AND rownum = 1), rfq.rfq_rqr_sts) AS status,
                 rfq.rfq_internal_ref_no                  AS internal_ref_no,
                 spb.spb_interface                        AS spb_connect_type,
                 rfq.rfq_alert_last_send_date             AS alert_last_sent,
                 rfq.rfq_cancellation_last_sent           AS cancellation_last_sent,
                 (SELECT att_txn_id FROM att_txn WHERE att_txn_type = 'RFQ' AND att_txn_id = rfq.rfq_internal_ref_no GROUP BY att_txn_id) AS atttxnid,
                 'RFQ'                                    AS attType,
                 rfq.byb_branch_code                      AS byb_branch_code,
                 DECODE( UPPER( TRIM( :vessel )), 'ALL', 'ALL', UPPER( CLEAN_VESSEL_NAME( NVL( rfq.rfq_vessel_name, 'NO VESSEL NAME' )))) AS clean_vessel_name,
                 rfq.rfq_reminder_completed reminder_completed,
                 rfq.rfq_reminder_total reminder_total,
                 TO_CHAR(rfq.rfq_reminder_last_sent, 'DD-MON-YYYY HH24:MI') reminder_last_sent,
                 /* S16126 */
                 TO_CHAR( rfq.rfq_advice_before_date, 'DD-MON-YYYY HH24:MI' ) rfq_advice_before_date,
                 NVL( rfq.rfq_deadline_allow_mgr_unlock, 0 ) rfq_deadline_allow_mgr_unlock,
                 TO_CHAR( rfq.rfq_deadline_mgr_unlocked_date, 'DD-MON-YYYY HH24:MI' ) rfq_deadline_mgr_unlocked_date,
                 TO_CHAR( rfq.rfq_quoted_date, 'DD-MON-YYYY HH24:MI' ) rfq_quoted_date,
                 rfq.rfq_priority priority,
                 null variance
            FROM buyer byb,
                 rfq,
                 supplier spb
            WHERE " . ($includeChildren ? " byb.parent_branch_code IN (" . $buyerbranch . ") " : " byb.byb_branch_code IN (" . $buyerbranch . ")") ."
             AND rfq.byb_branch_code        = byb.byb_branch_code
             AND rfq.rfq_submitted_date BETWEEN to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS') AND to_date(:todate,'DD-MON-YYYY HH24:MI:SS') + 0.99999
             AND rfq.spb_branch_code        = DECODE( :supplierbranch, 0, rfq.spb_branch_code, :supplierbranch )
            
             /* S16126 */
             AND ( TRIM( :qotdeadline ) IS NULL OR
                   ( TRIM( :qotdeadline ) IS NOT NULL AND
                     rfq.rfq_advice_before_date BETWEEN to_date(:qotdeadline,'DD-MON-YYYY HH24:MI:SS') AND to_date(:qotdeadline,'DD-MON-YYYY HH24:MI:SS') + 0.99999
                   )
                 )
            
             AND spb.spb_branch_code        = rfq.spb_branch_code
             AND spb.spb_interface          = DECODE( UPPER( :suppliertype ),
                                                      'ALL', spb.spb_interface,
                                                      'TRADENETSUPPLIER', DECODE( spb.spb_interface, 'STARTSUPPLIER', 'NUL', spb.spb_interface ),
                                                      UPPER( :suppliertype ))
             AND spb.spb_interface          = DECODE( UPPER( :status ),
                                                      'SENT', DECODE( spb.spb_interface, 'SMARTSUPPLIER', 'SMARTSUPPLIER', 'INTEGRATION', 'INTEGRATION','NUL'),
                                                      'NEW',  'STARTSUPPLIER',
                                                      'OPN',  'STARTSUPPLIER',
                                                      spb.spb_interface )
            AND (
                    (
                        DECODE(UPPER(:urgent), 'PRI', rfq.rfq_priority, 1) = 1
                        AND NVL(DECODE(UPPER(:noturgent), 'NOPRI', rfq.rfq_priority, 0), 0) != 1
                    )
                    OR 
                    (
                        UPPER(:urgent) = 'PRI' AND UPPER(:noturgent) = 'NOPRI'
                    )
                )
            AND (
                    DECODE(UPPER(:variance), 'VAR', 0, 1) = 1
                    OR (
                        UPPER(:variance) = 'VAR' AND UPPER(:novariance) = 'NOVAR'
                    )
                )
        )
        WHERE status = DECODE( UPPER( :status ),
                              'ALL',  status,
                              'QUO',  'ACC',
                              'SUB',  'NUL',
                              'CON',  'NUL',
                              'ACC',  'NUL',
                              'NREP', DECODE( status, 'NEW', 'NEW', 'OPN', 'OPN', 'NUL' ),
                              'SENT', DECODE( status, 'NEW', 'NEW', 'OPN', 'OPN', 'NUL' ),
                              UPPER( :status ))
        AND (
                (
                    DECODE(UPPER(:attachment), 'ATT', atttxnid, 1) is not null
                    AND DECODE(UPPER(:noattachment), 'NOATT', atttxnid, null) is null
                )
                OR 
                (
                    UPPER(:attachment) = 'ATT' AND UPPER(:noattachment) = 'NOATT'
                )
            )";

            return $sql;
    }

    /**
     * Get query partial for PO list
     *
     * @return string
     */
    protected function _getPoQuery()
    {
        $includeChildren = $buyerbranch = null;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        $sql = "
            SELECT * FROM (
            SELECT
             ord.ord_submitted_date       AS submitted_date,
             'PO'                         AS doc_type,
             ''                           AS supplier_reference,
             ord.ord_ref_no               AS buyer_reference,
             spb.spb_name,
             spb.spb_branch_code,
             DECODE( SIGN( LENGTH( ord.ord_vessel_name ) - 28 ), 1, SUBSTR( ord.ord_vessel_name, 0, 28 ) || '...', ord.ord_vessel_name ) AS vessel_name,
             ord.ord_po_sts               AS status,
             ord.ord_internal_ref_no      AS internal_ref_no,
             spb.spb_interface            AS spb_connect_type,
             ord.ord_alert_last_send_date AS alert_last_sent,
             null                         AS cancellation_last_sent,
             (SELECT att_txn_id FROM att_txn WHERE att_txn_type = 'PO' AND att_txn_id = ord.ord_internal_ref_no GROUP BY att_txn_id) AS atttxnid,
             'PO'                         AS attType,
             ord.byb_branch_code          AS byb_branch_code,
             DECODE( UPPER( TRIM( :vessel )), 'ALL', 'ALL', UPPER( CLEAN_VESSEL_NAME( NVL( ord.ord_vessel_name, 'NO VESSEL NAME' )))) AS clean_vessel_name,
             ord.ord_reminder_completed reminder_completed,
             ord.ord_reminder_total reminder_total,
             TO_CHAR(ord.ord_reminder_last_sent, 'DD-MON-YYYY HH24:MI') reminder_last_sent,
             /* S16126 */
             null rfq_advice_before_date,
             0 rfq_deadline_allow_mgr_unlock,
             null rfq_deadline_mgr_unlocked_date,
             null rfq_quoted_date,
             ord.ord_priority priority,
             null variance
            FROM buyer byb,
             ord,
             supplier spb
            WHERE " . ($includeChildren ? " byb.parent_branch_code IN (" . $buyerbranch . ") " : " byb.byb_branch_code IN (" . $buyerbranch . ") ") . "
            AND ord.byb_branch_code = byb.byb_branch_code
            AND ord.ord_submitted_date BETWEEN to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS') AND to_date(:todate,'DD-MON-YYYY HH24:MI:SS') + 0.99999
            AND ord.spb_branch_code = DECODE( :supplierbranch, 0, ord.spb_branch_code, :supplierbranch )
            AND ord.ord_po_sts      = DECODE( UPPER( :status ),
                                           'SENT', DECODE( ord.ord_po_sts, 'NEW', 'NEW', 'OPN', 'OPN', 'NUL' ),
                                           'NREP', DECODE( ord.ord_po_sts, 'NEW', 'NEW', 'OPN', 'OPN', 'NUL' ),
                                           'ALL',  ord.ord_po_sts,
                                           UPPER( :status ))
            AND spb.spb_branch_code = ord.spb_branch_code
            AND spb.spb_interface   = DECODE( UPPER( :suppliertype ),
                                           'ALL', spb.spb_interface,
                                           'TRADENETSUPPLIER', DECODE( spb.spb_interface, 'STARTSUPPLIER', 'NUL', spb.spb_interface ),
                                           UPPER( :suppliertype ))
            AND spb.spb_interface   = DECODE( UPPER( :status ),
                                           'SENT', DECODE( spb.spb_interface, 'SMARTSUPPLIER', 'SMARTSUPPLIER', 'INTEGRATION', 'INTEGRATION','NUL'),
                                           'NEW',  'STARTSUPPLIER',
                                           'OPN',  'STARTSUPPLIER',
                                           spb.spb_interface )
            AND (
                    (
                    DECODE(UPPER(:urgent), 'PRI', ord.ord_priority, 1) = 1
                    AND NVL(DECODE(UPPER(:noturgent), 'NOPRI', ord.ord_priority, 0), 0) != 1
                    )
                    OR 
                    (
                        UPPER(:urgent) = 'PRI' AND UPPER(:noturgent) = 'NOPRI'
                    )
                )
            AND (
                    DECODE(UPPER(:variance), 'VAR', 0, 1) = 1
                    OR (
                        UPPER(:variance) = 'VAR' AND UPPER(:novariance) = 'NOVAR'
                    )
                )
            )
            WHERE 
                (
                    DECODE(UPPER(:attachment), 'ATT', atttxnid, 1) is not null
                    AND DECODE(UPPER(:noattachment), 'NOATT', atttxnid, null) is null
                )
                OR 
                (
                    UPPER(:attachment) = 'ATT' AND UPPER(:noattachment) = 'NOATT'
                )";

        return $sql;
    }

    /**
     * Get query partial for QUOT list
     *
     * @return string
     */
    protected function _getQotQuery()
    {
        $includeChildren = $buyerbranch = null;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        $sql = "
              SELECT * FROM (
              SELECT
                     qot.qot_submitted_date  AS submitted_date,
                     'QOT'                   AS doc_type,
                     qot.qot_ref_no          AS supplier_reference,
                     rfq.rfq_ref_no          AS buyer_reference,
                     spb.spb_name,
                     spb.spb_branch_code,
                     DECODE( SIGN( LENGTH( rfq.rfq_vessel_name ) - 28 ), 1, SUBSTR( rfq.rfq_vessel_name, 0, 28 ) || '...', rfq.rfq_vessel_name ) AS vessel_name,
                     CASE WHEN :mtmlbuyer = 'Y' THEN DECODE( qot.qot_mtml_exported, 'N', 'Not Imported', ' ' ) ELSE ' ' END AS status,
                     qot.qot_internal_ref_no AS internal_ref_no,
                     spb.spb_interface       AS spb_connect_type,
                     NULL                    AS alert_last_sent,
                     NULL                    AS cancellation_last_sent,
                     (SELECT att_txn_id FROM att_txn WHERE att_txn_type = 'QOT' AND att_txn_id = qot.qot_internal_ref_no GROUP BY att_txn_id) AS atttxnid,
                     'QOT'                   AS attType,
                     qot.byb_branch_code     AS byb_branch_code,
                     DECODE( UPPER( TRIM( :vessel )), 'ALL', 'ALL', UPPER( CLEAN_VESSEL_NAME( NVL( rfq.rfq_vessel_name, 'NO VESSEL NAME' )))) AS clean_vessel_name,
                     null reminder_completed,
                     null reminder_total,
                     null reminder_last_sent,
                     /* S16126 */
                     TO_CHAR( rfq.rfq_advice_before_date, 'DD-MON-YYYY HH24:MI' ) rfq_advice_before_date,
                     NVL( rfq.rfq_deadline_allow_mgr_unlock, 0 ) rfq_deadline_allow_mgr_unlock,
                     TO_CHAR( rfq.rfq_deadline_mgr_unlocked_date, 'DD-MON-YYYY HH24:MI' ) rfq_deadline_mgr_unlocked_date,
                     TO_CHAR( rfq.rfq_quoted_date, 'DD-MON-YYYY HH24:MI' ) rfq_quoted_date,
                     qot.qot_priority priority,
                     qot.qot_variance variance
                FROM buyer byb,
                     qot,
                     rfq,
                     supplier spb
               WHERE " . ($includeChildren ? " byb.parent_branch_code IN (" . $buyerbranch . ") " : " byb.byb_branch_code IN (" . $buyerbranch . ") ") . "
                 AND qot.byb_branch_code     = byb.byb_branch_code
                 AND qot.qot_submitted_date BETWEEN to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS') AND to_date(:todate,'DD-MON-YYYY HH24:MI:SS') + 0.99999
                 AND qot.spb_branch_code     = DECODE( :supplierbranch, 0, qot.spb_branch_code, :supplierbranch )
                 AND rfq.rfq_internal_ref_no = qot.rfq_internal_ref_no
                 AND rfq.byb_branch_code     = qot.byb_branch_code
                 AND rfq.spb_branch_code     = qot.spb_branch_code
                 AND rfq.rfq_submitted_date BETWEEN to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS') - (((to_date(:todate,'DD-MON-YYYY HH24:MI:SS') - to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS'))/2)+366) AND qot.qot_submitted_date
        
                 /* S16126 */
                 AND ( TRIM( :qotdeadline ) IS NULL OR
                       ( TRIM( :qotdeadline ) IS NOT NULL AND
                         rfq.rfq_advice_before_date BETWEEN to_date(:qotdeadline,'DD-MON-YYYY HH24:MI:SS') AND to_date(:qotdeadline,'DD-MON-YYYY HH24:MI:SS') + 0.99999
                       )
                     )
        
                 AND spb.spb_branch_code     = qot.spb_branch_code
                 AND spb.spb_interface       = DECODE(
                    UPPER( :suppliertype ),
                    'ALL', spb.spb_interface,
                    'TRADENETSUPPLIER', DECODE( spb.spb_interface, 'STARTSUPPLIER', 'NUL', spb.spb_interface ),
                    UPPER( :suppliertype ))
                AND (
                        (
                            DECODE(UPPER(:urgent), 'PRI', qot.qot_priority, 1) = 1
                            AND NVL(DECODE(UPPER(:noturgent), 'NOPRI', qot.qot_priority, 0), 0) != 1
                        )
                        OR 
                        (
                            UPPER(:urgent) = 'PRI' AND UPPER(:noturgent) = 'NOPRI'
                        )
                    )
                AND (
                        (
                            DECODE(UPPER(:variance), 'VAR', qot.qot_variance, 1) = 1
                            AND NVL(DECODE(UPPER(:novariance), 'NOVAR', qot.qot_variance, 0), 0) != 1
                        )
                        OR 
                        (
                            UPPER(:variance) = 'VAR' AND UPPER(:novariance) = 'NOVAR'
                        )
                    )
               )
               WHERE
                (
                    DECODE(UPPER(:attachment), 'ATT', atttxnid, 1) is not null
                    AND DECODE(UPPER(:noattachment), 'NOATT', atttxnid, null) is null
                )
                OR 
                (
                    UPPER(:attachment) = 'ATT' AND UPPER(:noattachment) = 'NOATT'
                )";
        return $sql;
    }

    /**
     * Get query partial for POC list
     *
     * @return string
     */
    protected function _getPocQuery()
    {

        $includeChildren = $buyerbranch = null;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        $sql = "
            SELECT * FROM (
            SELECT
                poc . poc_submitted_date  AS submitted_date,
                'POC'                   AS doc_type,
                poc . poc_ref_no          AS supplier_reference,
                ord . ord_ref_no          AS buyer_reference,
                spb . spb_name,
                spb . spb_branch_code,
                DECODE(SIGN(LENGTH(ord . ord_vessel_name) - 28), 1, SUBSTR(ord . ord_vessel_name, 0, 28) || '...', ord . ord_vessel_name) AS vessel_name,
                CASE WHEN :mtmlbuyer = 'Y' THEN DECODE(poc . poc_mtml_exported, 'N', 'Not Imported', ' ') ELSE ' ' END AS status,
                poc . ord_internal_ref_no AS internal_ref_no,
                spb . spb_interface       AS spb_connect_type,
                NULL                    AS alert_last_sent,
                NULL                    AS cancellation_last_sent,
                (SELECT att_txn_id FROM att_txn WHERE att_txn_type = 'POC' AND att_txn_id = poc . ord_internal_ref_no GROUP BY att_txn_id) AS atttxnid,
                'POC'                   AS attType,
                poc . byb_branch_code     AS byb_branch_code,
                DECODE(UPPER(TRIM( :vessel )), 'ALL', 'ALL', UPPER(CLEAN_VESSEL_NAME(NVL(ord . ord_vessel_name, 'NO VESSEL NAME')))) AS clean_vessel_name,
                null reminder_completed,
                null reminder_total,
                null reminder_last_sent,
                /* S16126 */
                null rfq_advice_before_date,
                0 rfq_deadline_allow_mgr_unlock,
                null rfq_deadline_mgr_unlocked_date,
                null rfq_quoted_date,
                poc.poc_priority priority,
                poc.poc_variance variance
                FROM buyer byb,
                poc,
                ord,
                supplier spb
             WHERE " . ($includeChildren ? " byb . parent_branch_code IN(" . $buyerbranch . ") " : " byb . byb_branch_code IN(" . $buyerbranch . ") ") ."
                AND poc . byb_branch_code = byb . byb_branch_code
                AND poc . poc_submitted_date BETWEEN to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS') AND to_date(:todate,'DD-MON-YYYY HH24:MI:SS') +0.99999
                AND poc . spb_branch_code = DECODE( :supplierbranch, 0, poc . spb_branch_code, :supplierbranch )
                                     AND ord . ord_internal_ref_no = poc . ord_internal_ref_no
                AND ord . byb_branch_code = poc . byb_branch_code
                AND ord . spb_branch_code = poc . spb_branch_code
                AND spb . spb_branch_code = poc . spb_branch_code
                AND spb . spb_interface = DECODE(
                    UPPER( :suppliertype ),
                    'ALL', spb . spb_interface,
                    'TRADENETSUPPLIER', DECODE(spb . spb_interface, 'STARTSUPPLIER', 'NUL', spb . spb_interface),
                    UPPER( :suppliertype ))
                AND (
                        (
                            DECODE(UPPER(:urgent), 'PRI', poc.poc_priority, 1) = 1
                            AND NVL(DECODE(UPPER(:noturgent), 'NOPRI', poc.poc_priority, 0), 0) != 1
                        )
                        OR 
                        (
                            UPPER(:urgent) = 'PRI' AND UPPER(:noturgent) = 'NOPRI'
                        )
                    )
                AND 
                (
                    (
                        DECODE(UPPER(:variance), 'VAR', poc.poc_variance, 1) = 1
                        AND NVL(DECODE(UPPER(:novariance), 'NOVAR', poc.poc_variance, 0), 0) != 1
                    )
                    OR 
                    (
                        UPPER(:variance) = 'VAR' AND UPPER(:novariance) = 'NOVAR'
                    )
                )
                )
                WHERE
                    (
                        DECODE(UPPER(:attachment), 'ATT', atttxnid, 1) is not null
                        AND DECODE(UPPER(:noattachment), 'NOATT', atttxnid, null) is null
                    )
                    OR 
                    (
                        UPPER(:attachment) = 'ATT' AND UPPER(:noattachment) = 'NOATT'
                    )";

        return $sql;

    }

    /**
     * Get query partial for INV list
     *
     * @return string
     */
    protected function _getInvQuery()
    {
        $includeChildren = $buyerbranch = null;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        $sql = "
            SELECT * FROM (
            SELECT 
              inv . inv_submitted_date  AS submitted_date,
             'INV' doc_type,
             inv . inv_ref_no          AS supplier_reference,
             inv . inv_ord_ref_no      AS buyer_reference,
             spb . spb_name,
             spb . spb_branch_code,
             DECODE(SIGN(LENGTH(inv . inv_vessel_name) - 28), 1, SUBSTR(inv . inv_vessel_name, 0, 28) || '...', inv . inv_vessel_name) AS vessel_name,
             CASE WHEN :mtmlbuyer = 'Y' THEN DECODE(inv . inv_mtml_exported, 'N', 'Not Imported', ' ') ELSE ' ' END AS status,
             inv . inv_internal_ref_no AS internal_ref_no,
             spb . spb_interface       AS spb_connect_type,
             NULL                    AS alert_last_sent,
             NULL                    AS cancellation_last_sent,
             (SELECT att_txn_id FROM att_txn WHERE att_txn_type = 'INV' AND att_txn_id = inv . inv_internal_ref_no GROUP BY att_txn_id) AS atttxnid,
             'INV'                   AS attType,
             inv . byb_branch_code     AS byb_branch_code,
             DECODE(UPPER(TRIM( :vessel )), 'ALL', 'ALL', UPPER(CLEAN_VESSEL_NAME(NVL(inv . inv_vessel_name, 'NO VESSEL NAME')))) AS clean_vessel_name,
             null reminder_completed,
             null reminder_total,
             null reminder_last_sent,
             /* S16126 */
             null rfq_advice_before_date,
             0 rfq_deadline_allow_mgr_unlock,
             null rfq_deadline_mgr_unlocked_date,
             null rfq_quoted_date,
             inv.inv_priority priority,
             null variance
            FROM 
              buyer byb,
              inv,
              supplier spb
            WHERE " . ($includeChildren ? " byb . parent_branch_code IN(" . $buyerbranch . ") " : " byb . byb_branch_code IN(" . $buyerbranch . ") ") ."
                AND inv . byb_branch_code = byb . byb_branch_code
                AND inv . inv_submitted_date BETWEEN to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS') AND to_date(:todate,'DD-MON-YYYY HH24:MI:SS') +0.99999
                AND inv . spb_branch_code = DECODE( :supplierbranch, 0, inv . spb_branch_code, :supplierbranch )
                AND inv . inv_integration_type = 'MTML'
                AND spb . spb_branch_code = inv . spb_branch_code
                AND spb . spb_interface = DECODE(
                    UPPER( :suppliertype ),
                   'ALL', spb . spb_interface,
                   'TRADENETSUPPLIER', DECODE(spb . spb_interface, 'STARTSUPPLIER', 'NUL', spb . spb_interface),
                   UPPER( :suppliertype ))
                AND (
                        (
                            DECODE(UPPER(:urgent), 'PRI', inv.inv_priority, 1) = 1
                            AND NVL(DECODE(UPPER(:noturgent), 'NOPRI', inv.inv_priority, 0), 0) != 1
                        )
                        OR 
                        (
                            UPPER(:urgent) = 'PRI' AND UPPER(:noturgent) = 'NOPRI'
                        )
                    )
                AND (
                       DECODE(UPPER(:variance), 'VAR', 0, 1) = 1
                        OR (
                            UPPER(:variance) = 'VAR' AND UPPER(:novariance) = 'NOVAR'
                        )
                    )
                )
                WHERE
                    (
                        DECODE(UPPER(:attachment), 'ATT', atttxnid, 1) is not null
                        AND DECODE(UPPER(:noattachment), 'NOATT', atttxnid, null) is null
                    )
                    OR 
                    (
                        UPPER(:attachment) = 'ATT' AND UPPER(:noattachment) = 'NOATT'
                    )";

        return $sql;
    }

    /**
     * Get query partial for REQ list
     *
     * @return string
     */
    protected function _getReqQuery()
    {
        $includeChildren = $buyerbranch = null;
        extract($this->sqlEmbeddedParams, EXTR_OVERWRITE);

        $sql = "
            SELECT * FROM (
            SELECT
                req . req_submitted_date  AS submitted_date,
                 'REQ'                   AS doc_type,
                 NULL                    AS supplier_reference,
                 req . req_ref_no        AS buyer_reference,
                 NULL                    AS spb_name,
                 NULL                    AS spb_branch_code,
                 DECODE(SIGN(LENGTH(req . req_vessel_name) - 28), 1, SUBSTR(req . req_vessel_name, 0, 28) || '...', req . req_vessel_name) AS vessel_name,
                 CASE WHEN :mtmlbuyer = 'Y' THEN DECODE(req . req_mtml_exported, 'N', 'Not Imported', ' ') ELSE ' ' END AS status,
                 req . req_internal_ref_no AS internal_ref_no,
                 NULL                    AS spb_connect_type,
                 NULL                    AS alert_last_sent,
                 NULL                    AS cancellation_last_sent,
                 (SELECT att_txn_id FROM att_txn WHERE att_txn_type = 'REQ' AND att_txn_id = req . req_internal_ref_no GROUP BY att_txn_id) AS atttxnid,
                 'REQ'                   AS attType,
                 req . byb_branch_code     AS byb_branch_code,
                 DECODE(UPPER(TRIM( :vessel )), 'ALL', 'ALL', UPPER(CLEAN_VESSEL_NAME(NVL(req . req_vessel_name, 'NO VESSEL NAME')))) AS clean_vessel_name,
                 null reminder_completed,
                 null reminder_total,
                 null reminder_last_sent,
                 /* S16126 */
                 null rfq_advice_before_date,
                 0 rfq_deadline_allow_mgr_unlock,
                 null rfq_deadline_mgr_unlocked_date,
                 null rfq_quoted_date,
                 req.req_priority priority,
                 null variance
                FROM buyer byb,
                 req
            WHERE " . ($includeChildren ? " byb . parent_branch_code IN(" . $buyerbranch . ") " : " byb . byb_branch_code IN(" . $buyerbranch . ") ") ."
                AND req . byb_branch_code = byb . byb_branch_code
                AND req . req_submitted_date BETWEEN to_date(:fromdate,'DD-MON-YYYY HH24:MI:SS') AND to_date(:todate,'DD-MON-YYYY HH24:MI:SS')+0.99999
                AND (
                        (
                            DECODE(UPPER(:urgent), 'PRI', req.req_priority, 1) = 1
                            AND NVL(DECODE(UPPER(:noturgent), 'NOPRI', req.req_priority, 0), 0) != 1
                        )
                        OR 
                        (
                            UPPER(:urgent) = 'PRI' AND UPPER(:noturgent) = 'NOPRI'
                        )
                    )
                AND (
                        DECODE(UPPER(:variance), 'VAR', 0, 1) = 1
                        OR (
                            UPPER(:variance) = 'VAR' AND UPPER(:novariance) = 'NOVAR'
                        )
                    )
                )
                WHERE
                    (
                        DECODE(UPPER(:attachment), 'ATT', atttxnid, 1) is not null
                        AND DECODE(UPPER(:noattachment), 'NOATT', atttxnid, null) is null
                    )
                    OR 
                    (
                        UPPER(:attachment) = 'ATT' AND UPPER(:noattachment) = 'NOATT'
                    )";

        return $sql;
    }

}
