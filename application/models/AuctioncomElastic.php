<?php

/**
 * Created by PhpStorm.
 * User: diao
 * Date: 16-9-12
 * Time: 下午5:27
 */
class AuctioncomElasticModel extends SearchModel
{
    private $serviceESHost;
    private $serviceESPort;
    private $serviceESIndex;
    private $serviceESType;
    private $ES_timeout;             //ES查询超时时间
    private $ES_dfs_query_then_fetch;//严格相关度搜索
    private $searchParams;
    private $pageSize;
    private $maxPageNum;
    private $returnTotal;
    private $agent;
    private $bizFlag;       //业务标识

    /**
     * 消息搜索模型
     */
    public function __construct()
    {
        $searchConfig = Yaf\Registry::get('g_config')->search->toArray();
        $serviceES_Cfg = ElasticSearchModel::getServer($searchConfig['auctioncomServiceES']);
        if (empty($serviceES_Cfg)) {
            return false;
        }
        $this->serviceESHost = $serviceES_Cfg['host'];
        $this->serviceESPort = $serviceES_Cfg['port'];
        $this->serviceESIndex = "auctioncom";
        $this->serviceESType = "auctioncom";
        $this->ES_timeout = 10;
        $this->ES_dfs_query_then_fetch = false;
        $this->pageSize = 50;
        $this->maxPageNum = 100;
        $this->returnTotal = 5000;
        $this->searchParams = array();
    }

    /**
     * 设置agent
     * @param $agent
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
        if ($realIP) { //人 + 爬虫
            if (substr($realIP, 0, 7) == '192.168' ||
                substr($realIP, 0, 10) == '117.121.31' ||
                substr($realIP, 0, 11) == '116.213.206'
            ) { //内网IP不做限制和公司外网IP不做限制
                return true;
            }
            if ($this->preventMaliciousAccess($realIP, $this->bizFlag, $this->agent) == true) {
                $this->agent = 'AbnormalAccess';
                return false;
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
    }

    /**
     * 格式化搜索数据
     * @param $result
     * @return array
     */
    private function formatSearchData($result)
    {
        $returnList = array(
            'total' => 0,
            'total_found' => 0,
            'time' => 0
        );
        if (empty($result)) {
            return $returnList;
        }
        if (isset($result['hits']) && isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
            foreach ($result['hits']['hits'] as $v) {
                $returnList[] = $v['_source'];
            }
            $returnList['total'] = $this->returnTotal > $result['hits']['total'] ? $result['hits']['total'] : $this->returnTotal;
            $returnList['total_found'] = $result['hits']['total'];
            $returnList['time'] = 0;
        }

        return $returnList;
    }

    /**
     * Format request params
     * @param $requestParams
     * @return array
     */
    public function formatRequestParams($requestParams)
    {
        $returnRequestParams = array();
        foreach ($requestParams as $k => $v) {
            $strLower = strtolower($k);
            $returnRequestParams[$strLower] = $v;
        }
        $this->searchParams = $returnRequestParams;
    }

    /**
     * Search data from ES
     * @param $requestParams
     */
    public function getAuctioncomList()
    {
        if (empty($this->searchParams)) {
            return array();
        }
        $params = array();
        foreach ($this->searchParams as $k => $v) {
            $params[$v['key']] = $v['value'];
        }
//        file_put_contents('/tmp/kfzsearch.log', var_export($params, true). "\n", FILE_APPEND);

        $ESParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $ESParams['filter']['must'][] = array('field' => 'ishidden', 'value' => 0);
        $ESParams['limit'] = array('from' => ($params['pagenum'] - 1) * 20, 'size' => 20);
        $ESParams['sort'] = array(array('field' => 'begintime2', 'order' => 'desc'));
        $ESParams['query']['type'] = 'bool';
        if (isset($params['itemname']) && !empty($params['itemname'])) {
            $ESParams['query']['must'][] = array('field' => '_itemname', 'value' => $this->fan2jian($params['itemname']), 'type' => 'include');
        }
        if (isset($params['comid']) && !empty($params['comid'])) {
            $ESParams['query']['must'][] = array('field' => 'comid', 'value' => $params['comid']);
        }

        $result = ElasticSearchModel::findDocument(
            $this->serviceESHost,
            $this->serviceESPort,
            $this->serviceESIndex,
            $this->serviceESType,
            0,
            $ESParams['fields'],
            $ESParams['query'],
            $ESParams['filter'],
            $ESParams['sort'],
            $ESParams['limit'],
            $ESParams['highlight'],
            array(),
            $this->ES_timeout,
            $this->ES_dfs_query_then_fetch);

        return $this->formatSearchData($result);
    }
}