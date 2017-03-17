<?php

/**
 * item sphinx搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年1月28日14:45:52
 */
class ItemSphinxModel extends SearchModel
{
    private $productService;
    private $productCache;
    private $productIndex;
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
     * product搜索操作模型
     */
    public function __construct()
    {
        $this->productService    = '';
        $this->productCache      = array();
        $this->productIndex      = '';
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
        if($this->index == 'product') {
            $where_pre       = 'isdeleted=0 AND shopstatus=1';
        } elseif ($this->index == 'seoproduct') {
            $where_pre       = 'isdeleted=0 AND shopstatus=1';
        } elseif ($this->index == 'unproduct') {
            $where_pre       = 'isdeleted=0';
        }

        $limit = array(
            'offset' => 0,
            'maxNum' => $this->pageSize
        );
        $catVarName          = isset($requestParams['catnum']['isV']) && $requestParams['catnum']['isV'] ? 'vcatid' : 'catid';
        $order               = '';
        $group               = $catVarName;
        
        $whereExt = array(); //单独项where
        $orderExt = array(); //单独项order
        $groupExt = array(//单独项group
            'catNum' => $group
        );
        $matchExt = array(); //单独项match

        //为了连接方便，为每一个筛选项建立单独变量
        $match_catNum        = '';
        $match_author        = '';
        $match_press         = '';
        $match_shopname      = '';
        $match_itemname      = '';
        $match_key           = '';
        $match_itemdesc      = '';
        
        $where_catNum        = '';
        $where_discount      = '';
        $where_addtime       = '';
        $where_pubdate       = '';
        $where_biztype       = '';
        $where_shopid        = '';
        $where_userid        = '';
        $where_hasimg        = '';
        $where_quality       = '';
        $where_updatetime    = '';
        $where_certifyStatus = '';
        $where_salestatus    = '';
        $where_recertifyStatus = '';
        $where_years         = '';
        $where_author        = '';
        $where_press         = '';
        $where_special1      = '';
        $where_special2      = '';
        $where_special3      = '';
        $where_price         = '';
        $where_location      = '';
        $where_approach      = '';
        $where_filteritemid  = '';
        $where_filtercatid   = '';
        $where_number        = '';
        $where_class         = '';
        $matchType           = '';

        $catTpl = 0;
        if (isset($requestParams['catnum']['value']) && $requestParams['catnum']['value'] != '') {
            //其中$where和$group为除去分类其它项使用 $whereExt['cat']和$groupExt['cat']为分类使用
            if (strpos($requestParams['catnum']['value'], 'h') === false) {
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
                if (strpos($requestParams['catnum']['value'], 'hh') === false) {
                    $catidArr = explode('h', $requestParams['catnum']['value']);
                    foreach ($catidArr as &$value) {
                        $value = CategoryModel::getFullCatId($value);
                    }
                    unset($value);
                    $catidStr = implode(',', $catidArr);
                    $where_catNum .= " AND catid1 in ($catidStr)";
                } else {
                    $catidArr = explode('hh', $requestParams['catnum']['value']);
                    if (count($catidArr) > 0) {
                        if ($catidArr[0]) {
                            $where_catNum .= " AND catid>=" . $catidArr[0];
                        }
                        if (isset($catidArr[1]) && $catidArr[1]) {
                            $where_catNum .= " AND catid<" . $catidArr[1];
                        }
                    }
                }
            }
        } else {
            $group = $catVarName . '1';
            $groupExt['catNum'] = $group;
        }
        
        if ($catTpl) {
            switch ($catTpl) {
                case 1:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND paper=" . $requestParams['special1']['value'];
                    }
                    if (isset($requestParams['special2']['value']) && $requestParams['special2']['value']) {
                        $where_special2 = " AND printType=" . $requestParams['special2']['value'];
                    }
                    break;
                case 2:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND binding=" . $requestParams['special1']['value'];
                    }
                    break;
                case 3:
                    break;
                case 4:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND sort=" . $requestParams['special1']['value'];
                    }
                    if (isset($requestParams['special2']['value']) && $requestParams['special2']['value']) {
                        $where_special2 = " AND material=" . $requestParams['special2']['value'];
                    }
                    if (isset($requestParams['special3']['value']) && $requestParams['special3']['value']) {
                        $where_special3 = " AND binding=" . $requestParams['special3']['value'];
                    }
                    break;
                case 5:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND material=" . $requestParams['special1']['value'];
                    }
                    break;
                case 6:
                    break;
                case 7:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND form=" . $requestParams['special1']['value'];
                    }
                    break;
                case 8:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND sort=" . $requestParams['special1']['value'];
                    }
                    if (isset($requestParams['special2']['value']) && $requestParams['special2']['value']) {
                        $where_special2 = " AND printType=" . $requestParams['special2']['value'];
                    }
                    if (isset($requestParams['special3']['value']) && $requestParams['special3']['value']) {
                        $where_special3 = " AND material=" . $requestParams['special3']['value'];
                    }
                    break;
                case 9:
                    break;
                case 10:
                    break;
                case 11:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND sort=" . $requestParams['special1']['value'];
                    }
                    break;
                case 12:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND material=" . $requestParams['special1']['value'];
                    }
                    break;
                case 13:
                    if (isset($requestParams['special1']['value']) && $requestParams['special1']['value']) {
                        $where_special1 = " AND binding=" . $requestParams['special1']['value'];
                    }
                    break;
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
                        if (isset($yearsArr[1]) && $yearsArr[1]) {
                            $where_years .= " AND params.postDate2<=" . intval($yearsArr[1] . '00');
                        }
                    }
                } else {
                    if (count($yearsArr) > 0) {
                        if ($yearsArr[0]) {
                            $where_years = " AND pubdate>=" . intval($yearsArr[0] . '00');
                        }
                        if (isset($yearsArr[1]) && $yearsArr[1]) {
                            $where_years .= " AND pubdate<=" . intval($yearsArr[1] . '00');
                        }
                    }
                }
            }
        }
        
        if (isset($requestParams['price']['value']) && $requestParams['price']['value']) {
            $priceArr = explode('h', $requestParams['price']['value']);
            if (count($priceArr) > 0) {
                if ($priceArr[0]) {
                    $where_price .= " AND price>=" . $priceArr[0];
                }
                if (isset($priceArr[1]) && $priceArr[1]) {
                    $where_price .= " AND price<=" . $priceArr[1];
                }
            }
        }
        if (isset($requestParams['number']['value']) && $requestParams['number']['value']) {
            $numberArr = explode('h', $requestParams['number']['value']);
            if (count($numberArr) > 0) {
                if ($numberArr[0]) {
                    $where_number .= " AND number>=" . $numberArr[0];
                }
                if (isset($numberArr[1]) && $numberArr[1]) {
                    $where_number .= " AND number<=" . $numberArr[1];
                }
            }
        }
         if (isset($requestParams['class']['value']) && $requestParams['class']['value']) {
            $classArr = explode('h', $requestParams['class']['value']);
            if (count($classArr) > 0) {
                if ($classArr[0]) {
                    $where_class .= " AND class>=" . $classArr[0];
                }
                if (isset($classArr[1]) && $classArr[1]) {
                    $where_class .= " AND class<=" . $classArr[1];
                }
            }
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
        
        if (isset($requestParams['discount']['value']) && $requestParams['discount']['value'] != '') {
            $discountArr = explode('h', $requestParams['discount']['value']);
            if (count($discountArr) > 0) {
                if ($discountArr[0]) {
                    $where_discount .= " AND discount>=" . $discountArr[0];
                }
                if (isset($discountArr[1]) && $discountArr[1]) {
                    $where_discount .= " AND discount<=" . $discountArr[1];
                }
            }
        }

        $authorArr = array();
        $pressArr = array();
        if (isset($requestParams['author']['value']) && $requestParams['author']['value']) {
            if (strpos($requestParams['author']['value'], 'h') !== false) {
                $authorArr = explode('h', $requestParams['author']['value'], 2);
                if (count($authorArr) > 0 && $authorArr[0]) {
                    $where_author = ' AND iauthor=' . $authorArr[0];
                }
            } else {
                $author = isset($requestParams['author']['nocode']) && $requestParams['author']['nocode'] == 1 ? $requestParams['author']['value'] : $this->unicode2str($requestParams['author']['value']);
                $match_author = '@(_author,x_author) ' . $this->sc->segwords($author);
            }
        }
        if (isset($requestParams['press']['value']) && $requestParams['press']['value']) {
            if (strpos($requestParams['press']['value'], 'h') !== false) {
                $pressArr = explode('h', $requestParams['press']['value'], 2);
                if (count($pressArr) > 0 && $pressArr[0]) {
                    $where_press = ' AND ipress=' . $pressArr[0];
                }
            } else {
                $press = isset($requestParams['press']['nocode']) && $requestParams['press']['nocode'] == 1 ? $requestParams['press']['value'] : $this->unicode2str($requestParams['press']['value']);
                $match_press = '@(_press,x_press) ' . $this->sc->segwords($press);
            }
        }
        if (count($authorArr) > 0 && !$authorArr[0] && isset($authorArr[1]) && $authorArr[1]) {
            $match_author = '@(_author,x_author) ' . $this->sc->segwords($this->unicode2str($authorArr[1]));
        }
        if (count($pressArr) > 0 && !$pressArr[0] && isset($pressArr[1]) && $pressArr[1]) {
            $match_press = '@(_press,x_press) ' . $this->sc->segwords($this->unicode2str($pressArr[1]));
        }
        if (isset($requestParams['shopname']['value']) && $requestParams['shopname']['value']) {
            $shopName = isset($requestParams['shopname']['nocode']) && $requestParams['shopname']['nocode'] == 1 ? $requestParams['shopName']['value'] : $this->unicode2str($requestParams['shopname']['value']);
            $match_shopname = '@_shopname  ' . $this->sc->segwords($shopName);
        }
        if (isset($requestParams['itemname']['value']) && $requestParams['itemname']['value']) {
            $itemname = isset($requestParams['itemname']['nocode']) && $requestParams['itemname']['nocode'] == 1 ? $requestParams['itemname']['value'] : $this->unicode2str($requestParams['itemname']['value']);
            $match_itemname = '@(_itemname,x_itemname)  ' . $this->sc->segwords($itemname);
        }
        
        if (isset($requestParams['addtime']['value']) && ($requestParams['addtime']['value'] || $requestParams['addtime']['value'] === '0')) {
            if(strpos($requestParams['addtime']['value'], 'h') === false) {
                if ($requestParams['addtime']['value'] === '0') { //如果为零，则取最近24小时的数据(shop)
                    $this->setExpire(600); //缓存10分钟
                    $curM = date('i');
                    if($curM % 10 === 0) {
                        $timeLimit = strtotime(date('Y-m-d H'). ':'. $curM. ':00') - 86400;
                    } else {
                        $cha = $curM - ($curM % 10);
                        $timeLimit = strtotime(date('Y-m-d H'). ':'. $cha. ':00') - 86400;
                    }
                    //不取ISBN批量上传 及 ISBN批量删除后恢复已删商品
                    $where_addtime = " AND addtime>" . $timeLimit. " AND approach NOT IN (2,5)";
                } elseif ($requestParams['addtime']['value'] == 1)  { //如果为1，则取当天的数据(book)
                    $timeLimit_1 = strtotime(date('Ymd'));
                    $timeLimit_2 = $timeLimit_1 + 3600 * 24;
                    $where_addtime = " AND addtime>" . $timeLimit_1 . " AND addtime<" . $timeLimit_2;
                } elseif (strlen($requestParams['addtime']['value']) == 8) { //取指定日期的数据
                    $timeLimit_1 = strtotime($requestParams['addtime']['value']);
                    $timeLimit_2 = $timeLimit_1 + 3600 * 24;
                    $where_addtime = " AND addtime>" . $timeLimit_1 . " AND addtime<" . $timeLimit_2;
                }
            } else {
                $addtimeArr = explode('h', $requestParams['addtime']['value']);
                if (count($addtimeArr) > 0) {
                    if ($addtimeArr[0] && strlen($addtimeArr[0]) == 8 && is_numeric($addtimeArr[0])) {
                        $where_addtime .= " AND addtime>=" . strtotime($addtimeArr[0]);
                    }
                    if (isset($addtimeArr[1]) && $addtimeArr[1] && strlen($addtimeArr[1]) == 8 && is_numeric($addtimeArr[1])) {
                        if($addtimeArr[1] == $addtimeArr[0]) {
                            $timeTmp = strtotime($addtimeArr[1]) + 86400;
                            $where_addtime .= " AND addtime<=" . $timeTmp;
                        } else {
                            $where_addtime .= " AND addtime<=" . strtotime($addtimeArr[1]);
                        }
                    }
                    if ($addtimeArr[0] && strlen($addtimeArr[0]) == 10 && is_numeric($addtimeArr[0])) {
                        $where_addtime .= " AND addtime>=" . $addtimeArr[0];
                    }
                    if (isset($addtimeArr[1]) && $addtimeArr[1] && strlen($addtimeArr[1]) == 10 && is_numeric($addtimeArr[1])) {
                        $where_addtime .= " AND addtime<=" . $addtimeArr[1];
                    }
                }
            }
        }
        if (isset($requestParams['updatetime']['value']) && $requestParams['updatetime']['value']) {
            $updatetimeArr = explode('h', $requestParams['updatetime']['value']);
            if (count($updatetimeArr) > 0) {
                if ($updatetimeArr[0] && strlen($updatetimeArr[0]) == 8 && is_numeric($updatetimeArr[0])) {
                    $where_updatetime .= " AND updatetime>=" . strtotime($updatetimeArr[0]);
                }
                if (isset($updatetimeArr[1]) && $updatetimeArr[1] && strlen($updatetimeArr[1]) == 8 && is_numeric($updatetimeArr[1])) {
                    if($updatetimeArr[1] == $updatetimeArr[0]) {
                        $timeTmp = strtotime($updatetimeArr[1]) + 86400;
                        $where_updatetime .= " AND updatetime<=" . $timeTmp;
                    } else {
                        $where_updatetime .= " AND updatetime<=" . strtotime($updatetimeArr[1]);
                    }
                }
                if ($updatetimeArr[0] && strlen($updatetimeArr[0]) == 10 && is_numeric($updatetimeArr[0])) {
                    $where_updatetime .= " AND updatetime>=" . $updatetimeArr[0];
                }
                if (isset($updatetimeArr[1]) && $updatetimeArr[1] && strlen($updatetimeArr[1]) == 10 && is_numeric($updatetimeArr[1])) {
                    $where_updatetime .= " AND updatetime<=" . $updatetimeArr[1];
                }
            }
        }
        if (isset($requestParams['pubdate']['value']) && $requestParams['pubdate']['value']) {
            $pubdateArr = explode('h', $requestParams['pubdate']['value']);
            if (count($pubdateArr) > 0) {
                if ($pubdateArr[0] && strlen($pubdateArr[0]) == 8 && is_numeric($pubdateArr[0])) {
                    $where_pubdate .= " AND pubdate>=" . intval($pubdateArr[0]);
                }
                if (isset($pubdateArr[1]) && $pubdateArr[1] && strlen($pubdateArr[1]) == 8 && is_numeric($pubdateArr[1])) {
                    $where_pubdate .= " AND pubdate<=" . intval($pubdateArr[1]);
                }
                if ($pubdateArr[0] && strlen($pubdateArr[0]) == 6 && is_numeric($pubdateArr[0])) {
                    $where_pubdate .= " AND pubdate>=" . intval($pubdateArr[0]. '00');
                }
                if (isset($pubdateArr[1]) && $pubdateArr[1] && strlen($pubdateArr[1]) == 6 && is_numeric($pubdateArr[1])) {
                    $where_pubdate .= " AND pubdate<=" . intval($pubdateArr[1]. '00');
                }
            }
        }
        if(isset($requestParams['biztype']['value']) && $requestParams['biztype']['value'] && is_numeric($requestParams['biztype']['value'])) {
            $where_biztype = " AND biztype=". $requestParams['biztype']['value'];
        }
        if((isset($requestParams['isfuzzy']['value']) && $requestParams['isfuzzy']['value']) || $this->matchType == 'fuzzy') {
            $matchType = 'fuzzy';
        }
        if(isset($requestParams['shopid']['value']) && $requestParams['shopid']['value']) {
            if(strpos($requestParams['shopid']['value'], 'h') !== false) {
                $shopidStr = str_replace('h', ',', $requestParams['shopid']['value']);
                $where_shopid = " AND shopid IN (". $shopidStr. ")";
            } else {
                $where_shopid = " AND shopid=". $requestParams['shopid']['value'];
            }
        }
        if(isset($requestParams['approach']['value']) && $requestParams['approach']['value']) {
            if(strpos($requestParams['approach']['value'], 'h') !== false) {
                $approachStr = str_replace('h', ',', $requestParams['approach']['value']);
                $where_approach = " AND approach IN (". $approachStr. ")";
            } else {
                $where_approach = " AND approach=". $requestParams['approach']['value'];
            }
        }
        if(isset($requestParams['filteritemid']['value']) && $requestParams['filteritemid']['value']) {
            $filteritemidStr = str_replace('h', ',', $requestParams['filteritemid']['value']);
            $where_filteritemid = " AND id NOT IN (". $filteritemidStr. ")";
        }
        if(isset($requestParams['filtercatid']['value']) && $requestParams['filtercatid']['value']) {
            $filtercatidStr = str_replace('h', ',', $requestParams['filtercatid']['value']);
            $where_filtercatid = " AND catid1 NOT IN (". $filtercatidStr. ")";
        }
        if(isset($requestParams['userid']['value']) && $requestParams['userid']['value']) {
            if(strpos($requestParams['userid']['value'], 'h') !== false) {
                $useridStr = str_replace('h', ',', $requestParams['userid']['value']);
                $where_userid = " AND userid IN (". $useridStr. ")";
            } else {
                $where_userid = " AND userid=". $requestParams['userid']['value'];
            }
        }
        if(isset($requestParams['hasimg']['value']) && $requestParams['hasimg']['value'] && is_numeric($requestParams['hasimg']['value'])) {
            if($requestParams['hasimg']['value'] == 1) {
                $where_hasimg = " AND hasimg=1";
            } elseif ($requestParams['hasimg']['value'] == 2) {
                $where_hasimg = " AND hasimg=0";
            }
        }
        if(isset($requestParams['quality']['value']) && $requestParams['quality']['value'] && is_numeric($requestParams['quality']['value'])) {
            if($requestParams['quality']['value'] == 101) {
                $where_quality = " AND quality!=100";
            } else {
                $where_quality = " AND quality=". $requestParams['quality']['value'];
            }
        }
        if($this->unLimit && isset($requestParams['certifystatus']['value']) && ($requestParams['certifystatus']['value'] || $requestParams['certifystatus']['value'] === '0')) {
            $where_certifyStatus = " AND certifystatus=". $requestParams['certifystatus']['value'];
        } else {
            $where_certifyStatus = " AND certifystatus=1";
        }
        if($this->unLimit && isset($requestParams['recertifystatus']['value']) && ($requestParams['recertifystatus']['value'] || $requestParams['recertifystatus']['value'] === '0')) {
            $where_recertifyStatus = " AND recertifystatus=". $requestParams['recertifystatus']['value'];
        } else {
            $where_recertifyStatus = '';
        }
        if (isset($requestParams['order']['value']) && intval($requestParams['order']['value'])) {
            switch (intval($requestParams['order']['value'])) {
                case 1:
                    $order = 'price asc';
                    break;
                case 2:
                    $order = 'price desc';
                    break;
                case 3:
                    $order = 'pubdate2 asc';
                    break;
                case 4:
                    $order = 'pubdate desc';
                    break;
                case 5:
                    $order = 'addtime asc';
                    break;
                case 6:
                    $order = 'addtime desc';
                    break;
                case 7:
                    $order = 'class desc';
                    break;
                case 8:
                    $order = 'updatetime asc';
                    break;
                case 9:
                    $order = 'updatetime desc';
                    break;
                case 10:
                    $order = 'class asc';
                    break;
                case 11:
                    $order = 'discount asc';
                    break;
                case 12:
                    $order = 'discount desc';
                    break;
                default:
                    $order = 'updatetime asc';
                    break;
            }
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
            if($this->index == 'unproduct') {
                $index = $this->index;
                if($requestParams['status']['value'] == 1) {
                    $where_salestatus = ' AND salestatus=1';
                }
            } else {
                if ($requestParams['status']['value'] == 1) {
                    $index = $this->index. '_sold';
                } else {
                    $index = $this->index. '_all';
                }
            }
        } else {
            if($this->index == 'unproduct') {
                $index = $this->index;
                $where_salestatus = ' AND salestatus=0';
            } else {
                $index = $this->index;
            }
        }
        
        $exKey = 0;
        $key = 0;
        if (isset($requestParams['exkey']['value']) && $requestParams['exkey']['value']) {
            $exKey = isset($requestParams['exkey']['nocode']) && $requestParams['exkey']['nocode'] == 1 ? $requestParams['exkey']['value'] : $this->unicode2str($requestParams['exkey']['value']);
        }
        if (isset($requestParams['key']['value']) && $requestParams['key']['value']) {
            $key = isset($requestParams['key']['nocode']) && $requestParams['key']['nocode'] == 1 ? $requestParams['key']['value'] : $this->unicode2str($requestParams['key']['value']);
        }
        if ($key !== 0 && $exKey !== 0) {
            $match_key = '@(_author,_press,_itemname,isbn,x_itemname,x_author,x_press) ' . $this->sc->segwords($key) . ' !(' . $this->sc->segwords($exKey) . ')';
        } elseif ($key !== 0 && $exKey === 0) {
            if ($matchType == 'fuzzy') { //模糊搜索
                $match_key = '@@relaxed @(_author,_press,_itemname,isbn,_tag,itemdesc,x_itemname,x_author,x_press) "' . $this->sc->segwords($key) . '"/0.5';
            } else {
                if($this->isMakeOr) {
                    $match_key = '@(_author,_press,_itemname,isbn,x_itemname,x_author,x_press) ' . $this->makeOr($this->sc->segwords($key));
                } else {
                    $match_key = '@(_author,_press,_itemname,isbn,x_itemname,x_author,x_press) ' . $this->sc->segwords($key);
                }
            }
        } elseif ($key === 0 && $exKey !== 0) {
            $match_key = '@(_author,_press,_itemname,isbn,x_itemname,x_author,x_press) !(' . $this->sc->segwords($exKey) . ')';
        }
        if (isset($requestParams['itemdesc']['value']) && $requestParams['itemdesc']['value']) {
            $itemdescKey = isset($requestParams['itemdesc']['nocode']) && $requestParams['itemdesc']['nocode'] == 1 ? $requestParams['itemdesc']['value'] : $this->unicode2str($requestParams['itemdesc']['value']);
            $match_itemdesc = '@itemdesc ' . $this->sc->segwords($itemdescKey);
        }

        //连接match
        $match = trim($match_author . ' ' . $match_press . ' ' . $match_shopname . ' ' . $match_itemname . ' ' . $match_key . ' '. $match_itemdesc);
        if(strpos($match_key, '@@relaxed') !== false) {
            $match = trim($match_key. ' '. $match_author . ' ' . $match_press . ' ' . $match_shopname . ' ' . $match_itemname . ' '. $match_itemdesc);
        }

        if ($match) { //有match时 分类走过滤
            if (!$match_author && !$match_press && !$match_shopname && !$match_itemname && $key === 0 && $exKey !== 0) { //当仅有一个排除关键字时，情况特殊
                $match = trim($match_catNum . ' ' . $match);
                if ($match_catNum && $where_catNum) {
                    $where_catNum = '';
                }
                $isMatch = 0;
                $order = $order ? $order : 'addTime DESC';
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
            $order = $order ? $order : 'addTime DESC';
        }

        //连接where
        $where = $where_pre . $where_catNum . $where_discount. $where_addtime. $where_pubdate. $where_biztype. $where_shopid. $where_userid. $where_hasimg. $where_quality. $where_updatetime. $where_certifyStatus. $where_salestatus. $where_recertifyStatus. $where_years. $where_author. $where_press. $where_special1. $where_special2. $where_special3. $where_price. $where_location. $where_approach. $where_filteritemid. $where_filtercatid. $where_number. $where_class;

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
            $matchExt['author'] = trim($match_catNum . ' ' . $match_press . ' ' . $match_shopname . ' ' . $match_itemname . ' ' . $match_key. $match_itemdesc);
        }
        if (count($pressArr) > 0 && !$pressArr[0] && $pressArr[1]) {
            $matchExt['press'] = $match;
        } else {
            $matchExt['press'] = trim($match_catNum . ' ' . $match_author . ' ' . $match_shopname . ' ' . $match_itemname . ' ' . $match_key. $match_itemdesc);
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
        $queryArr = array();
        
        //获取分类聚类
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $this->sc->setStmtColumnList('groupby() as cid,count(*) as num', 0);
            $this->sc->setStmtGroupBy($this->searchParams['groupExt']['catNum'], '', 0);
            $this->sc->setStmtOrderBy('num desc', 1);
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
            $queryArr[] = 1;
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
            $queryArr[] = 2;
        }

        $max_matches = $max_matches = isset($this->requestParams['pagenum']['value']) && $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
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
        $queryArr = array();
        
        //获取分类聚类
        if (!isset($this->requestParams['catnum']['hasLeaf']) || $this->requestParams['catnum']['hasLeaf']) {
            $this->sc->setStmtColumnList('groupby() as cid,count(*) as num', 0);
            $this->sc->setStmtGroupBy($this->searchParams['groupExt']['catNum'], '', 0);
            $this->sc->setStmtOrderBy('num desc', 1);
            $this->sc->setStmtLimit(0, 999, 0);
            if ($this->searchParams['whereExt']['catNum']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['catNum'], 0);
            }
            $this->sc->setStmtQuery($this->searchParams['match'], 0);
            $queryArr[] = 0;
        }

        $max_matches = $max_matches = isset($this->requestParams['pagenum']['value']) && $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
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
        $this->sc->setStmtQueryIndex($this->productIndex, array(0, 7));
        //获取分类聚类
        $this->sc->setStmtColumnList('groupby() as catid,count(*) as num', 0);
        $this->sc->setStmtGroupBy('catid1', '', 0);
        $this->sc->setStmtOrderBy('num desc', 0);
        $this->sc->setStmtLimit(0, 999, 0);
        //获取相应商品
        $this->sc->setStmtColumnList('*', 7);
//        $this->sc->setStmtOrderBy('addTime DESC', 1);

        $this->sc->setStmtFilter('isdeleted=0 AND certifystatus=1 AND shopstatus=1', array(0, 7));
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
     * 跟据用户条件获得product num数量
     */
    public function getPNWithFilter()
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
        $this->sc->setStmtColumnList('count(*) as num', 7);
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
     * 获取只有分类的聚类为搜索首页使用
     */
    public function getOnlyCatFilter()
    {
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => 2000, 'cutoff' => $this->cutoff), 0);
        $this->sc->setStmtQueryIndex($this->productIndex, 0);
        //获取分类聚类
        $this->sc->setStmtColumnList('groupby() as cid,count(*) as num', 0);
        $this->sc->setStmtGroupBy('catid1', '', 0);
        $this->sc->setStmtOrderBy('num desc', 0);
        $this->sc->setStmtLimit(0, 999, 0);

        $this->sc->setStmtFilter('isdeleted=0 AND certifystatus=1 AND shopstatus=1', 0);
        //搜索全部
        $result = $this->sc->query('*', $this->getExpire());
//        echo '<pre>';
//        print_r($result);exit;

        return $this->formatSearchData($result);
    }
    
    /**
     * 获取最新上架的商品
     */
    public function getTodayItemList()
    {
        $this->sc->setStmtQueryIndex($this->productIndex, 7);
        $this->sc->setStmtColumnList('*', 7);
        $this->sc->setStmtOrderBy('addtime desc', 7);
        $this->sc->setStmtFilter('isdeleted=0 AND certifystatus=1 AND shopstatus=1 AND hasimg=1', 7);
        $this->sc->setStmtLimit(0, $this->pageSize, 7);
        $result = $this->sc->query(array(7), $this->getExpire());
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
        $queryArr = array();
        $max_matches = isset($this->requestParams['pagenum']['value']) && $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
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
        $queryArr[] = 7;
        
        //获取分类聚类
        if (!isset($this->searchParams['catNum']['hasLeaf']) || $this->searchParams['catNum']['hasLeaf']) {
            $this->sc->setStmtColumnList('groupby() as cid,count(*) as num', 0);
            if(isset($this->searchParams['catNum']['groupby'])){
                $this->sc->setStmtGroupBy($this->searchParams['catNum']['groupby'], '', 0);
            }else{
                $this->sc->setStmtGroupBy($this->searchParams['groupExt']['catNum'], '', 0);
            }
            $this->sc->setStmtOrderBy('num desc', 0);
            $this->sc->setStmtLimit(0, 999, 0);
            if ($this->searchParams['whereExt']['catNum']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['catNum'], 0);
            }
            if(!isset($this->searchParams['catNum']['groupby'])){
                $this->sc->setStmtQuery($this->searchParams['match'], 0);
            }
            $queryArr[] = 0;
        }

        //获取作者聚类
        if ($this->searchParams['catNum']['value']) {
            $this->sc->setStmtColumnList('groupby() as authorid,author2,count(*) as num', 1);
            $this->sc->setStmtGroupBy('iauthor', '', 1);
            $this->sc->setStmtOrderBy('num desc', 1);
            $this->sc->setStmtLimit(0, 9, 1);
            if ($this->searchParams['where']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['author'], 1);
            }
            $this->sc->setStmtQuery($this->searchParams['matchExt']['author'], 1);
            $queryArr[] = 1;
        }

        //获取出版社聚类
        if ($this->searchParams['catNum']['value']) {
            $this->sc->setStmtColumnList('groupby() as pressid,press2,count(*) as num', 2);
            $this->sc->setStmtGroupBy('ipress', '', 2);
            $this->sc->setStmtOrderBy('num desc', 2);
            $this->sc->setStmtLimit(0, 9, 2);
            if ($this->searchParams['where']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['press'], 2);
            }
            $this->sc->setStmtQuery($this->searchParams['matchExt']['press'], 2);
            $queryArr[] = 2;
        }
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), $queryArr);

        //搜索全部
        $this->sc->setStmtQueryIndex($this->searchParams['index'], $queryArr);
        $result = $this->sc->query($queryArr, $this->getExpire());
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
        $queryArr = array();
        $result = array();

        //获取分类聚类
        if (!isset($this->searchParams['catNum']['hasLeaf']) || $this->searchParams['catNum']['hasLeaf']) {
            $this->sc->setStmtColumnList('groupby() as cid,count(*) as num', 0);
            $this->sc->setStmtGroupBy($this->searchParams['groupExt']['catNum'], '', 0);
            $this->sc->setStmtOrderBy('num desc', 0);
            $this->sc->setStmtLimit(0, 999, 0);
            if ($this->searchParams['whereExt']['catNum']) {
                $this->sc->setStmtFilter($this->searchParams['whereExt']['catNum'], 0);
            }
            $this->sc->setStmtQuery($this->searchParams['match'], 0);
            $queryArr[] = 0;
            $max_matches = isset($this->requestParams['pagenum']['value']) && $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
            $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), $queryArr);

            //搜索全部
            $this->sc->setStmtQueryIndex($this->searchParams['index'], 0);
            $result = $this->sc->query(array(0), $this->getExpire());
        }

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
        //取消class>=5的条件
        $queryStr = "id,itemname,price,imgurl,shopid";
        if (isset($this->requestParams['itemname']['value']) && $this->requestParams['itemname']['value']) {
            $itemname = isset($this->requestParams['itemname']['nocode']) && $this->requestParams['itemname']['nocode'] == 1 ? $this->requestParams['itemname']['value'] : $this->unicode2str($this->requestParams['itemname']['value']);
            $match_key = '@@relaxed @(_itemname,x_itemname) "' . $this->sc->segwords($itemname) . '"/0.7';
        } else {
            return $this->formatSearchData(array());
        }
        
        $this->sc->setStmtQuery($match_key, 7);
        $this->sc->setStmtFilter($this->searchParams['where'], 7);
        $this->sc->setStmtQueryIndex($this->searchParams['index'], 7);
        $this->sc->setStmtColumnList($queryStr, 7);
        $this->sc->setStmtLimit(0, $this->searchParams['limit']['maxNum'], 7); //限制返回条数
        $this->sc->setStmtOption(array('ranker' => $this->fuzzyRanker, 'field_weights' => $this->field_weights, 'max_matches' => 22, 'cutoff' => $this->cutoff), 7);
        $result = $this->sc->query(array(7), $this->getExpire());

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
        
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => 2000, 'cutoff' => $this->cutoff), array(7));
        $this->sc->setStmtQueryIndex($this->searchParams['index'], 7);
        //获取分类聚类
        $this->sc->setStmtColumnList('groupby() as shopid,count(*) as num', 7);
        $this->sc->setStmtGroupBy('shopid', '', 7);
        $this->sc->setStmtFilter($this->searchParams['where'], 7);
        $this->sc->setStmtOrderBy('num desc', 7);
        $this->sc->setStmtLimit(0, 10, 7);
        $result = $this->sc->query(array(7), $this->getExpire());

        return $this->formatSearchData($result);
    }
    
    /**
     * 按类别获取统计
     */
    public function SHOP_getCategoryItemCount()
    {
        if(empty($this->searchParams) || !isset($this->otherParams['groupbyas']) || !$this->otherParams['groupbyas'] || !isset($this->otherParams['groupby']) || !$this->otherParams['groupby']) {
            return $this->formatSearchData(array());
        }
        
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => 2000, 'cutoff' => $this->cutoff), 7);
        $this->sc->setStmtQueryIndex($this->searchParams['index'], 7);
        //获取分类聚类
        $this->sc->setStmtColumnList('groupby() as '. $this->otherParams['groupbyas']. ',count(*) as num', 7);
        $this->sc->setStmtGroupBy($this->otherParams['groupby'], '', 7);
        $this->sc->setStmtFilter($this->searchParams['where'], 7);
        $this->sc->setStmtOrderBy('num desc', 7);
        $this->sc->setStmtLimit(0, 100, 7);
        $result = $this->sc->query(array(7), $this->getExpire());

        return $this->formatSearchData($result);
    }
    
    /**
     * 根据出版社名称查询商品
     */
    public function LIB_searchBooksByPressName()
    {
        if(empty($this->searchParams)) {
            return $this->formatSearchData(array());
        }
        $queryArr = array();
        $max_matches = isset($this->requestParams['pagenum']['value']) && $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
        $press = isset($this->requestParams['press']['nocode']) && $this->requestParams['press']['nocode'] == 1 ? $this->requestParams['press']['value'] : $this->unicode2str($this->requestParams['press']['value']);
        $match = '@(_press,x_press) ' . $this->sc->segwords($press);
        //获取相应商品
        $this->sc->setStmtColumnList('*', 7);
        $this->sc->setStmtQuery($match, 7);
        if($this->searchParams['order']) {
            $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
        }
        $this->sc->setStmtFilter($this->searchParams['where'], 7);
        $this->sc->setStmtLimit($this->searchParams['limit']['offset'], $this->searchParams['limit']['maxNum'], 7);
        $this->sc->setStmtOption(array('ranker' => $this->ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
        $queryArr[] = 7;
        
        $where = 'isdeleted=0 AND certifystatus=1 AND shopstatus=1';
        if (isset($this->requestParams['location']['value']) && $this->requestParams['location']['value']) {
            $locationArr = explode('h', $this->requestParams['location']['value']);
            if ($locationArr[0]) {
                $where .= " AND area1=" . $locationArr[0];
            }
            if (isset($locationArr[1]) && $locationArr[1]) {
                $where .= " AND area2=" . $locationArr[1];
            }
    	}
        $this->sc->setStmtColumnList('groupby() as cid, count(*) as num', 0);
    	$this->sc->setStmtFilter($where, 0);
    	$this->sc->setStmtQuery($match, 0);
    	$this->sc->setStmtLimit(0,50,0);
    	$this->sc->setStmtGroupBy('catId1','',0);
    	$this->sc->setStmtOrderBy('num DESC ', 0);
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 0);
        $queryArr[] = 0;

        //搜索全部
        $this->sc->setStmtQueryIndex($this->searchParams['index'], $queryArr);
        $result = $this->sc->query($queryArr, $this->getExpire());
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
        $queryArr = array();
        $max_matches = isset($this->requestParams['pagenum']['value']) && $this->requestParams['pagenum']['value'] > $this->maxPageNum ? $this->otherMaxMatch : $this->maxMatch;
        $author = isset($this->requestParams['author']['nocode']) && $this->requestParams['author']['nocode'] == 1 ? $this->requestParams['author']['value'] : $this->unicode2str($this->requestParams['author']['value']);
        $match = '@(_author,x_author) ' . $this->sc->segwords($author);
        //获取相应商品
        $this->sc->setStmtColumnList('*', 7);
        $this->sc->setStmtQuery($match, 7);
        if($this->searchParams['order']) {
            $this->sc->setStmtOrderBy($this->searchParams['order'], 7);
        }
        $this->sc->setStmtFilter($this->searchParams['where'], 7);
        $this->sc->setStmtLimit($this->searchParams['limit']['offset'], $this->searchParams['limit']['maxNum'], 7);
        $this->sc->setStmtOption(array('ranker' => $this->ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 7);
        $queryArr[] = 7;
        
        $where = 'isdeleted=0 AND certifystatus=1 AND shopstatus=1';
        if (isset($this->requestParams['location']['value']) && $this->requestParams['location']['value']) {
            $locationArr = explode('h', $this->requestParams['location']['value']);
            if ($locationArr[0]) {
                $where .= " AND area1=" . $locationArr[0];
            }
            if (isset($locationArr[1]) && $locationArr[1]) {
                $where .= " AND area2=" . $locationArr[1];
            }
    	}
        $this->sc->setStmtColumnList('groupby() as cid, count(*) as num', 0);
    	$this->sc->setStmtFilter($where, 0);
    	$this->sc->setStmtQuery($match, 0);
    	$this->sc->setStmtLimit(0,50,0);
    	$this->sc->setStmtGroupBy('catId1','',0);
    	$this->sc->setStmtOrderBy('num DESC ', 0);
        $this->sc->setStmtOption(array('ranker' => $this->default_ranker, 'max_matches' => $max_matches, 'cutoff' => $this->cutoff), 0);
        $queryArr[] = 0;

        //搜索全部
        $this->sc->setStmtQueryIndex($this->searchParams['index'], $queryArr);
        $result = $this->sc->query($queryArr, $this->getExpire());
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