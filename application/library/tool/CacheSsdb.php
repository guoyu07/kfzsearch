<?php

/**
 * SSDB类
 * @author xinde <zxdxinde@gmail.com>
 * @date   2014年8月29日12:15:58
 */
include_once('SSDB.php');

class Tool_CacheSsdb
{
    private $rc;
    private $keyprefix;
    private $connStatus;
    
    public function __construct($server, $keyprefix = '', $distribution = true)
    {
        try {
            $this->rc = new SimpleSSDB($server['host'], $server['port'], 9000);
            $this->connStatus = true;
        } catch (SSDBException $e) {
            $this->connStatus = false;
        }
        $this->keyprefix = $keyprefix;
    }
    
    public function getConnectStatus()
    {
        return $this->connStatus;
    }
    
    public function set($key, $value, $expire = 0)
    {
        try {
            if($this->keyprefix) {
                $key = $this->keyprefix. $key;
            }
            $status = $this->rc->set($key, $value);
            if($expire) {
                $this->rc->expire($key, $expire);
            }
            return $status;
        } catch (SSDBException $e) {
            return false;
        }
    }
    
    public function get($key)
    {
        try {
            if($this->keyprefix) {
                $key = $this->keyprefix. $key;
            }
            $ttl = $this->rc->ttl($key);
            if($ttl && isset($ttl['0']) && $ttl['0'] == '-1') {
                $this->rc->expire($key, 10);
            }
            return $this->rc->get($key);
        } catch (Exception $ex) {
            return false;
        }
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
        return false;
    }
    
    public function decr($key)
    {
        return false;
    }
    
    public function ttl($key)
    {
        return false;
    }
    
    public function expire($key, $time)
    {
        return false;
    }
    
    public function exists($key)
    {
        return false;
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