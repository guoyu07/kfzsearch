<?php

/**
 * endauction elastic搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年8月26日13:39:14
 */
class EndauctionElasticModel extends SearchModel
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
    private $endauctionServiceESHost;
    private $endauctionServiceESPort;
    private $endauctionServiceESIndex;
    private $endauctionServiceESType;
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
        $this->endauctionServiceESHost  = '';
        $this->endauctionServiceESPort  = '';
        $this->endauctionServiceESIndex = '';
        $this->endauctionServiceESType  = '';
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
        $productServiceES_Cfg        = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if(empty($productServiceES_Cfg)) {
            return false;
        }
        $this->endauctionServiceESHost  = $productServiceES_Cfg['host'];
        $this->endauctionServiceESPort  = $productServiceES_Cfg['port'];
        $this->endauctionServiceESIndex = 'endauction';
        $this->endauctionServiceESType  = 'endauction';
        $this->pageSize              = isset($bizIndexConfig['pageSize']) && $bizIndexConfig['pageSize'] ? $bizIndexConfig['pageSize'] : 50;
        $this->maxPageNum            = isset($bizIndexConfig['maxPageNum']) && $bizIndexConfig['maxPageNum'] ? $bizIndexConfig['maxPageNum'] : 50;
        $this->maxMatch              = isset($bizIndexConfig['maxMatch']) && $bizIndexConfig['maxMatch'] ? $bizIndexConfig['maxMatch'] : $this->pageSize * $this->maxPageNum;
        $this->otherMaxMatch         = isset($bizIndexConfig['otherMaxMatch']) && $bizIndexConfig['otherMaxMatch'] ? $bizIndexConfig['otherMaxMatch'] : $this->pageSize * $this->maxPageNum * 2;
        $this->otherMaxPageNum       = isset($bizIndexConfig['otherMaxPageNum']) && $bizIndexConfig['otherMaxPageNum'] ? $bizIndexConfig['otherMaxPageNum'] : 100;
        $this->unLimit               = isset(Conf_Sets::$bizSets[$this->bizFlag]['unlimit']) && Conf_Sets::$bizSets[$this->bizFlag]['unlimit'] == true ? true : false;
        $this->returnTotal           = $this->maxMatch;
        $this->expire                = 1200;
        $this->spider_expire         = 86400;
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
     * 设置是否为长链接
     */
    public function setIsPersistent($isPersistent)
    {
        $this->isPersistent = $isPersistent;
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
     * 获取分词
     */
    public function getFuzzyWord()
    {
        $fuzzyWordArr = array();
        if(isset($this->requestParams['key']) && isset($this->requestParams['key']['value']) && $this->requestParams['key']['value']) {
            $fuzzyWordArr['key'] = isset($this->requestParams['key']['nocode']) && $this->requestParams['key']['nocode'] == 1 ? htmlspecialchars($this->requestParams['key']['value']) : htmlspecialchars($this->unicode2str($this->requestParams['key']['value']));
            $fuzzyWordArr['fuzzy'] = $fuzzyWordArr['key'];
            $fuzzyJson = ElasticSearchModel::getSegwords($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndexSold, $fuzzyWordArr['key']);
            if($fuzzyJson) {
                $fuzzyArr = json_decode($fuzzyJson, true);
                if(isset($fuzzyArr['tokens']) && $fuzzyArr['tokens']) {
                    $fuzzyWordArr['fuzzy'] = '';
                    foreach($fuzzyArr['tokens'] as $token) {
                        $fuzzyWordArr['fuzzy'] .= $token['token']. ' ';
                    }
                    $fuzzyWordArr['fuzzy'] = trim($fuzzyWordArr['fuzzy']);
                }
            }
        }
        return $fuzzyWordArr;
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
        
        $searchParams['fields'] = array("itemid","userid","catid","nickname","auctionarea","specialarea","area","class","itemname","author","press","beginprice","minaddprice","maxprice","pubdate","pubdate2","years","years2","quality","addtime","prestarttime","begintime","endtime","iscreatetrade","viewednum","bidnum","img","isbn","params","itemstatus","isdeleted","rank","catid1","catid2","catid3","catid4","vcatid","vcatid1","vcatid2","vcatid3","vcatid4","hasimg","area1","area2","paper","printtype","binding","sort","material","form","flag1","flag2");
        
        $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        
        $searchParams['limit'] = array(
            'from' => 0,
            'size' => $this->pageSize
        );
        
        $catVarName          = isset($requestParams['catnum']['isV']) && $requestParams['catnum']['isV'] ? 'vcatid' : 'catid';

        $catTpl = 0;
        if (isset($requestParams['catnum']['value']) && $requestParams['catnum']['value'] != '') {
            if (strpos($requestParams['catnum']['value'], 'h') === false) {
                $catId = $requestParams['catnum']['fullId'];
                $catTpl = $requestParams['catnum']['tpl'];
                switch ($requestParams['catnum']['level']) {
                    case 1:
                        $searchParams['filter']['must'][] = array('field' => $catVarName . '1', 'value' => $catId);
                        if ($requestParams['catnum']['hasLeaf'] == 0) {
                            $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '1', 'size' => 100));
                        } else {
                            $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '2', 'size' => 100));
                        }
                        break;
                    case 2:
                        $searchParams['filter']['must'][] = array('field' => $catVarName . '2', 'value' => $catId);
                        if ($requestParams['catnum']['hasLeaf'] == 0) {
                            $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '2', 'size' => 100));
                        } else {
                            $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '3', 'size' => 100));
                        }
                        break;
                    case 3:
                        $searchParams['filter']['must'][] = array('field' => $catVarName . '3', 'value' => $catId);
                        if ($requestParams['catnum']['hasLeaf'] == 0) {
                            $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '3', 'size' => 100));
                        } else {
                            $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '4', 'size' => 100));
                        }
                        break;
                    case 4:
                        $searchParams['filter']['must'][] = array('field' => $catVarName . '4', 'value' => $catId);
                        $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '4', 'size' => 100));
                        break;
                }
            } else {
                $catidArr = explode('h', $requestParams['catnum']['value']);
                if (count($catidArr) > 0) {
                    $range = array('field' => 'catid');
                    if ($catidArr[0]) {
                        $range['from'] = $catidArr[0];
                    }
                    if (isset($catidArr[1]) && $catidArr[1]) {
                        $range['to'] = $catidArr[1];
                    }
                    $searchParams['filter']['range_must'][] = $range;
                }
            }
        } else {
            $searchParams['facets']['catid_facet'] = array(array('field' => $catVarName. '1', 'size' => 100));
        }
        
        if(isset($requestParams['catnum']['value']) && $requestParams['catnum']['value'] != '') {
            $searchParams['facets']['author_facet'] = array(array('field' => 'author2'));
            $searchParams['facets']['press_facet'] = array(array('field' => 'press2'));
            $searchParams['facets']['years_facet'] = array(array('field' => 'years2'));
        }
        
        if ($catTpl) {
            switch ($catTpl) {
                case 1:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'paper'));
                    $searchParams['facets']['special2_facet'] = array(array('field' => 'printtype'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'paper', 'value' => $requestParams['special1']['value']);
                    }
                    if (isset($requestParams['special2']['value']) && $requestParams['special2']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'printtype', 'value' => $requestParams['special2']['value']);
                    }
                    break;
                case 2:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'binding'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'binding', 'value' => $requestParams['special1']['value']);
                    }
                    break;
                case 3:
                    break;
                case 4:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'sort'));
                    $searchParams['facets']['special2_facet'] = array(array('field' => 'material'));
                    $searchParams['facets']['special3_facet'] = array(array('field' => 'binding'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'sort', 'value' => $requestParams['special1']['value']);
                    }
                    if (isset($requestParams['special2']['value']) && $requestParams['special2']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'material', 'value' => $requestParams['special2']['value']);
                    }
                    if (isset($requestParams['special3']['value']) && $requestParams['special3']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'binding', 'value' => $requestParams['special3']['value']);
                    }
                    break;
                case 5:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'material'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'material', 'value' => $requestParams['special1']['value']);
                    }
                    break;
                case 6:
                    break;
                case 7:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'form'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'form', 'value' => $requestParams['special1']['value']);
                    }
                    break;
                case 8:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'sort'));
                    $searchParams['facets']['special2_facet'] = array(array('field' => 'printtype'));
                    $searchParams['facets']['special3_facet'] = array(array('field' => 'material'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'sort', 'value' => $requestParams['special1']['value']);
                    }
                    if (isset($requestParams['special2']['value']) && $requestParams['special2']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'printtype', 'value' => $requestParams['special2']['value']);
                    }
                    if (isset($requestParams['special3']['value']) && $requestParams['special3']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'material', 'value' => $requestParams['special3']['value']);
                    }
                    break;
                case 9:
                    break;
                case 10:
                    break;
                case 11:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'sort'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'sort', 'value' => $requestParams['special1']['value']);
                    }
                    break;
                case 12:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'material', 'value' => $requestParams['special1']['value']);
                    }
                    break;
                case 13:
                    $searchParams['facets']['special1_facet'] = array(array('field' => 'binding'));
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $searchParams['filter']['must'][] = array('field' => 'binding', 'value' => $requestParams['special1']['value']);
                    }
                    break;
            }
        }
        
        if (isset($requestParams['years']['value']) && $requestParams['years']['value']) {
            $years = $requestParams['years']['value'];
            if (strpos($years, 'h') === false) {
                $searchParams['filter']['must'][] = array('field' => 'years2', 'value' => $years);
            } else {
                $yearsArr = explode('h', $years);
                if ($catTpl == 6) {
                    if (count($yearsArr) > 0) {
                        if ($yearsArr[0]) {
                            $searchParams['filter']['range_must'][] = array('field' => 'pubdate', 'from' => intval($yearsArr[0] . '00'));
                        }
                        if (isset($yearsArr[1]) && $yearsArr[1]) {
                            $searchParams['filter']['range_must'][] = array('field' => 'pubdate2', 'to' => intval($yearsArr[1] . '00'));
                        }
                    }
                } else {
                    if (count($yearsArr) > 0) {
                        $range = array('field' => 'pubdate');
                        if ($yearsArr[0]) {
                            $range['from'] = intval($yearsArr[0] . '00');
                        }
                        if (isset($yearsArr[1]) && $yearsArr[1]) {
                            $range['to'] = intval($yearsArr[1] . '00');
                        }
                        $searchParams['filter']['range_must'][] = $range;
                    }
                }
            }
        }
        
        if(isset($requestParams['itemid']['value']) && $requestParams['itemid']['value'] && is_numeric($requestParams['itemid']['value'])) {
            $searchParams['filter']['must'][] = array('field' => 'itemid', 'value' => $requestParams['itemid']['value']);
        }
        
        if(isset($requestParams['userid']['value']) && $requestParams['userid']['value'] && is_numeric($requestParams['userid']['value'])) {
            $searchParams['filter']['must'][] = array('field' => 'userid', 'value' => $requestParams['userid']['value']);
        }
        
        if (isset($requestParams['location']['value']) && $requestParams['location']['value']) {
            $locationArr = explode('h', $requestParams['location']['value']);
            if ($locationArr[0]) {
                $searchParams['filter']['must'][] = array('field' => 'area1', 'value' => $locationArr[0]);
            }
            if (isset($locationArr[1]) && $locationArr[1]) {
                $searchParams['filter']['must'][] = array('field' => 'area2', 'value' => $locationArr[1]);
            }
        }
        
        $authorArr = array();
        $pressArr = array();
        if (isset($requestParams['author']['value']) && $requestParams['author']['value']) {
            if (strpos($requestParams['author']['value'], 'h') !== false && is_numeric(substr($requestParams['author']['value'], 0, strpos($requestParams['author']['value'], 'h')))) {
                $authorArr = explode('h', $requestParams['author']['value'], 2);
                if (count($authorArr) > 0 && $authorArr[0]) {
                    $searchParams['filter']['must'][] = array('field' => 'iauthor', 'value' => $authorArr[0]);
                }
            } else {
                if(substr($requestParams['author']['value'], 0, 1) == 'h') {
                    $requestParams['author']['value'] = substr($requestParams['author']['value'], 1);
                }
                $author = isset($requestParams['author']['nocode']) && $requestParams['author']['nocode'] == 1 ? $requestParams['author']['value'] : $this->unicode2str($requestParams['author']['value']);
//                $searchParams['query']['type'] = 'dis_max';
//                $searchParams['query']['queries'][] = array('fields' => '_author', 'key' => $this->unicode2str($requestParams['author']['value']), 'minimum_should_match' => '100%');
                
//                $searchParams['filter']['must'][] = array('field' => '_author', 'value' => $this->unicode2str($requestParams['author']['value']));
                
                $searchParams['query']['type'] = 'bool';
                $searchParams['query']['must'][] = array('field' => '_author', 'value' => $this->fan2jian($author), 'type' => 'include');
                $searchParams['query']['should'][] = array('field' => 'author', 'value' => $this->fan2jian($author));
            }
        }
        if (isset($requestParams['press']['value']) && $requestParams['press']['value']) {
            if (strpos($requestParams['press']['value'], 'h') !== false && is_numeric(substr($requestParams['press']['value'], 0, strpos($requestParams['press']['value'], 'h')))) {
                $pressArr = explode('h', $requestParams['press']['value'], 2);
                if (count($pressArr) > 0 && $pressArr[0]) {
                    $searchParams['filter']['must'][] = array('field' => 'ipress', 'value' => $pressArr[0]);
                }
            } else {
                if(substr($requestParams['press']['value'], 0, 1) == 'h') {
                    $requestParams['press']['value'] = substr($requestParams['press']['value'], 1);
                }
                $press = isset($requestParams['press']['nocode']) && $requestParams['press']['nocode'] == 1 ? $requestParams['press']['value'] : $this->unicode2str($requestParams['press']['value']);
//                $searchParams['query']['type'] = 'dis_max';
//                $searchParams['query']['queries'][] = array('fields' => '_press', 'key' => $this->unicode2str($requestParams['press']['value']), 'minimum_should_match' => '100%');
//                
//                $searchParams['filter']['must'][] = array('field' => '_press', 'value' => $this->unicode2str($requestParams['press']['value']));
                $searchParams['query']['type'] = 'bool';
                $searchParams['query']['must'][] = array('field' => '_press', 'value' => $this->fan2jian($press), 'type' => 'include');
                $searchParams['query']['should'][] = array('field' => 'press', 'value' => $this->fan2jian($press));
            }
        }
        if (count($authorArr) > 0 && !$authorArr[0] && isset($authorArr[1]) && $authorArr[1]) {
//            $searchParams['query']['type'] = 'dis_max';
//            $searchParams['query']['queries'][] = array('fields' => '_author', 'key' => $this->unicode2str($authorArr[1]), 'minimum_should_match' => '100%');
//            
//            $searchParams['filter']['must'][] = array('field' => '_author', 'value' => $this->unicode2str($authorArr[1]));
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_author', 'value' => $this->fan2jian($this->unicode2str($authorArr[1])), 'type' => 'include');
            $searchParams['query']['should'][] = array('field' => 'author', 'value' => $this->fan2jian($this->unicode2str($authorArr[1])));
        }
        if (count($pressArr) > 0 && !$pressArr[0] && isset($pressArr[1]) && $pressArr[1]) {
//            $searchParams['query']['type'] = 'dis_max';
//            $searchParams['query']['queries'][] = array('fields' => '_press', 'key' => $this->unicode2str($pressArr[1]), 'minimum_should_match' => '100%');
//            
//            $searchParams['filter']['must'][] = array('field' => '_press', 'value' => $this->unicode2str($pressArr[1]));
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_press', 'value' => $this->fan2jian($this->unicode2str($pressArr[1])), 'type' => 'include');
            $searchParams['query']['should'][] = array('field' => 'press', 'value' => $this->fan2jian($this->unicode2str($pressArr[1])));
        }
        if (isset($requestParams['nickname']['value']) && $requestParams['nickname']['value']) {
            $nickname = isset($requestParams['nickname']['nocode']) && $requestParams['nickname']['nocode'] == 1 ? $requestParams['nickname']['value'] : $this->unicode2str($requestParams['nickname']['value']);
//            $searchParams['query']['type'] = 'dis_max';
//            $searchParams['query']['queries'][] = array('fields' => '_shopname', 'key' => $shopName, 'minimum_should_match' => '100%');
//            
//            $searchParams['filter']['must'][] = array('field' => '_shopname', 'value' => $shopName);
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_nickname', 'value' => $this->fan2jian($nickname));
        }
        $match_phrase_itemname_flag = 0;
        if (isset($requestParams['itemname']['value']) && $requestParams['itemname']['value']) {
            $itemname = isset($requestParams['itemname']['nocode']) && $requestParams['itemname']['nocode'] == 1 ? $requestParams['itemname']['value'] : $this->unicode2str($requestParams['itemname']['value']);
//            $searchParams['query']['type'] = 'dis_max';
//            $searchParams['query']['queries'][] = array('fields' => '_itemname', 'key' => $itemname, 'minimum_should_match' => '100%');
//            
//            $searchParams['filter']['must'][] = array('field' => '_itemname', 'value' => $itemname);
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_itemname', 'value' => $this->fan2jian($itemname));
            $match_phrase_itemname_flag = 1;
        }
        if (isset($requestParams['endtime']['value']) && $requestParams['endtime']['value']) {
            $endtimeArr = explode('h', $requestParams['endtime']['value']);
            if (count($endtimeArr) > 0) {
                $range = array('field' => 'endtime');
                if ($endtimeArr[0] && strlen($endtimeArr[0]) == 8) {
                    $range['from'] = strtotime($endtimeArr[0]);
                }
                if ($endtimeArr[1] && strlen($endtimeArr[1]) == 8) {
                    $range['to'] = strtotime($endtimeArr[1]);
                }
                $searchParams['filter']['range_must'][] = $range;
            }
        }
        if (isset($requestParams['begintime']['value']) && $requestParams['begintime']['value']) {
            $begintimeArr = explode('h', $requestParams['begintime']['value']);
            if (count($begintimeArr) > 0) {
                //年月日
                $range = array('field' => 'begintime');
                if ($begintimeArr[0] && strlen($begintimeArr[0]) == 8) {
                    $range['from'] = strtotime($begintimeArr[0]);
                }
                if ($begintimeArr[1] && strlen($begintimeArr[1]) == 8) {
                    $range['to'] = strtotime($begintimeArr[1]);
                }
                //时间戳
                if($begintimeArr[0] && strlen($begintimeArr[0]) == 10) {
                    $range['from'] = $begintimeArr[0];
                }
                if($begintimeArr[1] && strlen($begintimeArr[1]) == 10) {
                    $range['to'] = $begintimeArr[1];
                }
                $searchParams['filter']['range_must'][] = $range;
            }
        }
        
        if (isset($requestParams['order']['value']) && intval($requestParams['order']['value'])) {
            switch (intval($requestParams['order']['value'])) {
                case 1:
                    $searchParams['sort'] = array(array('field' => 'endtime', 'order' => 'desc'));
                    break;
                case 2:
                    $searchParams['sort'] = array(array('field' => 'maxprice', 'order' => 'asc'));
                    break;
                case 3:
                    $searchParams['sort'] = array(array('field' => 'maxprice', 'order' => 'desc'));
                    break;
                case 4:
                    $searchParams['sort'] = array(array('field' => 'bidnum', 'order' => 'desc'));
                    break;
                case 5:
                    $searchParams['sort'] = array(array('field' => 'bidnum', 'order' => 'asc'));
                    break;
                case 6:
                    $searchParams['sort'] = array(array('field' => 'viewednum', 'order' => 'desc'));
                    break;
                case 7:
                    $searchParams['sort'] = array(array('field' => 'viewednum', 'order' => 'asc'));
                    break;
                case 8:
                    $searchParams['sort'] = array(array('field' => 'beginprice', 'order' => 'desc'));
                    break;
                case 9:
                    $searchParams['sort'] = array(array('field' => 'beginprice', 'order' => 'asc'));
                    break;
                case 10:
                    $searchParams['sort'] = array(array('field' => 'endtime', 'order' => 'asc'));
                    break;
                default:
                    $searchParams['sort'] = array( "_score", array('field' => 'endtime', 'order' => 'desc') );
                    break;
            }
        } else {
            $searchParams['sort'] = array( "_score", array('field' => 'rank', 'order' => 'desc') );
        }
        
        if((isset($requestParams['isfuzzy']['value']) && $requestParams['isfuzzy']['value']) || $this->matchType == 'fuzzy') {
            $this->matchType = 'fuzzy';
        }

        if (isset($requestParams['pagenum']['value']) && intval($requestParams['pagenum']['value'])) {
            $pageNum = intval($requestParams['pagenum']['value']) <= 1 ? 1 : intval($requestParams['pagenum']['value']);
            if ($pageNum > $this->maxPageNum && (!isset($requestParams['getmore']) || !$requestParams['getmore']['value'])) {
                $pageNum = 1;
            } elseif ($pageNum > $this->otherMaxPageNum && isset($requestParams['getmore']) && $requestParams['getmore']['value']) {
                $pageNum = 1;
            }
            if(isset($requestParams['getmore']) && $requestParams['getmore']['value']) {
                $this->returnTotal = $this->otherMaxMatch;
            }
            $searchParams['limit'] = array(
                'from' => ($pageNum - 1) * $this->pageSize,
                'size' => $this->pageSize
            );
        }
        
        if (isset($requestParams['status']['value']) && $requestParams['status']['value']) {
            if($requestParams['status']['value'] != '11') {
                $statusArr = explode('h', $requestParams['status']['value']);
                $statusStr = implode(',', $statusArr);
                $searchParams['filter']['must_in'][] = array('field' => 'itemstatus', 'value' => $statusStr);
            }
        } else {
            $searchParams['filter']['must'][] = array('field' => 'itemstatus', 'value' => 0);
        }
        
        if(isset($requestParams['specialarea']['value']) && $requestParams['specialarea']['value']) {
            $searchParams['filter']['must'][] = array('field' => 'specialarea', 'value' => $requestParams['specialarea']['value']);
        }
        
        $exKey = 0;
        $key = 0;
        if (isset($requestParams['exkey']['value']) && $requestParams['exkey']['value']) {
            $exKey = isset($requestParams['exkey']['nocode']) && $requestParams['exkey']['nocode'] == 1 ? $requestParams['exkey']['value'] : $this->unicode2str($requestParams['exkey']['value']);
            $exKey = $this->fan2jian($exKey);
        }
        if (isset($requestParams['key']['value']) && $requestParams['key']['value']) {
            $key = isset($requestParams['key']['nocode']) && $requestParams['key']['nocode'] == 1 ? $requestParams['key']['value'] : $this->unicode2str($requestParams['key']['value']);
            $key = $this->fan2jian($key);
        }
        
        if((isset($requestParams['isfuzzy']['value']) && $requestParams['isfuzzy']['value']) || $this->matchType == 'fuzzy') {
            $matchType = 'fuzzy';
        }
        
        if ($key !== 0 && $exKey !== 0) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname", "_author","_nickname"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
            $searchParams['query']['must_not-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname", "_author","_nickname"), 'key' => $exKey, 'minimum_should_match' => '100%');
            if($match_phrase_itemname_flag == 0) {
                $searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
                $searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $key, 'type' => 'phrase', 'slop' => 2);
            }
        } elseif ($key !== 0 && $exKey === 0) {
            if ($this->matchType == 'fuzzy') { //模糊搜索
                $searchParams['query']['type'] = 'bool';
                $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname", "_author","_nickname"), 'key' => $key, 'minimum_should_match' => '50%');
                if($match_phrase_itemname_flag == 0) {
                    $searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
                    $searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $key, 'type' => 'phrase', 'slop' => 2);
                }
            } else {
                $searchParams['query']['type'] = 'bool';
                if(preg_match('/^[\s0-9a-zA-Z\s]+$/isU', $key)) {
                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname", "_author","_nickname","py_itemname"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
                } else {
                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname", "_author","_nickname"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
                }
                if($match_phrase_itemname_flag == 0) { //增加相关度
                    $searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
                    $searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $key, 'type' => 'phrase', 'slop' => 2);
                }
            }
        } elseif ($key === 0 && $exKey !== 0) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must_not-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname", "_author","_nickname"), 'key' => $exKey);
        }
        if($this->isBuildSnippets) { //高亮
            $searchParams['highlight'] = array('pre_tags' => array('<b>'), 'post_tags' => array('</b>'), 'fields' => array(array('field' => '_itemname'), array('field' => '_author'), array('field' => '_nickname')) );
        }

        $this->searchParams = $searchParams;
//        echo '<pre>';
//        print_r($this->searchParams);exit;
        return $searchParams;
    }
    
    /**
     * 转换筛选项
     * 
     * @param array $filterArr
     * @param int   $type    如果type为1则对筛选项进行排序
     * @return array
     */
    private function translateFilters($filterArr, $type = 0)
    {
        $otherArr = array(
            '其它',
            '其他',
            '不详',
            '不祥'
        );
        $temp = array();
        $orderArr = array();
        if (isset($filterArr['list']) && !empty($filterArr['list'])) {
            foreach ($filterArr['list'] as $k => &$f) {
                if (!is_array($f)) {
                    unset($filterArr['list'][$k]);
                    continue;
                }
                if (!$f['name']) {
                    unset($filterArr['list'][$k]);
                    continue;
                }
                $f['name'] = htmlspecialchars($f['name']);
                if (in_array($f['name'], $otherArr)) {
                    $temp = $f;
                    unset($filterArr['list'][$k]);
                    continue;
                }
                $orderArr[] = intval($f['id']);
            }
            if ($type) {
                array_multisort($orderArr, SORT_ASC, $filterArr['list']);
            }
            if (count($filterArr['list']) > 8) {
                $filterArr['list'] = array_slice($filterArr['list'], 0, 8);
            }
            if (count($filterArr['list']) < 8 && $temp) {
                $filterArr['list'][] = $temp;
            }
        }
        return $filterArr;
    }
    
    /**
     * 格式化搜索数据数组
     * 
     * 键值为catList的存放的是分类聚类数据
     * 键值为authorList的存放的是作者聚类数据
     * 键值为pressList的存放的是出版社聚类数据
     * 键值为yearsList的存放的是年代聚类数据
     * 键值为special1List的存放的是著录项1聚类数据
     * 键值为special2List的存放的是著录项2聚类数据
     * 键值为special3List的存放的是著录项3聚类数据
     * 键值为itemList的存放的是商品具体数据
     */
    private function formatSearchData($result)
    {
        $returnList = array(
            'itemList' => array(),
            'catList' => array(),
            'authorList' => array(),
            'pressList' => array(),
            'yearsList' => array(),
            'special1List' => array(),
            'special2List' => array(),
            'special3List' => array()
        );
        if(empty($result)) {
            return $returnList;
        }
        if(isset($result['hits']) && isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
            foreach($result['hits']['hits'] as $v) {
                $tmp = $v['_source'];
                $tmp['id'] = $v['_id'];
                if(isset($v['highlight']) && isset($v['highlight']['_itemname'])) {
                    $tmp['itemname_snippet'] = $v['highlight']['_itemname'][0];
                } else {
                    $tmp['itemname_snippet'] = $v['_source']['itemname'];
                }
                if(isset($v['highlight']) && isset($v['highlight']['_author'])) {
                    $tmp['author_snippet'] = $v['highlight']['_author'][0];
                } else {
                    $tmp['author_snippet'] = $v['_source']['author'];
                }
                if(isset($v['highlight']) && isset($v['highlight']['_nickname'])) {
                    $tmp['nickname_snippet'] = $v['highlight']['_nickname'][0];
                } else {
                    $tmp['nickname_snippet'] = $v['_source']['nickname'];
                }
                $returnList['itemList'][] = $tmp;
            }
            $returnList['itemList']['total'] = $this->returnTotal > $result['hits']['total'] ? $result['hits']['total'] : $this->returnTotal;
            $returnList['itemList']['total_found'] = $result['hits']['total'];
            $returnList['itemList']['time'] = 0;
        }
        if(isset($result['facets']) && isset($result['facets']['catid_facet']) && isset($result['facets']['catid_facet']['terms'])) {
            $returnList['catList'] = $result['facets']['catid_facet']['terms'];
        }
        if(isset($result['facets']) && isset($result['facets']['author_facet']) && isset($result['facets']['author_facet']['terms'])) {
            foreach($result['facets']['author_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['author2'] = $term['term'];
                $tmp['num'] = $term['count'];
                $tmp['authorid'] = strval($this->fnv64($tmp['author2']));
                $returnList['authorList'][] = $tmp;
            }
        }
        if(isset($result['facets']) && isset($result['facets']['press_facet']) && isset($result['facets']['press_facet']['terms'])) {
            foreach($result['facets']['press_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['press2'] = $term['term'];
                $tmp['num'] = $term['count'];
                $tmp['pressid'] = strval($this->fnv64($tmp['press2']));
                $returnList['pressList'][] = $tmp;
            }
        }
        if(isset($result['facets']) && isset($result['facets']['years_facet']) && isset($result['facets']['years_facet']['terms'])) {
            foreach($result['facets']['years_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['years2'] = $term['term'];
                $tmp['num'] = $term['count'];
                $returnList['yearsList'][] = $tmp;
            }
        }
        if(isset($result['facets']) && isset($result['facets']['special1_facet']) && isset($result['facets']['special1_facet']['terms'])) {
            foreach($result['facets']['special1_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['id'] = $term['term'];
                $tmp['num'] = $term['count'];
                $returnList['special1List'][] = $tmp;
            }
        }
        if(isset($result['facets']) && isset($result['facets']['special2_facet']) && isset($result['facets']['special2_facet']['terms'])) {
            foreach($result['facets']['special2_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['id'] = $term['term'];
                $tmp['num'] = $term['count'];
                $returnList['special2List'][] = $tmp;
            }
        }
        if(isset($result['facets']) && isset($result['facets']['special3_facet']) && isset($result['facets']['special3_facet']['terms'])) {
            foreach($result['facets']['special3_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['id'] = $term['term'];
                $tmp['num'] = $term['count'];
                $returnList['special3List'][] = $tmp;
            }
        }
        return $returnList;
    }
    
    /**
     * 跟据用户条件获得filterList和productList
     */
    public function getFPWithFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array();
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
        }
        if (!isset($this->requestParams['catnum']['value']) || $this->requestParams['catnum']['value']) {
            if(isset($this->searchParams['facets']['author_facet'])) {
                $facets['author_facet'] = $this->searchParams['facets']['author_facet'];
            }
            if(isset($this->searchParams['facets']['press_facet'])) {
                $facets['press_facet'] = $this->searchParams['facets']['press_facet'];
            }
            if(isset($this->searchParams['facets']['years_facet'])) {
                $facets['years_facet'] = $this->searchParams['facets']['years_facet'];
            }
            if(isset($this->searchParams['facets']['special1_facet'])) {
                $facets['special1_facet'] = $this->searchParams['facets']['special1_facet'];
            }
            if(isset($this->searchParams['facets']['special2_facet'])) {
                $facets['special2_facet'] = $this->searchParams['facets']['special2_facet'];
            }
            if(isset($this->searchParams['facets']['special3_facet'])) {
                $facets['special3_facet'] = $this->searchParams['facets']['special3_facet'];
            }
        }
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->endauctionServiceESHost, $this->endauctionServiceESPort, $this->endauctionServiceESIndex, $this->endauctionServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 用户首次访问获得基础filterList和productList（无筛选）
     */
    public function getFPWithOutFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->endauctionServiceESHost, $this->endauctionServiceESPort, $this->endauctionServiceESIndex, $this->endauctionServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 跟据用户条件获得productList
     */
    public function getPWithFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->endauctionServiceESHost, $this->endauctionServiceESPort, $this->endauctionServiceESIndex, $this->endauctionServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
        
        return $this->formatSearchData($result);
    }
    
    /**
     * 跟据用户条件获得filterList和productList
     */
    public function getOnlyCatFilterForEndItem()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $limit = array(
            'from' => 0,
            'size' => 0
        );
        $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
        $cacheSearchParamsArr = array_merge(array('limit' => $limit), array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->endauctionServiceESHost, $this->endauctionServiceESPort, $this->endauctionServiceESIndex, $this->endauctionServiceESType, 0, array(), array(), $this->searchParams['filter'], array(), $limit, array(), $facets, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['facets']) && isset($result['facets']['catid_facet']) && $result['facets']['catid_facet']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 跟据用户条件获得catsList和productList
     */
    public function getFPWithFilterForFinishedList()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array('catid_facet' => $this->searchParams['facets']['catid_facet']);
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->endauctionServiceESHost, $this->endauctionServiceESPort, $this->endauctionServiceESIndex, $this->endauctionServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 格式化filterList和productList
     */
    public function translateFPWithFilter($searchData)
    {
        $catTpl = 0;
        if ($this->requestParams['catnum']['value']) {
            $catTpl = $this->requestParams['catnum']['tpl'];
        }
        if(empty($searchData)) {
            return $searchData;
        }
        $catsList = $searchData['catList'];
        $authorList = $searchData['authorList'];
        $pressList = $searchData['pressList'];
        $yearsList = $searchData['yearsList'];
        $special1List = $searchData['special1List'];
        $special2List = $searchData['special2List'];
        $special3List = $searchData['special3List'];

        $categoryArr = array();
        $authorArr = array();
        $pressArr = array();
        $yearsArr = array();
        $special1Arr = array();
        $special2Arr = array();
        $special3Arr = array();

        $categoryArr['title'] = '分类浏览';
        $categoryArr['list'] = array();
        $orderCatIds = array();
        $type = isset($this->requestParams['catnum']['isV']) && $this->requestParams['catnum']['isV'] ? 'Data_ItemVCategory' : 'Data_ItemCategory';
        if (!$this->requestParams['catnum']['value']) {
            $tmpCatArray = $type::getTop(); //此处不能用array_keys，因为数据结构已变
            foreach ($tmpCatArray as $tmpCat) {
                $orderCatIds[] = $tmpCat['id'];
            }
        } else {
            $catId = $this->requestParams['catnum']['fullId'];
            if ($this->requestParams['catnum']['hasLeaf']) {
                $catInfo = $type::getItemInfo($catId);
                $orderCatIds = $this->getChildCatIds($catInfo, $this->requestParams['catnum']['isV']);
            }
        }

        if (!empty($catsList)) {
            $newCatsList = array();
            $num2catArr = $this->getnum2catArr();
            foreach ($catsList as $k => $cat) {
                if (!is_array($cat) || !$cat['term']) {
                    continue;
                }
                $cid = sprintf("%.0f", $cat['term']);
                $catInfo = $type::getItemInfo($cid);
                $cat['id'] = CategoryModel::getShortCatId($cid);
                $cat['cid'] = $cid;
                $cat['name'] = $catInfo['name'];
                $cat['num'] = $cat['count'];
                unset($cat['term']);
                unset($cat['count']);
                if (!$cat['name']) {
                    continue;
                }
                $topid = sprintf("%.0f", Tool_CommonData::getTopParentId($cid));
                $topShortId = CategoryModel::getShortCatId($topid);
                $cat['top_pinyin'] = $num2catArr[$topShortId];
                $key = array_search($cid, $orderCatIds);
                $newCatsList[$key] = $cat;
            }
            ksort($newCatsList);
            $categoryArr['list'] = $newCatsList;
        }

        switch ($catTpl) {
            case 1:
                $authorArr['title'] = '作者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $pressArr['title'] = '出版人';
                $pressArr['list'] = array();
                if (!empty($pressList)) {
                    foreach ($pressList as $k => &$press) {
                        if (!is_array($press)) {
                            unset($pressList[$k]);
                            continue;
                        }
                        $press['name'] = $press['press2'];
                        unset($press['press2']);
                        $press['id'] = $press['pressid'];
                        unset($press['pressid']);
                    }
                    $pressArr['list'] = $pressList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }

                $special1Arr['title'] = '纸张';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['paper'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['paper'];
                        $special1['name'] = Data_Paper1::getValueByCode(TRUE, $special1['paper']);
                        unset($special1['paper']);
                    }
                    $special1Arr['list'] = $special1List;
                }

                $special2Arr['title'] = '刻印方式';
                $special2Arr['list'] = array();
                if (!empty($special2List)) {
                    foreach ($special2List as $k => &$special2) {
                        if (!is_array($special2)) {
                            unset($special2List[$k]);
                            continue;
                        }
                        $special2['printtype'] = $special2['term'];
                        unset($special2['term']);
                        $special2['id'] = $special2['printtype'];
                        $special2['name'] = Data_PrintType1::getValueByCode(TRUE, $special2['printtype']);
                        unset($special2['printtype']);
                    }
                    $special2Arr['list'] = $special2List;
                }
                break;
            case 2:
                if ($catId == '37000000000000000') {
                    break;
                }
                $authorArr['title'] = '作者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $pressArr['title'] = '出版社';
                $pressArr['list'] = array();
                if (!empty($pressList)) {
                    foreach ($pressList as $k => &$press) {
                        if (!is_array($press)) {
                            unset($pressList[$k]);
                            continue;
                        }
                        $press['name'] = $press['press2'];
                        unset($press['press2']);
                        $press['id'] = $press['pressid'];
                        unset($press['pressid']);
                    }
                    $pressArr['list'] = $pressList;
                }

                $yearsArr['title'] = '出版时间';
                $yearsArr['type'] = 'input';
                $yearsArr['list'] = array();

                $special1Arr['title'] = '装订';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['binding'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['binding'];
                        $special1['name'] = Data_BindingFull::getValueByCode(TRUE, $special1['binding']);
                        unset($special1['binding']);
                    }
//                    $special1List = $this->turnBindToNew($special1List, $catTpl);
                    $special1Arr['list'] = $special1List;
                }
                break;
            case 3:
                $authorArr['title'] = '作者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }
                break;
            case 4:
                $authorArr['title'] = '题名';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }

                $special1Arr['title'] = '类别';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['sort'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['sort'];
                        $special1['name'] = Data_Sort4::getValueByCode(TRUE, $special1['sort']);
                        unset($special1['sort']);
                    }
                    $special1Arr['list'] = $special1List;
                }

                $special2Arr['title'] = '材质';
                $special2Arr['list'] = array();
                if (!empty($special2List)) {
                    foreach ($special2List as $k => &$special2) {
                        if (!is_array($special2)) {
                            unset($special2List[$k]);
                            continue;
                        }
                        $special2['material'] = $special2['term'];
                        unset($special2['term']);
                        $special2['id'] = $special2['material'];
                        $special2['name'] = Data_Material4::getValueByCode(TRUE, $special2['material']);
                        unset($special2['material']);
                    }
                    $special2Arr['list'] = $special2List;
                }

                $special3Arr['title'] = '装裱形式';
                $special3Arr['list'] = array();
                if (!empty($special3List)) {
                    foreach ($special3List as $k => &$special3) {
                        if (!is_array($special3)) {
                            unset($special3List[$k]);
                            continue;
                        }
                        $special3['binding'] = $special3['term'];
                        unset($special3['term']);
                        $special3['id'] = trim($special3['binding'], '"');
                        $special3['name'] = Data_BindingFull::getValueByCode(TRUE, trim($special3['binding'], '"'));
                        unset($special3['binding']);
                    }
//                    $special3List = $this->turnBindToNew($special3List, $catTpl);
                    $special3Arr['list'] = $special3List;
                }
                break;
            case 5:
                $authorArr['title'] = '作者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }

//                $special1Arr['title'] = '类别';
//                $special1Arr['list'] = array();
//                if(!empty($special1List)) {
//                    foreach($special1List as $k => &$special1) {
//                        if(!is_array($special1)) {
//                            unset($special1List[$k]);
//                            continue;
//                        }
//                        $special1['id'] = $special1['sort'];
//                        $special1['name'] = CommonData::getValueByCode(OptionsList::SORT_5, TRUE, $special1['sort']);
//                        unset($special1['sort']);
//                        if($isMakeUrl) {
//                            $special1['url'] = $this->getUrl($requestParams, 'special1', $special1['id']);
//                        }
//                    }
//                    $special1Arr['list'] = $special1List;
//                }

                $special1Arr['title'] = '材质';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['material'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['material'];
                        $special1['name'] = Data_Material5::getValueByCode(TRUE, $special1['material']);
                        unset($special1['material']);
                    }
                    $special1Arr['list'] = $special1List;
                }
                break;
            case 6:
                $authorArr['title'] = '责任人（主编）';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $pressArr['title'] = '出版单位';
                $pressArr['list'] = array();
                if (!empty($pressList)) {
                    foreach ($pressList as $k => &$press) {
                        if (!is_array($press)) {
                            unset($pressList[$k]);
                            continue;
                        }
                        $press['name'] = $press['press2'];
                        unset($press['press2']);
                        $press['id'] = $press['pressid'];
                        unset($press['pressid']);
                    }
                    $pressArr['list'] = $pressList;
                }

                $yearsArr['title'] = '期号';
                $yearsArr['type'] = 'input';
                $yearsArr['list'] = array();
                break;
            case 7:
                $authorArr['title'] = '绘者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $pressArr['title'] = '出版社';
                $pressArr['list'] = array();
                if (!empty($pressList)) {
                    foreach ($pressList as $k => &$press) {
                        if (!is_array($press)) {
                            unset($pressList[$k]);
                            continue;
                        }
                        $press['name'] = $press['press2'];
                        unset($press['press2']);
                        $press['id'] = $press['pressid'];
                        unset($press['pressid']);
                    }
                    $pressArr['list'] = $pressList;
                }

                $yearsArr['title'] = '出版时间';
                $yearsArr['list'] = array();
                $yearsArr['type'] = 'input';

                $special1Arr['title'] = '形式';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['form'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['form'];
                        $special1['name'] = Data_Form7::getValueByCode(TRUE, $special1['form']);
                        unset($special1['form']);
                    }
                    $special1Arr['list'] = $special1List;
                }
                break;
            case 8:
                $authorArr['title'] = '作者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }

                $special1Arr['title'] = '类别';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['sort'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['sort'];
                        $special1['name'] = Data_Sort8::getValueByCode(TRUE, $special1['sort']);
                        unset($special1['sort']);
                    }
                    $special1Arr['list'] = $special1List;
                }

                $special2Arr['title'] = '印制方式';
                $special2Arr['list'] = array();
                if (!empty($special2List)) {
                    foreach ($special2List as $k => &$special2) {
                        if (!is_array($special2)) {
                            unset($special2List[$k]);
                            continue;
                        }
                        $special2['printtype'] = $special2['term'];
                        unset($special2['term']);
                        $special2['id'] = $special2['printtype'];
                        $special2['name'] = Data_PrintType8::getValueByCode(TRUE, $special2['printtype']);
                        unset($special2['printtype']);
                    }
                    $special2Arr['list'] = $special2List;
                }

                $special3Arr['title'] = '材质';
                $special3Arr['list'] = array();
                if (!empty($special3List)) {
                    foreach ($special3List as $k => &$special3) {
                        if (!is_array($special3)) {
                            unset($special3List[$k]);
                            continue;
                        }
                        $special3['material'] = $special3['term'];
                        unset($special3['term']);
                        $special3['id'] = $special3['material'];
                        $special3['name'] = Data_Material8::getValueByCode(TRUE, $special3['material']);
                        unset($special3['material']);
                    }
                    $special3Arr['list'] = $special3List;
                }
                break;
            case 9:
                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }
                break;
            case 10:
                $pressArr['title'] = '发行人';
                $pressArr['list'] = array();
                if (!empty($pressList)) {
                    foreach ($pressList as $k => &$press) {
                        if (!is_array($press)) {
                            unset($pressList[$k]);
                            continue;
                        }
                        $press['name'] = $press['press2'];
                        unset($press['press2']);
                        $press['id'] = $press['pressid'];
                        unset($press['pressid']);
                    }
                    $pressArr['list'] = $pressList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }
                break;
            case 11:
                $authorArr['title'] = '作者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }

                $special1Arr['title'] = '类别';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['sort'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['sort'];
                        $special1['name'] = Data_Sort11::getValueByCode(TRUE, $special1['sort']);
                        unset($special1['sort']);
                    }
                    $special1Arr['list'] = $special1List;
                }
                break;
            case 12:
                $authorArr['title'] = '制作（发行）人';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $yearsArr['title'] = '年代';
                $yearsArr['type'] = 'select';
                $yearsArr['list'] = array();
                if (!empty($yearsList)) {
                    foreach ($yearsList as $k => &$years) {
                        if (!is_array($years)) {
                            unset($yearsList[$k]);
                            continue;
                        }
                        $years['id'] = $years['years2'];
                        unset($years['years2']);
                        $years['name'] = $this->getYearsById($years['id']);
                        if (!$years['name']) {
                            unset($yearsList[$k]);
                        }
                    }
                    $yearsArr['list'] = $yearsList;
                }

                $special1Arr['title'] = '材质';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['material'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = $special1['material'];
                        $special1['name'] = Data_Material5::getValueByCode(TRUE, $special2['material']);
                        unset($special1['material']);
                    }
                    $special1Arr['list'] = $special1List;
                }
                break;
            case 13:
                $authorArr['title'] = '作者';
                $authorArr['list'] = array();
                if (!empty($authorList)) {
                    foreach ($authorList as $k => &$author) {
                        if (!is_array($author)) {
                            unset($authorList[$k]);
                            continue;
                        }
                        $author['name'] = $author['author2'];
                        unset($author['author2']);
                        $author['id'] = $author['authorid'];
                        unset($author['authorid']);
                    }
                    $authorArr['list'] = $authorList;
                }

                $pressArr['title'] = '出版社';
                $pressArr['list'] = array();
                if (!empty($pressList)) {
                    foreach ($pressList as $k => &$press) {
                        if (!is_array($press)) {
                            unset($pressList[$k]);
                            continue;
                        }
                        $press['name'] = $press['press2'];
                        unset($press['press2']);
                        $press['id'] = $press['pressid'];
                        unset($press['pressid']);
                    }
                    $pressArr['list'] = $pressList;
                }

                $yearsArr['title'] = '出版时间';
                $yearsArr['type'] = 'input';
                $yearsArr['list'] = array();

                $special1Arr['title'] = '装订';
                $special1Arr['list'] = array();
                if (!empty($special1List)) {
//                    echo '<pre>';
//                    print_r($special1List);
                    foreach ($special1List as $k => &$special1) {
                        if (!is_array($special1)) {
                            unset($special1List[$k]);
                            continue;
                        }
                        $special1['binding'] = $special1['term'];
                        unset($special1['term']);
                        $special1['id'] = trim($special1['binding'], '"');
                        $special1['name'] = Data_BindingFull::getValueByCode(TRUE, trim($special1['binding'], '"'));
                        unset($special1['binding']);
                    }
//                    $special1List = $this->turnBindToNew($special1List, $catTpl);
//                    echo '<pre>';
//                    print_r($special1List);
                    $special1Arr['list'] = $special1List;
                }
                break;
        }

        $searchData['catList']      = $categoryArr;
        $searchData['authorList']   = $this->translateFilters($authorArr);
        $searchData['pressList']    = $this->translateFilters($pressArr);
        $searchData['yearsList']    = $this->translateFilters($yearsArr, 1);
        $searchData['special1List'] = $this->translateFilters($special1Arr, 1);
        $searchData['special2List'] = $this->translateFilters($special2Arr, 1);
        $searchData['special3List'] = $this->translateFilters($special3Arr, 1);

        return $searchData;
    }
}

?>