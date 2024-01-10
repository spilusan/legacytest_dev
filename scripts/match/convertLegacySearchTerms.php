<?php
/**
 * One-time script to convert legacy sets of search terms saved for some RFQs into new stored searches
 *
 * @author  Yuriy Akopov
 * @date    2013-10-01
 */

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main extends Myshipserv_Cli {
    const
        PARAM_KEY_SCORE_BRAND       = 'score_brand',
        PARAM_KEY_SCORE_CATEGORY    = 'score_category',

        SCORE_BRAND_DEFAULT     = 500,
        SCORE_CATEGORY_DEFAULT  = 500,

        TO_REMOVE  = 'to_remove',
        TO_ADD     = 'to_add'
    ;

    public function displayHelp() {
        print implode(PHP_EOL, array(
            "Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS]",
            "",
            "ENVIRONMENT has to be development|testing|test2|ukdev|production",
            "",
            "   -b          Score to assign to new brands",
            "               Default: " . self::SCORE_BRAND_DEFAULT,
            "",
            "   -c          Score to assign to new categories",
            "               Default: " . self::SCORE_CATEGORY_DEFAULT,
        )) . PHP_EOL;
    }

    /**
     * Defines parameters accepted by the script
     *
     * @return array
     */
    protected function getParamDefinition() {
        return array(
            array(
                self::PARAM_DEF_NAME        => self::PARAM_KEY_SCORE_BRAND,
                self::PARAM_DEF_KEYS        => '-b',
                self::PARAM_DEF_OPTIONAL    => true,
                self::PARAM_DEF_REGEX       => '/^\d+$/',
                self::PARAM_DEF_DEFAULT     => self::SCORE_BRAND_DEFAULT
            ),
            array(
                self::PARAM_DEF_NAME        => self::PARAM_KEY_SCORE_CATEGORY,
                self::PARAM_DEF_KEYS        => '-c',
                self::PARAM_DEF_OPTIONAL    => true,
                self::PARAM_DEF_REGEX       => '/^\d+$/',
                self::PARAM_DEF_DEFAULT     => self::SCORE_CATEGORY_DEFAULT
            )
        );
    }

    /**
     * Returns paginator to all the RFQs to be processed (their searches converted
     *
     * @return Zend_Paginator
     */
    protected function getRfqPaginator() {
        $db = Shipserv_Helper_Database::getDb();

        $selectRfqsFromCategories = new Zend_Db_Select($db);
        $selectRfqsFromCategories
            ->from(
                array('t' => 'match_category_replacement'),
                array('id' => 'mcr_rfq_internal_ref_no')
            )
        ;

        $selectRfqsFromBrands = new Zend_Db_Select($db);
        $selectRfqsFromBrands
            ->from(
                array('t' => 'match_brand_replacement'),
                array('id' => 'mbr_rfq_internal_ref_no')
            )
        ;

        $selectRfqsFromTags = new Zend_Db_Select($db);
        $selectRfqsFromTags
            ->from(
                array('t' => 'match_tag_replacement'),
                array('id' => 'mtr_rfq_internal_ref_no')
            )
        ;

        $selectRfqs = new Zend_Db_Select($db);
        $selectRfqs
            ->union(array($selectRfqsFromBrands, $selectRfqsFromCategories, $selectRfqsFromTags))
            ->distinct()
        ;

        $paginator = Zend_Paginator::factory($selectRfqs);
        return $paginator;
    }

    protected function getUserBrands($rfqId) {
        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('t' => 'match_brand_replacement'),
                array(
                    'id'    => 'mbr_brand_id',
                    'addition'   => 'mbr_is_addition'
                )
            )
            ->where('mbr_rfq_internal_ref_no = :rfq')
        ;

        $rows = $db->fetchAll($select, array('rfq' => $rfqId));
        $data = array(
            self::TO_ADD    => array(),
            self::TO_REMOVE => array()
        );

        if (count($rows)) {
            foreach ($rows as $row) {
                $id = $row['ID'];

                try {
                    $brand = Shipserv_Brand::getInstanceById($id);
                    if (strlen($brand->id) === 0) {
                        throw new Exception();
                    }

                } catch (Exception $e) {
                    $this->output("Brand " . $id . " for RFQ " . $rfqId . " doesn't seem to exist, skipping it");
                    continue;
                }

                if ($row['ADDITION'] === 'Y') {
                    $data[self::TO_ADD][] = $brand->id;
                } else {
                    $data[self::TO_REMOVE][] = $brand->id;
                }
            }
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function run() {
    	
    	 
        try {
            $params = $this->getParams();
        } catch (Exception $e) {
            $this->output("Parameter error: " . $e->getMessage());
            $this->displayHelp();
            return 1;
        }

        $pageRfqs = $this->getRfqPaginator();
        if ($pageRfqs->getTotalItemCount() === 0) {
            $this->output("Nothing to convert");
            return 0;
        }

        $db = Shipserv_Helper_Database::getDb();
        $db->beginTransaction();

        for ($pageNo = 1; $pageNo <= count($pageRfqs); $pageNo++) {
            $pageRfqs->setCurrentPageNumber($pageNo);
            $rfqRows = $pageRfqs->getCurrentItems();

            foreach ($rfqRows as $rfqRow) {
                $rfqId = $rfqRow['ID'];

                try {
                    $rfq = Shipserv_Rfq::getInstanceById($rfqId);

                } catch (Exception $e) {
                    $this->output("RFQ " . $rfqId . " doesn't seem to exist, skipping it");
                    continue;
                }

                try {
                    $match = @new Shipserv_Match_Match($rfqId, true);

                    print_r($this->getUserBrands($rfqId));

                } catch (Exception $e) {
                    $this->output("An error occured while processing RFQ " . $rfqId . " (" . get_class($e) . ": " . $e->getMessage() . ")");

                    $db->rollBack();
                    $this->output("DB changes rolled back");

                    return 1;
                }

                $this->output("Converted RFQ " . $rfqId . ", search created ");
            }
        }


        $db->commit();

        $this->output("Finished");
        return 0;
    }
}

$script = new Cl_Main();
$status = $script->run();

exit($status);
