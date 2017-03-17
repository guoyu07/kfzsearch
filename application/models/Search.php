<?php

/**
 * 搜索操作模型
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2014年12月19日16:09:10
 */
class SearchModel extends Kfz_System_ModelAbstract
{
    /**
     * 屏蔽控制等redis名柄
     */
    public static $preventMaliciousAccessCacheObj = null;
    
    /**
     * 并发、统计等redis句柄
     */
    public static $statisticsCacheObj = null;
    
    /**
     * 搜索操作模型
     */
    public function __construct()
    {

    }
    
    public function getCacheObj()
    {
        $searchConfig = Yaf\Registry::get('g_config')->search->toArray();
        if(self::$preventMaliciousAccessCacheObj == null) {
            self::$preventMaliciousAccessCacheObj = new Tool_SearchCache($searchConfig['preventMaliciousAccessCache'], 'redis', 'kfzsearch_');
            if (self::$preventMaliciousAccessCacheObj->getConnectStatus() === false) {
                self::$preventMaliciousAccessCacheObj = false;
            }
        }
        if(self::$statisticsCacheObj == null) {
            self::$statisticsCacheObj = new Tool_SearchCache($searchConfig['statisticsCache'], 'redis', 'kfzsearch_');
            if (self::$statisticsCacheObj->getConnectStatus() === false) {
                self::$statisticsCacheObj = false;
            }
        }
    }
    
    /**
     * 判断是否为爬虫
     */
    public function isSpider($agent = '')
    {
        if(!$agent) {
            return false;
        }
        if($agent == 'AbnormalAccess') {
            return true;
        }
        if(preg_match('/Baiduspider|spi_der|msnbot|Googlebot|Mediapartners-Google|Yahoo\! Slurp|360Spider|Sogou.(.*)?spider|YisouSpider|bingbot|ChinasoSpider|EtaoSpider|YandexBot/isU', $agent)) { //爬虫
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 获取用户真实IP
     */
    public function getIP()
    {
        $realip = '';
        if (isset($_SERVER)){
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
                $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $realip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $realip = $_SERVER["REMOTE_ADDR"];
            }
        } else {
            if (getenv("HTTP_X_FORWARDED_FOR")){
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } else if (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }

        return $realip;
    }
    
    /**
     * 防止恶意访问（监控,同一ip恶意抓取屏蔽并发邮件提醒）
     * 
     * 屏蔽规则：
     * 规则一：单IP情况下，如果1分钟最大次数超过800次，则直接屏蔽 （当前 ：350）
     * 规则二：单IP情况下，10分钟内单IP访问次数超过3000次，则直接屏蔽（当前 : 2000）
     *      10分钟内，超过 每分钟多于200的次数差总和 的最大值1000，则直接屏蔽
     *          如果第一次单分钟为201，则此时总和为1
     *          如果第二次单分钟为550，则此时总和为351
     *          如果第三次单分钟为750，则此时总和为901
     *          如果第四次单分钟为330，则此时总和为1031，进行屏蔽
     * 规则三：Agent头判为爬虫情况下，3分钟内最大访问次数（当前：24000）
     * 规则四：单模块屏蔽 例：kfzsearch_block_shop_interest
     * 规则五：全部搜索调用屏蔽 kfzsearch_block_all
     * 规则六：除搜索主业务外全部调用屏蔽 kfzsearch_block_all_without_main
     * 规则七：全部爬虫屏蔽 kfzsearch_block_spider
     * 规则八：单模块屏蔽爬虫 例：kfzsearch_block_shop_interest_spider
     */
    public function preventMaliciousAccess($realIP, $bizFlag, $agent)
    {
        //1分钟最大次数临界点（规则一上限）
        $cutOffTimes       = 100;
        //1分钟请求最大次数
        $maxTimes          = 30;
        //10分钟内每分钟多于$maxTimes的次数差总和最大值
        $overMaxTimesFor10 = 200;
        //封锁时间
        $blockTime         = $bizFlag == 'search' ? 10800 : 86400 * 30 * 12;
        //10分钟单IP最大访问次数（规则二上限）
        $maxAccessFor10    = $maxTimes * 10 + $overMaxTimesFor10;
        //爬虫3分钟内最大访问次数（规则三上限）
        $maxSpiderTimes    = 24000;
        //爬虫封锁时间
        $spiderBlockTime   = 60 * 1;
        //爬虫标识
        $spiderMark        = 'Baiduspider|spi_der|msnbot|Googlebot|Mediapartners-Google|Yahoo\! Slurp|360Spider|Sogou.(.*)?spider|YisouSpider|bingbot|ChinasoSpider|EtaoSpider|YandexBot';
        
        if (!empty($realIP)) {
            if($this->getCacheObj() && self::$preventMaliciousAccessCacheObj === false) {
                return false;
            }
            if(self::$preventMaliciousAccessCacheObj->exists('block_all')) { //判断是否已屏蔽所有业务模块
                return true;
            }
            if(self::$preventMaliciousAccessCacheObj->exists('block_all_without_main')) { //判断是否已屏蔽除了搜索主业务外所有业务模块
                if(!in_array($bizFlag, Conf_Sets::$mainBiz)) {
                    return true;
                }
            }
            if(self::$preventMaliciousAccessCacheObj->exists('block_'. $bizFlag)) { //判断是否为已屏蔽模块
                return true;
            }
            if(self::$preventMaliciousAccessCacheObj->exists('block_'. $realIP)) { //判断是否为已屏蔽IP
                if(self::$preventMaliciousAccessCacheObj->ttl('block_'. $realIP) == -1) {
                    self::$preventMaliciousAccessCacheObj->expire('block_'. $realIP, $blockTime);
                }
                //其它业务模块封锁不影响search模块（可选）
                if($bizFlag == 'search') {
                    $blockText = self::$preventMaliciousAccessCacheObj->get('block_'. $realIP);
                    if($blockText != 'search_maxtime' && $blockText != 'search_cutoff') {
                        goto blockIp;
                    }
                }
                //其它业务模块封锁不影响shop模块（可选）
                if($bizFlag == 'shop') {
                    $blockText = self::$preventMaliciousAccessCacheObj->get('block_'. $realIP);
                    if($blockText != 'shop_maxtime' && $blockText != 'shop_cutoff') {
                        goto blockIp;
                    }
                }
                return true;
            }
            if($this->isSpider($agent)) { //判断是否为爬虫
                if(self::$preventMaliciousAccessCacheObj->exists('block_spider')) { //判断是否已屏蔽spider_agent
                    if(self::$preventMaliciousAccessCacheObj->ttl('block_spider') == -1) {
                        self::$preventMaliciousAccessCacheObj->expire('block_spider', $spiderBlockTime);
                    }
                    return true;
                }
                if(self::$preventMaliciousAccessCacheObj->exists('block_'. $bizFlag. '_spider')) { //判断该业务模块是否已屏蔽spider_agent
                    if(self::$preventMaliciousAccessCacheObj->ttl('block_'. $bizFlag. '_spider') == -1) {
                        self::$preventMaliciousAccessCacheObj->expire('block_'. $bizFlag. '_spider', $spiderBlockTime);
                    }
                    return true;
                }
                $spiderKey = 'spider_agent';
                $spiderCount = self::$preventMaliciousAccessCacheObj->incr($spiderKey);
                $spiderTtl = self::$preventMaliciousAccessCacheObj->ttl($spiderKey);
                if($spiderTtl == -1) { //如果未设置过期时间则设置60*3秒
                    self::$preventMaliciousAccessCacheObj->expire($spiderKey, 60 * 3);
                }
                if($spiderCount == 1) { //第一次访问，设置过期时间60*3秒
                    self::$preventMaliciousAccessCacheObj->expire($spiderKey, 60 * 3);
                }
                if($spiderCount > $maxSpiderTimes) {
                    self::$preventMaliciousAccessCacheObj->set('block_spider', $spiderMark, $spiderBlockTime);
                    Kfz_Lib_Mail::sendMailBySmtp('zhangxinde@kongfz.com', '张新德', '【SpiderBlock】搜索频繁调用报警【' . date('Y-m-d H:i:s', time()) . '】', "爬虫3分钟内搜索超过{$maxSpiderTimes}次，已屏蔽，屏蔽时间为{$spiderBlockTime}秒。屏蔽爬虫标识为{$spiderMark}，封锁时IP:{$realIP}，BizFlag:{$bizFlag}，Agent:{$agent}。");
                    return true;
                }
            }
blockIp:            
            $count = self::$preventMaliciousAccessCacheObj->incr($realIP);
            $ttl = self::$preventMaliciousAccessCacheObj->ttl($realIP);
            if($ttl == -1) { //如果未设置过期时间则设置60秒
                self::$preventMaliciousAccessCacheObj->expire($realIP, 60);
            }
            if ($count == 1) { //第一次访问，设置过期时间60秒
                self::$preventMaliciousAccessCacheObj->expire($realIP, 60);
            }
            if($count > $cutOffTimes) { //如果1分钟超过最大次数临界点，直接封锁
                self::$preventMaliciousAccessCacheObj->set('block_'. $realIP, $bizFlag. '_cutoff', $blockTime);
                Kfz_Lib_Mail::sendMailBySmtp('zhangxinde@kongfz.com', '张新德', '【CutOff】搜索频繁调用报警【' . date('Y-m-d H:i:s', time()) . '】', "IP:{$realIP}，BizFlag:{$bizFlag}，Agent:{$agent}，1分钟内搜索超过{$cutOffTimes}次，已屏蔽。");
                return true;
            }
            if ($count > $maxTimes) { //如果1分钟超过最大次数
                $alarm = self::$preventMaliciousAccessCacheObj->incr('alarm_' . $realIP);
                if(self::$preventMaliciousAccessCacheObj->ttl('alarm_'. $realIP) == -1) {
                    self::$preventMaliciousAccessCacheObj->expire('alarm_'. $realIP, 600);
                }
                if($alarm == 1) {
                    self::$preventMaliciousAccessCacheObj->expire('alarm_'. $realIP, 600);
                }
                if($alarm >= $overMaxTimesFor10) {
                    self::$preventMaliciousAccessCacheObj->set('block_'. $realIP, $bizFlag. '_maxtime', $blockTime);
                    Kfz_Lib_Mail::sendMailBySmtp('zhangxinde@kongfz.com', '张新德', '【MaxTime】搜索频繁调用报警【' . date('Y-m-d H:i:s', time()) . '】', "IP:{$realIP}，BizFlag:{$bizFlag}，Agent:{$agent}，10分钟内单IP访问次数超过{$maxAccessFor10}次，已屏蔽。");
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * 接口统计
     */
    public function statistics($bizFlag)
    {
        if($this->getCacheObj() && self::$statisticsCacheObj === false) {
            return false;
        }

        //统计总量
        $dateStr = date('Ymd');
        $cacheTime = 86400 * 365;
        $key = $dateStr. '_'. $bizFlag;
        if(self::$statisticsCacheObj->exists($key)) {
            self::$statisticsCacheObj->incr($key);
        } else {
            self::$statisticsCacheObj->set($key, 1, $cacheTime);
        }
        
        return true;
    }
    
    /**
     * 并发统计
     */
    public function runtimeState($bizFlag, $isOver)
    {
        if($this->getCacheObj() && self::$statisticsCacheObj === false) {
            return false;
        }
        
        //统计并发
        $runTimeKey = 'runtime_'. $bizFlag;
        $runTimeNum = self::$statisticsCacheObj->get($runTimeKey);
        if($isOver) {
            if($runTimeNum > 1) {
                self::$statisticsCacheObj->decr($runTimeKey);
            }
        } else {
            self::$statisticsCacheObj->incr($runTimeKey);
        }
        
        //统计并发
        $runTimeAllKey = 'runtime_all';
        $runTimeAllNum = self::$statisticsCacheObj->get($runTimeAllKey);
        if($isOver) {
            if($runTimeAllNum > 1) {
                self::$statisticsCacheObj->decr($runTimeAllKey);
            }
        } else {
            self::$statisticsCacheObj->incr($runTimeAllKey);
        }
        
    }
    
    /**
     * 降级与分流限流控制层（主要应用于商品搜索服务）
     * 
     * @return int 4为降级 ，3为限流 ，2为分流 ，1为截流 ，-1为不限流
     */
    public function limitFlow($bizFlag)
    {
        if($this->getCacheObj() && (self::$statisticsCacheObj === false || self::$preventMaliciousAccessCacheObj === false)) {
            return -1;
        }
        if(!isset(Conf_Sets::$bizChannel)) {
            return -1;
        }
        if(!isset(Conf_Sets::$bizChannel[$bizFlag])) {
            return -1;
        }
        $bizSets = Conf_Sets::$bizChannel[$bizFlag];
        if(!isset($bizSets['channelLevel']) || !isset($bizSets['limitFlow']) || !isset($bizSets['maxFlow'])) {
            return -1;
        }
        
        $runTimeKey = 'runtime_'. $bizFlag;
        if($bizSets['channelLevel'] == 1) { //一级通道、核心业务、分流 + 限流
            $bf = self::$statisticsCacheObj->get($runTimeKey);
            if($bf > $bizSets['maxFlow']) { //限流
                return 3;
            } else {
                if($bf > $bizSets['limitFlow']) { //分流
                    if(self::$preventMaliciousAccessCacheObj->exists('block_all_without_main_auto')) { //如果有自动设置屏蔽除主业务外所有模块标识
                        self::$preventMaliciousAccessCacheObj->set('block_all_without_main', 1, 5);
                        self::$preventMaliciousAccessCacheObj->del('block_all_without_main_auto');
                    } else {
                        self::$preventMaliciousAccessCacheObj->set('block_all_without_main_auto', 1, 5);
                    }
                    
                    return 2;
                }
                if(isset($bizSets['demote']) && $bf > $bizSets['demote']) {
                    return 4;
                }
            }
            
        } elseif ($bizSets['channelLevel'] == 2) { //二级通道、次级业务、限流
            $bf = self::$statisticsCacheObj->get($runTimeKey);
            if($bf > $bizSets['limitFlow']) { //限流
                if(self::$preventMaliciousAccessCacheObj->exists('block_'. $bizFlag. '_spider_auto')) { //如果有自动屏蔽该业务爬虫标识
                    if($bf > $bizSets['maxFlow']) { //只有大于最大流量时才会执行限流
                        $ttl = self::$preventMaliciousAccessCacheObj->ttl('block_'. $bizFlag. '_spider_auto');
                        if($ttl > 0 && $ttl < 10) { //(N)s后并发还没降下来，执行限流操作
                            return 3;
                        }
                    }
                } else { //执行截流操作（即封锁爬虫）
                    self::$preventMaliciousAccessCacheObj->set('block_'. $bizFlag. '_spider_auto', 1, 30);
                    self::$preventMaliciousAccessCacheObj->set('block_'. $bizFlag. '_spider', 1, 20);
                    return 1;
                }
            }
        } elseif ($bizSets['channelLevel'] == 3) { //三级通道、可暂时停止业务、限流
            $bf = self::$statisticsCacheObj->get($runTimeKey);
            if($bf > $bizSets['limitFlow']) { //限流
                return 3;
            }
        }
        
    }

    /**
     * 字符串转化为unicode码
     * 
     * @param string $str
     * @return string
     */
    public function str2unicode($str)
    {
        if (!$str) {
            return '';
        }
        $unicode = '';
        $str = '' . $str;
        $len = mb_strlen($str, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $temp = '';
            $s = mb_substr($str, $i, 1, 'UTF-8');
            $strlen = strlen($s);
            if ($strlen === 1) {
                $temp = sprintf("\\u%x", ord($s[0]));
            } elseif ($strlen === 2) {
                $temp = sprintf("\\u%x", ((ord($s[0]) - 192) << 6) + (ord($s[1]) - 128));
            } elseif ($strlen === 3) {
                $temp = sprintf("\\u%x", ((ord($s[0]) - 224) << 12) + ((ord($s[1]) - 128) << 6) + (ord($s[2]) - 128));
            } elseif ($strlen === 4) {
                $temp = sprintf("\\u%x", ((ord($s[0]) - 240) << 18) + ((ord($s[1]) - 128) << 12) + ((ord($s[2]) - 128) << 6) + (ord($s[3]) - 128));
            } elseif ($strlen === 5) {
                $temp = sprintf("\\u%x", ((ord($s[0]) - 248) << 24) + ((ord($s[1]) - 128) << 18) + ((ord($s[2]) - 128) << 12) + ((ord($s[3]) - 128) << 6) + (ord($s[3]) - 128));
            } elseif ($strlen === 6) {
                $temp = sprintf("\\u%x", ((ord($s[0]) - 252) << 30) + ((ord($s[1]) - 128) << 24) + ((ord($s[2]) - 128) << 18) + ((ord($s[3]) - 128) << 12) + ((ord($s[4]) - 128) << 6) + (ord($s[5]) - 128));
            }
            $unicode .= $temp;
        }
        return trim(str_replace('\u', 'k', $unicode), '"');
    }

    /**
     * unicode码转化为字符串
     * 
     * @param string $str
     * @return string
     */
    public function unicode2str($unicode)
    {
        if (!$unicode) {
            return '';
        }
        $arr = explode('k', $unicode);
        $str = '';
        foreach ($arr as &$v) {
            if (!$v) {
                continue;
            }
            $v = str_pad($v, 4, '0', STR_PAD_LEFT);
        }
        $name = strtolower(implode('\u', $arr));
        $pattern = '/([\w]+)|(\\\u([\w]{4}))/i';
        preg_match_all($pattern, $name, $matches);
        if (!empty($matches)) {
            $name = '';
            for ($j = 0; $j < count($matches[0]); $j++) {
                $str = $matches[0][$j];
                if (strpos($str, '\\u') === 0) {
                    $code = base_convert(substr($str, 2, 2), 16, 10);
                    $code2 = base_convert(substr($str, 4), 16, 10);
                    $c = chr($code) . chr($code2);
                    $c = iconv('UCS-2BE', 'UTF-8', $c);
                    $name .= $c;
                } else {
                    $name .= $str;
                }
            }
        }
        return $name;
    }
    
    /**
     * 把一个字符串转换为一个64位的十进制整数，注意只能在64位平台运行，32位平台返回科学计数法形式5.3615184559484E+18
     */
    public function fnv64($value)
    {
        if (empty($value)) {
            return 0;
        }
        $b128s = md5($value);
        $b64s = substr($b128s, 0, 14); // 用7个字节，因为sphinx不支持uint64 
        $b64 = hexdec($b64s);        // 把一个十六进制的64位整数转换为十进制的64位整数
        return $b64;
    }
    
    /**
     * 格式化查询OR
     * 
     * 将查询     外军 OR 军区 OR 部队 OR 英烈 OR 英雄
     * 格式化为  (外军) | (军区) | (部队) | (英烈) | (英雄)
     * 
     * @param string $queryStr
     * @return string
     */
    public function makeOr($queryStr)
    {
        if(strpos($queryStr, ' OR ') === false) {
            return $queryStr;
        }
        $queryArr = explode(' OR ', $queryStr);
        $returnStr = '';
        foreach($queryArr as $key) {
            $returnStr .= '('. $key. ') | ';
        }
        $returnStr = trim($returnStr, ' | ');
        return $returnStr;
    }
    
    /**
     * 取得大图的路径
     * 
     * @param      string    $imageUrl     图片路径
     * @return     string    $bigImageUrl  原图路径
     */
    public function getBigImageUrl($imageUrl)
    {
        $bigImageUrl = '';
        if (!empty($imageUrl)) {
            $bigImageUrl = preg_replace("/_[bns]\.$|_[bns]$|(_[bns])?\.(jp(e)?g|gif|png)$/i", '', $imageUrl) . B_JPG;
        }
        return $bigImageUrl;
    }

    /**
     * 取得中图的路径
     * 
     * @param      string    $imageUrl        图片路径
     * @return     string    $normalImageUrl  原图路径
     */
    public function getNormalImageUrl($imageUrl)
    {
        $normalImageUrl = '';
        if (!empty($imageUrl)) {
            $normalImageUrl = preg_replace("/_[bns]\.$|_[bns]$|(_[bns])?\.(jp(e)?g|gif|png)$/i", '', $imageUrl) . N_JPG;
        }
        return $normalImageUrl;
    }

    /**
     * 取得小图的路径
     * 
     * @param      string    $imageUrl       图片路径
     * @return     string    $smallImageUrl  原图路径
     */
    public function getSmallImageUrl($imageUrl)
    {
        $smallImageUrl = '';
        if (!empty($imageUrl)) {
            $smallImageUrl = preg_replace("/_[bns]\.$|_[bns]$|(_[bns])?\.(jp(e)?g|gif|png)$/i", '', $imageUrl) . S_JPG;
        }
        return $smallImageUrl;
    }
    
    /**
     * 获取品相
     * 
     * @param int $quality
     * @return string
     */
    public function getQualityName($quality)
    {
        $qualityArr = array
        (
            '100' => '十品',
            '95' => '九五品',
            '90' => '九品',
            '85' => '八五品',
            '80' => '八品',
            '75' => '七五品',
            '70' => '七品',
            '65' => '六五品',
            '60' => '六品',
            '50' => '五品',
            '40' => '四品',
            '30' => '三品',
            '20' => '二品',
            '10' => '一品'
        );
        return array_key_exists($quality, $qualityArr) ? $qualityArr[$quality] : '不详';
    }
    
    /**
     * 转换出版时间为页面显示
     * 
     * @param int $pubDate
     * @return string
     */
    public function pubDate($pubDate, $type = 0)
    {
        $pubDate = strval($pubDate);
        $returnPubDate = '';
        if ($pubDate == 0 || $pubDate == '29991231') {
            return '0000-00-00';
        }
        if (strlen($pubDate) == 8 || strlen($pubDate) == 6) {
            $returnPubDate = substr($pubDate, 0, 4);
            if (substr($pubDate, 4, 2) && substr($pubDate, 4, 2) != '00') {
                if ($type == 1) {
                    $returnPubDate .= '年' . substr($pubDate, 4, 2) . '月';
                } else {
                    $returnPubDate .= '-' . substr($pubDate, 4, 2);
                }
            }
            if (substr($pubDate, 6, 2) && substr($pubDate, 6, 2) != '00') {
                if ($type == 1) {
                    $returnPubDate .= substr($pubDate, 6, 2) . '日';
                } else {
                    $returnPubDate .= '-' . substr($pubDate, 6, 2);
                }
            }
        }
        return $returnPubDate;
    }
    
    /**
     * 根据父类分类信息获取其所有子分类ID
     * 
     * @param type $catInfo
     * @param int  $isV    是否是虚拟分类
     * @return type
     */
    public function getChildCatIds($catInfo, $isV)
    {
        $catIds = array();
        $type = $isV ? 'Data_ItemVCategory' : 'Data_ItemCategory';
        $tmpArr = $type::getChildren($catInfo['id'], $catInfo['level']);
        if ($tmpArr) {
            foreach ($tmpArr as $arr) {
                $catIds[] = $arr['id'];
            }
        }
        return $catIds;
    }
    
    /**
     * 将搜索返回的数据key转换成数据库大小写形式
     * 
     * @param array $item
     * @return array
     */
    public function turnLetterCase($item)
    {
        $newItem = array();
        $oldKeys = array('id', 'biztype', 'userid', 'catid', 'itemname', 'pubdate', 'addtime', 'imgurl', 'nickname', 'shopname', 'shopid', 'shopstatus', 'isdeleted', 'salestatus', 'certifystatus', 'olreceivetype', 'updatetime');
        $newKeys = array('itemId', 'bizType', 'userId', 'catId', 'itemName', 'pubDate', 'addTime', 'imgUrl', 'nickName', 'shopName', 'shopId', 'shopStatus', 'isDeleted', 'saleStatus', 'certifyStatus', 'olReceiveType', 'updateTime');
        foreach ($item as $k => $v) {
            if (in_array($k, $oldKeys)) {
                $pos = array_search($k, $oldKeys);
                $key = $newKeys[$pos];
                $newItem[$key] = $v;
            } else {
                $newItem[$k] = $v;
            }
        }
        return $newItem;
    }
    
    /**
     * 根据年代ID获得年代名称
     * 
     * @param int $id
     * @return string
     */
    public function getYearsById($id)
    {
        $yearsArr = array(
            10 => '建国后',
            11 => '民国',
            12 => '清代',
            13 => '明代',
            14 => '宋元及以前',
            15 => '不详',
            90 => '清代',
            91 => '民国',
            92 => '新中国早期',
            93 => '文革',
            94 => '70年代',
            95 => '80年代',
            96 => '90年代',
            97 => '2000年之后',
            98 => '不详',
            100 => '古代',
            101 => '近现代',
            102 => '现代',
            103 => '不详',
            110 => '清朝',
            111 => '民国',
            112 => '新中国时期',
            113 => '文革',
            114 => '现代',
            115 => '其它'
        );
        if (!array_key_exists($id, $yearsArr)) {
            return '';
        } else {
            return $yearsArr[$id];
        }
    }
    
    /**
     * 获得分类对应拼音数组
     * 
     * @param int $type
     * @return array
     */
    public function getnum2catArr($type = 0)
    {
        $num2catArr = array(
            '8' => 'xianzhuang',
            '9' => 'minguo',
            '21' => 'moji',
            '37' => 'zihua',
            '10' => 'qikan',
            '41' => 'baozhi',
            '6' => 'waiwenshu',
            '32' => 'jiaocai',
            '43' => 'xiaoshuo',
            '1' => 'wenxue',
            '3' => 'lishi',
            '23' => 'dili',
            '5' => 'falv',
            '24' => 'junshi',
            '14' => 'jingji',
            '25' => 'guanli',
            '4' => 'yishu',
            '26' => 'shenghuo',
            '27' => 'shaoer',
            '7' => 'shwh',
            '28' => 'jiaoyu',
            '13' => 'yuyan',
            '44' => 'zhexue',
            '29' => 'zongjiao',
            '18' => 'zhengzhi',
            '19' => 'tiyu',
            '11' => 'jishu',
            '15' => 'kexue',
            '17' => 'yiyao',
            '31' => 'jisuanji',
            '16' => 'gongjushu',
            '20' => 'zonghe',
            '12' => 'guoxue',
            '34' => 'hswx',
            '55' => 'ditu',
            '35' => 'lianhuanhua',
            '56' => 'banhua',
            '36' => 'youpiao',
            '46' => 'qianbi',
            '57' => 'beitieyinpu',
            '38' => 'zhaopian',
            '58' => 'zaxiang',
        );
        $num2vcatArr = array(
            '101' => 'xiaoshuo',
            '102' => 'wenxue',
            '103' => 'lishi',
            '104' => 'dili',
            '105' => 'falv',
            '106' => 'junshi',
            '107' => 'jingji',
            '108' => 'guanli',
            '109' => 'yishu',
            '110' => 'shenghuo',
            '111' => 'shaoer',
            '112' => 'shwh',
            '113' => 'jiaoyu',
            '114' => 'yuyan',
            '115' => 'zhexue',
            '116' => 'zongjiao',
            '117' => 'zhengzhi',
            '118' => 'tiyu',
            '119' => 'jishu',
            '120' => 'kexue',
            '121' => 'yiyao',
            '122' => 'gongjushu'
        );
        if($type === 0) {
            return $num2catArr;
        } elseif ($type === 1) {
            return array_flip($num2catArr);
        } elseif ($type === 2) {
            return $num2vcatArr;
        } elseif ($type === 3) {
            return array_flip($num2vcatArr);
        }
    }
    
    // 此方法依赖于mbstring扩展。
    public function fan2jian($value)
    {
        $Unihan = Data_Unihan::get();
        
        if($value === '') return '';
        $r = '';
        $len = mb_strlen($value,'UTF-8'); 
        for($i=0; $i<$len; $i++){
            $c = mb_substr($value,$i,1,'UTF-8');
            if(isset($Unihan[$c])) $c = $Unihan[$c];
            $r .= $c;
        }
        
        return $r;
    }
    
    /**
     * 检测系统负载
     * 
     * @param string $ip
     * @param int    $port
     * @param int    $maxLoad
     * @return boolean
     */
    public function checkLoad($ip, $port, $maxLoad)
    {
        $loadInfo = ElasticSearchModel::getLoadInfo($ip, $port);
        $loadInfoArr = explode(' ', trim($loadInfo));
        if(is_array($loadInfoArr) && !empty($loadInfoArr)) {
            foreach($loadInfoArr as $info) {
                $load = trim($info);
                if($load > $maxLoad) {
                    return false;
                }
            }
        }
        return true;
    }

}
?>