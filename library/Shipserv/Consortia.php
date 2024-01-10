<?php
/**
 * Kind of active record for the consortia table
 *
 * @package ShipServ
 *
 * @author Attila O
 * @copyright Copyright (c) 2017, ShipServ
 */

class Shipserv_Consortia extends Shipserv_Object
{
    public $ids;
    public $internalRefNo;
    public $name;
    public $branding;
    public $createdBy;
    public $createdDate;
    public $updatedBy;
    public $updatedDate;
    public $validFrom;
    public $validTill;

    protected $db;
    protected $data;

    /**
     * Shipserv_Consortia constructor
     *
     * @author  Attila Olbrich
     *
     * @param   string|array    $id
     * @throws  Myshipserv_Exception_MessagedException
     */
    public function __construct($id)
    {
        $this->ids = (is_array($id)) ? $id : array((int)$id);
        $this->db = $this->getDb();

        $this->data = $this->getConsortiaFromDb();

        $this->internalRefNo = $this->data[0][Shipserv_Oracle_Consortia::COL_ID];
        $this->name          = $this->data[0][Shipserv_Oracle_Consortia::COL_NAME];
        $this->branding      = $this->data[0][Shipserv_Oracle_Consortia::COL_BRANDING];
        $this->createdBy     = $this->data[0][Shipserv_Oracle_Consortia::COL_CREATED_BY];
        $this->createdDate   = $this->data[0][Shipserv_Oracle_Consortia::COL_CREATED_DATE];
        $this->updatedBy     = $this->data[0][Shipserv_Oracle_Consortia::COL_UPDATED_BY];
        $this->updatedDate   = $this->data[0][Shipserv_Oracle_Consortia::COL_UPDATED_DATE];

        $status = $this->getConsortiaStatusFromDb($this->internalRefNo);

        $this->validFrom = ($status) ? $status[0][Shipserv_Oracle_Consortia_Status::COL_VALID_FROM] : null;
        $this->validTill = ($status) ? $status[0][Shipserv_Oracle_Consortia_Status::COL_VALID_TILL] : null;
    }

    /**
     * Read consortia data from the DB
     *
     * @author  Attila Olbrich
     *
     * @return array
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function getConsortiaFromDb()
    {
        $select = new Zend_Db_Select($this->db);
        $select
            ->from(
                array('con' => Shipserv_Oracle_Consortia::TABLE_NAME),
                'con.*'
            )
            ->where('con.' . Shipserv_Oracle_Consortia::COL_ID . ' IN (?)', $this->ids);

        $data = $this->db->fetchAll($select);
        if (!$data) {
            throw new Myshipserv_Exception_MessagedException('Consortia company does not exists');
        }

        return $data;

    }

    /**
     * Returns with the actual consortia status
     *
     * @author  Attila Olbrich
     *
     * @param int $id
     * @return array
     */
    protected function getConsortiaStatusFromDb($id)
    {

        $select = new Zend_Db_Select($this->db);
        $select
            ->from(
                array('cst' => Shipserv_Oracle_Consortia_Status::TABLE_NAME),
                'cst.*'
            )
            ->where('cst.' . Shipserv_Oracle_Consortia_Status::COL_CONSORTIA_ID . ' = (?)', $id)
            ->where('cst.' . Shipserv_Oracle_Consortia_Status::COL_VALID_FROM . ' <= SYSDATE')
            ->where('cst.' . Shipserv_Oracle_Consortia_Status::COL_VALID_TILL . ' >= SYSDATE');

        $data = $this->db->fetchAll($select);

        return $data;
    }

    /**
     * Get the consortia companies as an array
     *
     * @author  Attila Olbrich
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns a new object of Consortia record
     *
     * @author  Attila Olbrich
     *
     * @param int|array $id
     * @return Shipserv_Consortia
     */
    public static function getConsortiaInstanceById($id)
    {
        return new self($id);
    }

    /**
     * Return a list of consortia companies
     *
     * @author  Attila Olbrich
     *
     * @param array $ids
     * @return array
     */
    public static function getConsortiaInstanceByIds($ids)
    {
        $consortia = new self($ids);
        return $consortia->getData();
    }

    /**
     * Lazy populating the pages_company for Consortia
     *
     * @author  Attila Olbrich
     *
     * @param int $id
     */
    public static function addConsortiaToPagesCompany($id)
    {
        $db = $GLOBALS['application']->getBootstrap()->getResource('db');

        $sql = "
            MERGE INTO PAGES_COMPANY USING DUAL ON (PCO_TYPE = 'CON' AND PCO_ID = :pcoId)
			WHEN NOT MATCHED THEN
				INSERT (PCO_TYPE, PCO_ID, PCO_ANONYMISED_NAME)
					VALUES ('CON', :pcoId, 'Consortia Company')";

        $params = array(
            'pcoId' => (int) $id
        );

        $db->query($sql, $params);

    }

    /**
     * Get list of consortia companies by a list of keyword(s)
     *
     * @param string $input
     * @return array|string
     */
    public static function getListOfCompanyByKeyword($input)
    {
        $db = $GLOBALS['application']->getBootstrap()->getResource('db');

        $keyword = strtolower($input);
        $conditionalForOr = array();
        $implodedKeywordWithAnd = str_replace(" ", " AND ", $keyword);
        //$escapedKeyword = "{" . $keyword . "}";
        $words = explode(" ", $keyword);
        if (count($words) === 0) $words[] = $keyword;
        foreach ($words as $word) {
            $conditionalForOr['con_consortia_name'][] = " LOWER(con_consortia_name) LIKE '%" . $word . "%'";
        }

        //@todo uncomment the lines in SQL afer adding a fuzzy index. and uncomment the parameter escapekeyword
        $sql = "
					SELECT * FROM
					(
			            SELECT
			              DISTINCT
			                RN, VALUE, DISPLAY, NON_DISPLAY, LOCATION, CODE, PK
			            FROM
			            (
							SELECT
								/*+ index(buyer_branch IDX_BYB_FUZZY FIRST_ROWS(30))*/
								rownum rn
								, con_consortia_name AS VALUE
								, con_internal_ref_no || ': ' || REPLACE(con_consortia_name, :keyword, '<em>') || :keyword  as DISPLAY
								, con_consortia_name || ', ID: ' || con_internal_ref_no  as NON_DISPLAY
								, null as LOCATION
								, 'CON-' || con_internal_ref_no AS CODE
								, con_internal_ref_no AS PK
							FROM
								consortia
					       WHERE
							    (
                                    --CONTAINS (con_consortia_name, 'fuzzy(' || :escapedKeyword || ',60,100,weight)', 10) > 0
                                    --OR CONTAINS (con_consortia_name, '" . $implodedKeywordWithAnd . "') > 0
                                    --OR
                                    (" . implode(" AND ", $conditionalForOr['con_consortia_name']) . ")
                                )
                                OR TO_CHAR(con_internal_ref_no)=:keyword
                        )
			            ORDER BY UPPER(NON_DISPLAY) ASC
				)
				WHERE rn <30
		";

        $params = array(
            'keyword' => $keyword
            //    'escapedKeyword' => $escapedKeyword
        );

        return $db->fetchAll($sql, $params);

    }

    /**
     * Return the consortia login URL with the consortia ID, and the Hash
     *
     * @return string
     */
    public static function getSalesforceLoginUrl()
    {
        $activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        if ($activeCompany->type !== 'c') {
            throw new Myshipserv_Exception_MessagedException("Active company is not consortia.");
        }

        $toHash =  $activeCompany->id . '::' . Myshipserv_Config::getSalesForceCredentials()->shipservApiKey;
        $consortiaUrl = Myshipserv_Config::getSalesForceCredentials()->loiginPageUrl;
        $consortiaUrl .= '?id=' . $activeCompany->id;
        $consortiaUrl .= '&k=' . hash("SHA256", $toHash, false);

        return $consortiaUrl;

    }

}

