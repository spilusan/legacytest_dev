<?php

/**
 * A wrapper class to make access to application INI config and main settings convenient.
 *
 * Instead of accessing config structure directly in code add methods to this class to keep all the references at one place
 *
 * @author  Yuriy Akopov
 * @date    2014-04-25
 * @story   S10029
 */
class Myshipserv_Config
{
    const ENV_LIVE = 'production';
    const ENV_UAT = 'testing';
    const ENV_UAT2 = 'test2';
    const ENV_UAT3 = 'test3';
    const ENV_UKDEV = 'ukdev';
    const ENV_UKDEV2 = 'ukdev2';
    const ENV_UKDEV3 = 'ukdev3';
    const ENV_UKDEV4 = 'ukdev4';
    const ENV_UKDEV5 = 'ukdev5';
    const ENV_UKDEV6 = 'ukdev6';
    const ENV_UKDEV7 = 'ukdev7';
    const ENV_UKDEV8 = 'ukdev8';
    const ENV_UKDEV9 = 'ukdev9';
    const ENV_DEV = 'development';
    const ENV_MANILA_DEV = 'manila-dev';

    /**
     * @var Zend_Config_Ini
     */
    protected static $_configIni = null;

    /**
     * Returns the name of the current environment.
     *
     * @return string
     */
    public static function getEnv()
    {
        return $_SERVER['APPLICATION_ENV'];
    }

    /**
     * @author Yuriy Akopov
     *         @date 2016-02-03
     *
     * @return bool
     */
    public static function isInProduction()
    {
        return self::getEnv() === self::ENV_LIVE;
    }

    /**
     * @return bool
     */
    public static function isInUat()
    {
        return in_array(self::getEnv(),
                [
                        self::ENV_UAT,
                        self::ENV_UAT2,
                        self::ENV_UAT3,
                ]);
    }

    /**
     * Returns true if the app is running in development environment.
     *
     * @param bool $includeUKDEV
     * @param bool $includeManila
     *
     * @return bool
     */
    public static function isInDevelopment($includeUKDEV = false, $includeManila = false)
    {
        $allowedEnvironments = [
                self::ENV_DEV,
        ];

        if ($includeUKDEV) {
            $allowedEnvironments[] = self::ENV_UKDEV;
            $allowedEnvironments[] = self::ENV_UKDEV2;
            $allowedEnvironments[] = self::ENV_UKDEV3;
            $allowedEnvironments[] = self::ENV_UKDEV4;
            $allowedEnvironments[] = self::ENV_UKDEV5;
            $allowedEnvironments[] = self::ENV_UKDEV6;
            $allowedEnvironments[] = self::ENV_UKDEV7;
            $allowedEnvironments[] = self::ENV_UKDEV8;
            $allowedEnvironments[] = self::ENV_UKDEV9;
        }

        if ($includeManila) {
            $allowedEnvironments[] = self::ENV_MANILA_DEV;
        }

        return in_array(self::getEnv(), $allowedEnvironments);
    }

    /**
     * Returns INI file structure.
     *
     * @return Zend_Config_Ini
     */
    public static function getIni()
    {
        if (is_null(self::$_configIni)) {
            self::$_configIni = Zend_Registry::get('config');
        }

        return self::$_configIni;
    }

    /**
     * Returns buyer branch or organisation ID of the Pages proxy (usually
     * 11128).
     *
     * @param bool $getOrgId
     *
     * @return int
     */
    public static function getProxyPagesBuyer($getOrgId = false)
    {
        $id = self::getIni()->shipserv->pagesrfq->buyerId;

        if ($getOrgId) {
            $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
            $select->from([
                    'byb' => 'BUYER_BRANCH',
            ], 'byb.byb_byo_org_code')->where('byb.byb_branch_code = ?', $id);

            $id = $select->getAdapter()->fetchOne($select);
        }

        return (int) $id;
    }

    /**
     * Returns buyer branch or organisation ID of the Match proxy (usually
     * 11107).
     *
     * @param bool $getOrgId
     *
     * @return int
     */
    public static function getProxyMatchBuyer($getOrgId = false)
    {
        $id = self::getIni()->shipserv->match->buyerId;

        if ($getOrgId) {
            $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
            $select->from([
                    'byb' => 'BUYER_BRANCH',
            ], 'byb.byb_byo_org_code')->where('byb.byb_branch_code = ?', $id);

            $id = $select->getAdapter()->fetchOne($select);
        }

        return (int) $id;
    }

    /**
     * Returns supplier branch or organisation ID of the Match proxy (usually
     * 999999).
     *
     * @param bool $getOrgId
     *
     * @return int
     */
    public static function getProxyMatchSupplier($getOrgId = false)
    {
        $id = self::getIni()->shipserv->match->supplierId;

        if ($getOrgId) {
            $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
            $select->from([
                    'spb' => Shipserv_Supplier::TABLE_NAME,
            ], 'spb.'.Shipserv_Supplier::COL_ORG_ID)->where(
                    'spb.'.Shipserv_Supplier::COL_ID.' = ?', $id);

            $id = $select->getAdapter()->fetchOne($select);
        }

        return (int) $id;
    }

    /**
     * Returns timeout in seconds allowed for the remote webservice to forward
     * an RFQ.
     *
     * @param int $default
     *
     * @return int
     */
    public static function getRfqForwarderTimeout($default = 5)
    {
        $timeout = self::getIni()->shipserv->services->tradenet->forwarded->timeout;
        if (strlen($timeout) === 0) {
            $timeout = $default;
        }

        return (int) $timeout;
    }

    /**
     * Returns the URL of remove webservice to forward an RFQ.
     *
     * @return string
     */
    public static function getRfqForwarderUrl()
    {
        $url = self::getIni()->shipserv->services->tradenet->forwarder->url;

        return $url;
    }

    /**
     * Returns timeout in seconds for line items index.
     *
     * @param int $default
     *
     * @return int
     */
    public static function getSolrTimeoutLineItems($default = 5)
    {
        $timeout = self::getIni()->shipserv->services->solr->lineitem->timeout;
        if (strlen($timeout) === 0) {
            $timeout = $default;
        }

        return (int) $timeout;
    }

    /**
     * Returns line items index URL.
     *
     * @return string
     */
    public static function getSolrUrlLineItems()
    {
        return self::getIni()->shipserv->services->solr->lineitem->url;
    }

    /**
     * Returns timeout in seconds for suppliers index.
     *
     * @param int $default
     *
     * @return int
     */
    public static function getSolrTimeoutSuppliers($default = 5)
    {
        $timeout = self::getIni()->shipserv->services->solr->supplier->timeout;
        if (strlen($timeout) === 0) {
            $timeout = $default;
        }

        return (int) $timeout;
    }

    /**
     * Returns suppliers index URL.
     *
     * @return string
     */
    public static function getSolrUrlSuppliers()
    {
        return self::getIni()->shipserv->services->solr->supplier->url;
    }

    /**
     * Returns IDs of users who are match operators (i.e.
     * who match RFQs in /match/tagAction)
     * The purpose is to distinguish RFQs sent by those users from ones sent by
     * ordinary users from RFQ outbox.
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getMatchOperatorUserIds()
    {
        $strValue = self::getIni()->shipserv->match->operator->userIds;
        $ids = explode(',', $strValue);

        $userIds = [];
        foreach ($ids as $id) {
            $id = str_replace(' ', '', $id);

            if (strlen($id) === 0) {
                continue;
            }

            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                throw new Exception(
                        'Invalid match operator user ID '.$id.
                                 ' specified in config');
            }

            $userIds[] = (int) $id;
        }

        return $userIds;
    }

    /**
     * Returns IDs of buyer organisations participating in match early adoption.
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getMatchBuyerIds()
    {
        $strValue = self::getIni()->shipserv->match->participant->buyerId;
        $ids = explode(',', $strValue);

        $buyerOrgIds = [];
        foreach ($ids as $id) {
            $id = str_replace(' ', '', $id);

            if (strlen($id) === 0) {
                continue;
            }

            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                throw new Exception(
                        'Invalid buyer organisation '.$id.
                                 ' ID specified in config for auto match');
            }

            $buyerOrgIds[] = (int) $id;
        }

        return $buyerOrgIds;
    }

    /**
     * Returns current client's IP.
     *
     * @return string
     */
    public static function getUserIp()
    {
        $ipAddress = '';
        // check if the real IP address sent by proxy server
        // value of this can be: "10.0.3.4, 124.30.2.344, 123.32.44.55"
        // the real IP address of the browser is the last one
        if (($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '') != '') {
            if (strstr($_SERVER['HTTP_X_FORWARDED_FOR'], ' ') !== false) {
                $ipAddress = array_pop(
                        explode(',',
                                str_replace(' ', '',
                                        $_SERVER['HTTP_X_FORWARDED_FOR'])));
                $ipAddress = $ipAddress;
            } else {
                $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        }

        if ($ipAddress == '' && ($_SERVER['REMOTE_ADDR'] ?? '') != '') {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        return $ipAddress;
    }

    public static function _deprecated_getUserIp()
    {
        return $_SERVER['HTTP_PROXY_CLIENT_IP'] != '' ? $_SERVER['HTTP_PROXY_CLIENT_IP'] : $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Returns the list of super admin IP addresses.
     *
     * @param bool $allowLocalhostOnDev
     *
     * @return array
     */
    public static function getSuperIps($allowLocalhostOnDev = true)
    {
        $strValue = self::getIni()->shipserv->auth->superIps;
        $rawIps = explode(',', $strValue);

        $superIps = [];
        foreach ($rawIps as $ip) {
            $ip = str_replace(' ', '', $ip);

            if (strlen($ip) === 0) {
                continue;
            }

            $superIps[] = $ip;
        }

        if ($allowLocalhostOnDev) {
            if (self::getEnv() === self::ENV_DEV) {
                $localhost = '127.0.0.1';
                if (!in_array($localhost, $superIps)) {
                    $superIps[] = $localhost;
                }
            }
        }

        return $superIps;
    }

    /**
     * Returns true if the current IP is superIP.
     *
     * @author Yuriy Akopov, Refactored for this use by Attila O
     *         @date 2017-01-17
     *
     * @return bool
     *
     * @throws Exception
     */
    public static function isCurrentIpSuperIp()
    {
        $range = self::getSuperIps();
        $ip = self::getUserIp();

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new Exception(
                    'Invalid IP address '.$ip.
                             ', unable to validate against the given range');
        }

        $octets = explode('.', $ip);

        if (!is_array($range)) {
            $range = [
                    $range,
            ];
        }

        foreach ($range as $rangeIp) {
            $matched = true;

            if (strpos($rangeIp, '/') !== false) {
                list($subnet, $bits) = explode('/', $rangeIp);
                $longIp = ip2long($ip);
                $subnet = ip2long($subnet);
                $mask = -1 << (32 - $bits);
                $subnet &= $mask;
                $isIpCIDRMatch = ($longIp & $mask) == $subnet;
                if ($isIpCIDRMatch === false) {
                    continue;
                } else {
                    return true;
                }

                return false;
            } else {
                $rangeOctets = explode('.', $rangeIp);
                foreach ($rangeOctets as $index => $rangeOctet) {
                    if ($rangeOctet === '*') {
                        continue;
                    }

                    if ($octets[$index] !== $rangeOctet) {
                        $matched = false;
                        break;
                    }
                }

                if ($matched) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns user session storage.
     *
     * @return Zend_Auth
     */
    public static function getAuthStorage()
    {
        $auth = Zend_Auth::getInstance();
        $auth->setStorage(
                new Zend_Auth_Storage_Session(
                        self::getIni()->shipserv->services->authentication->namespace));

        return $auth;
    }

    /**
     * Returns the mask of the URL to edit supplier.
     *
     * @return string @throw Exception
     */
    public static function getPagesAdminListingsUrl()
    {
        $url = self::getIni()->shipserv->pagesAdmin->listings->url;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception(
                    'Invalid Pages Admin Listings Tool URL ('.$url.')');
        }

        return $url;
    }

    /**
     * Returns comma separated list of email addresses as an array, invalid
     * addresses removed.
     *
     * @param
     *            $string
     *
     * @return array
     */
    protected static function parseEmails($string)
    {
        $emails = explode(',', $string);

        foreach ($emails as $index => $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$index] = $email;
            } else {
                unset($emails[$index]);
            }
        }

        return $emails;
    }

    /**
     * Returns the list of emails to BCC when sending a match quote import
     * failure notification to buyer.
     *
     * @return array
     */
    public static function getMatchQuoteImportBccRecipients()
    {
        $emails = self::getIni()->shipserv->match->quoteImport->notifications->bcc;

        return self::parseEmails($emails);
    }

    /**
     * Returns the list of emails to BCC when sending a match quote import
     * failure notification to buyer.
     *
     * @return array
     */
    public static function getErroneousTransactionBccRecipients()
    {
        $emails = self::getIni()->shipserv->erroneousTransaction->notifications->bcc;

        return self::parseEmails($emails);
    }

    /**
     * Returns the applicatin host name.
     *
     * @return string
     */
    public static function getApplicationHostName()
    {
        $host = (string) self::getIni()->shipserv->application->hostname;

        return $host;
    }

    /**
     * Returns the application url.
     *
     * @return string
     */
    public static function getApplicationUrl()
    {
        return self::getIni()->shipserv->application->hostname;
    }

    /**
     * Returns true if buyer notifications are enabled for match quote failure.
     *
     * @return bool
     */
    public static function isMatchQuoteImportNotificationEnabled()
    {
        $enabled = (bool) self::getIni()->shipserv->match->quoteImport->notifications->enabled;

        return $enabled;
    }

    /**
     * Returns true if buyer notification are enabled for erroneous transaction.
     *
     * @return boolean
     */
    public static function isErroneousTransactionNotificationEnabled()
    {
        $enabled = (bool) self::getIni()->shipserv->erroneousTransaction->notifications->enabled;

        return $enabled;
    }

    public static function isErroneousTransactionNotificationSentToBuyer()
    {
        $enabled = (bool) self::getIni()->shipserv->erroneousTransaction->notifications->sendToCustomer;

        return $enabled;
    }

    public static function isSupplierAutomaticReminderEnabled()
    {
        $enabled = (bool) self::getIni()->shipserv->pages->supplierAutomaticReminder->notifications->enabled;

        return $enabled;
    }

    /**
     * Returns user to use in match processes which require userId to be stored
     * by are not initiated by a particular user.
     *
     * @author Yuriy Akopov
     *         @date 2014-10-23
     *         @story S11438
     *
     * @return Shipserv_User
     */
    public static function getMatchPagesUser()
    {
        $userId = self::getIni()->shipserv->match->pagesUser;

        $user = Shipserv_User::getInstanceById($userId);

        return $user;
    }

    /**
     * Returns, is Raise Alert for approved supplier is enabled.
     */
    public static function isApprovedSupplierRaiseAlertEnabled()
    {
        $enabled = (bool) self::getIni()->shipserv->pages->approvedSupplierRaiseAlert->notifications->enabled;

        return $enabled;
    }

    /**
     * Returns URL of the match engine application.
     *
     * @param bool $domainOnly
     *
     * @return string
     */
    public static function getMatchUrl($domainOnly = false)
    {
        $url = self::getIni()->shipserv->match->url;

        if ($domainOnly) {
            $bits = parse_url($url);
            $url = $bits['scheme'].'://'.$bits['host'];
            if (array_key_exists('port', $bits) and strlen($bits['port'])) {
                $url .= ':'.$bits['port'];
            }
        }

        return $url;
    }

    /**
     * Returns configuration node for SalesForce credentials.
     *
     * @author Yuriy Akopov
     *         @story S15735
     *         @date 2016-02-03
     *
     * Modified by Attila O, using relatice path for wsdl instead of hard coded absolute path
     *
     * @param bool $useSandbox
     *
     * @return object
     */
    public static function getSalesForceCredentials($useSandbox = null)
    {
        $salesforceConfig = self::getIni()->shipserv->salesforce;

        if (is_null($useSandbox)) {
            // use settings default for the environment
            $credentialsConfig = clone $salesforceConfig->{$salesforceConfig->mode};
        } else {
            if ($useSandbox) {
                $credentialsConfig = clone $salesforceConfig->sandbox;
            } else {
                $credentialsConfig = clone $salesforceConfig->integration;
            }
        }

        //Object is cloned, as multiple call of this function append the root path multiple times
        $credentialsConfig->wsdl = APPLICATION_PATH.'/'.$credentialsConfig->wsdl;

        return $credentialsConfig;
    }

    /**
     * @return float
     */
    public static function getSalesForceDefaultPayingCustomerRate()
    {
        return (float) self::getIni()->shipserv->salesforce->ratesSync->defaultPayingCustomerRate;
    }

    /**
     * @return string
     */
    public static function getSalesForceSyncReportEmail()
    {
        return self::getIni()->shipserv->salesforce->ratesSync->reportEmail;
    }

    /**
     * Returns the list of emails to BCC when sending Active Promotion emails.
     *
     * @return array
     */
    public static function getTargetingRecipients()
    {
        $emails = self::getIni()->shipserv->targeting->email->bcc;

        return self::parseEmails($emails);
    }

    /**
     * Returns default address and name to use on outgoing emails.
     *
     * @author Yuriy Akopov
     *         @date 2016-03-22
     *         @story DE6437
     *
     * @return array
     */
    public static function getDefaultFromAddressName()
    {
        $email = self::getIni()->shipserv->email->default->from->email;
        if (strlen($email) === 0) {
            $email = 'support@shipserv.com';
        }

        $name = self::getIni()->shipserv->email->default->from->name;
        if (strlen($name) === 0) {
            $name = 'ShipServ';
        }

        return [
                $email,
                $name,
        ];
    }

    /**
     * Decorates given memcache key with config specified prefix and suffix.
     *
     * @author Yuriy Akopov
     *         @date 2016-06-29
     *
     * @param string $key
     *
     * @return string
     */
    public static function decorateMemcacheKey($key)
    {
        return implode('',
                [
                        self::getIni()->memcache->client->keyPrefix,
                        $key,
                        self::getIni()->memcache->client->keySuffix,
                ]);
    }

    /**
     * Returns IDs of buyer organisations and / or branches allowed to access
     * Price Benchmarking
     * Returns empty array if none are allowed
     * Returns null if all organisations are allowed.
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getPriceBenchmarkAllowedBuyerOrgIds()
    {
        // check if all the organisations are allowed
        $restricted = (bool) self::getIni()->shipserv->priceBenchmark->access->restricted;
        if (!$restricted) {
            // any buyer organisation is allowed in
            return null;
        }

        // retrieve the IDs of allowed organisations and/or branches
        $buyerIdsStr = self::getIni()->shipserv->priceBenchmark->access->buyerIds;
        $buyerIds = explode(',', $buyerIdsStr);

        foreach ($buyerIds as $index => $id) {
            $id = trim($id);

            if (strlen($id) === 0) {
                unset($buyerIds[$index]);
            } else {
                if (!is_numeric($id)) {
                    throw new Exception(
                            'Non-numeric buyer org ID in price benchmarking settings: '.
                                     $id);
                }
            }
        }

        return $buyerIds;
    }

    /**
     * Returns max range in date that is allowed for Price Benchmarking and
     * Spend Tracking tools requests.
     *
     * @return int
     */
    public static function getPriceBenchmarkDaysRange()
    {
        return (int) self::getIni()->shipserv->priceBenchmark->daysRange;
    }

    /**
     * Retuns the application protocol http or https.
     *
     * @return string
     */
    public static function getApplicationProtocol()
    {
        /*
         * //It was requested by Claudio to use HTTPS in all cases, and get rid
         * of application.ini settings
         * $protocol = self::getIni()->shipserv->application->protocol;
         * return ($protocol) ? $protocol : 'http';
         */
        return 'https';
    }

    /**
     * Return the root domain for corporate website, http or https
     * NOTE it is not specificly for corporate anymore as we do not require to
     * corporate be on a different protocol (http[s]), so it can be used to get
     * the page root domain.
     *
     * @return string
     */
    public static function getCorporateSiteUrl()
    {
        return 'https://'.
                 Zend_Registry::get('config')->shipserv->application->hostname;
    }

    /**
     * Safely get the application release timestamp used for cache busting (and
     * eventually something else)
     * If the placeholder in application.ini was not replaced during build, this
     * function will return the current date.
     *
     * @return Int
     */
    public static function getCachebusterTag()
    {
        if (!self::getIni()->shipserv->cachebuster->use) {
            return '';
        }
        $applicationTimestamp = self::getIni()->shipserv->application->releasets;
        if (!is_numeric($applicationTimestamp)) {
            $applicationTimestamp = date('Ymd');
        }

        return $applicationTimestamp;
    }

    /**
     * This is a bit modified version of getCacheBusterTag() function
     * as this way we will be able insert the tag directly to the URL
     * and we will avoid the ugly js//dummy.js if cacheBuster is off.
     *
     * @return string
     */
    public static function getCachebusterTagAddition()
    {
        $tag = self::getCachebusterTag();

        return ($tag === '') ? '' : $tag.'/';
    }

    /**
     * Safely get the application release version used for Shipserv Analytics
     * and other logging purposes
     * If the placeholder in application.ini was not replaced during build, this
     * function will return the current date.
     *
     * @return Int
     */
    public static function getApplicationReleaseVersion()
    {
        $applicationTimestamp = self::getIni()->shipserv->application->version;
        if (!is_numeric(str_replace('.', '', $applicationTimestamp))) {
            $applicationTimestamp = date('Y.m.d');
        }

        return $applicationTimestamp;
    }

    /**
     * Returns the list of emails to BCC when sending a match SupplierRecommendations_Alert.
     *
     * @return array
     */
    public static function getSupplierRecommendationsBccRecipients()
    {
        $emails = self::getIni()->shipserv->supplierRecommendations->notifications->bcc;

        return self::parseEmails($emails);
    }

    /**
     * Returns if we send out the notificatin to real buyers or not.
     *
     * @return boolean
     */
    public static function isSupplierRecommendationsNotificationSentToBuyer()
    {
        $enabled = (bool) self::getIni()->shipserv->supplierRecommendations->notifications->sendToCustomer;

        return $enabled;
    }

    /**
     * Returns with the list of TNID's we have to exclude from IMPA reports.
     *
     * @return array
     */
    public static function getExcludeSuppliersFromImpaReport()
    {
        $spbBranches = self::getIni()->shipserv->priceBenchmark->excludeSuppliers;

        if (!$spbBranches) {
            return null;
        }

        if (!preg_match('/^([0-9]+,?)+$/', $spbBranches)) {
            throw new Myshipserv_Exception_MessagedException('in application.ini shipserv.priceBenchmark.excludeSuppliers has invalid value. Can be empty, one TNID, or a comma separated list of TNIDs', 500);
        }

        return array_map('intval', explode(',', $spbBranches));
    }

    /**
     * Returns the email to add to every Market Sizing report sent.
     *
     * @author  Yuriy Akopov
     * @date    2018-03-22
     * @story   DEV-2563
     *
     * @return string
     */
    public static function getMarketSizingCcEmail()
    {
        return self::getIni()->shipserv->pages->report->marketSizing->email;
    }

    /**
     * Returns the list of user emails which are allowed to access Market Sizing tool.
     *
     * @author  Yuriy Akopov
     * @date    2018-03-22
     * @story   DEV-2563
     *
     * @return array
     */
    public static function getMarketSizingAccessEmails()
    {
        return explode(',', self::getIni()->shipserv->pages->report->marketSizing->access);
    }

    /**
     * Check if SIR outdated message shuld be displayed.
     *
     * @return bool
     */
    public static function displaySirOutdatedAlert()
    {
        if (!isset(self::getIni()->shipserv->sir->outdated)) {
            return false;
        }
        
        return (bool) self::getIni()->shipserv->sir->outdated->message;
    }
}
