<?php
class MarketSizing_IndexController extends Myshipserv_Controller_Action
{
    /**
     * @throws Myshipserv_Exception_MessagedException
     */
    public function init()
    {
    	parent::init();
    	 
        $this->abortIfNotAllowed();
    }

    /**
     * Restricts access to ShipMate users whose emails are on the list
     *
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function abortIfNotAllowed()
    {
        $this->abortIfNotShipMate();

        $user = Shipserv_User::getUser();
        if (!in_array(strtolower($user->getEmail()), Myshipserv_Config::getMarketSizingAccessEmails())) {
            throw new Myshipserv_Exception_MessagedException(
                "Access denied: this page is only available to " .
                implode(", ", Myshipserv_Config::getMarketSizingAccessEmails()) . ". " .
                "Please contact " . Myshipserv_Config::getMarketSizingCcEmail() . " to request access",
                403
            );
        }
    }

    /**
     * Market Sizing Tool front page
     */
    public function indexAction()
    {
        $this->view->activeSessions = Myshipserv_Search_MarketSizingDb::getActiveSessions();
        $this->view->pendingRequests = Myshipserv_Search_MarketSizingDb::getPendingRequests();
    }

    /**
     * Parses user TEXTAREA input into an array of keywords (split by lines and commas)
     *
     * @param   string  $strKeywords
     *
     * @return  array
     */
    protected function _prepareKeywords($strKeywords)
    {
        if (strlen($strKeywords) === 0) {
            return array();
        }

        $keywords = array();
        $lines = explode("\n", $strKeywords);

        foreach ($lines as $ln) {
            $ln = strtolower(trim($ln));

            if (strlen($ln) === 0) {
                continue;
            }

            $lnKeywords = explode(",", $ln);
            foreach ($lnKeywords as $kw) {
                $kw = trim($kw);

                if (strlen($kw) === 0) {
                    continue;
                }

                $keywords[] = $kw;
            }
        }

        return array_unique($keywords);
    }

    /**
     * Creates a request in the queue table
     *
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Myshipserv_Search_MarketSizing_Exception_Session
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Myshipserv_Exception_MessagedException
     */
    public function createSessionRequestAction()
    {
        /*
        $email = $this->_getParam('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Myshipserv_Exception_MessagedException(
                "You need to specify a valid email to send the report to"
            );
        }
        */

        $keywordsInclude = $this->_prepareKeywords($this->_getParam('incKeywords'));
        if (empty($keywordsInclude)) {
            throw new Myshipserv_Exception_MessagedException(
                "You need to specify at least one keyword to search for"
            );
        }
        $keywordsExclude = $this->_prepareKeywords($this->_getParam('excKeywords'));

        $filters = array();

        $locations = $this->_getParam('location');
        if (strlen($locations) and ($locations !== 'Globally')) {
            $countryCodes = array();
            foreach (explode(',', $locations) as $code) {
                $code = trim($code);
                if (strlen($code)) {
                    $countryCodes[] = $code;
                }
            }

            $filters[Myshipserv_Search_MarketSizingDb::FILTER_LOCATIONS] = $countryCodes;
        } else {
            $filters[Myshipserv_Search_MarketSizingDb::FILTER_LOCATIONS] = null;
        }

        $vesselType = $this->_getParam('vesselType');
        if (strlen($vesselType) and ($vesselType !== 'null')) {
            $filters[Myshipserv_Search_MarketSizingDb::FILTER_VESSEL_TYPE] = $vesselType;
        } else {
            $filters[Myshipserv_Search_MarketSizingDb::FILTER_VESSEL_TYPE] = null;
        }

        $filters[Myshipserv_Search_MarketSizingDb::FILTER_DATE_FROM] = DateTime::createFromFormat(
            'd/m/Y',
            $this->_getParam('fromDate')
        );
        $filters[Myshipserv_Search_MarketSizingDb::FILTER_DATE_TO] = DateTime::createFromFormat(
            'd/m/Y',
            $this->_getParam('toDate')
        );

        $tool = new Myshipserv_Search_MarketSizingDb(
            false,
            implode(PHP_EOL, $keywordsInclude),
            $keywordsExclude,
            $filters
        );

        $user = Shipserv_User::getUser();
        $email = $user->getEmail();

        $this->view->requestId = $tool->createSessionRequest($email);
        $this->view->pendingRequests = Myshipserv_Search_MarketSizingDb::getPendingRequests();
    }
}