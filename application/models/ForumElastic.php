<?php

/**
 * 论坛搜索模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年12月15日11:26:00
 */
class ForumElasticModel extends SearchModel
{
    private $requestParams;
    private $searchParams;
    private $serviceESHost;
    private $serviceESPort;
    private $serviceESIndex;
    private $serviceESType;
    private $ES_timeout;             //ES查询超时时间
    private $ES_dfs_query_then_fetch;//严格相关度搜索
    private $bizFlag;
    private $agent;
    private $clusterMaxLoad;         //系统最高负载
    private $cache;
    private $cacheKeyFix;
    private $cacheType;

    /**
     * 论坛搜索模型
     */
    public function __construct()
    {
        $this->expire                = -1;
    }
    
    public function init()
    {
        if($this->bizFlag == '') {
            return false;
        }
        //系统最高负载
        $this->clusterMaxLoad = 50;
        $this->requestParams      = array();
        $this->searchParams       = array();
        $searchConfig             = Yaf\Registry::get('g_config')->search->toArray();
        $useIndex                 = 'forumSearch';
        $index                    = 'forum_posts';
        $type                     = 'posts';
        $serviceKey               = $useIndex. 'ServiceES';
        $serviceES_Cfg            = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if(empty($serviceES_Cfg)) {
            return false;
        }
        $this->serviceESHost      = $serviceES_Cfg['host'];
        $this->serviceESPort      = $serviceES_Cfg['port'];
//        if(!$this->checkLoad($this->serviceESHost, $this->serviceESPort, $this->clusterMaxLoad)) {
//            return false;
//        }
        $this->serviceESIndex     = $index;
        $this->serviceESType      = $type;
        $this->ES_timeout            = 10;
        $this->ES_dfs_query_then_fetch = true;
        
        $cacheKey                    = $useIndex. 'Cache';
        $cacheServers                = $searchConfig[$cacheKey];
        $cacheKeyFix                 = '';
        $cacheType                   = 'redis';
        if(!empty($cacheServers)) {
            $this->cache = new Tool_SearchCache($cacheServers, $cacheType, $cacheKeyFix, true);
            if($this->cache->getConnectStatus() === false) {
                $this->cache = null;
            }
        }
        
        return true;
    }
    
    /**
     * 设置业务标识，在init之前执行设置
     */
    public function setBizFlag($bizFlag)
    {
        $this->bizFlag = $bizFlag;
        $this->statistics($bizFlag);
    }
    
    /**
     * 设置agent
     */
    public function setAgent($agent)
    {
        $this->agent = $agent;
    }
    
    /**
     * 设置用户IP
     */
    public function setIP($realIP)
    {
        $this->realIP = $realIP;
        //防恶意访问
//        if($realIP && !$this->isSpider($this->agent)) { //非爬虫
        if($realIP) { //人 + 爬虫
            if(substr($realIP, 0, 7) == '192.168' || 
               substr($realIP, 0, 10) == '117.121.31' || 
               substr($realIP, 0, 11) == '116.213.206') { //内网IP不做限制和公司外网IP不做限制
                return true;
            }
            if($this->preventMaliciousAccess($realIP, $this->bizFlag, $this->agent) == true) {
                $this->agent = 'AbnormalAccess';
                return false;
            }
        }
        return true;
    }
    
    /**
     * 设置缓存过期时间
     */
    public function setExpire($expire)
    {
        $this->expire = $expire;
    }
    
    /**
     * 获取实际的缓存过期时间
     */
    private function getExpire()
    {
        return $this->expire;
    }
    
    /**
     * 获取缓存
     */
    private function getCache($searchParamsArr)
    {
        if(!is_array($searchParamsArr) || !$searchParamsArr) {
            return false;
        }
        if($this->cache !== NULL && $this->getExpire() >= 0) {
            $key   = sha1(json_encode($searchParamsArr));
            $value = $this->cache->get($key);
            if($value) {
                return unserialize($value);
            }
        }
        return false;
    }
    
    /**
     * 设置缓存
     */
    private function setCache($searchParamsArr, $searchResult)
    {
        if(!is_array($searchParamsArr) || !$searchParamsArr) {
            return false;
        }
        $expire = $this->getExpire();
        if($this->cache !== NULL && $expire >= 0) {
            $key   = sha1(json_encode($searchParamsArr));
            $this->cache->set($key, serialize($searchResult), $expire);
            return true;
        }
        return false;
    }
    
    public function translateParams($requestParams)
    {
        $this->requestParams = $requestParams;
        $searchParams = array(
            'fields'    => array(),
            'query'     => array(),
            'filter'    => array(),
            'sort'      => array(),
            'limit'     => array(),
            'highlight' => array(),
            'facets'    => array()
        );
        
        $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        
        if (isset($requestParams['order']) && intval($requestParams['order'])) {
            switch (intval($requestParams['order'])) {
                case 1:
                    $searchParams['sort'] = array(array('field' => 'postdate', 'order' => 'desc'));
                    break;
                case 2:
                    $searchParams['sort'] = array(array('field' => 'postdate', 'order' => 'asc'));
                    break;
                default:
                    $searchParams['sort'] = array( array('field' => 'postdate', 'order' => 'desc') );
                    break;
            }
        } else {
            $searchParams['sort'] = array( array('field' => 'postdate', 'order' => 'desc') );
        }
        
        if(isset($requestParams['key']) && !empty($requestParams['key'])) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_subject", "_content"), 'key' => $requestParams['key'], 'minimum_should_match' => '100%', 'type' => 'cross_fields');
        }
        
        if(isset($requestParams['pageNum'])) {
            $pageNum = intval($requestParams['pageNum']) > 1 ? intval($requestParams['pageNum']) : 1;
            $pageSize = isset($requestParams['pageSize']) && intval($requestParams['pageSize']) > 1 ? intval($requestParams['pageSize']) : 20;
            $searchParams['limit'] = array(
                'from' => ($pageNum - 1) * $pageSize,
                'size' => $pageSize
            );
        } else {
            $searchParams['limit'] = array(
                'from' => 0,
                'size' => 20
            );
        }
        
        $searchParams['highlight'] = array('pre_tags' => array('<b>'), 'post_tags' => array('</b>'), 'fields' => array(array('field' => '_subject'), array('field' => '_content')) );
        
        $this->searchParams = $searchParams;
        return $searchParams;
    }
    
    private function formatSearchData($result)
    {
        $returnList = array(
            
        );
        if(empty($result)) {
            return $returnList;
        }
        return $result;
    }
    
    /*
     * 论坛搜索
     */
    public function getForumData()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }

        return $this->formatSearchData($result);
    }
    
}
?>