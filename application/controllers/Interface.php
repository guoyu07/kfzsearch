<?php

/**
 * 公开接口搜索，非rpc方式
 * @author liguizhi <liguizhi_001@163.com>
 * @date   2015年5月28日
*/ 
class InterfaceController extends Kfz_System_ControllerAbstract
{
	private $searchModel;
    
    public function init()
    {
        //执行父类的初始化方法
        parent::init();
        
    }
    
    public function suggestAction()
    {
    	error_reporting(0);
//        $logfile = DATA_LOG . 'search/suggest/' . date('Y') . '/' . 'suggest_'. date('md') .'.log';
    	$result = 'var sugWords = [];';//默认格式
    	$wordname = $this->request->get('query');//兼容老的格式，老系统里面的参数都是query为传入值
        if(strlen($wordname) > 30) {
//            Kfz_Lib_Log::writeLog($logfile, $wordname. '           []', 3);
            echo $result;
            exit;
        }
        $bizFlag        = 'suggest';
        $this->searchModel = new SuggestElasticModel($bizFlag);
        $requestParams  = array('wordname' => $wordname);
        //设置业务类别
        $this->searchModel->setBizFlag($bizFlag);
        //解析请求数组为搜索条件数组
        $this->searchModel->translateParams($requestParams);
        //执行搜索
        $searchData = $this->searchModel->getSuggestList();
        
        $callback = $this->request->get('callback');
        if(!empty($callback)) { //jsonp返回格式
            echo $callback. '('. json_encode($searchData). ')';
        } else {
            if($searchData) {
                $result = 'var sugWords=["'.implode("\",\"", $searchData).'"];';
            }
            echo $result;
        }
        exit;
        
//        Kfz_Lib_Log::writeLog($logfile, $wordname. '           [' . implode(',', $searchData) . ']', 3);
        
    }
}
