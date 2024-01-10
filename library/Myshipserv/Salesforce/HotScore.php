<?php

class Myshipserv_Salesforce_HotScore extends Myshipserv_Salesforce
{
	
    /**
     * Function to do one-time update of Hotscore Object to populate the URL link for account managers to be able to send the hotscore link to.
     */
    public function updateHotscoreEmaiLinks() {
        $soql = "SELECT Id, Contact_Name__r.Email,Voted__c FROM Net_Promoter_Score__c where Voted__c = false";

        $sfObj = new Shipserv_Adapters_Salesforce();

        $sfResults = $sfObj->query($soql);

        $tmpName = tempnam("\tmp", "NPS_");
        $tmpCSV = fopen($tmpName, "w");

        //Write header
        fwrite($tmpCSV, "Id,Hotscore_Link__c\n");


        foreach ($sfResults as $result) {
            $email = $result->Contact_name__r->Email;
            $id = $result->Id;
            $url = "https://www.shipserv.com/survey/?surveyId=1&email=" . urlencode($email) . "&c=" . md5("CSS" . $email);

            fputcsv($tmpCSV, array($id, $url));
        }

        fclose($tmpCSV);

        $params = array(
            'objectName' => 'Net_Promoter_Score__c',
            'operation' => 'update',
            'concurrencyMode' => 'Parallel'
        );

        $sfObj->bulkUpdateFromCSV($params, $tmpName);
        unlink($tmpName);
    }

    /**
     * Not being used
     * @param unknown $inviteId
     * @param unknown $score
     * @param string $comment
     * @return unknown
     */
    public static function updateInvitedSurveyResponse($inviteId, $score, $comment = "") {
    	$sfObj = new Shipserv_Adapters_Salesforce();
    
    	$params = array(
    			'Id' => $inviteId,
    			'Net_Promoter_Score__c' => $score,
    			'Comment__c' => $comment,
    	);
    
    	$response = $sfObj->updateObject('Net_Promoter_Score__c', $params);
    
    	return $response;
    }
    
    
}