<?php
/**
 * A container class for general purpose functions related to match but not belonging exactly to any of its entities
 * and still too detailed to be put in controller
 *
 * @author  Yuriy Akopov
 * @date    2014-06-05
 * @story   S10311
 */
class Shipserv_Match_Manager {
    const
        INBOX_COL_MATCH_TYPE = 'MATCH_TYPE',

        INBOX_COL_RFQ_ID     = 'RFQ_ID',
        INBOX_COL_RFQ_BYB_ID = 'RFQ_BYB_ID',
        INBOX_COL_RFQ_EVENT  = 'RFQ_EVENT_ID',
        INBOX_COL_RFQ_SUBJ   = 'RFQ_SUBJECT',
        INBOX_COL_RFQ_REF    = 'RFQ_REFERENCE',
        INBOX_COL_RFQ_LI     = 'RFQ_LINE_ITEM_COUNT',

        INBOX_COL_DATE       = 'MATCH_DATE',
        INBOX_COL_SET_COUNT  = 'MATCH_SET_COUNT',
        INBOX_COL_SET_NAME   = 'MATCH_SET_NAME',

        INBOX_COL_BUYER_ID      = 'BUYER_ID',
        INBOX_COL_BUYER_NAME    = 'BUYER_NANE',
        INBOX_COL_BUYER_COUNTRY = 'BUYER_CONTRY'
    ;

    const
        MATCH_TYPE_AUTO = 'AUTO',
        MATCH_TYPE_USER = 'USER'
    ;

    /**
     * Returns a query to retrieve data for match inbox
     *
     * @param   bool|null   $showAuto
     *
     * @return Zend_Db_Select
     */
    public static function getMatchInboxSelect($showAuto = null) {
        $db = Shipserv_Helper_Database::getDb();

        if (is_null($showAuto)) {
            // display both user and automatically matched RFQs
            $selectUser = self::getUserMatchInboxSelect();
            $selectAuto = self::getAutoMatchInboxSelect();

            $selectRfq = new Zend_Db_Select($db);
            $selectRfq->union(array(
                $selectUser,
                $selectAuto
            ), Zend_Db_Select::SQL_UNION_ALL);

        } else if ($showAuto) {
            // display RFQs sent to match by automatic scripts only
            $selectRfq = self::getAutoMatchInboxSelect();
        } else {
            // display only RFQs sent to match by users
            $selectRfq = self::getUserMatchInboxSelect();
        }

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('rfq' => $selectRfq),
                array(
                    self::INBOX_COL_MATCH_TYPE  => 'rfq.' . self::INBOX_COL_MATCH_TYPE,

                    self::INBOX_COL_RFQ_ID      => 'rfq.' . self::INBOX_COL_RFQ_ID,
                    self::INBOX_COL_RFQ_EVENT   => new Zend_Db_Expr('RAWTOHEX(rfq.' . self::INBOX_COL_RFQ_EVENT . ')'),
                    self::INBOX_COL_DATE        => new Zend_Db_Expr('TO_CHAR(rfq.' . self::INBOX_COL_DATE . ', \'YYYY-MM-DD HH24:MI:SS\')'),

                    self::INBOX_COL_RFQ_SUBJ    => 'rfq.' . self::INBOX_COL_RFQ_SUBJ,
                    self::INBOX_COL_RFQ_REF     => 'rfq.' . self::INBOX_COL_RFQ_REF,
                    self::INBOX_COL_RFQ_LI      => 'rfq.' . self::INBOX_COL_RFQ_LI,

                    self::INBOX_COL_SET_COUNT   => 'rfq.' . self::INBOX_COL_SET_COUNT,
                    self::INBOX_COL_SET_NAME    => 'rfq.' . self::INBOX_COL_SET_NAME
                )
            )
            ->join(
                array('byb' => 'buyer_branch'),
                'byb.byb_branch_code = rfq.' . self::INBOX_COL_RFQ_BYB_ID,
                array(
                    self::INBOX_COL_BUYER_ID        => 'byb.byb_branch_code',
                    self::INBOX_COL_BUYER_NAME      => 'byb.byb_name',
                    self::INBOX_COL_BUYER_COUNTRY   => 'byb.byb_country'
                )
            )
            ->order(self::INBOX_COL_DATE . ' DESC')
        ;

        // print $select->assemble(); exit;

        return $select;
    }

    /**
     * Returns a query to select RFQs sent to match by users
     *
     * @return  Zend_Db_Select
     */
    public static function getUserMatchInboxSelect() {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                array(
                    self::INBOX_COL_MATCH_TYPE => new Zend_Db_Expr($db->quote(self::MATCH_TYPE_USER)),

                    self::INBOX_COL_RFQ_ID     => 'rfq.' . Shipserv_Rfq::COL_ID,
                    self::INBOX_COL_RFQ_BYB_ID => 'rfq.' . Shipserv_Rfq::COL_BUYER_ID,
                    self::INBOX_COL_RFQ_SUBJ   => 'rfq.' . Shipserv_Rfq::COL_SUBJECT,
                    self::INBOX_COL_RFQ_REF    => 'rfq.' . Shipserv_Rfq::COL_PUBLIC_ID,
                    self::INBOX_COL_RFQ_LI     => 'rfq.' . Shipserv_Rfq::COL_LINE_ITEM_COUNT,
                    self::INBOX_COL_RFQ_EVENT  => 'rfq.' . Shipserv_Rfq::COL_EVENT_HASH,
                    self::INBOX_COL_DATE       => 'rfq.' . Shipserv_Rfq::COL_DATE,
                    self::INBOX_COL_SET_COUNT  => new Zend_Db_Expr('NULL'),
                    self::INBOX_COL_SET_NAME   => new Zend_Db_Expr('NULL')
                )
            )
            ->join(
                array('rqr' => 'rfq_quote_relation'),
                implode(' AND ', array(
                    'rqr.rqr_rfq_internal_ref_no = rfq.'. Shipserv_Rfq::COL_ID,
                    $db->quoteInto('rqr.rqr_spb_branch_code = ?', Myshipserv_Config::getProxyMatchSupplier())
                )),
                array()
            )
            ->where('rfq.' . Shipserv_Rfq::COL_STATUS . ' = ?', Shipserv_Rfq::STATUS_SUBMITTED)
            // no RFQs which have already been processed
            ->joinLeft(
                array('mrp' => 'match_rfq_processed_list'),
                'mrp.mrp_rfq_internal_ref_no = rfq.rfq_internal_ref_no',
                array()
            )
            ->where('mrp.mrp_rfq_internal_ref_no IS NULL')
            // no RFQs which were marked for match automatically
            ->joinLeft(
                array('msr' => Shipserv_Match_Auto_Component_Event::TABLE_NAME),
                'msr.' . Shipserv_Match_Auto_Component_Event::COL_RFQ_EVENT . ' = rfq.' . Shipserv_Rfq::COL_EVENT_HASH,
                array()
            )
            ->where('msr.' . Shipserv_Match_Auto_Component_Event::COL_ID . ' IS NULL')
         ;

        return $select;
    }

    /**
     * Returns a query to select RFQs which were marked for match by automatic scripts
     *
     * @return  Zend_Db_Select
     */
    public static function getAutoMatchInboxSelect() {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);

        $select
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                array(
                    self::INBOX_COL_MATCH_TYPE  => new Zend_Db_Expr($db->quote(self::MATCH_TYPE_AUTO)),
                    self::INBOX_COL_RFQ_ID      => new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_ID . ')'),
                    self::INBOX_COL_RFQ_BYB_ID  => new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_BUYER_ID . ')'),
                    self::INBOX_COL_RFQ_SUBJ    => new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_SUBJECT . ')'),
                    self::INBOX_COL_RFQ_REF     => new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_PUBLIC_ID . ')'),
                    self::INBOX_COL_RFQ_LI      => new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_LINE_ITEM_COUNT . ')')
                )
            )
            ->join(
                array('msr' => Shipserv_Match_Auto_Component_Event::TABLE_NAME),
                'msr.' . Shipserv_Match_Auto_Component_Event::COL_RFQ_EVENT . ' = rfq.' . Shipserv_Rfq::COL_EVENT_HASH,
                array(
                    self::INBOX_COL_RFQ_EVENT  => 'msr.' . Shipserv_Match_Auto_Component_Event::COL_RFQ_EVENT,
                    self::INBOX_COL_DATE      => new Zend_Db_Expr('MIN(msr.' . Shipserv_Match_Auto_Component_Event::COL_DATE . ')'),
                    self::INBOX_COL_SET_COUNT => new Zend_Db_Expr('COUNT(DISTINCT msr.' . Shipserv_Match_Auto_Component_Event::COL_SET_ID . ')')
                )
            )
            ->joinLeft(
                array('mss' => Shipserv_Match_Auto_Component_Set::TABLE_NAME),
                'mss.' . Shipserv_Match_Auto_Component_Set::COL_ID . ' = msr.' . Shipserv_Match_Auto_Component_Event::COL_SET_ID,
                array(
                    self::INBOX_COL_SET_NAME => new Zend_Db_Expr('MAX(mss.' . Shipserv_Match_Auto_Component_Set::COL_NAME . ')')
                )
            )
            ->where('rfq.' . Shipserv_Rfq::COL_STATUS . ' = ?', Shipserv_Rfq::STATUS_SUBMITTED)
            ->group('msr.' . Shipserv_Match_Auto_Component_Event::COL_RFQ_EVENT)
            // no events which already have processed RFQs in them
            ->joinLeft(
                array('mrp' => 'match_rfq_processed_list'),
                'mrp.mrp_rfq_internal_ref_no = rfq.' . Shipserv_Rfq::COL_ID,
                array()
            )
            ->having(new Zend_Db_Expr('MAX(mrp.mrp_rfq_internal_ref_no) IS NULL'))
        ;

        return $select;
    }

	/**
	 * Adds meta data to processed RFQs row in Match outbox, first checks if that's already part of the query
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-10-10
	 * @story   S18245
	 *
	 * @param   array   $row
	 *
	 * @return  array
	 */
    public static function extendMatchOutboxRow(array $row)
    {
    	$db = Shipserv_Helper_Database::getDb();

	    if (!array_key_exists('BUYER_NAME', $row)) {
		    // @todo: support for Pages RFQs
		    $select = new Zend_Db_Select($db);
		    $select
			    ->from(
			    	array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
				    'byb.' . Shipserv_Buyer_Branch::COL_NAME
			    )
			    ->where('byb.' . Shipserv_Buyer_Branch::COL_ID . ' = ?', $row['BUYER_ID'])
		    ;

		    $row['BUYER_NAME'] = $db->fetchOne($select);
	    }

        if (
        	!array_key_exists('MATCH_RFQ_COUNT', $row) or
	        !array_key_exists('MATCH_RFQ_DATE', $row)
        ) {
        	$select = new Zend_Db_Select($db);
	        $select
	            ->from(
	            	array('mrfq' => Shipserv_Rfq::TABLE_NAME),
		            array(
			            'MATCH_RFQ_COUNT' => new Zend_Db_Expr('COUNT(DISTINCT mrfq.' . Shipserv_Rfq::COL_ID . ')'),
			            'MATCH_RFQ_DATE'  => new Zend_Db_Expr(
				            'TO_CHAR(MIN(mrfq.' . Shipserv_Rfq::COL_DATE . "), 'YYYY-MM-DD HH24:MI:SS')"
			            )
		            )
	            )
		        ->where('mrfq.' . Shipserv_Rfq::COL_SOURCE_ID . ' = ?', $row['RFQ_ID']);
	        ;

	        $values = $db->fetchRow($select);
	        foreach ($values as $key => $value) {
	        	$row[$key] = $value;
	        }
        }

        if (!array_key_exists('MATCH_QOT_COUNT', $row)) {
	        $select = new Zend_Db_Select($db);
	        $select
	            ->from(
	            	array('mqot' => Shipserv_Quote::TABLE_NAME),
		            new Zend_Db_Expr('COUNT(DISTINCT mqot.' . Shipserv_Quote::COL_ID . ')')
	            )
		        ->join(
		        	array('mrfq' => Shipserv_Rfq::TABLE_NAME),
			        'mrfq.' . Shipserv_Rfq::COL_ID . ' = mqot.' . Shipserv_Quote::COL_RFQ_ID,
			        array()
		        )
		        ->where('mrfq.' . Shipserv_Rfq::COL_SOURCE_ID . ' = ?', $row['RFQ_ID'])
		        ->where('mqot.' . Shipserv_Quote::COL_TOTAL_COST . ' > ?', 0)
		        ->where('mqot.' . Shipserv_Quote::COL_STATUS . ' = ?', Shipserv_Quote::STATUS_SUBMITTED)
	        ;

	        $row['MATCH_QOT_COUNT'] = $db->fetchOne($select);
        }

        if (!array_key_exists('MATCH_DEC_COUNT', $row)) {
	        $select = new Zend_Db_Select($db);
	        $select
		        ->from(
			        array('rfp' =>'rfq_response'),
			        new Zend_Db_Expr('COUNT(DISTINCT rfp.rfp_rfq_internal_ref_no)')
		        )
		        ->join(
			        array('mrfq' => Shipserv_Rfq::TABLE_NAME),
			        'mrfq.' . Shipserv_Rfq::COL_ID . ' = rfp.rfp_rfq_internal_ref_no',
			        array()
		        )
		        ->where('mrfq.' . Shipserv_Rfq::COL_SOURCE_ID . ' = ?', $row['RFQ_ID'])
		        ->where('rfp.rfp_sts = ?', 'DEC')
	        ;

	        $row['MATCH_DEC_COUNT'] = $db->fetchOne($select);
        }

        return $row;
    }

	/**
	 * Returns a query for Match Outbox for further pagination and other modifications
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-10-10
	 * @story   S18245
	 *
	 * @param   array   $filters
	 * @param   string  $orderBy
	 * @param   string  $orderDir
	 *
	 * @return  Zend_Db_Select
	 */
    public static function getMatchOutboxSelect(array $filters, $orderBy, $orderDir = 'desc')
    {
    	$db = Shipserv_Helper_Database::getDb();
    	$select = new Zend_Db_Select($db);

		$select
			->from(
				array('rfq' => Shipserv_Rfq::TABLE_NAME),
				array(
					'RFQ_ID'        => 'rfq.' . Shipserv_Rfq::COL_ID,
					'RFQ_SUBJECT'   => 'rfq.' . Shipserv_Rfq::COL_SUBJECT,
					'RFQ_REF_NO'    => 'rfq.' . Shipserv_Rfq::COL_PUBLIC_ID,
					'BUYER_ID'      => 'rfq.' . Shipserv_Rfq::COL_BUYER_ID
				)
			)
			->join(
				array('rqr' => 'rfq_quote_relation'),
				implode(
					' AND ',
					array(
						'rqr.rqr_rfq_internal_ref_no = rfq.' . Shipserv_Rfq::COL_ID,
						$db->quoteInto('rqr.rqr_spb_branch_code = ?', Myshipserv_Config::getProxyMatchSupplier())
					)
				),
				array()
			)
		;

	    if (array_key_exists('BUYER_ID', $filters) and strlen($filters['BUYER_ID'])) {
	    	$select->where('rfq.' . Shipserv_Rfq::COL_BUYER_ID . ' = ?', $filters['BUYER_ID']);
	    }

	    if (array_key_exists('RFQ_REF_NO', $filters) and strlen($filters['RFQ_REF_NO'])) {
	    	$select->where(
	    		'rfq.' . Shipserv_Rfq::COL_PUBLIC_ID .
			    Shipserv_Helper_Database::escapeLike($db, $filters['RFQ_REF_NO'])
		    );
	    }

	    if ($orderBy === 'BUYER_NAME') {
	    	$select
			    ->join(
				    array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
				    'byb.' . Shipserv_Buyer_Branch::COL_ID . ' = rfq.' . Shipserv_Rfq::COL_BUYER_ID,
				    array(
					    $orderBy => 'byb.' . Shipserv_Buyer_Branch::COL_NAME
				    )
			    )
		    ;
	    }

	    if (
	        (($orderBy === 'MATCH_RFQ_COUNT') or ($orderBy === 'MATCH_RFQ_DATE')) or
	        ($filters['MATCH_RFQ_DATE_FROM'] or $filters['MATCH_RFQ_DATE_TO'])
        ) {
	    	$select
			    ->joinLeft(
				    array('mrfq' => Shipserv_Rfq::TABLE_NAME),
				    'mrfq.' . Shipserv_Rfq::COL_SOURCE_ID . ' = rfq.' . Shipserv_Rfq::COL_ID,
				    array(
					    'MATCH_RFQ_COUNT' => new Zend_Db_Expr('COUNT(DISTINCT mrfq.' . Shipserv_Rfq::COL_ID . ')'),
					    'MATCH_RFQ_DATE'  => new Zend_Db_Expr(
						    'TO_CHAR(MIN(mrfq.' . Shipserv_Rfq::COL_DATE . "), 'YYYY-MM-DD HH24:MI:SS')"
					    )
				    )
			    )
			    ->group(array(
				    'rfq.' . Shipserv_Rfq::COL_ID,
				    'rfq.' . Shipserv_Rfq::COL_SUBJECT,
				    'rfq.' . Shipserv_Rfq::COL_PUBLIC_ID,
				    'rfq.' . Shipserv_Rfq::COL_BUYER_ID
			    ))
		    ;

		    if ($filters['MATCH_RFQ_DATE_FROM']) {
		    	$select->having(
				    'MIN(mrfq.' . Shipserv_Rfq::COL_DATE . ') >= ' . Shipserv_Helper_Database::getOracleDateExpr(
					    $filters['MATCH_RFQ_DATE_FROM']
				    )
			    );
		    }

		    if ($filters['MATCH_RFQ_DATE_TO']) {
			    $select->having(
				    'MIN(mrfq.' . Shipserv_Rfq::COL_DATE . ') < ' . Shipserv_Helper_Database::getOracleDateExpr(
					    $filters['MATCH_RFQ_DATE_TO']
				    )
			    );
		    }
	    }

	    if ($orderBy === 'MATCH_QOT_COUNT') {
            $from = $select->getPart(Zend_Db_Select::FROM);

            if (!array_key_exists('mrfq', $from)) {
                $select
                    ->joinLeft(
                        array('mrfq' => Shipserv_Rfq::TABLE_NAME),
                        'mrfq.' . Shipserv_Rfq::COL_SOURCE_ID . ' = rfq.' . Shipserv_Rfq::COL_ID,
                        array()
                    )
                ;
            }


	    	$select
			    ->joinLeft(
				    array('qot' => Shipserv_Quote::TABLE_NAME),
				    implode(
					    ' AND ',
					    array(
						    'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = mrfq.' . Shipserv_Rfq::COL_ID,
						    $db->quoteInto('qot.' . Shipserv_Quote::COL_STATUS . ' = ?', Shipserv_Quote::STATUS_SUBMITTED),
						    $db->quoteInto('qot.' . Shipserv_Quote::COL_TOTAL_COST . ' > ?', 0),
					    )
				    ),
				    array(
					    'MATCH_QOT_COUNT' => new Zend_Db_Expr('COUNT(DISTINCT(qot.' . Shipserv_Quote::COL_ID . '))')
				    )
			    )
			    ->group(array(
				    'rfq.' . Shipserv_Rfq::COL_ID,
				    'rfq.' . Shipserv_Rfq::COL_SUBJECT,
				    'rfq.' . Shipserv_Rfq::COL_PUBLIC_ID,
				    'rfq.' . Shipserv_Rfq::COL_BUYER_ID
			    ))
		    ;
	    }

	    if ($orderBy === 'MATCH_DEC_COUNT') {
	        $from = $select->getPart(Zend_Db_Select::FROM);

	        if (!array_key_exists('mrfq', $from)) {
	            $select
                    ->joinLeft(
                        array('mrfq' => Shipserv_Rfq::TABLE_NAME),
                        'mrfq.' . Shipserv_Rfq::COL_SOURCE_ID . ' = rfq.' . Shipserv_Rfq::COL_ID,
                        array()
                    )
                ;
            }

	    	$select
			    ->joinLeft(
				    array('rfp' => 'rfq_response'),
				    implode(
					    ' AND ',
					    array(
						    'rfp.rfp_rfq_internal_ref_no = mrfq.' . Shipserv_Rfq::COL_ID,
						    $db->quoteInto('rfp.rfp_sts = ?', 'DEC')
					    )
				    ),
				    array(
					    'MATCH_DEC_COUNT' => new Zend_Db_Expr('COUNT(DISTINCT(rfp.rfp_rfq_internal_ref_no))')
				    )
			    )
			    ->group(array(
				    'rfq.' . Shipserv_Rfq::COL_ID,
				    'rfq.' . Shipserv_Rfq::COL_SUBJECT,
				    'rfq.' . Shipserv_Rfq::COL_PUBLIC_ID,
				    'rfq.' . Shipserv_Rfq::COL_BUYER_ID
			    ))
		    ;
	    }

	    $select->order($orderBy . ' ' . $orderDir);

	    return $select;
    }
}