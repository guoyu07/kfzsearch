<?php

/**
 * 消息搜索模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年9月16日12:20:01
 */
class MessageElasticModel extends SearchModel
{
    private $requestParams; //请求参数数组
    private $messageServiceESHost;
    private $messageServiceESPort;
    private $messageServiceESIndex;
    private $messageServiceESType;
    private $ES_timeout;             //ES查询超时时间
    private $ES_dfs_query_then_fetch;//严格相关度搜索
    private $searchParams;
    private $pageSize;
    private $maxPageNum;
    private $returnTotal;

    /**
     * 消息搜索模型
     */
    public function __construct()
    {
        $this->requestParams      = array();
        $searchConfig             = Yaf\Registry::get('g_config')->search->toArray();
        $useIndex                 = 'message';
        $serviceKey               = $useIndex. 'ServiceES';
        $suggestServiceES_Cfg     = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if(empty($suggestServiceES_Cfg)) {
            return false;
        }
        $this->messageServiceESHost  = $suggestServiceES_Cfg['host'];
        $this->messageServiceESPort  = $suggestServiceES_Cfg['port'];
        $this->messageServiceESIndex = "message";
        $this->messageServiceESType  = "message";
        $this->ES_timeout            = 10;
        $this->ES_dfs_query_then_fetch = false;
        $this->pageSize              = 50;
        $this->maxPageNum            = 100;
        $this->returnTotal           = 5000;
    }
    
    public function formatRequestParams($requestParams)
    {
        $returnRequestParams = array();
        foreach ($requestParams as $k => $v) {
            $strLower = strtolower($k);
            $returnRequestParams[$strLower] = $v;
        }
        return $returnRequestParams;
    }

    /**
     * 解析请求参数为搜索条件
     * 
     * @param array $requestParams
     * @return array
     */
    public function translateParams($requestParams)
    {
        $requestParams = $this->formatRequestParams($requestParams);
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
        $searchParams['fields'] = array("messageid", "catid", "sender", "sendernickname", "receiver", "receivernickname", "msgcontent", "sendtime", "contentid");
        
        $searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);

        $searchParams['limit'] = array(
            'from' => 0,
            'size' => $this->pageSize
        );
        $searchParams['sort'] = array();
        
        if (isset($requestParams['catid']) && $requestParams['catid']) {
            $searchParams['filter']['must'][] = array('field' => 'catid', 'value' => $requestParams['catid']);
        }
        if (isset($requestParams['comeandgo']) && $requestParams['comeandgo'] == 1 && isset($requestParams['sender']) && $requestParams['sender'] && isset($requestParams['receiver']) && $requestParams['receiver']) {
            $searchParams['filter']['must_or_s'][] = array(
                array('field' => 'sender', 'value' => $requestParams['sender']),
                array('field' => 'receiver', 'value' => $requestParams['sender']),
            );
            $searchParams['filter']['must_or_s'][] = array(
                array('field' => 'sender', 'value' => $requestParams['receiver']),
                array('field' => 'receiver', 'value' => $requestParams['receiver']),
            );
        } else {
            if (isset($requestParams['sender']) && $requestParams['sender']) {
                $searchParams['filter']['must'][] = array('field' => 'sender', 'value' => $requestParams['sender']);
            }
            if (isset($requestParams['receiver']) && $requestParams['receiver']) {
                $searchParams['filter']['must'][] = array('field' => 'receiver', 'value' => $requestParams['receiver']);
            }
        }
        
        if (isset($requestParams['sendtime']) && $requestParams['sendtime']) {
            $sendtimeArr = explode('h', $requestParams['sendtime']);
            $range = array('field' => 'sendtime');
            if ($sendtimeArr[0] && strlen($sendtimeArr[0]) == 8 && is_numeric($sendtimeArr[0])) {
                $range['from'] = strtotime($sendtimeArr[0]);
            }
            if (isset($sendtimeArr[1]) && $sendtimeArr[1] && strlen($sendtimeArr[1]) == 8 && is_numeric($sendtimeArr[1])) {
                if ($sendtimeArr[1] == $sendtimeArr[0]) {
                    $timeTmp = strtotime($sendtimeArr[1]) + 86400;
                    $range['to'] = $timeTmp;
                } else {
                    $range['to'] = strtotime($sendtimeArr[1]);
                }
            }
            if ($sendtimeArr[0] && strlen($sendtimeArr[0]) == 10 && is_numeric($sendtimeArr[0])) {
                $range['from'] = $sendtimeArr[0];
            }
            if (isset($sendtimeArr[1]) && $sendtimeArr[1] && strlen($sendtimeArr[1]) == 10 && is_numeric($sendtimeArr[1])) {
                $range['to'] = $sendtimeArr[1];
            }
            $searchParams['filter']['range_must'][] = $range;
        }

        if (isset($requestParams['sendernickname']) && $requestParams['sendernickname']) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_sendernickname', 'value' => $requestParams['sendernickname']);
        }
        
        if (isset($requestParams['receivernickname']) && $requestParams['receivernickname']) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_receivernickname', 'value' => $requestParams['receivernickname']);
        }
        
        if (isset($requestParams['msgcontent']) && $requestParams['msgcontent']) {
            $searchParams['query']['type'] = 'bool';
            $searchParams['query']['must'][] = array('field' => '_msgcontent', 'value' => $requestParams['msgcontent']);
        }
        
        if (isset($requestParams['pagenum']) && intval($requestParams['pagenum'])) {
            $pageNum = intval($requestParams['pagenum']) <= 1 ? 1 : intval($requestParams['pagenum']);
            if ($pageNum > $this->maxPageNum) {
                $pageNum = 1;
            }
            $searchParams['limit'] = array(
                'from' => ($pageNum - 1) * $this->pageSize,
                'size' => $this->pageSize
            );
        }
        
        if (isset($requestParams['order']) && intval($requestParams['order'])) {
            switch (intval($requestParams['order'])) {
                case 1:
                    $searchParams['sort'] = array(array('field' => 'sendtime', 'order' => 'asc'));
                    break;
                case 2:
                    $searchParams['sort'] = array(array('field' => 'sendtime', 'order' => 'desc'));
                    break;
                case 3:
                    $searchParams['sort'] = array(array('field' => 'messageid', 'order' => 'asc'));
                    break;
                case 4:
                    $searchParams['sort'] = array(array('field' => 'messageid', 'order' => 'desc'));
                    break;
                default:
                    $searchParams['sort'] = array( "_score", array('field' => 'sendtime', 'order' => 'desc') );
                    break;
            }
        } else {
            $searchParams['sort'] = array( "_score", array('field' => 'sendtime', 'order' => 'desc') );
        }
        
        $this->searchParams = $searchParams;
        return $searchParams;
    }
        
    /**
     * 跟据用户条件获得productList
     */
    public function getMessageList()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $result = ElasticSearchModel::findDocument($this->messageServiceESHost, $this->messageServiceESPort, $this->messageServiceESIndex, $this->messageServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }
    
    public function formatSearchData($result)
    {
        $returnList = array(
            'total' => 0,
            'total_found' => 0,
            'time' => 0
        );
        if(empty($result)) {
            return $returnList;
        }
        if(isset($result['hits']) && isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
            foreach($result['hits']['hits'] as $v) {
                $returnList[] = $v['_source'];
            }
            $returnList['total'] = $this->returnTotal > $result['hits']['total'] ? $result['hits']['total'] : $this->returnTotal;
            $returnList['total_found'] = $result['hits']['total'];
            $returnList['time'] = 0;
        }
        
        return $returnList;
    }
}
?>