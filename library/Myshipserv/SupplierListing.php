<?php
class Myshipserv_SupplierListing 
{
	public $supplier;
	// if profile was not updated last 12 mo
	// You have not updated your profile in the last 12 months
	//---
	// if zero pages admin
	// There is no ShipServ Pages administrator for your company, please contact <a href="mailto:support@shipserv.com">support@shipserv.com</a>
	
	/// PREMIUM
	// need to check telephone, address and website separately, and each can score max of 3%
	
	
	public $score;
	public $totalMaxScore = 0;
	
	public $basicListerRules = array( 
	     'general' 			=> array( 	'heading' 	=> 'Telephone',
		   								'maxScore'	=> 8, 
									  	'criterion' => array(	'forTelephone' 					=> array(	'true' 					=> array('score' => array(8),		'todo' => '', 'info' => 'You have telephone number'), 
																											'false' 				=> array('score' => 0, 				'todo' => 'Make sure that you have a telephone number on your profile', 'info' => '') 
																										)															
															) 
									)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'primaryCategories'=> array(	'heading' 	=> 'Primary categories',
		  								'maxScore' 	=> 8,
		  								'criterion' => array(	'forTotalPrimaryCategories'		=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have no primary categories listed. This is very important you add a category here'), // basic can have one 
		  																									'range==1' 				=> array('score' => array(8),		'info' => 'You have enough primary category' )
		 																								) 
		 													) 
		 								)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'secondaryCategories'		=> array(	'heading' 	=> 'Secondary categories',
		  								'maxScore' 	=> 5,
		  								'criterion' => array(	'forTotalSecondaryCategories'	=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have no secondary (other) categories. There is a maximum number of 2 secondary categories you can add as long as they are relevant to your business'),
		  																									'range==1' 				=> array('score' => array(1), 		'todo' => 'You have 1 secondary (other) categories. You may add a second category as long as it is relevant to your business'),
		  																									'range==2' 				=> array('score' => array(5), 		'todo' => '', 'info' => 'You have secondary categories') 
		  																								)
		  													)
		  							)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'brands'			=> array(	'heading' 	=> 'Brands',
		  								'maxScore' 	=> 10,
		  								'criterion' => array(	'forTotalBrand'					=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have zero brands on your profile. This is important to change this. Add brands to your profile if you supply them'), 
		  																									'range==1' 				=> array('score' => array(3),		'todo' => 'You have a small number of brands on your profile. You can add more to your profile if you supply them' ),
		  																									'range==2' 				=> array('score' => array(6),		'todo' => 'You have a small number of brands on your profile. You can add more to your profile if you supply them' ),
		  																									'range>=3' 				=> array('score' => array(10),		'todo' => '', 'info' => 'You have brands') 
		  																								)
		  													)
		  							)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'ports'			=> array(	'heading' 	=> 'Ports',
		  								'maxScore' 	=> 5,
		  								'criterion' => array(	'forTotalPorts'					=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have no port on your profile. This is important to change this.' ), 
		  																									'range==1' 				=> array('score' => array(5),		'todo' => '', 'info' => 'You have some ports') 
		  																								)
		  													)
		  							) 
		  																									
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'brandAuthorisation'		=> array(	'heading' 	=> 'Authorisation',
		  								'maxScore' 	=> 5,
		  								'criterion' => array(	'forTotalAuthorisation'			=> array(	'false' 			=> array('score' => array(0),		'todo' => '', 'info' => 'No brands' ), // not done yet
		  																									'range==0' 				=> array('score' => array(0),		'todo' => 'Please review your authorised supplier status for all the brands you supply or deal with', 'info' => '' ),
		  																									'range>0&&range<49'		=> array('score' => array(3),		'todo' => 'Please review your authorised supplier status for all the brands you supply or deal with' ), 
		  																									'range>=50' 			=> array('score' => array(5), 		'todo' => '', 'info' => 'Please review your authorised supplier status for all the brands you supply or deal with' ) 
		  																								) 
		  													) 
		  							)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'admin'			=> array(	'heading' 	=> 'Pages Admin',
		  								'maxScore' 	=> 3,
		  								'criterion' => array(	'forAdmin'						=> array(	
		  																									'false'					=> array('score' => array(0),		'todo' => 'There is no ShipServ Pages administrator for your company, please contact <a href="mailto:support@shipserv.com">support@shipserv.com</a>', 'info' => '' ),
		  																									'true' 					=> array('score' => array(3),		'todo' => '', 'info' => 'You have at least one administrator' )
		  																								) 
		  													) 
		  							)

		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'listing'		=> array(	'heading' 	=> 'Listing freshness',
		  								'maxScore' 	=> 1,
		  								'criterion' => array(	'forVerifiedListing'			=> array(	'true' 				=> array('score' => array(1),		'todo' => '', 'info' => 'You have updated your profile in the last 12 months' ), // not done yet
		  																									'false'				=> array('score' => array(0),		'todo' => 'You have not updated your profile in the last 12 months', 'info' => '' )
		  																								) 
		  													) 
		  							)
		  							
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'review'			=> array(	'heading' 	=> 'Reviews',
		  								'maxScore' 	=> 4,
			  							'criterion' => array(	'forTotalReview'				=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'Reviews are an important part of the ShipServ ranking algorithm. You have not got any. Reviews can be written by buyers after they have transacted with you on TradeNet. For more information on TradeNet see the ShipServ <a href="/info/save-time-and-money-with-tradenet/?b=1">website</a>' ),
		  																									'range>0'				=> array('score' => array(4),		'todo' => '', 'info' => 'You have at least 1 review but more reviews are even better. Please encourage your buyers to add further reviews' ) ) ) ) 
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'tradeNetMember'	=> array(	'heading' 	=> 'TradeNet',
		  								'maxScore' 	=> 1,
		  								'criterion' => array(	'forTradeNetMember'				=> array(	'true' 					=> array('score' => array(1),		'todo' => '', 'info' => 'You are trading on TradeNet' ),
		  																									'false'					=> array('score' => array(0),		'todo' => 'Trading on TradeNet is an important part of the ShipServ ranking algorithm. You have not traded on TradeNet. For more information on TradeNet see the ShipServ <a href="/info/save-time-and-money-with-tradenet/?b=1">website</a>' ) ) ) ) 
		  
		  
	);
	
	public $premiumListerRules = array( 
	
	     'telephone' 			=> array( 	'heading' 	=> 'Telephone',
		   								'maxScore'	=> 3, 
									  	'criterion' => array(	'forTelephone' 					=> array(	'true' 					=> array('score' => array(3),	 	'todo' => '', 'info' => 'You have telephone number'), 
																											'false' 				=> array('score' => 0, 				'todo' => 'Make sure that you have a telephone number on your profile', 'info' => '') 
																										)															) 
									)
 		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		 ,'totalWordListing' => array( 	'heading' 	=> 'Total word',
		   								'maxScore'	=> 7, 
									  	'criterion' => array(	'forTotalWordsOfListing' 		=> array(	'range==0' 				=> array('score' => 0, 				'todo' => 'Please complete a description of your business'),
																											'range>=1&&range<=150' 	=> array('score' => array(4), 		'todo' => 'You have a description on your profile but you could benefit from making it longer and more detailed'), 
																											'range>150' 			=> array('score' => array(7), 		'todo' => '', 'info' => 'You have good description on your profile but you could benefit from reviewing it' ) ) ) )

																										
 		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'address'		=> array(	'heading' 	=> 'Address',
		  								'maxScore' 	=> 3,
		  								'criterion' => array(																	
		  														'forAddress' 					=> array(	'true' 					=> array('score' => array(3),	 	'todo' => '', 'info' => 'You have address'), 
																											'false' 				=> array('score' => 0, 				'todo' => 'Make sure that you have an address on your profile', 'info' => '') 
																										)
		  													)
		  							)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'website'		=> array(	'heading' 	=> 'Website',
		  								'maxScore' 	=> 3,
		  								'criterion' => array(	'forWebsite' 					=> array(	'true' 					=> array('score' => array(3),	 	'todo' => '', 'info' => 'You have website'), 
																											'false' 				=> array('score' => 0, 				'todo' => 'Make sure that you have a website on your profile', 'info' => '') 
																								)
		  													)
		  							)
																										
		// --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'contacts' 		=> array(	'heading' 	=> 'Contact information',
		  								'maxScore' 	=> 4,
		  								'criterion' => array( 	'forTotalContacts' 				=> array(	'range==0' 				=> array('score' => 0, 				'todo' => 'You have no contacts on your profile. Add them now' ),
		  																									'range>=1' 				=> array('score' => array(4), 		'todo' => '', 'info' => 'You might want to add more contact names to your profile' ) ) ) )
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'primaryCategories'=> array(	'heading' 	=> 'Primary categories',
		  								'maxScore' 	=> 9,
		  								'criterion' => array(	'forTotalPrimaryCategories'		=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have no primary categories listed. This is very important you add a category here'), // basic can have one 
		  																									'range==1' 				=> array('score' => array(5),		'todo' => 'You have one primary category on your profile, you will benefit significantly from adding more categories' ), 
		  																									'range>1' 				=> array('score' => array(9), 		'todo' => '', 'info' => 'You could benefit by adding more categories'),
		 																								) 
		 													) 
		 								)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'secondaryCategories'		=> array(	'heading' 	=> 'Secondary categories',
		  								'maxScore' 	=> 5,
		  								'criterion' => array(	'forTotalSecondaryCategories'	=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have no secondary (other) categories. There is no maximum number of secondary category you can add as long as they are relevant to your business'),
		  																									'range==1' 				=> array('score' => array(1), 		'todo' => 'There is no maximum number of secondary (other) categories you can add as long as they are relevant to your business'), 
		  																									'range>1&&range<=6' 	=> array('score' => array(2), 		'todo' => 'There is no maximum number of secondary (other) categories you can add as long as they are relevant to your business'),
		  																									'range>=6' 				=> array('score' => array(5),		'todo' => '', 'info' => 'There is no maximum number of secondary category you can add as long as they are relevant to your business' ) 
		 																								) 
		 													) 
		 								)																			
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'brands'			=> array(	'heading' 	=> 'Brands',
		  								'maxScore' 	=> 18,
		  								'criterion' => array(	'forTotalBrand'					=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have zero brands on your profile. This is important to change this. Add brands to your profile if you supply them'), 
		  																									'range>=1&&range<=3' 	=> array('score' => array(5),		'todo' => 'You have a small number of brands on your profile. Add some more to your profile if you supply them' ),
		  																									'range>=4&&range<=9' 	=> array('score' => array(12),		'todo' => 'You have a small number of brands on your profile. Add some more to your profile if you supply them' ), 
		  																									'range>=10' 			=> array('score' => array(18),		'todo' => '', 'info' => 'There is no maximum number of brands you can add to your profile as long as you supply them. Please add more if relevant' ) ) ) )
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'ports'			=> array(	'heading' 	=> 'Ports',
		  								'maxScore' 	=> 7,
		  								'criterion' => array(	'forTotalPorts'					=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have no ports on your profile. This is important to change this. You can add up to 60 ports' ), 
		  																									'range>=1&&range<=3' 	=> array('score' => array(2),		'todo' => 'You have a small number of ports on your profile. You can add up to 60 ports' ), 
		  																									'range>=4&&range<=9' 	=> array('score' => array(4), 		'todo' => 'You have a small number of ports on your profile. You can add up to 60 ports' ), 
		  																									'range>=10' 			=> array('score' => array(7), 		'todo' => '', 'info' => 'You can add up to 60 ports on your profile. Please review to see if you should add more' ) ) ) )
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'catalogues'		=> array(	'heading' 	=> 'Catalogues',
		  								'maxScore' 	=> 9,
		  								'criterion' => array(	'forTotalCatalogues'			=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'To add your catalogues to your profile please contact your ShipServ account manager' ), 
		  																									'range>0' 				=> array('score' => array(9), 		'todo' => '', 'info' => 'You have added catalogues' ) ) ) )
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
		  ,'media'			=> array(	'heading' 	=> 'Media',
		  								'maxScore' 	=> 11,
		  								'criterion' => array(	'forTotalLogo'					=> array(	'true' 					=> array('score' => array(4),		'todo' => '', 'info' => 'You have added your company logo' ), 
		  																									'false' 				=> array('score' => array(0), 		'todo' => 'It is very important that you add your company logo to your profile. This will make your company standout in search results' ) ),
																'forTotalOtherMedia'			=> array(	'true' 					=> array('score' => array(7),		'todo' => '', 'info' => 'You have PDF or video on your profile. Review if there are any more documents you can add' ), 
																  											'false' 				=> array('score' => array(0), 		'todo' => 'Upload video, word documents or PDFs to your profile. For example product brochures or fact sheets' ) ) ) )					 							
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'memberships'	=> array(	'heading' 	=> 'Memberships',
		  								'maxScore' 	=> 2,
		  								'criterion' => array(	'forTotalMembership'			=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'Add memberships to your profile. For example: IMPA and ISSA' ), 
		  																									'range>0' 				=> array('score' => array(2), 		'todo' => '', 'info' => 'You have added at least 1 membership to your profile' ) ) ) )
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'certification'	=> array(	'heading' 	=> 'Certification',
		  								'maxScore' 	=> 2,
		  								'criterion' => array(	'forTotalCertification'			=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'Add any certifications to your profile. For example: ISO 9001 certification' ), 
		  																									'range>0' 				=> array('score' => array(2), 		'todo' => '', 'info' => 'You have added at least 1 certification to your profile. Are there any others?' ) ) ) )
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'brandAuthorisation'		=> array(	'heading' 	=> 'Authorisation',
		  								'maxScore' 	=> 5,
		  								'criterion' => array(	'forTotalAuthorisation'			=> array(	'false' 				=> array('score' => array(0),		'todo' => '', 'info' => 'No brands' ), // not done yet
		  																									'range==0' 				=> array('score' => array(0),		'todo' => 'Please review your authorised supplier status for all the brands you supply or deal with', 'info' => '' ),
		  																									'range>0&&range<49'		=> array('score' => array(3),		'todo' => 'Please review your authorised supplier status for all the brands you supply or deal with' ), 
		  																									'range>=50' 			=> array('score' => array(5), 		'todo' => '', 'info' => 'Please review your authorised supplier status for all the brands you supply or deal with' ) 
		  																								) 
		  													) 
		  							)		  
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'admin'			=> array(	'heading' 	=> 'Pages Admin',
		  								'maxScore' 	=> 3,
		  								'criterion' => array(	'forAdmin'						=> array(	'false' 				=> array('score' => array(0),		'todo' => '', 'info' => 'You have at least 1 administrator' ), // not done yet
		  																									'true' 					=> array('score' => array(3),		'todo' => 'There is no ShipServ Pages administrator for your company, please contact <a href="mailto:support@shipserv.com">support@shipserv.com</a>', 'info' => '' )
		  																								) 
		  													) 
		  							)

		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'listing'		=> array(	'heading' 	=> 'Listing freshness',
		  								'maxScore' 	=> 1,
		  								'criterion' => array(	'forVerifiedListing'			=> array(	'true' 			=> array('score' => array(1),		'todo' => '', 'info' => 'You have updated your profile' ), // not done yet
		  																									'false'			=> array('score' => array(0),		'todo' => 'You have not updated your profile in the last 12 months', 'info' => '' )
		  																								) 
		  													) 
		  							)
		  							
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'marinersAnnual' => array(	'heading' 	=> 'Mariners annual',
		  								'maxScore' 	=> 4,
		  								'criterion' => array(	'forMarinersAnnual'				=> array(	'true' 			=> array('score' => array(4),		'todo' => '', 'info' => 'You are in ShipServ Onboard' ), 
		  																									'false'			=> array('score' => array(0),		'todo' => 'Appearing in the ShipServ Onboard will show on your profile and will mean you are included in more search results. For information on appearing on the guide contact <a href="mailto:support@shipserv.com">support@shipserv.com</a>', 'info' => '' )
		  																								) 
		  													) 
		  							)
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'review'			=> array(	'heading' 	=> 'Reviews',
		  								'maxScore' 	=> 3,
		  								'criterion' => array(	'forTotalReview'				=> array(	'range==0' 				=> array('score' => array(0),		'todo' => 'You have not got any Reviews, they can be written by buyers after they have 
		  																																											transacted with you on TradeNet. For more information see the 
		  																																											ShipServ <a href="/info/save-time-and-money-with-tradenet/?b=1">website</a>' ),
		  																									'range>0'				=> array('score' => array(3),		'todo' => '', 'info' => 'You have at least 1 review but more reviews are even better. Please encourage your buyers to add further reviews' ) ) ) ) 
		  // --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------							
		  ,'tradeNetMember'	=> array(	'heading' 	=> 'TradeNet',
		  								'maxScore' 	=> 1,
		  								'criterion' => array(	'forTradeNetMember'				=> array(	'true' 					=> array('score' => array(1),		'todo' => '', 'info' => 'You are trading on TradeNet' ),
		  																									'false'					=> array('score' => array(0),		'todo' => 'Trading on TradeNet is an important part of the ShipServ ranking algorithm. You have not traded on TradeNet. For more information on TradeNet see the ShipServ <a href="/info/save-time-and-money-with-tradenet/?b=1">website</a>' ) ) ) ) 
		  
	  
	);	
	
	/**
	 * Store supplier and execute the report
	 * 
	 * @param int $tnid
	 */
	function __construct( $tnid )
	{
		if( $tnid == "" )
		{
			throw new Exception("Profile checker, missing TNID");
		}
		
		if( ctype_digit( $tnid ) )
		{
			$this->supplier = Shipserv_Supplier::fetch($tnid, $this->getDb());
		}

		if( is_object( $tnid ) )
		{
			$this->supplier = $tnid;
		}
		$this->execute();
		
	}
	public function getDebug()
	{
		return str_replace("getScoreFor", "", $this->debug) . "<br /><br /><br />";
	}
	/**
	 * Publicly accessible function to get the completeness of a TNID
	 * @return int score
	 */
	public function getScore()
	{
		$totalScore = 0;
		foreach ($this->score as $score )
		{
			$totalScore += $score;
		}
		return $totalScore;
	}

	/**
	 * Publicly accessible function to get completeness of a TNID as a percentage
	 * @return int score
	 */
	public function getCompletenessAsPercentage()
	{
		return round($this->getScore() );
		//return round($this->getScore() / $this->totalMaxScore * 100);	
	}
	
	
	/**
	 * Publicly accessible function that is being used by the partial to show todo list of a listing
	 * @return array todos
	 */
	public function getToDoForPartial()
	{
		return $this->toDo;
	}
	
	public function getCompletedTaskForPartial()
	{
		return $this->completed;
	}
	
	/**
	 * Create todo as a HTML
	 * @return int score
	 * @deprecated
	 */
	public function getToDoAsHtmlBlock()
	{
		$score = $this->getScore();
		$totalMaxScore = $this->totalMaxScore;
		$html = '';
		$html .= 'Your profile is: ' . $score / $totalMaxScore * 100 . "% complete";
		$html .= " which you have scored: " . $score .' / ' . $totalMaxScore;
		
		
		foreach( (array)$this->completed as $sectionName => $toDos )
		{
			$html .= "<h1>" . $this->rules[$sectionName]['heading'] . "</h1>";
			foreach( $toDos as $toDo )
			{
				$html .= "<br /> - $toDo";
			}
		}	
		
		$html .= "====================================================================================";
		
		foreach( (array)$this->toDo as $sectionName => $toDos )
		{
			$html .= "<h1>" . $this->rules[$sectionName]['heading'] . "</h1>";
			foreach( $toDos as $toDo )
			{
				$html .= "<br /> - $toDo";
			}
		}	
		return $html;
	}
	// 59637 -- happy supplier limited
	// 206532 -- sad supplier limited
	/**
	 * This private function will try to match the input data (total) with the 
	 * rules and apply correct score and storing todo
	 * 
	 * @param mixed $total this can be true|false|array|int
	 * @param array $rules that being passed by the single criterion function
	 * @param string $sectionName
	 * @param boolean $debug
	 */
	private function getScoreByRule( $total, $rules, $sectionName, $debug = true )
	{
		$totalScored = 0;
		

		$trace = debug_backtrace();
		$caller = array_shift($trace);
		$caller = array_shift($trace);
		$functionName = $caller['function'];
		
		ob_start();
		
		if( $debug ) echo "<hr class='dotted' style='width:50%; margin-bottom:5px;' /><div class='clear'></div>";
		if( $debug ) echo "Section: $functionName<br />";
		if( $debug ) echo "Data: " . ((gettype($total)=="boolean")?(($total)?'true':'false'):$total) . "<br />";
		
		// parse thru the rules
		foreach( $rules as $rule => $data )
		{
			$score = $data['score'];
			$todo = $data['todo'];
			$info = $data['info'];
			//$totalScored = 0;
			$maxScore = $score[0];
			
			if( $this->isPremium() )
			{
				$possibleScore = $this->premiumListerRules[$sectionName]['maxScore'];
			}
			else 
			{
				$possibleScore = $this->basicListerRules[$sectionName]['maxScore'];	
			}
			
			// translating string to conditional rule
			if( strstr($rule, "range") !== false )
			{
				$syntaxForConditional = array();
				$conditions = explode('&&', $rule);
				
				foreach( $conditions as $condition )
				{ 
					$match = preg_match("/range(\>|\>\=|\=\=|\<\=|\<)([0-9]+)/", $condition, $matches );
					if( count( $matches ) == 3 )
					{
						$syntaxForConditional[] = '( $total ' . $matches[1] . ' ' . $matches[2] . ' )';
					}
				}
				
				if( $maxScore == '' ) $maxScore = 0;

				$syntax = '
					$'.'a=$'.'maxScore . " " . $'.'totalScored;
				
					if( ' . implode(" && ", $syntaxForConditional) . '  )
					{ 
						$'.'totalScored=' . $maxScore . ';
						
						// storing the points
						$'.'this->debugScores[$' . 'sectionName' . '][]=$' . 'maxScore' . ';
						
						// storing the todo if available
						if( $' . 'todo != "" )
						{ 
							$'.'this->toDo[] = array( "score" => $' . 'possibleScore' . '-$' . 'maxScore' . ', "todo" => $' . 'todo' . ');
						} 
						
						// storing information of what\'s been done that scored him a point
						if( $' . 'info != "" )
						{ 
							$'.'this->completed[]=array( "score" => $' . 'maxScore' . ', "info" => $' . 'info' . ');
						}
						else
						{
							if( $' . 'maxScore' . ' > 0 )
						 	$'.'this->completed[]=array( "score" => $' . 'maxScore' . ', "info" => "Section name: " . $' . 'functionName' . ');
						}
					}
				';
				
				eval( $syntax );
				
				//if( $debug ) echo nl2br($syntax);
				//if( $debug ) echo "a: $a <br />TOTALSCOREDBYTHISSYNTAXIS:" . $totalScored;
				//if( $debug ) echo "<hr />";
			}
			else if( $rule == 'true' || $rule == 'false' ) 
			{
				if( $rule == 'true' ) $rule = true;
				else $rule = false;
				
				// check the true
				if( $total === true && $rule === true)
				{
					$totalScored = $maxScore;
					$this->debugScores[$sectionName][] = $totalScored;
					if( $info != "" )
					{
						$this->completed[]=array("score" => $maxScore, "info" => $info );
					}
					else
					{
						if( $maxScore > 0 )
						$this->completed[]=array("score" => $maxScore, "info" => "Section name: " . $functionName );
					}
					if( $debug ) echo "Total Score: $totalScored <br />";
					$this->debug .= ob_get_contents();
					ob_end_clean();
										
					return $totalScored;
				}
				
				if( $total === false && $rule === false )
				{
					if( $todo != "" && $totalScored == 0 )
					{
						$this->toDo[] = array("score" => $possibleScore-$maxScore, "todo" => $todo );
					}
					
					if( $debug ) echo "Total Score: $totalScored <br />";
					$this->debug .= ob_get_contents();
					ob_end_clean();
							
					return $totalScored;
				}
			}
		}

		if( $debug ) echo "Total Score: $totalScored <br />";
		$this->debug .= ob_get_contents();
		ob_end_clean();
		return $totalScored;
		
	}
	
	private function getScoreForTelephone(&$score, $sectionName)
	{
		$t = false;
		if( (trim($this->supplier->phoneNo) != "-" && trim($this->supplier->phoneNo) != "" && is_null($this->supplier->phoneNo) == false ) 
		 || (trim($this->supplier->faxNo) != "-" && trim($this->supplier->faxNo) != "" && is_null($this->supplier->faxNo) == false ) 
		 || (trim($this->supplier->afterHoursNo) != "-" && trim($this->supplier->afterHoursNo) != "" && is_null($this->supplier->afterHoursNo) == false ) 
		)
		{
			$t = true;
		}
		$score = $this->getScoreByRule( $t, $score, $sectionName );
	}

	private function getScoreForAddress(&$score, $sectionName)
	{
		$t = false;
		
		if( $this->supplier->address1 )
		{
			$t = true;
		}
		
		$score = $this->getScoreByRule( $t, $score, $sectionName );
	}
	
	private function getScoreForWebsite(&$score, $sectionName)
	{
		$t = false;
		if( $this->supplier->homePageUrl != "")
		{
			$t = true;
		}
		$score = $this->getScoreByRule( $t, $score, $sectionName );
	}
		
	private function getScoreForTotalWordsOfListing(&$score, $sectionName)
	{
		if( $this->supplier->description == null )
		{
			$total = 0;
		}
		else
		{
			$words = explode(" ", trim($this->supplier->description) );
			$total = count( $words );
		}
		$score = $this->getScoreByRule( $total, $score, $sectionName );
	}
	
	private function getScoreForTotalContacts(&$score, $sectionName)
	{
		$total = 0;
		foreach( $this->supplier->contacts as $contact )
		{
			if( $contact['status'] != 'HIDDEN' )
			{
				$total++;
			}
		}
		$score = $this->getScoreByRule( $total, $score, $sectionName );
	}
	
	private function getScoreForTotalPrimaryCategories(&$score, $sectionName)
	{
		$total = 0;
		foreach( $this->supplier->categories as $item )
		{
			if( $item['primary'] == '1' )
			{
				$total++;
			}
		}
		$score = $this->getScoreByRule( $total, $score, $sectionName );		
	}
	
	private function getScoreForTotalSecondaryCategories(&$score, $sectionName)
	{
		$total = 0;
		foreach( $this->supplier->categories as $item )
		{
			if( $item['primary'] == '0' )
			{
				$total++;
			}
		}
		$score = $this->getScoreByRule( $total, $score, $sectionName );		
	}
	
	private function getScoreForTotalBrand(&$score, $sectionName)
	{
		$uniqueBrands = array();
		$total = $totalAuthorised = 0;
		
		$total = count( $this->supplier->brands );
		
		if( $total > 0 )
		{
			foreach( (array) $this->supplier->brands as $brand )
			{
				if( in_array($brand['name'], $uniqueBrands) === false )
				{
					$uniqueBrands[] =$brand['name'];
				}	
			}
			
		}
						
		$score = $this->getScoreByRule( count( $uniqueBrands ), $score, $sectionName );
	}
	
	private function getScoreForTotalPorts(&$score, $sectionName)
	{
		$score = $this->getScoreByRule( count( $this->supplier->ports ), $score, $sectionName );		
	}

	private function getScoreForTotalLogo(&$score, $sectionName)
	{
		$t = false;
		if( $this->supplier->logoUrl != "" )
		{
			$t = true;
		}
		$score = $this->getScoreByRule( $t, $score, $sectionName );		
	}
	
	private function getScoreForTotalOtherMedia(&$score, $sectionName)
	{
		$t = false;
		
		if( count($this->supplier->videos) > 0 || count( $this->supplier->attachments ) > 0 || count( $this->supplier->maAttachments ) > 0 )
		{
			$t = true;
		}
		$score = $this->getScoreByRule( $t, $score, $sectionName );		
	}
	
	
	private function getScoreForTotalCatalogues(&$score, $sectionName)
	{
		$score = $this->getScoreByRule( count( $this->supplier->catalogues ), $score, $sectionName );		
	}
	
	private function getScoreForTotalMembership(&$score, $sectionName)
	{
		$score = $this->getScoreByRule( count( $this->supplier->memberships ), $score, $sectionName );		
	}
	
	private function getScoreForTotalCertification(&$score, $sectionName)
	{
		$score = $this->getScoreByRule( count( $this->supplier->certifications ), $score, $sectionName );		
	}	
	
	private function getScoreForTotalAuthorisation(&$score, $sectionName)
	{
		$total = $totalAuthorised = 0;
		
		$total = count( $this->supplier->brands );
		
		if( $total > 0 )
		{
			foreach( (array) $this->supplier->brands as $brand )
			{
				if( $brand['isAuthorised'] === 'Y' ) 
				{
					$totalAuthorised++;	
				}
			}
			$score = @$this->getScoreByRule( ($totalAuthorised/$total) * 100, $score, $sectionName );
		}
		else
		{
			$score = @$this->getScoreByRule( false, $score, $sectionName );
		}	
				
	}		
	
	private function getScoreForTotalReview(&$score, $sectionName)
	{
		$total = Shipserv_Review::getReviewsCounts(array($this->supplier->tnid));
		if( empty($total) == true )
		{
			$total = 0;
		}
		$score = $this->getScoreByRule( $total, $score, $sectionName );		
	}		
	
	private function getScoreForTradeNetMember(&$score, $sectionName)
	{
		$score = $this->getScoreByRule( ( ( $this->supplier->tradeRank > 0 ) ? true : false), $score, $sectionName );
	}

	private function getScoreForMarinersAnnual(&$score, $sectionName)
	{
		$adapter = new Shipserv_Oracle_Suppliers( $this->getDb());
		$total = $adapter->getTotalMAAttachments( $this->supplier->tnid);
		$score = $this->getScoreByRule( ( ( $total > 0 ) ), $score, $sectionName );
	}
	
	private function getScoreForVerifiedListing(&$score, $sectionName)
	{
		$supplierDbAdapter = new Shipserv_Oracle_Suppliers( $this->getDb() );
		$score = $this->getScoreByRule( $supplierDbAdapter->isVerifiedById( $this->supplier->tnid ), $score, $sectionName );
	}
	
	private function getScoreForAdmin(&$score, $sectionName)
	{
		
		$adapter = new Shipserv_Oracle_Suppliers( $this->getDb());
		$userArr = $adapter->getActiveUsersByTnid( $this->supplier->tnid);
		
		foreach( $userArr as $user )
		{
			if( $user['PUC_LEVEL'] == 'ADM') $admin++;
		}
		$score = $this->getScoreByRule( (($admin>0)?true:false), $score, $sectionName );
	}
	
	
	private function getDb()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	private function isPremium()
	{
		
		return ( $this->supplier->premiumListing == "1") ? true:false;
	}
	
	private function execute()
	{
		// pull the right rule
		$rules = ( $this->isPremium() ) ? $this->premiumListerRules: $this->basicListerRules;
		$this->debug = "<hr class='dotted' style='width:50%; margin-bottom:5px;' /><div class='clear'></div><br />Listing type: " . (($this->isPremium())?"Premium": "Basic") . "<br />";		
		// parse all rules and calculate the score
		foreach( $rules as $sectionName => $rule )
		{
			$maxScore = $rule['maxScore'];
			$this->maxScore = $maxScore;
			if( count( $rule['criterion'] ) > 0 )
			{
				// parse through all criterias
				foreach( $rule['criterion'] as $rawFunctionName => $score )
				{
					// preparing the function name
					$functionName = 'getScore' . ucfirst( $rawFunctionName );
					
					// executing the function
					$this->{ $functionName }( $score, $sectionName );
					
					// store the score
					$this->score[ $rawFunctionName ] = $score;
					
					// store the max score temporarily
					//$this->maxScore -= $score;	
				}
			}	
			$this->totalMaxScore += $maxScore;
		}
	}
	
	public static function storeAllTnidToDb()
	{
		
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set('memory_limit', -1);
		
		$sql = "SELECT spb_branch_code FROM supplier_branch WHERE ";
			$sql.= "  spb_account_deleted = 'N'";
			$sql.= " AND spb_test_account = 'N'";
			$sql.= " AND spb_branch_code <= 999999";
			$sql.= " AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)";
			$sql.= " AND rownum < 4000 ";
		
		$data = self::getDb()->fetchAll($sql);
		$score = array();
		foreach( $data as $row )
		{
			$tnid = $row["SPB_BRANCH_CODE"];
			$supplier = Shipserv_Supplier::fetch($tnid, self::getDb());
			$profile = new self($tnid, self::getDb());
			
			echo '[' . $supplier->tnid . ']: ' . $supplier->name . ' scored =  ' . $profile->getCompletenessAsPercentage() . '%<br />';
			
			$score[$profile->getCompletenessAsPercentage()] = $supplier->tnid;
			/*
			$sql = 'SELECT COUNT(*) as TOTAL FROM pages_salesforce WHERE psf_tnid=' . $tnid;
			$total = self::getDb()->fetchAll($sql);
			if( $total[0]['TOTAL'] == 0 )
			{
				$sql = 'INSERT INTO pages_salesforce (psf_tnid, psf_profile_completion) values(:tnid, :score)';
			}
			else 
			{
				$sql = 'UPDATE pages_salesforce SET psf_profile_completion=:score WHERE psf_tnid=:tnid';
				
			}
			self::getDb()->query($sql, array('tnid' => $tnid, 'score' => $profile->getCompletenessAsPercentage()));
			*/
		}
		
		krsort($score);
		
		var_dump( $score );
		
	}
}
