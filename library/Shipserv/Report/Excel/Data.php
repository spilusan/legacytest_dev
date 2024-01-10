<?php
class Shipserv_Report_Excel_Data
{
	protected $data = array();
	protected $report;
	
	function __construct( $data, $report )
	{
		$this->report = $report;
		$this->data = $data;
		$this->data = $this->normalise();
		
	}
	
	private function normalise()
	{
		$periods = array();
		$multiplePeriods = ( count( $this->report->data["supplier"] ) == 2 );

		if( $multiplePeriods )
		{
			$periods = array_keys($this->report->data["supplier"]);
		}
		else 
		{
			$periods = array( $this->report->data["supplier"]["period"]["start"] . " " . $this->report->data["supplier"]["period"]["end"] ); 
			
		}
		if( count( $this->data ) > 0 )
		{
			if( count( $this->data ) < 30 )
			{
				return $this->data;
			}
			
			// process first period
			foreach( $this->data as $row )
			{
				$tmp = explode("-", $row["Name"]);
				
				$x["year"] = $tmp[0];
				$x["month"] = $tmp[1];
				$x["day"] = $tmp[2];
				
				if( $multiplePeriods )
				{
					if( $row[ $periods[0] ] != '' )
					{
						$x["count"] = $row[ $periods[0] ];
						$period1RawData[] = $x;
					}
					else if( $row[ $periods[1] ] != '' )
					{
						$x["count"] = $row[ $periods[1] ];
						$period2RawData[] = $x;
					}
				}
				else
				{
					$x["count"] = $row[ $periods[0] ];
					$period1RawData[] = $x;
				}
			}
		}
		$period1 = $this->groupByMonth( $period1RawData, $periods[0] );
		$period2 = $this->groupByMonth( $period2RawData, $periods[1] );

		$result = array_merge( $period1, $period2 );

		return $result;
	}
	
	public function groupByMonth( $data, $period )
	{
		$sums = array();
		$x = array();
		if( count( $data ) > 0 )
		{
			foreach ($data as $entry) {
			   	if ( !isset( $sums[$entry['year']] ) ) 
			   	{
			        $sums[$entry['year']] = array();
			   	}
			   
			 	if (!isset($sums[$entry['year']][$entry['month']]) ) 
			 	{
			        $sums[$entry['year']][$entry['month']] = 0;
			   	}
			   	$sums[$entry['year']][$entry['month']] += $entry['count'];
			}
		}
		
		foreach( $sums as $year => $data )
		{
			foreach( $data as $month => $count )
			{
				$time = strtotime( "01-" . $month . "-" . $year );
				$x[date('M y', $time)] = array("Name" => date('M y', $time), $period => $count );
			}
		}
		
		
		
		return $x;
	}	
	
	public function getData()
	{
		return $this->data;
	}
}