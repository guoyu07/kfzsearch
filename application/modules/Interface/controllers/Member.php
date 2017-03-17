<?php

/**
 * 会员搜索对外接口
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2015年9月18日10:36:48
 */
class MemberController extends BaseInterfaceController
{

    /**
     * 搜索业务类
     * 
     * @var searchModel 
     */
    private $memberSearchModel = null;

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
        $this->memberSearchModel = new MemberSearchModel();
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
            $action         = isset($params['action']) && $params['action'] ? $params['action'] : 'getUserList';
            //是否进行高亮      非必传  默认为0不执行飘红 如果值为1则飘红
            $isBuildSnippets = isset($params['isBuildSnippets']) && $params['isBuildSnippets'] ? $params['isBuildSnippets'] : 0;
            //预留参数          非必传
            $otherParams    = isset($params['otherParams']) && $params['otherParams'] ? $params['otherParams'] : array();
            //每页商品数
            $pageSize       = !empty($otherParams) && isset($otherParams['pageSize']) ? intval($otherParams['pageSize']) : 0;
            //设置用户缓存时间
            $expire         = !empty($otherParams) && isset($otherParams['expire']) ? intval($otherParams['expire']) : -1;
            //设置爬虫缓存时间
            $spider_expire  = !empty($otherParams) && isset($otherParams['spider_expire']) ? intval($otherParams['spider_expire']) : -1;
            //允许的方法列表
            $actionList     = array('getUserList');
            //必须传参的方法列表
            $mustParamList  = array('getUserList');
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
            $requestParams = $this->memberSearchModel->formatRequestParams($requestParams);
            //设置业务类别
            $this->memberSearchModel->setBizFlag($bizFlag);
            //设置agent
            $this->memberSearchModel->setAgent($agent);
            //设置用户IP并进行防抓取检测
            if(!$this->memberSearchModel->setIP($realIP)) { //如果返回假则采取防抓取屏蔽策略
                $this->result['failDesc'] = 'searchapi access denied';
                $this->response($this->result);
                exit;
            }
            //初始化搜索模型
            if(!$this->memberSearchModel->init()) {
                $this->result['failDesc'] = 'init failure';
                $this->response($this->result);
                exit;
            }
            //设置分页
            if($pageSize) {
                $this->memberSearchModel->setPageSize($pageSize);
            }
            //设置用户缓存时间
            if($expire) {
                $this->memberSearchModel->setExpire($expire);
            }
            //设置爬虫缓存时间
            if($spider_expire) {
                $this->memberSearchModel->setSpiderExpire($spider_expire);
            }
            //设置高亮
            if($isBuildSnippets) {
                $this->memberSearchModel->setBuildSnippets($isBuildSnippets);
            }
            //设置其它扩展参数数组
            if($otherParams) {
                $this->memberSearchModel->setOtherParams($otherParams);
            }
            //解析请求数组为搜索条件数组
            $this->memberSearchModel->translateParams($requestParams);
            //执行搜索
            $searchData = $this->memberSearchModel->$action();

            $this->result['queryRet'] = 'true';
            $this->result['result'] = $searchData;
        }
        
        $this->response($this->result);
    	exit;
    }

}

?>
