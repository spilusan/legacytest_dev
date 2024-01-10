<?php 
/**
 * Store supplier response of Erroneous Transaction, and handle exceptions
 * Rewritten on 24/11/11 to use new pages_competitor_cache
 * @package ShipServ
 * @author Attila O <aolbrich@shipserv.com>
 * @copyright Copyright (c) 2015, ShipServ
 */
class Shipserv_Erroneous_SupplierResponse extends Shipserv_Object
{

	const
		RESPONSE_ACC = 'ACC',
		RESPONSE_CONTACT_BUYER = 'CON',
		RESPONSE_SENDMAIL = 'SEN',
		RESPONSE_NOP	= ''
		;

	protected $selectedResponse;
	protected $reqest;
	protected $ordInternalRefNo;
	protected $hash;
	protected $message;
	/**
	* Construct object, set database
	*/
	public function __construct( $params )
	{
		//$this->db = $this->getDb();
		$this->db = Shipserv_Helper_Database::getSsreport2Db();
		$this->config = $this->getConfig();
		$this->params = $params;
		$this->reqest = (array_key_exists('answ', $this->params)) ? $this->params['answ'] : '';
		$this->ordInternalRefNo = (array_key_exists('id', $this->params)) ? (int)$this->params['id'] : null;
		$this->hash  = (array_key_exists('h', $this->params)) ? $this->params['h'] : null;
		$this->message =(array_key_exists('message', $this->params)) ? $this->params['message'] : null;
		$order = Shipserv_Order::getInstanceById($this->ordInternalRefNo);
		$this->printable = $order->getUrl();
	}

	/**
	* Saves the supplier response, then returns with an arrey, can be passed to the view, with Supplier, Buyer info
	*/
	public function saveResponse()
	{
		if ($this->isAlreadyAnswered() === true) {
		/* if (false) { */
			return array(
				'renderPage' => 'erroneous/already-sent',
				);
		} else {

			$emailSentToBuyer = false; //Indicate initial status, email was not sent to buyer
			$renderPage = 'erroneous/reply';
			switch ($this->reqest) {
				case 'acc':
					# code...
					$hashModifier = 'acc_ord';
					$this->selectedResponse = self::RESPONSE_ACC;
					$renderPage = 'erroneous/accept-order';
					break;
				case 'con':
					$hashModifier = 'con_ord';
					$this->selectedResponse = self::RESPONSE_CONTACT_BUYER;
					$renderPage = 'erroneous/contact-buyer';
					break;
				case 'sen':
					$hashModifier = 'sen_ord';
					$this->selectedResponse = self::RESPONSE_NOP;
					$renderPage = 'erroneous/send-mail';
					break;
				case 'send':
					$hashModifier = 'send_ord';
					$this->selectedResponse = self::RESPONSE_SENDMAIL;
					$renderPage = 'erroneous/mail-sent';
					break;								
				default:
					throw new Exception($this->reqest . " is not supported");
					break;
			}
			if (md5($this->ordInternalRefNo.'_'.$hashModifier) === $this->hash) {

			} else {
				throw new Exception("Invalid parameters");
			}
			
			$sendToBuyer = new Myshipserv_Poller_ErroneousTransactionMonitor(false);
			//Email must be sent to the buyer, if the supplier selected this option
			


			

			if ($this->updateRecord($this->reqest == 'sen')) {
		        $order = Shipserv_Order::getInstanceById($this->ordInternalRefNo);
		        $quote = Shipserv_Quote::getInstanceById($order->ordQotInternalRefNo);
		        $supplier = Shipserv_Supplier::getInstanceById($order->ordSpbBranchCode, '', true);
		        $buyer = Shipserv_Buyer::getBuyerBranchInstanceById($order->ordBybBuyerBranchCode, '', true);
			} else {
				throw new Exception("Whoops something went wrong, Please try later");
			}

			if ($this->selectedResponse === self::RESPONSE_SENDMAIL) {
				//Send mail, if the user clicked the send option
				$emailSentToBuyer = $sendToBuyer->sendToBuyer($this->ordInternalRefNo);
			}
			
			return array(
				'ordInternalRefNo' => $this->ordInternalRefNo,
				'renderPage' => $renderPage,
				'order' => $order,
				'quote' => $quote,
				'supplier' => $supplier,
				'buyer' => $buyer,
				'request' => $this->reqest,
				'emailSentToBuyer' => $emailSentToBuyer,
				'rows' => $sendToBuyer->getRowsForBuyer($this->ordInternalRefNo),
				'printable' => $this->printable,
				);
		}
	}

	protected function updateRecord( $skipSend )
	{
		if ($skipSend) {
			return true;
		} else {
			if ($this->selectedResponse === self::RESPONSE_SENDMAIL) {
				
				$sql = "
					UPDATE
						erroneous_txn_notification
					SET
						etn_supplier_response=:response,
						etn_buy_notification_message=:message
					WHERE
						etn_ord_internal_ref_no = :ordInternalRefNo
						and etn_doc_type='ORD'
				";

				return $this->db->query($sql, array('ordInternalRefNo' => $this->ordInternalRefNo, 'response' => $this->selectedResponse, 'message' => $this->message ));
			} else {
				$sql = "
					UPDATE
						erroneous_txn_notification
					SET
						etn_supplier_response=:response
					WHERE
						etn_ord_internal_ref_no = :ordInternalRefNo
						and etn_doc_type='ORD'
				";

				return $this->db->query($sql, array('ordInternalRefNo' => $this->ordInternalRefNo, 'response' => $this->selectedResponse ));
			}
		}
	}

	protected function isAlreadyAnswered()
	{
				$sql = "
					SELECT
						COUNT(*)
					FROM
						erroneous_txn_notification
					WHERE
						etn_ord_internal_ref_no = :ordInternalRefNo
						and etn_doc_type='ORD'
						and etn_supplier_response IS NOT null
				";

		$params = array(
			'ordInternalRefNo' => $this->ordInternalRefNo
			);
		return ( $this->db->fetchOne($sql, $params) > 0 );
	}

}