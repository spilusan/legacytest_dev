<?php
/**
 * A collection of method useful for classes running complex Solr queries
 *
 * @author  Yuriy Akopov
 * @date    2015-02-26
 * @story   S12607
 */
trait Shipserv_Helper_Solr
{
    /**
     * @var Shipserv_Adapters_Solr_Index
     */
    protected $client = null;

    /**
     * Runs a query to get sum of all values of the given field
     *
     * @param   Solarium_Query_Select|Solarium_Query_Select[]   $queryDrafts
     * @param   string  $field
     * @param   Solarium_Query_Select_FilterQuery|Solarium_Query_Select_FilterQuery[]|null $filters
     * @param   int     $numFound
     *
     * @return float|int
     *
     * @throws  Solarium_Exception
     */
    protected function _getFieldValueSum($queryDrafts, $field, $filters = null, &$numFound = null)
    {
        return $this->_getFieldStats($queryDrafts, $field, $filters, $numFound)['sum'];
    }

    /**
     * Runs a query to get mean of all values of the given field
     *
     * @param   Solarium_Query_Select|Solarium_Query_Select[]   $queryDrafts
     * @param   string  $field
     * @param   Solarium_Query_Select_FilterQuery|Solarium_Query_Select_FilterQuery[]|null $filters
     * @param   int     $numFound
     *
     * @return float|int
     *
     * @throws  Solarium_Exception
     */
    protected function _getFieldValueMean($queryDrafts, $field, $filters = null, &$numFound = null)
    {
        return $this->_getFieldStats($queryDrafts, $field, $filters, $numFound)['mean'];
    }

    /**
     * Runs a query to get min of all values of the given field
     *
     * @param   Solarium_Query_Select|Solarium_Query_Select[]   $queryDrafts
     * @param   string  $field
     * @param   Solarium_Query_Select_FilterQuery|Solarium_Query_Select_FilterQuery[]|null $filters
     * @param   int     $numFound
     *
     * @return  float|int
     *
     * @throws  Solarium_Exception
     */
    protected function _getFieldValueMin($queryDrafts, $field, $filters = null, &$numFound = null)
    {
        return $this->_getFieldStats($queryDrafts, $field, $filters, $numFound)['min'];
    }

    /**
     * Returns stats for the given field
     *
     * IMPORTANT: if several queries are supplied, it is important for them to produce non-overlapping sets of
     * line items to keep the stats correct
     *
     * @param   Solarium_Query_Select|Solarium_Query_Select[]   $queryDrafts
     * @param   string  $field
     * @param   Solarium_Query_Select_FilterQuery|Solarium_Query_Select_FilterQuery[]|null $filters
     * @param   int     $numFound
     *
     * @return  array
     *
     * @throws  Solarium_Exception
     */
    protected function _getFieldStats($queryDrafts, $field, $filters = null, &$numFound = null)
    {
        if (!is_array($queryDrafts)) {
            $queryDrafts = array($queryDrafts);
        }

        if (!is_null($filters)) {
            if (!is_array($filters)) {
                $filters = array($filters);
            }
        }

        $stats = array(
            'min'   => null,
            'max'   => null,
            'sum'   => null,
            'mean'  => null,
            'count' => null
        );

        $numFound = 0;

        foreach ($queryDrafts as $queryDraft) { /** @var Solarium_Query_Select $query */
            $query = clone($queryDraft);

            if (!is_null($filters) and !empty($filters)) {
                foreach ($filters as $filter) {
                    $query->addFilterQuery($filter);
                }
            }

            $query->getStats()->createField($field);

            $result = $this->client->select($query, true, true);
            $statsResult = $result->getStats()->getResult($field);

            if (is_null($stats['min']) or ($stats['min'] > $statsResult->getMin())) {
                $stats['min'] = $statsResult->getMin();
            }

            if (is_null($stats['max']) or ($stats['max'] < $statsResult->getMax())) {
                $stats['max'] = $statsResult->getMax();
            }

            $stats['sum']   += (float) $statsResult->getSum();
            $stats['count'] += (int) $statsResult->getCount();
            $stats['mean']  += (float) $statsResult->getMean(); // to be divided afterwards

            $numFound += $result->getNumFound();
        }

        $stats['mean'] /= count($queryDrafts);

        return $stats;
    }

    /**
     * A wrapper for processing all the values matching the query
     *
     * @date    2018-02-22
     * @story   DEV-2563
     *
     * @param   Solarium_Query_Select   $query
     * @param   callable                $pageFunction
     *
     * @return int
     */
    protected function processAllValues(Solarium_Query_Select $query, callable $pageFunction)
    {
        return $this->client->processAllValues($query, $pageFunction, 1000);
    }

    /**
     * Returns distinct values of the given field
     *
     * @param   Solarium_Query_Select|Solarium_Query_Select[] $queryDrafts
     * @param   string  $field
     * @param   Solarium_Query_Select_FilterQuery|Solarium_Query_Select_FilterQuery[]|null $filters
     *
     * @return  array
     * @throws  Solarium_Exception
     */
    protected function _getFieldDistinctValues($queryDrafts, $field, $filters = null)
    {
        if (!is_array($queryDrafts)) {
            $queryDrafts = array($queryDrafts);
        }

        $values = array();
        $pageSize = 1000;

        if (!is_null($filters)) {
            if (!is_array($filters)) {
                $filters = array($filters);
            }
        }

        foreach ($queryDrafts as $queryDraft) {
            $query = clone ($queryDraft); /** @var Solarium_Query_Select $query */

            if (!is_null($filters) and !empty($filters)) {
                foreach ($filters as $filter) {
                    $query->addFilterQuery($filter);
                }
            }

            $query->getGrouping()->addField($field);    // group by the requesting field value
            $query->getGrouping()->setLimit(0);         // no need to return documents under groups

            $pageNo = 1;
            while (true) {
                $query->setStart(($pageNo - 1) * $pageSize);
                $query->setRows($pageSize);

                $pageResults = $this->client->select($query);
                $valueGroups = $pageResults->getGrouping()->getGroup($field)->getValueGroups();
                /** @var $valueGroups Solarium_Result_Select_Grouping_ValueGroup[] */

                if (count($valueGroups) === 0) {
                    break;
                }

                // we need the exact values, not just their number because they may overlap between queries from $queryDrafts
                foreach ($valueGroups as $group) {
                    $values[] = $group->getValue();
                }

                $pageNo++;
            }
        }

        $values = array_unique($values);

        return $values;
    }
    
    /**
     * Returns distinct values of the given field, if it is a multiple array value
     *
     * @param   Solarium_Query_Select|Solarium_Query_Select[] $queryDrafts
     * @param   string  $field
     * @param   Solarium_Query_Select_FilterQuery|Solarium_Query_Select_FilterQuery[]|null $filters
     *
     * @return  int
     * @throws  Solarium_Exception
     */
    protected function _getFieldDistinctMultipleValuesCount($queryDrafts, $field, $filters = null)
    {
    	if (!is_array($queryDrafts)) {
    		$queryDrafts = array($queryDrafts);
    	}
    	
    	$values = array();
    	$pageSize = 1000;
    	
    	if (!is_null($filters)) {
    		if (!is_array($filters)) {
    			$filters = array($filters);
    		}
    	}
    	
    	foreach ($queryDrafts as $queryDraft) {
    		$query = clone ($queryDraft); /** @var Solarium_Query_Select $query */
    		
    		if (!is_null($filters) and !empty($filters)) {
    			foreach ($filters as $filter) {
    				$query->addFilterQuery($filter);
    			}
    		}

    		$pageNo = 1;
    		while (true) {
    			$query->setStart(($pageNo - 1) * $pageSize);
    			$query->setRows($pageSize);
    			
    			$pageResults = $this->client->select($query);
    			$response = json_decode($pageResults->getResponse()->getBody())->response->docs;
    			
    			if (count($response) === 0) {
    				break;
    			}
    			
    			// we need the exact values, not just their number because they may overlap between queries from $queryDrafts
    			foreach ($response as $resp) {
    				foreach ($resp->supplierBranchId as $spbId) {
    					$values[] = $spbId;
       				}
    				
    			}
    			
    			$pageNo++;
    		}
    	}
    	
    	$values = array_unique($values);
    	
    	return count($values);
    }

    /**
     * Returns number of distinct values of the given field
     *
     * @param   Solarium_Query_Select[] $queryDrafts
     * @param   string  $field
     * @param   Solarium_Query_Select_FilterQuery|Solarium_Query_Select_FilterQuery[]|null $filters
     *
     * @return  int
     * @throws  Solarium_Exception
     */
    protected function _getFieldDistinctValueCount($queryDrafts, $field, $filters = null)
    {
        $distinctCount = count($this->_getFieldDistinctValues($queryDrafts, $field, $filters));
        return $distinctCount;
    }

    /**
     * Helper function to extract data returned by Solr StatsComponent into a front end JSON structure
     *
     * @param   Solarium_Result_Select_Stats_Result|Solarium_Result_Select_Stats_FacetValue $stats
     *
     * @return  array
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public static function statsToJson($stats)
    {
        if (
            !($stats instanceof Solarium_Result_Select_Stats_Result)
            and !($stats instanceof Solarium_Result_Select_Stats_FacetValue)
        ) {
            throw new Shipserv_Adapters_Solr_Exception("Invalid stats object supplied to be converted into JSON");
        }

        return array(
            'num'   => round($stats->getCount(), 2),
            'mean'  => round($stats->getMean(), 2),
            'min'   => round($stats->getMin(), 2),
            'max'   => round($stats->getMax(), 2),
            'sum'   => round($stats->getSum(), 2)
        );
    }
}