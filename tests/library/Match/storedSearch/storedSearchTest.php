<?php
/**
 *
 * @author  Yuriy Akopov
 * @date    2013-08-20
 * @story   S7924
 */
class StoredSearchTest extends PHPUnit_Framework_Testcase {
    /**
     * RFQ IDs to run the test searches for
     *
     * @var array
     */
    protected static $rfqIds = null;

    /**
     * Fields which values should be matched when comparing search results
     *
     * @var array
     */
    protected static $resultFieldsToMatch = array(
        'supplier',
        'score'
    );

    /**
     * IDs of the searches created in the process of testing
     *
     * @var array
     */
    protected $createdSearchIds = array();

    /**
     * Returns array of RFQs
     *
     * @return  array
     */
    protected function getRfqIds() {
        if (!is_null(self::$rfqIds)) {
            return self::$rfqIds;
        }

        self::$rfqIds = array();

        foreach ($GLOBALS as $var => $value) {
            if (preg_match('/^rfqId\d+$/', $var)) {
                self::$rfqIds[] = $value;
            }
        }

        return self::$rfqIds;
    }

    /**
     * Compares results saved for the given search with what have been earlier retrieved from the match engine
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   array                           $resultsOriginal
     */
    protected function compareLoadedResults(Shipserv_Match_Component_Search $search, array $resultsOriginal) {
        $resultsStored = $search->getAllResults(false);

        // checking if the lengths of search results lists are identical
        $this->assertEquals(count($resultsStored), count($resultsOriginal));
        if (empty($resultsStored)) {
            return;
        }

        // comparing the items in the lists
        foreach ($resultsStored as $index => $resultObj) {
            $supplierInfoOriginal = $resultsOriginal[$index];
            $supplierInfo = $resultObj->toMatchEngine();

            foreach (self::$resultFieldsToMatch as $key) {
                $this->assertEquals($supplierInfoOriginal[$key], $supplierInfo[$key]);
            }
        }
    }

    /**
     * Compares two search results lists
     *
     * @param   array   $resultsRerun
     * @param   array   $resultsOriginal
     */
    protected function compareSearchResults(array $resultsRerun, array $resultsOriginal) {
        // checking if the lengths of search results lists are identical
        $this->assertEquals(count($resultsRerun), count($resultsOriginal));
        if (empty($resultsRerun)) {
            return;
        }

        // comparing the items in the lists
        foreach ($resultsRerun as $index => $supplierInfo) {
            $supplierInfoOriginal = $resultsOriginal[$index];

            foreach (self::$resultFieldsToMatch as $key) {
                $this->assertEquals($supplierInfoOriginal[$key], $supplierInfo[$key]);
            }
        }
    }

    /**
     * The global "umbrella" test for the stored search functionality defined in story S7924
     *
     * 1. Run a search with match engine
     * 2. Save it.
     * 3. Load the search back from the database into a new match engine instance
     * 4. Run a search
     * 5. Compare the results with received on step 1
     */
    public function testSaveLoadMatch() {
        // searches take time and we allow many RFQs to be tested at once, so disabling the time out here
        // running the search
        $rfqIds = $this->getRfqIds();
        foreach ($rfqIds as $rfqId) {
            // initialising the match engine from RFQ
            $oldTimeOut = ini_set('max_execution_time', 0); // starting a time-consuming process of searching for suppliers matching our RFQ

            $matchEngineOriginal = @new Shipserv_Match_Match($rfqId); // @ is here to supress the numerous warnings from the match engine as it isn't what we're testing at the moment
            $resultsOriginal = $matchEngineOriginal->getMatchedSuppliers();

            ini_set('max_execution_time', $oldTimeOut); // finished with the time-consuming operation

            ////////////////////////////////////
            // STEP 1: saving the search
            $search = Shipserv_Match_Component_Search::fromMatchEngine($matchEngineOriginal, false);
            $this->createdSearchIds[] = $search->getId();   // remembering the ID to removed the search

            ////////////////////////////////////
            // STEP 2: comparing the saved results to the results original search has returned
            $search = Shipserv_Match_Component_Search::getInstanceById($search->getId());
            $this->compareLoadedResults($search, $resultsOriginal);


            ////////////////////////////////////
            // STEP 3: loading the search terms from DB, running the search comparing the results received with the original ones
            $oldTimeOut = ini_set('max_execution_time', 0); // starting a time-consuming process of searching for suppliers matching our RFQ

            // initialising the match engine from search we have just saved
            $matchEngineRestored = @$search->toMatchEngine($search);    // muting the warnings again because of the match engine ones
            $resultsRerun = $matchEngineRestored->getMatchedSuppliers();

            ini_set('max_execution_time', $oldTimeOut); // finished with the time-consuming operation

            $this->compareSearchResults($resultsRerun, $resultsOriginal);
        }
    }

    /**
     * Removes the DB records created during testing if requested or prints out their IDs to tester
     */
    public function tearDown() {
        global $dbCleanUp;

        if (!$dbCleanUp) {
            print PHP_EOL . PHP_EOL;
            print "No DB cleanup requested.";

            if (!empty($this->createdSearchIds)) {
                print
                    "The IDs of the searches generated in the process are: " .
                    implode(', ', $this->createdSearchIds)
                ;
            } else {
                print "No searches were generated in the process";
            }

            print PHP_EOL;

            return;
        }

        print PHP_EOL . PHP_EOL;
        print "DB cleanup: " . PHP_EOL;

        foreach ($this->createdSearchIds as $searchId) {
            print "Removing search " . $searchId . "...";
            Shipserv_Match_Component_Search::removeById($searchId);
            print " removed." . PHP_EOL;
        }
    }
}