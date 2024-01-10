<?php

class Shipserv_Oracle_CategoryEditors extends Shipserv_Oracle{

	/**
	 * Retrieve records that match supplied filter
	 *
	 * @param array $filters
	 * @param boolean $useCache
	 * @param integer $cacheTTL
	 * @return array
	 */
	public function fetch($filters = array()) {

		$sql = "";
		$sql .= 'SELECT PCE.*';
		$sql .= ' FROM PAGES_CATEGORY_EDITOR PCE ';

		$sqlData = array();

		if (count($filters)>0)
		{
			$sql.= ' WHERE ';
			$isFirst = true;
			foreach ($filters as $column=>$value)
			{
				if (!$isFirst){
					$sql.= ' AND ';
				}
				else
				{
					$isFirst = false;
				}
				if (!is_null($value))
				{
					$sql .= $column.' = :'.$column."_FILTER";
					$sqlData[$column."_FILTER"] = $value;
				}
				else
				{
					$sql .= ' ('. $column.' IS NULL) ';
				}
			}
		}

		$result = $this->db->fetchAll($sql,$sqlData);
		

		return $result;
	}
}
?>
