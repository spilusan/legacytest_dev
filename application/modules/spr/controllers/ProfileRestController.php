<?php
/**
* Controller actions for SPR Profile Page
* Sample URL: /reports/data/supplier-performance-profile/52323
*
* @author attilaolbrich
*
*/
class Spr_ProfileRestController extends Myshipserv_Controller_RestController
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
	 * Triggered when GET request is sent
	 *
	 * @return json
	 */
	public function getAction()
	{
		$id = (int)$this->getRequest()->getParam('id', 0);
		
		if ($id === 0) {
			return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Supplier ID is missing, incomplete URL"), 500);
		}

		$supplierDetails = Shipserv_Profile_SupplierProfileDetails::getInstance()->getSupplierDetails($id);
		
		$anonimization = array(
		    'root/name' => 'Supplier {X}',
		    'categories/name' => 'Category {X}',
		    'brands/name' => 'Brand {X}',
		    'ports/name' => 'Port {X}',
		    'address1' => 'Address',
		    'address2' => null,
		    'description' => 'Description',
		    'firstName' => 'Name {X}',
		    'lastName' => null,
		    'phoneNo' => '+0 00 000000',
		    'emailAddress' => 'email@email.com',
		    'mobileNo' => null,
		    'middleName' => null,
		    'skypeName' => null,
		    'city' => 'City',
		    'countryCode' => null,
		    'countryName' => 'Country',
		    'logoUrl' => null,
		    'zipCode' => 'ZIP CODE',
            'names' => 'Company {X}'
		);
		
		Myshipserv_Spr_Anonymize::anonimizeData($supplierDetails, $anonimization);
		
		$this->_replyJson($supplierDetails);
	}

}