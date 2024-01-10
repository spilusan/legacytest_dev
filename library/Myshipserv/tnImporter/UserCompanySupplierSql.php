<?php

class Myshipserv_tnImporter_UserCompanySupplierSql
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
			$emailSql = "AND LOWER(TRIM(sbu.SBU_EMAIL_ADDRESS)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'SPB',
	sbu.SBU_SPB_BRANCH_CODE,
	LOWER(TRIM(sbu.SBU_EMAIL_ADDRESS)),
	TRIM(sbu.SBU_FIRST_NAME),
	TRIM(sbu.SBU_LAST_NAME),
	'Y'
	
	FROM SUPPLIER_BRANCH_USER sbu
	WHERE
		
		-- Supplier user must be active
		sbu.SBU_STS = 'ACT'
		
		-- Active supplier branch must exist
		AND EXISTS
		(
			SELECT * FROM SUPPLIER_BRANCH WHERE
				SPB_BRANCH_CODE = sbu.SBU_SPB_BRANCH_CODE
				AND SPB_STS = 'ACT'
				AND SPB_TEST_ACCOUNT = 'N'
		)
		
		-- Active USER of correct type must exist
		AND EXISTS
		(
			SELECT * FROM USERS WHERE
				USR_USER_CODE = sbu.SBU_USR_USER_CODE
				AND USR_TYPE = 'V'
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
			$emailSql = "AND LOWER(TRIM(SPB_REGISTRANT_EMAIL_ADDRESS)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'SPB',
	SPB_BRANCH_CODE,
	LOWER(TRIM(SPB_REGISTRANT_EMAIL_ADDRESS)),
	TRIM(SPB_REGISTRANT_FIRST_NAME),
	TRIM(SPB_REGISTRANT_LAST_NAME),
	'N'
	
	FROM SUPPLIER_BRANCH
	WHERE
		SPB_STS = 'ACT'
		AND SPB_TEST_ACCOUNT = 'N'
		
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
			$emailSql = "AND LOWER(TRIM(SPB_EMAIL)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'SPB',
	SPB_BRANCH_CODE,
	LOWER(TRIM(SPB_EMAIL)),
	NULL,	-- First name
	NULL,	-- Last name
	'N'
	
	FROM SUPPLIER_BRANCH
	WHERE
		SPB_STS = 'ACT'
		AND SPB_TEST_ACCOUNT = 'N'
		
		-- Optional constraint on e-mail
		$emailSql
EOT;
		
		return $sql;
	}

	public function populateBranchPublicContacts ($email = null)
	{
		if ($email !== null)
		{
			$normEmail = strtolower(trim($email));
			$emailSql = "AND LOWER(TRIM(PUBLIC_CONTACT_EMAIL)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'SPB',
	SPB_BRANCH_CODE,
	LOWER(TRIM(PUBLIC_CONTACT_EMAIL)),
	NULL,	-- First name
	NULL,	-- Last name
	'N'
	
	FROM SUPPLIER_BRANCH		
		WHERE SPB_STS = 'ACT'
		AND SPB_TEST_ACCOUNT = 'N'
		
		-- Optional constraint on e-mail
		$emailSql
EOT;
		
		return $sql;
	}

	public function populateBranchContacts ($email = null)
	{
		if ($email !== null)
		{
			$normEmail = strtolower(trim($email));
			$emailSql = "AND LOWER(TRIM(cp.ELECTRONIC_MAIL)) = " . $this->getDb()->quote($normEmail);
		}
		else
		{
			$emailSql = '';
		}
		
		$sql = 
<<<EOT
SELECT
	'SPB',
	cp.SUPPLIER_BRANCH_CODE,
	LOWER(TRIM(cp.ELECTRONIC_MAIL)),
	TRIM(cp.FIRST_NAME),
	TRIM(cp.LAST_NAME),
	'N'
	
	FROM CONTACT_PERSON cp
	WHERE
		-- Active supplier branch must exist
		EXISTS
		(
			SELECT * FROM SUPPLIER_BRANCH WHERE
				SPB_BRANCH_CODE = cp.SUPPLIER_BRANCH_CODE
				AND SPB_STS = 'ACT'
				AND SPB_TEST_ACCOUNT = 'N'
		)
		
		-- Optional constraint on e-mail
		$emailSql
EOT;
		
		return $sql;
	}
}
