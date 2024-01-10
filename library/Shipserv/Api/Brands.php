<?php
/**
 * XML-RPC Handler for Brands API
 *
 * Refactored by Yuriy Akopov on 2016-08-25, DE6813
 *
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Api_Brands extends Shipserv_Object
{
	/**
	 * Clears company authorisations / models
	 *
	 * Modified by Yuriy Akopov on 2014-09-11, DE5017
	 * Modified by Yuriy Akopov on 2016-09-01, DE6813
	 *
	 * @param   int                             $companyId
	 * @param   int                             $brandId
	 * @param   array                           $authLevelArray
	 * @param   array                           $modelNameArray
	 * @param   Myshipserv_NotificationManager  $notificationManager
	 *
	 * @return  bool
	 */
	public static function saveCompanyBrand($companyId, $brandId, array $authLevelArray = array(), $modelNameArray = array(), Myshipserv_NotificationManager $notificationManager = null)
	{
		//check if brand is managed (owned)
		$isBrandManaged = Shipserv_BrandAuthorisation::isBrandOwned($brandId);

		$actionTaken = false;   // DE6813, tracking if the action resulted in any actual change

		// step 1: go through existing auth levels and remove ones outside the requested set
		$existingAuths = array();
		foreach (Shipserv_BrandAuthorisation::getCompanyBrandAuthLevels($companyId, $brandId) as $auth) {
			if (!in_array($auth->authLevel, $authLevelArray)) {
				// we do not permanently remove managed statuses of a managed brand that were already authorised, otherwise, the record is physically removed
				// Exception: LST auth level records are removed regardless
				$keepTheRecord = (
					$isBrandManaged and                                                     // brand has authorised owners
					Shipserv_BrandAuthorisation::isAuthLevelManaged($auth->authLevel) and   // record is not about LST level
					($auth->isAuthorised === Shipserv_BrandAuthorisation::AUTH_YES)         // record is authorised
				);
				$auth->remove(!$keepTheRecord);
				$actionTaken = true;
			}

			$existingAuths[] = $auth->authLevel;
		}

		// step 2: determine which levels from the requested set do not exist for the brand yet
		$authDiff = array();
		// loop through authorisation levels which are not already confirmed - created them if necessary
		foreach (array_diff($authLevelArray, $existingAuths) as $authLevel) {
			$authLevel = trim($authLevel);
			if (strlen($authLevel)) {
				try {
					$newAuth = self::requestAuthorisation($companyId, $brandId, $authLevel);
					if ($newAuth->isAuthorised === Shipserv_BrandAuthorisation::AUTH_NO) {
						// only include if we have created a request, not authorised straight away
						$authDiff[] = $newAuth;
						$actionTaken = true;
					}
				} catch (Exception $e) {
					// an exception here means the record is already created, and we are fine with that
				}
			}
		}

		// changed by Yuriy Akopov on 2014-09-11, DE5017
		// if new set of authorisations is not empty and is also different from the previous state...
		if (!empty($authDiff)) {
			// ...request an approval by sending a consolidated email request to confirm all changes

			// $notificationManager->returnAsHtml(); // uncomment for debugging
			$emailContent = $notificationManager->brandAuthorisationRequestInSingleNotification($authDiff);
			// uncomment for debugging
			// print $emailContent[0]; die;
		}
		// changes by Yuriy Akopov end

		//remove all brand models for this company
		Shipserv_CompanyBrandModel::removeAllModels($companyId, $brandId);

		//store provided models
		if (is_array($modelNameArray)) {
			foreach ($modelNameArray as $modelName) {
				$modelName = trim($modelName);
				if (strlen($modelName)) {
					Shipserv_CompanyBrandModel::create($companyId, $brandId, $modelName);
					$actionTaken = true;
				}
			}
		}

		return $actionTaken;
	}

	/**
	 * Refactored by Yuriy Akopov on 2014-09-11, DE5017
	 *
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 *
	 * @return  Shipserv_BrandAuthorisation
	 * @throws  Exception
	 */
	public static function requestAuthorisation($companyId, $brandId, $authLevel)
	{
		if ($auths = Shipserv_BrandAuthorisation::search(
			array(
				'PCB_COMPANY_ID' => $companyId,
				'PCB_BRAND_ID'   => $brandId,
				'PCB_AUTH_LEVEL' => $authLevel
			)
		)) {
			throw new Exception("Brand authorisation is already pending or granted", 1);
		}

		$isBrandManaged = false; // if null, a request will be created, if true, it will be accepted automatically

		if (Shipserv_BrandAuthorisation::isAuthLevelManaged($authLevel)) {
			// non-LST brands are authorised straight away if the brand is not owned
			if (!Shipserv_BrandAuthorisation::isBrandOwned($brandId)) {
				$isBrandManaged = true;
			}
		} else {
			// NULL is a special status for LST requests only
			$isBrandManaged = null;
		}

		$auth = Shipserv_BrandAuthorisation::create($companyId, $brandId, $authLevel, $isBrandManaged);
		return $auth;
	}

	/**
	 * Modified by Yuriy Akopov on 2016-09-01, DE6813
	 *
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 *
	 * @return  bool
	 */
	public static function removeAuthorisation($companyId, $brandId, $authLevel)
	{
		$records = Shipserv_BrandAuthorisation::search(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_BRAND_ID'      => $brandId,
				'PCB_AUTH_LEVEL'    => $authLevel
			)
		);

		$actionTaken = false;

		if (is_array($records)) {
			foreach ($records as $rec) { /** @var $rec Shipserv_BrandAuthorisation */
				$rec->remove();
				$actionTaken = true;
			}
		}

		return $actionTaken;
	}

	/**
	 *
	 * @param   int $companyId
	 * @return  array
	 */
	public static function listAllAuthorisations($companyId)
	{
		$records = Shipserv_BrandAuthorisation::search(
			array(
				'PCB_COMPANY_ID' => $companyId
			)
		);

		$result = array();
		if (is_array($records)) {
			foreach ($records as $rec) {
				$result[] = self::authToArray($rec);
			}
		}

		return $result;
	}

	/**
	 * Modified by Yuriy Akopov on 2016-09-01, DE6813
	 *
	 * @param   int                             $companyId
	 * @param   int                             $brandId
	 * @param   bool                            $isAuthorised
	 * @param   Myshipserv_NotificationManager  $notificationManager
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	public static function createBrandOwner($companyId, $brandId, $isAuthorised, $notificationManager = null)
	{
		$isAuthorisedBool = (strtoupper($isAuthorised) === 'Y');
		$isAuthorisedStr  = $isAuthorisedBool ? 'Y' : 'N';

		$actionTaken = false;   // a bit pointless here because it's always true in the current workflow

		//check if this owner does not already exist for this brand
		if (Shipserv_BrandAuthorisation::search(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_BRAND_ID'      => $brandId,
				'PCB_AUTH_LEVEL'    => Shipserv_BrandAuthorisation::AUTH_LEVEL_OWNER,
				'PCB_IS_AUTHORISED' => $isAuthorisedStr
			)
		)) {
			throw new Exception("Brand authorisation is already pending or granted", 1);
		}

		//check if we have active/inactive authorisation
		if ($auths = Shipserv_BrandAuthorisation::search(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_BRAND_ID'      => $brandId,
				'PCB_AUTH_LEVEL'    => Shipserv_BrandAuthorisation::AUTH_LEVEL_OWNER,
				'PCB_IS_AUTHORISED' => ($isAuthorisedBool) ? 'N' : 'Y'
			)
		)) {
			// there is already a record - switch its state
			$auth = $auths[0];
			if ($isAuthorisedBool) {
				$auth->authorise();
			} else {
				$auth->deauthorise();
			}

			$actionTaken = true;
		} else {
			// do not have auth - create one
			$auth = Shipserv_BrandAuthorisation::create(
				$companyId,
				$brandId,
				Shipserv_BrandAuthorisation::AUTH_LEVEL_OWNER,
				$isAuthorisedBool
			);

			$actionTaken = true;
		}

		$brandOwners = Shipserv_BrandAuthorisation::getBrandOwners($brandId, false);

		//if this is active and first owner of brand - we need to de-authorise all brand auth levels
		if ($isAuthorisedBool and (count($brandOwners) === 1)) {
			if ($auths = Shipserv_BrandAuthorisation::getAuthorisations($brandId)) {
				//we want to send single notification per supplier
				$notifiedCompanies = array();

				foreach ($auths as $auth) {
					//only de-authorise statuses that do not belong to brand owners
					if (!in_array($auth->companyId, $brandOwners)) {
						$auth->deauthorise();

						//send notifications if needed
						if (!is_null($notificationManager)) {
							//send notifications only once
							if (!in_array($auth->companyId, $notifiedCompanies)) {
								$notificationManager->brandAuthorisationPendingApproval($auth, $companyId);
								$notifiedCompanies[] = $auth->companyId;
							}
						}

						$actionTaken = true;
					}
				}
			}
		}

		return $actionTaken;
	}

	/**
	 * Modified by Yuriy Akopov on 2016-09-01, DE6813
	 *
	 *
	 * @param   int                             $companyId
	 * @param   int                             $brandId
	 * @param   Myshipserv_NotificationManager  $notificationManager
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	public static function removeBrandOwner($companyId, $brandId, Myshipserv_NotificationManager $notificationManager = null)
	{
		$actionTaken = false;

		//check if this owner does not already exist for this brand
		if ($auths = Shipserv_BrandAuthorisation::search(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_BRAND_ID'      => $brandId,
				'PCB_AUTH_LEVEL'    => Shipserv_BrandAuthorisation::AUTH_LEVEL_OWNER
			)
		)) {
			foreach ($auths as $auth) {
				$auth->remove();
				$actionTaken = true;
			}
		} else {
			throw new Exception("Brand authorisation doesn't exist");
		}

		//if there no brand owners left - authorise all requests
		if (count(Shipserv_BrandAuthorisation::getBrandOwners($brandId)) === 0) {
			if ($requests = Shipserv_BrandAuthorisation::getRequests($brandId)) {
				foreach ($requests as $request) {
					$request->authorise();
					if (!is_null($notificationManager)) {
						//if required - send notifications to suppliers that their requests were granted because brand become uncontrolled
						//$notificationManager->brandAuthorisationRestored($request);
					}

					$actionTaken = true;
				}
			}
		}

		return $actionTaken;
	}

	/**
	 *
	 * @param  Shipserv_BrandAuthorisation $auth
	 * @return array
	 */
	public static function authToArray($auth)
	{
		return array(
			'companyId'     => $auth->companyId,
			'brandId'	    => $auth->brandId,
			'authLevel'	    => $auth->authLevel,
			'isAuthorised'	=> $auth->isAuthorised
		);
	}
}