<?php

/**
 * Created by diao.
 * Date: 16-9-13
 * Time: 上午9:32
 */
class Interface_TestauctioncomModel extends Kfz_System_SimpleRpcClient
{
    public function __construct()
    {
        $serverUrl = Yaf\Registry::get('g_config')->site->kfzsearch . 'interface/Auctioncom/service';
        parent::__construct($serverUrl, 30);
    }

    public function getAuctioncomList()
    {
        $parameters['action'] = 'getAuctioncomList';
        $parameters['bizFlag'] = 'auctioncom';
        $parameters['requestParams'] = array(
            array('key' => 'itemname', 'value' => '李盛钟'),//指定拍品名称查询
            array('key' => 'order', 'value' => '8'),
            array('key' => 'pagenum', 'value' => 20),//分页页码
            array('key' => 'comid', 'value' => 1)
        );
        $result = $this->call('getAuctioncomList', $parameters);
        if (empty($result) || !empty($result['error']) || empty($result['result']) || $result['result']['queryRet'] != 'true') {
            return array();
        }
        return $result['result']['result'];
    }
}