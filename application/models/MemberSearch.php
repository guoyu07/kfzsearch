<?php

/**
 * 会员搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2015年9月18日10:39:56
 */
class MemberSearchModel extends SearchModel
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
        if($engine) {
            $this->searchObj      = new MemberElasticModel();
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
     * 查询会员列表
     */
    public function getUserList()
    {
        if(method_exists($this->searchObj, __FUNCTION__)) {
            return $this->searchObj->getUserList();
        } else {
            return false;
        }
    }
    
}
?>