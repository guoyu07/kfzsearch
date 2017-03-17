<?php

/**
 * 测试 客户端接口类
 */
class Interface_TestfootprintModel extends kfz_system_SimpleRpcClient
{

    /**
     * 公司平台网站网址
     * 
     * @var array 
     */
    private $site = array();

    /**
     * 构建函数
     */
    function __construct()
    {
        $serverUrl             = Yaf\Registry::get('g_config')->site->kfzsearch . 'interface/Footprint/service';
        parent::__construct($serverUrl, 30);
    }

    public function testFootprintSearch($bizFlag, $realIp, $agent, $action, $requestParams)
    {
        $parameters = array();
        $parameters['bizFlag'] = $bizFlag;
        $parameters['realIP'] = $realIp;
        $parameters['agent'] = $agent;
        $parameters['action'] = $action;
        $parameters['requestParams'] = $requestParams;
        $result = self::call('search', $parameters);
        echo '<pre>';
        print_r($result);exit;
        if(empty($result) || !empty($result['error']) || empty($result['result']) || $result['result']['queryRet'] != 'true')
        {
            return false;
        }
        return $result['result'];
    }

}
