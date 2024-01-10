<?php

/**
 * Groups DB functionality used by this script: writing to BUYER_ORGANISATION,
 * PAGES_COMPANY, PAGES_ENDORSEMENT_PRIVACY, PAGES_BYO_NORM.
 */
class Cl_ModelFacade
{
	private $db;
	private $privacyDao;
	
	public function __construct ($db)
	{
		$this->db = $db;
		$this->privacyDao = new Shipserv_Oracle_EndorsementPrivacy($this->db);
	}
	
	/**
	 * Updates BUYER_ORGANISATION table for PK = $byoOrgCode. Columns and
	 * values are specified in $colMap as an associative array.
	 *
	 * An exception is thrown if a client attempts to update an unpermitted
	 * column, or attempts to provide an illegal value for a column.
	 *
	 * If no columns are specified for update, udpated-by and updated-when columns
	 * are nonetheless updated.
	 *
	 * If no row exists for $byoOrgCode then 0 is returned.
	 * 
	 * @param mixed $byoOrgCode
	 * @param array $colMap
	 * @return int Number of rows affected.
	 */
	public function setBuyerOrgCols ($byoOrgCode, array $colMap)
	{
		// Columns that may be updated by this method
		static $updateableCols = array (
			'BYO_NAME' => 1,
			'BYO_CONTACT_NAME' => 1,
			'BYO_CONTACT_ADDRESS1' => 1,
			'BYO_CONTACT_ADDRESS2' => 1,
			'BYO_CONTACT_CITY' => 1,
			'BYO_CONTACT_STATE' => 1,
			'BYO_CONTACT_PIN' => 1,
			'BYO_COUNTRY' => 1,
			'BYO_CONTACT_PHONE1' => 1,
			'BYO_CONTACT_EMAIL' => 1,
			'BYO_CONTACT_PHONE2' => 1,
		);
		
		// Build SQL statement parameter list: 'colName1 = :colName1, ...'
		$colSql = '';
		foreach ($colMap as $colName => $colVal)
		{
			// Check that column may be updated
			if (!@$updateableCols[$colName])
			{
				throw new Exception("Update not permitted on column: '$colName'");
			}
			
			// Augment SQL statement
			$colSql = $this->augmentSetSql($colSql, "$colName = :$colName");
		}
		
		// Add update-by column
		$colSql = $this->augmentSetSql($colSql, "BYO_UPDATED_BY = 'PGS_BATCH'");
		
		// Add updated-when column
		$colSql = $this->augmentSetSql($colSql, 'BYO_UPDATED_DATE = SYSDATE');
		
		// Add to statement parameters
		$colMap['BYO_ORG_CODE'] = $byoOrgCode;
		
		// Make SQL statement and execute with parameters
		$sql = "UPDATE BUYER_ORGANISATION SET $colSql WHERE BYO_ORG_CODE = :BYO_ORG_CODE";
		$stmt = $this->doParamQuery($sql, $colMap);
		
		// Return number of rows affected
		return $stmt->rowCount();
	}
	
	/**
	 * Updates, or inserts into PAGES_COMPANY for PK = $byoOrgCode. Columns and
	 * values are specified in $colMap as an associative array.
	 *
	 * If a row already exists for $byoOrgCode an update is performed, otherwise
	 * an insert is performed.
	 *
	 * An exception is thrown if a client attempts to update an unpermitted
	 * column, or attempts to provide an illegal value for a column.
	 * 
	 * If an insert is attempted and required columns are missing, the method
	 * will fail.
	 *
	 * If no columns are specified, method returns 0 rows affected.
	 *
	 * @param mixed $byoOrgCode
	 * @param array $colMap
	 * @return int Number of rows affected.
	 */
	public function setPagesCompanyCols ($byoOrgCode, array $colMap)
	{
		// Columns that may be updated by this method
		static $updateableCols = array (
			'PCO_ANONYMISED_NAME' => 1,
			'PCO_ANONYMISED_LOCATION' => 1,
			'PCO_REVIEWS_OPTOUT' => 1,
			'PCO_IS_JOIN_REQUESTABLE' => 1,
		);
		
		// If no columns are to be updated, return 0 rows affected
		if (!$colMap)
		{
			return 0;
		}
		
		// Loop over supplied columns & values building SQL substrings
		$updateSql = '';
		$insertColSql = '';
		$insertColValSql = '';
		foreach ($colMap as $colName => $colVal)
		{
			// Check that column may be updated
			if (!@$updateableCols[$colName])
			{
				throw new Exception("Update not permitted on column: '$colName'");
			}
			
			// Some columns require validation
			if ($colName == 'PCO_REVIEWS_OPTOUT' || $colName == 'PCO_IS_JOIN_REQUESTABLE')
			{
				// Test acceptable values
				if ($colVal === null || $colVal == '' || $colVal == 'N' || $colVal == 'Y')
				{
					// Value OK: do nothing
				}
				else
				{
					throw new Exception("Illegal value for column: '$colName' = $colVal");
				}
			}
			
			// Generate quoted value to insert, or NULL
			if ($colVal === null)
			{
				$colValSql = 'NULL';
			}
			else
			{
				$colValSql = $this->dbQuote($colVal);
			}
			
			// Augment update SQL statement
			$updateSql = $this->augmentSetSql($updateSql, "$colName = $colValSql");
			
			// Augment insert SQL column list
			$insertColSql = $this->augmentSetSql($insertColSql, $colName);
			
			// Augment insert SQL column value list
			$insertColValSql = $this->augmentSetSql($insertColValSql, $colValSql);
		}
		
		// Generate upsert SQL & execute
		$byoOrgCodeQuoted = $this->dbQuote($byoOrgCode);
		$sql = "MERGE INTO PAGES_COMPANY USING DUAL ON (PCO_TYPE = 'BYO' AND PCO_ID = $byoOrgCodeQuoted)
			WHEN MATCHED THEN
				UPDATE SET $updateSql
			WHEN NOT MATCHED THEN
				INSERT (PCO_TYPE, PCO_ID, $insertColSql)
					VALUES ('BYO', $byoOrgCodeQuoted, $insertColValSql)";
		
		$stmt = $this->doQuery($sql);
		
		// Return number of rows affected
		return $stmt->rowCount();
	}
	
	/**
	 * Sets global privacy policy for buyer. If exceptions are present, the
	 * exception cases are preserved but brought into line with the new
	 * global policy.
	 * 
	 * @param string $anonPolicy Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_TN
	 */
	public function setAnonPolicy ($byoOrgCode, $anonPolicy)
	{
		$currentSetting = $this->privacyDao->getBuyerPrivacy($byoOrgCode);
		$this->privacyDao->setBuyerPrivacy(new Cl_SaveablePrivacySetting($byoOrgCode, $currentSetting, $anonPolicy));
	}
	
	/**
	 * Updates the canonicalization status for buyer.
	 *
	 * If $boolActive is true: activates inactive buyer, by removing any
	 * null-mapping from PAGES_BYO_NORM; if buyer is active (canonical or
	 * non-canonical), no action is taken.
	 *
	 * If $boolActive is false: buyer is disactivated, by inserting a null-mapping
	 * into PAGES_BYO_NORM.
	 * 
	 * @param bool $boolActive
	 */
	public function setCanonActive ($byoOrgCode, $boolActive)
	{
		// Sanitize param
		$boolActive = (bool) $boolActive;
		
		// Quote DB values
		$quotedVals = array();
		$quotedVals['byoOrgCode'] = $this->dbQuote($byoOrgCode);
		
		// If instructed to set active ...
		if ($boolActive)
		{
			// Generate SQL to:
			// Remove this buyer's null mapping, if present
			$sql = "DELETE FROM PAGES_BYO_NORM
				WHERE PBN_BYO_ORG_CODE = {$quotedVals['byoOrgCode']}
				AND PBN_NORM_BYO_ORG_CODE IS NULL";
		}
		
		// If instructed to set inactive
		else
		{
			// Generate SQL to:
			// Udpate this buyer's mapping to null, if mapping present
			// Insert null mapping for buyer, if no mapping present
			$sql = "MERGE INTO PAGES_BYO_NORM USING DUAL ON (PBN_BYO_ORG_CODE = {$quotedVals['byoOrgCode']})
				WHEN MATCHED THEN
					UPDATE SET PBN_NORM_BYO_ORG_CODE = NULL
				WHEN NOT MATCHED THEN
					INSERT (PBN_BYO_ORG_CODE, PBN_NORM_BYO_ORG_CODE)
						VALUES ({$quotedVals['byoOrgCode']}, NULL)";
		}
		
		// Execute SQL
		$stmt = $this->doQuery($sql);
		
		// Return number of rows affected
		return $stmt->rowCount();
	}
	
	private function augmentSetSql ($setSql, $toAdd)
	{
		if ($setSql != '')
		{
			$setSql .= ', ';
		}
		$setSql .= $toAdd;
		return $setSql;
	}
	
	private function dbQuote ($val)
	{
		return $this->db->quote($val);
	}
	
	private function doParamQuery ($sql, $params)
	{
		return $this->db->query($sql, $params);
	}
	
	private function doQuery ($sql)
	{
		return $this->db->query($sql);
	}
}

class Cl_TestDummy
{
	public function __call ($name, array $arguments)
	{
		return 0;
	}
}

/**
 * A privacy setting that may be saved to DB by Shipserv_Oracle_EndorsementPrivacy.
 * Specifically, this class takes an existing privacy setting and modifies it to a
 * newly specified value, preserving all of the exception cases (but bringing them
 * in-line with the new global setting).
 */
class Cl_SaveablePrivacySetting implements Shipserv_Oracle_EndorsementPrivacy_Saveable
{
	private $buyerOrgCode;
	private $globalAnon;
	private $exceptionList = array();
	
	/**
	 * @param Shipserv_Oracle_EndorsementPrivacy_Setting $oldSetting Current privacy setting
	 * @param string $newGlobalSetting Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_TN
	 */
	public function __construct ($buyerOrgCode, Shipserv_Oracle_EndorsementPrivacy_Setting $oldSetting, $newGlobalSetting)
	{
		// Set organisation code
		$this->buyerOrgCode = $buyerOrgCode;
		
		// Validate new global anon setting
		$newGlobalSetting = (string) $newGlobalSetting;
		if (!Shipserv_Oracle_EndorsementPrivacy::isValidAnonPolicy($newGlobalSetting))
		{
			throw new Exception("Invalid anonymisation policy: '$newGlobalSetting'");
		}
		
		// Record new global anon setting
		$this->globalAnon = $newGlobalSetting;
		
		// Preserve IDs of exception cases, but set their policy to the new global setting
		foreach ($oldSetting->getExceptionRules() as $exId => $exVal)
		{
			$this->exceptionList[$exId] = $newGlobalSetting;
		}
	}
	
	public function getOwnerId ()
	{
		return $this->buyerOrgCode;
	}
	
	public function getGlobalAnon ()
	{
		return $this->globalAnon;
	}
	
	public function getExceptionList ()
	{
		return $this->exceptionList;
	}
}

/**
 * Reads buyer data from CSV file applies it to DB.
 */
class Cl_FileLoader
{
	private $modelFacade;
	
	public function __construct (Cl_ModelFacade $modelFacade)
	{
		$this->modelFacade = $modelFacade;
	}
	
	public function loadFile ($filePath)
	{
		// YUCK: fgetcsv requires this global setting to recognise char 13 as EOL
		ini_set('auto_detect_line_endings', true);
		
		// Open input file
		$fh = fopen($filePath, 'r');
		if ($fh === false)
		{
			throw new Exception("Unable to open input file");
		}
		
		// Loop over rows
		$rowIdx = 0;
		while (true)
		{
			// Read next row
			$r = fgetcsv($fh);
			
			// Exit loop on EOF, or other error
			if ($r === false)
			{
				break;
			}
			
			// Defensively handle unexpected behaviour from fgetcsv()
			if (!is_array($r))
			{
				throw new Exception("Unexpected return type from fgetcsv()");
			}
			
			// Skip first row: header row
			$isFirstRow = ($rowIdx == 0);
			$rowIdx++;
			if ($isFirstRow)
			{
				$this->log("Skipping row 1: header row");
				continue;
			}
			
			// Check for empty row: back to top of loop.
			// NB PHP doc states that for empty lines fgetcsv() returns array(null).
			// I found this not to be so.
			if (count($r) == 1 && $r[0] == '')
			{
				continue;
			}
			
			// Clean row elements
			$cleanRow = array();
			foreach ($r as $ri => $rv)
			{
				// NB fgetcsv() seems to already to a left-trim
				$cleanRow[$ri] = trim($rv);
			}
			
			try
			{
				$this->processRow($cleanRow);
			}
			catch (Cl_RowParseException $e)
			{
				// No error handling for the moment
				throw $e;
			}
		}
		
		// Close input file
		fclose($fh);
	}
	
	/**
	 * Applies a CSV row to the DB. CSV fields are as follows (from column 0
	 * going right):
	 * 
	 * 0: (int) Buyer organisation code
	 * 1: (string) 'Y' = Set buyer active; other values set buyer inactive.
	 * 2: (string) Company name.
	 * 3: (string) Company location.
	 * 4: (string) 'Y' | 'N' = Company may / may not be join requested.
	 * 5: (string) 'Y' | 'N' = Company opts out / does not opt out of review functionality.
	 * 6: (string) '1' | '0' | 'T' = Company to be anonymised / not to be anonymised / to be anonymised except for TN suppliers.
	 * 7: (string) Anonymised company name (if anonymised).
	 * 8: (string) Anonymised company location (if anonymised).
	 */
	private function processRow (array $row)
	{
		// Re-index numerically-indexed columns with unique names
		$iRow = $this->assocRow($row);
		
		// If row is not marked for modification, skip it
		if ($iRow['BI_MODIFY'] != 'M')
		{
			return;
		}
		
		// Update BUYER_ORGANISATION
		$byoNumChanged = $this->modelFacade->setBuyerOrgCols($iRow['BI_ORG_CODE'], array(
			'BYO_NAME' => $iRow['BI_NAME'],
			'BYO_CONTACT_CITY' => $iRow['BI_CITY'] == '' ? 'City' : $iRow['BI_CITY'],
			'BYO_COUNTRY' => $iRow['BI_COUNTRY'] == '' ? 'Country' : $iRow['BI_COUNTRY'],
		));
		
		// Check if no BYO was changed
		if ($byoNumChanged == 0)
		{
			throw new Cl_RowParseException("No BYO found for id: '{$iRow['BI_ORG_CODE']}'");
		}
		
		// Update active/inactive canonicalization state
		$this->modelFacade->setCanonActive($iRow['BI_ORG_CODE'], ($iRow['BI_NORM_ACTIVE'] == 'Y'));
		
		// Update / insert PAGES_COMPANY
		$this->modelFacade->setPagesCompanyCols($iRow['BI_ORG_CODE'], array(
			'PCO_IS_JOIN_REQUESTABLE' => $iRow['BI_IS_JOIN_REQABLE'],
			'PCO_REVIEWS_OPTOUT' => $iRow['BI_REVIEWS_OPTOUT'],
			'PCO_ANONYMISED_NAME' => $iRow['BI_ANON_NAME'],
			'PCO_ANONYMISED_LOCATION' => $iRow['BI_ANON_LOCATION'],
		));
		
		// Update anon policy
		$this->modelFacade->setAnonPolicy($iRow['BI_ORG_CODE'], $iRow['BI_ANON_POLICY']);
		
		$this->log("Updated BYO {$iRow['BI_ORG_CODE']}");
	}
	
	private function log ($msg)
	{
		echo date('Y-m-d H:i:s') . " $msg\n";
	}
	
	/**
	 * Turns a numerically indexed CSV row into an associative array (so that
	 * dependent code is easier to maintain).
	 */
	private function assocRow (array $row)
	{
		// Map numeric indexes to unique textual keys
		static $idxMap = array(
			'BI_MODIFY',				// Column 0
			'BI_ORG_CODE',				// Column 1
			'BI_NORM_ACTIVE',			// ...
			'BI_NAME',
			'BI_CITY',
			'BI_COUNTRY',
			'BI_IS_JOIN_REQABLE',
			'BI_REVIEWS_OPTOUT',
			'BI_ANON_POLICY',
			'BI_ANON_NAME',
			'BI_ANON_LOCATION',
		);
		
		$idxRow = array();
		foreach ($idxMap as $idxR => $idxV)
		{
			if (!array_key_exists($idxR, $row))
			{
				throw new Cl_RowParseException("Failed to find column offset $idxR (starts from 0)");
			}
			
			$idxRow[$idxV] = $row[$idxR];
		}
		
		return $idxRow;
	}
}

class Cl_RowParseException extends Exception { }
