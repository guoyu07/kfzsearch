<?php

/**
 * 基于memcached和ketama分布式一致性hash算法的search缓存类
 * liuxingzhi@2013.12
 */

class Tool_SearchCache
{
    private $sc;
    private $keyprefix;
    private $connStatus;
    
    public function __construct($server, $cacheType, $keyprefix = '', $distribution = true)
    {
        $caches = array('ssdb' => 'Tool_CacheSsdb', 'redis' => 'Tool_CacheRedis', 'memcached' => 'Tool_CacheMemcached');
        if(!array_key_exists($cacheType, $caches)) {
            $this->connStatus = false;
        } else {
            $cls = $caches[$cacheType];
            $this->sc = new $cls($server, $keyprefix, $distribution);
            if($this->sc->getConnectStatus()) {
                $this->connStatus = true;
            } else {
                $this->connStatus = false;
            }
        }
    }
    
    public function getConnectStatus()
    {
        return $this->sc->getConnectStatus();
    }
    
    public function set($key, $value, $expire = 0)
    {
        return $this->sc->set($key, $value, $expire);
    }
    
    public function get($key)
    {
        return $this->sc->get($key);
    }
    
    public function delete($key)
    {
        return $this->sc->delete($key);
    }
    
    public function incr($key)
    {
        return $this->sc->incr($key);
    }
    
    public function decr($key)
    {
        return $this->sc->decr($key);
    }
    
    public function ttl($key)
    {
        return $this->sc->ttl($key);
    }
    
    public function expire($key, $time)
    {
        return $this->sc->expire($key, $time);
    }
    
    public function exists($key)
    {
        return $this->sc->exists($key);
    }
    
    public function keys($preFix = '')
    {
        return $this->sc->keys($preFix. '*');
    }
    
    public function llen($key)
    {
        return $this->sc->llen($key);
    }
    
    public function rpush($key, $value)
    {
        return $this->sc->rpush($key, $value);
    }
    
    public function lpush($key, $value)
    {
        return $this->sc->lpush($key, $value);
    }
    
    public function lrange($key, $start, $end)
    {
        return $this->sc->lrange($key, $start, $end);
    }
    
    public function ltrim($key, $start, $end)
    {
        return $this->sc->ltrim($key, $start, $end);
    }
    
    public function setMultiByKey($server_key, $items, $expire = 0)
    {
        return false;
    }
    
    public function getMultiByKey($server_key, $keys)
    {
        return false;
    }

}