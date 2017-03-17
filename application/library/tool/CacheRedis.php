<?php

/**
 * Redis类
 * @author xinde <zxdxinde@gmail.com>
 * @date   2014年8月29日11:57:06
 */

class Tool_CacheRedis
{
    private $rc;
    private $keyprefix;
    private $connStatus;
    
    public function __construct($server, $keyprefix = '', $distribution = true)
    {
        $this->rc = new Redis();
        if($this->rc->connect($server['host'], $server['port'], 0) === false) {
            $this->connStatus = false;
        } else {
            $this->connStatus = true;
        }
        $this->keyprefix = $keyprefix;
    }
    
    public function getConnectStatus()
    {
        try {
            $result = $this->rc->ping();
        } catch (RedisException $e) {
                
        }
        $this->connStatus = $result ? true : false;
        return $this->connStatus;
    }
    
    public function set($key, $value, $expire = 0)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        $status = $this->rc->set($key, $value);
        if($expire) {
            $this->rc->expire($key, $expire);
        }
        return $status;
    }
    
    public function get($key)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->get($key);
    }
    
    public function delete($key)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->delete($key);
    }
    
    public function incr($key)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->incr($key);
    }
    
    public function decr($key)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->decr($key);
    }
    
    public function ttl($key)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->ttl($key);
    }
    
    public function expire($key, $time)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->expire($key, $time);
    }
    
    public function exists($key)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->exists($key);
    }
    
    public function keys($preFix = '')
    {
        return $this->rc->keys($preFix. '*');
    }
    
    public function llen($key)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->llen($key);
    }
    
    public function rpush($key, $value)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        $status = $this->rc->rPush($key, $value);
        return $status;
    }
    
    public function lpush($key, $value)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        $status = $this->rc->lPush($key, $value);
        return $status;
    }
    
    public function lrange($key, $start, $end)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->lrange($key, $start, $end);
    }
    
    public function ltrim($key, $start, $end)
    {
        if($this->keyprefix) {
            $key = $this->keyprefix. $key;
        }
        return $this->rc->ltrim($key, $start, $end);
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