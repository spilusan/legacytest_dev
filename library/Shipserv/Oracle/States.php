<?php

/**
 * Return list of states by country code
 *
 * Class Shipserv_Oracle_States
 */
class Shipserv_Oracle_States extends Shipserv_Oracle
{

    const CACHE_TTL = 86400;

    /**
     * Return the list of states by country code
     *
     * @param string $countryCode
     * @return array|string
     */
    public function fetchStateByCountryCode($countryCode)
    {
        $sql = '
            SELECT 
              stt_code as code,
              stt_name as name
            FROM
              states
            WHERE
              stt_cnt_country_code = :stateCode
            ORDER BY code  
        ';

        $params = array(
            'stateCode' => $countryCode
        );

        $key = 'STATES:' . md5($sql . '_' . serialize($params));

        return $this->fetchCachedQuery($sql, $params, $key, self::CACHE_TTL);
    }
}
