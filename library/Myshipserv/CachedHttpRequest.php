<?php

/**
 * Class Myshipserv_CachedHttpRequest
 *
 * Forward a URL then cache it
 */
class Myshipserv_CachedHttpRequest
{
    const HTTP_CACHE_TTL = 3600;
    const REQUEST_TIMEOUT = 30;

    protected $client;
    protected $memcache;
    protected $hasMemcache;

    /**
     * Create cache and HTTP requests
     *
     * Myshipserv_CachedHttpRequest constructor.
     * @throws Myshipserv_Exception_MessagedException
     */
    public function __construct()
    {
        $this->memcache = Shipserv_Memcache::getMemcache();
        $this->hasMemcache = ($this->memcache instanceof Memcache);
        $this->client = new Zend_Http_Client();

        try {
            $this->client->setConfig(
                array(
                    'maxredirects' => 0,
                    'timeout' => self::REQUEST_TIMEOUT
                )
            );
            $this->client->setHeaders('Accept-Language', 'en');
            $this->client->setMethod(Zend_Http_Client::GET);
        } catch (Zend_Http_Client_Exception $e) {
            throw new Myshipserv_Exception_MessagedException('Error creating HTTP request object ' .  $e->getMessage(), 500);
        }
    }

    /**
     * Get the request and cache it, or return from cache
     *
     * @param string $url
     * @param array $params
     * @return bool|mixed
     * @throws Zend_Http_Client_Exception
     */
    public function getRequest($url, $params = array())
    {
        Myshipserv_CAS_CasRest::getInstance()->sessionWriteClose();
        $memcacheKey = 'CATALOG:' . md5($url . '_' . serialize($params));
        if ($this->hasMemcache === true) {
            $data = $this->memcache->get($memcacheKey);
            if ($data) {
                return json_decode($data, true);
            }
        }

        $this->client->setUri($url);
        $this->client->resetParameters();
        $this->client->setParameterGet($params);
        $response = $this->client->request();

        if ($response->getStatus() === 200) {
            $body = $response->getBody();
            if ($this->hasMemcache === true) {
                $this->memcache->set($memcacheKey, $body, null, self::HTTP_CACHE_TTL);
            }
            return json_decode($body, true);
        }

        return false;
    }
}
