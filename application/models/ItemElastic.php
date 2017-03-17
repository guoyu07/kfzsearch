<?php

/**
 * product elastic搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年1月28日14:45:52
 */
class ItemElasticModel extends SearchModel
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
    private $productServiceESHost;
    private $productServiceESPort;
    private $productServiceESIndex;
    private $productServiceESIndexSold;
    private $productServiceESIndexAll;
    private $productServiceESType;
    private $returnTotal;            //接口返回的total，用于分页
    private $ES_timeout;             //ES查询超时时间
    private $ES_dfs_query_then_fetch;//严格相关度搜索
    private $forceSpider;            //强制爬虫
    
    /**
     * product搜索操作模型
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
        $this->productServiceESHost  = '';
        $this->productServiceESPort  = '';
        $this->productServiceESIndex = '';
        $this->productServiceESIndexSold = '';
        $this->productServiceESIndexAll = '';
        $this->productServiceESType  = '';
        $this->returnTotal           = 0;
        $this->ES_timeout            = 0;
        $this->ES_dfs_query_then_fetch = false;
        $this->forceSpider           = false;
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
        if($this->forceSpider == true) {
            $this->isSpiderFlag   = true;
        }
        $useIndex                    = $this->isSpiderFlag && isset($bizIndexConfig['spiderIndex']) ? $bizIndexConfig['spiderIndex'] : $bizIndexConfig['index'];
        $serviceKey                  = $useIndex. 'ServiceES';
        $productServiceES_Cfg        = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if(empty($productServiceES_Cfg)) {
            return false;
        }
        $this->productServiceESHost  = $productServiceES_Cfg['host'];
        $this->productServiceESPort  = $productServiceES_Cfg['port'];
        $this->productServiceESIndex = 'item';
        $this->productServiceESIndexSold = 'item_sold';
        $this->productServiceESIndexAll  = 'item,item_sold';
        $this->productServiceESType  = 'product';
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
        $cacheKeyFix                 = $this->isSpiderFlag && isset($bizIndexConfig['spiderCacheKeyFix']) ? $bizIndexConfig['spiderCacheKeyFix'] : $bizIndexConfig['cacheKeyFix'];
        $cacheType                   = $this->isSpiderFlag && isset($bizIndexConfig['spiderCacheType']) ? $bizIndexConfig['spiderCacheType'] : $bizIndexConfig['cacheType'];
        if(!empty($cacheServers)) {
            $this->cache = new Tool_SearchCache($cacheServers, $cacheType, $cacheKeyFix, true);
            if($this->cache->getConnectStatus() === false) {
                $this->cache = null;
            }
        }
        return true;
    }
    
    /**
     * 设置强制爬虫
     */
    public function setForSpider($isForceSpider)
    {
        $this->forceSpider = $isForceSpider;
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

        $searchParams['fields'] = array("olreceivetype","certifystatus","imgurl","recertifystatus","addtime","quality","number","discount","years","pubdate","price","press","author","itemname","catid","userid","shopstatus","class","area","shopid","shopname","nickname","biztype","itemid","iauthor","ipress","pubdate2","years2","updatetime","approach","isbn","params","salestatus","isdeleted","catid1","catid2","catid3","catid4","vcatid","vcatid1","vcatid2","vcatid3","vcatid4","hasimg","area1","area2","paper","printtype","binding","sort","material","form","trust","isautoverify","istrustshop","flag1","flag2");
        
        if($this->productServiceESType == 'product') {
            $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
            $searchParams['filter']['must'][] = array('field' => 'shopstatus', 'value' => 1);
        } elseif ($this->productServiceESType == 'seoproduct') {
            $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
            $searchParams['filter']['must'][] = array('field' => 'shopstatus', 'value' => 1);
        } elseif ($this->productServiceESType == 'unproduct') {
            $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        }
        
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
                if (strpos($requestParams['catnum']['value'], 'hh') === false) {
                    $catidArr = explode('h', $requestParams['catnum']['value']);
                    foreach ($catidArr as &$value) {
                        $value = CategoryModel::getFullCatId($value);
                    }
                    unset($value);
                    $catidStr = implode(',', $catidArr);
                    $searchParams['filter']['must_in'][] = array('field' => 'catid1', 'value' => $catidStr);
                } else {
                    $catidArr = explode('hh', $requestParams['catnum']['value']);
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
        
        if (isset($requestParams['price']['value']) && $requestParams['price']['value']) {
            $priceArr = explode('h', $requestParams['price']['value']);
            if (count($priceArr) > 0) {
                $range = array('field' => 'price');
                if ($priceArr[0]) {
                    $range['from'] = $priceArr[0];
                }
                if (isset($priceArr[1]) && $priceArr[1]) {
                    $range['to'] = $priceArr[1];
                }
                $searchParams['filter']['range_must'][] = $range;
            }
        }
        if (isset($requestParams['number']['value']) && $requestParams['number']['value']) {
            $numberArr = explode('h', $requestParams['number']['value']);
            if (count($numberArr) > 0) {
                $range = array('field' => 'number');
                if ($numberArr[0]) {
                    $range['from'] = $numberArr[0];
                }
                if (isset($numberArr[1]) && $numberArr[1]) {
                    $range['to'] = $numberArr[1];
                }
                $searchParams['filter']['range_must'][] = $range;
            }
        }
        if (isset($requestParams['class']['value']) && $requestParams['class']['value']) {
            $classArr = explode('h', $requestParams['class']['value']);
            if (count($classArr) > 0) {
                $range = array('field' => 'class');
                if ($classArr[0]) {
                    $range['from'] = $classArr[0];
                }
                if (isset($classArr[1]) && $classArr[1]) {
                    $range['to'] = $classArr[1];
                }
                $searchParams['filter']['range_must'][] = $range;
            }
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
        
        if (isset($requestParams['discount']['value']) && $requestParams['discount']['value'] != '') {
            $discountArr = explode('h', $requestParams['discount']['value']);
            if (count($discountArr) > 0) {
                $range = array('field' => 'discount');
                if ($discountArr[0]) {
                    $range['from'] = $discountArr[0];
                }
                if (isset($discountArr[1]) && $discountArr[1]) {
                    $range['to'] = $discountArr[1];
                }
                $searchParams['filter']['range_must'][] = $range;
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
        if (isset($requestParams['shopname']['value']) && $requestParams['shopname']['value']) {
            $shopName = isset($requestParams['shopname']['nocode']) && $requestParams['shopname']['nocode'] == 1 ? $requestParams['shopname']['value'] : $this->unicode2str($requestParams['shopname']['value']);
//            $searchParams['query']['type'] = 'dis_max';
//            $searchParams['query']['queries'][] = array('fields' => '_shopname', 'key' => $shopName, 'minimum_should_match' => '100%');
//            
//            $searchParams['filter']['must'][] = array('field' => '_shopname', 'value' => $shopName);
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_shopname', 'value' => $this->fan2jian($shopName));
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
        if (isset($requestParams['addtime']['value']) && ($requestParams['addtime']['value'] || $requestParams['addtime']['value'] === '0')) {
            if(strpos($requestParams['addtime']['value'], 'h') === false) {
                if ($requestParams['addtime']['value'] === '0') { //如果为零，则取最近24小时的数据(shop)
                    $this->setExpire(600);
                    //缓存10分钟
                    $curM = date('i');
                    if($curM % 10 === 0) {
                        $timeLimit = strtotime(date('Y-m-d H'). ':'. $curM. ':00') - 86400;
                    } else {
                        $cha = $curM - ($curM % 10);
                        $timeLimit = strtotime(date('Y-m-d H'). ':'. $cha. ':00') - 86400;
                    }
                    //不取ISBN批量上传 及 ISBN批量删除后恢复已删商品
                    $range = array('field' => 'addtime');
                    $range['from'] = $timeLimit;
                    $searchParams['filter']['range_must'][] = $range;
//                    $searchParams['filter']['must_not_in'][] = array('field' => 'approach', 'value' => '2,5');
                } elseif ($requestParams['addtime']['value'] == 1)  { //如果为1，则取当天的数据(book)
                    $timeLimit_1 = strtotime(date('Ymd'));
                    $timeLimit_2 = $timeLimit_1 + 3600 * 24;
                    $range = array('field' => 'addtime');
                    $range['from'] = $timeLimit_1;
                    $range['to'] = $timeLimit_2;
                    $searchParams['filter']['range_must'][] = $range;
                } elseif (strlen($requestParams['addtime']['value']) == 8) { //取指定日期的数据
                    $timeLimit_1 = strtotime($requestParams['addtime']['value']);
                    $timeLimit_2 = $timeLimit_1 + 3600 * 24;
                    $range = array('field' => 'addtime');
                    $range['from'] = $timeLimit_1;
                    $range['to'] = $timeLimit_2;
                    $searchParams['filter']['range_must'][] = $range;
                }
            } else {
                $addtimeArr = explode('h', $requestParams['addtime']['value']);
                if (count($addtimeArr) > 0) {
                    $range = array('field' => 'addtime');
                    if ($addtimeArr[0] && strlen($addtimeArr[0]) == 8 && is_numeric($addtimeArr[0])) {
                        $range['from'] = strtotime($addtimeArr[0]);
                    }
                    if (isset($addtimeArr[1]) && $addtimeArr[1] && strlen($addtimeArr[1]) == 8 && is_numeric($addtimeArr[1])) {
                        if($addtimeArr[1] == $addtimeArr[0]) {
                            $timeTmp = strtotime($addtimeArr[1]) + 86400;
                            $range['to'] = $timeTmp;
                        } else {
                            $range['to'] = strtotime($addtimeArr[1]);
                        }
                    }
                    if ($addtimeArr[0] && strlen($addtimeArr[0]) == 10 && is_numeric($addtimeArr[0])) {
                        $range['from'] = $addtimeArr[0];
                    }
                    if (isset($addtimeArr[1]) && $addtimeArr[1] && strlen($addtimeArr[1]) == 10 && is_numeric($addtimeArr[1])) {
                        $range['to'] = $addtimeArr[1];
                    }
                    $searchParams['filter']['range_must'][] = $range;
                }
            }
        }
        if (isset($requestParams['updatetime']['value']) && $requestParams['updatetime']['value']) {
            $updatetimeArr = explode('h', $requestParams['updatetime']['value']);
            if (count($updatetimeArr) > 0) {
                $range = array('field' => 'updatetime');
                if ($updatetimeArr[0] && strlen($updatetimeArr[0]) == 8 && is_numeric($updatetimeArr[0])) {
                    $range['from'] = strtotime($updatetimeArr[0]);
                }
                if (isset($updatetimeArr[1]) && $updatetimeArr[1] && strlen($updatetimeArr[1]) == 8 && is_numeric($updatetimeArr[1])) {
                    if($updatetimeArr[1] == $updatetimeArr[0]) {
                        $timeTmp = strtotime($updatetimeArr[1]) + 86400;
                        $range['to'] = $timeTmp;
                    } else {
                        $range['to'] = strtotime($updatetimeArr[1]);
                    }
                }
                if ($updatetimeArr[0] && strlen($updatetimeArr[0]) == 10 && is_numeric($updatetimeArr[0])) {
                    $range['from'] = $updatetimeArr[0];
                }
                if (isset($updatetimeArr[1]) && $updatetimeArr[1] && strlen($updatetimeArr[1]) == 10 && is_numeric($updatetimeArr[1])) {
                    $range['to'] = $updatetimeArr[1];
                }
                $searchParams['filter']['range_must'][] = $range;
            }
        }
        if (isset($requestParams['pubdate']['value']) && $requestParams['pubdate']['value']) {
            $pubdateArr = explode('h', $requestParams['pubdate']['value']);
            if (count($pubdateArr) > 0) {
                $range = array('field' => 'pubdate');
                if ($pubdateArr[0] && strlen($pubdateArr[0]) == 8 && is_numeric($pubdateArr[0])) {
                    $range['from'] = intval($pubdateArr[0]);
                }
                if (isset($pubdateArr[1]) && $pubdateArr[1] && strlen($pubdateArr[1]) == 8 && is_numeric($pubdateArr[1])) {
                    $range['to'] = intval($pubdateArr[1]);
                }
                if ($pubdateArr[0] && strlen($pubdateArr[0]) == 6 && is_numeric($pubdateArr[0])) {
                    $range['from'] = intval($pubdateArr[0]. '00');
                }
                if (isset($pubdateArr[1]) && $pubdateArr[1] && strlen($pubdateArr[1]) == 6 && is_numeric($pubdateArr[1])) {
                    $range['to'] = intval($pubdateArr[1]. '00');
                }
                $searchParams['filter']['range_must'][] = $range;
            }
        }
        if(isset($requestParams['biztype']['value']) && $requestParams['biztype']['value'] && is_numeric($requestParams['biztype']['value'])) {
            $searchParams['filter']['must'][] = array('field' => 'biztype', 'value' => $requestParams['biztype']['value']);
        }
        if((isset($requestParams['isfuzzy']['value']) && $requestParams['isfuzzy']['value']) || $this->matchType == 'fuzzy') {
            $this->matchType = 'fuzzy';
        }
        if((isset($requestParams['exact']['value']) && $requestParams['exact']['value']) || $this->matchType == 'exact') {
            $this->matchType = 'exact';
        }
        if((isset($requestParams['perfect']['value']) && $requestParams['perfect']['value']) || $this->matchType == 'perfect') {
            $this->matchType = 'perfect';
        }
        if(isset($requestParams['shopid']['value']) && $requestParams['shopid']['value']) {
            if(strpos($requestParams['shopid']['value'], 'h') !== false) {
                $shopidStr = str_replace('h', ',', $requestParams['shopid']['value']);
                $searchParams['filter']['must_in'][] = array('field' => 'shopid', 'value' => $shopidStr);
            } else {
                $searchParams['filter']['must'][] = array('field' => 'shopid', 'value' => $requestParams['shopid']['value']);
            }
        }
        if(isset($requestParams['approach']['value']) && $requestParams['approach']['value']) {
            if(strpos($requestParams['approach']['value'], 'h') !== false) {
                $approachStr = str_replace('h', ',', $requestParams['approach']['value']);
                $searchParams['filter']['must_in'][] = array('field' => 'approach', 'value' => $approachStr);
            } else {
                $searchParams['filter']['must'][] = array('field' => 'approach', 'value' => $requestParams['approach']['value']);
            }
        }
        if(isset($requestParams['filteritemid']['value']) && $requestParams['filteritemid']['value']) {
            $filteritemidStr = str_replace('h', ',', $requestParams['filteritemid']['value']);
            $searchParams['filter']['must_not_in'][] = array('field' => 'itemid', 'value' => $filteritemidStr);
        }
        if(isset($requestParams['filtercatid']['value']) && $requestParams['filtercatid']['value']) {
            $filtercatidStr = str_replace('h', ',', $requestParams['filtercatid']['value']);
            $searchParams['filter']['must_not_in'][] = array('field' => 'catid1', 'value' => $filtercatidStr);
        }
        if(isset($requestParams['userid']['value']) && $requestParams['userid']['value']) {
            if(strpos($requestParams['userid']['value'], 'h') !== false) {
                $useridStr = str_replace('h', ',', $requestParams['userid']['value']);
                $searchParams['filter']['must_in'][] = array('field' => 'userid', 'value' => $useridStr);
            } else {
                $searchParams['filter']['must'][] = array('field' => 'userid', 'value' => $requestParams['userid']['value']);
            }
        }
        if(isset($requestParams['hasimg']['value']) && $requestParams['hasimg']['value'] && is_numeric($requestParams['hasimg']['value'])) {
            if($requestParams['hasimg']['value'] == 1) {
                $searchParams['filter']['must'][] = array('field' => 'hasimg', 'value' => 1);
            } elseif ($requestParams['hasimg']['value'] == 2) {
                $searchParams['filter']['must'][] = array('field' => 'hasimg', 'value' => 0);
            }
        }
        if(isset($requestParams['quality']['value']) && $requestParams['quality']['value'] && is_numeric($requestParams['quality']['value'])) {
            if($requestParams['quality']['value'] == 101) {
                $searchParams['filter']['must_not'][] = array('field' => 'quality', 'value' => 100);
            } else {
                $searchParams['filter']['must'][] = array('field' => 'quality', 'value' => $requestParams['quality']['value']);
            }
        }
        if($this->unLimit && isset($requestParams['certifystatus']['value']) && ($requestParams['certifystatus']['value'] || $requestParams['certifystatus']['value'] === '0')) {
            $searchParams['filter']['must'][] = array('field' => 'certifystatus', 'value' => $requestParams['certifystatus']['value']);
        } else {
            $searchParams['filter']['must'][] = array('field' => 'certifystatus', 'value' => 1);
        }
        if($this->unLimit && isset($requestParams['recertifystatus']['value']) && ($requestParams['recertifystatus']['value'] || $requestParams['recertifystatus']['value'] === '0')) {
            $searchParams['filter']['must'][] = array('field' => 'recertifystatus', 'value' => $requestParams['recertifystatus']['value']);
        }
        if (isset($requestParams['order']['value']) && intval($requestParams['order']['value'])) {
            switch (intval($requestParams['order']['value'])) {
                case 1:
                    $searchParams['sort'] = array(array('field' => 'price', 'order' => 'asc'));
                    break;
                case 2:
                    $searchParams['sort'] = array(array('field' => 'price', 'order' => 'desc'));
                    break;
                case 3:
                    $searchParams['sort'] = array(array('field' => 'pubdate2', 'order' => 'asc'));
                    break;
                case 4:
                    $searchParams['sort'] = array(array('field' => 'pubdate', 'order' => 'desc'));
                    break;
                case 5:
                    $searchParams['sort'] = array(array('field' => 'addtime', 'order' => 'asc'));
                    break;
                case 6:
                    $searchParams['sort'] = array(array('field' => 'addtime', 'order' => 'desc'));
                    break;
                case 7:
                    $searchParams['sort'] = array(array('field' => 'class', 'order' => 'desc'));
                    break;
                case 8:
                    $searchParams['sort'] = array(array('field' => 'updatetime', 'order' => 'asc'));
                    break;
                case 9:
                    $searchParams['sort'] = array(array('field' => 'updatetime', 'order' => 'desc'));
                    break;
                case 10:
                    $searchParams['sort'] = array(array('field' => 'class', 'order' => 'asc'));
                    break;
                case 11:
                    $searchParams['sort'] = array(array('field' => 'discount', 'order' => 'asc'));
                    break;
                case 12:
                    $searchParams['sort'] = array(array('field' => 'discount', 'order' => 'desc'));
                    break;
                default:
                    $searchParams['sort'] = array( "_score", array('field' => 'updatetime', 'order' => 'asc') );
                    break;
            }
        } else {
            $searchParams['sort'] = array( "_score", array('field' => 'rank', 'order' => 'desc') );
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
            if ($requestParams['status']['value'] == 1) {
                $this->productServiceESIndex = $this->productServiceESIndexSold;
                $searchParams['filter']['must'][] = array('field' => 'salestatus', 'value' => 1);
            } else {
                $this->productServiceESIndex = $this->productServiceESIndexAll;
            }
        } else {
            $this->productServiceESIndex = $this->productServiceESIndex;
            $searchParams['filter']['must'][] = array('field' => 'salestatus', 'value' => 0);
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
        if($this->isBuildSnippets) { //高亮
            $searchParams['highlight'] = array('pre_tags' => array('<b>'), 'post_tags' => array('</b>'), 'fields' => array(array('field' => '_itemname'), array('field' => '_author'), array('field' => '_press')) );
        }
        if ($key !== 0 && $exKey !== 0) {
//            $searchParams['query']['key'] = $key;
//            $searchParams['query']['fields'][] = array('field' => '_author', 'weight' => '60');
//            $searchParams['query']['fields'][] = array('field' => '_press', 'weight' => '50');
//            $searchParams['query']['fields'][] = array('field' => '_itemname', 'weight' => '300');
//            $searchParams['query']['fields'][] = array('field' => 'isbn', 'weight' => '30');
//            $searchParams['query']['type'] = 'best_fields';
//            $searchParams['query']['tie_breaker'] = '0.3';
//            $searchParams['query']['minimum_should_match'] = '90%';
//            
//            $searchParams['query']['type'] = 'dis_max';
//            $searchParams['query']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '90%');
            
            $searchParams['query']['type'] = 'bool';
            if($this->bizFlag == 'search') {
                $searchParams['query']['type'] = 'bool_function_score';
                $this->ES_dfs_query_then_fetch = true;
                $this->ES_timeout = 10;
            }
            $searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
            $searchParams['query']['must_not-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $exKey, 'minimum_should_match' => '100%');
            if($match_phrase_itemname_flag == 0) {
                $searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
                $searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $key, 'type' => 'phrase', 'slop' => 2);
            }
        } elseif ($key !== 0 && $exKey === 0) {
            if ($this->matchType == 'fuzzy') { //模糊搜索
//                $searchParams['query']['key'] = $key;
//                $searchParams['query']['fields'][] = array('field' => '_author', 'weight' => '60');
//                $searchParams['query']['fields'][] = array('field' => '_press', 'weight' => '50');
//                $searchParams['query']['fields'][] = array('field' => '_itemname', 'weight' => '300');
//                $searchParams['query']['fields'][] = array('field' => 'isbn', 'weight' => '30');
//                $searchParams['query']['type'] = 'best_fields';
//                $searchParams['query']['tie_breaker'] = '0.3';
//                $searchParams['query']['minimum_should_match'] = '50%';
//                
//                $searchParams['query']['type'] = 'dis_max';
//                $searchParams['query']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '50%');
                $searchParams['query']['type'] = 'bool';
                if($this->bizFlag == 'search') {
//                    $searchParams['query']['type'] = 'bool_function_score';
                    $this->ES_dfs_query_then_fetch = true;
                    $this->ES_timeout = 10;
//                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30","_tag^20","itemdesc^1"), 'key' => $key, 'minimum_should_match' => '50%');
                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '50%');
                } else {
                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '50%');
                }
                if($match_phrase_itemname_flag == 0) {
                    $searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
                    $searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $key, 'type' => 'phrase', 'slop' => 2);
                }
            } elseif ($this->matchType == 'exact') { //精确搜索
                $searchParams['query']['type'] = 'bool';
                if($this->bizFlag == 'search') {
                    $searchParams['query']['type'] = 'bool_function_score';
                    $this->ES_dfs_query_then_fetch = true;
                    $this->ES_timeout = 10;
                    if (preg_match("/^[\s0-9\x{4e00}-\x{9fa5}]+$/u", $key)) { //纯中文、数字
//                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'tie_breaker' => 1, 'type' => 'phrase');
                    } elseif (preg_match('/^[\s0-9a-zA-Z\s]+$/isU', $key)) { //纯数字、拼音、英文
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("py_itemname^300","py_author^60","py_press^50","isbn^30","_itemname^300","_author^60","_press_50"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                    } elseif (preg_match("/[\x{0400}-\x{052f}]+/u", $key)) { //俄文搜索、同义词
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","n_itemname^300","n_author^60","n_press^50","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                    }else { //其它
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                    }
                } else {
                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                }
                if($match_phrase_itemname_flag == 0) { //增加相关度
                    $searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
                    $searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $key, 'type' => 'phrase', 'slop' => 2);
                }
            } elseif ($this->matchType == 'perfect') { //完全匹配
                $searchParams['query']['type'] = 'bool';
                if($this->bizFlag == 'search') {
                    $searchParams['query']['type'] = 'bool_function_score';
                    $this->ES_dfs_query_then_fetch = true;
                    $this->ES_timeout = 10;
                    if (preg_match("/^[\s0-9\x{4e00}-\x{9fa5}]+$/u", $key)) { //纯中文、数字
//                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("author^60", "press^50","itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'tie_breaker' => 1, 'type' => 'phrase');
                    } elseif (preg_match('/^[\s0-9a-zA-Z\s]+$/isU', $key)) { //纯数字、拼音、英文
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("py_itemname^300","py_author^60","py_press^50","isbn^30","itemname^300","author^60","press_50"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                    } elseif (preg_match("/[\x{0400}-\x{052f}]+/u", $key)) { //俄文搜索、同义词
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("author^60", "press^50","itemname^300","n_itemname^300","n_author^60","n_press^50","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                    }else { //其它
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("author^60", "press^50","itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                    }
                } else {
                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("author^60", "press^50","itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'phrase');
                }
//                if($match_phrase_itemname_flag == 0) { //增加相关度
//                    $searchParams['query']['must'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
//                }
                if($this->isBuildSnippets) { //改写高亮
                    $searchParams['highlight'] = array('pre_tags' => array('<b>'), 'post_tags' => array('</b>'), 'fields' => array(array('field' => 'itemname'), array('field' => 'author'), array('field' => 'press')) );
                }
            } else {
//                $searchParams['query']['key'] = $key;
//                $searchParams['query']['fields'][] = array('field' => '_author', 'weight' => '60');
//                $searchParams['query']['fields'][] = array('field' => '_press', 'weight' => '50');
//                $searchParams['query']['fields'][] = array('field' => '_itemname', 'weight' => '300');
//                $searchParams['query']['fields'][] = array('field' => 'isbn', 'weight' => '30');
//                $searchParams['query']['type'] = 'best_fields';
//                $searchParams['query']['tie_breaker'] = '0.1';
//                $searchParams['query']['minimum_should_match'] = '90%';
                
//                $searchParams['query']['type'] = 'dis_max';
//                $searchParams['query']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '90%');
                $searchParams['query']['type'] = 'bool';
                if($this->bizFlag == 'search') {
                    $searchParams['query']['type'] = 'bool_function_score';
                    $this->ES_dfs_query_then_fetch = true;
                    $this->ES_timeout = 10;
                    if (preg_match("/^[\s0-9\x{4e00}-\x{9fa5}]+$/u", $key)) { //纯中文、数字
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
//                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'tie_breaker' => 1);
                    } elseif (preg_match('/^[\s0-9a-zA-Z\s]+$/isU', $key)) { //纯数字、拼音、英文
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("py_itemname^300","py_author^60","py_press^50","isbn^30","_itemname^300","_author^60","_press_50"), 'key' => $key, 'minimum_should_match' => '100%');
                    } elseif (preg_match("/[\x{0400}-\x{052f}]+/u", $key)) { //俄文搜索、同义词
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","n_itemname^300","n_author^60","n_press^50","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%');
                    }else { //其它
                        $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
                    }
                } else {
                    $searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $key, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
                }
                if($match_phrase_itemname_flag == 0) { //增加相关度
                    $searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $key, 'type' => 'phrase');
                    $searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $key, 'type' => 'phrase', 'slop' => 2);
                }
            }
        } elseif ($key === 0 && $exKey !== 0) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must_not-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $exKey);
        }
        
        if (isset($requestParams['itemdesc']['value']) && $requestParams['itemdesc']['value']) {
            $itemdescKey = isset($requestParams['itemdesc']['nocode']) && $requestParams['itemdesc']['nocode'] == 1 ? $requestParams['itemdesc']['value'] : $this->unicode2str($requestParams['itemdesc']['value']);
            $itemdescKey = $this->fan2jian($itemdescKey);
//            $searchParams['query']['type'] = 'dis_max';
//            $searchParams['query']['queries'][] = array('field' => 'itemdesc', 'key' => $itemdescKey, 'minimum_should_match' => '100%');
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => 'itemdesc', 'value' => $itemdescKey);
        }
        
        //如果不是搜索，而是分类浏览或今日上书或地区图书或感兴趣图书等等，将ISBN批量图书过滤掉
        if($this->bizFlag != 'search' && $this->bizFlag != 'msearch' && $this->bizFlag != 'app') {
            if (!(isset($requestParams['shopname']['value']) && $requestParams['shopname']['value']) && !(isset($requestParams['shopid']['value']) && $requestParams['shopid']['value'])) { //如果没有店铺名称并且没有店铺id筛选
                $searchParams['filter']['must_not_in'][] = array('field' => 'approach', 'value' => '2,5');
            }
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
                } elseif (isset($v['highlight']) && isset($v['highlight']['itemname'])) {
                    $tmp['itemname_snippet'] = $v['highlight']['itemname'][0];
                } else {
                    $tmp['itemname_snippet'] = $v['_source']['itemname'];
                }
                if(isset($v['highlight']) && isset($v['highlight']['_author'])) {
                    $tmp['author_snippet'] = $v['highlight']['_author'][0];
                } elseif (isset($v['highlight']) && isset($v['highlight']['author'])) {
                    $tmp['author_snippet'] = $v['highlight']['author'][0];
                } else {
                    $tmp['author_snippet'] = $v['_source']['author'];
                }
                if(isset($v['highlight']) && isset($v['highlight']['_press'])) {
                    $tmp['press_snippet'] = $v['highlight']['_press'][0];
                } elseif (isset($v['highlight']) && isset($v['highlight']['press'])) {
                    $tmp['press_snippet'] = $v['highlight']['press'][0];
                } else {
                    $tmp['press_snippet'] = $v['_source']['press'];
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
     * 跟据用户条件获得simple filterList和productList
     * 
     * @param array $requestParams
     * @param string $type       默认为精确搜索、'fuzzy'为模糊搜索
     * @return array
     */
    public function getSFPWithFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array();
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
            if(isset($this->searchParams['facets']['author_facet'])) {
                $facets['author_facet'] = $this->searchParams['facets']['author_facet'];
            }
            if(isset($this->searchParams['facets']['press_facet'])) {
                $facets['press_facet'] = $this->searchParams['facets']['press_facet'];
            }
        }
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
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
    public function getCPWithFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array('catid_facet' => $this->searchParams['facets']['catid_facet']);
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
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
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
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
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
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
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r(ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array()));exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 跟据用户条件获得product num数量
     */
    public function getPNWithFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $this->searchParams['limit'] = array(
                'from' => 0,
                'size' => 1
            );
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array('itemid'), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
        $returnList = array(
            'itemList' => array(
                '0' => array(
                    'num' => 0
                )
            ),
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
        if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
            $returnList['itemList'][0]['num'] = $result['hits']['total'];
            $returnList['itemList']['total'] = 1;
            $returnList['itemList']['total_found'] = 1;
            $returnList['itemList']['time'] = 0;
        }
        return $returnList;
    }
    
    /**
     * 获取只有分类的聚类为搜索首页使用
     */
    public function getOnlyCatFilter()
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
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), array(), array(), array(), $limit, array(), $facets);
            if(isset($result['facets']) && isset($result['facets']['catid_facet']) && $result['facets']['catid_facet']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 获取最新上架的商品
     */
    public function getTodayItemList()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array());
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 跟据用户条件获得filterList(catsList、authorList、pressList)和productList
     */
    public function getFWithFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array();
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
            if(isset($this->searchParams['facets']['author_facet'])) {
                $facets['author_facet'] = $this->searchParams['facets']['author_facet'];
            }
            if(isset($this->searchParams['facets']['press_facet'])) {
                $facets['press_facet'] = $this->searchParams['facets']['press_facet'];
            }
        }
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 跟据用户条件获得catList
     */
    public function getCatListStat()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array();
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
        }
        $limit = array('from' => 0, 'size' => 0);
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets, 'limit' => $limit));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), array(), $this->searchParams['filter'], $this->searchParams['sort'], $limit, array(), $facets);
            if(isset($result['facets']) && isset($result['facets']['catid_facet']) && $result['facets']['catid_facet']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 获取可能感兴趣的商品(查询5星级及以上的未售商品)
     * 按照书名模糊匹配
     */
    public function SHOP_getInterestItems()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        if (isset($this->requestParams['itemname']['value']) && $this->requestParams['itemname']['value']) {
            $itemname = isset($this->requestParams['itemname']['nocode']) && $this->requestParams['itemname']['nocode'] == 1 ? $this->requestParams['itemname']['value'] : $this->unicode2str($this->requestParams['itemname']['value']);
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['should-dis_max']['queries'] = array();
            $this->searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname"), 'key' => $itemname, 'minimum_should_match' => '70%');
        } else {
            return $this->formatSearchData(array());
        }
        
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array('itemid','itemname','price','imgurl','shopid'), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), 1);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 获取可能感兴趣的商品，全字段(查询5星级及以上的未售商品)
     * 按照书名模糊匹配
     */
    public function SHOP_getFullInterestItems()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        if (isset($this->requestParams['itemname']['value']) && $this->requestParams['itemname']['value']) {
            $itemname = isset($this->requestParams['itemname']['nocode']) && $this->requestParams['itemname']['nocode'] == 1 ? $this->requestParams['itemname']['value'] : $this->unicode2str($this->requestParams['itemname']['value']);
            $this->searchParams['query'] = array();
            $this->searchParams['query']['type'] = 'bool';
            //2016-06-09
            $this->searchParams['query']['must-dis_max']['queries'] = array();
            $this->searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "_press^50","_itemname^300","isbn^30"), 'key' => $itemname, 'minimum_should_match' => '100%', 'type' => 'cross_fields');
            //2016-06-12
            $this->searchParams['query']['should'] = array();
            $this->searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $itemname, 'type' => 'phrase');
            $this->searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $itemname, 'type' => 'phrase', 'slop' => 2);
            //$this->searchParams['query']['should'] = array();
            //$this->searchParams['query']['should'][] = array('field' => 'itemname', 'value' => $itemname, 'type' => 'phrase');
            //$this->searchParams['query']['should'][] = array('field' => '_itemname', 'value' => $itemname, 'type' => 'phrase', 'slop' => 2);

            //$this->searchParams['query']['should-dis_max']['queries'] = array();
            //$this->searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_itemname"), 'key' => $itemname, 'minimum_should_match' => '70%');
        } else {
            return $this->formatSearchData(array());
        }
        
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array());
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 获取店铺24小时最新上书统计
     */
    public function SHOP_getNewAddItemNumByShopIds()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array();
        $facets['shopid_facet'] = array(array('field' => 'shopid'));
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
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
        if(isset($result['facets']) && isset($result['facets']['shopid_facet']) && isset($result['facets']['shopid_facet']['terms'])) {
             foreach($result['facets']['shopid_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['num'] = $term['count'];
                $tmp['shopid'] = $term['term'];
                $returnList['itemList'][] = $tmp;
            }
        }
        return $returnList;
    }
    
    /**
     * 按类别获取统计
     */
    public function SHOP_getCategoryItemCount()
    {
        if(empty($this->searchParams) || !isset($this->otherParams['groupbyas']) || !$this->otherParams['groupbyas'] || !isset($this->otherParams['groupby']) || !$this->otherParams['groupby']) {
            return $this->formatSearchData(array());
        }
        $facets = array();
        $facets['field_facet'] = array(array('field' => $this->otherParams['groupby']));
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, array(), $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
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
        if(isset($result['facets']) && isset($result['facets']['field_facet']) && isset($result['facets']['field_facet']['terms'])) {
             foreach($result['facets']['field_facet']['terms'] as $term) {
                $tmp = array();
                $tmp['num'] = $term['count'];
                $askey = $this->otherParams['groupbyas'];
                $key = $this->otherParams['groupby'];
                $tmp[$askey] = $term[$key];
                $returnList['itemList'][] = $tmp;
            }
        }
        return $returnList;
    }
    
    /**
     * 根据出版社名称查询商品
     */
    public function LIB_searchBooksByPressName()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        if (isset($this->requestParams['press']['value']) && $this->requestParams['press']['value']) {
            $press = $this->unicode2str($this->requestParams['press']['value']);
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['should-dis_max']['queries'] = array();
            $this->searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_press"), 'key' => $press, 'minimum_should_match' => '90%');
        } else {
            return $this->formatSearchData(array());
        }
        $facets = array();
        $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 根据作者名查商品
     */
    public function LIB_searchBooksByAuthorName()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        if (isset($this->requestParams['author']['value']) && $this->requestParams['author']['value']) {
            $author = $this->unicode2str($this->requestParams['author']['value']);
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['should-dis_max']['queries'] = array();
            $this->searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author"), 'key' => $author, 'minimum_should_match' => '90%');
        } else {
            return $this->formatSearchData(array());
        }
        $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 根据ISBN查商品
     */
    public function LIB_searchBooksByIsbn()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        if (isset($this->requestParams['key']['value']) && $this->requestParams['key']['value']) {
            $isbn = isset($this->requestParams['key']['nocode']) && $this->requestParams['key']['nocode'] == 1 ? $this->requestParams['key']['value'] : $this->unicode2str($this->requestParams['key']['value']);
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must-dis_max']['queries'] = array();
            $this->searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("isbn"), 'key' => $isbn, 'minimum_should_match' => '100%');
        } else {
            return $this->formatSearchData(array());
        }
        
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array());
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 根据ISBN查最低价格图书
     */
    public function LIB_searchMinPriceByIsbn()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        if (isset($this->requestParams['key']['value']) && $this->requestParams['key']['value']) {
            $isbn = isset($this->requestParams['key']['nocode']) && $this->requestParams['key']['nocode'] == 1 ? $this->requestParams['key']['value'] : $this->unicode2str($this->requestParams['key']['value']);
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must-dis_max']['queries'] = array();
            $this->searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("isbn"), 'key' => $isbn, 'minimum_should_match' => '100%');
        } else {
            return $this->formatSearchData(array());
        }
        
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], array(array('field' => 'price', 'order' => 'asc')), $this->searchParams['limit'], $this->searchParams['highlight'], array());
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 查询ISBN对应商品有无库存
     */
    public function LIB_checkStockByIsbn()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        if (isset($this->requestParams['key']['value']) && $this->requestParams['key']['value']) {
            $isbn = isset($this->requestParams['key']['nocode']) && $this->requestParams['key']['nocode'] == 1 ? $this->requestParams['key']['value'] : $this->unicode2str($this->requestParams['key']['value']);
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must-dis_max']['queries'] = array();
            $this->searchParams['query']['must-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("isbn"), 'key' => $isbn, 'minimum_should_match' => '100%');
        } else {
            return $this->formatSearchData(array());
        }
        
        $filter = array();
        $filter['range_must'] = array();
        $filter['range_must'][] = array('from' => 1, 'include_lower' => true);
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => array()));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $filter, array(), $this->searchParams['limit'], $this->searchParams['highlight'], array());
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
    
    /**
     * 跟据用户条件获得filterList和productList（爬虫不进行聚类）
     */
    public function SEARCH_getFPWithFilter()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $facets = array();
        if(!$this->isSpiderFlag) { //不是爬虫
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
        } else {
            if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
                $facets['catid_facet'] = $this->searchParams['facets']['catid_facet'];
            }
        }
        
        $cacheSearchParamsArr = array_merge($this->searchParams, array('facets' => $facets));
        $result = $this->getCache($cacheSearchParamsArr);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $facets, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($cacheSearchParamsArr, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }

    /**
     * 书房搜索在售图书
     * 根据_author,_press,_itemname,isbn进行搜索
     */
    public function STUDY_searchSaledBooks()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $result = $this->getCache($this->searchParams);
        if(!$result) {
            $result = ElasticSearchModel::findDocument($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndex, $this->productServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array());
            if(isset($result['hits']) && isset($result['hits']['total']) && $result['hits']['total']) {
                $this->setCache($this->searchParams, $result);
            }
        }
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
}

?>