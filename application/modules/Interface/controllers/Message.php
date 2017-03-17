<?php

/**
 * 消息搜索接口
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2015年9月16日11:48:35
 */
class MessageController extends BaseInterfaceController
{

    /**
     * 消息搜索业务类
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
            $this->searchModel = new MessageElasticModel();
            //具体搜索参数      必传项  如果是字符串型，则会当url解析；也可以是数组类型
            $requestParams  = isset($params['requestParams']) && !empty($params['requestParams']) ? $params['requestParams'] : '';
            $action         = isset($params['action']) && $params['action'] ? $params['action'] : 'getMessageList';
            if(empty($requestParams)) {
                $this->response($this->result);
                exit;
            }
            //记录请求数量
            $bizFlag = 'message';
            $this->searchModel->statistics($bizFlag);
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
