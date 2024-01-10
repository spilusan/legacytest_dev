<?php

class Myshipserv_tnImporter_UserCompanyBuyerSql
{
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	public function populateBranchUsers ($email = null)
	{
		if ($email !== null)
		{
			$normEmail = strtolower(trim($email));
			$emailSql = "AND LOWER(TRIM(bbu.BBU_EMAIL_ADDRESS)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'BYO',
	bbu.BBU_BYO_ORG_CODE,
	LOWER(TRIM(bbu.BBU_EMAIL_ADDRESS)),
	TRIM(bbu.BBU_FIRST_NAME),
	TRIM(bbu.BBU_LAST_NAME),
	'Y'										-- Importable as Pages user
	
	FROM BUYER_BRANCH_USER bbu
	WHERE
		
		-- Supplier user must be active
		bbu.BBU_STS = 'ACT'
		
		-- Active buyer branch must exist
		AND EXISTS
		(
			SELECT * FROM BUYER_BRANCH WHERE
				BYB_BRANCH_CODE = bbu.BBU_BYB_BRANCH_CODE
				AND BYB_STS = 'ACT'
				AND BYB_TEST_ACCOUNT = 'N'
		)
		
		-- Active USER of correct type must exist
		AND EXISTS
		(
			SELECT * FROM USERS WHERE
				USR_USER_CODE = bbu.BBU_USR_USER_CODE
				AND USR_TYPE = 'B'
				AND (USR_STS = 'ACT' OR USR_STS IS NULL)
		)
		
		-- Optional constraint on e-mail
		$emailSql
EOT;
		
		return $sql;
	}

	public function populateBranchRegistrants ($email = null)
	{
		if ($email !== null)
		{
			$normEmail = strtolower(trim($email));
			$emailSql = "AND LOWER(TRIM(BYB_REGISTRANT_EMAIL_ADDRESS)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'BYO',
	BYB_BYO_ORG_CODE,
	LOWER(TRIM(BYB_REGISTRANT_EMAIL_ADDRESS)),
	TRIM(BYB_REGISTRANT_FIRST_NAME),
	TRIM(BYB_REGISTRANT_LAST_NAME),
	'N'										-- Importable as Pages user
	
	FROM BUYER_BRANCH
	WHERE
		BYB_STS = 'ACT'
		AND BYB_TEST_ACCOUNT = 'N'
		
		-- Optional constraint on e-mail
		$emailSql
EOT;
		
		return $sql;
	}
	
	public function populateBranchEmails ($email = null)
	{
		if ($email !== null)
		{
			$normEmail = strtolower(trim($email));
			$emailSql = "AND LOWER(TRIM(BYB_EMAIL_ADDRESS)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'BYO',
	BYB_BYO_ORG_CODE,
	LOWER(TRIM(BYB_EMAIL_ADDRESS)),
	NULL,	-- First name
	NULL,	-- Last name
	'N'										-- Importable as Pages user
	
	FROM BUYER_BRANCH
	WHERE
		BYB_STS = 'ACT'
		AND BYB_TEST_ACCOUNT = 'N'
		
		-- Optional constraint on e-mail
		$emailSql
EOT;
		
		return $sql;
	}
	
	public function populateOrgEmails ($email = null)
	{
		if ($email !== null)
		{
			$normEmail = strtolower(trim($email));
			$emailSql = "AND LOWER(TRIM(byo.BYO_CONTACT_EMAIL)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'BYO',
	byo.BYO_ORG_CODE,
	LOWER(TRIM(byo.BYO_CONTACT_EMAIL)),
	NULL,									-- First name
	NULL,									-- Last name
	'N'										-- Importable as Pages user
	
	FROM BUYER_ORGANISATION byo
	WHERE
		EXISTS
		(
			SELECT * FROM BUYER_BRANCH WHERE
				BYB_BYO_ORG_CODE = byo.BYO_ORG_CODE
				AND BYB_STS = 'ACT'
				AND BYB_TEST_ACCOUNT = 'N'
		)
		
		-- Optional constraint on e-mail
		$emailSql
EOT;

		return $sql;
	}
}
