<?php

use Google\AdsApi\AdManager\v201902\Dimension;
use Google\AdsApi\AdManager\v201902\DimensionAttribute;
use Google\AdsApi\AdManager\v201902\Column;

/**
 * Adapter class for Google DFP
 * 
 * @package myshipserv
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 *
 * Updated by Yuriy Akopov on 2015-03-17, S12993 (Google API version upgrade)
 * Updated by Claudio Ortelli on 2016-03-17, S12993 (Google API version upgrade + use lib via composer)
 * Updated by Attila Olbrich on 2018-06-17 refactored for New API version which is completely different from the previous ones
 */
class Shipserv_Adapters_Report_GoogleDFP extends Shipserv_Memcache
{
	const TMP_DIR = '/tmp/';
	const MEMCACHE_TTL = 36000;

	/**
	 * The XML-RPC Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	protected $session;
	protected $debug = false;
	
	protected $logger;	
	protected $db;


    /**
     * Perform authenticatin check
     */
    public function performAuthenticationCheck()
    {

        $getToken = new Myshipserv_Dfp_RefreshToken();
        $getToken->getToken();

        try {
            new Myshipserv_Dfp_GetCurrentUser();
        } catch (Exception $e) {
            print 'It appears that system cannot login into Google successfully. Please start again! (Reason: ' . $e->getMessage() . '), replacing token with the previous token' . PHP_EOL;
            $getToken->replaceOldToken();
        }
    }

    /**
     * Creating DFP session and checking if temporary folders are writable
     *
     * Shipserv_Adapters_Report_GoogleDFP constructor.
     * @throws Myshipserv_Exception_MessagedException
     */
	public function __construct()
    {
        try {
            $dfp = new Myshipserv_Dfp_GetCurrentUser();
            $this->client = $dfp->getDfpUser();
            $this->session = $dfp->getSession();
        } catch(Exception $e) {
            print 'FATAL ERROR - DfpUser constructor failed (contact Attila or Yuriy)';
            print $e->getMessage();
            exit;
        }

        // check temporary table structures
        if (!is_dir(self::TMP_DIR)) {
            throw new Myshipserv_Exception_MessagedException('System error. Temporary folder ' . self::TMP_DIR . " doesn't exist", 500);
        } else {
            $tmpFolders = array(
                'google-dfp',
                'google-dfp/csv',
                'google-dfp/log'
            );

            foreach ($tmpFolders as $folder) {
                $tmpPath = self::TMP_DIR . $folder;

                if (!is_dir($tmpPath)) {
                    mkdir($tmpPath, 0777);
                }
            }
        }

        $this->db = $this->getDb();
        $this->logger = new Myshipserv_Logger(false);
    }


	/**
	 * This function will populate PAGES_ACTIVE_BANNER table on the database
	 * to pull all active banner yesterday
	 * @throws Exception
	 */
	public function getActiveBanner()
	{
		$n = new DateTime;		
		$successfulInsert = 0;
		$this->logger->log('Getting active banner data from GoogleDFP');
		try 
		{
			// initialise dates
			$eDate = new DateTime;
			$eTimestamp = $eDate->format('U');
			$sTimestamp = (int) $eTimestamp;
			$sDate = new DateTime();
			$sDate->setDate(date('Y', $sTimestamp)-1, date('m', $sTimestamp), date('d', $sTimestamp));
			$eDate = new DateTime();
			$eDate->setDate(date('Y', $sTimestamp), date('m', $sTimestamp), date('d', $sTimestamp));

            $dimensions = [
                Dimension::ORDER_ID,
                Dimension::CREATIVE_NAME,
                Dimension::AD_UNIT_NAME,
                Dimension::LINE_ITEM_NAME
            ];

            $dimAttr = [
                DimensionAttribute::LINE_ITEM_START_DATE_TIME,
                DimensionAttribute::LINE_ITEM_END_DATE_TIME
            ];

            $columns = [
                Column::AD_SERVER_IMPRESSIONS,
                Column::AD_SERVER_CLICKS
            ];

            $reportParams = new Myshipserv_Dfp_ReportParams();
            $reportParams->assignDimensions($dimensions);
            $reportParams->assignColumns($columns);
            $reportParams->assignDimensionAttributes($dimAttr);

            $reportParams->setStartDate($sDate);
            $reportParams->setEndDate($eDate);


            $currentUser = new Myshipserv_Dfp_GetCurrentUser();
            $report = new Myshipserv_Dfp_RunReport($currentUser->getSession());

            $this->logger->log('Running the query');
            $filePath = $this->getDownloadFileName($eDate);

            try {
                $localPathToReport = $report->runReport($reportParams, $filePath);
            } catch (Exception $e) {
                $this->logger->log('Problem on occured, please re-execute this again. If this still happening, contact Yuriy or Attila. Error:' . $e->getMessage());
                throw new Exception('Pages cannot download report from Google DFP because of a problem on their side', 504);
            }

            $sql = 'TRUNCATE TABLE pages_active_banner';
            $this->getDb()->query($sql);
            $this->getDb()->commit();

            if (($handle = fopen($localPathToReport, 'r')) !== false) {
                $row = 1;
                $this->logger->log('Inserting to Pages_active_banner table on database..');

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    // for the first row - check the location of all needed columns on the CSV
                    if ($row === 1) {
                        $itemCount = count($data);
                        for ($i=0; $i < $itemCount; $i++) {
                            if ($data[$i] == 'Column.AD_SERVER_CLICKS') {
                                $clicksColumnIndex = $i;
                            } else if ($data[$i] === 'Column.AD_SERVER_IMPRESSIONS') {
                                $impressionColumnIndex = $i;
                            } else if ($data[$i] === 'Dimension.LINE_ITEM_NAME') {
                                $lineItemColumnIndex = $i;
                            } else if ($data[$i] === 'Dimension.ORDER_ID') {
                                $orderIdColumnIndex = $i;
                            } else if ($data[$i] === 'Dimension.CREATIVE_ID') {
                                $creativeIdColumnIndex = $i;
                            } else if ($data[$i] === 'DimensionAttribute.LINE_ITEM_END_DATE_TIME') {
                                $lineItemEndDateColumnIndex = $i;
                            }
                        }
                    } else {
                        // for next columns, insert it to the table
                        if ($data[$lineItemEndDateColumnIndex] !== '') {
                            $x = explode('T', $data[$lineItemEndDateColumnIndex]);
                            $x = explode('-', $x[0]);
                            $d = new DateTime;
                            if ($x[0] !== '' && $x[1] !== '' && $x[3] !== '') {
                                $d->setDate((int)$x[0], (int)$x[1], (int)$x[2]);
                                $checkDate = true;
                            } else {
                                $checkDate = false;
                            }
                        } else {
                            $checkDate = false;
                        }

                        if ($checkDate === true && $d->format('U') > $n->format('U')) {
                            $sql = 'INSERT INTO pages_active_banner(PAB_ORDER_ID, PAB_ORDER, PAB_CLICK, PAB_IMPRESSION, PAB_CREATIVE_ID, PAB_DATE_ADDED)  VALUES(:orderId, :orderName, :click, :impression, :creativeId, SYSDATE)';
                            $click = $data[$clicksColumnIndex];
                            $click = str_replace(',', '', $click);
                            $click = (float)$click;
                            $impression = $data[$impressionColumnIndex];
                            $impression = str_replace(',', '', $impression);
                            $impression = (float)$impression;

                            $sqlData = array(
                                'orderName' => $data[$lineItemColumnIndex],
                                'orderId' => (int)$data[$orderIdColumnIndex],
                                'creativeId' => (int)$data[$creativeIdColumnIndex],
                                'click' =>  $click,
                                'impression' =>  $impression
                            );

                            $this->getDb()->query($sql, $sqlData);
                            if ($row % 100 === 0) {
                                $this->logger->log($row . ' rows stored');
                            }
                            $successfulInsert++;
                        }
                    }
                    $row++;
                }
            }

            $this->logger->log($successfulInsert . ' rows stored');
            $this->logger->log('Committing the insertion');
            $this->getDb()->commit();

            $this->logger->log('Cleansing data');

            fclose($handle);

            // REMOVE ALL THAT DOESN'T HAVE TNID
            $sql = 'DELETE FROM pages_active_banner WHERE pab_tnid IS null';
            $this->getDb()->query($sql);
            $this->getDb()->commit();

            $this->logger->log('Finished');

		} 
		catch (Exception $e) {
		  	print $e->getMessage() . PHP_EOL;
		}		
	}
	
	/**
	 * On each request, there will be a small call to oracle to check the missing date
     * @param bool $debug
	 * @return void
	 */
	public function updateTableWithLatestDataFromGoogle($debug = false)
	{
		if ($debug === true) {
		    $this->debug = true;
		}
		
		// get interval from the latest date on the database
		// get number of days that are missing
		$sql = 'SELECT FLOOR((sysdate - 1) - MAX(sbs_date)) DIFF FROM pages_svr_banner_statistic';
		$dayInterval = $this->getDb()->fetchOne($sql);
		$dayInterval = min($dayInterval, 365*2); //this is to avoid ReportError.START_DATE_MORE_THAN_THREE_YEARS_AGO

		// if one or more days missing
		if ($dayInterval > 0) {
			// get the last date on the database

			// get yesterday's date and use that as the reference going backw ards with the interval
			$date = new DateTime;
			$yesterday = strtotime('-1 day');
			$date->setDate(date('Y', $yesterday), date('m', $yesterday), date('d', $yesterday));

			if ($this->debug) {
                $this->logger->log('Pulling DFP data from google for ' . $dayInterval . ' days from ' . $date->format('d-m-Y h:i:s'));
            }


            if ($this->debug) {
                $this->logger->log('Downloading data from google DFP');
            }

            // check if this report has been added
            $sql = 'SELECT COUNT(*) FROM pages_svr_banner_statistic WHERE sbs_date=:sbsDate';
            $result = $this->getDb()->fetchOne($sql, array('sbsDate' => $date->format('d-M-y')));

            if ((int)$result[0] === 0) {
                // run report job on Google DFP
                $localPathToReport = $this->runReport($date, $dayInterval);

                if ($this->debug) {
                    $this->logger->log('Storing data to PAGES database');
                }

                // parse CSV and store it to oracle
                $this->saveReportToDb($localPathToReport);
            }

			
			if ($this->debug) {
                $this->logger->log('Finished...');
            }
		}
	}


    /**
     * Save the report
     *
     * @param DateTime|null $dateInput
     * @param int $dayInterval
     */
	public function saveReport(DateTime $dateInput = null, $dayInterval = 0)
	{
		if ($dateInput === null) {
			$date = new DateTime;
			$yesterday = strtotime('yesterday');
			$date->setDate(date('Y', $yesterday), date('m', $yesterday), date('d', $yesterday));
		} else {
			$date = $dateInput;
		}

		// check if this report has been added
		$sql = 'SELECT COUNT(*) FROM pages_svr_banner_statistic WHERE sbs_date=:sbsDate';
		$result = $this->getDb()->fetchOne($sql, array('sbsDate' => $date->format('d-M-y')));

		if ((int)$result[0] === 0) {
			// run report job on Google DFP
            $localPathToReport = $this->runReport($date, $dayInterval);

			// parse CSV and store it to oracle
			$this->saveReportToDb($localPathToReport);
		}
	}

    /**
     * Pull related line item on google DFP matching it up with TNID
     *
     * @param int $tnid
     * @return array
     */
	public function getLineItemByTnid($tnid)
	{
		$data = array();
        $key = $this->memcacheConfig->client->keyPrefix . 'SVR_GOOGLEDFP_ALL_LINEITEM_' . $tnid. $this->memcacheConfig->client->keySuffix;

        $memcache = $this::getMemcache();
		if ($memcache) {
			$result = $memcache->get($key);
			if ($result !== false) {
                $data = $result;
            }
		}
		
		// if miss cache
		if (count($data) === 0) {
			// get fresh data
			$sql = 'SELECT DISTINCT sbs_line_item_id FROM pages_svr_banner_statistic WHERE sbs_tnid=:tnid';
			$data = $this->getDb()->fetchAll($sql, array('tnid' => $tnid));

			// store lineitem ID
			if ($memcache) {
                $memcache->set($key, $data, false, 86400);
            }
		}

		// put it on the correct structure (there might be a better way doing this)
		$d = array();
		foreach ($data as $row) {
			$d[] = $row['SBS_LINE_ITEM_ID'];
		}

		return $d;
	}

    /**
     * Truncate table and insert a marker
     *
     * @param bool $debug
     */
	public function resetTable($debug = false)
	{
		if ($debug === true) {
            $this->debug = true;
        }
		
		$this->logger->log('Truncating pages_svr_banner_statistic');
		$sql = 'DELETE FROM pages_svr_banner_statistic';

		$this->getDb()->query($sql);
		$this->getDb()->commit();
		
		$this->logger->log('Inserting marker');
		$sql = "INSERT INTO pages_svr_banner_statistic VALUES('01-JAN-04', 231295, '--Pages SIR Marker--',0,0,SYSDATE,NULL)";

		$this->getDb()->query($sql);
		$this->getDb()->commit();
		$this->logger->log('Finish...');
		
	}

    /**
     * Save downloaded google DFP report to oracle
     *
     * @param string $path
     * @return bool
     */
	public function saveReportToDb($path)
	{
        $row = 1;
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if ($row === 1) {
                    $dataCount = count($data);
                    for ($i=0; $i < $dataCount; $i++) {
                        if ($data[$i] === 'Column.AD_SERVER_CLICKS') {
                            $clicksColumnIndex = $i;
                        } else if ($data[$i] === 'Column.AD_SERVER_IMPRESSIONS') {
                            $impressionColumnIndex = $i;
                        }
                    }
                }
                if ($row > 1 && $data[0] !== 'Totals') {

                    $sql = 'INSERT INTO pages_svr_banner_statistic(sbs_date, sbs_line_item_id, sbs_line_item, sbs_click, sbs_impression, sbs_date_added) VALUES(:sbsDate, :sbsLineItemId, :sbsLineItem, :sbsClick, :sbsImpression, SYSDATE)';

                    $click = $data[$clicksColumnIndex];
                    $click = str_replace(',', '', $click);
                    $click = (float)$click;
                    $impression = $data[$impressionColumnIndex];
                    $impression = str_replace(',', '', $impression);
                    $impression = (float)$impression;

                    $sqlData = array(
                        'sbsDate' => $this->formatDateToOracleFormat($data[0]),
                        'sbsLineItemId' => (int) $data[8],
                        'sbsLineItem' => $data[4],
                        'sbsClick' =>  $click,
                        'sbsImpression' =>  $impression
                    );

                    $this->getDb()->query($sql, $sqlData);
                }
                $row++;
                if ($this->debug && $row % 1000 === 0) {
                    $this->logger->log($row . ' rows stored');
                }
            }
            fclose($handle);
        }
        $this->getDb()->commit();

		return true;
	}

    /**
     * Tell google to run the report
     *
     * @param DateTime|null $date
     * @param int $dayInterval
     * @return null|string
     */
	public function runReport(DateTime $date = null, $dayInterval = 0)
	{
		$eDate = new DateTime;
        $filePath = null;
		
		// if date is not specified, assume it's yesterday's date
		if ($date === null) {
			$yesterday = strtotime('yesterday');
			$eDate->setDate(date('Y', $yesterday), date('m', $yesterday), date('d', $yesterday));
		} else {
			$eDate = $date;
		}

		try 
		{
            $dimensions = [
                Dimension::DATE,
                Dimension::ORDER_ID,
                Dimension::CREATIVE_NAME,
                Dimension::AD_UNIT_NAME,
                Dimension::LINE_ITEM_NAME
            ];

            $columns = [
                Column::AD_SERVER_IMPRESSIONS,
                Column::AD_SERVER_CLICKS
            ];

			// Get the ReportService.
            $reportParams = new Myshipserv_Dfp_ReportParams();
            $currentUser = new Myshipserv_Dfp_GetCurrentUser();
            $report = new Myshipserv_Dfp_RunReport($currentUser->getSession());
            $reportParams->assignDimensions($dimensions);
            $reportParams->assignColumns($columns);

			if ($dayInterval === 0) {
                // if dayInterval is 0 then pull only 1 day report
                $reportParams->setStartDate($eDate);
                $reportParams->setEndDate($eDate);

			} else {
                // if dayInterval specified, subtract the endDate with the interval to get the startDate
				$eTimestamp = $eDate->format('U');
				$sTimestamp = (int) $eTimestamp - (86400 * (int)$dayInterval);

				$sDate = new DateTime();
				$sDate->setDate(date('Y', $sTimestamp), date('m', $sTimestamp), date('d', $sTimestamp));

                $reportParams->setStartDate($sDate);
                $reportParams->setEndDate($eDate);
			}

			$filePath = $this->getDownloadFileName($date, $dayInterval);
			try {
                $downloadedFilePath = $report->runReport($reportParams, $filePath);
                return $downloadedFilePath;
            } catch (Exception $e) {
                throw new Exception('Report cannot download report from Google DFP because of a problem on their side', 504);
            }
		}
		catch (Exception $e) {
		  	print $e->getMessage() . "\n";
		  	exit;
		}

		return null;
	}

    /**
     * Get the download file name
     *
     * @param DateTime $date
     * @param string $dayInterval
     * @return string
     */
	public function getDownloadFileName(DateTime $date, $dayInterval = '')
    {
        // use different file if interval is specified
        if ($dayInterval === '') {
            $fileName = 'report-for-' . $date->format('Y-m-d') . '.csv.gz';
        } else {
            $fileName = 'report-for-' . $date->format('Y-m-d') . '-for-' . $dayInterval . '-days'  . '.csv.gz';
        }

        // Specify path to save the file
        $filePath = self::TMP_DIR . 'google-dfp/csv/' . $fileName;

        return $filePath;
    }

    /**
     *  Query the database to get the banner data of a given supplier
     *
     * @param string $tnid
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
	public function getBannerDataForSupplier($tnid = '', $startDate = '', $endDate = '')
	{
		// initialisation
		$impressionTotal = 0;
		$clickTotal = 0;
		$clickData = array();
		$impressionData = array();

        $key = $this->memcacheConfig->client->keyPrefix . 'SVR_BANNER_IMPRESSION_' . $tnid . '-' . $startDate . '-' . $endDate . $this->memcacheConfig->client->keySuffix;
        $key = md5($key);

        // check if memcached is enabled
		$memcache = $this::getMemcache();
		if ($memcache) {
			$result = $memcache->get($key);
			if ($result !== false) {
                return $result;
            }
		}		

		/*
		 * Due to inconsistent date response, this needs to be disabled until google's fixed it.
		 * From now on, there will be nighly script to download data from DFP
		 * $this->updateTableWithLatestDataFromGoogle();
		 *
		 * get all line item that relates to a TNID.
		 * $matchedLineItemId = $this->getLineItemByTnid($tnid); // for testing purposes please use: 56936
		 * if nothing matched then return false
		 * if( count( $matchedLineItemId ) == 0 ) return false;

		 * proceed collecting data based on the lineItemId
		*/
		$sql = "SELECT TO_CHAR(SBS_DATE, 'YYYY-MM-DD') SBS_DATE, sum(SBS_IMPRESSION) AS SBS_IMPRESSION, sum(SBS_CLICK) AS SBS_CLICK FROM pages_svr_banner_statistic WHERE sbs_tnid=:tnid";

		// if period is specified
		if ($startDate !== '' && $endDate !== '') {
			$date = new DateTime;
			$date->setDate(substr($startDate, 0, 4), substr($startDate, 4, 2), substr($startDate, 6, 2));
			$sql .= " AND sbs_date  BETWEEN TO_DATE('". $date->format("d-M-y") . "')";
			
			$date = new DateTime;
			$date->setDate(substr($endDate, 0, 4), substr($endDate, 4, 2), substr($endDate, 6, 2));
			$sql .= " AND TO_DATE('". $date->format("d-M-y") . "')";
		}
		
		// grouped by the date
		$sql .= ' GROUP BY sbs_date';
		$sql .= ' ORDER BY sbs_date DESC';
		
		$result = $this->getDb()->fetchAll($sql, array('tnid' => $tnid));
		
		// parse the result from the db
		foreach ($result as $row) {
			$impressionData[] = array('date' => $row['SBS_DATE'], 'count' => (int) $row['SBS_IMPRESSION']);
			$clickData[] = array('date' => $row['SBS_DATE'], 'count' => (int) $row['SBS_CLICK']);
			
			$impressionTotal += (int) $row['SBS_IMPRESSION'];
			$clickTotal += (int) $row['SBS_CLICK'];
		}
		
		// prepare the data to be pushed
		$data = array(
			'impression' => array('count' => $impressionTotal, 'days' => $impressionData),
			'click' => array('count' => $clickTotal, 'days' => $clickData)
		);
		
		// store the result to cache
		if ($memcache) {
            $memcache->set($key, $data, false, self::MEMCACHE_TTL);
        }

		return $data;
	}

    /**
     * Get sservdba database
     *
     * @return mixed
     */
	protected static function getDb()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}

    /**
     * Convert YYYYMMDD to oracle data format (DD-MON-YY)
     *
     * @param string $date
     * @return string
     */
	public function formatDateToOracleFormat($date)
	{
		$arr = explode('-', $date);
		$date = new DateTime;
		$date->setDate($arr[0], $arr[1], $arr[2]);
		return $date->format('d-M-y');
	}


}
