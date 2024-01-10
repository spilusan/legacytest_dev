<?php
/**
 * Controller actions for SPR general rest calls
 * Sample URL: /reports/data/supplier-performance-data/supplier-branches?byo=10529,10530
 *
 * @author attilaolbrich
 *
 */
class Spr_RestController extends Myshipserv_Controller_RestController
{
	
	/**
	 * Maybe called on get request, and redirected to getAction
	 * @return undefined
	 */
	public function indexAction()
	{
		$this->getAction();
	}

	
	/**
	 * We support all actions, get, post for the same type of request
	 * 
	 */
	public function postAction()
	{
		$this->getAction();
	}
	
	/**
	 * Triggered when GET request is sent
	 *
	 * @return json
	 */
	public function getAction()
	{
		$type = $this->getRequest()->getParam('type', null);
		
		if ($type === null) {
			return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Report type is missing, incomplete URL"), 500);
		}

		switch ($type) {
			case 'supplier-branches':
				$buyerBranches = $this->getRequest()->getParam('byo', null);
                $keywords = $this->getRequest()->getParam('keywords', "");
                $limit = $this->getRequest()->getParam('limit', null);
				if (!is_array($buyerBranches)) {
					if (!preg_match('/^([0-9]+,?)+$/', $buyerBranches)) {
						return $this->_replyJsonError(new Myshipserv_Exception_JSONException("byb parameter missing, or incorrect, use an individual TNID, or a list of TNID's seppareted by comma"), 500);
					}
					$buyerBranches = explode(',', $buyerBranches);
				}

                $supplierBranches = $this->getRequest()->getParam('spb', null);
                if (!is_array($supplierBranches) && $supplierBranches !== null) {
                    if (!preg_match('/^([0-9]+,?)+$/', $supplierBranches)) {
                        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("spb parameter missing, or incorrect, use an individual TNID, or a list of TNID's seppareted by comma"), 500);
                    }
                    $supplierBranches = explode(',', $supplierBranches);
                }

				$service = new Shipserv_Spr_SupplierLookup();
                $reply = $service->getDefaultSpbList($buyerBranches, $supplierBranches, $keywords, $limit);
				Myshipserv_Spr_Anonymize::anonimizeData($reply, array('value' => 'Supplier {X}'));
				return $this->_replyJson($reply);
			default:
				return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Invalid report type " .$type), 500);

		}

	}
	
}