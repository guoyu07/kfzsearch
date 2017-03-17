<?php

/**
 * endauction sphinx搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年8月26日11:00:09
 */
class EndauctionSphinxModel extends SearchModel
{
    private $endauctionService;
    private $endauctionCache;
    private $endauctionIndex;
    private $cacheKeyFix;
    private $cacheType;
    private $sc;
    private $expire;
    private $spider_expire;
    private $ranker;
    private $field_weights;
    private $fuzzyRanker;
    private $default_ranker;
    private $cutoff;
    private $maxMatch;
    private $otherMaxMatch;
    private $pageSize;
    private $maxPageNum;
    private $otherMaxPageNum;
    private $index;
    private $bizFlag;       //业务标识
    private $isMakeOr;      //是否支持OR查询
    private $isPersistent;  //是否为长链接
    private $requestParams; //请求参数数组
    private $otherParams;   //其它扩展参数数组
    private $searchParams;  //解析请求参数数组为搜索参数数组
    private $matchType;     //搜索类型 默认为精确搜索、'fuzzy'为模糊搜索
    private $unLimit;       //不受限类型
    private $isBuildSnippets;//是否高亮显示
    private $agent;          //agent
    private $isSpiderFlag;   //判断是否为爬虫

    /**
     * endauction搜索操作模型
     */
    public function __construct()
    {
        $this->endauctionService = '';
        $this->endauctionCache   = array();
        $this->endauctionIndex   = '';
        $this->cacheKeyFix       = '';
        $this->cacheType         = '';
        $this->sc                = null;
        $this->expire            = -1;
        $this->spider_expire     = -1;
        $this->ranker            = '';
        $this->field_weights     = '';
        $this->fuzzyRanker       = '';
        $this->default_ranker    = '';
        $this->cutoff            = 0;
        $this->maxMatch          = 0;
        $this->otherMaxMatch     = 0;
        $this->pageSize          = 0;
        $this->index             = '';
        $this->bizFlag           = '';
        $this->isMakeOr          = 0;
        $this->isPersistent      = false;
        $this->requestParams     = array();
        $this->otherParams       = array();
        $this->searchParams      = array();
        $this->maxPageNum        = 0;
        $this->otherMaxPageNum   = 0;
        $this->matchType         = '';
        $this->unLimit           = false;
        $this->isBuildSnippets   = false;
        $this->agent             = '';
        $this->isSpiderFlag      = false;
    }
    
    public function init($bizFlag)
    {
        $this->bizFlag            = $bizFlag;
        $searchConfig             = Yaf\Registry::get('g_config')->search->toArray();
        $bizIndexConfig           = Conf_Sets::$bizSphinxSets[$this->bizFlag];
        $this->isSpiderFlag       = $this->isSpider($this->agent);
        if(isset($bizIndexConfig['forceSpider']) && $bizIndexConfig['forceSpider'] == 1) {
            $this->isSpiderFlag   = true;
        } elseif (isset($bizIndexConfig['forceSpider']) && $bizIndexConfig['forceSpider'] == 0) {
            $this->isSpiderFlag   = false;
        }
        $this->index              = $this->isSpiderFlag && isset($bizIndexConfig['spiderIndex']) ? $bizIndexConfig['spiderIndex'] : $bizIndexConfig['index'];
        $serviceKey               = $this->index. 'Service';
        $cacheKey                 = $this->isSpiderFlag && isset($bizIndexConfig['spiderCacheName']) ? $bizIndexConfig['spiderCacheName'] : (isset($bizIndexConfig['cacheName']) ? $bizIndexConfig['cacheName'] : $this->index. 'Cache');
        $this->productService     = $searchConfig[$serviceKey];
        $this->productCache       = $searchConfig[$cacheKey];
        $this->productIndex       = $this->index;
        $this->cacheKeyFix        = $this->isSpiderFlag && isset($bizIndexConfig['spiderCacheKeyFix']) ? $bizIndexConfig['spiderCacheKeyFix'] : $bizIndexConfig['cacheKeyFix'];
        $this->cacheType          = $this->isSpiderFlag && isset($bizIndexConfig['spiderCacheType']) ? $bizIndexConfig['spiderCacheType'] : $bizIndexConfig['cacheType'];
        $this->sc                 = new Tool_SearchClient($this->productService, $this->isPersistent, $this->productCache, $this->cacheKeyFix, true, false, $this->cacheType);
        $this->expire             = 1200;
        $this->spider_expire      = 86400;
        $this->ranker             = "expr('sum((4*lcs+2*(min_hit_pos==1)+15*exact_hit+15*(exact_order==1)-min_gaps*15+(min_best_span_pos <= 4)+(word_count-lcs))*user_weight)*10000+rank')";
        $this->fuzzyRanker        = "expr('sum((4*lcs+100*wlccs+2*(min_hit_pos==1)+(min_best_span_pos <= 4)+(word_count-lcs)+CEIL(400*sum_idf))*user_weight)*10000+rank')";
        $this->default_ranker     = "expr('1')";
        $this->cutoff             = 0;
        $this->pageSize           = isset($bizIndexConfig['pageSize']) && $bizIndexConfig['pageSize'] ? $bizIndexConfig['pageSize'] : 50;
        $this->maxPageNum         = isset($bizIndexConfig['maxPageNum']) && $bizIndexConfig['maxPageNum'] ? $bizIndexConfig['maxPageNum'] : 50;
        $this->maxMatch           = isset($bizIndexConfig['maxMatch']) && $bizIndexConfig['maxMatch'] ? $bizIndexConfig['maxMatch'] : $this->pageSize * $this->maxPageNum;
        $this->otherMaxMatch      = isset($bizIndexConfig['otherMaxMatch']) && $bizIndexConfig['otherMaxMatch'] ? $bizIndexConfig['otherMaxMatch'] : $this->pageSize * $this->maxPageNum * 2;
        $this->otherMaxPageNum    = isset($bizIndexConfig['otherMaxPageNum']) && $bizIndexConfig['otherMaxPageNum'] ? $bizIndexConfig['otherMaxPageNum'] : 100;
        $this->field_weights      = "(_itemName=300, _author=60, _press=50, x_itemName=200, x_author=50, x_press=40, isbn=30, _tag=20, itemDesc=1)";
        $this->isMakeOr           = $bizIndexConfig['isMakeOr'];
        $this->unLimit            = isset(Conf_Sets::$bizSets[$this->bizFlag]['unlimit']) && Conf_Sets::$bizSets[$this->bizFlag]['unlimit'] == true ? true : false;
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
        $bizIndexConfig           = Conf_Sets::$bizSphinxSets[$this->bizFlag];
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
            $fuzzyWordArr['fuzzy'] = $this->sc->segwords($fuzzyWordArr['key']);
        }
        return $fuzzyWordArr;
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
        $where_pre = 'isdeleted=0';
        $index = 'endauction';
        $limit = array(
            'offset' => 0,
            'maxNum' => $this->pageSize
        );
        $catVarName = isset($requestParams['catnum']['isV']) && $requestParams['catnum']['isV'] ? 'vcatid' : 'catid';
        $order = '';
        $group = $catVarName;

        $whereExt = array(); //单独项where
        $orderExt = array(); //单独项order
        $groupExt = array(//单独项group
            'catNum' => $group
        );
        $matchExt = array(); //单独项match
        //为了连接方便，为每一个筛选项建立单独变量
        $match_catNum = '';
        $match_author = '';
        $match_press = '';
        $match_nickname = '';
        $match_itemname = '';
        $match_key = '';
        $where_author = '';
        $where_press = '';
        $where_catNum = '';
        $where_years = '';
        $where_special1 = '';
        $where_special2 = '';
        $where_special3 = '';
        $where_endtime = '';
        $where_location = '';
        $where_begintime = '';
        $where_itemId = '';
        $where_status = '';
        $where_specialArea = '';

        $catTpl = 0;
        if (isset($requestParams['catnum']['value']) && $requestParams['catnum']['value'] != '') {
            if (strpos($requestParams['catnum']['value'], 'h') === false) {
                //其中$where和$group为除去分类其它项使用 $whereExt['cat']和$groupExt['cat']为分类使用
                $catId = $requestParams['catnum']['fullId'];
                $catTpl = $requestParams['catnum']['tpl'];
                switch ($requestParams['catnum']['level']) {
                    case 1:
                        $where_catNum = ' AND ' . $catVarName . '1=' . $catId;
                        $match_catNum = '@_' . $catVarName . '1 "' . $catId . '"';
                        if ($requestParams['catnum']['hasLeaf'] == 0) {
                            $group = $catVarName . '1';
                        } else {
                            $group = $catVarName . '2';
                        }
                        break;
                    case 2:
                        $where_catNum = ' AND ' . $catVarName . '2=' . $catId;
                        $match_catNum = '@_' . $catVarName . '2 "' . $catId . '"';
                        if ($requestParams['catnum']['hasLeaf'] == 0) {
                            $group = $catVarName . '2';
                        } else {
                            $group = $catVarName . '3';
                        }
                        break;
                    case 3:
                        $where_catNum = ' AND ' . $catVarName . '3=' . $catId;
                        $match_catNum = '@_' . $catVarName . '3 "' . $catId . '"';
                        if ($requestParams['catnum']['hasLeaf'] == 0) {
                            $group = $catVarName . '3';
                        } else {
                            $group = $catVarName . '4';
                        }
                        break;
                    case 4:
                        $where_catNum = ' AND ' . $catVarName . '4=' . $catId;
                        $match_catNum = '@_' . $catVarName . '4 "' . $catId . '"';
                        $group = $catVarName . '4';
                        break;
                }
            } else {
                $catidArr = explode('h', $requestParams['catnum']['value']);
                if (count($catidArr) > 0) {
                    if ($catidArr[0]) {
                        $where_catNum .= " AND catid>=" . $catidArr[0];
                    }
                    if ($catidArr[1]) {
                        $where_catNum .= " AND catid<" . $catidArr[1];
                    }
                }
            }
        } else {
            $group = $catVarName . '1';
            $groupExt['catNum'] = $group;
        }

        $authorArr = array();
        $pressArr = array();
        if (isset($requestParams['author']['value']) && $requestParams['author']['value']) {
            if (strpos($requestParams['author']['value'], 'h') !== false) {
                $authorArr = explode('h', $requestParams['author']['value']);
                if (count($authorArr) > 0 && $authorArr[0]) {
                    $where_author = ' AND iauthor=' . $authorArr[0];
                }
            }
        }
        if (isset($requestParams['press']['value']) && $requestParams['press']['value']) {
            if (strpos($requestParams['press']['value'], 'h') !== false) {
                $pressArr = explode('h', $requestParams['press']['value']);
                if (count($pressArr) > 0 && $pressArr[0]) {
                    $where_press = ' AND ipress=' . $pressArr[0];
                }
            }
        }
        if (isset($requestParams['itemid']['value']) && $requestParams['itemid']['value']) {
            $where_itemId = ' AND id='. $requestParams['itemid']['value'];
        }
        if (isset($requestParams['location']['value']) && $requestParams['location']['value']) {
            $locationArr = explode('h', $requestParams['location']['value']);
            if ($locationArr[0]) {
                $where_location = " AND area1=" . $locationArr[0];
            }
            if (isset($locationArr[1]) && $locationArr[1]) {
                $where_location = " AND area2=" . $locationArr[1];
            }
        }
        if (isset($requestParams['years']['value']) && $requestParams['years']['value']) {
            $years = $requestParams['years']['value'];
            if (strpos($years, 'h') === false) {
                $where_years = " AND years2=" . $years;
            } else {
                $where_years = '';
                $yearsArr = explode('h', $years);
                if ($catTpl == 6) {
                    if (count($yearsArr) > 0) {
                        if ($yearsArr[0]) {
                            $where_years = " AND params.postDate1>=" . intval($yearsArr[0] . '00');
                        }
                        if ($yearsArr[1]) {
                            $where_years .= " AND params.postDate2<=" . intval($yearsArr[1] . '00');
                        }
                    }
                } else {
                    if (count($yearsArr) > 0) {
                        if ($yearsArr[0]) {
                            $where_years = " AND pubdate>=" . intval($yearsArr[0] . '00');
                        }
                        if ($yearsArr[1]) {
                            $where_years .= " AND pubdate<=" . intval($yearsArr[1] . '00');
                        }
                    }
                }
            }
        }
        if ($catTpl) {
            switch ($catTpl) {
                case 1:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND paper=" . $requestParams['special1']['value'];
                    }
                    if ($requestParams['special2']['value']) {
                        $where_special2 = " AND printType=" . $requestParams['special2']['value'];
                    }
                    break;
                case 2:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND binding=" . $requestParams['special1']['value'];
                    }
                    break;
                case 3:
                    break;
                case 4:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND sort=" . $requestParams['special1']['value'];
                    }
                    if ($requestParams['special2']['value']) {
                        $where_special2 = " AND material=" . $requestParams['special2']['value'];
                    }
                    if ($requestParams['special3']['value']) {
                        $where_special3 = " AND binding=" . $requestParams['special3']['value'];
                    }
                    break;
                case 5:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND material=" . $requestParams['special1']['value'];
                    }
                    break;
                case 6:
                    break;
                case 7:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND form=" . $requestParams['special1']['value'];
                    }
                    break;
                case 8:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND sort=" . $requestParams['special1']['value'];
                    }
                    if ($requestParams['special2']['value']) {
                        $where_special2 = " AND printType=" . $requestParams['special2']['value'];
                    }
                    if ($requestParams['special3']['value']) {
                        $where_special3 = " AND material=" . $requestParams['special3']['value'];
                    }
                    break;
                case 9:
                    break;
                case 10:
                    break;
                case 11:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND sort=" . $requestParams['special1']['value'];
                    }
                    break;
                case 12:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND material=" . $requestParams['special1']['value'];
                    }
                    break;
                case 13:
                    if ($requestParams['special1']['value']) {
                        $where_special1 = " AND binding=" . $requestParams['special1']['value'];
                    }
                    break;
            }
        }
        if (count($authorArr) > 0 && !$authorArr[0] && isset($authorArr[1]) && $authorArr[1]) {
            $match_author = '@_author "' . $this->sc->segwords($this->unicode2str($authorArr[1])). '"';
        }
        if (count($pressArr) > 0 && !$pressArr[0] && isset($pressArr[1]) && $pressArr[1]) {
            $match_press = '@_press "' . $this->sc->segwords($this->unicode2str($pressArr[1])). '"';
        }
        if (isset($requestParams['nickname']['value']) && $requestParams['nickname']['value']) {
            $nickname = isset($requestParams['nickname']['nocode']) && $requestParams['nickname']['nocode'] == 1 ? $requestParams['nickname']['value'] : $this->unicode2str($requestParams['nickname']['value']);
            $match_nickname = '@_nickname  "' . $this->sc->segwords($nickname). '"';
        }
        if (isset($requestParams['itemname']['value']) && $requestParams['itemname']['value']) {
            $itemname = isset($requestParams['itemname']['nocode']) && $requestParams['itemname']['nocode'] == 1 ? $requestParams['itemname']['value'] : $this->unicode2str($requestParams['itemname']['value']);
            $match_itemname = '@_itemname  "' . $this->sc->segwords($itemname). '"';
        }
        if (isset($requestParams['endtime']['value']) && $requestParams['endtime']['value']) {
            $endtimeArr = explode('h', $requestParams['endtime']['value']);
            if (count($endtimeArr) > 0) {
                if ($endtimeArr[0] && strlen($endtimeArr[0]) == 8) {
                    $where_endtime .= " AND endtime>=" . strtotime($endtimeArr[0]);
                }
                if ($endtimeArr[1] && strlen($endtimeArr[1]) == 8) {
                    $where_endtime .= " AND endtime<=" . strtotime($endtimeArr[1]);
                }
            }
        }
        if (isset($requestParams['begintime']['value']) && $requestParams['begintime']['value']) {
            $begintimeArr = explode('h', $requestParams['begintime']['value']);
            if (count($begintimeArr) > 0) {
                //年月日
                if ($begintimeArr[0] && strlen($begintimeArr[0]) == 8) {
                    $where_begintime .= " AND begintime>=" . strtotime($begintimeArr[0]);
                }
                if ($begintimeArr[1] && strlen($begintimeArr[1]) == 8) {
                    $where_begintime .= " AND begintime<=" . strtotime($begintimeArr[1]);
                }
                //时间戳
                if($begintimeArr[0] && strlen($begintimeArr[0]) == 10) {
                    $where_begintime .= " AND begintime>=". $begintimeArr[0];
                }
                if($begintimeArr[1] && strlen($begintimeArr[1]) == 10) {
                    $where_begintime .= " AND begintime<=". $begintimeArr[1];
                }
            }
        }
        if (isset($requestParams['order']['value']) && intval($requestParams['order']['value'])) {
            switch (intval($requestParams['order']['value'])) {
                case 1:
                    $order = 'endtime desc';
                    break;
                case 2:
                    $order = 'maxprice asc';
                    break;
                case 3:
                    $order = 'maxprice desc';
                    break;
                case 4:
                    $order = 'bidnum desc';
                    break;
                case 5:
                    $order = 'bidnum asc';
                    break;
                case 6:
                    $order = 'viewednum desc';
                    break;
                case 7:
                    $order = 'viewednum asc';
                    break;
                case 8:
                    $order = 'beginprice desc';
                    break;
                case 9:
                    $order = 'beginprice asc';
                    break;
                case 10:
                    $order = 'endtime asc';
                    break;
                default:
                    $order = 'endtime desc';
                    break;
            }
        } else {
            $order = 'endtime desc';
        }

        if (isset($requestParams['pagenum']['value']) && intval($requestParams['pagenum']['value'])) {
            $pageNum = intval($requestParams['pagenum']['value']) <= 1 ? 1 : intval($requestParams['pagenum']['value']);
            if ($pageNum > $this->maxPageNum && (!isset($requestParams['getmore']) || !$requestParams['getmore']['value'])) {
                $pageNum = 1;
            } elseif ($pageNum > $this->otherMaxPageNum && isset($requestParams['getmore']) && $requestParams['getmore']['value']) {
                $pageNum = 1;
            }
            $pageSize = $this->pageSize;
            $limit['offset'] = ($pageNum - 1) * $pageSize;
            $limit['maxNum'] = $pageSize;
        }
        
        if (isset($requestParams['status']['value']) && $requestParams['status']['value']) {
            if($requestParams['status']['value'] != '11') {
                $statusArr = explode('h', $requestParams['status']['value']);
                $statusStr = implode(',', $statusArr);
                $where_status = " AND itemstatus IN ({$statusStr})";
            }
        } else {
            $where_status = " AND itemstatus=0";
        }
        if(isset($requestParams['specialarea']['value']) && $requestParams['specialarea']['value']) {
            $where_specialArea = " AND specialarea=". $requestParams['specialarea']['value'];
        }
        
        $exKey = 0;
        $key = 0;
        if (isset($requestParams['exkey']['value']) && $requestParams['exkey']['value']) {
            $exKey = isset($requestParams['exkey']['nocode']) && $requestParams['exkey']['nocode'] == 1 ? $requestParams['exkey']['value'] : $this->unicode2str($requestParams['exkey']['value']);
        }
        if (isset($requestParams['key']['value']) && $requestParams['key']['value']) {
            $key = isset($requestParams['key']['nocode']) && $requestParams['key']['nocode'] == 1 ? $requestParams['key']['value'] : $this->unicode2str($requestParams['key']['value']);
        }
        if((isset($requestParams['isfuzzy']['value']) && $requestParams['isfuzzy']['value']) || $this->matchType == 'fuzzy') {
            $matchType = 'fuzzy';
        }
        if ($key !== 0 && $exKey !== 0) {
            $match_key = '@(_itemname,_author,_nickname) "' . $this->sc->segwords($key) . '" !(' . $this->sc->segwords($exKey) . ')';
        } elseif ($key !== 0 && $exKey === 0) {
            if ($matchType == 'fuzzy') { //模糊搜索
                $match_key = '@@relaxed @(_itemname,_author,_nickname) "' . $this->sc->segwords($key) . '"/0.5';
            } else {
                $match_key = '@(_itemname,_author,_nickname) "' . $this->sc->segwords($key). '"';
            }
        } elseif ($key === 0 && $exKey !== 0) {
            $match_key = '@(_itemname,_author,_nickname) !(' . $this->sc->segwords($exKey) . ')';
        }

        //连接match
        $match = trim($match_author . ' ' . $match_press . ' ' . $match_nickname . ' ' . $match_itemname . ' ' . $match_key);

        if ($match) { //有match时 分类走过滤
            if (!$match_author && !$match_press && !$match_nickname && !$match_itemname && $key === 0 && $exKey !== 0) { //当仅有一个排除关键字时，情况特殊
                $match = trim($match_catNum . ' ' . $match);
                if ($match_catNum && $where_catNum) {
                    $where_catNum = '';
                }
                $isMatch = 0;
                $order = $order ? $order : 'endtime DESC';
            } else {
                $match_catNum = '';
                $isMatch = 1;
                $order = $order ? $order : '';
            }
        } else {
            $match = trim($match_catNum . ' ' . $match);
            if ($match_catNum && $where_catNum) {
                $where_catNum = '';
            }
            $isMatch = 0;
            $order = $order ? $order : 'endtime DESC';
        }

        //连接where
        $where = $where_pre . $where_status. $where_itemId. $where_catNum . $where_author . $where_press . $where_years . $where_special1 . $where_special2 . $where_special3 . $where_endtime . $where_location . $where_begintime. $where_specialArea;

        //特殊项 where
//        $whereExt['catNum'] = $where;
        $groupExt['catNum'] = $group;
        //当前筛选项为最后一项时与其它的筛选不一样
        if(!isset($requestParams['nolast']['value']) || !$requestParams['nolast']['value']) {
            $lastFields = array('catnum', 'author', 'press', 'years', 'special1', 'special2', 'special3');
            foreach($lastFields as $field) {
                if (isset($requestParams[$field]['isLast']) && $requestParams[$field]['isLast'] == 1) {
                    $var = 'where_'. $field;
                    $whereExt[$field] = str_replace($$var, '', $where);
                } else {
                    $whereExt[$field] = $where;
                }
            }
        }
        
        //特殊项 match
        if (count($authorArr) > 0 && !$authorArr[0] && $authorArr[1]) {
            $matchExt['author'] = $match;
        } else {
            $matchExt['author'] = trim($match_catNum . ' ' . $match_press . ' ' . $match_nickname . ' ' . $match_itemname . ' ' . $match_key);
        }
        if (count($pressArr) > 0 && !$pressArr[0] && $pressArr[1]) {
            $matchExt['press'] = $match;
        } else {
            $matchExt['press'] = trim($match_catNum . ' ' . $match_author . ' ' . $match_nickname . ' ' . $match_itemname . ' ' . $match_key);
        }

        $searchParams = array(
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
     * 键值为0的存放的是分类聚类数据
     * 键值为1的存放的是作者聚类数据
     * 键值为2的存放的是出版社聚类数据
     * 键值为3的存放的是年代聚类数据
     * 键值为4的存放的是著录项1聚类数据
     * 键值为5的存放的是著录项2聚类数据
     * 键值为6的存放的是著录项3聚类数据
     * 键值为7的存放的是商品具体数据
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
        if(isset($result['7'])) {
            $returnList['itemList'] = $this->isBuildSnippets ? $this->buildSnippets($result['7']) : $result['7'];
        }
        if(isset($result['0'])) {
            $returnList['catList'] = $result['0'];
        }
        if(isset($result['1'])) {
            $returnList['authorList'] = $result['1'];
        }
        if(isset($result['2'])) {
            $returnList['pressList'] = $result['2'];
        }
        if(isset($result['3'])) {
            $returnList['yearsList'] = $result['3'];
        }
        if(isset($result['4'])) {
            $returnList['special1List'] = $result['4'];
        }
        if(isset($result['5'])) {
            $returnList['special2List'] = $result['5'];
        }
        if(isset($result['6'])) {
            $returnList['special3List'] = $result['6'];
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
        $queryArr = array();
        $query2Arr = array();

        //获取分类聚类
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $this->sc->setStmtColumnList('groupby() as cid,count(*) as num', 0);
            $this->sc->setStmtGroupBy($this->searchParams['groupExt']['catNum'], '', 0);
            $this->sc->setStmtOrderBy('num desc', 0);
            $this->sc->setStmtLimit(0, 999, 0);
            if ($this->searchParams['whereExt']['catNum']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['catNum'], 0);
            }
            $this->sc->setStmtQuery($this->searchParams['match'], 0);
            $queryArr[] = 0;
        }

        //获取作者聚类
        if ($this->requestParams['catnum']['value']) {
            $this->sc->setStmtColumnList('groupby() as authorid,author2,count(*) as num', 1);
            $this->sc->setStmtGroupBy('iauthor', '', 1);
            $this->sc->setStmtOrderBy('num desc', 1);
            $this->sc->setStmtLimit(0, 9, 1);
            if ($this->searchParams['where']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['author'], 1);
            }
            $this->sc->setStmtQuery($this->searchParams['matchExt']['author'], 1);
            if (isset($this->requestParams['author']['isLast']) && $this->requestParams['author']['isLast'] == 1) {
                $query2Arr[] = 1;
            } else {
                $queryArr[] = 1;
            }
        }

        //获取出版社聚类
        if ($this->requestParams['catnum']['value']) {
            $this->sc->setStmtColumnList('groupby() as pressid,press2,count(*) as num', 2);
            $this->sc->setStmtGroupBy('ipress', '', 2);
            $this->sc->setStmtOrderBy('num desc', 2);
            $this->sc->setStmtLimit(0, 9, 2);
            if ($this->searchParams['where']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['press'], 2);
            }
            $this->sc->setStmtQuery($this->searchParams['matchExt']['press'], 2);
            if (isset($this->requestParams['press']['isLast']) && $this->requestParams['press']['isLast'] == 1) {
                $query2Arr[] = 2;
            } else {
                $queryArr[] = 2;
            }
        }

        //获取年代聚类
        if ($this->requestParams['catnum']['value']) {
            $this->sc->setStmtColumnList('years2,count(*) as num', 3);
            $this->sc->setStmtGroupBy('years2', '', 3);
            $this->sc->setStmtOrderBy('num desc', 3);
            $this->sc->setStmtLimit(0, 20, 3);
            if ($this->searchParams['where']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['years'], 3);
            }
            $this->sc->setStmtQuery($this->searchParams['match'], 3);
            if (isset($this->requestParams['years']['isLast']) && $this->requestParams['years']['isLast'] == 1) {
                $query2Arr[] = 3;
            } else {
                $queryArr[] = 3;
            }
        }

        //获取特殊项聚类
        $catTpl = 0;
        if ($this->requestParams['catnum']['value']) {
            $catTpl = $this->requestParams['catnum']['tpl'];
            switch ($catTpl) {
                case 1:
                    $this->sc->setStmtColumnList('groupby() as paper,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('paper', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }

                    $this->sc->setStmtColumnList('groupby() as printtype,count(*) as num', 5);
                    $this->sc->setStmtGroupBy('printType', '', 5);
                    $this->sc->setStmtOrderBy('num desc', 5);
                    if ($this->searchParams['whereExt']['special2']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special2'], 5);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 5);
                    if (isset($this->requestParams['special2']['isLast']) && $this->requestParams['special2']['isLast'] == 1) {
                        $query2Arr[] = 5;
                    } else {
                        $queryArr[] = 5;
                    }
                    break;
                case 2:
                    $this->sc->setStmtColumnList('groupby() as binding,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('binding', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }
                    break;
                case 3:
                    break;
                case 4:
                    $this->sc->setStmtColumnList('groupby() as sort,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('sort', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }

                    $this->sc->setStmtColumnList('groupby() as material,count(*) as num', 5);
                    $this->sc->setStmtGroupBy('material', '', 5);
                    $this->sc->setStmtOrderBy('num desc', 5);
                    if ($this->searchParams['whereExt']['special2']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special2'], 5);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 5);
                    if (isset($this->requestParams['special2']['isLast']) && $this->requestParams['special2']['isLast'] == 1) {
                        $query2Arr[] = 5;
                    } else {
                        $queryArr[] = 5;
                    }

                    $this->sc->setStmtColumnList('groupby() as binding,count(*) as num', 6);
                    $this->sc->setStmtGroupBy('binding', '', 6);
                    $this->sc->setStmtOrderBy('num desc', 6);
                    if ($this->searchParams['whereExt']['special3']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special3'], 6);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 6);
                    if (isset($this->requestParams['special3']['isLast']) && $this->requestParams['special3']['isLast'] == 1) {
                        $query2Arr[] = 6;
                    } else {
                        $queryArr[] = 6;
                    }
                    break;
                case 5:
                    $this->sc->setStmtColumnList('groupby() as material,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('material', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }
                    break;
                case 6:
                    break;
                case 7:
                    $this->sc->setStmtColumnList('groupby() as form,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('form', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }
                    break;
                case 8:
                    $this->sc->setStmtColumnList('groupby() as sort,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('sort', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }

                    $this->sc->setStmtColumnList('groupby() as printtype,count(*) as num', 5);
                    $this->sc->setStmtGroupBy('printType', '', 5);
                    $this->sc->setStmtOrderBy('num desc', 5);
                    if ($this->searchParams['whereExt']['special2']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special2'], 5);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 5);
                    if (isset($this->requestParams['special2']['isLast']) && $this->requestParams['special2']['isLast'] == 1) {
                        $query2Arr[] = 5;
                    } else {
                        $queryArr[] = 5;
                    }

                    $this->sc->setStmtColumnList('groupby() as material,count(*) as num', 6);
                    $this->sc->setStmtGroupBy('material', '', 6);
                    $this->sc->setStmtOrderBy('num desc', 6);
                    if ($this->searchParams['whereExt']['special3']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special3'], 6);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 6);
                    if (isset($this->requestParams['special3']['isLast']) && $this->requestParams['special3']['isLast'] == 1) {
                        $query2Arr[] = 6;
                    } else {
                        $queryArr[] = 6;
                    }
                    break;
                case 9:
                    break;
                case 10:
                    break;
                case 11:
                    $this->sc->setStmtColumnList('groupby() as sort,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('sort', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }
                    break;
                case 12:
//                    $this->sc->setStmtColumnList('groupby as material,count(*) as num', 4);
//                    $this->sc->setStmtGroupBy('material', '', 4);
//                    $this->sc->setStmtOrderBy('num desc', 4);
//                    if($this->searchParams['whereExt']['special1']) {
//                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
//                    }
//                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
//                    $queryArr[] = 4;
                    break;
                case 13:
                    $this->sc->setStmtColumnList('groupby() as binding,count(*) as num', 4);
                    $this->sc->setStmtGroupBy('binding', '', 4);
                    $this->sc->setStmtOrderBy('num desc', 4);
                    if ($this->searchParams['whereExt']['special1']) {
                        $this->sc->setStmtFilter($this->searchParams['whereExt']['special1'], 4);
                    }
                    $this->sc->setStmtQuery($this->searchParams['match'], 4);
                    if (isset($this->requestParams['special1']['isLast']) && $this->requestParams['special1']['isLast'] == 1) {
                        $query2Arr[] = 4;
                    } else {
                        $queryArr[] = 4;
                    }
                    break;
            }
        }

        $max_matches = isset($this->requestParams['pagenum']['value']) && $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), $queryArr);

        //获取相应商品
        $this->sc->setStmtColumnList('*', 7);
        $this->sc->setStmtQuery($this->searchParams['match'], 7);
        if ($this->searchParams['isMatch']) {
            if ($this->searchParams['order']) {
                $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
            }
            if ($this->matchType == 'fuzzy') { //模糊搜索
                $this->sc->setStmtOption(array('ranker' => $this->fuzzyRanker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
            } else {
                $this->sc->setStmtOption(array('ranker' => $this->ranker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
            }
        } else {
            $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
            $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
        }
        if ($this->searchParams['where']) {
            $this->sc->setStmtFilter($this->searchParams['where'], 7);
        }
        $this->sc->setStmtLimit($this->searchParams['limit']['offset'], $this->searchParams['limit']['maxNum'], 7);
        array_unshift($queryArr, 7);

        //搜索全部
        $this->sc->setStmtQueryIndex($this->searchParams['index'], $queryArr);
        $result = $this->sc->query($queryArr, $this->getExpire());
        $result2 = array();
        if ($query2Arr) {
            $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), $query2Arr);
            $this->sc->setStmtQueryIndex($this->searchParams['index'], $query2Arr);
            $key = $query2Arr[0];
            $result2 = $this->sc->query($query2Arr, $this->getExpire());
            $result[$key] = $result2[$key];
        }
//        file_put_contents('/tmp/kfzsearch.log', var_export($this->formatSearchData($result), true). "\n", FILE_APPEND);
//        echo '<pre>';
//        print_r($result);exit;
        return $this->formatSearchData($result);
    }
    
    /**
     * 用户首次访问获得基础filterList和productList（无筛选）
     */
    public function getFPWithOutFilter()
    {
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => 2000, 'cutoff' => $this->cutoff), array(0, 7));
        $this->sc->setStmtQueryIndex($this->endauctionIndex, array(0, 7));
        //获取分类聚类
        $this->sc->setStmtColumnList('groupby() as catid,count(*) as num', 0);
        $this->sc->setStmtGroupBy('catid1', '', 0);
        $this->sc->setStmtOrderBy('num desc', 0);
        $this->sc->setStmtLimit(0, 999, 0);
        //获取相应商品
        $this->sc->setStmtColumnList('*', 7);
//        $this->sc->setStmtOrderBy('addTime DESC', 1);

        $this->sc->setStmtFilter('isdeleted=0', array(0, 7));
        $this->sc->setStmtLimit(0, $this->pageSize, 7);
        //搜索全部
        $result = $this->sc->query(array(7, 0), $this->getExpire());
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
        $max_matches = $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
        if ($this->searchParams['isMatch']) {
            if ($this->searchParams['order']) {
                $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
            }
            if ($this->matchType == 'fuzzy') { //模糊搜索
                $this->sc->setStmtOption(array('ranker' => $this->fuzzyRanker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
            } else {
                $this->sc->setStmtOption(array('ranker' => $this->ranker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
            }
        } else {
            $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
            $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
        }
        $this->sc->setStmtColumnList('*', 7);
        $this->sc->setStmtQueryIndex($this->searchParams['index'], 7);
        //获取相应商品
        if ($this->searchParams['where']) {
            $this->sc->setStmtFilter($this->searchParams['where'], 7);
        }
        if ($this->searchParams['limit']) {
            $this->sc->setStmtLimit($this->searchParams['limit']['offset'], $this->searchParams['limit']['maxNum'], 7);
        }
        if ($this->searchParams['match']) {
            $this->sc->setStmtQuery($this->searchParams['match'], 7);
        }
        //搜索全部
        $result = $this->sc->query('*', $this->getExpire());

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
    	$this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $this->maxMatch, 'cutoff' => $this->cutoff), 0);
    	$this->sc->setStmtQueryIndex($this->searchParams['index'], 0);
    	
    	//获取分类聚类
    	$this->sc->setStmtColumnList('groupby() as catid,count(*) as num', 0);
    	$this->sc->setStmtGroupBy('catid1', '', 0);
    	$this->sc->setStmtOrderBy('num desc', 0);
    	$this->sc->setStmtLimit(0, 999, 0);
    	$this->sc->setStmtFilter($this->searchParams['where'], 0);
        $this->sc->setStmtQuery($this->searchParams['match'], 0);
        
    	$result = $this->sc->query(array(0), $this->getExpire());
    
    	return $this->formatSearchData($result);
    }
    
    /**
     * 跟据用户条件获得catsList和productList
     */
    public function getFPWithFilterForFinishedList()
    {
        $max_matches = $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
        $queryArr = array();
        $query2Arr = array();

        //获取分类聚类
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $this->sc->setStmtColumnList('groupby() as cid,count(*) as num', 0);
            $this->sc->setStmtGroupBy($this->searchParams['groupExt']['catNum'], '', 0);
            $this->sc->setStmtOrderBy('num desc', 0);
            $this->sc->setStmtLimit(0, 999, 0);
            if ($this->searchParams['whereExt']['catNum']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['catNum'], 0);
            }
            $this->sc->setStmtQuery($this->searchParams['match'], 0);
            $queryArr[] = 0;
        }

        //获取相应商品
        $this->sc->setStmtColumnList('*', 7);
        $this->sc->setStmtQuery($this->searchParams['match'], 7);
        if ($this->searchParams['isMatch']) {
            if ($this->searchParams['order']) {
                $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
            }
            if ($this->matchType == 'fuzzy') { //模糊搜索
                $this->sc->setStmtOption(array('ranker' => $this->fuzzyRanker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
            } else {
                $this->sc->setStmtOption(array('ranker' => $this->ranker, 'field_weights' => $this->field_weights, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
            }
        } else {
            $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
            $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
        }
        if ($this->searchParams['where']) {
            $this->sc->setStmtFilter($this->searchParams['where'], 7);
        }
        $this->sc->setStmtLimit($this->searchParams['limit']['offset'], $this->searchParams['limit']['maxNum'], 7);
        array_unshift($queryArr, 7);

        //搜索全部
        $this->sc->setStmtQueryIndex($this->searchParams['index'], $queryArr);
        $result = $this->sc->query($queryArr, $this->getExpire());
        $result2 = array();
        if ($query2Arr) {
            $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), $query2Arr);
            $this->sc->setStmtQueryIndex($this->searchParams['index'], $query2Arr);
            $key = $query2Arr[0];
            $result2 = $this->sc->query($query2Arr, $this->getExpire());
            $result[$key] = $result2[$key];
        }
        
        return $this->formatSearchData($result);
    }
    
    /**
     * 对商品信息进行高亮显示
     * 
     * @param array  $productList
     * @return array
     */
    private function buildSnippets($productList)
    {
        if (!is_array($productList) || count($productList) <= 3) {
            return $productList;
        }
        $keyWord = isset($this->requestParams['key']['nocode']) && $this->requestParams['key']['nocode'] == 1 ? $this->sc->segwords($this->requestParams['key']['value']) : $this->sc->segwords($this->unicode2str($this->requestParams['key']['value']));
        $oriWordsArr = array();
        $sniWordsArr = array();
        $itemNameKeyStartNum = 0;
        $authorKeyStartNum = $this->pageSize;
        $pressKeyStartNum = $this->pageSize * 2;
        $autoIncrementNum = 0;
        foreach ($productList as $item) { //提取商品信息
            if (!is_array($item)) {
                for (; $autoIncrementNum < $this->pageSize; $autoIncrementNum++) {
                    $oriWordsArr[$itemNameKeyStartNum + $autoIncrementNum] = '';
                    $oriWordsArr[$authorKeyStartNum + $autoIncrementNum] = '';
                    $oriWordsArr[$pressKeyStartNum + $autoIncrementNum] = '';
                }
                break;
            }
            $oriWordsArr[$itemNameKeyStartNum + $autoIncrementNum] = htmlspecialchars($item['itemname']);
            $oriWordsArr[$authorKeyStartNum + $autoIncrementNum] = $item['author'] ? htmlspecialchars($item['author']) : '';
            $oriWordsArr[$pressKeyStartNum + $autoIncrementNum] = $item['press'] ? htmlspecialchars($item['press']) : '';
            ++$autoIncrementNum;
        }
        ksort($oriWordsArr);
//        echo '<pre>';
//        print_r($oriWordsArr);exit;

        $sniWordsArr = $this->sc->buildSnippets($oriWordsArr, $keyWord); //高亮
        if(!$sniWordsArr) {
            return $productList;
        }

        $autoIncrementNum = 0;
        foreach ($productList as &$value) { //分配高亮词
            if (!is_array($value)) {
                break;
            }
            $value['itemname_snippet'] = $sniWordsArr[$itemNameKeyStartNum + $autoIncrementNum];
            if ($value['author']) {
                $value['author_snippet'] = $sniWordsArr[$authorKeyStartNum + $autoIncrementNum];
            }
            if ($value['press']) {
                $value['press_snippet'] = $sniWordsArr[$pressKeyStartNum + $autoIncrementNum];
            }
            ++$autoIncrementNum;
        }

        return $productList;
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
                if (!is_array($cat) || !$cat['cid']) {
                    continue;
                }
                $cid = $cat['cid'];
                $catInfo = $type::getItemInfo($cid);
                $cat['id'] = CategoryModel::getShortCatId($cid);
                $cat['name'] = $catInfo['name'];
//                unset($cat['cid']);
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