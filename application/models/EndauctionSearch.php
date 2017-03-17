<?php

/**
 * 历史拍卖搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年8月25日16:42:46
 */
class EndauctionSearchModel extends SearchModel
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
        if($this->bizFlag == '' || !array_key_exists($this->bizFlag, Conf_Sets::$bizSets)) {
            return false;
        }
        $engine                   = Conf_Sets::$bizSets[$this->bizFlag]['engine'];
        if($engine == 'sphinx') {
            $this->searchObj      = new EndauctionSphinxModel();
        } elseif ($engine == 'elastic') {
            $this->searchObj      = new EndauctionElasticModel();
        } elseif ($engine == 'man_Sph_spider_ES') { //爬虫走ES,用户走Sphinx
            if($this->isSpider($this->agent)) {
                $this->searchObj      = new EndauctionElasticModel();
            } else {
                $this->searchObj      = new EndauctionSphinxModel();
            }
        } elseif ($engine == 'man_ES_spider_Sph') { //爬虫走Sphinx,用户走ES
            if($this->isSpider($this->agent)) {
                $this->searchObj      = new EndauctionSphinxModel();
            } else {
                $this->searchObj      = new EndauctionElasticModel();
            }
        } elseif ($engine == 'man_ES_spider_none') { //用户走ES,爬虫禁止访问
            if($this->isSpider($this->agent)) {
                return false;
            } else {
                $this->searchObj      = new EndauctionElasticModel();
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
             * 专场编号
             */
            'specialarea' => 'spe_',
            /**
             * 地区
             */
            'location' => 'area_',
            /**
             * 获取更多结果
             * more_1        如果项目默认返回50页数据，指定此值后则返回100页，需要项目跟据cookie来设置该字段是否为1
             */
            'getmore' => 'more_',
            /**
             * 卖家用户ID
             */
            'userid'  => 'auid_',
            /**
             * 拍品结束时间
             */
            'endtime' => 'g',
            /**
             * 图片或列表方式浏览
             */
            'islist' => 'i',
            /**
             * 是否进行模糊搜索
             */
            'isfuzzy' => 'j',
            /**
             * 作者
             */
            'author' => 'l',
            /**
             * 出版社
             */
            'press' => 'm',
            /**
             * 年代
             */
            'years' => 'n',
            /**
             * 著录项1
             */
            'special1' => 'o',
            /**
             * 著录项2
             */
            'special2' => 'p',
            /**
             * 著录项3
             */
            'special3' => 'q',
            /**
             * 拍品开始时间
             */
            'begintime' => 'r',
            /**
             * 拍卖主题
             */
            'itemname' => 's',
            /**
             * 拍主昵称
             */
            'nickname' => 't',
            /**
             * 拍品编号
             */
            'itemid' => 'u',
            /**
             * 排序
             */
            'order' => 'v',
            /**
             * 分页
             */
            'pagenum' => 'w',
            /**
             * 排除关键字
             */
            'exkey' => 'x',
            /**
             * 状态
             */
            'status' => 'y',
            /**
             * 搜索关键字
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
        $paramsArr = $this->getParamsMapping();
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
        $ex_arr = array('islist', 'pagenum', 'order', 'status',   'isfuzzy', 'specialarea');
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
        $p = $paramsArr['pagenum'];
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
     * 跟据用户条件获得filterList和productList
     */
    public function getOnlyCatFilterForEndItem()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getOnlyCatFilterForEndItem();
        } else {
            return false;
        }
    }
    
    /**
     * 跟据用户条件获得filterList和productList
     */
    public function getFPWithFilterForFinishedList()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getFPWithFilterForFinishedList();
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