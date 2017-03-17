<?php

/**
 * Created by diao
 * Date: 16-8-25
 * Time: 下午12:07
 */
class BooklibElasticModel extends SearchModel
{
    private $bookServiceESHost;
    private $bookServiceESPort;
    private $bookServiceESIndex;
    private $bookServiceESType;
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
        $useIndex = 'booklib';
        $serviceKey = $useIndex . 'ServiceES';
        $suggestServiceES_Cfg = ElasticSearchModel::getServer($searchConfig[$serviceKey]);
        if (empty($suggestServiceES_Cfg)) {
            return false;
        }
        $this->bookServiceESHost = $suggestServiceES_Cfg['host'];
        $this->bookServiceESPort = $suggestServiceES_Cfg['port'];
        $this->bookServiceESIndex = "booklib";
        $this->bookServiceESType = "books";
        $this->ES_timeout = 10;
        $this->ES_dfs_query_then_fetch = false;
        $this->pageSize = 50;
        $this->maxPageNum = 100;
        $this->returnTotal = 5000;
        $this->searchParams = array(
            'fields'    => array(),
            'query'     => array(),
            'filter'    => array(),
            'sort'      => array(),
            'limit'     => array(),
            'highlight' => array(),
            'facets'    => array()
        );
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


    /**
     * 跟据JD分类ID搜索图书
     * @param $requestParams
     * @return array
     */
    public function getBooksByJcat($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('bookid', 'bookname', 'author', 'press', 'pubdate', 'normalimg', 'contentintroduction');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        $this->searchParams['limit'] = array(
            'from' => intval($params['pagenum']) > 0 ? ($params['pagenum'] - 1) * $params['pagesize'] : 0,
            'size' => $this->searchParams['pagesize']
        );
        if (isset($params['jcatid1']) && !empty($params['jcatid1'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'jcatid1', 'value' => $params['jcatid1']);
        }
        if (isset($params['jcatid2']) && !empty($params['jcatid2'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'jcatid2', 'value' => $params['jcatid2']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 跟据JD分类ID和pressId搜索图书
     * @param $requestParams
     * @return array
     */
    public function getBooksByJcatAndPressId($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('bookid', 'bookname', 'author', 'press', 'pubdate', 'normalimg', 'contentintroduction');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);

        $this->searchParams['limit'] = array(
            'from' => intval($params['pagenum']) > 0 ? ($params['pagenum'] - 1) * $params['pagesize'] : 0,
            'size' => $this->searchParams['pagesize']
        );

        if (isset($params['jcatid1']) && !empty($params['jcatid1'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'jcatid1', 'value' => $params['jcatid1']);
        }
        if (isset($params['jcatid2']) && !empty($params['jcatid2'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'jcatid2', 'value' => $params['jcatid2']);
        }

        if (isset($params['pressid']) && !empty($params['pressid'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'pressid', 'value' => $params['pressid']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 跟据JD分类ID和authorId搜索图书
     * @param $requestParams
     * @return array
     */
    public function getBooksByJcatAndAuthorId($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('bookid', 'bookname', 'author', 'press', 'pubdate', 'normalimg', 'contentintroduction', 'jcatid1');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);

        $this->searchParams['limit'] = array(
            'from' => intval($params['pagenum']) > 0 ? ($params['pagenum'] - 1) * $params['pagesize'] : 0,
            'size' => $this->searchParams['pagesize']
        );

        if (isset($params['jcatid1']) && !empty($params['jcatid1'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'jcatid1', 'value' => $params['jcatid1']);
        }
        if (isset($params['jcatid2']) && !empty($params['jcatid2'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'jcatid2', 'value' => $params['jcatid2']);
        }

        if (isset($params['authorid']) && !empty($params['authorid'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'authorid', 'value' => $params['authorid']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 图书资料库搜索图书跟据书ID
     * @param $requestParams
     * @return array
     */
    public function getBookDetailById($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array();
        if (isset($params['bookid']) && !empty($params['bookid'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'bookid', 'value' => $params['bookid']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 图书资料库搜索图书跟据作家ID
     * @param $requestParams
     * @return array
     */
    public function searchBooksByAuthorId($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('bookid', 'bookname', 'normalimg');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        $this->searchParams['limit'] = array('from' => 0, 'size' => $params['pagesize']);
        if (isset($params['authorid']) && !empty($params['authorid'])) {
            $this->searchParams['filter']['must'][] = array('field' => 'authorid', 'value' => $params['authorid']);
        }
        if (isset($params['bookid']) && !empty($params['bookid'])) {
            $this->searchParams['filter']['must'][] = array('field' => 'bookid', 'value' => $params['bookid']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 图书资料库搜索图书跟据JD分类ID
     * @param $requestParams
     * @return array
     */
    public function searchBooksByJcatId($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('bookid', 'bookname', 'normalimg');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        $this->searchParams['limit'] = array('from' => 0, 'size' => $params['pagesize']);
        if (isset($params['jcatid2']) && !empty($params['jcatid2'])) {
            $this->searchParams['filter']['must'][] = array('field' => 'jcatid2', 'value' => $params['jcatid2']);
        }
        if (isset($params['bookid']) && !empty($params['bookid'])) {
            $this->searchParams['filter']['must'][] = array('field' => 'bookid', 'value' => $params['bookid']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 获得JD分类
     * @param $requestParams
     * @return array
     */
    public function getGroupJDList($requestParams)
    {
        // Begin format
        $this->searchParams['fields'] = array('jcatid1');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        $this->searchParams['limit'] = array('from' => 0, 'size' => 100);
        $this->searchParams['facets']['num'] = array(array('field' => 'jcatid1'));
        // End format
        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $this->searchParams['facets'], $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 图书资料库里按出版社下书籍数量多少倒序搜索出版社
     * @param $requestParams
     * @return array
     */
    public function getPressListByBooksNum($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('pressid', 'pressname', 'pressurl');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'pressid', 'value' => 0);
        if (isset($params['page']) && !empty($params['page']) && isset($params['pagesize']) && !empty($params['pagesize'])) {
            $this->searchParams['limit'] = array('from' => ($params['page'] - 1) * $this->pageSize, 'size' => $this->pageSize);
        }
        $this->searchParams['facets']['num'] = array(array('field' => 'pressid'));
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $this->searchParams['facets'], $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 出版社下的书籍的分类
     * @param $requestParams
     * @return array
     */
    public function getBookCatsByPressId($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('jcatid1', 'pressid', 'pressname', 'pressurl');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'jcatid1', 'value' => 0);
        $this->searchParams['limit'] = array('from' => 0, 'size' => 100);
        if (isset($params['pressid']) && !empty($params['pressid'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'] = array('field' => 'pressid', 'value' => $params['pressid']);
        }
        $this->searchParams['facets']['num'] = array(array('field' => 'jcatid1'));
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $this->searchParams['facets'], $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 作家书籍的分类
     * @param $requestParams
     * @return array
     */
    public function getBookCatsByAuthorId($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('jcatid1', 'authorid');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'jcatid1', 'value' => 0);
        $this->searchParams['limit'] = array('from' => 0, 'size' => 100);
        if (isset($params['authorid']) && !empty($params['authorid'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => $params['authorid']);
        }
        $this->searchParams['facets']['num'] = array(array('field' => 'jcatid'));
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], $this->searchParams['facets'], $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 跟据JD分类ID搜索图书
     * @param $requestParams
     * @return array
     */
    public function getRecBooksByJcat($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array('bookid', 'bookname', 'author', 'press', 'pubdate', 'normalimg', 'contentintroduction');
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        if (isset($params['num']) && !empty($params['num'])) {
            $this->searchParams['limit'] = array('from' => 0, 'size' => $params['num']);
        }
        if (isset($params['jcatid2']) && !empty($params['jcatid2'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => 'jcatid2', 'value' => $params['jcatid2']);
        }
        $this->searchParams['sort'] = array(array('field' => 'bookid', 'order' => 'desc'));
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * 跟据author get book detail
     * @param $requestParams
     * @return array
     */
    public function getDetailWithAuthor($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array();
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        $this->searchParams['limit'] = array('from' => 0, 'size' => 1);
        if (isset($params['authorname']) && !empty($params['authorname'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['must'][] = array('field' => '_author', 'value' => $params['authorname']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    /**
     * get book detail base bookname isbn
     * @param $requestParams
     * @return array
     */
    public function searchBooksByBookNameIsbn($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array();
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        if (isset($params['pagesize']) && !empty($params['pagesize']) && isset($params['pagenum']) && !empty($params['pagenum'])) {
            $this->searchParams['limit'] = array('from' => ($params['pagenum'] - 1) * $params['pagesize'], 'size' => $params['pagesize']);
        }
        if (isset($params['key']) && !empty($params['key'])) {
            $this->searchParams['query']['type'] = 'bool';
            $this->searchParams['query']['should'][] = array('field' => '_bookname', 'value' => $this->fan2jian($params['key']));
            $this->searchParams['query']['should'][] = array('field' => 'isbn', 'value' => $params['key']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }

    public function searchBookWithAPForShop($requestParams)
    {
        if (empty($requestParams)) {
            return $this->formatSearchData(array());
        }
        $params = $this->formatRequestParams($requestParams);

        // Begin format
        $this->searchParams['fields'] = array();
        $this->searchParams['filter']['must'][] = array('field' => 'isdeleted', 'value' => 0);
        $this->searchParams['filter']['must_not'][] = array('field' => 'certifystatus', 'value' => 2);
        $this->searchParams['limit'] = array('from' => 0, 'size' => 1);
        $this->searchParams['query']['type'] = 'bool';
        if (isset($params['authorname']) && !empty($params['authorname'])) {
            $this->searchParams['query']['must'][] = array('field' => '_author', 'value' => $params['authorname']);
        }
        if (isset($params['pressname']) && !empty($params['pressname'])) {
            $this->searchParams['query']['must'][] = array('field' => '_pressname', 'value' => $params['pressname']);
        }
        // End format

        $result = ElasticSearchModel::findDocument($this->bookServiceESHost, $this->bookServiceESPort, $this->bookServiceESIndex, $this->bookServiceESType, 0, $this->searchParams['fields'], $this->searchParams['query'], $this->searchParams['filter'], $this->searchParams['sort'], $this->searchParams['limit'], $this->searchParams['highlight'], array(), $this->ES_timeout, $this->ES_dfs_query_then_fetch);
        return $this->formatSearchData($result);
    }
}