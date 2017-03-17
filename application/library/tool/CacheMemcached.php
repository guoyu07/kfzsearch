<?php

/**
 * Memcached类
 * @author xinde <zxdxinde@gmail.com>
 * @date   2014年8月29日12:13:01
 */

class Tool_CacheMemcached 
{
    private $mc;
    private $connStatus;
    
    /**
     * 构造函数。
     * @param array  $servers       memcached服务地址，格式为：
     *                              $servers = array(
     *                                         array('host'=>'127.0.0.1','port'=>11211,'weight'=>1), 
     *                                         array('host'=>'127.0.0.1','port'=>11212,'weight'=>1))
     * @param string $keyprefix     key的前缀。
     * @param boolean $distribution 是否采用分布式。
     */
    public function __construct($servers, $keyprefix = '', $distribution = true)
    {
        $this->mc = new Memcached();
 
        if($keyprefix !== '') {
            $this->mc->setOption(Memcached::OPT_PREFIX_KEY, $keyprefix);
        }
        
        if ($distribution) { 
            $this->mc->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);//开启一致性哈希算法
            $this->mc->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);  //开启ketama算法兼容
            $this->mc->setOption(Memcached::OPT_REMOVE_FAILED_SERVERS, true); //移除失效服务器
            
        }
        
        //$this->mc->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_IGBINARY); //编译memcached模块时需要指定--enable-memcached-igbinary
        $this->mc->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
        $this->mc->setOption(Memcached::OPT_TCP_NODELAY, true);       //关闭延迟
        $this->mc->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 2); //重连次数
        $this->mc->addServers($servers);                              //将服务器增加到连接池
        if(!$this->mc) {
            $this->connStatus = false;
        } else {
            $this->connStatus = true;
        }
    }
    
    public function getConnectStatus()
    {
        return $this->connStatus;
    }
    
    /**
     * 把(key,value)存放到缓存。
     * @param string $key
     * @param mixed  $value
     * @param int    $expire 过期时间，单位s，默认为0，永不过期。
     * @return boolean 
     */
    public function set($key, $value, $expire = 0)
    {
        return $this->mc->set($key, $value, $expire);
    }
    
    /**
     * 从缓存里取出key对应的值。
     * @param string $key
     * @return false: error, NULL: key not found.
     */
    public function get($key)
    {
        $value = $this->mc->get($key);         // Note that this function can return NULL as FALSE
        $rescode = $this->mc->getResultCode(); 
        if(!$value && $rescode == Memcached::RES_NOTFOUND)
            return NULL;
        else if($rescode == Memcached::RES_SUCCESS)
            return $value;
        else
            return false;
    }
    
     /**
     * 从缓存里删除key。
     * @param string $key
     * @return true: ok, false: error, NULL: key not found.
     */
    public function delete($key)
    {
        $r = $this->mc->delete($key);
        $rescode = $this->mc->getResultCode();
        if(!$r && $rescode == Memcached::RES_NOTFOUND)
            return NULL;
        else if(!$r)
            return false;
        else
            return $r;
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
        return $this->mc->setMultiByKey($server_key, $items, $expire);
    }

    public function getMultiByKey($server_key, $keys)
    {
        return $this->mc->getMultiByKey($server_key, $keys);
    }

}