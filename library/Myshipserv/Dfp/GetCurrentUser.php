<?php

use Google\AdsApi\Common\OAuth2TokenBuilder;
// use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\v201902\UserService;
use Google\AdsApi\AdManager\v201902\ServiceFactory;

/**
 * Getting the DFP
 * Authentication info is in /application/configs/adsapi_php.ini (with new API moved from dfp-auth.ini)
 *
 * Class Myshipserv_Dfp_GetCurrentUser
 */
class Myshipserv_Dfp_GetCurrentUser
{

    protected $dfpUser;
    protected $session;

    /**
     * Myshipserv_Dfp_GetCurrentUser constructor.
     * Login, get the user
     */
    public function __construct()
    {
        // Set the home directory for the library
        $settingsPath = APPLICATION_PATH . '/configs';
        putenv('HOME='.$settingsPath);

        // Generate a refreshable OAuth2 credential for authentication.
        $oAuth2Credential = (new OAuth2TokenBuilder())->fromFile()->build();
        // Construct an API session configured from a properties file and the
        // OAuth2 credentials above.
        
        $this->session = (new AdManagerSessionBuilder())->fromFile()->withOAuth2Credential($oAuth2Credential)->build();

        $dfpServices = new ServiceFactory();
        $userService = $dfpServices->createUserService($this->session);
        
        // $userService = $dfpServices->get($this->session, UserService::class);

        // Get the current user.
        
        $this->dfpUser =  $userService->getCurrentUser();
    }

    /**
     * Get the DFP user object
     *
     * @return Google\AdsApi\AdManager\v201902\User
     */
    public function getDfpUser()
    {
        return $this->dfpUser;
    }

    /**
     * Get the DFP session
     *
     * @return \Google\AdsApi\AdManager\AdManagerSession|mixed
     */
    public function getSession()
    {
        return $this->session;
    }

}
