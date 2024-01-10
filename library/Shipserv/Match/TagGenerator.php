<?php

class Shipserv_Match_TagGenerator extends Shipserv_Object {
    const
        // @todo: it's possible one delimiter is enough, so far it's just legacy strings moved to constants, not minimised / optimised
        CONCATENATE_DELIMITER_SUBJ  = ' / / / / / / / / / / ',
        CONCATENATE_DELIMITER_ITEM  = ' > > > > > ',
        CONCATENATE_DELIMITER_ITEM2 = ' / / / / / / ',
        CONCATENATE_DELIMITER_CAT   = ' / / / / / / / / '
    ;

    private $tagIsInProfileMultiplier = 1.2;

    private $cache;
    private $db;

    /** @var Shipserv_Helper_Pattern */
    public $helper;

    /**
     * @param   Zend_Db_Adapter_Oracle $db
     */
    function __construct(Zend_Db_Adapter_Oracle $db = null) {
        if (is_null($db)) {
            $db = $this->getDb();
        }
        $this->db = $db;

        $this->cache = new Shipserv_Match_MatchCache($db);
        $this->helper = new Shipserv_Helper_Pattern();
    }

    /**
     * Helper function which merges tags (found on another step of RFQ tokenisation) into the resulting list of tags
     *
     * Refactored by Yuriy Akopov on 2013-08-30, S7028
     *
     * @param array $primary    array of tags to merge into
     * @param array $secondary  array of search terms to merge in (can be tags, categories, brands)
     * @param int   $minScore
     *
     * @return array
     */
    public function mergeToArray(array $primary, $secondary, $minScore = 10) {
        if (!is_array($secondary) or (count($secondary) === 0)) {
            return $primary;
        }

        // when one-dimensional array is supplied, make it two-dimensional with a single element
        if (!is_array($secondary[0])) {
            $secondary = array($secondary);
        }

        $indices = $this->mapKeyIndices($primary, Shipserv_Match_Match::TERM_TAG, false);

        foreach ($secondary as $sItem) {
            if ($sItem[Shipserv_Match_Match::TERM_SCORE] < $minScore) {
                continue;
            }

            $tagsAdded = $sItem[Shipserv_Match_Match::TERM_TAG];
            if (!is_array($tagsAdded)) {
                // if $secondary is tags, there will be one tag per item, with categories and brands can be more than one
                $tagsAdded = array($tagsAdded);
            }

            foreach ($tagsAdded as $tag) {
                $tag = strtolower($tag);

                if (array_key_exists($tag, $indices)) {
                    // tag already in the list, increase its score
                    $existingIndex = $indices[$tag];
                    $primary[$existingIndex][Shipserv_Match_Match::TERM_SCORE] += $sItem[Shipserv_Match_Match::TERM_SCORE];
                } else {
                    // adding a new item into the source array
                    $primary[] = array(
                        Shipserv_Match_Match::TERM_TAG      => $tag,
                        Shipserv_Match_Match::TERM_SCORE    => $sItem[Shipserv_Match_Match::TERM_SCORE]
                    );
                    end($primary);

                    $indices[$tag] = key($primary);
                }
            }
        }

        return $primary;
    }

    /**
     * Helper function similar
     * Returns an array where keys are values of keys supplied and values are indices in the source arrays these values were found at
     *
     * @param   array   $tags
     * @param   string  $key
     * @param   bool    $caseSensitive
     * @param   bool    $supportMultipleIndices
     *
     * @return  array
     */
    protected function mapKeyIndices(array $tags, $key = Shipserv_Match_Match::TERM_TAG, $caseSensitive, $supportMultipleIndices = false) {
        $result = array();

        foreach ($tags as $index => $t) {
            $value = $t[$key];

            if (!$caseSensitive) {
                $value = strtolower($value);
            }

            if ($supportMultipleIndices) {
                if (!is_array($result[$value])) {
                    $result[$value] = array();
                }
                $result[$value][] = $index;
            } else {
                $result[$value] = $index;
            }
        }

        return $result;
    }

    /**
     * Searches an array for a string passed in $tag, if it exists, return the index, false otherwise.
     *
     * Refactored by Yuriy Akopov on 2013-08-30, S7028
     *
     * @param   array   $array
     * @param   string  $tag
     *
     * @return int|boolean
     */
    protected function searchTagArray($array, $tag) {
        if (!is_array($array) or (count($array) === 0)) {
            return false;
        }

        foreach ($array as $index => $item) {
            if (strtolower($item[Shipserv_Match_Match::TERM_TAG]) === strtolower($tag)) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Function that will accept an array of text items (e.g. Subject and Body of email) with optional
     * weightings for them. This will perform the brill parsing, brand, category and part number
     * parsing and retrn an array of tags.
     *
     * @param Array $arrTextDetails : An array of format array('text' => 'abc', 'weight' (optional) => 1);
     */
    public function generateTagsFromText($arrTextDetails) {
        if (is_array($arrTextDetails)) {
            //$helper = new Shipserv_Helper_Pattern();
            $textTags = array();
            $deferCount = 0;
            $brandsOutput = array();

            $nouns = array();
            $brands = array();
            $tags = array();
            $categories = array();

            foreach ($arrTextDetails as $textToParse) {
                if (!empty($textToParse['weight'])) {
                    $weight = $textToParse['weight'];
                } else {
                    $weight = 1;
                }

                $text = $textToParse['text'];
                //===================
                //Brands
                //===================
                //if($brands){
                $tmpBrands = $this->helper->parseBrands($text);
                $brands = array_merge($brands, $tmpBrands);

                //}
                //===================
                //Categories
                //===================
                //if($categories){
                $tmpCategories = $this->helper->parseCategories($text);
                $categories = array_merge($categories, $tmpCategories);
                unset($tmpCategories);
                //}
                //===================
                //Tags themselves!
                //===================
                //if($tags){
                //Singles
                $tmpNouns = $this->helper->brillParse($text);
                $tmpNouns = $this->dedupeTagArray($tmpNouns);
                $nouns = $this->mergeToArray($nouns, $tmpNouns);
                //Bigrams
                $tmpPairs = $this->helper->extractAdjectiveNounPairs($text, 2);
                //Trigrams
                $tmpTrips = $this->helper->extractAdjectiveNounPairs($text, 3);
                //$nouns = array_merge($tags, $tmpTags);

                $arrayCount = count($tmpPairs);
                if ($arrayCount > 0) {
                    foreach ($tmpPairs as $pair => $value) {
                        $split_pair = explode(' ', $pair);
                        if (!$this->helper->isStopWord($split_pair[0]) && !$this->helper->isStopWord($split_pair[1])) {
                            $score = $this->getCombinedScore($pair, $nouns) + 75;
                            $pairs_set[] = array('tag' => $pair, 'score' => $score);
                        }
                    }


                    $pairCount = 0;
                    foreach ($pairs_set as $pair) {
                        //Find out if its part of a trip if so remove it.
                        if (is_array($tmpTrips)) {
                            foreach ($tmpTrips as $trip => $tValue) {
                                $pos = strpos($trip, $pair['tag']);
                                if ($pos !== false) {
                                    //unset($pairs_set[$pairCount]);
                                }
                            }
                        }
                        $pairCount++;
                    }


                    // $pairs_set = $this->dedupeTagArray($pairs_set);

                    $tags = $this->mergeToArray($tags, $pairs_set, 5);
                    unset($pairs_set);
                }


                //Now handle the trigrams
                $arrayCount = count($tmpTrips);
                if ($arrayCount > 0) {
                    foreach ($tmpTrips as $trip => $value) {
                        $split_trip = explode(' ', $trip);
                        if (!$this->helper->isStopWord($split_trip[0]) && !$this->helper->isStopWord($split_trip[1]) && !$this->helper->isStopWord($split_trip[2])) {
                            $score = $this->getCombinedScore($trip, $nouns) + 150;
                            $trips_set[] = array('tag' => $trip, 'score' => $score);
                        }
                    }
                    //$trips_set = $this->dedupeTagArray($trips_set);
                    $tags = $this->mergeToArray($tags, $trips_set, 5);
                    unset($trips_set);
                }

                $tags = $this->mergeToArray($tags, $nouns);


                //}
                //===================
                //Part Numbers
                //===================
                //1. Strip Measurements
                $tmpText = $this->helper->stripMeasurements($text);
                $tmpParts = $this->helper->parsePartNumbers($tmpText);
                if (count($tmpParts) > 0) {
                    $this->matchOccurences($tmpBrands, $tmpParts, $tmpText, $this->helper);
                    $tags = $this->mergeToArray($tags, $tmpParts);
                }
                //$tags = array_merge($tags, );

                unset($tmpBrands);
            }
            $returnArray = array('tags' => $tags, 'brands' => $brands, 'categories' => $categories);
        }
        return $returnArray;
    }

    /**
     * Helper function, returns line item text fields concatenated in preparation for parsing
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param array $item
     *
     * @return string|null
     */
    protected function flattenLineItem(array $item) {
        $fieldsToAdd = array(
            'RFL_CONFG_DESC',
            'RFL_COMMENTS',
            'RFL_PRODUCT_DESC',
            'RFL_CONFG_NAME'
        );

        $elements = array();
        foreach($fieldsToAdd as $field) {
            if (strlen($item[$field])) {
                $elements[] = $item[$field];
            }
        }

        if (count($elements) === 0) {
            return null;
        }

        $flatItem = implode(self::CONCATENATE_DELIMITER_CAT, $elements);
        return $flatItem;
    }

    /**
     * Helper function, returns every line item text fields concatenated in preparation for parsing
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param   array   $items
     *
     * @return string|null
     */
    protected function flattenAllLineItems(array $items) {
        if (count($items) === 0) {
            return null;
        }

        $lines = array();
        foreach ($items as $item) {
            $str = $this->flattenLineItem($item);

            if (!is_null($str)) {
                $lines[] = $str;
            }
        }

        if (count($lines) === 0) {
            return null;
        }

        $flatItems = implode(self::CONCATENATE_DELIMITER_CAT, $lines);
        return $flatItems;
    }

    /**
     * Helper function, returns RFQ direct text fields concatenated in preparation for parsing
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param   array   $rfqText
     *
     * @return null|string
     */
    protected function flattenRfq(array $rfqText) {
        $fieldsToAdd = array(
            'RFQ_SUBJECT',
            'RFQ_COMMENTS'
        );

        $elements = array();
        foreach($fieldsToAdd as $field) {
            if (strlen($rfqText[$field])) {
                $elements[] = $rfqText[$field];
            }
        }

        if (count($elements) === 0) {
            return null;
        }

        $comments = implode(self::CONCATENATE_DELIMITER_SUBJ, $elements);
        return $comments;
    }

    /**
     * Returns brands recognised in given RFQ properties
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param   array   $listItems
     * @param   array   $rfqText
     *
     * @return  array
     */
    protected function extractBrandsFromRfq(array $listItems, array $rfqText) {
        $strVagueBrandsRemoved = $this->flattenRfq($rfqText);
        $strVagueBrandsAllowed = '';
        foreach ($listItems as $li) {
            foreach ($li as $field => $value) {
                if ($field === 'RFL_CONFG_MANUFACTURER') {
                    $strVagueBrandsAllowed .= self::CONCATENATE_DELIMITER_CAT . $value;
                } else {
                    $strVagueBrandsRemoved .= self::CONCATENATE_DELIMITER_CAT . $value;
                }
            }
        }

        $brands = array();
        // first searching for brands with 'vague restriction' enabled
        $parsedBrands = $this->helper->parseBrands($strVagueBrandsRemoved, false, true);
        foreach ($parsedBrands as $brand) {
            $brands[$brand[Shipserv_Match_Match::TERM_ID]] = $brand;
        }

        // now searching for brands with 'vague restriction' disabled'
        $parsedBrands = $this->helper->parseBrands($strVagueBrandsAllowed, true, true);
        foreach ($parsedBrands as $brand) {
            // if the brand was found before, do not duplicate but update its score to the highest of the two
            if (array_key_exists($brand[Shipserv_Match_Match::TERM_ID], $brands)) {
                if ($brands[$brand[Shipserv_Match_Match::TERM_ID]][Shipserv_Match_Match::TERM_SCORE] < $brand[Shipserv_Match_Match::TERM_SCORE]) {
                    $brands[$brand[Shipserv_Match_Match::TERM_ID]][Shipserv_Match_Match::TERM_SCORE] = $brand[Shipserv_Match_Match::TERM_SCORE];
                }
            } else {
                $brands[$brand[Shipserv_Match_Match::TERM_ID]] = $brand;
            }
        }

        foreach ($brands as $brandId => $brand) {
            $brands[$brandId][Shipserv_Match_Match::TERM_SCORE] = 500 * min($brand[Shipserv_Match_Match::TERM_SCORE], 3);
        }

        return array_values($brands);
    }

    /**
     * Helper function to perform array_count_values against the two-dimensional arrays
     *
     * @author  Yuriy Akopov
     * @date    2013-08-20
     * @story   S7028
     *
     * @param   array   $source
     * @param   string  $key
     * @param   bool    $caseSensitive
     *
     * @return  array
     * @throws  Exception
     */
    protected function countOccasions(array $source, $key, $caseSensitive) {
        $result = array();

        foreach ($source as $subArray) {
            if (!is_array($subArray) or !array_key_exists($key, $subArray)) {
                throw new Exception('Invalid array supplied for counting "' . $key . '" values in it');
            }

            $value = $subArray[$key];
            if (!$caseSensitive) {
                $value = strtolower($value);
            }

            if (array_key_exists($value, $result)) {
                $result[$value]++;
            } else {
                $result[$value] = 1;
            }
        }

        return $result;
    }

    /**
     * Helper function for extractNounTagsFromRfq as we're running PHP 5.2 and therefore can't use closures
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param   array   $pairs
     * @param   array   $nouns
     * @param   int     $wordCount  number of words in pairs (2 or 3)
     * @param   int     $baseScore
     *
     * @return array
     */
    protected function processPairTags(array $pairs, array $nouns, $wordCount, $baseScore) {
        if (count($pairs) === 0) {
            return array();
        }

        $pairsSet = array();

        foreach ($pairs as $pair => $value) {
            $splitPair = explode(' ', $value);
            for ($i = 0; $i < min($wordCount, count($splitPair)); $i++) {
                if ($this->helper->isStopWord($splitPair[$i])) {
                    continue;
                }
            }

            $score = $this->getCombinedScore($pair, $nouns) + $baseScore;
            $pairsSet[] = array(
                Shipserv_Match_Match::TERM_TAG   => $pair,
                Shipserv_Match_Match::TERM_SCORE => $score
            );
        }

        // commented out as it does nothing
        /*
        $pairCount = 0;
        foreach ($pairs_set as $pair) {
            //Find out if its part of a trip if so remove it.
            foreach ($trips as $trip => $tValue) {
                $pos = strpos($trip, $pair['tag']);
                if ($pos !== false) {
                    //unset($pairs_set[$pairCount]);    // @todo: this was commented out in the legacy code
                }
            }
            $pairCount++;
        }
        */

        return $pairsSet;
    }

    /**
     * @param   array $listItems
     * @param   array $nouns
     *
     * @return  array
     */
    protected function extractNounPairsFromRfq(array $listItems, array $nouns) {
        $concatDesc = $this->flattenAllLineItems($listItems);
        if (is_null($concatDesc)) {
            return array();
        }

        //N-gram processing, taking valid 2 & 3 word pairs that contain nouns
        // 2-word pairs
        $pairs      = $this->helper->extractAdjectiveNounPairs($concatDesc, 2);
        $pairTags   = $this->processPairTags($pairs, $nouns, 2, 75);
        // 3-word pairs
        $trips      = $this->helper->extractAdjectiveNounPairs($concatDesc, 3);
        $tripTags   = $this->processPairTags($trips, $nouns, 3, 150);

        return array_merge($pairTags, $tripTags);
    }

    /**
     * Helper function that extracts nouns and their combinations and adds them to the list of tags to search for
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param   array   $listItems
     *
     * @return  array
     */
    protected function extractNounTagsFromRfq(array $listItems) {
        $concatDesc = $this->flattenAllLineItems($listItems);
        if (is_null($concatDesc)) {
            return array();
        }

        $nouns = $this->helper->brillParse($concatDesc);
        $nouns = $this->dedupeTagArray($nouns);

        return $nouns;
    }

    /**
     * Returns tags recognised in given RFQ properties
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param array $rfqText
     *
     * @return array
     */
    protected function extractTagsFromRfq(array $rfqText) {
        // @todo: the below block used to be executed only when $listItems was not empty (check now removed)
        $rawTags = $this->helper->brillParse(implode(self::CONCATENATE_DELIMITER_SUBJ, $rfqText));
        $tags = $this->dedupeTagArray($rawTags);

        // check if the Subject has too many spaces, if not, add it as a tag
        $rfqSubj = $rfqText['RFQ_SUBJECT'];
        if (strlen($rfqSubj) and (substr_count($rfqSubj, ' ') <= 4)) { // @todo: used to be always empty as was referenced as [0]['RFQ_SUBJECT'] in the block, also needle hasn't been specified
            $subjTag    = strtolower($rfqSubj);
            $subjScore  = 300;

            $subjTagFound = false;

            foreach ($tags as $index => $tag) {
                if ($tag[Shipserv_Match_Match::TERM_TAG] == $subjTag) {
                    $tags[$index][Shipserv_Match_Match::TERM_SCORE] += $subjScore;
                    $subjTagFound = true;
                    break;
                }
            }

            if (!$subjTagFound) {
                $tags[] = array(
                    Shipserv_Match_Match::TERM_TAG   => $subjTag,
                    Shipserv_Match_Match::TERM_SCORE => $subjScore
                );
            }
        }

        /*
        if (count($tags) === 0) {
            return array();
        }
        foreach ($tags as $index => $item) {
            if (is_numeric($item)) {
                unset($tags[$index]);
            }
        }
        */

        return $tags;
    }


    /**
     * Returns categories recognised in given RFQ properties
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param array $listItems
     *
     * @return array
     */
    public function extractCategoriesFromRfq(array $listItems) {
        if (empty($listItems)) {
            return array();
        }

        $flattenedItems = $this->flattenAllLineItems($listItems);
        $concatDesc = $this->helper->stripMeasurements($flattenedItems);

        if (strlen($concatDesc) <= 4) {
            return array();
        }

        $categories = $this->helper->parseCategories($concatDesc);

        return $categories;
    }

    /**
     * Extract part numbers fro RFQ line items and direct fields and adds them as tags to search for
     *
     * Separated into a dedicated function and refactored by Yuriy Akopov on 2013-08-27, S7028
     *
     * @param   array   $brands
     * @param   array   $lineItems
     * @param   array   $rfqText
     *
     * @return  array
     */
    protected function extractPartTagsFromRfq(array $brands, array $lineItems, array $rfqText) {
        $parts = array();
        $allParts = array();
        //part no;

        $comments = $this->flattenRfq($rfqText);
        if (!is_null($comments)) {
            // @todo: line below added as it doesn't make sense otherwise
            $commentParts = $this->helper->parsePartNumbers($comments);
            if ($commentParts !== false) {
                $parts = $this->mergeToArray($parts, $commentParts);
            }
        }

        // now parse line items for part numbers as part numbers are not always found in the part number field only

        $types = array(
            'MF',
            'ZIM',
            'VP',
            'ZIS',
            'ZMA',
            'UP',
            'EN'
        );

        $typesParse = array(
            'MF',
            'ZIM',
            'VP'
        );

        $fields = array(
            'RFL_PRODUCT_DESC',
            'RFL_CONFG_MODEL_NO',
            'RFL_COMMENTS',
            'RFL_CONFG_DESC'
        );

        foreach ($lineItems as $item) {
            if (in_array($item['RFL_ID_TYPE'], $types)) {
                if (!empty($item['RFL_ID_CODE'])) {                         // @todo: this and other checks below also dismiss '0', is that intended?
                    if (in_array($item['RFL_ID_TYPE'], $typesParse)) {
                        $partTag = $this->helper->parsePartNumbers($item['RFL_ID_CODE']);
                        $parts = $this->mergeToArray($parts, $partTag, 150);
                    } else {
                        $partTag = array(
                            Shipserv_Match_Match::TERM_TAG      => $item['RFL_ID_CODE'],
                            Shipserv_Match_Match::TERM_SCORE    => 300
                        );
                        $parts = $this->mergeToArray($parts, $partTag);
                    }
                }
            }

            // @todo: why searching fields individually if still searhing concatenated string below?
            $elements = array();
            foreach ($fields as $field) {
                if (!empty($item[$field])) {
                    $parts = $this->mergeToArray($parts, $this->helper->parsePartNumbers($item[$field]));
                    $elements[] = $item[$field];
                }
            }

            if (count($parts) > 0) {
                $concat = implode(self::CONCATENATE_DELIMITER_CAT, $elements);
                $this->matchOccurences($brands, $parts, $concat, $this->helper);

                //TODO: Find word proximity of parts to brands/categories.
                $allParts = $this->mergeToArray($allParts, $parts);
            }
        }

        return $allParts;
    }

    /**
     * Parses an RFQ into brands, categories and terms to be used as incoming search parameters
     *
     * Refactored by Yuriy Akopov on 2013-08-28, S7028
     * Support for location terms added on 2013-03-14, S9764
     *
     * @param   Shipserv_Tradenet_RequestForQuote  $rfq
     *
     * @return  array
     */
    public function generateTagsFromRFQ(Shipserv_Tradenet_RequestForQuote $rfq) {
        $rfqID = $rfq->getInternalRefNo();

        $rfqTags = array();  // an array to house our collection of tags

        ///////////////////////////
        // loading data to analyse:
        $paramsArray = array('rfq' => $rfqID);

        // loading RFQ line items text fields
        $liSql = "
          SELECT
            RFL_PRODUCT_DESC,
            RFL_CONFG_MANUFACTURER,
            rfl_comments,
            RFL_CONFG_NAME,
            RFL_CONFG_DESC,
            RFL_ID_TYPE,
            RFL_ID_CODE,
            rfl_confg_model_no,
            rfl_confg_serial_no,
            rfl_confg_drawing_no
          FROM
            rfq_line_item
          WHERE
            rfl_rfq_internal_ref_no = :rfq
        ";
        $qli_items = $this->db->fetchAll($liSql, $paramsArray);

        // loading RFQ text fields
        $sql = "
          SELECT
            rfq_subject,
            rfq_comments
          FROM
            request_for_quote
          WHERE
            rfq_internal_ref_no = :rfq
        ";
        $q_comments = $this->db->fetchRow($sql, $paramsArray);

        ///////////////////////////////////////////////////////////////////////////////////////
        // Processing loaded RFQ content - breaking it down into search terms of different kind

        // extracting tags from RFQ content
        $tags       = $this->extractTagsFromRfq($q_comments);
        $rfqTags    = $this->mergeToArray($rfqTags, $tags);

        // extracting tags from RFQ line items
        // nouns
        $nouns      = $this->extractNounTagsFromRfq($qli_items);
        $rfqTags    = $this->mergeToArray($rfqTags, $nouns);
        // words adjacent to nouns
        $pairs      = $this->extractNounPairsFromRfq($qli_items, $nouns);
        $rfqTags    = $this->mergeToArray($rfqTags, $pairs, 5);

        // extracting brands and categories
        $brands     = $this->extractBrandsFromRfq($qli_items, $q_comments);
        $categories = $this->extractCategoriesFromRfq($qli_items);
        // no more merging their tags here because users should not see that noise - do it right before searching instead
        // $rfqTags    = $this->mergeToArray($rfqTags, $brands);
        //$rfqTags    = $this->mergeToArray($rfqTags, $categories, 0);    // categories come with low scores, so setting 0 threshold

        // extracting part number tags
        $allParts   = $this->extractPartTagsFromRfq($brands, $qli_items, $q_comments);
        $rfqTags    = $this->mergeToArray($rfqTags, $allParts, 5);

        /////////////////////////////////////
        // continuing with extracting tags...

        // disabled on 2013-11-27 as the data we have in model number isn't clean enough
        /*
        foreach ($rfqTags as $index => $value) { // @todo: shouldn't we only traverse $allParts here instead of all tags?
            // check if the part number matches anything in the supply_brand_model table and add that brand into brands array.
            $sql = "
                SELECT
                  b.*
                FROM
                  brand b
                  JOIN supply_brand sb ON
                    sb.brand_id = b.id
                  JOIN supply_brand_model sm ON
                    sm.sbm_sbr_id = sb.sbr_id
                    AND sm.sbm_number = :partNo
            ";
            $partBrands = $this->db->fetchAll($sql, array(
                'partNo' => $value[Shipserv_Match_Match::TERM_TAG]   // @todo: was probably always empty before ($value['taga']) instead of 'tag'
            ));

            if (count($partBrands) === 0) {
                continue;
            }

            foreach ($partBrands as $key => $brand) {
                $newBrand = true;
                // check if the brand found was already recovered earlier
                foreach ($brands as $existingKey => $existingBrand) {
                    if (
                        ($existingBrand[Shipserv_Match_Match::TERM_ID] == $brand['ID']) or
                        (strtolower($existingBrand[Shipserv_Match_Match::TERM_NAME]) == strtolower($brand['NAME']))
                    ) {
                        // update existing brand's score
                        $brands[$existingKey][Shipserv_Match_Match::TERM_SCORE] = 1000;
                        $newBrand = false;
                        break;
                    }
                }

                if ($newBrand) {
                    // add matched brand to the brands list
                    $brandToAdd = self::brandToTag($brand);
                    $brandToAdd[Shipserv_Match_Match::TERM_SCORE]  = 1000;
                    $brands[] = $brandToAdd;
                }

                // add the matched brand as a tag as well
                $rfqTags = $this->mergeToArray($rfqTags, array(
                    Shipserv_Match_Match::TERM_TAG   => strtolower($brand['NAME']),    // @todo: in the legacy code it was strtolower($key), but shouldn't it be $brand? key is numeric here
                    Shipserv_Match_Match::TERM_SCORE => 100
                ), 0);
            }

            // remove tag which first or last character is not alphanumeric
            // @todo: that doesn't work for tags added from brands as we're looping over a copy of an array - was that intended?
            if (
                !preg_match('#[a-z0-9]#i', $value[Shipserv_Match_Match::TERM_TAG][0]) and
                !preg_match('#[a-z0-9]#i', $value[Shipserv_Match_Match::TERM_TAG][strlen($value[Shipserv_Match_Match::TERM_TAG]) - 1])
            ) {
                unset($rfqTags[$index]);
            }
        }
        */

        // add recovered keywords to database for future reference
        foreach ($rfqTags as $value) {
            try {
                $tag = $value[Shipserv_Match_Match::TERM_TAG];
                if (!preg_match('/\s/', $tag)) {
                    $sql = "
                        MERGE INTO MATCH_KEYWORD_FREQUENCY USING DUAL ON
                            (mkf_keyword= :keyword)
                        WHEN MATCHED THEN
                          UPDATE SET mkf_frequency = mkf_frequency + 1
                        WHEN NOT MATCHED THEN
                          INSERT (mkf_keyword, mkf_frequency) VALUES (:keyword2 , 1)
                        ";
                    $this->db->query($sql, array(
                        'keyword'   => $tag,
                        'keyword2'  => $tag
                    ));
                }
            } catch (Exception $ex) {
            }
        }

        Shipserv_Match_Match::aasort($rfqTags, Shipserv_Match_Match::TERM_SCORE);


        $pattern = new Shipserv_Helper_Pattern();
        // added by Yuriy Akopov to exclude empty tags as that was the case for some RFQs
        // @todo: the problem needs to be investigated and resolved at the point where empty tags are generated
        foreach ($rfqTags as $key => $tag) {
            if (strlen($tag[Shipserv_Match_Match::TERM_TAG]) === 0) {
                unset($rfqTags[$key]);
                continue;
            }

            if ($pattern->isStopWord($tag[Shipserv_Match_Match::TERM_TAG])) {
                unset($rfqTags[$key]);
            }
        }
        // hotfix changes end here

        $ssRfq = Shipserv_Rfq::getInstanceById($rfqID);
        $locations = $this->extractLocationsFromRfq($ssRfq);

        return array(
            Shipserv_Match_Match::TERM_TYPE_TAGS        => $rfqTags,
            Shipserv_Match_Match::TERM_TYPE_BRANDS      => $brands,
            Shipserv_Match_Match::TERM_TYPE_CATEGORIES  => $categories,
            Shipserv_Match_Match::TERM_TYPE_LOCATIONS   => $locations
        );
    }

    /**
     * N-Grams contain words that may be frequent elsewhere, this will generate a score for an N-Gram based on a combined score of all the words
     * @todo: to be re-written to support any combinations, not only 2- and 3-word pairs
     *
     *
     * Refactored by Yuriy Akopov on 2013-08-28
     *
     * @param   string  $pairString
     * @param   array   $nouns
     *
     * @return  float
     */
    private function getCombinedScore($pairString, $nouns) {
        $split = explode(' ', $pairString);

        $scoreA = $scoreB = $scoreC = null;

        foreach ($nouns as $item) {
            if ($item[Shipserv_Match_Match::TERM_TAG] == strtolower(trim($split[0]))) {
                $scoreA = round($item[Shipserv_Match_Match::TERM_SCORE] * .66);
            } elseif ($item[Shipserv_Match_Match::TERM_TAG] == strtolower(trim($split[1]))) {
                $scoreB = round($item[Shipserv_Match_Match::TERM_SCORE] * .66);
            }

            if (count($split) == 3) {
                if ($item[Shipserv_Match_Match::TERM_TAG] == strtolower(trim($split[2]))) {  // @todo: $split[0] replaced with $split[3]? 3 replaced with 2 on 2014-05-20
                    $scoreC = round($item[Shipserv_Match_Match::TERM_SCORE] * .66);
                }
            }

            if (!is_null($scoreA) and !is_null(!$scoreB)) {
                if (!is_null($scoreC)) {
                    return $scoreA + $scoreB + $scoreC;
                } else {
                    return $scoreA + $scoreB;
                }
            }
        }

        // will be either 0 or $scoreA
        return $scoreA + $scoreB;
    }

    /**
     *
     * @param type $supplierID
     * @param type $db
     * @return type
     */
    private function getSupplierBrands($supplierID, $db) {
        $sql = "Select brand.name from Supply_brand, brand where brand.id =  supply_brand.brand_id and supply_brand.supplier_branch_code = :tnid";
        $params = array('tnid' => $supplierID);

        $results = $this->db->fetchAll($sql, $params);
        foreach ($results as $result) {
            $brands[] = array('tag' => $result['NAME'], 'score' => 20);
        }

        return $brands;
    }

    /**
     *
     * @param type $supplierID
     * @param type $db
     * @return type
     */
    public function getSupplierBrandsArray($supplierID, $db) {
        $sql = "SELECT brand.name FROM Supply_brand, brand WHERE brand.id =  supply_brand.brand_id AND supply_brand.supplier_branch_code = :tnid";
        $params = array('tnid' => $supplierID);

        $results = $this->db->fetchAll($sql, $params);

        foreach ($results as $result) {
            $supplierBrands[] = array('tag' => $result['NAME'], 'score' => 100);
        }

        return $results;
    }

    /**
     *
     * @param type $supplierID
     * @param type $db
     * @return int
     */
    public function getSupplierCategories($supplierID, $db) {
        $sql = "Select Name, Keywords_comma_separated from Product_Category where id in (Select Product_category_id from Supply_category where Supplier_Branch_Code = :tnid)";
        $params = array('tnid' => $supplierID);

        $returnArr = array();

        $helper = $this->helper;

        $results = $this->db->fetchAll($sql, $params);
        foreach ($results as $result) {
            //If its a section head with multiple categories.
            if (strpos($result['NAME'], "&") > 0) {
                $returnArr[] = trim($result['NAME']);
            } else {
                $returnArr[] = $helper->singularise(trim($result['NAME']));
            }
            $returnArr[] = $helper->singularise(trim($result['NAME']));
            if (!empty($result['KEYWORDS_COMMA_SEPARATED'])) {
                $exp = explode(',', $result['KEYWORDS_COMMA_SEPARATED']);
                foreach ($exp as $tag) {
                    $returnArr[] = $helper->singularise(trim($tag));
                }
            }
        }
        $retArr = array_unique($returnArr);
        foreach ($retArr as $item) {
            if (trim($item) != '') {
                $formattedArray[] = array('tag' => $item, 'score' => 100);
            }
        }
        return $formattedArray;
    }

    /**
     * Adjust words in given arrays basing on their proximity to each other in a s
     * TODO: Take a line item, concatenate the varius cols, and look for proximities in the score array
     *
     * @param array     $arrayA
     * @param array     $arrayB
     * @param string    $string
     * @param Shipserv_Helper_Pattern $helper
     */
    private function matchOccurences(&$arrayA, &$arrayB, $string, Shipserv_Helper_Pattern $helper) {
        if ($arrayA && $arrayB) {
            $i = 0;
            foreach ($arrayA as $aItem) {
                $j = 0;
                foreach ($arrayB as $bItem) {
                    // @todo: changed by Yuriy Akopov to make sense
                    //$pScore = $helper->findWordProximity($aItem[0], $bItem[0], $string);

                    $pScore = $helper->findWordProximity($aItem[Shipserv_Match_Match::TERM_TAG], $bItem[Shipserv_Match_Match::TERM_TAG], $string);

                    //if ($pScore >= 0) {
                    if (($pScore >= 0) and ($pScore !== false)) {
                        $arrayA[$i][Shipserv_Match_Match::TERM_SCORE] = $arrayA[$i][Shipserv_Match_Match::TERM_SCORE] + (30 * (1 + ($pScore / 10)));
                        $arrayB[$j][Shipserv_Match_Match::TERM_SCORE] = $arrayB[$j][Shipserv_Match_Match::TERM_SCORE] + (30 * (1 + ($pScore / 10)));
                    }
                    $j++;
                }
                $i++;
            }
        }
    }

    /**
     * Receives a plain array of plain strings, returns multidim array containing unique tags and their scores
     *
     * @param   array   $array
     * @param   int     $multiplier
     *
     * @return array
     */
    protected function dedupeTagArray(array $array, $multiplier = 10) {
        $tagStrings = array_count_values($array);
        asort($tagStrings);

        $tagTerms = array();
        foreach ($tagStrings as $key => $value) {
            $tagTerms[] = array(
                Shipserv_Match_Match::TERM_TAG      => $key,
                Shipserv_Match_Match::TERM_SCORE    => $value * $multiplier
            );
        }
        return $tagTerms;
    }

    /**
     * Converts location information into a structure accepted by match engine as a search term
     *
     * The purpose of the function is to produce the same data structure match engine needs out of those two possible sources
     *
     * @param   array    $location
     *
     * @return  array
     * @throws  Exception
     */
    public static function locationToTag(array $location) {
        $locationInfo = array(
            Shipserv_Match_Match::TERM_ID   => $location[Shipserv_Oracle_Countries::COL_CODE_COUNTRY],
            Shipserv_Match_Match::TERM_TAG  => $location[Shipserv_Oracle_Countries::COL_NAME_COUNTRY],
            Shipserv_Match_Match::TERM_NAME => $location[Shipserv_Oracle_Countries::COL_NAME_COUNTRY]
        );

        return $locationInfo;
    }

    /**
     * Converts brand information into a structure accepted by match engine as a search term
     *
     * Brands come to match engine from two possible sources - from the ad-hoc query run on RFQ tokenisation
     * and from the front-end when user has altered the terms
     *
     * The purpose of the function is to produce the same data structure match engine needs out of those two possible sources
     *
     * @param   Shipserv_Brand|array    $brand
     *
     * @return  array
     * @throws  Exception
     */
    public static function brandToTag($brand) {
        $brandInfo = array();
        if ($brand instanceof Shipserv_Brand) {
            // brand data comes as a raw ID (e.g. from the front end)
            $brandInfo[Shipserv_Match_Match::TERM_ID]   = (int) $brand->id;
            $brandInfo[Shipserv_Match_Match::TERM_TAG]  = array($brand->getName());
            $brandInfo[Shipserv_Match_Match::TERM_NAME] = $brand->name;
        } else if (is_array($brand)) {
            // brand data comes from an ad-hoc query in RFQ tokenisation
            $brandInfo[Shipserv_Match_Match::TERM_ID]    = (int) $brand[Shipserv_Brand::COL_ID];
            $brandInfo[Shipserv_Match_Match::TERM_TAG]   = array($brand[Shipserv_Brand::COL_NAME]);
            $brandInfo[Shipserv_Match_Match::TERM_NAME]  = $brand[Shipserv_Brand::COL_NAME];
        } else {
            throw new Exception('Invalid brand structure, unable to convert to a search term');
        }

        // adding brand synonyms to the list of keywords
        $keywords = Shipserv_Brand::getAllSynonyms($brandInfo[Shipserv_Match_Match::TERM_ID]);

        $brandInfo[Shipserv_Match_Match::TERM_TAG] = array_unique(array_merge(
            $brandInfo[Shipserv_Match_Match::TERM_TAG],
            $keywords
        ));

        return $brandInfo;
    }

    /**
     * Converts category information into a structure accepted by match engine as a search term
     *
     * Categories comes to match engine from two possible sources - from the ad-hoc query run on RFQ tokenisation
     * and from the front-end when user has altered the terms
     *
     * The purpose of the function is to produce the same data structure match engine needs out of those two possible sources
     *
     * @param   Shipserv_Category|array $category
     *
     * @return  array
     * @throws  Exception
     */
    public static function categoryToTag($category) {
        $categoryInfo = array();
        if ($category instanceof Shipserv_Category) {
            // category data comes as a raw ID (e.g. from the front end)
            $categoryInfo[Shipserv_Match_Match::TERM_ID] = (int) $category->id;
            $strName = $category->name;

            try {
                $parent = $category->getParent();
                $categoryInfo[Shipserv_Match_Match::TERM_PARENT_ID] = (int) $parent->id;
                $parentName = $parent->name;

            } catch (Exception $e) {
                $categoryInfo[Shipserv_Match_Match::TERM_PARENT_ID]  = null;
                $parentName = null;
            }

        } else if (is_array($category)) {
            // category data comes from an ad-hoc query in RFQ tokenisation
            $categoryInfo[Shipserv_Match_Match::TERM_ID]         = (int) $category['ID'];
            $categoryInfo[Shipserv_Match_Match::TERM_PARENT_ID]  = (int) (strlen($category['PARENT_ID']) ? $category['PARENT_ID'] : null);

            $strName       = $category['NAME'];
            $parentName    = $category['PARENTNAME'];

        } else {
            throw new Exception('Invalid category structure, unable to convert to a search term');
        }

        // building display name
        // @todo: should be removed from this structure and be derived from category ID
        $categoryInfo[Shipserv_Match_Match::TERM_NAME] = $strName;
        if (!is_null($categoryInfo[Shipserv_Match_Match::TERM_PARENT_ID]) and strlen($parentName)) {
            $categoryInfo[Shipserv_Match_Match::TERM_NAME] .= ' (' . $parentName . ')';
        }

        $keywords = Shipserv_Category::getAllSynonyms($categoryInfo[Shipserv_Match_Match::TERM_ID]);

        // producing a structure for every tag collected from category
        $categoryInfo[Shipserv_Match_Match::TERM_TAG] = array_unique(array_merge(
            array(Shipserv_Helper_Pattern::singularise(strtolower(trim($strName)))),
            $keywords
        ));

        return $categoryInfo;
    }

    /**
     * Helper function to get RFQ buyer location
     *
     * using an ad-hoc query because in this branch we don't have Shipserv_Buyer_Branch class yet
     * @todo: replace with the smarter method when all the 4.7 branches are finally merged together
     *
     * @author  Yuriy Akopov
     * @date    2014-07-30
     * @story   S11066
     *
     * @param   Shipserv_Rfq    $rfq
     *
     * @return  string|null
     */
    public function getRfqBuyerLocation(Shipserv_Rfq $rfq) {
        $rfq = $rfq->resolveMatchForward();

        // get RFQ buyer branch location
        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                array()
            )
            // buyer branch location
            ->join(
                array('byb' => 'buyer_branch'),
                'rfq.' . Shipserv_Rfq::COL_BUYER_ID . ' = byb.byb_branch_code',
                array()
            )
            ->joinLeft(
                array('cbyb' => Shipserv_Oracle_Countries::TABLE_NAME),
                'byb.byb_country = cbyb.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY,
                array(
                    'COUNTRY_BRANCH' => 'cbyb.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY
                )
            )
            // buyer organisation location as well to fall back to it if branch location not found
            ->join(
                array('byo' => 'buyer_organisation'),
                'rfq.' . Shipserv_Rfq::COL_BUYER_ORG . ' = byo.byo_org_code',
                array()
            )
            ->joinLeft(
                array('cbyo' => Shipserv_Oracle_Countries::TABLE_NAME),
                'byo.byo_country = cbyo.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY,
                array(
                    'COUNTRY_ORG' => 'cbyo.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY
                )
            )
            ->where('rfq.' . Shipserv_Rfq::COL_ID . ' = ?', $rfq->rfqInternalRefNo)
            // pages proxy not included
            //->where('rfq.' . Shipserv_Rfq::COL_BUYER_ID . ' <> ?', Myshipserv_Config::getProxyPagesBuyer())
        ;

        $buyerLocations = $select->getAdapter()->fetchRow($select);

        $buyerCountry = null;

        if (strlen($buyerLocations['COUNTRY_BRANCH'])) {
            $buyerCountry = $buyerLocations['COUNTRY_BRANCH'];

        } else if (strlen($buyerLocations['COUNTRY_ORG'])) {
            $buyerCountry = $buyerLocations['COUNTRY_ORG'];

        } else {
            // maybe it was a pages proxy RFQ?
            try {
                $sender = $rfq->getOriginalSender();

                if ($sender instanceof Shipserv_Buyer) {
                    $buyerCountry = $sender->byoCountry; // @todo: need to validate it against the list of known countries?
                }

            } catch (Exception $e) {
                // unknown or unsupported sender, buyer location is not added to the list
            }
        }

        return $buyerCountry;
    }

    /**
     * Helper function, returns county of the RFQ delivery port, if specified
     *
     * @param   Shipserv_Rfq  $rfq
     *
     * @return  string|null|array
     */
    public function getRfqPortLocation(Shipserv_Rfq $rfq) {
        $portStr = trim($rfq->rfqDeliveryPort);

        if (strlen($portStr) === 0) {
            // no port information specified
            return null;
        }

        $db = $this->getDb();

        // check if it is a port code
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('prt' => Shipserv_Oracle_Ports::TABLE_NAME),
                'prt.' . Shipserv_Oracle_Ports::COL_COUNTRY
            )
            ->where('UPPER(prt.' . Shipserv_Oracle_Ports::COL_CODE . ') = UPPER(?)', $portStr);
        ;

        $country = $db->fetchOne($select);
        if (strlen($country)) {
            return $country;
        }

        // if we are here, there is some value in delivery port field, but it isn't a port code
        // let's check if it is a port name
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('prt' => Shipserv_Oracle_Ports::TABLE_NAME),
                'prt.' . Shipserv_Oracle_Ports::COL_COUNTRY
            )
            ->where('UPPER(prt.' . Shipserv_Oracle_Ports::COL_NAME . ') = UPPER(?)', $portStr);
        ;

        $countries = $db->fetchCol($select);
        if (!empty($countries)) {
            return $countries;
        }

        // if we are here, let's check if the value entered in the port field is a country code or name
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('cnt' => Shipserv_Oracle_Countries::TABLE_NAME),
                'cnt.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY
            )
            ->where(implode(' OR ', array(
                $db->quoteInto('UPPER(cnt.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY . ') = UPPER(?)', $portStr),
                $db->quoteInto('UPPER(cnt.' . Shipserv_Oracle_Countries::COL_NAME_COUNTRY . ') = UPPER(?)', $portStr)
            )))
        ;

        $country = $db->fetchOne($select);
        if (strlen($country)) {
            return $country;
        }

        // there is some value specified by user but we failed to recognise it
        return null;
    }

    /**
     * Searches RFQ for location information, returns the list of countries supposedly relevant to buyer
     *
     * @param   Shipserv_Rfq   $rfq
     *
     * @return  array
     */
    public function extractLocationsFromRfq(Shipserv_Rfq $rfq) {
        $timeStart = microtime(true);
        $locations = array();

        $rfq = $rfq->resolveMatchForward();

        $buyerCountry = $this->getRfqBuyerLocation($rfq);
        if (strlen($buyerCountry)) {
            $locations[$buyerCountry] = Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_BUYER_SCORE);
        }

        // finished with buyer location, now looking into port location, if specified
        // port field is a free text one so it may contain port code or port name etc.
        $portCountry = $this->getRfqPortLocation($rfq);
        if (!is_null($portCountry)) {
            if (is_array($portCountry)) {
                foreach ($portCountry as $country) {
                    $locations[$country] += ceil(Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_PORT_SCORE) / count($portCountry));
                }
            } else {
                $locations[$portCountry] += Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_PORT_SCORE);
            }
        }

        $suppliers = $rfq->getSuppliers();
        foreach ($suppliers as $supplierInfo) {
            if (!$supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_FROM_MATCH]) {
                $supplier = Shipserv_Supplier::getInstanceById($supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID], '', true);
                if (strlen($supplier->countryCode)) {
                    $locations[$supplier->countryCode] += Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_SUPPLIER_SCORE);
                }
            }
        }

        // convert our location structure optimised for searching into search terms accepted by the match engine
        $terms = array();
        foreach ($locations as $country => $score) {
            $terms[] = array(
                Shipserv_Match_Match::TERM_ID    => $country,
                Shipserv_Match_Match::TERM_SCORE => (float) $score
            );
        }

        $elapsed = microtime(true) - $timeStart;

        return $terms;
    }
}
