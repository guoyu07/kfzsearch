<?php

/**
 * 建议词搜索模型
 * 
 * @author      liguizhi <liguizhi_001@163.com>
 * @date        2015年5月26日17:39:59
 */
class SuggestSphinxModel extends SearchModel
{
    private $searchObj;     //搜索实例
    private $agent;         //agent
    private $realIP;        //用户IP
    private $bizFlag;       //业务标识
    private $isMakeOr;      //是否支持OR查询
    private $isPersistent;  //是否为长链接
    private $requestParams; //请求参数数组

    /**
     * product搜索操作模型
     */
    public function __construct($bizFlag)
    {
        $this->isMakeOr          = 0;
        $this->isPersistent      = false;
        $this->requestParams     = array();
        $this->agent             = '';
        $this->realIP            = '';
        $this->bizFlag            = $bizFlag;

        $bizIndexConfig           = Conf_Sets::$bizSphinxSets[$this->bizFlag];
        $this->maxPageNum         = isset($bizIndexConfig['maxPageNum']) && $bizIndexConfig['maxPageNum'] ? $bizIndexConfig['maxPageNum'] : 50;
        $this->maxMatch           = isset($bizIndexConfig['maxMatch']) && $bizIndexConfig['maxMatch'] ? $bizIndexConfig['maxMatch'] : $this->pageSize * $this->maxPageNum;
        $this->otherMaxMatch      = isset($bizIndexConfig['otherMaxMatch']) && $bizIndexConfig['otherMaxMatch'] ? $bizIndexConfig['otherMaxMatch'] : $this->pageSize * $this->maxPageNum * 2;
        $this->otherMaxPageNum    = isset($bizIndexConfig['otherMaxPageNum']) && $bizIndexConfig['otherMaxPageNum'] ? $bizIndexConfig['otherMaxPageNum'] : 100;
        
        $searchConfig             = Yaf\Registry::get('g_config')->search->toArray();
        $this->searchService = $searchConfig['suggestService'];
        $this->searchCache   = $searchConfig['suggestCache'];
        $this->searchIndex   = "suggest";
        $this->cacheKeyFix       = 'suggest_';
        $this->searchObj         = new Tool_SearchClient($this->searchService, false, $this->searchCache, $this->cacheKeyFix,true,true,'redis');
        $this->expire            = 600;
        $this->ranker            = "expr('sum((4*lcs+2*(min_hit_pos==1)+15*exact_hit+15*(exact_order==1)-min_gaps*15+(min_best_span_pos <= 4)+(word_count-lcs))*user_weight)*10000')";
        $this->fuzzyRanker       = "expr('sum((4*lcs+100*wlccs+2*(min_hit_pos==1)+(min_best_span_pos <= 4)+(word_count-lcs)+CEIL(400*sum_idf))*user_weight)*10000')";
        $this->default_ranker    = "expr('1')";
        $this->cutoff            = 0;
        $this->pageSize          = 10;
        $this->field_weights     = "(word=100,pinyin=10) ";
        // $this->field_weights     = "(word=100)";
        $this->site              = Yaf\Application::app()->getConfig()->site->toArray();
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
     * 设置agent
     */
    public function setExpire($expire)
    {
        $this->expire = $expire;
    }
    
    /**
     * 设置用户IP
     */
    public function setIP($realIP)
    {
        $this->realIP = $realIP;
        //防恶意访问
//        if($realIP && !$this->isSpider($this->agent)) { //非爬虫
        // if($realIP) { //非爬虫
        //     if(substr($realIP, 0, 7) == '192.168' || 
        //        substr($realIP, 0, 10) == '117.121.31' || 
        //        substr($realIP, 0, 11) == '116.213.206') { //内网IP不做限制和公司外网IP不做限制
        //         return true;
        //     }
        //     if($this->preventMaliciousAccess($realIP, $this->bizFlag, $this->agent) == true) {
        //         $this->agent = 'AbnormalAccess';
        //         return false;
        //     }
        // }
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
        $bizIndexConfig           = Conf_Sets::$bizSphinxSets[$this->bizFlag];
        $this->pageSize           = $pageSize;
        $this->maxMatch           = $this->pageSize * $bizIndexConfig['maxPageNum'] > $bizIndexConfig['maxMatch'] ? $bizIndexConfig['maxMatch'] : $this->pageSize * $bizIndexConfig['maxPageNum'];
        $this->otherMaxMatch      = $this->pageSize * $bizIndexConfig['maxPageNum'] * 2 > $bizIndexConfig['otherMaxMatch'] ? $bizIndexConfig['otherMaxMatch'] : $this->pageSize * $bizIndexConfig['maxPageNum'] * 2;
        return true;
    }
    
    
    /**
     * 获取分词
     */
    public function getFuzzyWord()
    {
        $fuzzyWordArr = array();
        if(isset($this->requestParams['key']) && isset($this->requestParams['key']['value']) && $this->requestParams['key']['value']) {
            $fuzzyWordArr['key'] = isset($this->requestParams['key']['nocode']) && $this->requestParams['key']['nocode'] == 1 ? htmlspecialchars($this->requestParams['key']['value']) : htmlspecialchars($this->requestParams['key']['value']);
            $fuzzyWordArr['fuzzy'] = $this->searchObj->segwords($fuzzyWordArr['key']);
        }
        return $fuzzyWordArr;
    }
    
    /**
     * 格式化查询数组
     */
    public function formatRequestParams($requestParams)
    {
        $returnRequestParams = array();
        if(!empty($requestParams)) {
            foreach($requestParams as $k => $v) {
                $strLower = strtolower($v['key']);
                $returnRequestParams[$strLower]['value'] = $v['value'];
            }
            unset($requestParams);
        }
        
        // $paramsArr = $this->getSpecifyParamsMapping();
        // foreach ($paramsArr as $k => &$v) {
        //     if(!isset($returnRequestParams[$k])) {
        //         $returnRequestParams[$k] = array('key' => '', 'value' => '');
        //     }
        // }
        return $returnRequestParams;
    }
    
    /**
     * 解析请求参数为搜索条件
     * 
     * @param array $requestParams
     * @param int   $isOnline
     * @return array
     */
    public function translateParams($requestParams)
    {
        // var_dump($requestParams);
        $this->requestParams = $requestParams;
        $where       = ' isdeleted=0';

        $limit = array(
            'offset' => 0,
            'maxNum' => $this->pageSize
        );
        // $catVarName          = isset($requestParams['catnum']['isV']) && $requestParams['catnum']['isV'] ? 'vcatid' : 'catid';
        $order               = ' querynum DESC';
        // $group               = $catVarName;
        
        $whereExt = array(); //单独项where
        $orderExt = array(); //单独项order
        $groupExt = array(//单独项group
            // 'catNum' => $group
        );
        $matchExt = array(); //单独项match

        //为了连接方便，为每一个筛选项建立单独变量
        $matchType           = '';
        if (isset($requestParams['wordname']) && $requestParams['wordname']) {
            $wordname = $requestParams['wordname'];
            if(preg_match('/^[a-zA-Z]/', $wordname)){
                $match_itemname = "@(word,pinyin) !(^$wordname$) ^" . ($wordname) ;//前匹配
            } else {
                $match_itemname = "@(word) !(^$wordname$) ^" . ($wordname) ;//前匹配
            }
        }
        
        $limit['offset'] = 0;
        $limit['maxNum'] = $this->pageSize;
        $index = $this->searchIndex;
        //连接match
        $match = trim($match_itemname);

        if ($match) { //有match时 分类走过滤
            $isMatch = 1;
            $order = $order ? $order : '';
        } else {
            $isMatch = 0;
            $order = $order ? $order : 'id DESC';
        }

        $searchParams =  array(
            'index' => $index,
            'where' => $where,
            'group' => $group,
            'order' => $order,
            'limit' => $limit,
            'match' => $match,
            'whereExt' => $whereExt,
            'groupExt' => $groupExt,
            'orderExt' => $orderExt,
            'matchExt' => $matchExt,
            'isMatch' => $isMatch,
            'matchType' => $matchType
        );
        $this->searchParams = $searchParams;
        // var_dump($this->searchParams);exit;
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
        $max_matches = $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
        if ($this->searchParams['isMatch']) {
            if ($this->searchParams['order']) {
                $this->searchObj->setStmtOrderBy($this->searchParams['order'], 0);
            }
            if ($this->matchType == 'fuzzy') { //模糊搜索
                $this->searchObj->setStmtOption(array('ranker' => $this->fuzzyRanker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 0);
            } else {
                $this->searchObj->setStmtOption(array('ranker' => $this->ranker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 0);
            }
        } else {
            $this->searchObj->setStmtOrderBy($this->searchParams['order'], 0);
            $this->searchObj->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 0);
        }
        $this->searchObj->setStmtColumnList('word', 0);
        $this->searchObj->setStmtQueryIndex($this->searchParams['index'], 0);
        //获取相应商品
        if ($this->searchParams['where']) {
            $this->searchObj->setStmtFilter($this->searchParams['where'], 0);
        }
        if ($this->searchParams['limit']) {
            $this->searchObj->setStmtLimit($this->searchParams['limit']['offset'], $this->searchParams['limit']['maxNum'], 0);
        }
        if ($this->searchParams['match']) {
            $this->searchObj->setStmtQuery($this->searchParams['match'], 0);
        }
        //搜索全部
        $result = $this->searchObj->query(0, $this->expire);
// var_dump($result);exit;
        // return ($result);
        return $this->formatSearchData($result);
    }
    
    public function formatSearchData($auctionlist){
        $newList = array();
        foreach ($auctionlist as $key => $value) {
            if(is_array($value)) {
                $newList[] = $this->transItem($value);
            } 
        }
        return $newList;
    }

    private function transItem($item) {
        return isset($item['word']) ? trim($item['word']) : '';
    }
}
?>