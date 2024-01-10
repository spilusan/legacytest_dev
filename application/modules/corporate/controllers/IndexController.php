<?php

class Corporate_IndexController extends Myshipserv_Controller_Action
{

    /**
     * @var Bool $noCache  decide whether or not to bypass WP response objects that we normally use/store in cache
     */
    public $noCache = false;

    /**
     * Contorller init method
     * @see Myshipserv_Controller_Action::init()
     */
    public function init()
    {
        parent::init();
        if (!$this->config['wordpress']['cache']['enabled'] || $this->getRequest()->getParam('nocache') == 'true' || $this->getRequest()->getParam('preview') == 'true') {
            $this->noCache = true;
        }
        //context needed for some js loading decisions
        $this->view->isCorporate = true;
    }
    
    
    /**
     * Dynamic route to proxy wp content
     */
    public function indexAction()
    {
        $wpPage = $this->_getCorporatePage(false);
        $this->view->wpTitle = str_replace('#038;', '', $wpPage->htmlTitle);
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }
    
    /**
     * Dynamic route to proxy wp content from blog
     */
    public function postAction()
    {
        $this->_helper->viewRenderer->setScriptAction('index');
        $wpPage = $this->_getCorporatePage(true);
        $this->view->wpTitle = $wpPage->htmlTitle;
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }
    
    /**
     * /info/news-feed/page/<page num>
     */
    public function postIndexAction()
    {
        $this->_helper->viewRenderer->setScriptAction('index');
        $page = $this->getRequest()->getParam('page');
        $wpPage = $this->_getCorporatePageFindingByPost(
            'index',
            'page ' . $page, 
            $page,
            $this->noCache
        );        
        $this->view->wpTitle = $wpPage->htmlTitle;
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }

    /**
     * /info/news-feed/search?query=<search string>
     */
    public function postSearchAction()
    {
        $this->_helper->viewRenderer->setScriptAction('index');
        $wpPage = $this->_getCorporatePageFindingByPost(
            'search',
            $this->getRequest()->getParam('query'),
            $this->getRequest()->getParam('page'),
            $this->noCache
        );        
        $this->view->wpTitle = $wpPage->htmlTitle;
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }

    
    /**
     * /info/news-feed/category
     */
    public function postCategoryAction()
    {
        $this->_helper->viewRenderer->setScriptAction('index');
        $wpPage = $this->_getCorporatePageFindingByPost(
            'category',
            $this->getRequest()->getParam('category'),
            $this->getRequest()->getParam('page'),
            $this->noCache
        );
        $this->view->wpTitle = $wpPage->htmlTitle;
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }

    /**
     * /info/news-feed/2016 (or other years of course)
     */
    public function postArchiveAction()
    {
        $this->_helper->viewRenderer->setScriptAction('index');     
        $wpPage = $this->_getCorporatePageFindingByPost(
            'year',
            $this->getRequest()->getParam('year'),
            $this->getRequest()->getParam('page'),
            $this->noCache
        );
        $this->view->wpTitle = $wpPage->htmlTitle;
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }
    
    /**
     * Post request json end point for submitting contact us form  
     */
    public function contactFormPostAction()
    {
        $jsonResponse = array('success' => 1, 'message' => 'Thanks for contacting us. Our team will reply to your request as soon as possible');
        
        //We only accept POST methof
        if (!$this->getRequest()->isPost()) {
            $jsonResponse['success'] = 1;
            $jsonResponse['message'] = 'Not a post';
            return $this->_printJsonOrRedirect($jsonResponse);            
        }
                
        //Get all expected params and filter them
        $secretTrick = $this->getRequest()->getPost('secretTrick', '');
        $subject = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('sub', ''))));
        $emailGroup = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('group', ''))));
        $name = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('name', ''))));
        $email = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('email', ''))));
        $company = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('company', ''))));
        $jobTitle = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('jobtitle', ''))));
        $phone = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('phone', ''))));
        $website = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('website', ''))));
        $message = _htmlspecialchars(strip_tags(trim($this->getRequest()->getPost('message', ''))));

        //Validation
        if (!strlen($secretTrick) || $secretTrick !== 'forStupidBots') {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (0)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }        
        if (!strlen($subject) || !strlen($emailGroup)) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (1)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!preg_match('/[a-zA-Z0-9-_]/', $emailGroup)) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (2)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a valid email";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!preg_match('/[0-9 \+]/', $phone) || strlen($phone) < 6) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a valid telephone number";
            return $this->_printJsonOrRedirect($jsonResponse);
        }        
        if (!$name || !$company || !$jobTitle || !$phone) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide all the required information (*)";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        
        //Set up vars needed for sending email
        $emailTo = $emailGroup . '@shipserv.com';
        $subject = '[contact us enquiry] ' . $subject;
        $message = $message
                    . '<br/><br/>Name: ' . $name
                    . '<br/><br/>Job title: ' . $jobTitle
                    . '<br/>Phone: ' . $phone
                    . '<br/>Email: ' . $email
                    . '<br/>Company: ' . $company
                    . '<br/>Website: ' . $website;
        
        // Send notification
        $nm = new Myshipserv_NotificationManager($this->getInvokeArg('bootstrap')->getResource('db'));
        $nm->contactUsEmail($emailTo, $subject, $message);
        
        return $this->_printJsonOrRedirect($jsonResponse);
    }
    

    /**
     * Post request json end point for submitting contact us form
     */
    public function landingFormPostAction()
    {
        //Get all expected params and filter them
        $secretTrick = $this->getRequest()->getPost('secretTrick', '');

        $subject = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('sub', '')))
        );
        $email_group = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('group', '')))
        );
        $name = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('name', '')))
        );
        $email = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('email', '')))
        );
        $company = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('company', '')))
        );
        $address = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('address', '')))
        );
        $addressLine1 = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('address-line-1', '')))
        );
        $addressLine2 = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('address-line-2', '')))
        );
        $job_title = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('job_title', '')))
        );
        $phone = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('phone', '')))
        );
        $website = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('website', '')))
        );
        $message = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('message', '')))
        );
        $redirect = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('redirect', '')))
        );

        $name_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('name_is_mandatory', '')))
        );
        $email_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('email_is_mandatory', '')))
        );
        $company_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('company_is_mandatory', '')))
        );
        $address_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('address_is_mandatory', '')))
        );
        $job_title_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('job_title_is_mandatory', '')))
        );
        $phone_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('phone_is_mandatory', '')))
        );
        $website_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('website_is_mandatory', '')))
        );
        $message_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('message_is_mandatory', '')))
        );

        $jsonResponse = array(
            'success' => 1,
            'message' => 'Thanks for contacting us. Our team will reply to your request as soon as possible',
            'url' => $redirect
        );

        //We only accept POST method
        if (!$this->getRequest()->isPost()) {
            $jsonResponse['success'] = 1;
            $jsonResponse['message'] = 'Not a post';
            return $this->_printJsonOrRedirect($jsonResponse);
        }


        //Validation
        if (!strlen($secretTrick) || $secretTrick !== 'forStupidBots') {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (0)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!strlen($subject) || !strlen($email_group)) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (1)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!preg_match('/[a-zA-Z0-9-_]/', $email_group)) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (2)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) && $email_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a valid email";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if ((!preg_match('/[0-9 \+]/', $phone) || strlen($phone) < 6) && $phone_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a valid telephone number";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!$name && $name_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a your name";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!$company && $company_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide your company name";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!$address && $address_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide your company address";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!$job_title && $job_title_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide your job title";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!$website && $website_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide your website";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!$message && $message_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a message";
            return $this->_printJsonOrRedirect($jsonResponse);
        }

        //Set up vars needed for sending email
        $emailTo = $email_group . '@shipserv.com';

        $body = $subject . ' <br />' . ' <br />';
        if ($name) {
            $body .= 'Name: '. $name . ' <br />';
        }
        if ($email) {
            $body .= 'Email: '. $email . ' <br />';
        }
        if ($company) {
            $body .= 'Company: '. $company . ' <br />';
        }
        
        if ($address) {
            $fullAddress = [$address];
            if ($addressLine1) {
                $fullAddress[] = $addressLine1;
            }
            if ($addressLine2) {
                $fullAddress[] = $addressLine2;
            }
            $body .= 'Address: '. implode(', ', $fullAddress) . ' <br />';
            $body .= ' '. $addressLine1 . ' <br />';
            $body .= ' '. $addressLine2 . ' <br />';
        }
        if ($job_title) {
            $body .= 'Job Title: '. $job_title . ' <br />';
        }
        if ($phone) {
            $body .= 'Phone: '. $phone . ' <br />';
        }
        if ($website) {
            $body .= 'Website: '. $website . ' <br />';
        }
        if ($message) {
            $body .= 'Message: '. $message;
        }

        // Send notification

        $nm = new Myshipserv_NotificationManager($this->getInvokeArg('bootstrap')->getResource('db'));
        $nm->contactUsEmail($emailTo, $subject, $body);

        return $this->_printJsonOrRedirect($jsonResponse);
    }

    /**
     * Post request json end point for submitting contact us form
     */
    public function ssoFormPostAction()
    {
        //Get all expected params and filter them
        $secretTrick = $this->getRequest()->getPost('secretTrick', '');

        $subject = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('sub', '')))
        );
        $email_group = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('group', '')))
        );
        $name = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('name', '')))
        );
        $address = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('address', '')))
        );
        $postcode = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('postcode', '')))
        );
        $country = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('country', '')))
        );
        $amount_usb = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('usb_amount', '')))
        );
        $amount_dvd = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('dvd_amount', '')))
        );
        $email = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('email', '')))
        );
        $company = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('company', '')))
        );
        $redirect = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('redirect', '')))
        );

        $email_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('email_is_mandatory', '')))
        );
        $company_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('company_is_mandatory', '')))
        );
        $name_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('name_is_mandatory', '')))
        );

        $address_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('address_is_mandatory', '')))
        );
        $postcode_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('postcode_is_mandatory', '')))
        );
        $country_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('country_is_mandatory', '')))
        );
        $amount_usb_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('usb_amount_is_mandatory', '')))
        );
        $amount_dvd_is_mandatory = _htmlspecialchars(
            strip_tags(trim($this->getRequest()->getPost('dvd_amount_is_mandatory', '')))
        );

        $jsonResponse = array(
            'success' => 1,
            'message' => 'Thanks for contacting us. Our team will reply to your request as soon as possible',
            'url' => $redirect
        );

        //We only accept POST method
        if (!$this->getRequest()->isPost()) {
            $jsonResponse['success'] = 1;
            $jsonResponse['message'] = 'Not a post';
            return $this->_printJsonOrRedirect($jsonResponse);
        }


        //Validation
        if (!strlen($secretTrick) || $secretTrick !== 'forStupidBots') {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (0)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!strlen($subject) || !strlen($email_group)) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (1)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!preg_match('/[a-zA-Z0-9-_]/', $email_group)) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = 'A technical problem occurred while processing your request (2)';
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) && $email_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a valid email";
            return $this->_printJsonOrRedirect($jsonResponse);
        }

        if (!$name && $name_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a your first name";
            return $this->_printJsonOrRedirect($jsonResponse);
        }

        if (!$company && $company_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide your company name";
            return $this->_printJsonOrRedirect($jsonResponse);
        }

        if (!$address && $address_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a address";
            return $this->_printJsonOrRedirect($jsonResponse);
        }


        if (!$postcode && $postcode_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a postcode";
            return $this->_printJsonOrRedirect($jsonResponse);
        }
        if (!$country && $country_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a country";
            return $this->_printJsonOrRedirect($jsonResponse);
        }

        if (null === $amount_usb && $amount_usb_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a USB amount";
            return $this->_printJsonOrRedirect($jsonResponse);
        }

        if (null === $amount_dvd && $amount_dvd_is_mandatory) {
            $jsonResponse['success'] = 0;
            $jsonResponse['message'] = "Please provide a DVD amount";
            return $this->_printJsonOrRedirect($jsonResponse);
        }


        //Set up vars needed for sending email
        $emailTo = $email_group . '@shipserv.com';

        $body = $subject . ' <br />' . ' <br />';
        if ($name) {
            $body .= 'Name : '. $name . ' <br />';
        }
        if ($email) {
            $body .= 'Email: '. $email . ' <br />';
        }
        if ($company) {
            $body .= 'Company: '. $company . ' <br />';
        }
        if ($address) {
            $body .= 'Address: '. $address . ' <br />';
        }
        if ($country) {
            $body .= 'Country: '. $country . ' <br />';
        }
        if ($postcode) {
            $body .= 'Postcode: '. $postcode . ' <br />';
        }
        if ($amount_usb) {
            $body .= 'USB Amount: '. $amount_usb . ' <br />';
        }
        if ($amount_dvd) {
            $body .= 'DVD Amount: '. $amount_dvd . ' <br />';
        }

        // Send notification

        $nm = new Myshipserv_NotificationManager($this->getInvokeArg('bootstrap')->getResource('db'));
        $nm->contactUsEmail($emailTo, $subject, $body);

        return $this->_printJsonOrRedirect($jsonResponse);
    }


    /**
     * Function to help contactFormPostAction printing a json or redirecting
     * 
     * @param Array $jsonResponse
     */
    private function _printJsonOrRedirect($jsonResponse) 
    {
        //This (no ajax call) can only happen if the javascript breaks or if it's not executed.
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $this->redirect('/info/contact-us');
        }
        return $this->_helper->json((array)$jsonResponse);
    }
    
    /**
     * Get the Shipserv_Corporate_WpPage created by searching for some post collection 
     * @param Shipserv_Corporate_WpApiClient::FIND_POST_BY_SEARCH|Shipserv_Corporate_WpApiClient::FIND_POST_BY_CATEGORY|Shipserv_Corporate_WpApiClient::FIND_POST_BY_VALUE|Shipserv_Corporate_WpApiClient::FIND_POST_BY_INDEX $paramName
     * @param String $paramValue
     * @param Int $pageNum  the pagination page number
     * @param Bool $noCache
     * @return Shipserv_Corporate_WpPage 
     */
    private function _getCorporatePageFindingByPost($paramName, $paramValue, $pageNum = 1, $noCache = false)
    {
        $wpPage = Shipserv_Corporate_WpApiClient::findPostsBy($paramName, $paramValue, $pageNum, $noCache);
        if (!$wpPage) {
            throw new Zend_Controller_Action_Exception('This page does not exist', 404);
        }
        return $wpPage;      
    }
    
    
    /**
     * Get the Shipserv_Corporate_WpPage doing a wp-json api call to WP corporate website 
     * @param Bool $isBlogPost
     * @throws Zend_Controller_Action_Exception
     * @return Shipserv_Corporate_WpPage
     */
    private function _getCorporatePage($isBlogPost = false)
    {
        //This is a very hacky trick: marketing is on wp-admin, click on "preview", wordpress route this through post.ph and redirect to a wordpress.shipserv.com url, which our apache redirect to the public www.shipserv.com url, which our php redirect to /info/private-page.
        //In this way marketing can really preview pages before publishing them
        if ($this->getRequest()->getParam('preview') === 'true') {
            $this->redirect('/info/' . ($isBlogPost? 'private-post' : 'private-page') . '/' . $this->getRequest()->getParam('preview_id'));
        }
        
        $slug = $this->getRequest()->getParam('slug');
        if (!$slug) {
            throw new Zend_Controller_Action_Exception('This page does not exist (no page name provide)', 404);
        }
        $expectedUrlPath = '/' . $this->getRequest()->getParam('parentOfParentSlug', '') . '/' . $this->getRequest()->getParam('parentSlug', '') . '/' . $slug;
        $expectedUrlPath = preg_replace('/\/+/', '/', $expectedUrlPath);
        
        //If it is a blog post, we need to use the posts api
        if ($isBlogPost) {
            $wpPage = Shipserv_Corporate_WpApiClient::getPostBySlug($slug, $expectedUrlPath, $this->noCache);
            //If it is a normal page, we wwill use the pages api
        } else {
            $wpPage = Shipserv_Corporate_WpApiClient::getPageBySlug($slug, $expectedUrlPath, $this->noCache);
        }
        
        if (!$wpPage) {
            throw new Zend_Controller_Action_Exception('This page does not exist', 404);
        }
        return $wpPage;
    }
    
    
    /**
     * Shipmates can access pages by id and see the content even if it is private 
     *  
     * This needs a little ahck in WpPage.php to delete the wp-admin bar and margin
     */
    public function privatePageAction()
    {
        //$this->abortIfNotShipMate();
        $this->_helper->viewRenderer->setScriptAction('index');

        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            throw new Zend_Controller_Action_Exception('This page does not exist (no id provided)', 404);
        }
        
        $wpPage = Shipserv_Corporate_WpApiClient::getPageById($id, true);
        if (!$wpPage) {
            throw new Zend_Controller_Action_Exception('This page does not exist, or it is not saved (still a draft?), or are not logged in as shipmate', 404);
        }        
        $this->view->wpTitle = $wpPage->htmlTitle;
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }
    
    
    /**
     * Shipmates can access pages by id and see the content even if it is private 
     *  
     * This needs a little ahck in WpPage.php to delete the wp-admin bar and margin
     */
    public function privatePostAction()
    {
        //$this->abortIfNotShipMate();
        $this->_helper->viewRenderer->setScriptAction('index');
    
        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            throw new Zend_Controller_Action_Exception('This post does not exist (no id provided)', 404);
        }
    
        $wpPage = Shipserv_Corporate_WpApiClient::getPostById($id, true);
        if (!$wpPage) {
            throw new Zend_Controller_Action_Exception('This post does not exist, or it is not saved (still a draft?), or are not logged in as shipmate', 404);
        }
        $this->view->wpTitle = $wpPage->htmlTitle;
        $this->view->wpMetaDescription = $wpPage->htmlDescription;
        $this->view->wpHtmlContent = $wpPage->htmlFullBody;
    }    
    
}
