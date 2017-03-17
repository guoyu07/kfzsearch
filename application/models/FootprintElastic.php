<?php

/**
 * 足迹搜索模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年10月21日17:06:19
 */
class FootprintElasticModel extends SearchModel
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
     * 足迹搜索模型
     */
    public function __construct()
    {
        
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
        switch($this->bizFlag) {
            case 'footprint_search':
                $useIndex         = 'footprintSearch';
                $index            = 'footprint_searchword';
                $type             = 'footprint';
                break;
            case 'footprint_shop':
                $useIndex         = 'footprintShop';
                $index            = 'footprint_shop';
                $type             = 'footprint';
                break;
            case 'footprint_pm':
                $useIndex         = 'footprintPm';
                $index            = 'footprint_pm';
                $type             = 'footprint';
                break;
            case 'shop_recommend':
                $useIndex         = 'shopRecommend';
                $index            = 'shop_recommend';
                $type             = 'item';
                break;
            case 'search_recommend':
                $useIndex         = 'shopRecommend';
                $index            = 'shop_recommend';
                $type             = 'item';
                break;
            case 'search_shop_recommend':
                $useIndex         = 'shopRecommend';
                $index            = 'shop_recommend';
                $type             = 'item';
                break;
            case 'search_book_recommend':
                $useIndex         = 'shopRecommend';
                $index            = 'shop_recommend';
                $type             = 'item';
                break;
            case 'myRecommend':
                $useIndex         = 'shopRecommend';
                $index            = 'shop_recommend';
                $type             = 'item';
                break;
            default :
                return false;
        }
        $serviceKey               = $useIndex. 'ServiceES';
        $suggestServiceES_Cfg     = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if(empty($suggestServiceES_Cfg)) {
            return false;
        }
        $this->serviceESHost      = $suggestServiceES_Cfg['host'];
        $this->serviceESPort      = $suggestServiceES_Cfg['port'];
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
    
    public function translateParams($requestParams)
    {
        $this->requestParams = $requestParams;
        if(!isset($requestParams['userId']) || empty($requestParams['userId'])) {
            $this->searchParams['userId'] = '';
        } else {
            $this->searchParams['userId'] = $this->requestParams['userId'];
        }
        $pageNum = isset($requestParams['pageNum']) && intval($requestParams['pageNum']) > 1 ? intval($requestParams['pageNum']) : 1;
        $pageSize = isset($requestParams['pageSize']) && intval($requestParams['pageSize']) > 1 ? intval($requestParams['pageSize']) : 50;
        
        //200个总数限制
        $maxPageNum = ceil(200 / $pageSize);
        $pageNum = $pageNum > $maxPageNum ? $maxPageNum : $pageNum;
        
        $this->searchParams['limit'] = array(
            'from' => ($pageNum - 1) * $pageSize,
            'size' => $pageSize
        );
    }
    
    /**
     * 获取足迹推荐（拍卖）
     */
    public function getFootprintRecommendForShopOrPm()
    {
        $userId = intval($this->searchParams['userId']);
        $emptyResult = array(
            'total' => 0,
            'data'  => array()
        );
        if(!$userId) {
            return $emptyResult;
        }
        //获取用户最近200次浏览
        $queryStr = '{"_source":[],"query":{"bool":{"must":[{"term":{"viewerid":"'. $userId. '"}}]}},"size":"200","from": "0","sort":[{"inserttime":{"order":"desc"}}]}';
        $result = ElasticSearchModel::trunslateFindResult(ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch));
//        file_put_contents('/tmp/kfzsearch.log', var_export($result, true), FILE_APPEND);
        if($result['total'] == 0) {
            return $emptyResult;
        }
        $lastCatId = 0;
        $itemIdsList = array();
        $catsList = array();
        foreach($result['data'] as $k => $row) {
            $catId = $row['catid'];
            $itemId = $row['itemid'];
            if($k == 0) {
                $lastCatId = $catId; //获取用户最近一次浏览商品分类
            }
            if($k < 50) { //只取最近50个
                $itemIdsList[] = $itemId;
            }
            if(isset($catsList[$catId])) {
                ++$catsList[$catId]['count'];
            } else {
                $catsList[$catId] = array(
                    'catId' => $catId,
                    'count' => 1
                );
            }
        }
//        file_put_contents('/tmp/kfzsearch.log', var_export($catsList, true), FILE_APPEND);
//        file_put_contents('/tmp/kfzsearch.log', var_export($lastCatId, true), FILE_APPEND);
        //取两个分类
        $catsResult = array();
        $catsResultNum = 0;
        $catsNum = count($catsList);
        if($catsNum < 1) {
            return $emptyResult;
        }
        if ($catsNum == 1) {
            $catsResult[] = $lastCatId;
            $catsResultNum = 1;
        } elseif ($catsNum > 1) {
            //取最近浏览最多的分类
            $catsOrderList = array();
            foreach($catsList as $cat) {
                $catsOrderList[] = $cat['count'];
            }
            $lastCatExistFlag = array_key_exists($lastCatId, $catsList) ? 1 : 0;
            array_multisort($catsOrderList, SORT_DESC, $catsList);
            $firAndSecCat = array_slice($catsList, 0, 2);
            if(!$lastCatExistFlag) { //如果不存在
                foreach($firAndSecCat as $cat) {
                    $catsResult[] = $cat['catId'];
                }
            } else { //如果存在
                $catsResult[] = $lastCatId;
                $firCat = array_shift($firAndSecCat);
                if(in_array($firCat['catId'], $catsResult)) {
                    $sedCat = array_pop($firAndSecCat);
                    $catsResult[] = $sedCat['catId'];
                } else {
                    $catsResult[] = $firCat['catId'];
                }
            }
            $catsResultNum = 2;
        }
//        file_put_contents('/tmp/kfzsearch.log', var_export($catsResult, true), FILE_APPEND);
        
        //获取两个分类下最热门的商品
        if($catsResultNum == 1) { //只有一个分类时，取近60条
            $queryStr = '{"size": 0,"aggs": {"group_by_itemid": {"filter":{"bool":{"must":[{"term":{"catid":"'. $catsResult[0]. '"}}]}},"aggs": {"itemid_return": {"terms": {"field": "itemid","size":"60"}}}}}}';
            $result = ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(!isset($result['aggregations']['group_by_itemid']['itemid_return']['buckets']) || empty($result['aggregations']['group_by_itemid']['itemid_return']['buckets'])) {
                return $emptyResult;
            }
            $itemIdsByCat = array();
            foreach($result['aggregations']['group_by_itemid']['itemid_return']['buckets'] as $row) {
                $itemIdsByCat[] = $row['key'];
            }
            $itemIdsByCatNum = count($itemIdsByCat);
            foreach($itemIdsByCat as $k => $itemId) {
                if(in_array($itemId, $itemIdsList)) {
                    unset($itemIdsByCat[$k]);
                }
            }
            $itemIdsResult = array_slice($itemIdsByCat, 0, 10);
        } elseif ($catsResultNum == 2) { //两个分类时，每个分类取30条
            //取cat1，group by itemid，first 30
            $queryStr = '{"size": 0,"aggs": {"group_by_itemid": {"filter":{"bool":{"must":[{"term":{"catid":"'. $catsResult[0]. '"}}]}},"aggs": {"itemid_return": {"terms": {"field": "itemid","size":"30"}}}}}}';
            $result1 = ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(!isset($result1['aggregations']['group_by_itemid']['itemid_return']['buckets']) || empty($result1['aggregations']['group_by_itemid']['itemid_return']['buckets'])) {
                return $emptyResult;
            }
            $itemIdsByCat1 = array();
            foreach($result1['aggregations']['group_by_itemid']['itemid_return']['buckets'] as $row) {
                $itemIdsByCat1[] = $row['key'];
            }
            foreach($itemIdsByCat1 as $k => $itemId) {
                if(in_array($itemId, $itemIdsList)) {
                    unset($itemIdsByCat1[$k]);
                }
            }
            if(count($itemIdsByCat1) > 5) {
                $itemIdsByCat1 = array_slice($itemIdsByCat1, 0, 5);
            }
            //取cat2，group by itemid，first 30
            $queryStr = '{"size": 0,"aggs": {"group_by_itemid": {"filter":{"bool":{"must":[{"term":{"catid":"'. $catsResult[1]. '"}}]}},"aggs": {"itemid_return": {"terms": {"field": "itemid","size":"30"}}}}}}';
            $result2 = ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
            if(!isset($result2['aggregations']['group_by_itemid']['itemid_return']['buckets']) || empty($result2['aggregations']['group_by_itemid']['itemid_return']['buckets'])) {
                return $emptyResult;
            }
            $itemIdsByCat2 = array();
            foreach($result2['aggregations']['group_by_itemid']['itemid_return']['buckets'] as $row) {
                $itemIdsByCat2[] = $row['key'];
            }
            foreach($itemIdsByCat2 as $k => $itemId) {
                if(in_array($itemId, $itemIdsList)) {
                    unset($itemIdsByCat2[$k]);
                }
            }
            $itemIdsByCat2 = array_slice($itemIdsByCat2, 0, 10 - count($itemIdsByCat1));
            $itemIdsResult = array_merge($itemIdsByCat1, $itemIdsByCat2);
        }
//        file_put_contents('/tmp/kfzsearch.log', var_export($itemIdsResult, true), FILE_APPEND);
        //根据商品ID取商品信息
        $queryFilterStr = '';
        foreach($itemIdsResult as $itemId) {
            $queryFilterStr .= '{"term":{"itemid":"'. $itemId. '"}},';
        }
        $queryFilterStr = trim($queryFilterStr, ',');
        $queryStr = '{"size": 0,"aggs": {"group_by_itemid": {"filter":{"bool":{"must":[{"or":['. $queryFilterStr. ']}]}},"aggs": {"itemid_return": {"terms": {"field": "itemid","size":"10"},"aggs" : {"id_return" : {"terms" : {"field" : "id","size" : 1}}}}}}}}';
        $result = ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        $ids = array();
        if(!isset($result['aggregations']['group_by_itemid']['itemid_return']['buckets']) || empty($result['aggregations']['group_by_itemid']['itemid_return']['buckets'])) {
            return $emptyResult;
        }
//        file_put_contents('/tmp/kfzsearch.log', var_export($result['aggregations']['group_by_itemid']['itemid_return']['buckets'], true), FILE_APPEND);
        foreach($result['aggregations']['group_by_itemid']['itemid_return']['buckets'] as $row) {
            $ids[] = $row['id_return']['buckets'][0]['key'];
        }
//        file_put_contents('/tmp/kfzsearch.log', var_export($ids, true), FILE_APPEND);
        $queryFilterStr = '';
        foreach($ids as $id) {
            $queryFilterStr .= '{"term":{"id":"'. $id. '"}},';
        }
        $queryFilterStr = trim($queryFilterStr, ',');
        $queryStr = '{"_source":[],"filter":{"bool":{"must":[{"or":['. $queryFilterStr. ']}]}},"size":"10","from":"0"}';
//        file_put_contents('/tmp/kfzsearch.log', var_export($queryStr, true), FILE_APPEND);
        $result = ElasticSearchModel::trunslateFindResult(ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch));
        return $result;
    }
    
    
    
    
    /**
     * 取shop_recommend推荐默认数据(规则：按书店分类，书店信誉值从高向低排序，每个书店取最新上的一本书)
     */
    private function getShopRecommendDefaultData($size = 10)
    {
        $emptyResult = array(
            'total' => 0,
            'data'  => array()
        );
        /*
        return $emptyResult; //因推荐默认数据，负载过高，暂不推荐默认
        $queryStr = '
{
    "size": 0,
    "aggs": {
        "group_by_itemid": {
            "filter": {"bool": {"must": [{"term": {"isdeleted": "0"}}],"must_not": [{"term": {"count": "0"}}]}},
            "aggs": {
                "group_by_shopid": {
                    "terms": {
                        "field": "shopid",
                        "size": "'. $size*2 . '",
                        "order" : { "shoptrust_return>shoptrust.avg" : "desc" }
                    },
                    "aggs": {
                        "itemid_return": {
                            "terms": {
                                "field": "itemid",
                                "size": 1,
                                "order" : { "addtime_return>addtime.avg" : "desc" }
                            },
                            "aggs": {
				"addtime_return" : {
					"filter": {"bool": {"must": [{"term": {"isdeleted": "0"}}],"must_not": [{"term": {"count": "0"}}]}},
					"aggs" : {
						"addtime" : { "stats" : { "field" : "addtime" }}
					}
				}
                            }
                        },
			"shoptrust_return" : {
				"filter": {"bool": {"must": [{"term": {"isdeleted": "0"}}],"must_not": [{"term": {"count": "0"}}]}},
				"aggs" : {
					"shoptrust" : { "stats" : { "field" : "shoptrust" }}
				}
			}
                    }
                }
            }
        }
    }
}';
        $result = ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        if (!isset($result['aggregations']) || !isset($result['aggregations']['group_by_itemid']) || !isset($result['aggregations']['group_by_itemid']['group_by_shopid']) || !isset($result['aggregations']['group_by_itemid']['group_by_shopid']['buckets']) || count($result['aggregations']['group_by_itemid']['group_by_shopid']['buckets']) < 1) {
            return $emptyResult;
        }
        $idStr = '';
        foreach ($result['aggregations']['group_by_itemid']['group_by_shopid']['buckets'] as $row) {
            if (!isset($row['itemid_return']) || !isset($row['itemid_return']['buckets']) || count($row['itemid_return']['buckets']) < 1) {
                continue;
            }
            $itemid = $row['itemid_return']['buckets'][0]['key'];
            $idStr .= '{"term":{"itemid":"' . $itemid . '"}},';
        }
        $queryIds = '{"filter":{"bool":{"must":[{"term": {"isdeleted": "0"}},{"or":[' . trim($idStr, ',') . ']}],"must_not": [{"term": {"count": "0"}}]}},"size":"' . $size*2 .'"}';
        $findResult = ElasticSearchModel::trunslateFindResult(ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryIds, $this->ES_timeout, $this->ES_dfs_query_then_fetch));
         * 
         */
        $queryStr = '{"query":{"bool":{"must":[{"range": {"count": {"from": 0,"include_lower": false}}}]}},"sort":[{"count":{"order":"desc"}}],"size":"'. $size. '"}';
        $findResult = ElasticSearchModel::trunslateFindResult(ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch));
        $sliceArr = array_slice($findResult['data'], 0, $size);
        $returnRet = array(
            'total' => count($sliceArr),
            'data' => $sliceArr
        );
        return $returnRet;
    }
    
    /**
     * 将用户加到推荐数据获取队列
     */
    private function getShopRecommendUserListAdd($userId)
    {
        $userExistsFlag   = 'get_shopRecommend_'. $userId;
        if($this->cache->exists($userExistsFlag)) { //如果用户已经在请求队列中
            return true;
        }
        
        $userListKey      = 'IndexUpdateES:get_shop_recommend';
        $pushTpl = array(
            'index' => 'get_shop_recommend',
            'type' => 'get_shop_recommend',
            'action' => 'customdeal',
            'user' => $this->bizFlag,
            'time' => date("Y-m-d H:i:s"),
            'data' => array(
                'userId' => ''
            )
        );
        
        $pushTpl['data']['userId'] = $userId;
        $pushJson = json_encode($pushTpl);
        if($this->cache->rpush($userListKey, $pushJson)) {
            $this->cache->set($userExistsFlag, 1, 300); //设置用户已经在请求队列标识，待请求消息处理后删除
        }
        
        return true;
    }

    
    /**
     * 获取推荐数据
     */
    public function getShopRecommend()
    {
        $userId = intval($this->searchParams['userId']);
        $emptyResult = array(
            'total' => 0,
            'data'  => array()
        );
        if(!$userId) {
            return $emptyResult;
        }
        if(!$this->cache) {
            return $emptyResult;
        }
        
        //从cache中取该用户推荐数据
        $itemListCacheKey = 'shopRecommend_'. $userId;
        
        //一次用户取得
        $perUserNum = 15;
        
        if($this->cache->llen($itemListCacheKey) > 0) { //cache中有数据
            //从cache中取前$perUserNum+20条数据
            $cacheData = $this->cache->lrange($itemListCacheKey, 0, $perUserNum+19);
//            if($userId == '201253') {
//                file_put_contents('/tmp/kfzsearch.log', "从cache中取前\$perUserNum+20条数据:". var_export($cacheData, true). "\n", FILE_APPEND);
//            }
            //将前$perUserNum+20条数据剪掉
            $this->cache->ltrim($itemListCacheKey, $perUserNum+20, -1);
//            if($userId == '201253') {
//                file_put_contents('/tmp/kfzsearch.log', "将前\$perUserNum+20条数据剪掉:". $this->cache->llen($itemListCacheKey). "\n", FILE_APPEND);
//            }
            $idStr = '';
            foreach($cacheData as $itemid) {
                $idStr .= '{"term":{"itemid":"'. $itemid . '"}},';
            }
//            $queryIds = '{"filter":{"bool":{"must":[{"term": {"isdeleted": "0"}},{"or":['. trim($idStr, ',') . ']}],"must_not": [{"term": {"count": "0"}}]}},"size":"'. ($perUserNum+10) . '"}';
            $queryIds = '{"filter":{"bool":{"must":[{"term": {"isdeleted": "0"}},{"or":['. trim($idStr, ',') . ']}]}},"size":"'. ($perUserNum+20) . '"}';
            $result = ElasticSearchModel::trunslateFindResult(ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryIds, $this->ES_timeout, $this->ES_dfs_query_then_fetch));
//            if($userId == '201253') {
//                file_put_contents('/tmp/kfzsearch.log', $queryIds. "\n", FILE_APPEND);
//                file_put_contents('/tmp/kfzsearch.log', var_export($result, true). "\n", FILE_APPEND);
//            }
            if(!$result['status'] || !$result['total']) { //数据全部失效
                //加入用户请求队列
                $this->getShopRecommendUserListAdd($userId);
                
                //取默认数据
                return $this->getShopRecommendDefaultData($perUserNum);
            }
            if($result['total'] < $perUserNum) { //有效数据不超过$perUserNum个
                $defaultData = $this->getShopRecommendDefaultData($perUserNum - $result['total']);
                $returnRet = array(
                    'total' => $result['total'] + $defaultData['total'],
                    'data' => array_merge($result['data'], $defaultData['data'])
                );
                
                //加入用户请求队列
                $this->getShopRecommendUserListAdd($userId);
                
                //去重
                $bookIdsTmp = array();
                foreach($returnRet['data'] as $k => $v) {
                    if(in_array($v['itemid'], $bookIdsTmp)) {
                        unset($returnRet['data'][$k]);
                        break;
                    }
                    $bookIdsTmp[] = $v['itemid'];
                }
                return $returnRet;
            } else { //有效数据大于$perUserNum个，取$perUserNum个后，其余放回
                $returnRet = array(
                    'total' => 0,
                    'data' => array()
                );
                for($i = 0; $i < $perUserNum; $i++) {
                    $returnRet['data'][] = array_shift($result['data']);
                    ++$returnRet['total'];
                }
//                if($userId == '201253') {
//                    file_put_contents('/tmp/kfzsearch.log', "返回数据:". var_export($returnRet, true). "\n", FILE_APPEND);
//                    file_put_contents('/tmp/kfzsearch.log', "其余放回:". var_export($result, true). "\n", FILE_APPEND);
//                }
                //其余放回
                if(!empty($result['data'])) {
                    foreach($result['data'] as $row) {
                        $this->cache->lpush($itemListCacheKey, $row['itemid']);
                    }
                    if($this->cache->llen($itemListCacheKey) < $perUserNum) { //判断剩余是否能够满足一次返回数量
                        //加入用户请求队列
                        $this->getShopRecommendUserListAdd($userId);
                    }
                } else {
                    //加入用户请求队列
                    $this->getShopRecommendUserListAdd($userId);
                }
                return $returnRet;
            }
        } else {
            //cache中没有数据，加入用户请求队列
            $this->getShopRecommendUserListAdd($userId);
            
            //取默认数据
            return $this->getShopRecommendDefaultData($perUserNum);
        }
    }
    
    
    /**
     * 获取用户商品浏览足迹
     */
    public function getShopFootprint()
    {
        $userId = intval($this->searchParams['userId']);
        $emptyResult = array(
            'total' => 0,
            'data'  => array()
        );
        if(!$userId) {
            return $emptyResult;
        }
        //获取用户足迹
        $queryStr = '{"_source":["id","itemid","itemname","imgurl","viewerid","sellerid","shopid","count","inserttime"],"query":{"bool":{"must":[{"term":{"viewerid":"'. $userId. '"}},{"term":{"isdeleted":"0"}}]}},"size":"'. $this->searchParams['limit']['size']. '","from": "'. $this->searchParams['limit']['from']. '","sort":[{"inserttime":{"order":"desc"}}]}';
        $result = ElasticSearchModel::trunslateFindResult(ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch));
//        file_put_contents('/tmp/kfzsearch.log', var_export($result, true), FILE_APPEND);
        if($result['total'] == 0) {
            return $emptyResult;
        }
        
        return $result;
    }
    
    
    /**
     * 获取用户拍品浏览足迹
     */
    public function getPmFootprint()
    {
        $userId = intval($this->searchParams['userId']);
        $emptyResult = array(
            'total' => 0,
            'data'  => array()
        );
        if(!$userId) {
            return $emptyResult;
        }
        //获取用户足迹
        $queryStr = '{"_source":["id","itemid","itemname","imgurl","viewerid","sellerid","shopid","count","inserttime"],"query":{"bool":{"must":[{"term":{"viewerid":"'. $userId. '"}},{"term":{"isdeleted":"0"}}]}},"size":"'. $this->searchParams['limit']['size']. '","from": "'. $this->searchParams['limit']['from']. '","sort":[{"inserttime":{"order":"desc"}}]}';
        $result = ElasticSearchModel::trunslateFindResult(ElasticSearchModel::findDocumentByJson($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, $queryStr, $this->ES_timeout, $this->ES_dfs_query_then_fetch));
//        file_put_contents('/tmp/kfzsearch.log', var_export($result, true), FILE_APPEND);
        if($result['total'] == 0) {
            return $emptyResult;
        }
        
        return $result;
    }
}
?>