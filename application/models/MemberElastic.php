<?php

/**
 * member elastic搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年9月18日10:54:48
 */
class MemberElasticModel extends SearchModel
{
    private $cache;
    private $cacheKeyFix;
    private $cacheType;
    private $expire;
    private $spider_expire;
    private $maxMatch;
    private $otherMaxMatch;
    private $pageSize;
    private $maxPageNum;
    private $otherMaxPageNum;
    private $index;
    private $bizFlag;                //业务标识
    private $isMakeOr;               //是否支持OR查询
    private $requestParams;          //请求参数数组
    private $otherParams;            //其它扩展参数数组
    private $searchParams;           //解析请求参数数组为搜索参数数组
    private $matchType;              //搜索类型 默认为精确搜索、'fuzzy'为模糊搜索
    private $unLimit;                //不受限类型
    private $isBuildSnippets;        //是否高亮显示
    private $agent;                  //agent
    private $isSpiderFlag;           //判断是否为爬虫
    private $memberServiceESHost;
    private $memberServiceESPort;
    private $memberServiceESIndex;
    private $memberServiceESType;
    private $returnTotal;            //接口返回的total，用于分页
    private $ES_timeout;             //ES查询超时时间
    private $ES_dfs_query_then_fetch;//严格相关度搜索
    
    /**
     * endauction搜索操作模型
     */
    public function __construct()
    {
        $this->cache                 = null;
        $this->cacheKeyFix           = '';
        $this->cacheType             = '';
        $this->expire                = -1;
        $this->spider_expire         = -1;
        $this->maxMatch              = 0;
        $this->otherMaxMatch         = 0;
        $this->pageSize              = 0;
        $this->index                 = '';
        $this->bizFlag               = '';
        $this->isMakeOr              = 0;
        $this->requestParams         = array();
        $this->otherParams           = array();
        $this->searchParams          = array();
        $this->maxPageNum            = 0;
        $this->otherMaxPageNum       = 0;
        $this->matchType             = '';
        $this->unLimit               = false;
        $this->isBuildSnippets       = false;
        $this->agent                 = '';
        $this->isSpiderFlag          = false;
        $this->memberServiceESHost   = '';
        $this->memberServiceESPort   = '';
        $this->memberServiceESIndex  = '';
        $this->memberServiceESType   = '';
        $this->returnTotal           = 0;
        $this->ES_timeout            = 10;
        $this->ES_dfs_query_then_fetch = true;
    }
    
    public function init($bizFlag)
    {
        $this->bizFlag               = $bizFlag;
        $this->isSpiderFlag          = $this->isSpider($this->agent);
        $searchConfig                = Yaf\Registry::get('g_config')->search->toArray();
        $bizIndexConfig              = Conf_Sets::$bizElasticSets[$this->bizFlag];
        if(isset($bizIndexConfig['forceSpider']) && $bizIndexConfig['forceSpider'] == 1) {
            $this->isSpiderFlag   = true;
        } elseif (isset($bizIndexConfig['forceSpider']) && $bizIndexConfig['forceSpider'] == 0) {
            $this->isSpiderFlag   = false;
        }
        $useIndex                    = $this->isSpiderFlag && isset($bizIndexConfig['spiderIndex']) ? $bizIndexConfig['spiderIndex'] : $bizIndexConfig['index'];
        $serviceKey                  = $useIndex. 'ServiceES';
        $memberServiceES_Cfg         = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if(empty($memberServiceES_Cfg)) {
            return false;
        }
        $this->memberServiceESHost  = $memberServiceES_Cfg['host'];
        $this->memberServiceESPort  = $memberServiceES_Cfg['port'];
        $this->memberServiceESIndex = 'member';
        $this->memberServiceESType  = 'member';
        $this->pageSize              = isset($bizIndexConfig['pageSize']) && $bizIndexConfig['pageSize'] ? $bizIndexConfig['pageSize'] : 50;
        $this->maxPageNum            = isset($bizIndexConfig['maxPageNum']) && $bizIndexConfig['maxPageNum'] ? $bizIndexConfig['maxPageNum'] : 50;
        $this->maxMatch              = isset($bizIndexConfig['maxMatch']) && $bizIndexConfig['maxMatch'] ? $bizIndexConfig['maxMatch'] : $this->pageSize * $this->maxPageNum;
        $this->otherMaxMatch         = isset($bizIndexConfig['otherMaxMatch']) && $bizIndexConfig['otherMaxMatch'] ? $bizIndexConfig['otherMaxMatch'] : $this->pageSize * $this->maxPageNum * 2;
        $this->otherMaxPageNum       = isset($bizIndexConfig['otherMaxPageNum']) && $bizIndexConfig['otherMaxPageNum'] ? $bizIndexConfig['otherMaxPageNum'] : 100;
        $this->unLimit               = isset(Conf_Sets::$bizSets[$this->bizFlag]['unlimit']) && Conf_Sets::$bizSets[$this->bizFlag]['unlimit'] == true ? true : false;
        $this->returnTotal           = $this->maxMatch;
        $this->expire                = -1;
        $this->spider_expire         = -1;
        $cacheKey                    = $this->isSpiderFlag && isset($bizIndexConfig['spiderCacheName']) ? $bizIndexConfig['spiderCacheName'] : (isset($bizIndexConfig['cacheName']) ? $bizIndexConfig['cacheName'] : $useIndex. 'Cache');
        $cacheServers                = $searchConfig[$cacheKey];
        $cacheKeyFix                 = $bizIndexConfig['cacheKeyFix'];
        $cacheType                   = $bizIndexConfig['cacheType'];
        if(!empty($cacheServers)) {
            $this->cache = new Tool_SearchCache($cacheServers, $cacheType, $cacheKeyFix, true);
            if($this->cache->getConnectStatus() === false) {
                $this->cache = null;
            }
        }
        $this->ES_timeout            = 10;
        $this->ES_dfs_query_then_fetch = true;
        return true;
    }
    
    /**
     * 设置agent
     */
    public function setAgent($agent)
    {
        $this->agent = $agent;
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
     * 设置缓存过期时间
     */
    public function setExpire($expire)
    {
        $this->expire = $expire;
    }
    
    /**
     * 设置爬虫缓存过期时间
     */
    public function setSpiderExpire($spider_expire)
    {
        $this->spider_expire = $spider_expire;
    }
    
    /**
     * 获取实际的缓存过期时间
     */
    private function getExpire()
    {
        return $this->isSpiderFlag ? $this->spider_expire : $this->expire;
    }
    
    /**
     * 可设置为模糊搜索
     */
    public function setMatchType($matchType)
    {
        $this->matchType = $matchType;
    }
    
    /**
     * 设置是否高亮
     */
    public function setBuildSnippets($isBuildSnippets)
    {
        $this->isBuildSnippets = $isBuildSnippets;
    }
    
    /**
     * 设置其它扩展参数数组
     */
    public function setOtherParams($otherParams)
    {
        $this->otherParams = $otherParams;
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
        
        $searchParams['fields'] = array("userid","username","nickname");
        
        $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $searchParams['filter']['must'][] = array('field' => 'isdelete', 'value' => 0);
        $searchParams['filter']['must'][] = array('field' => 'isforbidden', 'value' => 0);
        
        $searchParams['limit'] = array(
            'from' => 0,
            'size' => $this->pageSize
        );
        
        if($this->matchType == 'fuzzy') {
            $this->matchType = 'fuzzy';
        }

        if (isset($requestParams['pagenum']) && intval($requestParams['pagenum'])) {
            $pageNum = intval($requestParams['pagenum']) <= 1 ? 1 : intval($requestParams['pagenum']);
            if ($pageNum > $this->maxPageNum && (!isset($requestParams['getmore']) || !$requestParams['getmore']['value'])) {
                $pageNum = 1;
            } elseif ($pageNum > $this->otherMaxPageNum && isset($requestParams['getmore']) && $requestParams['getmore']) {
                $pageNum = 1;
            }
            if(isset($requestParams['getmore']) && $requestParams['getmore']) {
                $this->returnTotal = $this->otherMaxMatch;
            }
            $searchParams['limit'] = array(
                'from' => ($pageNum - 1) * $this->pageSize,
                'size' => $this->pageSize
            );
        }
        
        if (isset($requestParams['username']) && $requestParams['username']) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_username', 'value' => $this->fan2jian($requestParams['username']));
        }
        if (isset($requestParams['nickname']) && $requestParams['nickname']) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_nickname', 'value' => $this->fan2jian($requestParams['nickname']));
        }
        if($this->isBuildSnippets) { //高亮
            $searchParams['highlight'] = array('pre_tags' => array('<b>'), 'post_tags' => array('</b>'), 'fields' => array(array('field' => '_username'), array('field' => '_nickname')) );
        }

        $this->searchParams = $searchParams;
//        echo '<pre>';
//        print_r($this->searchParams);exit;
        return $searchParams;
    }
    
    /**
     * 格式化搜索数据数组
     */
    private function formatSearchData($result)
    {
        $returnList = array(
            
        );
        if(empty($result)) {
            return $returnList;
        }
        if(isset($result['hits']) && isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
            foreach($result['hits']['hits'] as $v) {
                $tmp = $v['_source'];
                $tmp['id'] = $v['_id'];
                if(isset($v['highlight']) && isset($v['highlight']['_username'])) {
                    $tmp['username_snippet'] = $v['highlight']['_username'][0];
                } else {
                    $tmp['username_snippet'] = $v['_source']['username'];
                }
                if(isset($v['highlight']) && isset($v['highlight']['_nickname'])) {
                    $tmp['nickname_snippet'] = $v['highlight']['_nickname'][0];
                } else {
                    $tmp['nickname_snippet'] = $v['_source']['nickname'];
                }
                $returnList[] = $tmp;
            }
            $returnList['total'] = $this->returnTotal > $result['hits']['total'] ? $result['hits']['total'] : $this->returnTotal;
            $returnList['total_found'] = $result['hits']['total'];
            $returnList['time'] = 0;
        }
        
        return $returnList;
    }
    
    /**
     * 查询会员列表
     */
    public function getUserList()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $result = $this->getCache($this->searchParams);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->memberServiceESHost, $this->memberServiceESPort, $this->memberServiceESIndex, $this->memberServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($this->searchParams, $result);
            }
        }
        
        return $this->formatSearchData($result);
    }
    
}

?>