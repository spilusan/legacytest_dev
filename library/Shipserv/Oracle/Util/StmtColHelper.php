<?php

/**
 * Helper class used to gather SQL statment parameters neatly.
 * Collects column names, parametized column value expressions and parameter values.
 */
class Shipserv_Oracle_Util_StmtColHelper
{
	private $colExprs;
	private $paramVals;
	private $prefix;
	
	public function __construct ($prefix)
	{
		$this->prefix = (string) $prefix;
	}
	
	/**
	 * Set column name and value.
	 */
	public function setCol ($col, $val)
	{
		$this->colExprs[$this->prefix . $col] = ':' . $this->prefix . $col;
		$this->paramVals[$this->prefix . $col] = $val;
	}
	
	public function setDateTimeCol ($col, $timestamp)
	{
		$this->colExprs[$this->prefix . $col] = $this->timestampToOracleDate(':' . $this->prefix . $col);
		$this->paramVals[$this->prefix . $col] = $timestamp;
	}
	
	private function timestampToOracleDate ($timestamp)
	{
		return "TO_DATE('01-JAN-1970 00:00:00', 'DD-MON-YYYY HH24:MI:SS') + NUMTODSINTERVAL($timestamp, 'SECOND')";
	}
	
	/**
	 * Fetch array of column names collected (with option column prefix).
	 *
	 * @return array e.g. array('COL_1', 'COL_2', 'COL_3')
	 */
	public function getColNames ()
	{
		return array_keys($this->colExprs);
	}
	
	/**
	 * Fetch array of (parameterized) column value expressions.
	 *
	 * Example return value containing literal column value, parameterized column value and expression containing parameterized column value:
	 * array( 'COL_1_LITERAL', ':COL_2_PARAM_NAME', 'TRIM(:COL_3_PARAM_NAME)' )
	 *
	 * Caution: if using literal values, these are NOT escaped.
	 * 
	 * @return array
	 */
	public function getColExprs ()
	{
		return array_values($this->colExprs);
	}
	
	/**
	 * Fetch associative array of parameter names and values.
	 *
	 * Example return value:
	 * array ( 'COL_2_PARAM_NAME' => 'val_2', 'COL_3_PARAM_NAME' => 'val_3' )
	 * 
	 * @return array
	 */
	public function getParamVals ()
	{
		return $this->paramVals;
	}
}
