<?php


/**
 * Predis set session handler to be able to work with prefixes when storing in redis storage
 *
 * Class PredisPrefixedSession
 * @package Shipserv\Cache
 */
class Myshipserv_Predis_PrefixableSession extends \Predis\Session\Handler
{

    protected $prefix = '';

    /**
     * Extend functionality of the original predis class adding a new option
     * 'prefix' => 'customprefix'
     *
     * Myshipserv_Predis_PrefixableSession constructor.
     * @param \Predis\ClientInterface $client
     * @param array $options
     */
    public function __construct(\Predis\ClientInterface $client, array $options = array())
    {

        if (array_key_exists('prefix', $options)) {
            $this->prefix = $options['prefix'];
            unset($options['prefix']);
        }

        parent::__construct($client, $options);
    }

    /**
     * Override read, adding new prefix to the Session ID
     *
     * @param string $session_id
     * @return string
     */
    public function read($session_id)
    {
        return parent::read($this->prefixSession($session_id));
    }

    /**
     * Override write, adding new prefix to the session key
     *
     * @param string $session_id
     * @param mixed $session_data
     * @return bool
     */
    public function write($session_id, $session_data)
    {
        return parent::write($this->prefixSession($session_id), $session_data);
    }

    /**
     * Override destroy to destroy the proper prefixed session
     * @param string $session_id
     * @return bool
     */
    public function destroy($session_id)
    {
        return parent::destroy($this->prefixSession($session_id));
    }

    /**
     * Return with a new prefixed session id
     *
     * @param string $session_id
     * @return string
     */
    protected function prefixSession($session_id)
    {
        return $this->prefix . $session_id;
    }
}


