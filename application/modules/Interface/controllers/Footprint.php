<?php

/**
 * 足迹推荐搜索接口
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2015年10月22日12:03:03
 */
class FootprintController extends BaseInterfaceController
{

    /**
     * 足迹推荐搜索接口
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
        $this->searchModel = new FootprintElasticModel();
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
            //具体搜索参数      必传项  
            $requestParams  = isset($params['requestParams']) && !empty($params['requestParams']) ? $params['requestParams'] : '';
            //业务类型          必传项  footprint_search、footprint_shop、footprint_pm、shop_recommend
            $bizFlag        = isset($params['bizFlag']) && !empty($params['bizFlag']) ? $params['bizFlag'] : '';
            //AGENT            非必传  用户agent(非脚本调用需传参)
            $agent          = isset($params['agent']) ? $params['agent'] : '';
            //realIP           非必传  用户IP（非脚本调用需传参）
            $realIP         = isset($params['realIP']) ? $params['realIP'] : '';
            //具体调用搜索方法  非必传
            $action         = isset($params['action']) && $params['action'] ? $params['action'] : 'getFootprintRecommendForShopOrPm';
            if(empty($requestParams)) {
                $this->result['failDesc'] = 'the requestParams is invalid';
                $this->response($this->result);
                exit;
            }
            if(empty($bizFlag) || !in_array($bizFlag, array('footprint_search', 'footprint_shop', 'footprint_pm', 'shop_recommend', 'search_recommend', 'search_shop_recommend', 'search_book_recommend', 'myRecommend'))) {
                $this->result['failDesc'] = 'searchapi access denied[bf]';
                $this->response($this->result);
                exit;
            }
            if(!isset($requestParams['userId']) || empty($requestParams['userId'])) {
                $this->result['failDesc'] = 'the requestParams is invalid';
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
            if(!$this->searchModel->init()) {
                $this->result['failDesc'] = 'init failure';
                $this->response($this->result);
                exit;
            }
            
            //解析请求数组为搜索条件数组
            $this->searchModel->translateParams($requestParams);
            //执行搜索
            $searchData = $this->searchModel->$action();
            $this->result['queryRet'] = true;
            $this->result['result'] = $searchData;
            
//            if($requestParams['userId'] == '201253') {
//                file_put_contents('/tmp/kfzsearch.log', var_export($this->result, true). "\n", FILE_APPEND);
//            }
        }
        $this->response($this->result);
    	exit;
    }

}

?>
