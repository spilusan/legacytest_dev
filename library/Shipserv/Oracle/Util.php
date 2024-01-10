<?php

class Shipserv_Oracle_Util
{
	/**
	 * Not instantiable.
	 */
	private function __construct () { }
	
	/**
	 * Form quoted SQL list from array, e.g. "'val_1', 2, 'val_3', ... ".
	 * Returns 'NULL' for empty array.
	 * 
	 * @return string
	 */
	public static function makeSqlList ($db, array $vals)
	{
		// No values supplied: return SQL NULL
		if (!$vals)
		{
			return 'NULL';
		}
		
		// Build quoted list
		$sqlList = '';
		$i = 0;
		foreach ($vals as $v)
		{
			if ($i++ != 0)
			{
				$sqlList .= ', ';
			}
			$sqlList .= $db->quote($v);
		}
		return $sqlList;
	}
}
