<?php

/*
 *
 * Class to help with pattern matching for Match by using some simple language hacks to try extract useful data like brands and part numbers
 */

class Shipserv_Helper_Pattern extends Shipserv_Object {
    /**
     * @var Shipserv_Match_MatchCache
     */
    protected $cache = null;

    /**
     * @var Shipserv_Lexicon_BrillParser
     */
    protected static $parser = null;

    /**
     * @var Shipserv_Helper_String
     */
    protected static $helper = null;

    /**
     * @var array
     */
    protected static $brands = null;

    /**
     * @var array
     */
    protected static $categories = null;

    /**
     * Array of lowercased stopwords
     *
     * Added by Yuriy Akopov on 2013-08-30, S7028
     *
     * @var array
     */
    protected static $stopWords = null;

    /**
     * Array of lowercased vague brand names
     *
     * @var null
     */
    protected static $vagueBrandNames = null;

    /**
     * Cache built up by singularise() method (words against their singular version)
     *
     * @var array
     */
    protected static $cacheSingulars = array();

    public function __construct() {
        $this->cache = new Shipserv_Match_MatchCache($this->getDb());

        if (is_null(self::$parser)) {
            self::$parser = new Shipserv_Lexicon_BrillParser();
        }

        if (is_null(self::$helper)) {
            self::$helper = new Shipserv_Helper_String();
        }
    }

    /**
     * Returns an array of part numbers recognised in a given string
     * Returns recognised tag structures
     *
     * Refactored by Yuriy Akopov on 2014-07-21
     *
     * @param   string  $stringToParse
     * @param   int     $scoreBoost
     *
     * @return  array
     */
    public function parsePartNumbers($stringToParse, $scoreBoost = 0) {
        if (strlen($stringToParse) === 0) {
            return array(); // used to return boolean false
        }

        $string = $this->stripMeasurements($stringToParse);

        $string = preg_replace("![\d\-]{1,}['\"]!", " ", $string);
        if (strlen($string) === 0) {
            return array();
        }

        $partNoCandidates = self::$helper->safeMatchAll("(?=[a-z-]*\d)[a-zA-Z0-9-.]{4,}(?<=[a-z0-9])", $string);
        if (empty($partNoCandidates)) {
            return array();
        }

        $partNumbers = array();
        foreach ($partNoCandidates as $item) {
            if (strlen($item) === 0) {
                continue;
            }

            // more than one space
            if (substr_count($item, ' ') > 1) {
                continue;
            }

            // numeric shorter than 5 digits
            if (is_numeric($item) and (strlen($item) < Shipserv_Match_Settings::get(Shipserv_Match_Settings::TOKEN_LENGTH_NUMPART))) {
                continue;
            }

            $charFirst = substr($item, 0, 1);
            $charLast  = substr($item, strlen($item) - 1, 1);

            // neither first and last characters are alphanumeric
            if (!ctype_alnum($charFirst) and !ctype_alnum($charLast)) {
                continue;
            }

            // starts with 'pn' or 'dn'
            $prefix = strtolower(substr($item, 0, 2));
            if (in_array($prefix, array('pn', 'dn'))) {
                continue;
            }

            // starts or ends with a dash on underscore
            $dashes = array('-', '_');
            if (in_array($charFirst, $dashes) or in_array($charLast, $dashes)) {
                continue;
            }

            // can be converted to a date
            if ($this->isDate($item)) {
                continue;
            }

            if ($this->looksLikeMeasurement($item)) {
                continue;
            }

            $partNumbers[] = array(
                Shipserv_Match_Match::TERM_TAG   => $item,
                Shipserv_Match_Match::TERM_SCORE => Shipserv_Match_Settings::get('PART_NUM_BASE_SCORE') + $scoreBoost
            );
        }

        return $partNumbers;
    }

    /**
     * Helper function performing the necessary replacements in a keyword/phrase to be used as a part of some regular expression
     *
     * @author  Yuriy Akopov
     * @date    2014-07-29
     *
     * @param   string  $phrase
     *
     * @return  string
     */
    public static function phraseToRegexp($phrase) {
        return'\b(' . str_replace(' ', '\s', preg_quote($phrase)) . ')\b';
    }

    /**
     * Simpler version of the brandParse string, doesn't return count, just the matched brands in a plain array rather than a scored version
     *
     * Refactored by Yuriy Akopov on 2013-08-29, S7028
     *
     * @param   string  $string
     * @param   bool    $disableVagueBrandRestriction   true to search for a simple substring, false for pattern
     * @param   bool    $includeBrandIds    false to enforce legacy behaviour and return raw strings not connected to IDs
     *
     * @return  array
     */
    public function parseBrands($string, $disableVagueBrandRestriction = false, $includeBrandIds = false) {
        $brandTags = array();

        // fastest way to go through an array is foreaching with a pointer and not key, hence &$word when it is not really needed
        // http://stackoverflow.com/a/16362604/454266

        $allBrands = $this->loadBrands();
        foreach ($allBrands as &$brand) {
            $brandTag = Shipserv_Match_TagGenerator::brandToTag($brand);
            $brandTag[Shipserv_Match_Match::TERM_SCORE] = 0;

            foreach ($brandTag[Shipserv_Match_Match::TERM_TAG] as &$word) {
                $pattern = self::phraseToRegexp($word);

                if (!$disableVagueBrandRestriction and $this->isBrandNameVague($word)) {
                    // a stricter pattern for vague brand names
                    $pattern = '[maker|mfg|manufacturer|builder|brand|]([: =;.]{1,})' . $pattern;
                }

                $matches = self::$helper->safeMatchAll($pattern, $string);
                $brandTag[Shipserv_Match_Match::TERM_SCORE] += count($matches);
            }
            unset($word);

            if ($brandTag[Shipserv_Match_Match::TERM_SCORE] > 0) {
                if ($includeBrandIds) {
                    $brandTags[] = $brandTag;
                } else {
                    // legacy behaviour for worfklows which might be not in use any more - check and remove later, if so
                    $brandTags[] = $brandTag[Shipserv_Match_Match::TERM_NAME];
                }
            }
        }

        unset($brand);

        return $brandTags;
    }

    /**
     * Returns categories matching the given text
     *
     * Refactored by Yuriy Akopov on 2014-07-22
     *
     * @param   string  $string
     * @param   bool    $useAbridged
     *
     * @return array
     */
    public function parseCategories($string, $useAbridged = false) {
        if (!$useAbridged) {
            $categoryRows = $this->loadCategories();
        } else {
            // @todo: this branch is apparently never used
            $categoryRows = $this->loadCategoriesAbridged();
        }

        // fastest way to go through an array is foreaching with a pointer and not key, hence &$word when it is not really needed
        // http://stackoverflow.com/a/16362604/454266

        $arrIgnore = array();

        $matches = array();
        foreach ($categoryRows as &$categoryRow) {
            if (in_array($categoryRow['ID'], $arrIgnore)) {
                // a lower level (hence more precise) category has already been found
                continue;
            }

            $category = Shipserv_Match_TagGenerator::categoryToTag($categoryRow);
            $category[Shipserv_Match_Match::TERM_SCORE] = 0;

            // loop through category's tags
            foreach ($category[Shipserv_Match_Match::TERM_TAG] as &$catTag) {
                $pattern = self::phraseToRegexp($catTag);
                $matchedBits = self::$helper->safeMatchAll($pattern, $string);
                $category[Shipserv_Match_Match::TERM_SCORE] += count($matchedBits);
            }
            unset($catTag);

            if ($category[Shipserv_Match_Match::TERM_SCORE] > 0) {
                $matches[] = $category;

                // get all parent categories and remove them from list
                if (!empty($category[Shipserv_Match_Match::TERM_PARENT_ID])) {
                    $parentIds = Shipserv_Category::getAllParents($category[Shipserv_Match_Match::TERM_PARENT_ID]);
                    $arrIgnore = array_merge($arrIgnore, $parentIds);
                }
            }
        }
        unset($categoryRow);

        if (empty($matches)) {
            return array();
        }

        if (count($arrIgnore)) {
            foreach ($matches as $key => $cat) {
                if (in_array($cat[Shipserv_Match_Match::TERM_ID], $arrIgnore)) {
                    unset($matches[$key]);
                }
            }
        }

        return $matches;
    }

    /**
     * Retrieves all the brands known to the system
     *
     * @return array
     */
    protected function loadBrands() {
        if (!is_null(self::$brands)) {
            return self::$brands;
        }

        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('b' => Shipserv_Brand::TABLE_NAME),
                array(
                    Shipserv_Brand::COL_ID,
                    Shipserv_Brand::COL_NAME
                )
            )
            // order by longest first, so that any matches early on, we can check if there are substring matches
            // to prevent issues like when Shin Shin brand and Shin would both be found
            ->order(new Zend_Db_Expr('LENGTH(' . Shipserv_Brand::COL_NAME . ')'))
        ;

        self::$brands = $this->cache->fetchData('matchHelperBrands', $select->assemble());

        return self::$brands;
    }

    /**
     * Unlike loadCategories(), loads top level categories only
     *
     * An odd method involved in some legacy match-related processes, possibly never user
     *
     * Simplified and cleansed by Yuriy Akopov on 2014-07-24
     *
     * @todo: check workflows dependent, could be obsolete
     *
     * @return  array
     */
    protected function loadCategoriesAbridged() {
        $sql = "
            SELECT
                ID,
                NAME,
                Keywords_comma_separated,
            FROM
                Product_Category p
            WHERE
                parent_id is null
        ";
        $results = $this->cache->fetchData('matchPatternGetCategoriesAbriged', $sql);

        $uniqueTags = array();
        $returnArr = array();
        foreach ($results as $result) {
            $name = strtolower(trim($result['NAME']));

            if (!in_array($name, $uniqueTags)) {
                $uniqueTags[] = $name;
                $returnArr[] = array(
                    'tag'         => $name,
                    'score'       => 0,
                    'id'          => $result['ID'],
                    'parent_id'   => null,
                    'output_name' => $name . ' (none)'
                );
            }

            if (!empty($result['KEYWORDS_COMMA_SEPARATED'])) {
                $exp = explode(',', trim(str_replace('"', '', $result['KEYWORDS_COMMA_SEPARATED'])));

                foreach ($exp as $tag) {
                    //$singularised = $this->singularise(trim($tag));
                    $name = strtolower(trim($tag));

                    if (!in_array($name, $uniqueTags)) {
                        $uniqueTags[] = $name;

                        $returnArr[] = array(
                            'tag'         => $name,
                            'score'       => 0,
                            'id'          => $result['ID'],
                            'parent_id'   => null,
                            'output_name' => $name . ' (none)'
                        );
                    }
                }
            }
        }

        return $returnArr;
    }

    /**
     * Gets a nested set of Categories, listed by their positions in the hierarchy
     * Prepares category names to be searched for
     *
     * Refactored by Yuriy Akopov on 2013-08-02
     *
     * @return  array
     */
    protected function loadCategories() {
        if (!is_null(self::$categories)) {
            return self::$categories;
        }

        $sql = '
            SELECT
              cat.*,
              p.NAME AS PARENTNAME
            FROM
              (
                SELECT
                    p.ID,
                    TRIM(p."NAME") AS "NAME",
                    p.parent_id,
                    LEVEL
                FROM
                    PRODUCT_CATEGORY p
                START WITH
                    p.parent_id IS NULL
                CONNECT BY
                    PRIOR p.id = p.parent_id
                ORDER BY
                    LEVEL
              ) cat
              LEFT JOIN product_category p ON
                p.ID = cat.parent_ID
        ';

        self::$categories = $this->cache->fetchData('matchHelperGetCategories', $sql);

        return self::$categories;
    }

    /**
     * Returns array of words recognised as tags for the match engine to search for
     *
     * Reworked by Yuriy Akopov on 2013-08-28
     *
     * @param   string  $string             String to parse
     * @param   bool    $returnNounsOnly    Only return the NN/NNS matches, or return all word types/
     *
     * @return  array
     */
    public function brillParse($string, $returnNounsOnly = true) {
        $allWords = self::$parser->tag($string);
        if (empty($allWords)) {  // no words recovered from the string
            return array();
        }

        $tags = array();
        foreach ($allWords as $word) {
            if (
                $returnNounsOnly and
                !in_array($word[Shipserv_Lexicon_BrillParser::TYPE], array(
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_SINGULAR,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PLURAL,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PROPER_SINGULAR
            ))) {
               continue;
            }

            if ($this->isStopWord($word[Shipserv_Lexicon_BrillParser::WORD])) {
                continue;
            }

            $tags[] = strtolower($word[Shipserv_Lexicon_BrillParser::WORD]);
        }

        sort($tags);
        return $tags;
    }

    /**
     * Function to take a plural word and return the singular equivalent using basic language construct rules.
     * Ported from the singularize method in Inflector class on RoR and modified to take small phrases like "Butterfly Valves" and return singular of Butterfly Valve.
     *
     * Refactored by Yuriy Akopov on 2013-08-02
     *
     * @param string $word
     * @return string
     */
    public static function singularise($word) {
        $cacheKey = strtolower($word);
        if (array_key_exists($cacheKey, self::$cacheSingulars)) {
            // if the word have been analysed before, skip expensive regular expressions
            return self::$cacheSingulars[$cacheKey];
        }

        $singulariseRules = array(
            'rules' => array(
                '/(quiz)zes$/i'         => '\1',
                '/(matr)ices$/i'        => '\1ix',
                '/(vert|ind)ices$/i'    => '\1ex',
                '/^(ox)en/i'            => '\1',
                '/(alias|status)es$/i'  => '\1',
                '/([octop|vir])i$/i'    => '\1us',
                '/(cris|ax|test)es$/i'  => '\1is',
                '/(shoe)s$/i'           => '\1',
                '/(o)es$/i'             => '\1',
                '/(bus)es$/i'           => '\1',
                '/([m|l])ice$/i'        => '\1ouse',
                '/(x|ch|ss|sh)es$/i'    => '\1',
                '/(m)ovies$/i'          => '\1ovie',
                '/(s)eries$/i'          => '\1eries',
                '/([^aeiouy]|qu)ies$/i' => '\1y',
                '/([lr])ves$/i'         => '\1f',
                '/(tive)s$/i'           => '\1',
                '/(hive)s$/i'           => '\1',
                '/([^f])ves$/i'         => '\1fe',
                '/(^analy)ses$/i'       => '\1sis',
                '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
                '/([ti])a$/i'           => '\1um',
                '/(n)ews$/i'            => '\1ews',
                '/s$/i'                 => '',
            ),
            'uncountable' => array(
                'equipment',
                'information',
                'rice',
                'money',
                'species',
                'series',
                'fish',
                'sheep'
            ),
            'irregular' => array(
                'person'    => 'people',
                'man'       => 'men',
                'child'     => 'children',
                'sex'       => 'sexes',
                'move'      => 'moves'
            )
        );

        $wordArr = explode(" ", $word);
        $multi = false;
        if (count($wordArr) > 1) {
            $multi = true;
            $word = array_pop($wordArr);
        }

        $returnWord     = null;
        $len            = strlen($word);
        $lowercasedWord = strtolower($word);

        // first checking of the word is an uncountable one as it's the simplest check to perform
        foreach ($singulariseRules['uncountable'] as $uncountable) {
            if (substr($lowercasedWord, (-1 * strlen($uncountable))) === $uncountable) {
                $returnWord = $word;
                break;
            }
        }

        // now checking if the word is an irregular one...
        if (is_null($returnWord)) {
            foreach ($singulariseRules['irregular'] as $singular => $plural) {
                $plLen = strlen($plural);
                if (substr($lowercasedWord, (-1 * $plLen)) === $plural) {
                    $returnWord = substr($word, 0, $len - $plLen) . $singular;
                    break;
                }

                // @todo: that legacy check below wasn't correct, was it?
                /*
                if (preg_match('/(' . $_singular . ')$/i', $word, $arr)) {
                    $returnWord = preg_replace('/(' . $_singular . ')$/i', substr($arr[0], 0, 1) . substr($_plural, 1), $word);
                    break;
                }
                */
            }
        }

        // and the most time-consuming check is the last and only runs if previous ones failed
        if (is_null($returnWord)) {
            foreach ($singulariseRules['rules'] as $rule => $replacement) {
                if (preg_match($rule, $word)) {
                    $returnWord = preg_replace($rule, $replacement, $word);
                    break;
                }
            }
        }

        if (is_null($returnWord)) {
            $returnWord = $word;    // failed to singularise (might be legit if $word was already singular)
        }

        if ($multi) {
            $wordArr[] = $returnWord;
            $returnWord = implode(" ", $wordArr);
        }

        self::$cacheSingulars[$cacheKey] = $returnWord;  // remember word in cache
        return $returnWord;
    }

    public function findWordProximity($wordA, $wordB, $haystack, $maxlength = 5) {
        //Prepare data.
        if (empty($wordA) || empty($wordB) || empty($haystack)) {
            return false;
        }

        // todo: 2014-03-19, a crutch to fix the issue of many fields supplied for analysis - to be investigated and fixed properly later
        if (is_array($wordA)) {
            $wordA = array_shift(array_values($wordA));;
        }

        if (is_array($wordB)) {
            $wordB = array_shift(array_values($wordB));;
        }

        $haystack = strtolower($haystack);
        $wordA = strtolower($wordA);
        $wordB = strtolower($wordB);

        $wordACount = substr_count($haystack, $wordA);
        $wordBCount = substr_count($haystack, $wordB);

        //One/Both of phrases/words not found so return false.
        if ($wordACount <= 0 || $wordBCount <= 0) {
            return -1;
        }

        $i = 0;
        for ($i = 0; $i < $wordACount; $i++) {
            if (count($wordAPos) == 0) {
                $start = 0;
            } else {
                $start = $wordAPos[count($wordAPos) - 1] + strlen($wordA) + 1;
            }
            $wordAPos[] = strpos($haystack, $wordA, $start);
        }

        $i = 0;
        for ($i = 0; $i < $wordBCount; $i++) {
            if (count($wordBPos) == 0) {
                $start = 0;
            } else {
                $start = $wordBPos[count($wordBPos) - 1] + strlen($wordB) + 1;
            }
            $wordBPos[] = strpos($haystack, $wordB, $start);
        }

        foreach ($wordAPos as $aPos) {
            foreach ($wordBPos as $bPos) {

                if ($aPos < $bPos) {
                    $start = $aPos + strlen($wordA);
                    $end = $bPos;
                } else {
                    $start = $bPos + strlen($wordB);
                    $end = $aPos;
                }

                //Get everything in between words so we can explode it
                $measureString = substr($haystack, $start, $end - $start);
                //Remove double spaces
                $measureString = preg_replace('#\s+#', ' ', $measureString);
                $measureArray = explode(" ", $measureString);
                $distance = 0;
                foreach ($measureArray as $item) {
                    if (!empty($item)) {
                        $distance++;
                    }
                }
                if ($distance <= $maxlength) {

                    $distanceChart[] = $distance;
                }
            }
        }

        if ($distanceChart) {
            return count($distanceChart);
        } else {
            return false; // @todo: added by Yuriy Akopov to make sense
        }
    }

    /**
     * Simple bigram (n-Gram) word extraction method
     *
     * Refactored by Yuriy Akopov on 2013-08-28
     *
     * @param   string  $string
     * @param   int     $wordCount
     *
     * @return  array
     * @throws  Exception
     */
    public function extractAdjectiveNounPairs($string, $wordCount = 2) {
        // @todo: commented as never used
        // // $regex = $this->settings['REGEX_EXTRACT_TRIPLE_WORDS'];   // was commented before I commented the whole block
        // $regex = "[a-z][a-z-']+[a-z] [a-z][a-z-']+[a-z]";
        // // $regex = "(\w+)\W+(?=(\w+))";                             // was commented before I commented the whole block
        // $sHelper = new Shipserv_Helper_String();

        $stopwords = $this->getMatchStopWords();
        $combinations = $this->parseNgrams($string, $wordCount); // $this->explodeForPairs($string);
        //$sHelper->regex_match_all($regex, $string);

        // number
        $criteria = array(
            // word type criteria for 2-word pairs
            2 => array(
                // last word
                1 => array(
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_SINGULAR,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PLURAL,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PROPER_SINGULAR
                ),
                // preceding word
                0 => array(
                    Shipserv_Lexicon_BrillParser::WORD_VERB_PRESENT_PARTICIPLE,
                    Shipserv_Lexicon_BrillParser::WORD_ADJECTIVE,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_SINGULAR,
                    // @todo: apparently Shipserv_Lexicon_BrillParser::WORD_NOUN_PLURAL is missing from here
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PROPER_SINGULAR
                )
            ),
            // word type criteria for 3-word pairs
            3 => array(
                // last word
                2 => array(
                    Shipserv_Lexicon_BrillParser::WORD_ADJECTIVE,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_SINGULAR,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PLURAL,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PROPER_SINGULAR
                ),
                // preceding word
                1 => array(
                    Shipserv_Lexicon_BrillParser::WORD_ADJECTIVE,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_SINGULAR,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PLURAL,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PROPER_SINGULAR
                ),
                // first word
                0 => array(
                    Shipserv_Lexicon_BrillParser::WORD_VERB_PRESENT_PARTICIPLE,
                    Shipserv_Lexicon_BrillParser::WORD_ADJECTIVE,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_SINGULAR,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PLURAL,
                    Shipserv_Lexicon_BrillParser::WORD_NOUN_PROPER_SINGULAR
                )
            )
        );

        if (!array_key_exists($wordCount, $criteria)) {
            throw new Exception('Word combinations ' . $wordCount . ' words longs is not supported');
        }

        $result = array();
        if (is_array($combinations) and !empty($combinations)) {
            foreach ($combinations as $combo) {
                if (strlen($combo) === 0) {
                    continue;
                }

                $split = explode(' ', trim($combo));

                for ($i = 0; $i < $wordCount; $i++) {
                    if (in_array(trim($split[$i]), $stopwords)) {
                        continue(2);
                    }
                }

                $parsed = self::$parser->tag($combo);

                if (count($parsed) !== $wordCount) {
                    continue;
                }

                $checksPassed = true;
                foreach ($parsed as $wordNo => $word) {
                    if (!in_array($word[Shipserv_Lexicon_BrillParser::TYPE], $criteria[$wordCount][$wordNo])) {
                        $checksPassed = false;
                        break;
                    }
                }

                if ($checksPassed) {
                    $result[] = strtolower($combo);
                }
            }
        }

        if (empty($result)) {
            return $result;
        }

        //if (count($result) > 1) {   // @todo: commented as we need to do that to one-element arrays as well, apparently
        $result = array_count_values($result);
        asort($result);
        //}

        return $result;
    }

    public function stripMeasurements($string) {
        $pattern = "!(?<=\A|[, :-=])\d+[- ]{0,3}(\"|barrel|bag|bottle|box|can|coil|cartridge|case|carton|cup|cylinder|degree|drum|dozen|foot|feet|squarefoot|gallon(us)|inch|gram|hole|jar|kilogram|pound|length|litre|milli-litre|meter|metre|milli-letre|milimetre|squaremetre|cubicmetre|metre|roll|numberofrolls|packet|piece|pail|pair|sack|set|sheet|shortton|tin|metricton|ton|thread|tonne|a|bar|bg|bo|bx|b|ca|cm|cl|cq|ct|cu|cy|cm2|c|deg|dr|din|fot|ftk|gll|grm|gm|hz|in|jr|kg|kgm|kgf|kwh|kw|l|lbr|ln|ltr|rpm|mlt|c2|cc3|c3|ma|m2|m3|mt3|mt2|ml|mm|mmt|mtk|mtq|mtr|m|nrl|pa|pin|psi|pkt|pce|pcs|rol|sa|set|st|stn|tn|tne|tu|va|v|x)[s]?(?=\Z|[, :-=])!i";

        $return = preg_replace($pattern, " ", $string);

        return $return;
    }

    /**
     * Strip measurements will only remove distinct measurements, but often
     * we receive things like 300-400V, which partParse will naturally pick up.
     * This will test if the partnumber matches something like that and return
     * true if it is
     * @param string $string
     * @return BOOL
     */
    public function looksLikeMeasurement($string) {
        // $pattern = "![[\d]+?[-x\/.]?]?[\d]+(\"|barrel|bag|bottle|box|can|coil|cartridge|case|carton|cup|cylinder|degree|drum|dozen|foot|feet|squarefoot|gallon(us)|inch|gram|hole|jar|kilogram|pound|length|litre|milli-litre|meter|metre|milli-letre|milimetre|squaremetre|cubicmetre|metre|roll|numberofrolls|packet|piece|pail|pair|sack|set|sheet|shortton|tin|metricton|ton|thread|tonne|a|bar|bg|bo|bx|b|ca|cm|cl|cq|ct|cu|cy|cm2|c|deg|dr|din|fot|ftk|gll|grm|gm|hz|in|jr|kg|kgm|kgf|kwh|kw|l|lbr|ln|ltr|rpm|mlt|c2|cc3|c3|m2|ma|m3|mt3|mt2|ml|mm|mmt|mtk|mtq|mtr|m|nrl|pa|pin|psi|pkt|pce|pcs|rol|sa|set|st|stn|tn|tne|tu|va|v|x)$!i";
        $pattern = "![[\d]+?[-x\/.]{1}]?[\d]+(\"|barrel|bag|bottle|box|can|coil|cartridge|case|carton|cup|cylinder|degree|drum|dozen|foot|feet|squarefoot|gallon(us)|inch|gram|hole|jar|kilogram|pound|length|litre|milli-litre|meter|metre|milli-letre|milimetre|squaremetre|cubicmetre|metre|roll|numberofrolls|packet|piece|pail|pair|sack|set|sheet|shortton|tin|metricton|ton|thread|tonne|a|bar|bg|bo|bx|b|ca|cm|cl|cq|ct|cu|cy|cm2|c|deg|dr|din|fot|ftk|gll|grm|gm|hz|in|jr|kg|kgm|kgf|kwh|kw|l|lbr|ln|ltr|rpm|mlt|c2|cc3|c3|m2|ma|m3|mt3|mt2|ml|mm|mmt|mtk|mtq|mtr|m|nrl|pa|pin|psi|pkt|pce|pcs|rol|sa|set|st|stn|tn|tne|tu|va|v|x)$!i";

        try {
            return preg_match($pattern, $string);
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * Returns array of stopwords
     *
     * @return array
     */
    protected function getMatchStopWords() {
        if (!is_null(self::$stopWords)) {
            return self::$stopWords;
        }

        $sql = "
            SELECT
              LOWER(msw_word) AS msw_word
            FROM
              match_stopwords
            ORDER BY
              LOWER(msw_word) ASC
        ";
        $results = Shipserv_Helper_Database::getDb()->fetchAll($sql);
        // $results = $this->cache->fetchData('matchStopWords', $sql);

        self::$stopWords = array();
        foreach ($results as $result) {
            self::$stopWords[] = $result['MSW_WORD'];
        }

        return self::$stopWords;
    }

    public function isStopWord($string) {
        return in_array(strtolower($string), $this->getMatchStopWords());
    }

    /**
     * @return array|null
     */
    protected function loadVagueBrandNames() {
        if (!is_null(self::$vagueBrandNames)) {
            return self::$vagueBrandNames;
        }

        // @todo: move list to DB
        self::$vagueBrandNames = array(
            '3m',
            'abb',
            'ace',
            'acs',
            'alfa',
            'bd',
            'bac',
            'bee',
            'biro',
            'bp',
            'bally',
            'baltic',
            'cdr',
            'coffin',
            'crc',
            'crown',
            'caddy',
            'carver',
            'castle',
            'crest',
            'commercial',
            'davit',
            'etc',
            'eye',
            'fellow',
            'ff',
            'facet',
            'fag',
            'gates',
            'hereford',
            'iron',
            'jets',
            'lips',
            'rh',
            'lh',
            'mac',
            'max',
            'mercury',
            'mission',
            'morse',
            'moss',
            'north',
            'oldham',
            'orbit',
            'perm',
            'pacific',
            'perfection',
            'plant',
            'plenty',
            'protector',
            'pc',
            'rose',
            'radium',
            'reheat',
            'republic',
            'responder',
            'rigid',
            'rise',
            'sata',
            'sea',
            'sailor',
            'seals',
            'sharp',
            'shell',
            'sick',
            'star',
            'sterling',
            'sun',
            'total',
            'turbo',
            'union',
            'wap',
            'wonderful',
            'watts',
            'wells',
            'whale',
            'york',
            'l3'
        );

        return self::$vagueBrandNames;
    }

    /**
     * Checks if the brand name also acts as a common word, eg. Total or Plant
     *
     * @param   string  $brand
     *
     * @return  bool
     */
    public function isBrandNameVague($brand) {
        return in_array(strtolower($brand), $this->loadVagueBrandNames());
    }

    /**
     * Returns true if the given word is valid to be a part of a recovered phrase
     *
     * @author  Yuriy Akopov
     * @date    2014-07-11
     * @story   S10773
     *
     * @param   string  $word
     *
     * @return bool
     */
    protected function isValidNgramWord($word) {
        return !(
            (strlen($word) < Shipserv_Match_Settings::get(Shipserv_Match_Settings::TOKEN_LENGTH_NGRAM)) // the original value was 3
            or $this->isStopWord($word)     // or it is a stopword
            or preg_match('/[0-9]/', $word) // word contains numbers
        );
    }

    /**
     * Refactored by Yuriy Akopov on 2014-07-11
     *
     * @param   string  $string
     * @param   int $n
     *
     * @return  array
     */
    public function parseNgrams($string, $n = 2) {
        // split the string using the list of delimeters specified
        // space is not in the list, so technically we split text into sentences
        $arrParts = preg_split('/[\)\(\?,\.:;\/%\$\!\r\n]/', $string);

        $ngrams = array();

        // loop through sentences recovered
        foreach ($arrParts as $parts) {
            // now split sentences into words
            $wordParts = explode(" ", trim($parts));
            if (count($wordParts) < $n) { // the sentence is too short
                continue;
            }

            foreach ($wordParts as $position => $word) {
                if ($position > count($wordParts) - $n) {
                    continue; // word is too close to the end of the sentence
                }

                if  (!$this->isValidNgramWord($word)) {
                    continue;
                }

                $ngramTmp = array();
                $ngramTmp[] = $word;

                //Get next $n-1 words in sequence
                for ($i = 0; $i < ($n - 1); $i++) {
                    $nextWord = $wordParts[$position + $i + 1];

                    if ($this->isValidNgramWord($nextWord)) {
                        $ngramTmp[] = $nextWord;
                    } else {
                        $ngramTmp = array();
                        continue 2;
                    }
                }

                if (count($ngramTmp) == $n) {
                    $ngrams[] = implode(' ', $ngramTmp);
                }
            }
        }

        return $ngrams;
    }

    /**
     * Returns true if the given string could be a date
     *
     * @param   string  $str
     *
     * @return bool
     */
    private function isDate($str) {
        if (is_numeric($str)) {
            // pure numbers should not be checked if they are dates
            // as such numbers will be successfully converted to date misleading us
            return false;
        }

        $stamp = strtotime($str);

        if (!is_numeric($stamp)) {
            return false;
        }

        $month  = date('m', $stamp);
        $day    = date('d', $stamp);
        $year   = date('Y', $stamp);

        return checkdate($month, $day, $year);
    }
}
