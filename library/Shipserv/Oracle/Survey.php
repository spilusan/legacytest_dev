<?php
/**
 * This class handles all survey that is available on pages
 * @author Elvir <eleonard@shipserv.com>
 *
 */
class Shipserv_Oracle_Survey extends Shipserv_Oracle
{
	protected $db;
	
	function __construct( )
	{
		$this->db = $this->getDb();
	}
	
	public function fetchById ($id)
	{
		$sql = "SELECT * FROM pages_survey WHERE psy_id=:id";
		$sqlData = array('id' => $id);
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	public function fetchQuestionsBySurveyId ($id)
	{
		$sql = "SELECT * FROM pages_survey_question WHERE psq_psy_id=:id";
		$sqlData = array('id' => $id);

		return $this->db->fetchAll($sql, $sqlData);
	}	


	public function insertAnswerForDeclineEnquiry( $questionId, $answer, $inquiryId, $tnid)
	{
		$sql = "INSERT INTO pages_survey_answer(PSA_PSQ_ID, PSA_ANSWER, PSA_DATE_CREATED, PSA_TNID) 
										VALUES (:questionId, :answer, CURRENT_TIMESTAMP, :tnid)";
		
		$sqlData = array( "tnid" => $tnid,
						  "questionId" => $questionId,
						  "answer" => $answer );
							
		$this->db->query($sql, $sqlData);
		
		$answerId = $this->db->lastSequenceId('SQ_PAGES_SURVEY_ANSWER_ID');
		// insert this to pages_inquiry_declined_survey
		$sql = "INSERT INTO pages_inquiry_survey(PDS_PIN_ID, PDS_PSA_ID, PDS_TYPE) 
												  VALUES (:inquiryId, :answerId, 'decline')";

		$sqlData = array( "inquiryId" => $inquiryId,
						  "answerId" => $answerId );
							
		$this->db->query($sql, $sqlData);
	}
	
	
	public function insertAnswerForSearchResultFeedback( $questionId, $answer)
	{
		
			$sql = "INSERT INTO pages_survey_answer(PSA_PSQ_ID, PSA_ANSWER, PSA_DATE_CREATED) 
											VALUES (:questionId, :answer, CURRENT_TIMESTAMP)";
			
			$sqlData = array( "questionId" => $questionId,
							  "answer" => $answer 
			);
								
			$this->db->query($sql, $sqlData);
	}
	
	public function insertNormalisedAnswerForSearchResultFeedback($ipAddress, $responseType, $url, $message, $email, $rawMessage, $userId)
	{
		$sql = "INSERT INTO pages_survey_search_feedback (psf_ip_address, psf_response_type, psf_url, psf_message, psf_email, psf_message_raw, psf_psu_id)
													VALUES( :ipAddress, :responseType, :url, :message, :email, :rawMessage, :userId )
		";
		
		$sqlData = array( "ipAddress" => $ipAddress,
						  "responseType" => $responseType,
						  "url" => substr($url, 0, 2000),
						  "message" => $message, 
						  "email" => $email,
						  "rawMessage" => substr($rawMessage, 0, 2000),
						  "userId" => $userId
		);
		
		$this->db->query($sql, $sqlData);
	}
	
	private function parseFlatTextToStruct( $row )
	{
		$answer = $row['PSA_ANSWER'];
		$lines = explode("\n", $answer );
		foreach( $lines as $line )
		{
			//echo $line . "\n";

			// get URL;
			if( preg_match('/^URL: (http:\/\/(.)+)$/i', $line, $matches) )
			{
				$url = isset($matches[1])?$matches[1]:'';
			}
			
			// get UserId;
			if( preg_match('/(\[userId\:protected\] \=\> ([0-9]+))/i', $line, $matches))
			{
				$userId = isset($matches[2]) ? $matches[2] : '';
			}			
			
			// find out type of response
			if( preg_match('/We just received a (negative|positive) feedback on search result page/i', $line,  $matches) )
			{
				$responseType = isset($matches[1]) ? $matches[1] : '';
			}

			// find out email
			if( preg_match('/^Email: (.+)$/i', $line, $matches))
			{
				$email = isset($matches[1]) ? $matches[1] : '';
			}

			// find out name
			if( preg_match('/^Name: (.+)$/i', $line, $matches))
			{
				$name = isset($matches[1]) ? $matches[1] : '';
			}

			// find out comment
			if( preg_match('/^Comments: (.+)$/i', $line, $matches))
			{
				$comments = isset($matches[1]) ? $matches[1] : '';
			}
		}
		
		/*
		echo "-----------------------------\n";
		echo "-----------------------------\n";
		echo "PSA_ID: " . $row["PSA_ID"] . "\n";
		echo "PSA_DATE_CREATED: " . $row["PSA_DATE_CREATED"] . "\n";
		echo "URL: $url\n";
		echo "UserId: $userId\n";
		echo "ResponseType: $responseType\n";
		echo "Email: $email\n";
		echo "Name: $name\n";
		echo "Comments: $comments\n";
		echo "-----------------------------\n";
		echo "-----------------------------\n";
		*/
		
		$data['url'] = trim($url);
		$data['userId'] = trim($userId);
		$data['responseType'] = trim($responseType);
		$data['email'] = trim($email);
		$data['name'] = trim($name);
		$data['comments'] = trim($comments);
		
		return $data;
		
	}
	
	
	
	public function normaliseAllAnswerForSearchResultFeedback()
	{
		$this->db->getProfiler()->setEnabled( true );
		$sql = "SELECT psa_id, psa_answer, psa_date_created FROM pages_survey_answer WHERE psa_psq_id=5  ORDER BY psa_id DESC";
		$results = $this->db->fetchAll( $sql );
		foreach ( $results as $row )
		{
			$new = $this->parseFlatTextToStruct( $row );
			
			// check for duplicate
			$sql2 = "
				SELECT COUNT(*) as TOTAL
				FROM pages_survey_search_feedback 
				WHERE
					TRIM(UPPER(psf_response_type))=:responseType
					AND TRIM(psf_url)=:url
					
			";
			
			$sqlData = array( 'responseType' => $new['responseType'],
							  'url' => trim($new['url'])
			);
			
			if( $new["userId"] == "" ) $sql2 .= ' AND TRIM(psf_psu_id) IS NULL ';
			else{
				$sqlData['userId'] = $new['userId'];
				$sql2 .= ' AND TRIM(psf_psu_id)=:userId';
			} 
			
			if( $new["email"] == "" ) $sql2 .= ' AND TRIM(psf_email) IS NULL ';
			else{
				$sqlData['email'] = $new['email'];
				$sql2 .= ' AND TRIM(psf_email)=:email';
			} 
			
			if( $new["comments"] == "" ) $sql2 .= ' AND TRIM(psf_message) IS NULL ';
			else
			{
				$sqlData['comments'] = $new['comments'];
				$sql2 .= ' AND TRIM(psf_message)=:comments';
			} 
			
			
			// enable profiler
			$results2 = $this->db->fetchAll( $sql2, $sqlData );
			$query = $this->db->getProfiler()->getLastQueryProfile()->getQuery();
			echo $query;  
			print_r( $results2);
			if( $results2[0]['TOTAL'] == 0 && $new['responseType'] != "" && $new['url'] != '')
			{
				$sql = "INSERT INTO pages_survey_search_feedback ( psf_response_type, psf_url, psf_email, psf_psu_id, psf_message, psf_message_raw)
															VALUES( :responseType, :url, :email, :userId, :comments, :rawData )
				";
				
				$sqlData2 = array( 'responseType' => $new['responseType'],
					  'url' => trim($new['url']),
					  'userId' => $new['userId']!="" ? trim($new['userId']):null,
					  'email' => $new['email'] != "" ? trim($new['email']) : null,
					  'comments' =>  $new['comments']!= "" ? trim($new['comments']):null,
					  'rawData' =>  $row['PSA_ANSWER'],
				);
				
				
				
				$this->db->query( $sql, $sqlData2 );
			}
			
		}
	}
	
	public function insertAnswer( $questionId, $answer, $email)
	{
		
		$sql = "SELECT * FROM pages_survey_invite WHERE pyu_email=:email";
		$rows = $this->db->fetchAll( $sql, array("email"=>$email));
		
		foreach( (array) $rows as $row )
		{
			$sql = "INSERT INTO pages_survey_answer(PSA_PSQ_ID, PSA_ANSWER, PSA_TNID, PSA_EMAIL, PSA_SF_USER_ID, PSA_DATE_CREATED, PSA_SF_RECORD_ID) 
											VALUES (:questionId, :answer, :tnid, :email, :salesforceUserId, CURRENT_TIMESTAMP, :recordId)";
			
			$sqlData = array( "questionId" => $questionId,
							  "answer" => $answer, 
							  "tnid" => $row["PYU_TNID"], 
							  "email" => $email, 
							  "salesforceUserId" => $row["PYU_SF_CONTACT_ID"],
							  "recordId" => $row["PYU_SF_RECORD_ID"]
			);
								
			$this->db->query($sql, $sqlData);
		}
		
		return true;
	}
	
	public function getSFRecordIdByEmail( $email )
	{
		$sql = "SELECT pyu_sf_record_id FROM pages_survey_invite WHERE pyu_email=:email";
		return $this->db->fetchAll( $sql, array("email"=>$email));
	}
	
	public function getNextBatchForInvitation( $rownum )
	{
		$sql = "SELECT pyu_email FROM pages_survey_invite WHERE pyu_invitation_sent IS null AND rownum<=" . $rownum . " GROUP BY pyu_email";
		return $this->db->fetchAll( $sql );
	}
	
	public function setTimestampOnInvitationByRecordId( $id )
	{
		$sql = "UPDATE pages_survey_invite SET pyu_invitation_sent=SYSDATE WHERE pyu_sf_record_id=:id";
		$sqlData = array( "id" => $id );
		return $this->db->query($sql, $sqlData);
	}
	
	public function setTimestampOnInvitationById( $id )
	{
		$sql = "UPDATE pages_survey_invite SET pyu_invitation_sent=SYSDATE WHERE pyu_id=:id";
		$sqlData = array( "id" => $id );
		return $this->db->query($sql, $sqlData);
	}

	public function setTimestampOnInvitationByEmail( $email )
	{
		$sql = "UPDATE pages_survey_invite SET pyu_invitation_sent=SYSDATE WHERE pyu_email=:email";
		$sqlData = array( "email" => $email );
		return $this->db->query($sql, $sqlData);
	}
}
