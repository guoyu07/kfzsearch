<?php

/**
 * 审核系统搜索模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2016年8月29日16:49:40
 */
class VerifyElasticModel extends SearchModel
{
    private $index;
    private $bizFlag;                //业务标识
    private $requestParams;          //请求参数数组
    private $otherParams;            //其它扩展参数数组
    private $searchParams;           //解析请求参数数组为搜索参数数组
    private $agent;                  //agent
    private $isSpiderFlag;           //判断是否为爬虫
    private $productServiceESHost;
    private $productServiceESPort;
    private $productServiceESIndex;
    private $productServiceESIndexSold;
    private $productServiceESIndexAll;
    private $productServiceESType;
    private $ES_timeout;             //ES查询超时时间
    private $ES_dfs_query_then_fetch;//严格相关度搜索
    
    /**
     * product搜索操作模型
     */
    public function __construct()
    {
        $this->index                 = '';
        $this->bizFlag               = '';
        $this->requestParams         = array();
        $this->otherParams           = array();
        $this->searchParams          = array();
        $this->agent                 = '';
        $this->isSpiderFlag          = false;
        $this->productServiceESHost  = '';
        $this->productServiceESPort  = '';
        $this->productServiceESIndex = '';
        $this->productServiceESIndexSold = '';
        $this->productServiceESIndexAll = '';
        $this->productServiceESType  = '';
        $this->ES_timeout            = 0;
        $this->ES_dfs_query_then_fetch = false;
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
        $this->productServiceESHost  = $productServiceES_Cfg['host'];
        $this->productServiceESPort  = $productServiceES_Cfg['port'];
        $this->productServiceESIndex = 'item';
        $this->productServiceESIndexSold = 'item_sold';
        $this->productServiceESIndexAll  = 'item,item_sold';
        $this->productServiceESType  = 'product';
        $this->ES_timeout            = 60;
        $this->ES_dfs_query_then_fetch = true;
        return true;
    }
    
    /**
     * 设置业务标识，在init之前执行设置
     */
    public function setBizFlag($bizFlag)
    {
        $this->bizFlag = $bizFlag;
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
        $returnState = true;
        $this->realIP = $realIP;
        //防恶意访问
//        if($realIP && !$this->isSpider($this->agent)) { //非爬虫
//        if($realIP) { //人 + 爬虫
//            if(substr($realIP, 0, 7) == '192.168' || 
//               substr($realIP, 0, 10) == '117.121.31' || 
//               substr($realIP, 0, 11) == '116.213.206') { //内网IP不做限制和公司外网IP不做限制
//                $returnState = true;
//            } else {
//                if($this->preventMaliciousAccess($realIP, $this->bizFlag, $this->agent) == true) {
//                    $this->agent = 'AbnormalAccess';
//                    $returnState = false;
//                }
//            }
//        }
        if($returnState) {
            $this->statistics($this->bizFlag);
        }
        return $returnState;
    }
    
    /**
     * 设置其它扩展参数数组
     */
    public function setOtherParams($otherParams)
    {
        $this->otherParams = $otherParams;
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
        if(isset($requestParams['indexName']) && $requestParams['indexName'] == $this->productServiceESIndex) {
            $indexName = $this->productServiceESIndex;
        } elseif (isset($requestParams['indexName']) && $requestParams['indexName'] == $this->productServiceESIndexSold) {
            $indexName = $this->productServiceESIndexSold;
        } elseif (isset($requestParams['indexName']) && $requestParams['indexName'] == $this->productServiceESIndexAll) {
            $indexName = $this->productServiceESIndexAll;
        } else {
            $indexName = $this->productServiceESIndex;
        }
        $this->searchParams = array(
            'ip' => $this->productServiceESHost,
            'port' => $this->productServiceESPort,
            'indexName' => $indexName,
            'indexType' => $this->productServiceESType,
            'isDebug' => 0,
            'fields' => isset($this->requestParams['fields']) ? $this->requestParams['fields'] : array(),
            'query' => isset($this->requestParams['query']) ? $this->requestParams['query'] : array(),
            'filter' => isset($this->requestParams['filter']) ? $this->requestParams['filter'] : array(),
            'sort' => isset($this->requestParams['sort']) ? $this->requestParams['sort'] : array(),
            'limit' => isset($this->requestParams['limit']) ? $this->requestParams['limit'] : array(),
            'highlight' => isset($this->requestParams['highlight']) ? $this->requestParams['highlight'] : array(),
            'facets' => isset($this->requestParams['facets']) ? $this->requestParams['facets'] : array(),
            'timeout' => isset($this->requestParams['timeout']) ? $this->requestParams['timeout'] : $this->ES_timeout,
            'dfsQuery' => isset($this->requestParams['dfsQuery']) ? $this->requestParams['dfsQuery'] : $this->ES_dfs_query_then_fetch,
        );
    }
    
    /**
     * 获取拼音
     * 
     * @return string
     */
    public function getPinyin()
    {
        $pinyinJson = '';
        if(!isset($this->otherParams['text']) || !$this->otherParams['text']) {
            return $pinyinJson;
        }
        $text = $this->otherParams['text'];
        $pinyinJson = ElasticSearchModel::getPinyin($this->productServiceESHost, $this->productServiceESPort, $this->productServiceESIndexSold, $text);
        
        return $pinyinJson;
    }
    
    /**
     * 获取搜索数据
     */
    public function getData()
    {
        $result = ElasticSearchModel::findDocument($this->searchParams['ip'], $this->searchParams['port'], $this->searchParams['indexName'], $this->searchParams['indexType'], $this->searchParams['isDebug'], $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $this->searchParams['facets'], $this->searchParams['timeout'], $this->searchParams['dfs_query_then_fetch']);
        
        return $result;
    }
    
}
?>