<?php

/**
 * Class to handle Customer Satisfaction Survey
 * This class can be used to store all votes to the database and push the
 * result to salesforce
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Survey extends Shipserv_Object
{
	public $id;
	public $name;


	/**
	 * Please makesure that these variables are matched with the ones on PAGES_SURVEY
	 */
	const SID_SEARCH_RESULT_FEEDBACK = 5;
	const SID_DECLINE_ENQUIRY = 2;
	const SID_ACCEPT_ENQUIRY = 3;
	const SID_VIEW_CONTACT = 4;
	const SID_ATTRACTIVE_RFQ = 7;
	const SID_UNATTRACTIVE_RFQ = 6;
	/**
	 * Please makesure that these variables are matched with the ones on PAGES_SURVEY_QUESTION
	 */
	const QID_SEARCH_RESULT_FEEDBACK = 5;
	const QID_DECLINE_ENQUIRY = 3;
	const QID_ACCEPT_ENQUIRY = 4;
	const QID_VIEW_CONTACT = 6;

	/**
	 *
	 */


	private function createObjectFromDb( $data )
	{
		$object = new self;
		$object->id = $data["id"];
		$object->name = $data["name"];
		return $object;
	}

	public static function getInstanceById( $id )
	{
		$row = self::getDao()->fetchById( $id );
		$row = $row[0];
		$data["id"] = $row['PSY_ID'];
		$data["name"] = $row['PSY_NAME'];
		return self::createObjectFromDb($data);
	}

	/**
	 * Pull all related question to this survey
	 * @return Array
	 */
	public function getQuestions()
	{
		return $this->getDao()->fetchQuestionsBySurveyId( $this->id );
	}

	/**
	 * Get the database access object
	 * @return object
	 */
	private function getDao()
	{
		return new Shipserv_Oracle_Survey( self::getDb() );
	}

	/**
	 * Store answer to the database using the DAO
	 * @param array $params ($_GET parameters)
	 */
	public function storeAnswers( $params )
	{
		$this->saveToDatabase( $params );
	}

	public function storeAnswersSearchResultFeedback( $response )
	{
		// question id has to be fixed
		self::getDao()->insertAnswerForSearchResultFeedback(self::QID_SEARCH_RESULT_FEEDBACK, $response);
	}

	public function storeAnswersForDeclineEnquiry( $inquiryId, $response, $tnid )
	{
		// question id has to be fixed
		self::getDao()->insertAnswerForDeclineEnquiry(self::QID_DECLINE_ENQUIRY, $response, $inquiryId, $tnid );
	}

	public function storeAnswersForAcceptingEnquiry( $inquiryId, $response, $tnid )
	{
		// question id has to be fixed
		self::getDao()->insertAnswerForDeclineEnquiry(self::QID_ACCEPT_ENQUIRY, $response, $inquiryId, $tnid );
	}

	protected function saveToDatabase( $params )
	{
		// insert all answers
		foreach( $params['question'] as $questionId => $answer )
		{
			if( $questionId == 1 )
			{
				$score = $answer;
			}
			else if( $questionId == 2 )
			{
				$comment = $answer;
			}

			// save the score + comments to the database
			$recordId = self::getDao()->insertAnswer($questionId, $answer, $params["email"]);

		}

		foreach( (array) self::getDao()->getSFRecordIdByEmail( $params["email"] ) as $row )
		{
			// save the score to salesforce
			$this->saveToSalesforce( $row["PYU_SF_RECORD_ID"], $score, $comment);
		}

		return true;
	}

	public function saveToSalesforce($recordId, $score, $comment)
	{
		$sfObj = new Shipserv_Adapters_Salesforce();

		$params = array(
			'Id' => $recordId,
			'Net_Promoter_Score__c' => $score,
			'Comment__c' => $comment,
			'Voted__c' => 1,
		);

		$response = $sfObj->updateObject('Net_Promoter_Score__c', $params);

		if( $response->code != "" )
		{
			return true;
		}
		else
		{
			throw new Exception("Salesforce error: " . $response->message);
		}


	}
}
