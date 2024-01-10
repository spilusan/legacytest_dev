<?php

/**
 * RFQ comment fields
 */
class Shipserv_TnMsg_Rfq_Comments
{
	private $humanRfqRef;
	private $termsOfPayment;
	private $generalTerms;
	private $subject;
	private $buyerComments;
	private $packagingInstructions;
	private $currencyInstructions;
	
	/**
	 * @param string $humanRfqRef
	 */
	public function __construct ($humanRfqRef)
	{
		$humanRfqRef = trim($humanRfqRef);
		
		if ($humanRfqRef == '')
		{
			throw new Exception("Human readable RFQ reference cannot be empty");
		}
		else
		{
			$this->humanRfqRef = $humanRfqRef;
		}
	}
	
	public function __get ($name)
	{
		if (property_exists($this, $name))
		{
			return $this->$name;
		}
		else
		{
			throw new Exception("Requested property does not exist");
		}
	}
	
	/**
	 * @param string $terms
	 */
	public function setTermsOfPayment ($terms = null)
	{
		$terms = trim($terms);
		
		if ($terms == '')
		{
			$this->termsOfPayment = null;
		}
		else
		{
			$this->termsOfPayment = $terms;
		}
	}
	
	/**
	 * @param string $terms
	 */
	public function setGeneralTermsAndConditions ($terms = null)
	{
		$terms = trim($terms);
		
		if ($terms == '')
		{
			$this->generalTerms = null;
		}
		else
		{
			$this->generalTerms = $terms;
		}
	}
	
	/**
	 * @param string $subject
	 */
	public function setSubject ($subject = null)
	{
		$subject = trim($subject);
		
		if ($subject == '')
		{
			$this->subject = null;
		}
		else
		{
			$this->subject = $subject;
		}
	}
		
	public function setBuyerComments ($comments = null)
	{
		$comments = trim($comments);
		
		if ($comments == '')
		{
			$this->buyerComments = null;
		}
		else
		{
			if (strpos($comments, '-') === false)
			{
				$this->buyerComments = $comments;
			}
			else
			{
				throw new Exception("Illegal character");
			}
		}
	}
	
	public function setPackagingInstructions ($instructions = null)
	{
		$instructions = trim($instructions);
		
		if ($instructions == '')
		{
			$this->packagingInstructions = null;
		}
		else
		{
			if (strpos($instructions, '-') === false)
			{
				$this->packagingInstructions = $instructions;
			}
			else
			{
				throw new Exception("Illegal character");
			}
		}
	}
	
	public function setCurrencyInstructions ($instructions = null)
	{
		$instructions = trim($instructions);
		
		if ($instructions == '')
		{
			$this->currencyInstructions = null;
		}
		else
		{
			if (strpos($instructions, '-') === false)
			{
				$this->currencyInstructions = $instructions;
			}
			else
			{
				throw new Exception("Illegal character");
			}
		}
	}
	
	public function getPurchasingInfo ()
	{
		$resStr = null;
		
		if (
			$this->buyerComments === null
			&& $this->packagingInstructions === null
			&& $this->currencyInstructions === null
		)
		{
			return $resStr;
		}
		else
		{
			$resStr = '';
			
			$resStr .= $this->buyerComments;
			$resStr .= '-' . $this->packagingInstructions;
			$resStr .= '-' . $this->currencyInstructions;
			
			return rtrim($resStr, '-');
		}
	}
}
