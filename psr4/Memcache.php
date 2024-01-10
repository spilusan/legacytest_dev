<?php

class Memcache {
    private $memcached;

    public function __construct() {
        $this->memcached = new Memcached();
    }

    public function addServer(string $host, int $port) {
        return $this->memcached->addServer($host, $port);
    }

    public function connect(string $host, int $port) {
        return $this->pconnect($host, $port, 0);
    }

    public function pconnect(string $host, int $port, int $timeout = 0) {
        return $this->memcached->addServer($host, $port, $timeout);
    }

    public function set($key, Mixed $value, ?int $expiration = 0) {
        return $this->memcached->set($key, $value, $expiration ?? 0);
    }

    public function get($key): Mixed {
        return $this->memcached->get($key);
    }

    public function delete($key) {
        return $this->memcached->delete($key);
    }

    public function flush(): bool
    {
       return $this->memcached->flush();
    }

    public function getResultCode() {
        return $this->memcached->getResultCode();
    }

    public function replace($key, $value, $expiration = 0)
    {
        // Emulating replace behavior
        $existingValue = $this->get($key);
        if ($existingValue !== false) {
            $this->set($key, $value, $expiration);
            return true;
        }
        return false;
    }
}
