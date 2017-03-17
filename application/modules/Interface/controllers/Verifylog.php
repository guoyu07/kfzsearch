<?php

/**
 * 审核日志系统接口（只进行统一接口管控、调度，不参与具体业务逻辑）
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2016年9月14日16:24:09
 */
class VerifylogController extends BaseInterfaceController
{

    /**
     * 审核日志系统接口
     * 
     * @var searchModel 
     */
    private $searchModel = null;

    /**
     * 接口返回数据格式
     */
    private $result = array('queryRet' => false, 'result' => array(), 'failDesc' => '');

    /**
     * 控制器的初始化方法，在控制器Action的调用之前，都会调用该方法
     */
    public function init()
    {
        parent::init();
        $this->searchModel = new VerifylogElasticModel();
    }
    
    /**
     * 搜索
     * 
     * requestParams array (
     *      fields    array
     *      query     array
     *      filter    array
     *      sort      array
     *      limit     array
     *      highlight array
     *      facets    array
     *      timeout   int
     *      dfsQuery  bool
     * )
     */
    public function search($params)
    {
        error_reporting(0);
        $this->result['result'] = FALSE;
        if(is_array($params) && !empty($params)){
            //业务类型          必传项
            $bizFlag        = isset($params['bizFlag']) && !empty($params['bizFlag']) ? $params['bizFlag'] : '';
            //具体搜索参数      非必传  
            $requestParams  = isset($params['requestParams']) && !empty($params['requestParams']) ? $params['requestParams'] : '';
            //具体调用搜索方法  非必传
            $action         = isset($params['action']) && $params['action'] ? $params['action'] : 'getData';
            //AGENT            非必传  用户agent(非脚本调用需传参)
            $agent          = isset($params['agent']) ? $params['agent'] : '';
            //realIP           非必传  用户IP（非脚本调用需传参）
            $realIP         = isset($params['realIP']) ? $params['realIP'] : '';
            //预留参数          非必传
            $otherParams    = isset($params['otherParams']) && $params['otherParams'] ? $params['otherParams'] : array();
            
            if(empty($bizFlag) || !in_array($bizFlag, array('verifylog'))) {
                $this->result['failDesc'] = 'searchapi access denied[bf]';
                $this->response($this->result);
                exit;
            }
            
            //设置业务类别
            $this->searchModel->setBizFlag($bizFlag);
            //设置agent
            $this->searchModel->setAgent($agent);
            //设置用户IP并进行防抓取检测
            if(!$this->searchModel->setIP($realIP)) { //如果返回假则采取防抓取屏蔽策略
                $this->result['failDesc'] = 'searchapi access denied';
                $this->response($this->result);
                exit;
            }
            //初始化搜索模型
            if(!$this->searchModel->init($bizFlag)) {
                $this->result['failDesc'] = 'init failure';
                $this->response($this->result);
                exit;
            }
            
            //设置其它扩展参数数组
            if($otherParams) {
                $this->searchModel->setOtherParams($otherParams);
            }
            
            //解析请求数组为搜索条件数组
            $this->searchModel->translateParams($requestParams);
            //执行搜索
            $searchData = $this->searchModel->$action();
            $this->result['queryRet'] = true;
            $this->result['result'] = $searchData;
            
        }
        $this->response($this->result);
    	exit;
    }

}

?>
