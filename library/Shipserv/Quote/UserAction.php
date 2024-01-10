<?php
/**
 * Manages actions users perform over quotes
 *
 * @author  Yuriy Akopov
 * @date    2014-01-29
 * @story   S9231
 */
class Shipserv_Quote_UserAction {
    const
        ACTION_READ = 'read',
        ACTION_STATS_EXCLUDE = 'stats_exclude'
    ;

    const
        TABLE_NAME = 'QUOTE_USER_ACTION',

        COL_ID          = 'QUA_ID',
        COL_QUOTE_ID    = 'QUA_QOT_INTERNAL_REF_NO',
        COL_USER_ID     = 'QUA_PSU_ID',
        COL_DATE        = 'QUA_DATE',
        COL_ACTION      = 'QUA_ACTION',
        COL_QUA_QUR_ID  = 'QUA_QUR_ID'
    ;

    const
        RESULT_KEY_USER   = 'user',
        RESULT_KEY_QUOTE  = 'quote',
        RESULT_KEY_DATE   = 'date',
        RESULT_KEY_ACTION = 'action'
    ;

    const
        REASON_TABLE_NAME = 'QUOTE_USER_ACTION_REASON',

        COL_QUR_ID          = 'QUR_ID',
        COL_QUR_ACTION      = 'QUR_ACTION', 
        COL_QUR_REASON      = 'QUR_REASON',
        COL_QUR_ACTIVE      = 'QUR_ACTIVE',
        COL_QUR_DEFAULT     = 'QUR_DEFAULT',
        COL_QUR_ORDER       = 'QUR_ORDER',
        COL_QUR_DESCRIPTION = 'QUR_DESCRIPTION'
   ;

   const 
        QUR_RESULT_ID           = 'id',
        QUR_RESULT_REASON       = 'reason',
        QUR_RESULT_DESCRIPTION  = 'description',
        QUR_RESULT_DEFAULT      = 'isDefault'
   ;

    /**
     * @return Zend_Db_Adapter_Oracle
     */
    protected static function _getDb() {
        return Shipserv_Helper_Database::getDb();
    }

    /**
     * Creates a new record about the user action in the table
     *
     * @param   int     $quoteId
     * @param   int     $userId
     * @param   string  $action
     * @param   bool    $updateLastAction
     *
     * @return  int
     * @throws  Shipserv_Helper_Database_Exception
     */
    protected static function _saveAction($quoteId, $userId, $action, $updateLastAction = true, $reasonId = null) {
        $db = self::_getDb();

        if ($updateLastAction) {
            // if a record already exists, just update the date and user
            // if not, create a new record
            $sql = implode(' ', array(
                'MERGE INTO ' . self::TABLE_NAME . ' USING dual ON (',
                implode(' AND ', array(
                    $db->quoteInto(self::COL_QUOTE_ID . ' = ?', $quoteId),
                    // $db->quoteInto(self::COL_USER_ID . ' = ?', $userId),
                    $db->quoteInto(self::COL_ACTION . ' = ?', $action),
                )),
                ') WHEN MATCHED THEN UPDATE SET',
                implode(', ', array(
                    $db->quoteInto(self::COL_USER_ID . ' = ?', $userId),
                    $db->quoteInto(self::COL_QUA_QUR_ID . ' = ?', $reasonId),
                    self::COL_DATE . ' = SYSDATE',
                )),
                'WHEN NOT MATCHED THEN INSERT (',
                implode(', ', array(
                    self::COL_QUOTE_ID,
                    self::COL_USER_ID,
                    self::COL_ACTION,
                    self::COL_QUA_QUR_ID,
                    self::COL_DATE,
                )),
                ') VALUES (',
                implode(', ', array(
                    $db->quote($quoteId),
                    $db->quote($userId),
                    $db->quote($action),
                    $db->quote($reasonId),
                    'SYSDATE',
                )),
                ')'
            ));

            $result = $db->query($sql);

        } else {
            // create a new record even a record for the same action already exists
            $result = $db->insert(
                self::TABLE_NAME,
                array(
                    self::COL_QUOTE_ID => $quoteId,
                    self::COL_USER_ID  => $userId,
                    self::COL_DATE     => new Zend_Db_Expr('SYSDATE'),
                    self::COL_ACTION   => $action
                )
            );
        }

        return $result;
    }

    /**
     * Removes all or the last action record for the given quote
     *
     * @date    2014-08-05
     * @story   S101172
     *
     * @param   int     $quoteId
     * @param   string  $action
     * @param   bool    $undoAll
     *
     * @return int
     */
    protected static function _undoAction($quoteId, $action, $undoAll = true) {
        $db = self::_getDb();

        $where = array(
            $db->quoteInto(self::COL_QUOTE_ID . ' = ?', $quoteId),
            $db->quoteInto(self::COL_ACTION . ' = ?', $action)
        );

        if (!$undoAll) {
            // remove only last action record
            $select = new Zend_Db_Select($db);
            $select
                ->from(
                    array('qua' => self::TABLE_NAME),
                    new Zend_Db_Expr('MAX(' . self::COL_ID . ')')
                )
                ->where(implode(' AND ', $where))
            ;

            $where[] = self::COL_ID . ' IN (' . $select->assemble() . ')';
        }

        $result = $db->delete(self::TABLE_NAME, $where);

        return $result;
    }

    /**
     * Returns current actions along with their latest dates recorded for the given quite
     *
     * @date    2014-08-05
     * @story   S101172
     *
     * @param   int                 $quoteId
     * @param   string|array|null   $onlyActions
     *
     * @return  array
     */
    protected static function _getActions($quoteId, $onlyActions = null) {
        if (!is_null($onlyActions)) {
            if (!is_array($onlyActions)) {
                $onlyActions = array($onlyActions);
            }
        }

        $db = self::_getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('qua' => self::TABLE_NAME),
                array(
                    self::COL_ACTION => 'qua.' . self::COL_ACTION,
                    self::COL_DATE   => new Zend_Db_Expr("TO_CHAR(MAX(qua." . self::COL_DATE . "), 'YYYY-MM-DD HH24:MI:SS')")
                )
            )
            ->where('qua.' . self::COL_QUOTE_ID . ' = ?', $quoteId)
            ->group('qua.' . self::COL_ACTION)
        ;

        if (!is_null($onlyActions)) {
            $select->where('qua.' . self::COL_ACTION . ' IN (?)', $onlyActions);
        }

        $rows = $db->fetchAll($select);
        if (empty($rows)) {
            return array();
        }

        $data = array();
        foreach ($rows as $row) {
            $data[] = array(
                $row[self::COL_ACTION] => new DateTime($row[self::COL_DATE])
            );
        }

        return $data;
    }

    /**
     * Marks (unmarks) the given RFQ as excluded from stats
     *
     * @param   bool            $exclude
     * @param   Shipserv_Quote  $quote
     * @param   Shipserv_User   $user
     * @param   int             $responseId
     */
    public static function setStatsExclude($exclude, Shipserv_Quote $quote, Shipserv_User $user, $reasonId = null) {
        if ($exclude) {
            self::_saveAction($quote->qotInternalRefNo, $user->userId, self::ACTION_STATS_EXCLUDE, true, $reasonId);
        } else {
            self::_undoAction($quote->qotInternalRefNo, self::ACTION_STATS_EXCLUDE);
        }
    }

    /**
     * Returns true if the given quote is marked as excluded from stats
     *
     * @date    2014-08-05
     * @story   S101172
     *
     * @param   Shipserv_Quote $quote
     *
     * @return  bool
     */
    public static function isStatsExcluded(Shipserv_Quote $quote) {
        $actions = self::_getActions($quote->qotInternalRefNo, self::ACTION_STATS_EXCLUDE);

        return !empty($actions);
    }

    /**
     * Marks the given quote as read by the given user
     *
     * @param   Shipserv_Quote  $quote
     * @param   Shipserv_User   $user
     */
    public static function markAsRead(Shipserv_Quote $quote, Shipserv_User $user) {
        self::_saveAction($quote->qotInternalRefNo, $user->userId, self::ACTION_READ);
    }

    /**
     * Returns the date quote was last read by a user, or null if never
     *
     * @param   Shipserv_Quote  $quote
     * @param   Shipserv_User   $user
     *
     * @return  Shipserv_Oracle_Util_DbTime
     */
    public static function getDateRead(Shipserv_Quote $quote, Shipserv_User $user) {
        $log = self::getActionHistory($quote, $user, self::ACTION_READ);
        if (empty($log)) {
            return null;
        }

        return $log[0][self::RESULT_KEY_DATE];
    }

    /**
     * Returns all the action history changes for the given user and/or quote
     *
     * @param   Shipserv_Quote  $quote
     * @param   Shipserv_User   $user
     * @param   array           $actions
     * @param   DateTime        $dateFrom
     * @param   DateTime        $dateTo
     *
     * @return  array
     * @throws  Exception
     */
    public static function getActionHistory(Shipserv_Quote $quote = null, Shipserv_User $user = null, $actions = null, DateTime $dateFrom = null, DateTime $dateTo = null) {
        if (is_null($quote) and is_null($user)) {
            throw new Exception("You need to specify either quote, or user, or both to get the actions history");
        }

        $db = self::_getDb();
        $params = array();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('qua' => self::TABLE_NAME),
                array(
                    'QUOTE_ID'      => self::COL_QUOTE_ID,
                    'USER_ID'       => self::COL_USER_ID,
                    'ACTION_DATE'   => new Zend_Db_Expr('TO_CHAR(' . self::COL_DATE . ", 'YYYY-MM-DD HH24:MI:SS')"),
                    'ACTION'        => self::COL_ACTION
                )
            )
            ->order(array(
                self::COL_USER_ID,
                self::COL_QUOTE_ID,
                self::COL_DATE . ' DESC'
            ))
        ;

        if ($quote) {
            $select->where('qua.' . self::COL_QUOTE_ID . ' = :quoteId');
            $params['quoteId'] = $quote->qotInternalRefNo;
        }

        if ($user) {
            $select->where('qua.' . self::COL_USER_ID . ' = :userId');
            $params['userId'] = $user->userId;
        }

        if (!is_null($actions)) {
            $select->where('qua.' . self::COL_ACTION . ' IN (?)', $actions);
        }

        if ($dateFrom) {
            $select->where('qua.' . self::COL_DATE . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateFrom));
        }

        if ($dateTo) {
            $select->where('qua.' . self::COL_DATE . ' <= ' . Shipserv_Helper_Database::getOracleDateExpr($dateTo));
        }

        $result = array();
        $rows = $db->fetchAll($select, $params);
        foreach($rows as $actionRow) {
            $result[] = array(
                self::RESULT_KEY_QUOTE  => Shipserv_Quote::getInstanceById($actionRow['QUOTE_ID']),
                self::RESULT_KEY_USER   => Shipserv_User::getInstanceById($actionRow['USER_ID']),
                self::RESULT_KEY_DATE   => $actionRow['ACTION_DATE'],
                self::RESULT_KEY_ACTION => $actionRow['ACTION']
            );
        }

        return $result;
    }

    /**
    * Return a list of exclude reasons
    */
    public static function getExcludeReasons()
    {

        $db = self::_getDb();
        $params = array(
            'qurAction' => self::ACTION_STATS_EXCLUDE
            );

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('qur' => self::REASON_TABLE_NAME),
                array(
                    'QUR_ID'      => self::COL_QUR_ID,
                    'QUR_REASON'  => self::COL_QUR_REASON,
                    'QUR_DESCRIPTION'  => self::COL_QUR_DESCRIPTION,
                    'QUR_DEFAULT' => self::COL_QUR_DEFAULT
                )
            )
            ->order(array(
                self::COL_QUR_ORDER 
            ))
        ;

        $select->where('qur.' . self::COL_QUR_ACTION . ' = :qurAction');
        $select->where('qur.' . self::COL_QUR_ACTIVE . ' = 1');

        $result = array();
        $rows = $db->fetchAll($select, $params);
        foreach($rows as $actionRow) {
            $result[] = array(
                self::QUR_RESULT_ID   => $actionRow['QUR_ID'],
                self::QUR_RESULT_REASON => $actionRow['QUR_REASON'],
                self::QUR_RESULT_DESCRIPTION => $actionRow['QUR_DESCRIPTION'],
                self::QUR_RESULT_DEFAULT => $actionRow['QUR_DEFAULT']
           );
        }


        return $result;
    }

    /**
    * Return the ID of the selected reason for the specific quote
    */
    public static function getExcludeReasonId( $qotInternalRefNo )
    {

        $db = self::_getDb();
        $params = array(
            'qotInternalRefNo' => (int) $qotInternalRefNo
            );

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('qua' => self::TABLE_NAME),
                array(
                    'QUA_QUR_ID'      => self::COL_QUA_QUR_ID
                )
            )

        ;

        $select->where('qua.' . self::COL_QUOTE_ID . ' = :qotInternalRefNo');

        $row = $db->fetchOne($select, $params);
        
        return $row;
    }
}