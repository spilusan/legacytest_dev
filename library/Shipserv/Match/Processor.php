<?php

/**
 * Used for CLI processes
 * Process suppliers for Shipserv Match
 *
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class Shipserv_Match_Processor extends Shipserv_Object {

    public static function test() {
        $db = self::getStandByDb();
        $test = "select count(*) from supplier_tag";
        $res = $db->fetchAll($test);
        echo print_r($res, true);
    }

    /**
     *
     * Function to fetch all recent IDs from RFQ,
     * Order and Quote tables on SSReport2 and
     * store them to the preprocess table.
     *
     */
    public static function PreProcess() {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        //Start with RFQs
        echo "<proc>\n\nStarting RFQ preprocess...\n\n";
        $rfqSQL = "INSERT INTO Line_item_Cat_Preprocess
                    SELECT DocType,
                      RFQ_INTERNAL_REF_NO
                    FROM
                      (SELECT 'RFQ' AS DocType, RFQ_INTERNAL_REF_NO, rownum AS r FROM RFQ WHERE RFQ_submitted_date > (sysdate - 1))";


        $result = $db->query($rfqSQL);
        echo "RFQs done.\n\n";
        echo "Starting QOT preprocess...\n\n";
        //Qot
        $qotSQL = "INSERT INTO Line_item_Cat_Preprocess
                    SELECT DocType,
                      QOT_INTERNAL_REF_NO
                    FROM
                      (SELECT 'QOT' AS DocType,
                        QOT_INTERNAL_REF_NO,
                        rownum AS r
                      FROM QOT
                      WHERE qot_submitted_date > (sysdate - 1)
                      )";
        $result = $db->query($qotSQL);
        echo "QOTs done.\n\n";
        echo "Starting ORD preprocess...\n\n";
        $ordSQL = "INSERT INTO Line_item_Cat_Preprocess
                    SELECT DocType,
                      ORD_INTERNAL_REF_NO
                    FROM
                      (SELECT 'ORD' AS DocType,
                        ORD_INTERNAL_REF_NO,
                        rownum AS r
                      FROM ORD
                      WHERE ord_submitted_date > (sysdate - 1)
                      )";

        $result = $db->query($ordSQL);

        echo "ORDs done. Getting final count:\n\n";

        $countSQL = "Select count(*) from line_item_cat_preprocess";
        $count = $db->fetchOne($countSQL);


        echo "Process complete. $count items ready to process.\n\n</proc>\n\n";
    }

    public static function StartProcessing($start = 0) {
        $db = self::getStandByDb();
        echo "Starting at $start\n";
        $supplierListSQL = "Select distinct qot_spb_branch_code from quote where qot_created_date > (sysdate-365) order by qot_spb_branch_code asc";

        $supplierList = $db->fetchAll($supplierListSQL);
        echo "Supplier List retreived. " . count($supplierList) . " suppliers to process\n";
        $tg = new Shipserv_Supplier_TagGenerator();


        foreach ($supplierList as $tnid) {
            if ($start > $tnid['QOT_SPB_BRANCH_CODE']) {
                continue;
            }
            echo "Processing " . $tnid['QOT_SPB_BRANCH_CODE'] . "\n";
            $tg->generateTagsFromHistoricQuotes($tnid['QOT_SPB_BRANCH_CODE'], $db);
        }
    }

    private static function getStandByDb() {
        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        echo "Resource fetched\n";
        return $resource->getDb('standbydb');
    }

    private static function getLocalDb() {
        return $GLOBALS['application']->getBootstrap()->getResource('db');
    }

    public static function processRfqs() {
        $numDays = 30;
        $sql = "Select rfq_internal_ref_no from request_for_quote r, rfq_quote_relation rq rq where r.rfq_internal_ref_no = rq.rqr_rfq_internal_ref_no and rqr_submitted_date > (sysdate - $numDays)";

        $db = self::getStandByDb();
    }

    public static function processLineItems() {

        $SERVER = '10.59.19.50';
        $DB_NAME = 'shipserv';

        //$conn = new Mongo($SERVER);
        //$mongodb = $conn->selectDB($DB_NAME);
        //$mongodb->dropCollection("catRfqLineItems");
        //$collection = $mongodb->createCollection("catRfqLineItems");

        $helper = new Shipserv_Helper_Pattern();

        $db = self::getStandbyDb();
        $dbLocal = self::getLocalDb();



        $sql = "Select Count(*) from rfq_line_item rl,
                      request_for_quote r,
                      rfq_quote_relation rq
                    WHERE r.rfq_internal_ref_no = rq.rqr_rfq_internal_ref_no
                    AND r.rfq_internal_ref_no   = rl.rfl_rfq_internal_ref_no
                    AND r.rfq_internal_ref_no in (Select qot_rfq_internal_ref_no from quote where qot_submitted_date > (sysdate - 62))
                    AND RFQ_STS                 = 'SUB'
                    AND rq.rqr_submitted_date   between (sysdate-61) and (sysdate-30)";

        $count = $db->fetchOne($sql);

        //$loopCount = 0;
        $batchCount = 1000;
        $stages = ceil($count / $batchCount);
        $failCounter = 0;
        $total = 0;
        for ($index = 0; $index < $stages; $index++) {
            $sql = "Select * from (SELECT r.rfq_subject, rl.*, rownum as r
                    FROM rfq_line_item rl,
                      request_for_quote r,
                      rfq_quote_relation rq
                    WHERE r.rfq_internal_ref_no = rq.rqr_rfq_internal_ref_no
                    AND r.rfq_internal_ref_no   = rl.rfl_rfq_internal_ref_no
                    AND r.rfq_internal_ref_no in (Select qot_rfq_internal_ref_no from quote where qot_submitted_date > (sysdate - 62))
                    AND RFQ_STS                 = 'SUB'
                    AND rq.rqr_submitted_date   between (sysdate-61) and (sysdate-30)) where r between " . ($index * $batchCount) . " AND " . ((($index + 1) * $batchCount) - 1);

            $results = $db->fetchAll($sql);

            $sqlInsert = "
INSERT
INTO Categorised_RFQ_line_item
  (
    RFQ_SUBJECT,
    RFL_RFQ_INTERNAL_REF_NO,
    RFL_LINE_ITEM_NO,
    RFL_QUANTITY ,
    RFL_ID_TYPE ,
    RFL_ID_CODE ,
    RFL_PRODUCT_DESC ,
    RFL_QUALITY ,
    RFL_UNIT ,
    RFL_PRIORITY ,
    RFL_COMMENTS ,
    RFL_UNIT_COST ,
    RFL_TOTAL_LINE_ITEM_COST ,
    RFL_STS ,
    RFL_CONFG_NAME ,
    RFL_CONFG_DESC ,
    RFL_CONFG_MANUFACTURER ,
    RFL_CONFG_MODEL_NO,
    RFL_CONFG_RATING ,
    RFL_CONFG_SERIAL_NO ,
    RFL_CONFG_DRAWING_NO ,
    RFL_CONFG_DEPT_TYPE ,
    RFL_DELIVERY_STS ,
    RFL_ACCOUNT_REF ,
    RFL_WEIGHT ,
    RFL_CONFG_DEPT_CODE ,
    RFL_CREATED_BY ,
    RFL_CREATED_DATE,
    RFL_UPDATED_BY ,
    RFL_UPDATED_DATE ,
    RFL_RLI_LINE_ITEM_NO ,
    RFL_DISCOUNTED_UNIT_COST ,
    RFL_SOURCERFQ_INTERNAL_NO ,
    RFL_SOURCERFQ_LINEITEM_NO ,
    CATEGORY,
    CATEGORYIDCODE
  )
  VALUES
  (
    :RFQ_SUBJECT,
    :RFL_RFQ_INTERNAL_REF_NO,
    :RFL_LINE_ITEM_NO,
    :RFL_QUANTITY ,
    :RFL_ID_TYPE ,
    :RFL_ID_CODE ,
    :RFL_PRODUCT_DESC ,
    :RFL_QUALITY ,
    :RFL_UNIT ,
    :RFL_PRIORITY ,
    :RFL_COMMENTS ,
    :RFL_UNIT_COST ,
    :RFL_TOTAL_LINE_ITEM_COST ,
    :RFL_STS ,
    :RFL_CONFG_NAME ,
    :RFL_CONFG_DESC ,
    :RFL_CONFG_MANUFACTURER ,
    :RFL_CONFG_MODEL_NO,
    :RFL_CONFG_RATING ,
    :RFL_CONFG_SERIAL_NO ,
    :RFL_CONFG_DRAWING_NO ,
    :RFL_CONFG_DEPT_TYPE ,
    :RFL_DELIVERY_STS ,
    :RFL_ACCOUNT_REF ,
    :RFL_WEIGHT ,
    :RFL_CONFG_DEPT_CODE ,
    :RFL_CREATED_BY ,
    :RFL_CREATED_DATE,
    :RFL_UPDATED_BY ,
    :RFL_UPDATED_DATE ,
    :RFL_RLI_LINE_ITEM_NO ,
    :RFL_DISCOUNTED_UNIT_COST ,
    :RFL_SOURCERFQ_INTERNAL_NO ,
    :RFL_SOURCERFQ_LINEITEM_NO ,
    :CategoryName ,
    :CategoryIDCode
  )";

            foreach ($results as $result) {

                $insert = $result;
                unset($insert['R']);
                $catStr = "";
                $concat = implode(" >>>><<<< ", array_values($result));
                $category = "";
                if ($result['RFL_ID_TYPE'] == 'ZIM' || $result['RFL_ID_TYPE'] == 'IMPA') {
                    //IMPA code
                    $stop = true;

                    $impa = preg_replace('/[^0-9]/', '', $result['RFL_ID_CODE']);
                    $primaryCat = substr($impa, 0, 2);
                    if (is_numeric($primaryCat)) {
                        $sql = "Select PCS_SECTION_NAME from Pages_Catalogue_IMPA_section where PCS_SECTION_INDEX = $primaryCat";
                        $section = $dbLocal->fetchOne($sql);
                        $category = "{IMPA} " . $section;

                        $insert['CategoryName'] = $category;
                        $insert['CategoryIDCode'] = $primaryCat;
                        $dbLocal->query($sqlInsert, $insert);
                    } else {
                        $category = '';
                    }
                } else {
                    //Parse subject first, if that has a category match, apply that, otherwise parse remaining fields.
                    $subjCategories = $helper->parseCategories($result['RFQ_SUBJECT'], true);
                    if (count($subjCategories) > 0) {
                        //Apply this to the reference.
                        //"Concatenate the categories and insert"
                        foreach ($subjCategories as $sCat) {
                            $category .= "{Pages Subj} " . $sCat['tag'] . " ({$sCat['id']})" . ";";
                        }

                        $insert['CategoryName'] = $category;
                        $insert['CategoryIDCode'] = '';
                        $dbLocal->query($sqlInsert, $insert);
                    } else {
                        $categories = $helper->parseCategories($concat, true);
                        if (count($categories) > 0) {
                            $stop = true;
                            foreach ($categories as $cat) {
                                $category .= "{Pages li} " . $cat['tag'] . " ({$cat['id']})" . ";";
                            }
                            $insert['CategoryName'] = $category;
                            $insert['CategoryIDCode'] = '';

                            $dbLocal->query($sqlInsert, $insert);
                            //Create record with Line Item Details and Category
                        } else {
                            $failCounter++;
                        }
                    }
                }

                $total++;
            }
        }
    }

    /**
     * This procedure will generate the keywords for all RFQs, look at
     * their suppliers and add details of the keyword and every categroy
     * to a histogram table. That should allow us to view trends in
     * keywords -> categories
     */
    public static function processMatchKeywordCategoryHistogram($days = 30) {
        $days = (Int) $days;
        //First get a list of RFQs to process. We only need one RFQ event, and a list of the suppliers it was sent to. No point duplicating the RFQs
        $sb = self::getStandbyDb();

        $db = self::getLocalDb();
        ini_set('memory_limit', '400M');
        $rfqHeadersQuery = "SELECT rfq_byb_branch_code,
                        rfq_ref_no,
                        r.rfq_imo_no,
                        r.rfq_vessel_name,
                        r.rfq_comments,
                        r.rfq_line_item_count
                      FROM request_for_quote r,
                        rfq_quote_relation rq
                      WHERE r.rfq_internal_ref_no  = rq.rqr_rfq_internal_ref_no
                      AND r.rfq_sts                = 'SUB'
                      AND rq.rqr_submitted_date    > (sysdate - $days)
                      AND rfq_byb_branch_code NOT            IN (11107)
                      GROUP BY rfq_byb_branch_code,
                        rfq_ref_no,
                        r.rfq_imo_no,
                        r.rfq_vessel_name,
                        r.rfq_comments,
                        r.rfq_line_item_count";

        $rfqItems = $sb->fetchAll($rfqHeadersQuery);

        echo "\n-->" . count($rfqItems) . " to be processed.\n";

        $tagGen = new Shipserv_Match_TagGenerator($sb);

        foreach ($rfqItems as $rfqHeader) {
            //First, get the first item to match the RFQ details, then the suppliers it was sent to.
            $rfqQuery = "SELECT rfq_internal_ref_no
                            FROM
                              (SELECT rfq_internal_ref_no,
                                rownum AS r
                              FROM request_for_quote
                              WHERE rfq_byb_branch_code = :RFQ_BYB_BRANCH_CODE
                              AND rfq_ref_no            = :RFQ_REF_NO
                              AND rfq_imo_no            = :RFQ_IMO_NO
                              AND rfq_vessel_name       = :RFQ_VESSEL_NAME
                              AND rfq_comments          = :RFQ_COMMENTS
                              AND rfq_line_item_count   = :RFQ_LINE_ITEM_COUNT
                              )
                            WHERE r = 1";


            $rfqIdHolder = $sb->fetchRow($rfqQuery, $rfqHeader);
            $rfqId = $rfqIdHolder['RFQ_INTERNAL_REF_NO'];

            echo "Found rfqId $rfqId for {$rfqHeader['RFQ_REF_NO']}\n";
            //Copy from Match Original Supplierss list query
            $supplierQuery = "SELECT RQR_SPB_BRANCH_CODE AS BranchCode,
                                c.cnt_con_code           AS Continent,
                                c.cnt_country_code       AS country,
                                sb.spb_name              AS name,
                                rqr.rqr_byb_branch_code
                              FROM rfq_quote_relation rqr,
                                supplier_branch sb,
                                country c
                              WHERE sb.spb_country             = c.cnt_country_code
                              AND rqr.rqr_spb_branch_code      = sb.spb_branch_code
                              AND rqr.rqr_rfq_internal_ref_no IN
                                (SELECT RFQ_INTERNAL_REF_NO
                                FROM request_for_quote
                                WHERE rqr.rqr_byb_branch_code = request_for_quote.rfq_byb_branch_code
                                AND rfq_ref_no                =
                                  (SELECT rfq_ref_no FROM request_for_quote WHERE rfq_internal_ref_no = :rfq
                                  )
                                )
                              AND RQR_SPB_BRANCH_CODE      != 999999
                              AND sb.directory_entry_status = 'PUBLISHED'
                              GROUP BY RQR_SPB_BRANCH_CODE,
                                c.cnt_con_code,
                                c.cnt_country_code,
                                sb.spb_name,
                                rqr.rqr_byb_branch_code";
            $params = array('rfq' => $rfqId);

            $suppliers = $sb->fetchAll($supplierQuery, $params);

            echo "Found " . count($suppliers) . " for RFQ id $rfqId ... Processing keywords.  \n";

            $tags = $tagGen->generateTagsFromRFQ($rfqId);

            //Now get suppliers categories, and insert to the database all keyword->cat combos.
            foreach ($suppliers as $supplier) {
                //Get Supp categories
                $supplierId = $supplier['BRANCHCODE'];
                $suppCatQuery = "Select Id from Product_Category where id in (Select Product_category_id from Supply_category where Supplier_Branch_Code = :tnid)";
                $params = array('tnid' => $supplierId);



                $suppCats = $sb->fetchAll($suppCatQuery, $params);

                foreach ($suppCats as $cat) {
                    foreach ($tags['tags'] as $tag) {
                        $sql = "Insert into match_keyword_cat_histogram (mkh_keyword, mkh_cat_id) values (:keyword, :category)";
                        $params = array('keyword' => $tag['tag'], 'category' => $cat['ID']);
                        $result = $db->query($sql, $params);
                    }
                }
            }
        }

        ini_set('memory_limit', '134M');
    }

    public function processLineItemsForKeywords() {

        //Get standby db
        $sb = $this->getStandByDb();

        $db = $this->getLocalDb();

        //============================
        // Process this by line item. First start
        // by getting the first quote id in the queue
        //============================
        $firstIDSQL = "SELECT MIN(QOT_INTERNAL_REF_NO) FROM quote@moses_link q
                        WHERE q.qot_submitted_date > (sysdate - 7) and q.qot_internal_ref_no > (Select max(lik_doc_id) from line_item_keywords_cache )
                        ";

        $subsequentIDSql = $firstIDSQL . " AND q.QOT_INTERNAL_REF_NO    > :id ";

        $id = $db->fetchOne($firstIDSQL);

        $tg = new Shipserv_Match_TagGenerator($db);

        do {
            $oldId = $id;
            $lineItemsSQL = "SELECT QLI_QOT_INTERNAL_REF_NO,
                            QLI_LINE_ITEM_NUMBER,
                            QLI_DESC,
                            QLI_COMMENTS,
                            QLI_CONFG_NAME,
                            QLI_CONFG_DESC,
                            QLI_CONFG_MODEL_NO,
                            QLI_CONFG_SERIAL_NO,
                            q.qot_spb_branch_code
                          FROM quote_line_item ql,
                            quote q
                          WHERE ql.qli_qot_internal_ref_no = q.qot_internal_ref_no
                          AND q.qot_internal_ref_no = :qli_id";

            $lineItems = $sb->fetchAll($lineItemsSQL, array('qli_id' => $oldId));
            $tnid = $item['QOT_SPB_BRANCH_CODE'];
            $categories = $this->getSupplierCategories($tnid, $sb);


            foreach ($lineItems as $item) {
                $copiedArr = $item;
                //Dont need these for the tagging.
                unset($copiedArr['QOT_SPB_BRANCH_CODE']);
                unset($copiedArr['QLI_QOT_INTERNAL_REF_NO']);
                unset($copiedArr['QLI_LINE_ITEM_NUMBER']);
                $stringToParse = implode('  =====  ', $copiedArr);

                $tags = $tg->generateTagsFromText(array(array('text' => $stringToParse)));
                //Supplier categories



                if (count($tags['tags']) > 0) {

                    $this->storeTagsInDB($tags['tags'], 'QOT', $id, $item['QLI_LINE_ITEM_NUMBER'], $db);

                    //================================
                    //Now loop through eacch tag and category
                    //and merge into stats
                    //================================
                    foreach ($tags['tags'] as $tag) {
                        $kwSql = "Select * from Line_item_keyword_histogram where lih_keyword = :tag";
                        $params = array('tag' => $tag['tag']);
                        $row = $db->fetchRow($kwSql, $params);
                        if ($row) {
                            $update = true;
                            $histogram = unserialize($row['LIH_HISTOGRAM']);
                        } else {
                            $update = false;
                            $histogram = array();
                        }

                        foreach ($categories as $category) {
                            //Select the keyword from the DB first
                            $histogram['cat_' . $category['ID']]++;
                        }

                        //serialise:
                        $serialisedHistogram = serialize($histogram);

                        if ($update) {
                            $sql = "Update line_item_keyword_histogram set lih_histogram = :histogram, lih_count = :count where lih_keyword = :keyword ";
                            $params = array('histogram' => $serialisedHistogram, 'count' => $row['LIH_COUNT'] + 1, 'keyword' => $tag['tag']);
                        } else {
                            $sql = "Insert into line_item_keyword_histogram (Lih_histogram, lih_count, lih_keyword) values (:histogram, :count, :keyword)";
                            $params = array('histogram' => $serialisedHistogram, 'count' => 1, 'keyword' => $tag['tag']);
                        }
                        $result = $db->query($sql, $params);
                    }
                }
            }


            $id = $db->fetchOne($subsequentIDSql, array('id' => $oldId));
        } while ($id !== false);
    }

    public function categoriseQuoteLineItems($qotId, $helper, $multiplier) {

        //$db = Shipserv_Helper_Database::getDb();
        $reporting = Shipserv_Helper_Database::getSsreport2Db();
        $sb = Shipserv_Helper_Database::getStandByDb(true);

        $docType = 'QOT'; //'ORD' or 'RFQ'
        //=====================================
        //1. Get Quote Id
        //2. Get Line Items
        //3. Concat String Fields
        //4. CategoryParse
        //5 .Store the results.
        //=====================================
        //$helper = new Shipserv_Helper_Pattern();
        //1.


        $lineItemsSQL = "SELECT q.qot_byb_branch_code,
                                q.qot_spb_branch_code,
                                QLI_DESC,
                                QLI_COMMENTS,
                                QLI_CONFG_NAME,
                                QLI_CONFG_DESC,
                                QLI_CONFG_MODEL_NO,
                                QLI_CONFG_SERIAL_NO,
                                QLI_LINE_ITEM_NUMBER,
                                round(ql.qli_total_line_item_cost / c.curr_exchange_rate, 2) as NormValue
                              FROM quote_line_item ql,
                                quote q, currency c
                              WHERE q.qot_internal_ref_no = ql.qli_qot_internal_ref_no
                              and q.qot_currency = c.curr_code
                              AND q.qot_internal_ref_no   = :id
                            ";
        //2.
        $lineItems = $sb->fetchAll($lineItemsSQL, array('id' => $qotId));

        //3.
        foreach ($lineItems as $item) {

            $concat = implode(' ==== ', $item);
            //4.
            $cats = $helper->categoryParse($concat);

            if (count($cats) > 0) {
                $cat1 = 0;
                $cat2 = 0;
                $cat3 = 0;
                for ($index = 0; $index < 3; $index++) {
                    if (!empty($cats[$index]['id'])) {
                        $indexName = 'cat' . ($index + 1);
                        $$indexName = $cats[$index]['id'];
                    }
                }
            }
            //5.
            $insertSQL = "Insert into Line_Item_category_info (LIC_DOC_TYPE,
                            LIC_DOC_ID, LIC_DOC_LI_NUM, LIC_BUYER_ID, LIC_SUPPLIER_ID,
                            LIC_CAT_1, LIC_CAT_2, LIC_CAT_3,LIC_NORM_VALUE) values (
                            :docType, :docId, :lineNum, :buyer, :supplier, :cat1, :cat2, :cat3, :val
                            )";
            $params = array(
                'docType' => 'QOT',
                'docId' => $qotId,
                'lineNum' => $item['QLI_LINE_ITEM_NUMBER'],
                'buyer' => $item['QOT_BYB_BRANCH_CODE'],
                'supplier' => $item['QOT_SPB_BRANCH_CODE'],
                'cat1' => $cat1,
                'cat2' => $cat2,
                'cat3' => $cat3,
                'val' => $item['NORMVALUE'],
            );
            for ($index1 = 0; $index1 < count($multiplier); $index1++) {
                $queryResult = $reporting->query($insertSQL, $params);
            }
        }


        //$histogram = $this->generateCategoryArray('QOT', 1, 1, $db);
        //print_r($histogram, true);
    }

    public function lineitemCategorisation($minmax = 'MIN') {
        $reporting = Shipserv_Helper_Database::getSsreport2Db();
        $nextIDSql = " SELECT *
                        FROM line_item_cat_preprocess where lip_doc_id in
                          (SELECT  $minmax(lip_doc_id) FROM line_item_cat_preprocess
                          )";

        echo "Fetching First document set:\n";

        $rows = $reporting->fetchAll($nextIDSql);

        //1.
        $helper = new Shipserv_Helper_Pattern();
        do {
            $rowCount = count($rows);
            $row = $rows[0];

            $docType = $row['LIP_DOC_TYPE'];
            $docId = $row['LIP_DOC_ID'];

            switch ($docType) {
                case 'ORD':
                    echo "Processing Order $docId x $rowCount\n";
                    $this->categoriseOrdLineItems($docId, $helper, $rowCount);
                    break;
                case 'QOT':
                    echo "Processing Quote $docId x $rowCount\n";
                    $this->categoriseQuoteLineItems($docId, $helper, $rowCount);
                    break;
                case 'RFQ':

                    echo "Processing this RFQ $docId x $rowCount\n";
                    $this->categoriseRfqLineItems($docId, $helper, $rowCount);
                    break;
                default:
                    break;
            }

            $deleteSQL = "Delete from
                            line_item_cat_preprocess
                         where
                            lip_doc_type = :docType
                                and
                            lip_doc_id = :docId";

            $params = array('docType' => $docType, 'docId' => (int) $docId);
            echo "Removing $rowCount record(s) from pre-process table\n";
            $reporting->query($deleteSQL, $params);

            $rows = $reporting->fetchAll($nextIDSql);
        } while (false !== $rows && count($rows) > 0);

        echo "\n\n**** Process Complete ****\n\n";
    }

    private function categoriseRfqLineItems($rfqid, $helper, $multiplier) {
        // $db = Shipserv_Helper_Database::getDb();
        $reporting = Shipserv_Helper_Database::getSsreport2Db();
        $sb = Shipserv_Helper_Database::getStandByDb(true);

        //=====================================
        //1. Get RFQ Id
        //2. Get Line Items
        //3. Concat String Fields
        //4. CategoryParse
        //5 .Store the results.
        //=====================================
        $firstIdSql = "";
        $firstId = 0;

        //$helper = new Shipserv_Helper_Pattern();

        $lineItemsSQL = "SELECT r.rfq_byb_branch_code,
                        rq.rqr_spb_branch_code,
                        Rfl_product_DESC,
                        rfl_COMMENTS,
                        rfl_CONFG_NAME,
                        rfl_CONFG_DESC,
                        rfl_CONFG_MODEL_NO,
                        rfl_CONFG_SERIAL_NO,
                        rfl_LINE_ITEM_NO
                      FROM rfq_line_item rl,
                        request_for_quote r,
                        rfq_quote_relation rq
                      WHERE R.rfq_internal_ref_no = rl.rfl_rfq_internal_ref_no
                      AND r.rfq_internal_ref_no   = rq.rqr_rfq_internal_ref_no
                      AND r.rfq_internal_ref_no   = :id
                            ";
        //2.
        $lineItems = $sb->fetchAll($lineItemsSQL, array('id' => $rfqid));

        //3.
        foreach ($lineItems as $item) {

            $concat = implode(' ==== ', $item);
            //4.
            $cats = $helper->categoryParse($concat);

            if (count($cats) > 0) {
                $cat1 = 0;
                $cat2 = 0;
                $cat3 = 0;
                for ($index = 0; $index < 3; $index++) {
                    if (!empty($cats[$index]['id'])) {
                        $indexName = 'cat' . ($index + 1);
                        $$indexName = $cats[$index]['id'];
                    }
                }
            }

            //5.

            $insertSQL = "Insert into Line_Item_category_info (LIC_DOC_TYPE,
                            LIC_DOC_ID, LIC_DOC_LI_NUM, LIC_BUYER_ID, LIC_SUPPLIER_ID,
                            LIC_CAT_1, LIC_CAT_2, LIC_CAT_3,LIC_NORM_VALUE) values (
                            :docType, :docId, :lineNum, :buyer, :supplier, :cat1, :cat2, :cat3, :val
                            )";
            $params = array(
                'docType' => 'RFQ',
                'docId' => $rfqid,
                'lineNum' => $item['RFL_LINE_ITEM_NO'],
                'buyer' => $item['RFQ_BYB_BRANCH_CODE'],
                'supplier' => $item['RQR_SPB_BRANCH_CODE'],
                'cat1' => $cat1,
                'cat2' => $cat2,
                'cat3' => $cat3,
                'val' => 0
            );
            for ($index1 = 0; $index1 < count($multiplier); $index1++) {
                $queryResult = $reporting->query($insertSQL, $params);
            }
        }
    }

    private function categoriseOrdLineItems($ordid, $helper, $multiplier) {

        //$db = Shipserv_Helper_Database::getDb();
        $reporting = Shipserv_Helper_Database::getSsreport2Db();
        $sb = Shipserv_Helper_Database::getStandByDb(true);



        //=====================================
        //1. Get Quote Id
        //2. Get Line Items
        //3. Concat String Fields
        //4. CategoryParse
        //5 .Store the results.
        //=====================================
        $firstIdSql = "";
        $firstId = 0;

        //$helper = new Shipserv_Helper_Pattern();

        $lineItemsSQL = "SELECT o.ord_byb_buyer_branch_code,
                        o.ord_spb_branch_code,
                        oLI_DESC,
                        oLI_COMMENTS,
                        oLI_CONFG_NAME,
                        oLI_CONFG_DESC,
                        oLI_CONFG_MODEL_NO,
                        oLI_CONFG_SERIAL_NO,
                        oLI_ORDER_LINE_ITEM_NO,
                        round(ol.oli_total_line_item_cost / c.curr_exchange_rate, 2) as NormValue
                      FROM order_line_item ol,
                        purchase_order o, currency c
                      WHERE o.ord_internal_ref_no = ol.oli_order_internal_ref_no
                      and o.ord_currency = c.curr_code
                      AND o.ord_internal_ref_no  = :id
                            ";
        //2.
        $lineItems = $sb->fetchAll($lineItemsSQL, array('id' => $ordid));

        //3.
        foreach ($lineItems as $item) {

            $concat = implode(' ==== ', $item);
            //4.
            $cats = $helper->categoryParse($concat);

            if (count($cats) > 0) {
                $cat1 = 0;
                $cat2 = 0;
                $cat3 = 0;
                for ($index = 0; $index < 3; $index++) {
                    if (!empty($cats[$index]['id'])) {
                        $indexName = 'cat' . ($index + 1);
                        $$indexName = $cats[$index]['id'];
                    }
                }
            }

            //5.

            $insertSQL = "Insert into Line_Item_category_info (LIC_DOC_TYPE,
                            LIC_DOC_ID, LIC_DOC_LI_NUM, LIC_BUYER_ID, LIC_SUPPLIER_ID,
                            LIC_CAT_1, LIC_CAT_2, LIC_CAT_3,LIC_NORM_VALUE) values (
                            :docType, :docId, :lineNum, :buyer, :supplier, :cat1, :cat2, :cat3, :val
                            )";
            $params = array(
                'docType' => 'ORD',
                'docId' => $ordid,
                'lineNum' => $item['OLI_ORDER_LINE_ITEM_NO'],
                'buyer' => $item['ORD_BYB_BUYER_BRANCH_CODE'],
                'supplier' => $item['ORD_SPB_BRANCH_CODE'],
                'cat1' => $cat1,
                'cat2' => $cat2,
                'cat3' => $cat3,
                'val' => $item['NORMVALUE'],
            );
            for ($index1 = 0; $index1 < count($multiplier); $index1++) {
                $queryResult = $reporting->query($insertSQL, $params);
            }
        }
    }

    private function generateCategoryArray($docType, $docId, $lineNumber, $db = "") {
        //First, get the keywords from the DB
        $lineItemQuery = "SELECT *
                            FROM line_item_keywords_cache c,
                              line_item_keyword_histogram h
                            WHERE c.lik_keyword    = h.lih_keyword
                            AND c.lik_doc_id       = 7236509
                            AND c.lik_li_number    = 1
                            AND c.lik_keyword NOT IN
                              (SELECT match_stopwords.msw_word FROM match_stopwords)
                            ";
        $results = $db->fetchAll($lineItemQuery);
        $histogram = array();
        foreach ($results as $result) {
            //  $histogram = unserialize($result['LIH_HISTOGRAM']);
            //$histogram = array_merge($histogram, unserialize($result['LIH_HISTOGRAM']));
            foreach (unserialize($result['LIH_HISTOGRAM']) as $key => $value) {
                $normVal = round($value / $result['LIH_COUNT'], 2);
                $histogram[$key] += $normVal;
            }
        }

        $copy = $histogram;
        foreach ($copy as $cat => $elem) {
            $id = preg_replace('/[^0-9]/', '', $cat);
            $sql = "Select scf_normaliser_value from supplier_cat_freq_histogram where scf_category_id = :cat_id";
            $param = array('cat_id' => (int) $id);

            $val = $db->fetchOne($sql, $param);

            if ($val) {
                $histogram[$cat] = $elem * sqrt($val);
            }
        }

        arsort($histogram);
        return $histogram;
    }

    private function storeTagsInDB($arrTags, $docType, $docId, $docLI, $db) {

        foreach ($arrTags as $tag) {
            $keyword = $tag['tag'];
            $sql = "Insert into Line_Item_keywords_Cache (LIK_DOC_TYPE, LIK_DOC_ID, LIK_LI_NUMBER, LIK_KEYWORD) values (:docType, :docId,:liNum,:keyword)";
            $params = array(
                'docType' => $docType,
                'docId' => $docId,
                'liNum' => $docLI,
                'keyword' => $keyword
            );

            $result = $db->query($sql, $params);
        }
    }

    private function getSupplierCategories($tnid, $db) {
        $sql = "Select id from Product_Category where id in (Select Product_category_id from Supply_category where Supplier_Branch_Code = :tnid)";
        $params = array('tnid' => $tnid);

        $results = $db->fetchAll($sql, $params);
        return $results;
    }

    public function indexImpaCatalogue() {
        /**
          1. Fetch first set of records where we have a mapping
          2. Brill parse
          3. Assign Mapped IMPA -> Pages category to keyword
          4. Save to DB.

         */
        $db = Shipserv_Helper_Database::getDb();
        $reporting = Shipserv_Helper_Database::getSsreport2Db();

        $startingIMPA = 0;
        $sql = "Select * from Match_Impa_pages_Mapping where MIP_IMPA_ID = $startingIMPA";

        $helper = new Shipserv_Helper_Pattern();

        $nextRecord = $db->fetchRow($sql);
        $currentIMPA = trim($nextRecord['MIP_IMPA_ID']);
        do {

            $IMPASQL = "Select * from Pages_CAtalogue_impa  where PCI_PART_NO like '$currentIMPA%'";

            $records = $db->fetchAll($IMPASQL);

            foreach ($records as $record) {
                //Brill parse and store to DB.
                $string = $record['PCI_DESCRIPTION'] . '  >>>  ' . $record['PCI_EXPLANATION'];
                $nouns = $helper->brillParse($string);

                $bigrams = $helper->extractAdjectiveNounPairs($string, 2);
                $trigrams = $helper->extractAdjectiveNounPairs($string, 3);
                $tags = array_merge($nouns, array_keys($bigrams), array_keys($trigrams));

                foreach ($tags as $tag) {
                    $save = "INSERT into MATCH_IMPA_KEYWORDS (MIK_KEYWORD, MIK_CATEGORY_ID) values (:phrase, :category)";
                    $params = array('category' => $nextRecord['MIP_PAGES_CATEGORY_ID'], 'phrase' => $tag);
                    $db->query($save, $params);
                }
            }

            $nextSQL = "Select * from Match_Impa_Pages_Mapping where MIP_IMPA_ID in (Select MIN(MIP_IMPA_ID) from Match_Impa_Pages_Mapping where MIP_IMPA_ID > $currentIMPA)";
            $nextRecord = $db->fetchRow($nextSQL);
            $currentIMPA = trim($nextRecord['MIP_IMPA_ID']);
        } while (is_array($nextRecord));
    }

}

