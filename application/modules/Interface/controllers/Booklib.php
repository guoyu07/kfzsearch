<?php

/**
 * Created by diao
 * Date: 16-8-25
 * Time: 下午12:02
 */
class BooklibController extends BaseInterfaceController
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
        $this->searchModel = new BooklibElasticModel();
    }

    /**
     * 搜索
     */
    public function search($params)
    {
//        file_put_contents('/tmp/kfzsearch.log', var_export($params, true). "\n", FILE_APPEND);
        error_reporting(0);
        $this->result['result'] = FALSE;
        if (is_array($params) && !empty($params)) {
            //AGENT            非必传  用户agent(非脚本调用需传参)
            $agent = isset($params['agent']) ? $params['agent'] : '';
            //realIP           非必传  用户IP（非脚本调用需传参）
            $realIP = isset($params['realIP']) ? $params['realIP'] : '';

            //具体搜索参数      必传项  如果是字符串型，则会当url解析；也可以是数组类型
            $requestParams = isset($params['requestParams']) && !empty($params['requestParams']) ? $params['requestParams'] : '';
            $action = isset($params['action']) && $params['action'] ? $params['action'] : 0;
            if (!$action) {
                return false;
            }
            /*
             * getGroupJDList don't pass any params
             */
            if ($action != 'getGroupJDList' && empty($requestParams)) {
                $this->response($this->result);
                exit;
            }

            $bizFlag = 'booklib_library';
            
            //设置业务类别
            $this->searchModel->setBizFlag($bizFlag);
            //设置agent
            $this->searchModel->setAgent($agent);
            //设置用户IP并进行防抓取检测
            if (!$this->searchModel->setIP($realIP)) { //如果返回假则采取防抓取屏蔽策略
                $this->result['failDesc'] = 'searchapi access denied';
                $this->response($this->result);
                exit;
            }
            //执行搜索
            $searchData = $this->searchModel->$action($requestParams);
            $this->result['queryRet'] = true;
            $this->result['result']['itemList'] = $searchData;
        }
        $this->response($this->result);
        exit;
    }
}