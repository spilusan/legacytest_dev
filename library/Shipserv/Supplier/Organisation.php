<?php
/**
 * Was initially required as merely a class to hold an ID so where supplier organisation or branch is required we could
 * supply Shipserv_Supplier and Shipserv_Supplier_Organisation instances defining the context of the ID given by the type
 * (instead of two parameters e.g. $isOrg and $id)
 *
 * Later this class is supposed to grow and be an example of a new style approach to system entity classes (as opposed tp
 * legacy Shipserv_Supplier, for example)
 *
 * @author  Yuriy Akopov
 * @date    2014-05-30
 */
class Shipserv_Supplier_Organisation extends Shipserv_Object implements Shipserv_Helper_Database_Object {
    const
        TABLE_NAME = 'SUPPLIER_ORGANISATION',

        COL_ID                  = 'SUP_ORG_CODE',
        COL_NAME                = 'SUP_SUPPLIER_NAME',
        COL_BRANCH_COUNT        = 'SUP_NO_OF_BRANCHES',
        COL_USER_COUNT          = 'SUP_NO_OF_USERS',
        COL_TYPE                = 'SUP_TYPE',

        COL_CONTACT_NAME        = 'SUP_CONTACT_NAME',
        COL_CONTACT_ADDRESS1    = 'SUP_CONTACT_ADDRESS1',
        COL_CONTACT_ADDRESS2    = 'SUP_CONTACT_ADDRESS2',
        COL_CONTACT_CITY        = 'SUP_CONTACT_CITY',
        COL_CONTACT_STATE       = 'SUP_CONTACT_STATE',
        COL_CONTACT_POSTCODE    = 'SUP_CONTACT_ZIP_CODE',
        COL_CONTACT_COUNTRY     = 'SUP_CONTACT_COUNTRY',
        COL_CONTACT_PHONE1      = 'SUP_CONTACT_PHONE_NO1',
        COL_CONTACT_PHONE2      = 'SUP_CONTACT_PHONE_NO2',
        COL_CONTACT_EMAIL       = 'SUP_CONTACT_EMAIL',
        COL_CONTACT_FAX         = 'SUP_CONTACT_FAX',

        COL_CREATED_BY          = 'SUP_CREATED_BY',
        COL_DATE_CREATED        = 'SUP_CREATED_DATE',
        COL_UPDATED_BY          = 'SUP_UPDATED_BY',
        COL_DATE_UPDATED        = 'SUP_UPDATED_DATE',

        COL_IS_TRANS_HISTORY_SEARCH = 'SUP_IS_TRANS_HISTORY_SEARCH'
    ;

    /**
     * @var int|null
     */
    protected $id = null;
    /**
     * @var string|null
     */
    protected $name = null;
    /**
     * @var int|null
     */
    protected $branchCount = null;
    /**
     * @var int null
     */
    protected $userCount = null;
    /**
     * @var string|null
     */
    protected $contactName = null;
    /**
     * @var string|null
     */
    protected $contactAddress1 = null;
    /**
     * @var string|null
     */
    protected $contactAddress2 = null;
    /**
     * @var string|null
     */
    protected $contactCity = null;
    /**
     * @var string|null
     */
    protected $contactState = null;
    /**
     * @var string|null
     */
    protected $contactPostcode = null;
    /**
     * @var string|null
     */
    protected $contactCountry = null;
    /**
     * @var string|null
     */
    protected $contactPhone1 = null;
    /**
     * @var string|null
     */
    protected $contactPhone2 = null;
    /**
     * @var string|null
     */
    protected $contactEmail = null;
    /**
     * @var string|null
     */
    protected $contactFax = null;
    /**
     * @var int|null
     */
    protected $createdBy = null;
    /**
     * @var string|null
     */
    protected $dateCreated = null;
    /**
     * @var int|null
     */
    protected $updatedBy = null;
    /**
     * @var string|null
     */
    protected $dateUpdated = null;
    /**
     * @var int|null
     */
    protected $isTransHistorySearch = null;

    /**
     * @param int|null $branchCount
     */
    public function setBranchCount($branchCount)
    {
        $this->branchCount = $branchCount;
    }

    /**
     * @return int|null
     */
    public function getBranchCount()
    {
        return $this->branchCount;
    }

    /**
     * @param null|string $contactAddress1
     */
    public function setContactAddress1($contactAddress1)
    {
        $this->contactAddress1 = $contactAddress1;
    }

    /**
     * @return null|string
     */
    public function getContactAddress1()
    {
        return $this->contactAddress1;
    }

    /**
     * @param null|string $contactAddress2
     */
    public function setContactAddress2($contactAddress2)
    {
        $this->contactAddress2 = $contactAddress2;
    }

    /**
     * @return null|string
     */
    public function getContactAddress2()
    {
        return $this->contactAddress2;
    }

    /**
     * @param null|string $contactCity
     */
    public function setContactCity($contactCity)
    {
        $this->contactCity = $contactCity;
    }

    /**
     * @return null|string
     */
    public function getContactCity()
    {
        return $this->contactCity;
    }

    /**
     * @param null|string $contactCountry
     */
    public function setContactCountry($contactCountry)
    {
        $this->contactCountry = $contactCountry;
    }

    /**
     * @return null|string
     */
    public function getContactCountry()
    {
        return $this->contactCountry;
    }

    /**
     * @param null|string $contactEmail
     */
    public function setContactEmail($contactEmail)
    {
        $this->contactEmail = $contactEmail;
    }

    /**
     * @return null|string
     */
    public function getContactEmail()
    {
        return $this->contactEmail;
    }

    /**
     * @param null|string $contactFax
     */
    public function setContactFax($contactFax)
    {
        $this->contactFax = $contactFax;
    }

    /**
     * @return null|string
     */
    public function getContactFax()
    {
        return $this->contactFax;
    }

    /**
     * @param null|string $contactName
     */
    public function setContactName($contactName)
    {
        $this->contactName = $contactName;
    }

    /**
     * @return null|string
     */
    public function getContactName()
    {
        return $this->contactName;
    }

    /**
     * @param null|string $contactPhone1
     */
    public function setContactPhone1($contactPhone1)
    {
        $this->contactPhone1 = $contactPhone1;
    }

    /**
     * @return null|string
     */
    public function getContactPhone1()
    {
        return $this->contactPhone1;
    }

    /**
     * @param null|string $contactPhone2
     */
    public function setContactPhone2($contactPhone2)
    {
        $this->contactPhone2 = $contactPhone2;
    }

    /**
     * @return null|string
     */
    public function getContactPhone2()
    {
        return $this->contactPhone2;
    }

    /**
     * @param null|string $contactPostcode
     */
    public function setContactPostcode($contactPostcode)
    {
        $this->contactPostcode = $contactPostcode;
    }

    /**
     * @return null|string
     */
    public function getContactPostcode()
    {
        return $this->contactPostcode;
    }

    /**
     * @param null|string $contactState
     */
    public function setContactState($contactState)
    {
        $this->contactState = $contactState;
    }

    /**
     * @return null|string
     */
    public function getContactState()
    {
        return $this->contactState;
    }

    /**
     * @param int|null $createdBy
     */
    public function setCreatedBy($createdBy)
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return int|null
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @param null|string $dateCreated
     */
    public function setDateCreated($dateCreated)
    {
        $this->dateCreated = $dateCreated;
    }

    /**
     * @return null|string
     */
    public function getDateCreated()
    {
        return $this->dateCreated;
    }

    /**
     * @param null|string $dateUpdated
     */
    public function setDateUpdated($dateUpdated)
    {
        $this->dateUpdated = $dateUpdated;
    }

    /**
     * @return null|string
     */
    public function getDateUpdated()
    {
        return $this->dateUpdated;
    }

    /**
     * @param int|null $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int|null $isTransHistorySearch
     */
    public function setIsTransHistorySearch($isTransHistorySearch)
    {
        $this->isTransHistorySearch = $isTransHistorySearch;
    }

    /**
     * @return int|null
     */
    public function getIsTransHistorySearch()
    {
        return $this->isTransHistorySearch;
    }

    /**
     * @param null|string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return null|string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param int|null $updatedBy
     */
    public function setUpdatedBy($updatedBy)
    {
        $this->updatedBy = $updatedBy;
    }

    /**
     * @return int|null
     */
    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }

    /**
     * @param int $userCount
     */
    public function setUserCount($userCount)
    {
        $this->userCount = $userCount;
    }

    /**
     * @return int
     */
    public function getUserCount()
    {
        return $this->userCount;
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
        $this->id           = $fields[self::COL_ID];
        $this->name         = $fields[self::COL_NAME];
        $this->branchCount  = $fields[self::COL_BRANCH_COUNT];
        $this->userCount    = $fields[self::COL_USER_COUNT];

        $this->contactName      = $fields[self::COL_CONTACT_NAME];
        $this->contactAddress1  = $fields[self::COL_CONTACT_ADDRESS1];
        $this->contactAddress2  = $fields[self::COL_CONTACT_ADDRESS2];
        $this->contactCity      = $fields[self::COL_CONTACT_CITY];
        $this->contactState     = $fields[self::COL_CONTACT_STATE];
        $this->contactPostcode  = $fields[self::COL_CONTACT_POSTCODE];
        $this->contactCountry   = $fields[self::COL_CONTACT_COUNTRY];
        $this->contactPhone1    = $fields[self::COL_CONTACT_PHONE1];
        $this->contactPhone2    = $fields[self::COL_CONTACT_PHONE2];
        $this->contactEmail     = $fields[self::COL_CONTACT_EMAIL];
        $this->contactFax       = $fields[self::COL_CONTACT_FAX];

        $this->createdBy    = $fields[self::COL_CREATED_BY];
        $this->dateCreated  = $fields[self::COL_DATE_CREATED];
        $this->updatedBy    = $fields[self::COL_UPDATED_BY];
        $this->dateUpdated  = $fields[self::COL_DATE_UPDATED];

        $this->isTransHistorySearch = $fields[self::COL_IS_TRANS_HISTORY_SEARCH];
    }

    /**
     * Returns current field values mapped to database columns
     *
     * @return array
     */
    public function toDbRow() {
        $fields = array(
            self::COL_ID            => $this->id,
            self::COL_NAME          => $this->name,
            self::COL_BRANCH_COUNT  => $this->branchCount,
            self::COL_USER_COUNT    => $this->userCount,

            self::COL_CONTACT_NAME      => $this->contactName,
            self::COL_CONTACT_ADDRESS1  => $this->contactAddress1,
            self::COL_CONTACT_ADDRESS2  => $this->contactAddress2,
            self::COL_CONTACT_CITY      => $this->contactCity,
            self::COL_CONTACT_STATE     => $this->contactState,
            self::COL_CONTACT_POSTCODE  => $this->contactPostcode,
            self::COL_CONTACT_COUNTRY   => $this->contactCountry,
            self::COL_CONTACT_PHONE1    => $this->contactPhone1,
            self::COL_CONTACT_PHONE2    => $this->contactPhone2,
            self::COL_CONTACT_EMAIL     => $this->contactEmail,
            self::COL_CONTACT_FAX       => $this->contactFax,

            self::COL_CREATED_BY    => $this->createdBy,
            self::COL_DATE_CREATED  => $this->dateCreated,
            self::COL_UPDATED_BY    => $this->updatedBy,
            self::COL_DATE_UPDATED  => $this->dateUpdated,

            self::COL_IS_TRANS_HISTORY_SEARCH => $this->isTransHistorySearch
        );

        return $fields;
    }

    /**
     * Retrieves buyer organisation by specified id
     *
     * @param   int     $id
     *
     * @return  Shipserv_Supplier_Organisation_Base
     * @throws  Exception
     */
    public static function getInstanceById($id) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('byo' => self::TABLE_NAME),
                'byo.*'
            )
            ->where('byo.' . self::COL_ID . ' = ?', $id)
        ;

        $row = $db->fetchRow($select);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested supplier organisation cannot be found');
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
            throw new Shipserv_Helper_Database_Exception("No user provided to store supplier organisation update info");
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
     * Returns all the associated supplier branches as raw IDs or instantiated
     *
     * @param bool $idsOnly
     *
     * @return Shipserv_Supplier[]|array
     */
    public function getBranches($idsOnly = false) {
        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('spb' => Shipserv_Supplier::TABLE_NAME),
                'spb.' . Shipserv_Supplier::COL_ID
            )
            ->where('spb.' . Shipserv_Supplier::COL_ORG_ID . ' = ?', $this->getId())
        ;
        $rows = $select->getAdapter()->fetchAll($select);

        $results = array();
        foreach ($rows as $row) {
            if ($idsOnly) {
                $results[] = $row[Shipserv_Supplier::COL_ID];
            } else {
                $spb = Shipserv_Supplier::getInstanceById($row[Shipserv_Supplier::COL_ID]);
                if ($spb->tnid > 0) {
                    $results[] = $spb;
                }
            }
        }

        return $results;
    }
}