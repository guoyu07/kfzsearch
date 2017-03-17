<?php

/**
 * 历史拍卖搜索对外接口
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2015年8月26日9:19:41
 */
class EndauctionController extends BaseInterfaceController
{

    /**
     * 搜索业务类
     * 
     * @var searchModel 
     */
    private $endauctionSearchModel = null;

    /**
     * 接口返回数据格式
     */
    private $result = array('queryRet' => 'false', 'result' => array(), 'failDesc' => '');

    /**
     * 控制器的初始化方法，在控制器Action的调用之前，都会调用该方法
     */
    public function init()
    {
        parent::init();
        $this->endauctionSearchModel = new EndauctionSearchModel();
    }
    
    /**
     * 搜索
     */
    public function search($params)
    {
//        file_put_contents('/tmp/kfzsearch.log', var_export($params, true). "\n", FILE_APPEND);
        error_reporting(0);
        $this->result['result'] = FALSE;
        if(is_array($params) && !empty($params)){
            //业务类型          必传项  如标签商品搜索传'bq'
            $bizFlag        = isset($params['bizFlag']) ? $params['bizFlag'] : '';
            //AGENT            非必传  用户agent(非脚本调用需传参)
            $agent          = isset($params['agent']) ? $params['agent'] : '';
            //realIP           非必传  用户IP（非脚本调用需传参）
            $realIP         = isset($params['realIP']) ? $params['realIP'] : '';
            //具体搜索参数      必传项  如果是字符串型，则会当url解析；也可以是数组类型
            $requestParams  = isset($params['requestParams']) && !empty($params['requestParams']) ? $params['requestParams'] : '';
            //具体调用搜索方法  非必传  默认为getFPWithFilter（根据条件获取聚类及数据）  其它还有getPWithFilter（根据条件获取聚类及数据分页时用） 如有项目查询需求特殊，再添加相应查询方法
            $action         = isset($params['action']) && $params['action'] ? $params['action'] : 'getFPWithFilter';
            //是否对数据进行格式化处理  非必传  默认为0  如果值为1则对返回聚类数据进行格式化
            $isFormatData   = isset($params['isFormatData']) && $params['isFormatData'] ? $params['isFormatData'] : 0;
            //是否执行模糊搜索  非必传  默认为0不执行模糊搜索，因模糊搜索影响性能，所以尽可能不要执行模糊搜索。1为执行模糊搜索
            $isFuzzySearch  = isset($params['isFuzzySearch']) && $params['isFuzzySearch'] ? $params['isFuzzySearch'] : 0;
            //是否进行高亮      非必传  默认为0不执行飘红 如果值为1则飘红
            $isBuildSnippets = isset($params['isBuildSnippets']) && $params['isBuildSnippets'] ? $params['isBuildSnippets'] : 0;
            //预留参数          非必传
            $otherParams    = isset($params['otherParams']) && $params['otherParams'] ? $params['otherParams'] : array();
            //每页商品数
            $pageSize       = !empty($otherParams) && isset($otherParams['pageSize']) ? intval($otherParams['pageSize']) : 0;
            //设置用户缓存时间
            $expire         = !empty($otherParams) && isset($otherParams['expire']) ? intval($otherParams['expire']) : 1200;
            //设置爬虫缓存时间
            $spider_expire  = !empty($otherParams) && isset($otherParams['spider_expire']) ? intval($otherParams['spider_expire']) : 86400;
            $isGetFuzzyWord = !empty($otherParams) && isset($otherParams['isGetFuzzyWord']) ? intval($otherParams['isGetFuzzyWord']) : 0;
            //允许的方法列表
            $actionList     = array('getFPWithFilter', 'getPWithFilter', 'getFPWithOutFilter', 'getOnlyCatFilterForEndItem', 'getFPWithFilterForFinishedList');
            //必须传参的方法列表
            $mustParamList  = array('getFPWithFilter', 'getPWithFilter');
            //可执行模糊搜索的方法列表
            $fuzzyMatchList = array('getFPWithFilter', 'getPWithFilter');
            if(empty($bizFlag) || !array_key_exists($bizFlag, Conf_Sets::$bizSets)){
                $this->result['failDesc'] = 'the bizFlag is invalid';
                $this->response($this->result);
                exit;
            }
            if(in_array($action, $mustParamList) && empty($requestParams)){
                $this->result['failDesc'] = 'the requestParams is invalid';
                $this->response($this->result);
                exit;
            }
            if(!in_array($action, $actionList)) {
                $this->result['failDesc'] = 'the action is invalid';
                $this->response($this->result);
                exit;
            }
            if(is_string($params['requestParams'])) {
                $requestParams = $this->endauctionSearchModel->decodeUrl($requestParams);
            } else {
                $requestParams = $this->endauctionSearchModel->formatRequestParams($requestParams);
            }
            //设置业务类别
            $this->endauctionSearchModel->setBizFlag($bizFlag);
            //设置agent
            $this->endauctionSearchModel->setAgent($agent);
            //设置用户IP并进行防抓取检测
            if(!$this->endauctionSearchModel->setIP($realIP)) { //如果返回假则采取防抓取屏蔽策略
                $this->result['failDesc'] = 'searchapi access denied';
                $this->response($this->result);
                exit;
            }
            //初始化搜索模型
            if(!$this->endauctionSearchModel->init()) {
                $this->result['failDesc'] = 'init failure';
                $this->response($this->result);
                exit;
            }
            //设置分页
            if($pageSize) {
                $this->endauctionSearchModel->setPageSize($pageSize);
            }
            //设置用户缓存时间
            if($expire) {
                $this->endauctionSearchModel->setExpire($expire);
            }
            //设置爬虫缓存时间
            if($spider_expire) {
                $this->endauctionSearchModel->setSpiderExpire($spider_expire);
            }
            //设置高亮
            if($isBuildSnippets) {
                $this->endauctionSearchModel->setBuildSnippets($isBuildSnippets);
            }
            //设置其它扩展参数数组
            if($otherParams) {
                $this->endauctionSearchModel->setOtherParams($otherParams);
            }
            //解析请求数组为搜索条件数组
            $this->endauctionSearchModel->translateParams($requestParams);
            //执行搜索
            $searchData = $this->endauctionSearchModel->$action();
            //执行模糊搜索（只有设置模糊搜索标识 并且 采用sphinx搜索 并且 有查询关键字 并且 初次搜索结果为0 才会再执行一次模糊搜索）
            if($isFuzzySearch && Conf_Sets::$bizSets[$bizFlag]['engine'] == 'sphinx' && isset($requestParams['key']) && isset($requestParams['key']['value']) && $requestParams['key']['value'] && in_array($action, $fuzzyMatchList) && !empty($searchData['itemList']) && isset($searchData['itemList']['total_found']) && $searchData['itemList']['total_found'] == 0) {
                $this->endauctionSearchModel->setMatchType('fuzzy');
                $this->endauctionSearchModel->translateParams($requestParams);
                $searchData = $this->endauctionSearchModel->$action();
            }
            //格式化数据
            if($isFormatData) {
                $searchData = $this->endauctionSearchModel->translateFPWithFilter($searchData);
            }
            //是否返回分词结果
            if($isGetFuzzyWord) {
                $searchData['otherList']['fuzzyWord'] = $this->endauctionSearchModel->getFuzzyWord();
            }
            
            $this->result['queryRet'] = 'true';
            $this->result['result'] = $searchData;
        }
        /*
        if($bizFlag == 'search' || $bizFlag == 'search_shop' || $bizFlag == 'search_book') {
            file_put_contents('/tmp/kfzsearch.log', "【". date("Y-m-d H:i:s"). "】". "\n", FILE_APPEND);
            $logStatus = $this->result['result'] ? 'success' : 'failure';
            file_put_contents('/tmp/kfzsearch.log', var_export($params, true). "\n", FILE_APPEND);
            file_put_contents('/tmp/kfzsearch.log', $logStatus. "\n", FILE_APPEND);
        }
        */
        /*
        if($bizFlag == 'shop' || $bizFlag == 'app') {
            file_put_contents('/tmp/kfzsearch.log', "【". date("Y-m-d H:i:s"). "】". "\n", FILE_APPEND);
            $logStatus = $this->result['result'] ? 'success' : 'failure';
            file_put_contents('/tmp/kfzsearch.log', var_export($params, true). "\n", FILE_APPEND);
            file_put_contents('/tmp/kfzsearch.log', $logStatus. "\n", FILE_APPEND);
        }
        */
        $this->response($this->result);
    	exit;
    }

}

?>
