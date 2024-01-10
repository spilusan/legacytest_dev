<?php
/*
* Return company selector dontent as an array, 
* Usage, webreporter 
*/
class Shipserv_User_CompanySelectorList 
{
	private $user;

	function __construct() {
		$this->user = Shipserv_User::isLoggedIn();
	}

	/**
	* Get the list, and active company
	*/
	public function getSelecorList()
	{
		$result = array();
		$isShipservUser = ($this->user !== false && $this->user !== null)?$this->user->isShipservUser():false;
		if (is_object($this->user)) {
			$result['loggedIn'] = true;
  			$result['user'] = array(
  					'userId' => $this->user->userId,
  					'username' => $this->user->username,
  					'firstName' => $this->user->firstName,
  					'lastName' => $this->user->lastName,
  					'email' => $this->user->email,
  					'emailConfirmed' => $this->user->emailConfirmed,
  					'isSuper' => $this->user->isSuper,
  					'isShipMate' => $this->user->isShipservUser()
  				);

			$suppliers = $this->user->fetchCompanies()->getSupplierIds();
			$buyers = $this->user->fetchCompanies()->getBuyerIds();

			foreach( $suppliers as $r )
			{
				$supplier = Shipserv_Supplier::fetch( $r, "", true );

				if (!$supplier->isPublished() ) {
					if ($supplier->isNormalised()) {
						$myCompanies[] = array("type" => "v", "name" => $supplier->name, "id" => $supplier->tnid, "value" => "v" . $supplier->tnid );
					}
				} else {
					$myCompanies[] = array("type" => "v", "name" => $supplier->name, "id" => $supplier->tnid, "value" => "v" . $supplier->tnid );
				}
			}

			foreach( $buyers as $r )
			{
				$buyer = Shipserv_Buyer::getInstanceById( $r );
				$myCompanies[] = array("type" => "b", "name" => $buyer->name, "id" => $buyer->id, "value" => "b" . $buyer->id );
			}

			$compCount = count($myCompanies);

			if ($isShipservUser && $this->user->canPerform("PSG_COMPANY_SWITCHER")) {
				$result['manualSelect'] = true; 
				$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
				$result['activeCompany'] = array(
						'companyName' => $activeCompany->company->name ,
						'id' => $activeCompany->id,
						'type' => ($activeCompany->type == 'v') ? 'supplier':'buyer'
					);
			} else {
				$result['manualSelect'] = false; 
				if( $compCount != null) {
					$result['companyCount'] = $compCount;
					if ($compCount  > 0) {
						if ($compCount == 1) {
							$result['activeCompany'] = array(
								'companyName' => $myCompanies[0]['name'] ,
								'id' => $myCompanies[0]['id'],
								'type' => ($myCompanies[0]['type'] == 'v') ? 'supplier':'buyer'
								);
						} else {
							$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
							$result['activeCompany'] = array(
								'companyName' => $activeCompany->company->name ,
								'id' => $activeCompany->id,
								'type' => ($activeCompany->type == 'v') ? 'supplier':'buyer'
								);
						}
							foreach( $myCompanies as $company ){
								if( !empty($company['name']) )
								{
									$result['companies'][] = array(
											'companyName' => $company['name'] ,
											'id' => $company['id'],
											'type' => ($company['type']=="v")?"Supplier":"Buyer"
										);
								}
							}
					}
				} else {
					$result['companyCount'] = 0;
				}
			}
		} else {
			$result['loggedIn'] = false;
		}
		return $result;
	}
}
