<?php
/**
 * Class Apiservices_ApiForwarderController
 * Forward calls to internal API serices.
 */
class Apiservices_ForwarderController extends Myshipserv_Controller_Action
{
    /**
     * Entry point of API forwarer.
     *
     * @return null
     */
    public function indexAction()
    {
        header('Content-Type: application/json');
        $config = Zend_Registry::get('config');
        $cache = Shipserv_Memcache::getMemcache();
        $hasCache = ($cache instanceof Memcache);

        $requestParts = explode('/reports/catalogue/api', $_SERVER['REQUEST_URI']);
        $relativeUrl = (count($requestParts) > 1) ? $requestParts[1] : '';

        $baseUrl = $config->shipserv->catalogue->api->url;
        $url = $baseUrl.$relativeUrl;
        $cacheKey = 'SP_CAT:'.md5($url);

        if ($hasCache) {
            $cachedResult = $cache->get($cacheKey);
            if ($cachedResult) {
                echo $cachedResult;
                exit;
            }
        }

        $result = file_get_contents($url);

        if ($hasCache && $result) {
            $cache->set($cacheKey, $result, null, 60 * 60 * 24);
        }

        if ($result) {
            echo $result;
        } else {
            echo json_encode(['error' => 'Cannot forward call'.$url]);
        }

        exit;
    }
}
