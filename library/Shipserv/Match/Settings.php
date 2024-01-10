<?php
/**
 * Manages Match engine factors and constants
 *
 * Is separate from INI file based config object as historically match engine has its settings stored in a database table
 *
 * @author  Yuriy Akopov
 * @date    2013-08-01
 */
class Shipserv_Match_Settings {
    const
        TABLE_NAME = 'MATCH_ALGORITHM_VALUES',
        COL_KEY     = 'MVA_NAME',
        COL_VALUE   = 'MVA_VALUE'
    ;

    // constants for settings keys, all of them should be listed here one day
    const
        // new values introduced by Yuriy Akopov during refactoring at 2013-08
        LEXICON_PATH            = 'LEXICON_PATH',
        LEXICON_CACHE_TTL       = 'LEXICON_CACHE_TTL',
        CATEGORY_ID_CHANDLERY   = 'CATEGORY_ID_CHANDLERY',

        SUPPLIER_PROXY_ID       = 'SUPPLIER_PROXY_ID',
        SUPPLIER_PROXY_ORG_ID   = 'SUPPLIER_PROXY_ORG_ID',
        BUYER_PROXY_ID          = 'BUYER_PROXY_ID',

        PREMIUM_DIR_LISTING_ID  = 'PREMIUM_DIR_LISTING_ID',
        META_FIGURES_DEPTH      = 'META_FIGURES_DEPTH',
        // legacy values defined as constants, in time all of the should be found here instead of string hardcodes scattered through the code
        CHANDLERY_PENALISATION          = 'CHANDLERY_PENALISATION',
        CATEGORY_SPAM_THRESHOLD_BASIC   = 'CATEGORY_SPAM_THRESHOLD_BASIC',
        CATEGORY_SPAM_THRESHOLD_PREMIUM = 'CATEGORY_SPAM_THRESHOLD_PREMIUM',
        CATEGORY_SPAM_MAX_PENALTY       = 'CATEGORY_SPAM_MAX_PENALTY_PERCENTAGE',
        CATEGORY_SPAM_MIN_PENALTY       = 'CATEGORY_SPAM_MIN_PENALTY_PERCENTAGE',
        // batch search processing related parameters
        BATCH_DEFAULT_STOP_AFTER        = 'BATCH_DEFAULT_STOP_AFTER',
        BATCH_DEFAULT_TIMEOUT           = 'BATCH_DEFAULT_TIMEOUT',
        BATCH_QUEUE_LEN_YEARS           = 'BATCH_QUEUE_LEN_YEARS',
        BATCH_DB_PAGE_SIZE              = 'BATCH_DB_PAGE_SIZE',
        // score ranges for search terms
        TERM_SCORE_RANGE_MEDIUM_MIN   = 'TERM_SCORE_RANGE_LOW_MIN',
        TERM_SCORE_RANGE_MEDIUM_MAX   = 'TERM_SCORE_RANGE_LOW_MAX',
        // default scores for search ranges
        TERM_SCORE_RANGE_LOW_DEFAULT      = 'TERM_SCORE_RANGE_LOW_DEFAULT',
        TERM_SCORE_RANGE_MEDIUM_DEFAULT   = 'TERM_SCORE_RANGE_MEDIUM_DEFAULT',
        TERM_SCORE_RANGE_HIGH_DEFAULT     = 'TERM_SCORE_RANGE_HIGH_DEFAULT',
        // supplier status check parameters
        SUPPLIER_AT_RISK_INTERVAL         = 'SUPPLIER_AT_RISK_INTERVAL',

        AUTOMATCH_LAST_RFQ_ID             = 'AUTOMATCH_LAST_RFQ_ID',
        // tokenisation parameters
        TOKEN_LENGTH_NGRAM    = 'TOKEN_LENGTH_NGRAM',
        TOKEN_LENGTH_NOUN     = 'TOKEN_LENGTH_NOUN',
        TOKEN_LENGTH_NUMPART  = 'TOKEN_LENGTH_NUMPART',
        TOKEN_LENGTH_CATEGORY = 'TOKEN_LENGTH_CATEGORY',

        LOCATION_WEIGHTS_ENABLED = 'LOCATION_WEIGHTS_ENABLED',

        LOCATION_SUPPLIER_SCORE           = 'LOCATION_SUPPLIER_SCORE',
        LOCATION_PORT_SCORE               = 'LOCATION_PORT_SCORE',
        LOCATION_BUYER_SCORE              = 'LOCATION_BUYER_SCORE',

        LOCATION_SCORE_DENOMINATOR           = 'LOCATION_SCORE_DENOMINATOR',
        LOCATION_CONTINENT_SCORE_DENOMINATOR = 'LOCATION_CONTINENT_SCORE_DENOMINATOR'
    ;

    /**
     * Loaded settings
     *
     * @var null|array
     */
    protected static $vars = null;

    /**
     * Default values for the settings to fall back to when not defined in the source
     *
     * @var array
     */
    protected static $defaults = array(
        self::LEXICON_CACHE_TTL         => 2592000, // one month TTL - because we can, that's why
        self::CATEGORY_ID_CHANDLERY     => 9,
        self::PREMIUM_DIR_LISTING_ID    => 4,       // directory listing ID making supplier eligible for premium ranking boost
        self::META_FIGURES_DEPTH        => 365,     // depth to calculate meta figures like number of RFQs sent to a supplier for (in days)
        self::BATCH_DEFAULT_STOP_AFTER  => 100,
        self::BATCH_DEFAULT_TIMEOUT     => 300,
        self::BATCH_QUEUE_LEN_YEARS     => 2,        // RFQ queue length in years from now
        self::BATCH_DB_PAGE_SIZE        => 1000,
        // search terms score ranges for user interface - low, medium, high
        // (how different scores will be presented to user)
        // everything below MIN would be LOW, in between MIN and MAX - medium, above MAX - HIGH
        self::TERM_SCORE_RANGE_MEDIUM_MIN   => 100,
        self::TERM_SCORE_RANGE_MEDIUM_MAX   => 1000,
        // default scores for user search terms (which scores to assign when user adds a term with a specified score range)
        self::TERM_SCORE_RANGE_LOW_DEFAULT      => 50,
        self::TERM_SCORE_RANGE_MEDIUM_DEFAULT   => 500,
        self::TERM_SCORE_RANGE_HIGH_DEFAULT     => 1500,

        self::SUPPLIER_AT_RISK_INTERVAL => 12,

        self::TOKEN_LENGTH_NGRAM    => 3,    // minimal allowed length for n-gram participant words
        self::TOKEN_LENGTH_NOUN     => 3,    // minimal allowed length for noun tags
        self::TOKEN_LENGTH_NUMPART  => 5,    // minimal allowed length for a numerical part number
        self::TOKEN_LENGTH_CATEGORY => 4,    // minimal allowed length for category synonyms

        self::LOCATION_PORT_SCORE       => 1000,
        self::LOCATION_SUPPLIER_SCORE   => 500,
        self::LOCATION_BUYER_SCORE      => 1000,

        self::LOCATION_SCORE_DENOMINATOR           => 10,
        self::LOCATION_CONTINENT_SCORE_DENOMINATOR => 10,

        self::LOCATION_WEIGHTS_ENABLED => 1
    );

    protected function __construct() {
        throw new Exception('This class is not supposed to be instantiated');
    }

    /**
     * Returns all the available settings
     *
     * @return array
     */
    public static function getAll() {
        if (!is_null(self::$vars)) {
            return self::$vars;
        }

        // settings requested for the first time, load them from DB
        // no real need to use the cache there as the query is quick and we will only need to run it once per session
        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mva' => self::TABLE_NAME),
                array(
                    self::COL_KEY,
                    self::COL_VALUE
                )
            )
        ;
        $results = $db->fetchAll($select);

        self::$vars = array();
        foreach ($results as $result) {
            self::$vars[$result[self::COL_KEY]] = $result[self::COL_VALUE];
        }

        $appPath = dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR;

        // extending defaults array with computable values which were won't be able to put into array declaration
        self::$defaults[self::LEXICON_PATH] =  $appPath . implode(DIRECTORY_SEPARATOR, array(
            'library',
            'Shipserv',
            'Lexicon',
            'lexicon.txt'
        ));

        self::$defaults[self::SUPPLIER_PROXY_ID]     = Myshipserv_Config::getProxyMatchSupplier();
        self::$defaults[self::BUYER_PROXY_ID]        = Myshipserv_Config::getProxyMatchBuyer();
        self::$defaults[self::SUPPLIER_PROXY_ORG_ID] = Myshipserv_Config::getProxyMatchSupplier(true);

        foreach (self::$defaults as $key => $value) {   // not using array_merge() here because of the egde case issue with non-string keys
            if (!array_key_exists($key, self::$vars)) {
                self::$vars[$key] = $value;
            }
        }

        return self::$vars;
    }

    /**
     * Returns requested value from the Match engine settings or falls back to a provided default
     *
     * @param   string  $key
     * @param   mixed|null $default
     * @return  mixed
     */
    public static function get($key, $default = null) {
        $vars = self::getAll();

        if (array_key_exists($key, $vars)) {
            return $vars[$key];
        }

        return $default;
    }

    /**
     * Stores a new value by updating an existing key or creating a new one
     *
     * @param   string  $key
     * @param   string  $value
     */
    public static function set($key, $value) {
        $db = Shipserv_Helper_Database::getDb();

        // first try to update an existing value as it is more likely for a parameter to exist
        $affected = $db->update(
            self::TABLE_NAME,
            array(
                self::COL_VALUE => $value
            ),
            $db->quoteInto(self::COL_KEY . ' = ?', $key)
        );

        if ($affected === 0) {
            $db->insert(self::TABLE_NAME, array(
                self::COL_KEY   => $key,
                self::COL_VALUE => $value
            ));
        }
    }

    /**
     * Sets more than one parameter at once
     *
     * @param   array   $settings
     *
     * @throws  Exception
     */
    public static function setMany(array $settings) {
        if (empty($settings)) {
            return;
        }

        foreach ($settings as $key => $value) {
            self::set($key, $value);
        }
    }
}
