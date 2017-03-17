<?php

/**
 * product搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年1月14日13:47:00
 */
class ProductSearchModel extends SearchModel
{
    private $searchObj;     //搜索实例
    private $agent;         //agent
    private $realIP;        //用户IP
    private $bizFlag;       //业务标识
    private $isMakeOr;      //是否支持OR查询
    private $isPersistent;  //是否为长链接
    private $requestParams; //请求参数数组
    private $isForceSpider; //是否强制爬虫
    private $isDemoted;     //是否降级

    /**
     * product搜索操作模型
     */
    public function __construct()
    {
        $this->searchObj         = null;
        $this->isMakeOr          = 0;
        $this->isPersistent      = false;
        $this->requestParams     = array();
        $this->agent             = '';
        $this->realIP            = '';
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
        if($realIP) { //人 + 爬虫
            if(substr($realIP, 0, 7) == '192.168' || 
               substr($realIP, 0, 10) == '117.121.31' || 
               substr($realIP, 0, 11) == '116.213.206') { //内网IP不做限制和公司外网IP不做限制
                $returnState = true;
            } else {
                if($this->preventMaliciousAccess($realIP, $this->bizFlag, $this->agent) == true) {
                    $this->agent = 'AbnormalAccess';
                    $returnState = false;
                }
            }
        }
        if($returnState) {
            $this->statistics($this->bizFlag);
        }
        return $returnState;
    }
    
    /**
     * 并发统计
     */
    public function runtimeState($bizFlag, $isOver)
    {
        return parent::runtimeState($bizFlag, $isOver);
    }
    
    /**
     * 降级与分流限流层
     * 
     * @return boolean true限流 false不限
     */
    public function limitFlow($bizFlag)
    {
        $limitNo = parent::limitFlow($bizFlag);
        if($limitNo < 0) {
            return false;
        }
        $logfile = PROJECT_LOG . 'limitFlow/' .date('Ymd') .'.log';
        if($limitNo == 4) { //降级
            Kfz_Lib_Log::writeLog($logfile, '【降级】搜索频繁调用报警【' . date('Y-m-d H:i:s', time()) . '】   '. "{$bizFlag}自动启动降级机制。", 3);
            $this->isDemoted = true;
            return false;
        }
        if($limitNo == 3) { //限流
            Kfz_Lib_Log::writeLog($logfile, '【限流】搜索频繁调用报警【' . date('Y-m-d H:i:s', time()) . '】   '. "{$bizFlag}自动启动限流机制。", 3);
            return true;
        }
        if($limitNo == 2) { //分流
            Kfz_Lib_Log::writeLog($logfile, '【分流】搜索频繁调用报警【' . date('Y-m-d H:i:s', time()) . '】   '. "{$bizFlag}自动启动分流机制。", 3);
            $this->isForceSpider = true;
            $this->isDemoted = true;
            return false;
        }
        if($limitNo == 1) { //截流
            Kfz_Lib_Log::writeLog($logfile, '【截流】搜索频繁调用报警【' . date('Y-m-d H:i:s', time()) . '】   '. "{$bizFlag}自动启动截流机制。", 3);
            return false;
        }
    }
    
    public function init()
    {
        if($this->bizFlag == '' || !array_key_exists($this->bizFlag, Conf_Sets::$bizSets)) {
            return false;
        }
        $engine                   = Conf_Sets::$bizSets[$this->bizFlag]['engine'];
        if($engine == 'sphinx') {
            $this->searchObj      = new ItemSphinxModel();
        } elseif ($engine == 'elastic') {
            $this->searchObj      = new ItemElasticModel();
            $this->searchObj->setForSpider($this->isForceSpider);
        } elseif ($engine == 'man_Sph_spider_ES') { //爬虫走ES,用户走Sphinx
            if($this->isSpider($this->agent)) {
                $this->searchObj      = new ItemElasticModel();
            } else {
                $this->searchObj      = new ItemSphinxModel();
            }
        } elseif ($engine == 'man_ES_spider_Sph') { //爬虫走Sphinx,用户走ES
            if($this->isSpider($this->agent)) {
                $this->searchObj      = new ItemSphinxModel();
            } else {
                $this->searchObj      = new ItemElasticModel();
            }
        } elseif ($engine == 'man_ES_spider_none') { //用户走ES,爬虫禁止访问
            if($this->isSpider($this->agent)) {
                return false;
            } else {
                $this->searchObj      = new ItemElasticModel();
            }
        } elseif ($engine == 'none') { //禁止
            return false;
        }
        if(method_exists($this->searchObj, 'setAgent')) {
            $this->searchObj->setAgent($this->agent);
        } else {
            return false;
        }
        if(!$this->searchObj->init($this->bizFlag)) {
            return false;
        }
        return true;
    }
    
    /**
     * 设置分页每页数量
     */
    public function setPageSize($pageSize)
    {
        if(!$this->bizFlag || !$this->searchObj) {
            return false;
        }
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->setPageSize($pageSize);
        } else {
            return false;
        }
    }
    
    /**
     * 设置缓存过期时间
     */
    public function setExpire($expire)
    {
        if(!$this->bizFlag || !$this->searchObj) {
            return false;
        }
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->setExpire($expire);
        } else {
            return false;
        }
    }
    
    /**
     * 设置爬虫缓存过期时间
     */
    public function setSpiderExpire($spider_expire)
    {
        if(!$this->bizFlag || !$this->searchObj) {
            return false;
        }
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->setSpiderExpire($spider_expire);
        } else {
            return false;
        }
    }
    
    /**
     * 可设置为模糊搜索
     */
    public function setMatchType($matchType)
    {
        if(!$this->bizFlag || !$this->searchObj) {
            return false;
        }
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->setMatchType($matchType);
        } else {
            return false;
        }
    }
    
    /**
     * 设置高亮
     */
    public function setBuildSnippets($isBuildSnippets)
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->setBuildSnippets($isBuildSnippets);
        } else {
            return false;
        }
    }
    
    /**
     * 设置其它扩展参数数组
     */
    public function setOtherParams($otherParams)
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->setOtherParams($otherParams);
        } else {
            return false;
        }
    }
    
    /**
     * 获取分词
     */
    public function getFuzzyWord()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getFuzzyWord();
        } else {
            return false;
        }
    }
    
    /**
     * 获取参数对应关系
     */
    private function getParamsMapping($type = 0)
    {
        $inteParamsArr = array(
            /**
             * 分类
             * 指定分类         cat_8
             * 在多个分类中查询 cat_8h9
             * 范围             cat_8hh9 会取所有>=8 && <9的分类
             */
            'catnum' => 'cat_',
            /**
             * 折扣
             * 范围dis_5h6   取折扣>=5 && <=6
             * 范围dis_5h    取折扣 >=5
             * 范围dis_h6    取折扣 <=6
             */
            'discount' => 'dis_',
            /**
             * 图书详情
             * 指定搜索图书详情desc_k6d4bk8bd5
             */
            'itemdesc' => 'desc_',
            /**
             * 店铺ID
             * 指定店铺ID    sid_1
             * 查多个店铺ID  sid_1h2h3h4h5
             */
            'shopid' => 'sid_',
            /**
             * 用户ID
             * 指定用户ID    uid_1
             * 查多个用户ID  uid_1h2h3h4h5
             */
            'userid' => 'uid_',
            /**
             * 品相
             * 指定品相      qua_75
             * 旧书          qua_101
             */
            'quality' => 'qua_',
            /**
             * 更新时间
             * 范围          up_20150120h20150121 取20 21两天数据
             * 范围          up_20150120h20150120 取20号数据
             * 范围          up_20150120h         取>=20150120号数据
             * 范围          up_h20150120         取<=20150120号数据
             * h可连接8位日期或10位时间戳
             */
            'updatetime' => 'up_',
            /**
             * 审核状态
             * ce_1          0未审核  1已审核  2驳回  3待复审  4冻结  5待确认
             * 在不受制列表中的项目可以指定此标识
             */
            'certifystatus' => 'ce_',
            /**
             * 出版时间
             * 范围          pub_20150120h20150121 取20 21两天数据
             * 范围          pub_20150120h         取>=20150120号数据
             * 范围          pub_h20150120         取<=20150120号数据
             * h可连接6位精确到月或8位精确到日
             */
            'pubdate' => 'pub_',
            /**
             * 二次审核状态
             * re_1          0未审核  1已审核  2驳回  3待复审  4冻结  5待确认
             * 在不受制列表中的项目可以指定此标识
             */
            'recertifystatus' => 're_',
            /**
             * 获取更多结果
             * more_1        如果项目默认返回50页数据，指定此值后则返回100页，需要项目跟据cookie来设置该字段是否为1
             */
            'getmore' => 'more_',
            /**
             * 上传途径
             * app_1         ISBN批量上书等区分
             */
            'approach' => 'app_',
            /**
             * 最后一项
             * nl_1          不需要有聚类时筛选最后一项时最后一项不做筛选条件情形
             */
            'nolast' => 'nl_',
            /**
             * 过滤商品ID
             * fi_1h2h3h4h5  过滤返回的商品ID
             */
            'filteritemid' => 'fi_',
            /**
             * 过滤分类ID     
             * fc_1h2h3h4h5  过滤返回的分类ID
             */
            'filtercatid' => 'fc_',
            /**
             * 店铺类型
             * biz_1         1书店  2书摊
             */
            'biztype' => 'biz_',
            /**
             * 库存
             * 范围          num_100h200 取>=100 && <=200
             * 范围          num_100h    取>=100
             * 范围          num_h200    取<=200
             */
            'number' => 'num_',
            /**
             * 店铺等级
             * 范围          cls_9h12   取>=9 && <=12
             * 范围          cls_9h     取>=9
             * 范围          cls_h12    取<=12
             */
            'class' => 'cls_',
            /**
             * 是否为精确搜索
             * jq_1          1精确   2正常
             */
            'exact' => 'i_',
            /**
             * 是否为完全匹配
             * wq_1          1精确   2正常
             */
            'perfect' => 'iq_',
            /**
             * 上书时间
             * 范围          g20150120h20150121 取20 21两天数据
             * 范围          g20150120h20150120 取20号数据
             * 范围          g20150120h         取>=20150120号数据
             * 范围          gh20150120         取<=20150120号数据
             * h可连接8位日期或10位时间戳
             */
            'addtime' => 'g',
            /**
             * 有图无图
             * i1            1有图  2无图  默认全部
             */
            'hasimg' => 'i',
            /**
             * 是否为模糊搜索
             * j1            目前业务需求为第一次精确搜索无结果时再执行模糊搜索
             */
            'isfuzzy' => 'j',
            /**
             * 作者
             * l1198999818655686hk66f9k96eak82b9
             * 数字h转码，数字为聚类时返回的值。如果没有聚类而是通过直接搜索，则传lhk66f9k96eak82b9
             */
            'author' => 'l',
            /**
             * 出版社
             * m1198999818655686hk66f9k96eak82b9
             * 数字h转码，数字为聚类时返回的值。如果没有聚类而是通过直接搜索，则传mhk66f9k96eak82b9
             */
            'press' => 'm',
            /**
             * 年代
             * n10           无连接符h时，指定聚类所得年代，有h时当出版时间使用（出版时间只支持到月）
             */
            'years' => 'n',
            /**
             * 特殊项1
             * o11           指定聚类所得特殊项1
             */
            'special1' => 'o',
            /**
             * 特殊项2
             * p11           指定聚类所得特殊项2
             */
            'special2' => 'p',
            /**
             * 特殊项3
             * q11           指定聚类所得特殊项3
             */
            'special3' => 'q',
            /**
             * 店铺名称
             * 指定搜索店铺名称rk6d4bk8bd5
             */
            'shopname' => 'r',
            /**
             * 商品名称
             * 指定搜索商品名称sk6d4bk8bd5
             */
            'itemname' => 's',
            /**
             * 价格
             * 范围          t100h200 取>=100 && <=200
             * 范围          t100h    取>=100
             * 范围          th200    取<=200
             */
            'price' => 't',
            /**
             * 地区
             * u1            精确到省
             * u1h2          精确到省下的市
             */
            'location' => 'u',
            /**
             * 排序
             * 1价格升  2价格降  3出版时间升  4出版时间降  5上架时间升  6上架时间降  7更新时间升  8更新时间降  9店铺等级升  10店铺等级降
             */
            'order' => 'v',
            /**
             * 当前页码
             */
            'pagenum' => 'w',
            /**
             * 排除关键字
             */
            'exkey' => 'x',
            /**
             * 已售or未售
             * 1已售 2全部 默认未售
             */
            'status' => 'y',
            /**
             * 搜索关键字
             * 搜索支持OR连接的或查询，需特殊项目指定。
             * 精确搜索字段：author,press,itemname,isbn
             * 模糊搜索字段：author,press,itemname,isbn,tag,itemdesc
             */
            'key' => 'z'
        );
        return $type ? array_flip($inteParamsArr) : $inteParamsArr;
    }
    
    /**
     * 获取指定业务参数对应关系
     */
    private function getSpecifyParamsMapping($type = 0)
    {
        if($this->bizFlag == 'verify') {
            $paramsArr = array(
                'catnum' => 'cat_',             //分类
                'addtime' => 'g',               //上书时间
                'biztype' => 'i',               //书店or书摊
                'isfuzzy' => 'j',               //是否为模糊搜索
                'author' => 'l',                //作者
                'press' => 'm',                 //出版社
                'itemdesc' => 'n',              //图书详情
                'shopid' => 'o',                //店铺ID
                'hasimg' => 'p',                //有图无图
                'isnew' => 'q',                 //是否为新书
                'shopname' => 'r',              //店铺名称
                'itemname' => 's',              //商品名称
                'updatetime' => 't',            //更新时间
                'certifystatus' => 'u',         //审核状态
                'order' => 'v',                 //排序
                'pagenum' => 'w',               //分页
                'pubdate' => 'x',               //出版时间
                'status' => 'y',                //已售or未售
                'key' => 'z',                   //搜索关键字
                'recertifystatus' => 're_'      //二次审核状态
            );
        } else {
            $paramsArr = $this->getParamsMapping();
        }
        return $type ? array_flip($paramsArr) : $paramsArr;
    }
    
    /**
     * 综合参数
     */
    private function inteParams($paramsArr)
    {
        $inteParamsArr = $this->getParamsMapping();
        foreach ($inteParamsArr as $k => &$v) {
            $temp = $v;
            if(isset($paramsArr[$k])) {
                $v = $paramsArr[$k];
            } else {
                $v = array();
                $v['key'] = '';
                $v['value'] = '';
            }
        }
        return $inteParamsArr;
    }

    /**
     * 将请求的URL解析为数组
     * 
     * @param string $url
     * @return boolean|array
     */
    public function decodeUrl($url)
    {
        if (!$url) {
            return false;
        }
        $url = strtolower(trim($url)); //初始url
        $pregStr = '/('. implode('|', array_keys($this->getSpecifyParamsMapping(1))). ')/is';
        $m_url = ltrim(preg_replace($pregStr, '-$1', $url), '-') . '-';  //用来批配各项的url
        $paramsArr = $this->getSpecifyParamsMapping();
        $ex_url = $m_url; //排除项url
        $ex_arr = array('pagenum', 'order', 'status', 'key', 'catnum', 'addtime', 'isfuzzy');
        foreach ($ex_arr as $key) {
            $ex_url = preg_replace('/' . $paramsArr[$key] . '([a-k0-9\.]+)-/isU', '', $ex_url);
        }
        $ex_url = preg_replace('/-/is', '', $ex_url);
        foreach ($paramsArr as $k => &$v) {
            $temp = $v;
            $v = array();
            $v['key'] = $temp;
            preg_match('/' . $temp . '([a-k0-9\.]+)-/isU', $m_url, $matches);
            if (count($matches) > 0) {
                $v['value'] = $matches[1];
                if (strrpos($ex_url, $v['key'] . $v['value']) + strlen($v['key'] . $v['value']) == strlen($ex_url)) {
                    //判断该项是否为用户最后一次筛选
                    $v['isLast'] = 1;
                }
            } else {
                $v['value'] = '';
            }
            if ($k == 'key' && $v['value']) { //关键字
                $v['fuzzy'] = 1; //执行模糊搜索标志
            }
        }

        if ($paramsArr['catnum']['value']) {
            $catId = CategoryModel::getFullCatId($paramsArr['catnum']['value']);
            $catId = sprintf("%.0f", $catId);
            $vHeadCatsMapping = Data_VHeadCatsMapping::get();
            if (($paramsArr['catnum']['value'] < 100 && array_key_exists($catId, $vHeadCatsMapping)) || strlen(strval($paramsArr['catnum']['value'])) % 3 == 0) { //虚拟分类
                if ($paramsArr['catnum']['value'] < 100) {
                    $catId = $vHeadCatsMapping[$catId];
                }
                $catInfo = Data_ItemVCategory::getItemInfo($catId);
                $paramsArr['catnum']['isV'] = 1;
            } else { //真实分类
                $catInfo = Data_ItemCategory::getItemInfo($catId);
                $paramsArr['catnum']['isV'] = 0;
            }
            $paramsArr['catnum']['hasLeaf'] = $catInfo['hasLeaf'];
            $paramsArr['catnum']['level'] = $catInfo['level'];
            $paramsArr['catnum']['tpl'] = $catInfo['tpl'] > 0 ? $catInfo['tpl'] : 2;
            $paramsArr['catnum']['name'] = $catInfo['name'];
            $paramsArr['catnum']['fullId'] = $catInfo['id'];
            $paramsArr['catnum']['shortId'] = CategoryModel::getShortCatId($paramsArr['catnum']['fullId']);
        }
        
        if($this->bizFlag == 'verify') {
            $paramsArr = $this->inteParams($paramsArr);
        }
        
        return $paramsArr;
    }
    
    /**
     * url去排序和分页
     * 
     * @param string $url
     * @return string
     */
    private function urlWithoutOrderAndPage($url)
    {
        if(!$url) {
            return $url;
        }
        $paramsArr = $this->getSpecifyParamsMapping();
        $o = $paramsArr['order'];
        $p = $paramsArr['pageNum'];
        $url = preg_replace('/'. $p. '[\d]+/is', '', $url); //去分页
        $url = preg_replace('/'. $o. '[\d]+/is', '', $url); //去排序
        return $url;
    }
    
    /**
     * 格式化查询数组
     */
    public function formatRequestParams($requestParams)
    {
        $returnRequestParams = array();
        if(!empty($requestParams)) {
            foreach($requestParams as $k => $v) {
                $strLower = strtolower($k);
                $returnRequestParams[$strLower] = $v;
            }
            unset($requestParams);
            foreach($returnRequestParams as $k => &$v) {
                if($k == 'catnum' && $v['value']) {
                    $catId = CategoryModel::getFullCatId($v['value']);
                    $catId = sprintf("%.0f", $catId);
                    $vHeadCatsMapping = Data_VHeadCatsMapping::get();
                    if (($v['value'] < 100 && array_key_exists($catId, $vHeadCatsMapping)) || strlen(strval($v['value'])) % 3 == 0) { //虚拟分类
                        if ($v['value'] < 100) {
                            $catId = $vHeadCatsMapping[$catId];
                        }
                        $catInfo = Data_ItemVCategory::getItemInfo($catId);
                        $v['isV'] = 1;
                    } else { //真实分类
                        $catInfo = Data_ItemCategory::getItemInfo($catId);
                        $v['isV'] = 0;
                    }
                    $v['hasLeaf'] = $catInfo['hasLeaf'];
                    $v['level'] = $catInfo['level'];
                    $v['tpl'] = $catInfo['tpl'] > 0 ? $catInfo['tpl'] : 2;
                    $v['name'] = $catInfo['name'];
                    $v['fullId'] = $catInfo['id'];
                    $v['shortId'] = CategoryModel::getShortCatId($v['fullId']);
                }
            }
        }
        
        $paramsArr = $this->getSpecifyParamsMapping();
        foreach ($paramsArr as $k => &$v) {
            if(!isset($returnRequestParams[$k])) {
                $returnRequestParams[$k] = array('key' => '', 'value' => '');
            }
        }
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
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->translateParams($requestParams);
        } else {
            return false;
        }
    }
    
    /**
     * 跟据用户条件获得simple filterList和productList
     */
    public function getSFPWithFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getSFPWithFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 跟据用户条件获得catsList和productList
     */
    public function getCPWithFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getCPWithFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 跟据用户条件获得filterList和productList
     */
    public function getFPWithFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getFPWithFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 用户首次访问获得基础filterList和productList（无筛选）
     */
    public function getFPWithOutFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getFPWithOutFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 跟据用户条件获得productList
     */
    public function getPWithFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getPWithFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 跟据用户条件获得product num数量
     */
    public function getPNWithFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getPNWithFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 获取只有分类的聚类为搜索首页使用
     */
    public function getOnlyCatFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getOnlyCatFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 获取最新上架的商品
     */
    public function getTodayItemList()
    {
        if(method_exists($this->searchObj, __FUNCTION__) && !$this->isDemoted) {
            return $this->searchObj->getTodayItemList();
        } else {
            return false;
        }
    }
    
    /*****************************************************SEARCH START********************************************************/
    /**
     * 跟据用户条件获得filterList和productList（爬虫不进行聚类）
     */
    public function SEARCH_getFPWithFilter()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->SEARCH_getFPWithFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 模糊搜索
     */
    public function SEARCH_getPWithFilterForFuzzy()
    {
        if(!$this->isDemoted) {
            return $this->searchObj->getPWithFilter();
        } else {
            return false;
        }
    }
    
    /**
     * 模糊搜索
     */
    public function SEARCH_getFPWithFilterForFuzzy()
    {
        if(!$this->isDemoted) {
            return $this->searchObj->SEARCH_getFPWithFilter();
        } else {
            return false;
        }
    }
    /*****************************************************SEARCH END********************************************************/
    
    /*****************************************************SHOP START********************************************************/
    
    /**
     * 跟据用户条件获得filterList和productList
     */
    public function SHOP_getFPWithFilter()
    {
        return $this->searchObj->getFPWithFilter();
    }
    
    /**
     * SHOP
     * 跟据用户条件获得filterList(catsList、authorList、pressList)和productList
     */
    public function SHOP_getFWithFilter()
    {
        return $this->searchObj->getFWithFilter();
    }
    
    /**
     * SHOP
     * 跟据用户条件获得catList
     */
    public function SHOP_getCatListStat()
    {
        return $this->searchObj->getCatListStat();
    }
    
    /**
     * SHOP
     * 跟据用户条件获得productList
     */
    public function SHOP_getPWithFilter()
    {
        return $this->searchObj->getPWithFilter();
    }
    
    /**
     * SHOP
     * 获取只有分类的聚类为搜索首页使用
     */
    public function SHOP_getOnlyCatFilter()
    {
        return $this->searchObj->getOnlyCatFilter();
    }
    
    /**
     * SHOP
     * 获取可能感兴趣的商品(查询5星级及以上的未售商品)
     * 按照书名模糊匹配
     */
    public function SHOP_getInterestItems()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->SHOP_getInterestItems();
        } else {
            return false;
        }
    }
    
    /**
     * SHOP
     * 获取可能感兴趣的商品，全字段(查询5星级及以上的未售商品)
     * 按照书名模糊匹配
     */
    public function SHOP_getFullInterestItems()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->SHOP_getFullInterestItems();
        } else {
            return false;
        }
    }
    
    /**
     * SHOP
     * 获取店铺24小时最新上书统计
     */
    public function SHOP_getNewAddItemNumByShopIds()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->SHOP_getNewAddItemNumByShopIds();
        } else {
            return false;
        }
    }
    
    /**
     * SHOP
     * 按类别获取统计
     */
    public function SHOP_getCategoryItemCount()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->SHOP_getCategoryItemCount();
        } else {
            return false;
        }
    }
    
    /*****************************************************SHOP END********************************************************/
    
    /*****************************************************BOOKLIB START***************************************************/
    
    /**
     * BOOKLIB
     * 根据出版社名称查询商品
     */
    public function LIB_searchBooksByPressName()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->LIB_searchBooksByPressName();
        } else {
            return false;
        }
    }
    
    /**
     * BOOKLIB
     * 根据作者名查商品
     */
    public function LIB_searchBooksByAuthorName()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->LIB_searchBooksByAuthorName();
        } else {
            return false;
        }
    }
    
    /**
     * BOOKLIB
     * 根据ISBN查商品
     */
    public function LIB_searchBooksByIsbn()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->LIB_searchBooksByIsbn();
        } else {
            return false;
        }
    }
    /*****************************************************BOOKLIB END*****************************************************/
    
    /*****************************************************ISBN BOOKLIB V2 START***************************************************/
    /**
     * BOOKLIB
     * 根据ISBN查最低价格图书
     */
    public function LIB_searchMinPriceByIsbn()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->LIB_searchMinPriceByIsbn();
        } else {
            return false;
        }
    }
    
    /**
     * BOOKLIB
     * 查询ISBN对应商品有无库存
     */
    public function LIB_checkStockByIsbn()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->LIB_checkStockByIsbn();
        } else {
            return false;
        }
    }
    /*****************************************************ISBN BOOKLIB V2 END*****************************************************/
    
    /**
     * 书房搜索在售图书
     * 根据_author,_press,_itemname,isbn进行搜索
     */
    public function STUDY_searchSaledBooks()
    {
        if (method_exists($this->searchObj,__FUNCTION__)) {
            return $this->searchObj->STUDY_searchSaledBooks();
        } else {
            return false;
        }
    }
    
    /**
     * 格式化filterList和productList
     */
    public function translateFPWithFilter($searchData)
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->translateFPWithFilter($searchData);
        } else {
            return $searchData;
        }
    }

}
?>