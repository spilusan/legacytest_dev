<?
class Shipserv_Adapters_Soap_MTMLLink extends Shipserv_Object
{
	private function getOneUsernamePasswordBySpbBranchCode( $spbBranchCode ){
		$sql = "
			WITH base AS (
			  SELECT sbu_usr_user_code user_id, 'SUPPLIER_BRANCH_USER' user_type FROM supplier_branch_user WHERE sbu_spb_branch_code=:tnid

			  UNION ALL

			  SELECT puc_psu_id user_id, 'PAGES_USER_COMPANY' user_type FROM pages_user_company WHERE puc_company_type='SPB' AND puc_company_id=:tnid
			)

			SELECT
			  usr_name
			  , user_type
			FROM
			  users JOIN base ON (usr_user_code=user_id)
			WHERE
			  usr_sts='ACT'
		";
		$users = $this->getDb()->fetchAll($sql, array('tnid' => $spbBranchCode));
		return $users;
	}

	private function isSupplierAuthorisedToUseTNC($spbBranchCode){
		$sql = "SELECT COUNT(*) TOTAL FROM weblogic.branch_group_hierarchy WHERE group_id=135 AND branch_id=:spbBranchCode AND branch_type='V'";
		return ($this->getDb()->fetchOne($sql, array('spbBranchCode' => $spbBranchCode))>0);
	}

	private function authoriseSupplierToUseTNC($spbBranchCode){
		$sql = "insert into weblogic.branch_group_hierarchy (group_id, branch_id, branch_type) values (135, :spbBranchCode, 'V')";
		$this->getDb()->query($sql, array('spbBranchCode' => $spbBranchCode));
	}

	private function deauthoriseSupplierToUseTNC($spbBranchCode){
		$sql = "DELETE FROM weblogic.branch_group_hierarchy WHERE group_id=135 AND branch_id=:spbBranchCode AND branch_type='V'";
		$this->getDb()->query($sql, array('spbBranchCode' => $spbBranchCode));
	}

	public function sendEncodedDocument( $mtmlDocumentAsString, $companyType, $tnid, $clientFileName = 'Pages Quote to PO' ){

		$config = $this->getConfig();

		$logger = new Myshipserv_Logger_File('MTMLLink-SendEncodedDocument');
		$logger->log("Shipserv_Adapters_Soap_MTMLLink::sendEncodedDocument");

		if( $mtmlDocumentAsString == "" ) throw new Exception("MTMLDocument string isn't specified");
		if( $tnid == "" ) throw new Exception("TNID isn't specified");
		if( $companyType == "" ) throw new Exception("CompanyType isn't specified");

		if( $companyType == "SPB" ){
			// getting one active username from the supplier branch table
			$logger->log("getting one active username from the supplier branch table");

			$users = $this->getOneUsernamePasswordBySpbBranchCode($tnid);
		}

		// @todo: Please remove when JP's patched up the TNC
		if( $config->shipserv->services->tradenet->core->weblogic->hack->enable == 1 ){
			$logger->log("Weblogic hack is enabled");

			$deauthoriseSupplier = false;
			if( $this->isSupplierAuthorisedToUseTNC($tnid) === false ){
				$logger->log("Authorising $tnid to send txn through TNC");

				$this->authoriseSupplierToUseTNC($tnid);
				$deauthoriseSupplier = true;
			}
		}


		// to send document, TNC need couple of information
		$params['AppDetails']['Name'] = $config->shipserv->application->name;
		$params['AppDetails']['Version'] = Myshipserv_Config::getApplicationReleaseVersion();

		$params['IntegrationDetails']['TradeNetID'] = $tnid;
		$params['IntegrationDetails']['UserID'] = $users[0]['USR_NAME'];
		$params['IntegrationDetails']['IntegrationCode'] = "STD";

		$params['DocumentDetails']['DocumentType'] = "PO";
		$params['DocumentDetails']['ClientFileName'] = $clientFileName;
		$params['DocumentDetails']['FileContentsAsBytes'] = $mtmlDocumentAsString;
		$params['DocumentDetails']['EncodingTypeCode'] = "utf-8";

		$dataToSend['UserIntegrationDoc'] = $params;

		$soapUrl = $config->shipserv->services->tradenet->core->url;
		
		// BUY-962  CAS Service ticket here instead of plain text password sending
		$strAuthHeader = "Authorization: CAS ". Myshipserv_CAS_CasRest::getInstance()->generateNewSt() . ' ' . Myshipserv_CAS_CasRest::getInstance()->getDefaultCasServiceUrl();
		
		$arrContext = array('http' =>array('header' => $strAuthHeader));
		$objContext = stream_context_create($arrContext);
		
		$soapConfig = array(
			"soap_version" => "SOAP 1.1",
			"encoding" => "utf-8",
			"trace" => true,
			"connection_timeout" => 10,
		    'stream_context' => $objContext
		);
		$client = new SoapClient($soapUrl, $soapConfig);

		$response = $client->ping();
		if($response->PingResult == 1)
		{
			$logger->log("TNC is available");
			$logger->log("Trying to send document to TNC::SendEncodedDocument");

			$response = $client->SendEncodedDocument($dataToSend);

			// @todo: Please remove when JP's patched up the TNC
			if( $config->shipserv->services->tradenet->core->weblogic->hack->enable == 1 ){
				if( $deauthoriseSupplier === true ){
					$logger->log("deauthorising $tnid to send txn through TNC");
					$this->deauthoriseSupplierToUseTNC($tnid);
				}
			}

			if( $response->SendEncodedDocumentResult->ErrorMessage != "" )
			{
				$dataToLog .= "----------- RAW MTML --------------\n";
				$dataToLog .= $mtmlDocumentAsString. "\n";
				$dataToLog .= "----------- SOAP RAW REQUEST --------------\n";
				$dataToLog .= print_r($client->__getLastRequest(), true) . "\n";
				$dataToLog .= "----------- SOAP RAW RESPONSE --------------\n";
				$dataToLog .= print_r($client->__getLastResponse(), true) . "\n\n\n";
				$logger->log("Error happened on TNC::SendEncodedDocument", $dataToLog);
				$logger->log("Failed");

				$success = false;
			}
			else
			{
				$dataToLog = "----------- RAW MTML --------------\n";
				$dataToLog .= $mtmlDocumentAsString. "\n";
				$dataToLog .= "----------- SOAP RAW RESPONSE --------------\n";
				$dataToLog .= print_r($client->__getLastResponse(), true) . "\n\n\n";

				$logger->log("Successful", $dataToLog);

				$success = true;
			}

			return $success;
		}
	}
}
?>
