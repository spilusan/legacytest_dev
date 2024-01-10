<?php

class Myshipserv_NotificationManager_Email_BrandAuthRequestConsolidated extends Myshipserv_NotificationManager_Email_Abstract
{
    /**
     * @var Shipserv_BrandAuthorisation[]
     */
    private $brandAuthRequests = null;

    /**
     * @param   Zend_Db_Adapter_Oracle  $db
     * @param   Shipserv_BrandAuthorisation|Shipserv_BrandAuthorisation[]   $brandAuthRequest
     */
    public function __construct ($db, $brandAuthRequest){
		parent::__construct($db);

        if (!is_array($brandAuthRequest)) {
            $brandAuthRequest = array($brandAuthRequest);
        }

		$this->brandAuthRequests = $brandAuthRequest;
	}

	public function getRecipients() {
		$res = array();
		$ucDom = $this->getUserCompanyDomain();

		foreach($this->brandAuthRequests as $brandAuthRequest) {
			//retrieve list of companies - brand owners
			foreach (Shipserv_BrandAuthorisation::getBrandOwners($brandAuthRequest->brandId) as $brandOwnerCompanyId) {
				$uColl = $ucDom->fetchUsersForCompany('SPB', $brandOwnerCompanyId);
	
				foreach ($uColl->getAdminUsers() as $u) {
					$row = array(
						'email'     => $u->email,
						'name'	    => $u->firstName.' '.$u->lastName,
						'companyId'	=> $brandOwnerCompanyId
					);

					$res[$u->email] = $row;
				}
			}
		}

		return $res;
	}

	public function getSubject ()
	{
		return 'You have new brand authorisation request on ShipServ Pages';
	}

	public function getBody() {
		$view = $this->getView();
        $view->brandAuthRequests = $this->brandAuthRequests;

		$body = array();
		foreach ($this->getRecipients() as $recipient) {
			$view->brandLink = $this->makeLinkToBrandsPage($recipient["companyId"]);

			$body[$recipient["email"]] = $view->render('email/request-brand-auth-consolidated.phtml');
		}

		return $body;
	}

	/**
	 * @return string
	 */
	private function makeLinkToBrandsPage ($companyId) {
		$params = array('type' => 'v', 'id' => $companyId);
		$link = 'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('company-brands', 'profile', null, $params)."?brand=" . $this->brandAuthRequests[0]->brandId;

		return $link;
	}

	private function getUserCompanyDomain() {
		return new Myshipserv_UserCompany_Domain($this->db);
	}
}
