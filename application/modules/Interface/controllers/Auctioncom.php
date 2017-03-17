<?php

/**
 * 拍卖公司搜索对外接口
 *
 * @author liguizhi<liguizhi_001@163.com>
 * @date   2015年5月6日
 */
class AuctioncomController extends BaseInterfaceController
{

    /**
     * 商品搜索业务类
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
        $this->searchModel = new AuctioncomElasticModel();
    }

    public function getAuctioncomList($params)
    {
//        file_put_contents('/tmp/kfzsearch.log', var_export($params, true). "\n", FILE_APPEND);
        error_reporting(0);
        $this->result['result'] = FALSE;
        if (is_array($params) && !empty($params)) {
            //业务类型          必传项  如标签商品搜索传'bq'
            $bizFlag = isset($params['bizFlag']) ? $params['bizFlag'] : '';

            //具体搜索参数      必传项  如果是字符串型，则会当url解析；也可以是数组类型
            $requestParams = isset($params['requestParams']) && !empty($params['requestParams']) ? $params['requestParams'] : '';
            //具体调用搜索方法  非必传  默认为getFPWithFilter（根据条件获取聚类及数据）  其它还有getPWithFilter（根据条件获取聚类及数据分页时用） 如有项目查询需求特殊，再添加相应查询方法
            $action = isset($params['action']) && $params['action'] ? $params['action'] : 'getFPWithFilter';
            //预留参数          非必传
            $otherParams = isset($params['otherParams']) && $params['otherParams'] ? $params['otherParams'] : array();
            //每页商品数
            $pageSize = !empty($otherParams) && isset($otherParams['pageSize']) ? intval($otherParams['pageSize']) : 0;
            //设置用户缓存时间
            $expire = !empty($otherParams) && isset($otherParams['expire']) ? intval($otherParams['expire']) : 1200;
            //允许的方法列表
            $actionList = array('getAuctioncomList');

            if (!in_array($action, $actionList)) {
                $this->result['failDesc'] = 'the action is invalid';
                $this->response($this->result);
                exit;
            }

            $this->searchModel->formatRequestParams($requestParams);

            // var_dump($requestParams);exit;
            //设置业务类别
            $this->searchModel->setBizFlag($bizFlag);

            $this->searchModel->statistics($bizFlag);

            //执行搜索
            // var_dump($action);exit;
            $searchData = $this->searchModel->$action();
            // var_dump($searchData);exit;

            $this->result['queryRet'] = true;
            $this->result['result'] = $searchData;
            $this->result['result']['stat']['total'] = $searchData['total'];
            unset($searchData['total'], $searchData['total_found'], $searchData['time']);
            foreach ($searchData as &$item) {
                $item['id'] = $item['itemid'];
            }
            $this->result['result']['list'] = $searchData;
        }
        $this->response($this->result);
        exit;
    }
}

?>
