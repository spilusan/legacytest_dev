<?php

/**
 * Class to parse text and identify noun within
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class Shipserv_Lexicon_BrillParser {
    /**
     * Loaded lexicon file
     *
     * @var array
     */
    static protected $dict = null;

    const
        LEXICON_CACHE_KEY_PREFIX    = 'lexicon_path:'    // key to search for in memcached when attempting to load the file
    ;

    // PennTags constants, should be extended if more types are used in processing
    // source: http://bulba.sdsu.edu/jeanette/thesis/PennTags.html
    const
        WORD_NOUN_SINGULAR          = 'NN',
        WORD_NOUN_PLURAL            = 'NNS',
        WORD_NOUN_PROPER_SINGULAR   = 'NNP',

        WORD_VERB_BASE              = 'VB',
        WORD_VERB_PAST_TENSE        = 'VBD',
        WORD_VERB_NON3_SINGULAR     = 'VBP',
        WORD_VERB_3_SINGULAR        = 'VBZ',
        WORD_VERB_PAST_PARTICIPLE   = 'VBN',
        WORD_VERB_PRESENT_PARTICIPLE= 'VBG',

        WORD_DETERMINER             = 'DT',
        WORD_NUMBER_CARDINAL        = 'CD',
        WORD_ADVERB                 = 'RB',
        WORD_ADJECTIVE              = 'JJ'
    ;

    const
        WORD = 'token',
        TYPE = 'tag'
    ;

    protected function initDictionary() {
        $lexiconPath = Shipserv_Match_Settings::get(Shipserv_Match_Settings::LEXICON_PATH);

        // attempt to load file from cache first as reading it from disk is a costly operation
        try {
            $memcache = Shipserv_Memcache::getMemcache();
            if (!is_object($memcache)) {
                throw new Exception("No access to memcached");
            }

            $cacheKey = self::LEXICON_CACHE_KEY_PREFIX . $lexiconPath;
            if (($cachedValue = $memcache->get($cacheKey)) === false) {
                throw new Exception("Lexicon file is not cached yet");
            }

            if ((self::$dict = unserialize($cachedValue)) === false) {
                throw new Exception("Lexicon cached value is invalid");
            }

        } catch (Exception $e) {
            // memcached isn't running or value not available, fall back to file load
            self::$dict = null;
        }

        if (is_null(self::$dict)) {
            // failed to load the file from cache, read it and parse from disk, then write it to cache
            if (($fh = fopen($lexiconPath, 'r')) === false) {
                throw new Exception("Unable to read lexicon file from " . $lexiconPath);
            }

            while ($line = fgets($fh)) {
                $tags = explode(' ', trim($line));
                self::$dict[strtolower(array_shift($tags))] = $tags;
            }
            fclose($fh);

            // file read, now cache the content if memcached is available
            if ($memcache) {
                $ttl = Shipserv_Match_Settings::get(Shipserv_Match_Settings::LEXICON_CACHE_TTL);

                // enable compression as our current file (as of 2013-08-02) is about 1.5 Mb plus serialisation clutter
                $memcache->setcompressthreshold(1048576);
                if ($memcache->set($cacheKey, serialize(self::$dict), null, $ttl) === false) {
                    // @todo: in case if compressed value size if greater than 1 Mb we will need to increase memcache limit or to break it into chunks
                    // send a notification to admin here saying that we need to do either of the above
                }
            }
        }
    }

    /**
     * Initialises lexicon parser by loading the set of pre-defined recognizable tags
     *
     * @author  Yuriy Akopov
     * @date    2013-08-02
     *
     * @throws Exception
     */
    public function __construct() {
        if (is_null(self::$dict)) {
            $this->initDictionary();
        }
    }

    /**
     * Returns word type
     *
     * Created by moving and refactoring code from tag() method
     *
     * @author  Yuriy Akopov
     * @date    2014-07-22
     * @story   S10773
     *
     * @param   string      $word
     * @param   string|null $prevWord
     * @param   string|null $prevType
     *
     * @return  string|null
     */
    public function getWordType($word, $prevWord = null, $prevWordType = null) {
        $word = strtolower($word);
        if (!is_null($prevWord)) {
            $prevWord = strtolower($prevWord);

            if (is_null($prevWordType)) {
                $prevWordType = $this->getWordType($prevWord, null, null);
            }
        }

        $nouns = array(
            self::WORD_NOUN_SINGULAR,
            self::WORD_NOUN_PLURAL,
            self::WORD_NOUN_PROPER_SINGULAR
        );

        $type = self::WORD_NOUN_SINGULAR;

        $typeInDict = false;
        if (isset(self::$dict[$word])) {
            $type = trim(self::$dict[$word][0]);

            if (strlen($type)) {
                $typeInDict = true;
            } else {
                $type = self::WORD_NOUN_SINGULAR;
            }
        } else if (strlen($word) < Shipserv_Match_Settings::get(Shipserv_Match_Settings::TOKEN_LENGTH_NOUN)) {
            // for words not found in dictionary there is a minimal length requirement
            return null;
        }

        // the following checks are only performed if the type wasn't initially found in the dictionary
        if (!$typeInDict) {
            $tail2 = substr($word, -2);
            $tail3 = substr($word, -3);

            if (
                ($tail2 == 'ly') and
                ($tail3 != 'fly')
            ) {
                $type = self::WORD_ADVERB;

            } else if (in_array($type, $nouns)) {

                if ($tail2 == 'ed') {
                    $type = self::WORD_VERB_PAST_PARTICIPLE;

                } else if ($tail2 == 'al') {
                    $type = self::WORD_ADJECTIVE;

                } else if ($tail3 == 'ing') {
                    $type = self::WORD_VERB_PRESENT_PARTICIPLE;
                }
            }
        }

        // verbs after 'the' become nouns
        if (
            ($prevWordType == self::WORD_DETERMINER) and
            in_array($type, array(
                self::WORD_VERB_PAST_TENSE,
                self::WORD_VERB_NON3_SINGULAR,
                self::WORD_VERB_BASE
            ))
        ) {
            $type = self::WORD_NOUN_SINGULAR;
        }

        // noun becomes a verb if the word before it is 'would'
        if (
            ($prevWord == 'would') and
            ($type == self::WORD_NOUN_SINGULAR)
        ) {
            $type = self::WORD_VERB_BASE;
        }

        // noun is plural if it ends with an s
        if (
            ($type == self::WORD_NOUN_SINGULAR) and
            (substr($word, -1) == 's')
        ) {
            $type = self::WORD_NOUN_PLURAL;
        }

        // if we get noun noun, and the second can be a verb, convert it to verb
        if (
            in_array($type, $nouns) and
            in_array($prevWordType, $nouns) and
            $typeInDict
        ) {
            $dictTypes =  self::$dict[$word];

            if (in_array(self::WORD_VERB_PAST_PARTICIPLE, $dictTypes)) {
                $type = self::WORD_VERB_PAST_PARTICIPLE;

            } else if (in_array(self::WORD_VERB_3_SINGULAR, $dictTypes)) {
                $type = self::WORD_VERB_3_SINGULAR;
            }
        }

        return $type;
    }

    /**
     * Breaks the given string into tags and assigns grammar types
     *
     * Refactored by Yuriy Akopov on 2014-07-17
     *
     * @param   string  $text
     *
     * @return  array
     */
    public function tag($text) {
        $helper = new Shipserv_Helper_String();

        $minLen = Shipserv_Match_Settings::get(Shipserv_Match_Settings::TOKEN_LENGTH_NOUN);
        $matches = $helper->safeMatchAll("(\b[a-zA-Z-]{" . $minLen . ",}\b)+?", $text);

        if (empty($matches)) {
            return array();
        }

        $wordInfo = array();

        $prevWord = null;
        $prevWordType = null;

        foreach ($matches as $word) {
            $word = trim($word);
            $type = $this->getWordType($word, $prevWord, $prevWordType);

            $prevWord = $word;
            $prevWordType = $type;

            if (is_null($type)) {
                continue;
            }

            // default type assigned to a word is base noun
            $wordInfo[] = array(
                self::WORD => $word,
                self::TYPE => $type
            );
        }

        return $wordInfo;
    }

    //More efficient search method for processing large datasets
    function binSearch($needle, $haystack) {
        // n is only needed if counting depth of search
        global $n;
        $n++;
        // get the length of passed array
        $l = count($haystack);
        // if length is 0, problem
        if ($l <= 0) {
            return -1;
        }
        // get the mid element
        $m = (($l + ($l % 2)) / 2);
        // if mid >= length (e.g. l=1)
        if ($m >= $l) {
            $m = $m - 1;
        }
        // get the indexed element to compare to the passed element and branch accordingly
        $compare = $haystack[$m];
        switch (true) {
            case($compare > $needle): {
                    // recurse on the lower half
                    $new_haystack = array_slice($haystack, 0, $m);
                    $c = count($new_haystack);
                    $r = binSearch($needle, $new_haystack);
                    // return current index - (length of lower half - found index in lower half)
                    return $m - ($c - $r);
                    break;
                }
            case($compare < $needle): {
                    // recurse on the upper half
                    $new_haystack = array_slice($haystack, $m, ($l - $m));
                    $c = count($new_haystack);
                    $r = binSearch($needle, $new_haystack);
                    // return current position + found index in upper half
                    return $m + $r;
                    break;
                }
            case($compare == $needle): {
                    // found it, so return index
                    return $m;
                    break;
                }
        }
    }

    //
    public function testSearches() {
        $searchTerms = array(
            'zoologist',
            'ascap',
            'ravencroft',
        );
        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $normalstarttime = $mtime;

        //Normal Array search here

        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $endtime = $mtime;
        $normaltotaltime = ($endtime - $starttime);
        $normalCost = "Normal Searches done in " . $totaltime . " seconds";


        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $normalstarttime = $mtime;

        //Binary Array search here

        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $endtime = $mtime;
        $normaltotaltime = ($endtime - $starttime);
        $normalCost = "Normal Searches done in " . $totaltime . " seconds";
    }
}

