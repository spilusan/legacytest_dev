<?php

/**
 * Represents a list of unique string keys, and provides unique
 * (string) numeric ids for each key. Useful for obscuring
 * e.g. tracking parameters passed in URLs.
 *
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_KeyObscurer
{
    private $keyMap = array();
    
    /**
     * Add a string key.
     * Internally, key is normalised to capitals.
     */
    public function addKey($plainKey)
    {
        $normKey = $this->normPlainKey($plainKey);
        if (! array_key_exists($normKey, $this->keyMap)) {
            $this->keyMap[$normKey] = count($this->keyMap);
        }
    }
    
	/**
	 * Converts obscured key into plain key.
	 * 
	 * @access public
	 * @param int $obscuredKey
	 * @return string Plain key,
	 *      or null if obscured key does not exist.
	 */
    public function getPlainKey($obscuredKey)
    {
        $seqNum = ($obscuredKey - 32) / 127;
        $plainKey = array_search($seqNum, $this->keyMap);
        if ($plainKey !== false) {
            return $plainKey;
        }
        return null;
    }

	/**
	 * Converts plain string key into obscured numeric string.
	 * 
	 * @access public
	 * @param string $plainKey
	 * @return string a numeric string representing search source,
	 *      or null if plain key does not exist.
	 */    
    public function getObscuredKey($plainKey)
    {
    	
        $normKey = $this->normPlainKey($plainKey);
        if (array_key_exists($normKey, $this->keyMap)) {
        	return $this->keyMap[$normKey] * 127 + 32;
        }
        return null;
    }
    
    private function normPlainKey($key)
    {
        return strtoupper($key);
    }
}
