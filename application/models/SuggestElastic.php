<?php

/**
 * 建议词搜索模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年9月11日11:14:27
 */
class SuggestElasticModel extends SearchModel
{
    private $agent;         //agent
    private $realIP;        //用户IP
    private $bizFlag;       //业务标识
    private $requestParams; //请求参数数组
    private $pageSize;
    private $maxPageNum;
    private $maxMatch;
    private $otherMaxMatch;
    private $otherMaxPageNum;
    private $suggestServiceESHost;
    private $suggestServiceESPort;
    private $suggestServiceESIndex;
    private $suggestServiceESType;
    private $cache;
    private $expire;
    private $ES_timeout;             //ES查询超时时间
    private $ES_dfs_query_then_fetch;//严格相关度搜索

    /**
     * suggest搜索操作模型
     */
    public function __construct($bizFlag)
    {
        $this->requestParams      = array();
        $this->agent              = '';
        $this->realIP             = '';
        $this->bizFlag            = $bizFlag;

        $bizIndexConfig           = Conf_Sets::$bizElasticSets[$this->bizFlag];
        $this->pageSize           = isset($bizIndexConfig['pageSize']) && $bizIndexConfig['pageSize'] ? $bizIndexConfig['pageSize'] : 10;
        $this->maxPageNum         = isset($bizIndexConfig['maxPageNum']) && $bizIndexConfig['maxPageNum'] ? $bizIndexConfig['maxPageNum'] : 20;
        $this->maxMatch           = isset($bizIndexConfig['maxMatch']) && $bizIndexConfig['maxMatch'] ? $bizIndexConfig['maxMatch'] : $this->pageSize * $this->maxPageNum;
        $this->otherMaxMatch      = isset($bizIndexConfig['otherMaxMatch']) && $bizIndexConfig['otherMaxMatch'] ? $bizIndexConfig['otherMaxMatch'] : $this->pageSize * $this->maxPageNum * 2;
        $this->otherMaxPageNum    = isset($bizIndexConfig['otherMaxPageNum']) && $bizIndexConfig['otherMaxPageNum'] ? $bizIndexConfig['otherMaxPageNum'] : 20;
        
        $searchConfig             = Yaf\Registry::get('g_config')->search->toArray();
        $useIndex                 = $bizIndexConfig['index'];
        $serviceKey               = $useIndex. 'ServiceES';
        $suggestServiceES_Cfg     = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if(empty($suggestServiceES_Cfg)) {
            return false;
        }
        $this->suggestServiceESHost  = $suggestServiceES_Cfg['host'];
        $this->suggestServiceESPort  = $suggestServiceES_Cfg['port'];
        $this->suggestServiceESIndex = "suggest";
        $this->suggestServiceESType  = "suggest";
        $cacheKey                    = isset($bizIndexConfig['cacheName']) ? $bizIndexConfig['cacheName'] : $useIndex. 'Cache';
        $cacheServers                = $searchConfig[$cacheKey];
        $cacheKeyFix                 = $bizIndexConfig['cacheKeyFix'];
        $cacheType                   = $bizIndexConfig['cacheType'];
        if(!empty($cacheServers)) {
            $this->cache = new Tool_SearchCache($cacheServers, $cacheType, $cacheKeyFix, true);
            if($this->cache->getConnectStatus() === false) {
                $this->cache = null;
            }
        } else {
            $this->cache = null;
        }
        $this->expire = -1;
        $this->ES_timeout            = 2;
        $this->ES_dfs_query_then_fetch = false;
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
     * 设置用户IP
     */
    public function setIP($realIP)
    {
        $this->realIP = $realIP;
        return true;
    }
    
    
    /**
     * 设置分页每页数量
     */
    public function setPageSize($pageSize)
    {
        if(!$this->bizFlag) {
            return false;
        }
        $bizIndexConfig           = Conf_Sets::$bizElasticSets[$this->bizFlag];
        $this->pageSize           = $pageSize;
        $this->maxMatch           = $this->pageSize * $bizIndexConfig['maxPageNum'] > $bizIndexConfig['maxMatch'] ? $bizIndexConfig['maxMatch'] : $this->pageSize * $bizIndexConfig['maxPageNum'];
        $this->otherMaxMatch      = $this->pageSize * $bizIndexConfig['maxPageNum'] * 2 > $bizIndexConfig['otherMaxMatch'] ? $bizIndexConfig['otherMaxMatch'] : $this->pageSize * $bizIndexConfig['maxPageNum'] * 2;
        return true;
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
    
    /**
     * 解析请求参数为搜索条件
     * 
     * @param array $requestParams
     * @return array
     */
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
        $searchParams['fields'] = array("word");
        
        $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);

        $searchParams['limit'] = array(
            'from' => 0,
            'size' => $this->pageSize
        );
        $searchParams['sort'] = array(array('field' => 'querynum', 'order' => 'desc'));
        
        if (isset($requestParams['wordname']) && $requestParams['wordname']) {
            $wordname = $this->requestParams['wordname'];
            $searchParams['query']['type'] = 'bool';
            if(preg_match('/^[a-zA-Z]+$/', $wordname)){
                $searchParams['query']['must-dis_max']['queries'][] = array('type' => 'prefix', 'field' => 'word', 'key' => $wordname);
                $searchParams['query']['must-dis_max']['queries'][] = array('type' => 'prefix', 'field' => 'py_word', 'key' => $wordname);
                $searchParams['query']['must_not-dis_max']['queries'][] = array('type' => 'match_phrase', 'field' => 'word', 'key' => $wordname);
                $searchParams['query']['must_not-dis_max']['queries'][] = array('type' => 'match_phrase', 'field' => 'py_word', 'key' => $wordname);
            } else {
                $searchParams['query']['must-dis_max']['queries'][] = array('type' => 'prefix', 'field' => 'word', 'key' => $wordname);
                $searchParams['query']['must_not-dis_max']['queries'][] = array('type' => 'match_phrase', 'field' => 'word', 'key' => $wordname);
            }
        }
        
        $this->searchParams = $searchParams;
        return $searchParams;
    }
        
    /**
     * 跟据用户条件获得productList
     */
    public function getSuggestList()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->suggestServiceESHost, $this->suggestServiceESPort, $this->suggestServiceESIndex, $this->suggestServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r(ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array()));exit;
        return $this->formatSearchData($result);
    }
    
    public function formatSearchData($result)
    {
        $returnList = array(

        );
        if(empty($result)) {
            return $returnList;
        }
        if(isset($result['hits']) && isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
            foreach($result['hits']['hits'] as $v) {
                $returnList[] = $v['_source']['word'];
            }
        }
        
        return $returnList;
    }
}
?>