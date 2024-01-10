<?php

class ajwp_QueryIterator
{
	private $batchSize = 100;
	private $sql;
	private $nextBatch = 0;
	private $currentRes;
	private $rnumAlias = 'RNUM_ALIAS';
	
	public function __construct ($sql)
	{
		$this->sql = $sql;
	}
	
	/**
	 * @return array or null if no more rows
	 */
	public function next ()
	{
		if ($this->nextBatch >= 0) for ($i = 0; $i < 2; $i++)
		{
			if ($this->currentRes === null)
			{
				$this->currentRes = $this->getDb()->fetchAll($this->getNextQuery());
				reset($this->currentRes);
				$this->nextBatch++;
			}
			
			if (list($k, $row) = each($this->currentRes))
			{
				unset($row[$this->rnumAlias]);
				return $row;
			}
			
			$this->currentRes = null;
		}
		
		$this->nextBatch = -1;
	}
	
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	private function getNextQuery ()
	{
		$sql = $this->getPagedQuery($this->nextBatch * $this->batchSize, $this->batchSize);
		return $sql;
	}
	
	private function getPagedQuery ($offset, $n)
	{
		$maxRow = $offset + $n;
		$minRow = $offset + 1;
		
		return 
			"select * from
			(
				select /*+ FIRST_ROWS(n) */ a.*, ROWNUM {$this->rnumAlias} from
				(
					{$this->sql}
				) a 
				where ROWNUM <= $maxRow
			) 
			where {$this->rnumAlias}  >= $minRow";
	}
}
