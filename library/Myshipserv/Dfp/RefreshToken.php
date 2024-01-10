<?php

use Google\Auth\CredentialsLoader;
use Google\Auth\OAuth2;


/**
 * Getting and updating the refresh token from Google
 *
 * Class Myshipserv_Dfp_RefreshToken
 */
class Myshipserv_Dfp_RefreshToken
{

    /**
     * @var string the OAuth2 scope for the AdWords API
     * @see https://developers.google.com/adwords/api/docs/guides/authentication#scope
     */
    const ADWORDS_API_SCOPE = 'https://www.googleapis.com/auth/adwords';

    /**
     * @var string the OAuth2 scope for the DFP API
     * @see https://developers.google.com/doubleclick-publishers/docs/authentication#scope
     */
    const DFP_API_SCOPE = 'https://www.googleapis.com/auth/dfp';

    /**
     * @var string the Google OAuth2 authorization URI for OAuth2 requests
     * @see https://developers.google.com/identity/protocols/OAuth2InstalledApp#formingtheurl
     */
    const AUTHORIZATION_URI = 'https://accounts.google.com/o/oauth2/v2/auth';

    /**
     * @var string the redirect URI for OAuth2 installed application flows
     * @see https://developers.google.com/identity/protocols/OAuth2InstalledApp#formingtheurl
     */
    const REDIRECT_URI = 'urn:ietf:wg:oauth:2.0:oob';

    protected $oldToken = null;

    /**
     * Get new Refresh Token
     */
    public function getToken()
    {
        $ini = $this->getIni();

        $clientId = $ini['clientId'];
        $clientSecret = $ini['clientSecret'];
        $oldRefreshToken = $ini['refreshToken'];

        $this->oldToken = $oldRefreshToken;

        $PRODUCTS = [
            ['AdWords', self::ADWORDS_API_SCOPE],
            ['DFP', self::DFP_API_SCOPE],
            ['AdWords and DFP', self::ADWORDS_API_SCOPE . ' ' . self::DFP_API_SCOPE]
        ];

        $api = 1;
        $scopes = $PRODUCTS[$api][1];
        $oauth2 = new OAuth2(
            [
                'authorizationUri' => self::AUTHORIZATION_URI,
                'redirectUri' => self::REDIRECT_URI,
                'tokenCredentialUri' => CredentialsLoader::TOKEN_CREDENTIAL_URI,
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'scope' => $scopes
            ]
        );

        printf(
            "Log into the Google account you use for %s and visit the following URL:\n%s\n\n",
            $PRODUCTS[$api][0],
            $oauth2->buildFullAuthorizationUri()
        );

        $code = $this->getOAuth2Credential($oauth2->buildFullAuthorizationUri());

        $oauth2->setCode($code);
        $authToken = $oauth2->fetchAuthToken();

        if ($oldRefreshToken === $authToken['refresh_token']) {
            print 'Your refresh token ' . $authToken['refresh_token'] . ' is the same no action has to be taken' + PHP_EOL;
            exit;
        }

        printf("Your refresh token is: %s\n\n", $authToken['refresh_token']);
        $this->writeRefreshTokenToSetting($authToken['refresh_token']);

    }

    /**
     * Get the parsed INI file content
     *
     * @return array|bool
     */
    protected function getIni()
    {
        $iniFile = $this->getIniPath();
        return parse_ini_file($iniFile);
    }

    /**
     * Get the ini file path
     *
     * @return string
     */
    protected function getIniPath()
    {
        return APPLICATION_PATH . '/configs/adsapi_php.ini';
    }

    /**
     * Get Oath2Credentials
     *
     * @param string $authorizationUrl
     * @return mixed
     */
    protected function getOAuth2Credential($authorizationUrl)
    {
        // In a web application you would redirect the user to the authorization URL
        // and after approving the token they would be redirected back to the
        // redirect URL, with the URL parameter "code" added. For desktop
        // or server applications, spawn a browser to the URL and then have the user
        // enter the authorization code that is displayed.
        print 'Step 1: Go to this url on your browser' . PHP_EOL;
        print '----------------------------------------------------------------------------------------------------------------' . PHP_EOL;
        print $authorizationUrl . PHP_EOL;
        print '----------------------------------------------------------------------------------------------------------------' . PHP_EOL;
        print 'Step 2: Login with username: squid@shipserv.com and password: qwerty86' . PHP_EOL;
        print 'Step 3: Copy paste the authentication token to here: ' . PHP_EOL;

        $stdin = fopen('php://stdin', 'r');
        $code = trim(fgets($stdin));
        fclose($stdin);
        print PHP_EOL;
        return $code;
    }

    /**
     * Write token back to the ini file
     * @param string $token
     */
    protected function writeRefreshTokenToSetting($token)
    {
        $file =  $this->getIniPath();
        if (file_exists($file) === false) {
            print 'File that holds authentication setting is missing! Make sure that you have this on: ' . $file . PHP_EOL;
            exit;
        }

        $pattern = '/refreshToken\ \=\ \"(.+)\"/i';
        $content = file_get_contents($file);

        preg_match_all($pattern, $content, $matches);
        $content = str_replace($matches[1][0], $token, $content);

        file_put_contents($file, $content);

        print 'Success: ' .PHP_EOL;
        print $file . ' has been updated with the OAUTH2 refresh token ' . $token . PHP_EOL;
    }

    /**
     * If we cannot login after changing refresh token we have to put back the previous one
     * User might repeat the process
     */
    public function replaceOldToken()
    {
        if ($this->oldToken === null) {
            print 'Old token could not be replaced as does not exists' . PHP_EOL;
            return;
        }

        $this->writeRefreshTokenToSetting($this->oldToken);
    }

}

