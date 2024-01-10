<?php

class Shipserv_Oracle_Currency extends Shipserv_Oracle
{
    const
        TABLE_NAME          = 'CURRENCY',
        COL_ID              = 'CURR_CODE',
        COL_EXCHANGE_WITH   = 'CURR_EXCHANGE_WITH',
        COL_EXCHANGE_RATE   = 'CURR_EXCHANGE_RATE',
        COL_DECIMAL         = 'CURR_DECIMAL'
    ;

    const
        CUR_USD = 'USD'
    ;
    
    public function fetchAll()
    {
    	$sql = "SELECT * FROM CURRENCY ORDER BY curr_name ASC";
    	$rows = $this->db->fetchAll($sql);
    	return $rows;
    	
    	 
    }

	public function fetchByCode ($code)
	{
		$sql = "SELECT * FROM CURRENCY WHERE CURR_CODE = :code";
		$rows = $this->db->fetchAll($sql, array('code' => $code));
		if ($rows)
		{
			return $rows[0];
		}
		throw new Exception("Currency code not found");
	}

    /**
     * Helper function to retrieve rate exchange properties
     *
     * @author  Yuriy Akopov
     * @date    2013-10-13
     *
     * @param   string  $fromCode
     * @param   string $toCode
     *
     * @return  array
     */
    public static function fetchForExchange($fromCode, $toCode = self::CUR_USD) {
        $select = new Zend_Db_Select(self::getDb());
        $select
            ->from(
                array('c' => self::TABLE_NAME),
                'c.*'
            )
            ->where('c.' . self::COL_ID . ' = :from_code')
            ->where('c.' . self::COL_EXCHANGE_WITH . ' = :to_code')
        ;

        $row = $select->getAdapter()->fetchRow($select, array(
            'from_code' => $fromCode,
            'to_code'   => $toCode
        ));

        return $row;
    }

    /**
     * @author  Yuriy Akopov
     * @date    2014-01-15
     *
     * @param   Shipserv_Quote|Shipserv_PurchaseOrder   $transaction
     * @param   bool                                    $total
     * @param   string                                  $convertTo
     *
     * @return  float|null
     * @throws  Exception
     */
    public static function convertTransactionCost($transaction, $total = true, $convertTo = self::CUR_USD) {
        // @todo: the block below can be replace with some getPrice() in Shipserv_Transaction overridden further, but that's bigger redesign
        if ($transaction instanceof Shipserv_Quote) {
            $convertFrom = $transaction->qotCurrency;
            $date = $transaction->qotSubmittedDate; // DE6362: changed from created date on 2016-01-25

            if ($total) {
                $amount = $transaction->qotTotalCost;
            } else {
                $amount = $transaction->qotUnitCost;
            }
        } else if ($transaction instanceof Shipserv_PurchaseOrder) {
            $convertFrom = $transaction->ordCurrency;
            $date = $transaction->ordSubmittedDate; // DE6362: changed from created date on 2016-01-25

            if ($total) {
                $amount = $transaction->ordTotalCost;
            } else {
                $amount = $transaction->ordUnitCost;
            }
        } else {
            throw new Exception("Unknown transaction, unable to convert between currencies");
        }

        if ($convertTo === $convertFrom) {
            return $amount;
        }

        if ($date instanceof Shipserv_Oracle_Util_DbTime) {
            $date = new DateTime('@' . $date->getTimestamp());
        } else if (strlen($date)) {
            $date = new DateTime($date);
        }

        // first check for an exchange rate for the particular date of transaction
        $db = self::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('ch' => 'currency_history'),
                'ch.currh_exchange_rate'
            )
            ->where('ch.currh_code = ?', $convertFrom)
            ->where('ch.currh_exchange_with = ?', $convertTo)
        ;

        $selectExact = clone($select);
        $selectExact->where('TRUNC(ch.currh_created_date) = TRUNC(' . Shipserv_Helper_Database::getOracleDateExpr($date, true) . ')');

        $rate = $db->fetchOne($selectExact);

        if (strlen($rate) === 0) {
            // if no rate found for that particular date, check for the most recent date available
            $select
                ->where('ch.currh_created_date <= ' . Shipserv_Helper_Database::getOracleDateExpr($date, true))
                ->order('ch.currh_created_date DESC')
            ;
            $rate = $db->fetchOne($select);

            if (strlen($rate) === 0) {
                // still no rate available
                return null;
            }
        }

        $rate = (float) $rate;
        if ($rate === 0.0) {
            return null;
        }

        $convertedAmount = $amount / $rate;
        return $convertedAmount;
    }
}
