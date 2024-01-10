<?php
/**
 * CLI script to match new incoming RFQs against the keywords defined by suppliers
 *
 * The purpose of the script is to check if keywords specified by participating suppliers are found in new RFQs and mark
 * such RFQs to be later processed by the match engine
 *
 * @author  Yuriy Akopov
 * @date    2013-09-02
 * @story   8133
 */

// when running on PHP 5.4 locally our legacy code generates hell lot of strict mode and other notices @todo: to be removed on deployment
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main extends Myshipserv_Cli {
    const
        PARAM_KEY_START_RFQ_ID         = 'rfqId',
        PARAM_KEY_START_RFQ_ID_SHORT   = '-r'
    ;

    public function displayHelp() {
        print implode(PHP_EOL, array(
                "Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS] [RFQIDS]",
                "",
                "ENVIRONMENT has to be development|testing|test2|ukdev|production",
                "",
                "Available options:",
                "   " . self::PARAM_KEY_START_RFQ_ID_SHORT . "          - ID of the RFQ to start processing from"
            )) . PHP_EOL
        ;
    }

    /**
     * Defines parameters accepted by the script
     *
     * @return array
     */
    protected function getParamDefinition() {
        // please see displayHelp() function for parameter description
        return array(array(
            self::PARAM_DEF_NAME        => self::PARAM_KEY_START_RFQ_ID,
            self::PARAM_DEF_KEYS        => self::PARAM_KEY_START_RFQ_ID_SHORT,
            self::PARAM_DEF_OPTIONAL    => true,
            self::PARAM_DEF_REGEX       =>'/^\d+$/'
        ));
    }

    /**
     * Returns ID of the first RFQ to process (so we don't process same RFQs twice)
     *
     * @return  int
     */
    public function getLastProcessedRfqId() {
        // check if RFQ ID was provided by user
        $params = $this->getParams();

        if (strlen($params[self::PARAM_GROUP_DEFINED][self::PARAM_KEY_START_RFQ_ID])) {
            $lastProcessedRfqId = $params[self::PARAM_GROUP_DEFINED][self::PARAM_KEY_START_RFQ_ID];
        } else {
            // attempt to load last stored RFQ Id
            $lastProcessedRfqId = Shipserv_Match_Settings::get(Shipserv_Match_Settings::AUTOMATCH_LAST_RFQ_ID);

            if (is_null($lastProcessedRfqId)) {
                // default to first RFQ placed later than 24 hours ago
                $db = Shipserv_Helper_Database::getDb();
                $select = new Zend_Db_Select($db);
                $select
                    ->from(
                        array('rfq' => Shipserv_Rfq::TABLE_NAME),
                        new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_ID . ')')
                    )
                    ->where('rfq.' . Shipserv_Rfq::COL_DATE . ' > (SYSDATE - ?)', 1);
                ;

                $lastProcessedRfqId = $db->fetchOne($select);
            }
        }

        return $lastProcessedRfqId;
    }

    /**
     * Stored the ID of the last RFQ matched against the supplier keywords set
     *
     * @param int $lastIndexedRfqId
     */
    public function saveLastProcessedRfqId($lastIndexedRfqId) {
        Shipserv_Match_Settings::set(Shipserv_Match_Settings::AUTOMATCH_LAST_RFQ_ID, $lastIndexedRfqId);
    }

    /**
     * Script entry point
     *
     * @throws Exception
     */
    public function run() {
    	
    	$cronLogger = new Myshipserv_Logger_Cron( 'AutoMatch::Run' );
    	$cronLogger->log();
    	 
        // check which RFQ do we need to start from
        $lastProcessedRfq = $this->getLastProcessedRfqId();
        $this->output("Processing RFQs starting from " . $lastProcessedRfq);

        if (!($lastProcessedRfq > 0)) {
            $this->output("No start RFQ specified, aborting");
            return 1;
        }

        // initialise search and request the most recent RFQ ID in index - on the next run we will start checking with this ID
        $search = new Myshipserv_Search_AutoMatch($lastProcessedRfq);
        $lastIndexedRfqId = $search->getLastIndexedRfqId();
        $this->output("Index initialised, last indexed RFQ: " . $lastIndexedRfqId);

        if ($lastIndexedRfqId <= $lastProcessedRfq) {
            $this->output("RFQ index not up to date or there are no new RFQs, aborting. Try running the script later after it's updated.");
            return 1;
        }

        // read keyword sets
        $pageSize = 100;
        $sets = Shipserv_Match_Auto_Manager::getKeywordSets();
        $this->output(count($sets) . " enabled keyword sets found, processing at rate " . $pageSize . " keywords at time");

        foreach ($sets as $setId => $setName) {
            // loading keywords from the set
            $keywordSelect = Shipserv_Match_Auto_Manager::getKeywordsSelectForSet($setId);
            $keywordPaginator = Zend_Paginator::factory($keywordSelect);
            $keywordCount = $keywordPaginator->getTotalItemCount();

            $this->output("Processing keyword set \"" . $setName . "\" (ID " . $setId . ") of " . $keywordCount . " keywords...");

            $keywordPaginator->setDefaultItemCountPerPage($pageSize);
            $pageNo = 1;
            $pageTotal = count($keywordPaginator);

            $matchNew = 0;
            $matchTotal = 0;

            while ($pageNo <= $pageTotal) {
                $keywordPaginator->setCurrentPageNumber($pageNo);
                $keywords = Shipserv_Match_Auto_Manager::flattenKeywordRows($keywordPaginator->getCurrentItems());

                $eventHashes = $search->getMatchingEvents($keywords);
                $matchTotal += count($eventHashes);

                foreach ($eventHashes as $rfqEventHash) {
                    try {
                        $rfq = Shipserv_Rfq::getInstanceByEvent($rfqEventHash);
                    } catch (Exception $e) {
                        // no such RFQ or no submitted RFQs in the event
                        continue;
                    }

                    if (Shipserv_Match_Auto_Manager::markAutoMatchedRfq($rfq, $setId)) {
                        $matchNew++;
                    }
                }

                $this->output("Finished processing page " . $pageNo . " of " . $pageTotal . " for set " . $setId);
                $pageNo++;
            }

            $this->output("Finished processing keyword set \"" . $setName . "\" (ID " . $setId . ") with " . $matchTotal . " (" . $matchNew . " new) matching RFQs found");
        }

        $this->saveLastProcessedRfqId($lastIndexedRfqId);
        $this->output("Saved last analysed RFQ ID " . $lastIndexedRfqId . " to start from it on the next run");
    }
}

$script = new Cl_Main();
$status = $script->run();

exit($status);
