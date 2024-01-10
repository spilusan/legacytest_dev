<?php

abstract class Myshipserv_NotificationManager_Email_Abstract
{
	protected $db;

	protected $companyTypeMap = array('BYO' => 'b', 'SPB' => 'v');

	public $enableSMTPRelay = false;

	public function __construct ($db)
	{
		$this->db = $db;
	}

	/**
	 * Return type is: array(0 => array('name' => <string> 'email' => <string>), ...)
	 *
	 * @return array
	 */
	abstract public function getRecipients ();

	/**
	 * @return string
	 */
	abstract public function getSubject ();

	/**
	 * Return type is: array(<recipient_email> => <string>, ...)
	 *
	 * @return array
	 */
	abstract public function getBody ();

	public function getTransport()
	{
		if ($this->enableSMTPRelay)
		{
			$c = Myshipserv_Config::getIni();

			$config = array(
				'auth' => $c->jangosmtp->auth,
				'username' => $c->jangosmtp->username,
				'password' => $c->jangosmtp->password);

			$res =  new Zend_Mail_Transport_Smtp($c->jangosmtp->host, $config);

			return $res;
		}
		else
		{
			return null;
		}
	}

	protected function getCompany($type, $id, $skipCheck = false)
	{
		switch ($type)
		{
			case 'BYO':
				$bDao = new Shipserv_Oracle_BuyerOrganisations($this->db);
				$bArr = $bDao->fetchBuyerOrgById($id);
				return $this->makeCompanyArr('BYO', $bArr['BYO_ORG_CODE'], $bArr['BYO_NAME']);

			case 'SPB':
				$sDao = new Shipserv_Oracle_Suppliers($this->db);
				$sArr = $sDao->fetchSupplierById($id, false, $skipCheck);
				return $this->makeCompanyArr('SPB', $sArr['SPB_BRANCH_CODE'], $sArr['SPB_NAME']);

			default:
				throw new Exception("Unrecognised company type: '$type'");
		}
	}

	protected function getUser ($userId)
	{
		$uDao = new Shipserv_Oracle_User($this->db);
		$res = $uDao->fetchUserById($userId);
		return $res;
	}

	protected function getUrlHelper ()
	{
		return Zend_Controller_Action_HelperBroker::getStaticHelper('Url');
	}

	protected function makeCompanyLink ($user = null)
	{
		$paramArr = array();
		if ($user) $paramArr['u'] = $user->userId;

		$relUrl = $this->makeLinkPath('companies', 'profile', null, $paramArr);
		return 'https://' . $_SERVER['HTTP_HOST'] . $relUrl;
	}

	/**
	 * Creates a relative URL path from parameters.
	 *
	 * Note: conscious decision not to use Zend URL helper - handles empty modules inadequately.
	 * Note: conscious decision not to use '?&' style parameters - these mess up some encoding/redirection practices used by the app.
	 *
	 * @return string
	 */
	protected function makeLinkPath ($action, $controller, $module, array $paramArr)
	{
		$url = '';

		if ($module != '')
		{
			$url .= '/' . $module;
		}

		$url .= '/' . $controller . '/' . $action;

		foreach ($paramArr as $pn => $pv)
		{
			$url .= '/' . urlencode($pn) . '/' . urlencode($pv);
		}

		return $url;
	}

	protected function getView ()
	{
		$view = new Zend_View();
		$view->setScriptPath(APPLICATION_PATH . "/views/scripts/");
		return $view;
	}

	private function makeCompanyArr ($type, $id, $name)
	{
		return compact('type', 'id', 'name');
	}


    /**
     * @return Zend_Db_Adapter_Oracle
     */
    protected  static function getDb()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}

	public function getHash()
	{
		$data[] = json_encode($this->getRecipients());
		$data[] = json_encode($this->getSubject());
		$data[] = json_encode($this->getBody());

		return md5(implode("--", $data));
	}

    /**
     * Returns buyer email array as many emails are sent to buyers
     *
     * @param $buyerBranchId
     */
    protected static function getBuyerBranchRecipients($buyerBranchId) {
        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
                array(
                    'NAME'  => 'byb.' . Shipserv_Buyer_Branch::COL_NAME,
                    'EMAIL' => 'byb.' . Shipserv_Buyer_Branch::COL_EMAIL
                )
            )
            ->where('byb.' . Shipserv_Buyer_Branch::COL_ID . ' = ?', $buyerBranchId)
        ;

        $row = $db->fetchRow($select);
        if ($row === false) {
            throw new Myshipserv_NotificationManager_Email_Exception("No email found for buyer branch " . $buyerBranchId);
        }

        if (!filter_var($row['EMAIL'], FILTER_VALIDATE_EMAIL)) {
            throw new Myshipserv_NotificationManager_Email_Exception("Email " . $row['EMAIL'] . " specified for buyer branch " . $buyerBranchId . " is not valid");
        }

        return array(
            Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => $row['NAME'],
            Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => $row['EMAIL']
        );
    }

    protected function isValidEmail($email){
    	return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    protected function getRootUrl() {
    	return Myshipserv_Config::getApplicationProtocol() . '://' . Myshipserv_Config::getApplicationHostName();
    }
}
