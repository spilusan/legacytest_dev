<?php
/**
 * Manage downloads, redirects
 * @author attilaolbrich
 *
 */
class Reports_DownloadController extends Myshipserv_Controller_Action
{
	/**
	 * Redirect to download, and add the download event to a log in database
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function downloadAction()
	{
		$redirect = $this->getRequest()->getParam('redirect', null);
		if ($redirect) {
			if (Myshipserv_Helper_CrawlerDetect::detect() === false) {
				$userId = null;
				$isShipMate = false;
				
				$user = Shipserv_User::isLoggedIn();
				if ($user) {
					$userId = $user->userId;
					$isShipMate = $user->isShipServUser();
				}
				
				$userDb = new Shipserv_Oracle_User(Shipserv_Helper_Database::getDb());
				$userDb->logActivity($userId, 'OINS_DOWNLOAD', 'PAGES_USER', $this->getRequest()->getParam('spbBranchCode', null), $isShipMate, $redirect);
			}
			$this->getResponse()->setRedirect($redirect);
		} else {
			throw new Myshipserv_Exception_MessagedException("redirect url is required.");
		}
		
	}
}