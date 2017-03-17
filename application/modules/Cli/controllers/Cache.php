<?php

/**
 * 缓存处理脚本
 */
class CacheController extends CliController
{

    /**
     * 执行父类的初始化方法
     */
    public function init()
    {
        parent::init();
    }

    /**
     * 删除指定的
     * php /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/cache/delNeverExpiresProductSearchKey/'
     */
    public function delNeverExpiresProductSearchKeyAction()
    {
        $logName   = 'delNeverExpiresProductSearch_'. date("Y_m_d");
        $logDir    = CLI_RUNTIME_LOG . 'cache';
        $logfile   = $logDir. '/'. $logName. '.log';
        is_dir($logDir) || mkdir($logDir, 0755, true);
        
        Kfz_Lib_Log::writeLog($logfile, "--------------- Start [". date("Y-m-d H:i:s"). "] ---------------", 3);
        $searchConfig = Yaf\Registry::get('g_config')->search->toArray();
        $cacheServers = $searchConfig['productCache'];
        $cache = new Tool_CacheRedis($cacheServers);
        $result = $cache->keys('productSearch_');
        if(empty($result)) {
            Kfz_Lib_Log::writeLog($logfile, "没有找到任何key !!!", 3);
            exit;
        }
        foreach($result as $key) {
            if($cache->exists($key)) {
                if($cache->ttl($key) == '-1') {
                    Kfz_Lib_Log::writeLog($logfile, "key : ". $key, 3);
                    $cache->delete($key);
                }
            }
        }
        
        $result2 = $cache->keys('app_');
        if(empty($result2)) {
            Kfz_Lib_Log::writeLog($logfile, "没有找到任何key !!!", 3);
            exit;
        }
        foreach($result2 as $key) {
            if($cache->exists($key)) {
                if($cache->ttl($key) == '-1') {
                    Kfz_Lib_Log::writeLog($logfile, "key : ". $key, 3);
                    $cache->delete($key);
                }
            }
        }
        Kfz_Lib_Log::writeLog($logfile, "--------------- End [". date("Y-m-d H:i:s"). "] ---------------", 3);
        exit;
    }
    
}
