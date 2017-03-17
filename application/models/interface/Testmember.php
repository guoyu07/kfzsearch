<?php

/**
 * 测试 客户端接口类
 */
class Interface_TestmemberModel extends kfz_system_SimpleRpcClient
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
        $serverUrl             = Yaf\Registry::get('g_config')->site->kfzsearch . 'interface/Member/service';
        parent::__construct($serverUrl, 30);
    }

    public function testMemberSearch($bizFlag, $realIp, $agent, $requestParams, $action, $isFormatData, $isFuzzySearch, $isBuildSnippets, $otherParams)
    {
        $parameters = array();
        $parameters['bizFlag'] = $bizFlag;
        $parameters['realIp'] = $realIp;
        $parameters['agent'] = $agent;
        $parameters['requestParams'] = $requestParams;
        $parameters['otherParams'] = $otherParams;
        $parameters['isFormatData'] = $isFormatData;
        $parameters['isFuzzySearch'] = $isFuzzySearch;
        $parameters['isBuildSnippets'] = $isBuildSnippets;
        $parameters['action'] = $action;
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
