<?php
/**
 * Controller for market sizing tool webservices and main page
 *
 * @author  Yuriy Akopov
 * @date    2014-11-25
 * @story   S12169
 */
class MarketSizing_ServiceController extends Myshipserv_Controller_Action
{
    /**
     * @throws Myshipserv_Exception_MessagedException
     */
    public function init()
    {
    	parent::init();
    	
        $this->abortIfNotShipMate();
    }

    /**
     * Returns the hierarchy of IHS vessel types to be used in Market Sizing Tool vessel type filter
     * @todo: might be moved to /data/... route if used elsewhere
     *
     * @author  Yuriy Akopov
     * @date    2015-06-11
     * @story   S13558
     */
    public function getVesselTypesAction()
    {
        $cache = Shipserv_Memcache::getMemcache();
        $config = Myshipserv_Config::getIni();

        $cacheKey = implode(
            '_',
            array(
                $config->memcache->client->keyPrefix,
                __METHOD__,
                $config->memcache->client->keySuffix
            )
        );

        if ($cache instanceof Memcache) {
            $types = $cache->get($cacheKey);
        } else {
            $types = null;
        }

        if (!is_array($types)) {
            $ssdb = Shipserv_Helper_Database::getSsreport2Db();

            $select = new Zend_Db_Select($ssdb);
            $select
                ->from(
                    array('ist' => 'IHS_SHIP_TYPE'),
                    array(
                        'IST_CODE',
                        'IST_NAME',
                        'IST_PARENT_CODE'
                    )
                )
                ->order('IST_NAME');

            $bindTypes = function ($parentId = null, $level = 0) use ($select, &$bindTypes) {
                $selectType = clone($select);

                if (is_null($parentId)) {
                    $selectType->where('IST_PARENT_CODE IS NULL');
                } else {
                    $selectType->where('IST_PARENT_CODE = ?', $parentId);
                }

                $rows = $selectType->getAdapter()->fetchAll($selectType);
                $types = array();

                if (!empty($rows)) {
                    foreach ($rows as $typeRow) {
                        $childId = $typeRow['IST_CODE'];

                        $types[$childId] = array(
                            'id'       => $childId,
                            'level'    => $level,
                            'name'     => /* '[' . $childId . '] ' . */ $typeRow['IST_NAME'],
                            'children' => $bindTypes($childId, $level + 1)
                        );
                    }
                }

                return $types;
            };

            $types = array_merge(
                array(
                    array(
                        'id'        => null,
                        'level'     => 0,
                        'name'      => 'All included',
                        'children'  => array()
                    )
                ),
                $bindTypes()
            );

            if ($cache instanceof Memcache) {
                $cache->set($cacheKey, $types, null, 60 * 60 * 24);
            }
        }

        $this->_helper->json((array)$types);
    }
}
