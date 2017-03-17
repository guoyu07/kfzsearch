<?php

/**
 * Created by PhpStorm.
 * User: diao
 * Date: 16-9-22
 * Time: 下午2:28
 */
class StudyElasticModel extends SearchModel
{
    private $serviceESHost;
    private $serviceESPort;
    private $serviceESIndex;
    private $serviceESType;
    private $ES_timeout;
    private $ES_dfs_query_then_fetch;
    private $pageSize;
    private $maxPageNum;
    private $returnTotal;
    private $searchParams;
    private $agent;
    private $bizFlag;

    /**
     * 消息搜索模型
     */
    public function __construct()
    {
        $searchConfig = Yaf\Registry::get('g_config')->search->toArray();
        $useIndex = 'study';
        $serviceKey = $useIndex . 'ServiceES';
        $serviceES_Cfg = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if (empty($serviceES_Cfg)) {
            return false;
        }
        $this->serviceESHost = $serviceES_Cfg['host'];
        $this->serviceESPort = $serviceES_Cfg['port'];
        $this->serviceESIndex = "shufang";
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
        $returnState = true;
        $this->realIP = $realIP;
        //防恶意访问
//        if($realIP && !$this->isSpider($this->agent)) { //非爬虫
        if ($realIP) { //人 + 爬虫
            if (substr($realIP, 0, 7) == '192.168' ||
                substr($realIP, 0, 10) == '117.121.31' ||
                substr($realIP, 0, 11) == '116.213.206'
            ) { //内网IP不做限制和公司外网IP不做限制
                $returnState = true;
            } else {
                if ($this->preventMaliciousAccess($realIP, $this->bizFlag, $this->agent) == true) {
                    $this->agent = 'AbnormalAccess';
                    $returnState = false;
                }
            }
        }
        if ($returnState) {
            $this->statistics($this->bizFlag);
        }
        return $returnState;
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
    private function formatRequestParams($requestParams)
    {
        $returnRequestParams = array();
        foreach ($requestParams as $k => $v) {
            $strLower = strtolower($k);
            $returnRequestParams[$strLower] = $v;
        }
        return $returnRequestParams;
    }

    public function getBooks($requestParams)
    {
        $this->serviceESType = 'study_book_search';
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        if (is_array($params)) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "press^50", "_bookname^300", "isbn^30"), 'key' => $params['key'], 'minimum_should_match' => '100%', 'type' => 'cross_fields');
        }

        $result = ElasticSearchModel::findDocument($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    public function getStudys($requestParams)
    {
        $this->serviceESType = 'study_search';
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);
        if (is_array($params)) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_nickname^60", "_studyname^300"), 'key' => $params['key'], 'minimum_should_match' => '100%', 'type' => 'cross_fields');
        }

        $result = ElasticSearchModel::findDocument($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    public function getMyBooks($requestParams)
    {
        $this->serviceESType = 'study_book_search';
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);
        if (is_array($params)) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['should-dis_max']['queries'][] = array('isMulti' => 1, 'fields' => array("_author^60", "press^50", "_bookname^300", "isbn^30"), 'key' => $params['key'], 'minimum_should_match' => '100%', 'type' => 'cross_fields');
            $this->searchParams['query']['must'][] = array('field' => 'studyid', 'value' => $params['studyid']);
        }
        $result = ElasticSearchModel::findDocument($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    public function getStudyById($requestParams)
    {
        $this->serviceESType = 'study_search';
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);
        if (is_array($params)) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'studyid', 'value' => $params['studyid']);
        }

        $result = ElasticSearchModel::findDocument($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    public function getBookForDel($requestParams)
    {
        $this->serviceESType = 'study_book_search';
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        if (is_array($params)) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'studyid', 'value' => $params['studyid']);
            $this->searchParams['query']['must'][] = array('field' => 'bookid', 'value' => $params['bookid']);
            $this->searchParams['query']['must'][] = array('field' => 'bookfrom', 'value' => $params['bookfrom']);
        }

        $result = ElasticSearchModel::findDocument($this->serviceESHost, $this->serviceESPort, $this->serviceESIndex, $this->serviceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

}