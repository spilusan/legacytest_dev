<?php
/**
 * Was initially required as merely a class to hold an ID so where buyer organisation or branch is required we could
 * supply Shipserv_Buyer and Shipserv_Buyer_Branch instances defining the context of the ID given by the type
 * (instead of two parameters e.g. $isOrg and $id)
 *
 * Later this class is supposed to grow and be an example of a new style approach to system entity classes (as opposed tp
 * legacy Shipserv_Buyer, for example)
 *
 * @author  Yuriy Akopov
 * @date    2014-07-01
 */
class Shipserv_Buyer_Branch extends Shipserv_Object implements Shipserv_Helper_Database_Object {
    const
        TABLE_NAME = 'BUYER_BRANCH',

        COL_ID      = 'BYB_BRANCH_CODE',
        COL_ORG_ID  = 'BYB_BYO_ORG_CODE',
        COL_NAME    = 'BYB_NAME',
        COL_EMAIL   = 'BYB_EMAIL_ADDRESS',
        COL_CONTRACT_TYPE = 'BYB_CONTRACT_TYPE',
        COL_STATUS  = 'BYB_STS',
        COL_TEST    = 'BYB_TEST_ACCOUNT',

        COL_UPDATED_BY   = 'BYB_UPDATED_BY',
        COL_DATE_UPDATED = 'BYB_UPDATED_DATE',

        COL_HIDE_CONTACTS = 'BYB_HIDE_CONTACT_DETAIL',

		// S16054 by Yuriy Akopov on 2016-03-14
        COL_MATCH_QUOTE_IMPORT  = 'BYB_ALLOW_MATCH_QOT_IMPORT',
        COL_MTML_BUYER          = 'BYB_MTML_BUYER',
        COL_QS_PROFILE_TYPE     = 'BYB_QS_PROFILE_TYPE',

        // S16094 by Yuriy Akopov on 2016-03-15
        COL_PROMOTE_CHILDREN = 'BYB_PROMOTE_CHILD_BRANCHES',

        // BUY-671 by Yuriy Akopov on 2017-08-29
        COL_COUNTRY = 'BYB_COUNTRY',
        COL_CITY    = 'BYB_CITY'
    ;

    const
        CONTRACT_TYPE_TRIAL        = 'TRIAL',
        CONTRACT_TYPE_CN3          = 'CN3',
        CONTRACT_TYPE_EXC          = 'EXC',
        CONTRACT_TYPE_POM          = 'POM',
        CONTRACT_TYPE_STANDARD     = 'STANDARD',
        CONTRACT_TYPE_CCP          = 'CCP',
        CONTRACT_TYPE_FREEOUTBOUND = 'FREEOUTBOUND'
    ;

    const
        STATUS_ACTIVE = 'ACT',
        STATUS_INACTIVE = 'INA'
    ;

    const
        TEST_YES = 'Y',
        TEST_NO  = 'N'
    ;

    /**
     * @var int
     */
    protected $id = null;

    /**
     * @var int
     */
    protected $orgId = null;

    /**
     * @var string
     */
    protected $name = null;

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $orgId
     */
    public function setOrgId($orgId)
    {
        $this->orgId = $orgId;
    }

    /**
     * @return int
     */
    public function getOrgId()
    {
        return $this->orgId;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Constructor unavailable to public - use static factory methods
     *
     */
    protected function __construct(array $fields = array()) {
        $this->fromDbRow($fields);
    }

    /**
     * Maps supplied database row onto object fields
     *
     * @param   array   $fields
     */
    public function fromDbRow(array $fields) {
        $this->id    = $fields[self::COL_ID];
        $this->name  = $fields[self::COL_NAME];
        $this->orgId = $fields[self::COL_ORG_ID];
    }

    /**
     * Returns current field values mapped to database columns
     *
     * @return array
     */
    public function toDbRow() {
        $fields = array(
            self::COL_ID     => $this->id,
            self::COL_NAME   => $this->name,
            self::COL_ORG_ID => $this->orgId
        );

        return $fields;
    }

    /**
     * Retrieves buyer organisation by specified id
     *
     * @param   int     $id
     *
     * @return  Shipserv_Buyer_Branch
     * @throws  Exception
     */
    public static function getInstanceById($id) {
        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('byb' => self::TABLE_NAME),
                'byb.*'
            )
            ->where('byb.' . self::COL_ID . ' = ?', $id)
        ;

        $row = $db->fetchRow($select);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested buyer branch cannot be found');
        }

        $instance = new self($row);

        return $instance;
    }

    /**
     * Saves or updates the search data into the database
     *
     * @param   Shipserv_User|int   $user
     *
     * @throws Shipserv_Helper_Database_Exception
     */
    public function save($user = null) {
        if (is_null($user)) {
            // we only allow null in declaration to be compatible with the abstract method
            throw new Shipserv_Helper_Database_Exception("No user provided to store buyer branch update info");
        }

        if ($user instanceof Shipserv_User) {
            $userId = $user->userId;
        } else {
            $userId = $user;
        }

        $db = $this->getDb();
        $dbRow = $this->toDbRow();
        $dbRow[self::COL_UPDATED_BY] = $userId;
        $dbRow[self::COL_DATE_UPDATED] = new Zend_Db_Expr('SYSDATE');

        if (strlen($this->id)) {
            // database ID assigned, attempting to update the existing record
            $db->update(self::TABLE_NAME, $dbRow, $db->quoteInto(self::COL_ID . ' = ?', $this->id));
        } else {
            throw new Shipserv_Helper_Database_Exception("No autoincrement ID for table " . self::TABLE_NAME . " so it needs to be specified");
        }
    }

    /**
     * Returns instantiated buyer organisation branch belongs to
     *
     * @param   bool    $skipNormalisation
     *
     * @return  Shipserv_Buyer
     */
    public function getOrganisation($skipNormalisation = false) {
        return Shipserv_Buyer::getInstanceById($this->getOrgId(), $skipNormalisation);
    }

    /**
     * Returns a query that returns buyer branch IDs along with the IDs of the corresponding top parent branch
     *
     * @author  Yuriy Akopov
     * @date    2016-03-16
     * @story   S16094
     *
     * @param   bool    $forReportingDb
     *
     * @return  string
     */
    public static function getTopBranchIdQuery($forReportingDb = false)
    {
        // the query to retrieve top level parent branches is Oracle-specific so cannot be built with Zend
        if ($forReportingDb) {
            return "
                SELECT
                    byb_branch_code,
                    parent_branch_code,
                    CONNECT_BY_ROOT(parent_branch_code) AS top_branch_code
                FROM
                    (
                        SELECT
                            byb_branch_code,
                            parent_branch_code
                        FROM
                            buyer
                    ) branches
                START WITH
                    byb_branch_code = parent_branch_code
                CONNECT BY PRIOR
                    byb_branch_code = parent_branch_code
                    AND byb_branch_code <> parent_branch_code            
            ";
        } else {
            return "
                SELECT
                    byb_branch_code,
                    byb_under_contract AS parent_branch_code,
                    CONNECT_BY_ROOT(byb_under_contract) AS top_branch_code
                FROM
                    (
                        SELECT
                            byb_branch_code,
                            CASE
                                WHEN byb_under_contract IS NULL THEN byb_branch_code
                                ELSE byb_under_contract
                            END AS byb_under_contract
                        FROM
                            buyer_branch
                    ) branches
                START WITH
                    byb_branch_code = byb_under_contract
                CONNECT BY PRIOR
                    byb_branch_code = byb_under_contract
                    AND byb_branch_code <> byb_under_contract
            ";
        }
    }

    /**
     * Returns true if the current branch is a top level branch in the hierarchy
     *
     * @author  Yuriy Akopov
     * @date    2016-03-16
     * @story   S16094
     *
     * @return bool
     */
    public function isTopLevelBranch() {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('branches' => new Zend_Db_Expr('(' . self::getTopBranchIdQuery() . ')')),
                'branches.byb_branch_code'
            )
            ->where('branches.byb_branch_code = ?', $this->getId())
            ->where('branches.byb_branch_code = branches.top_branch_code')
        ;

        $id = $select->getAdapter()->fetchOne($select);
        return (strlen($id) > 0);
    }

    /**
     * For top level branches, returns the list of all underlying child branches (on all levels of hierarchy)
     *
     * @param   bool    $validOnly
     * @param   bool    $includeOwnId
     *
     * @author  Yuriy Akopov
     * @date    2016-03-16
     * @story   S16094
     *
     * @return  array
     * @throws  Exception
     */
    public function getAllBranchesInTheHierarchy($validOnly = true, $includeOwnId = true) {
        $branches = self::getAllBranchesInTheHierarchyBulk($this->getId(), $validOnly, $includeOwnId);
        $ids = $branches[$this->getId()];

        return $ids;
    }

    /**
     * Returns array with top branches IDs as keys and array of child IDs (from all hierarchy levels)
     *
     * @author  Yuriy Akopov
     * @date    2016-03-23
     * @story   DE6509
     *
     * @param   array|int   $topBranchIds
     * @param   bool        $validOnly
     * @param   bool        $includeOwnId
     * @param   bool        $validate
     *
     * @return  array
     * @throws  Exception
     */
    public static function getAllBranchesInTheHierarchyBulk($topBranchIds, $validOnly = true, $includeOwnId = true, $validate = true) {
        if (!is_array($topBranchIds)) {
            $topBranchIds = array($topBranchIds);
        }

        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('branches' => new Zend_Db_Expr('(' . self::getTopBranchIdQuery() . ')')),
                array(
                    'TOP_ID'   => 'branches.top_branch_code',
                    'CHILD_ID' => 'branches.byb_branch_code'
                )
            )
            ->where('branches.top_branch_code IN (?)', $topBranchIds)
            ->order('branches.top_branch_code')
        ;

        if ($validOnly) {
            $select
                ->join(
                    array('byb' => self::TABLE_NAME),
                    'byb.' . self::COL_ID . ' = branches.byb_branch_code',
                    array()
                )
                // only specific contracts
                ->where('byb.' . self::COL_CONTRACT_TYPE . ' IN (?)', array(
                    self::CONTRACT_TYPE_CN3,
                    self::CONTRACT_TYPE_CCP,
                    self::CONTRACT_TYPE_TRIAL,
                    self::CONTRACT_TYPE_STANDARD
                ))
                // no proxy branches
                ->where('byb.' . self::COL_ID . ' NOT IN (?)', array(
                    Myshipserv_Config::getProxyMatchBuyer(),
                    Myshipserv_Config::getProxyPagesBuyer()
                ))
                // only active buyers
                ->where('byb.' . self::COL_STATUS . ' = ?', self::STATUS_ACTIVE)
                // no test branches
                ->where('byb.' . self::COL_TEST . ' = ?', self::TEST_NO)
                // no POM buyers linked to supplier accounts
                ->joinLeft(
                    array('spb' => Shipserv_Supplier::TABLE_NAME),
                    'spb.' . Shipserv_Supplier::COL_POM_BUYER . ' = byb.' . self::COL_ID,
                    array()
                )
                ->where('spb.' . Shipserv_Supplier::COL_ID . ' IS NULL')
            ;
        }

        // rows with two columns of top and child IDs
        $topChildRows = $select->getAdapter()->fetchAll($select);

        // collating by top branch
        $branchInfo = array();
        foreach ($topChildRows as $row) {
            $topBranchId = (int) $row['TOP_ID'];

            if (!array_key_exists($topBranchId, $branchInfo)) {
                $branchInfo[$topBranchId] = array();
            }

            $childBranchId = (int) $row['CHILD_ID'];
            if (!$includeOwnId and ($topBranchId === $childBranchId)) {
                continue;
            }

            $branchInfo[$topBranchId][] = $childBranchId;
        }

        if ($validate) {
            // ensuring the same number of the same top level branches are returned as what has been requested
            foreach ($branchInfo as $topId => $childIds) {
                if (($topIdNo = array_search($topId, $topBranchIds)) !== false) {
                    unset($topBranchIds[$topIdNo]);
                } else {
                    // @todo: do we have a specific exception type for Active Promotion errors?
                    throw new Exception("A top level branch " . $topId . " was found while it wasn't requested");
                }
            }

            if (count($topBranchIds)) {
                // @todo: same as above
                throw new Exception("Supplied branches " . implode(", ", $topBranchIds) . " were not found on the top level");
            }
        }

        return $branchInfo;
    }
}