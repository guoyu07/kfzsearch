<?php

/**
 * 搜索配置信息
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2015年1月28日13:59:50
 */
class Conf_Sets
{
    /**
     * 搜索核心业务（1级通道）
     */
    public static $mainBiz = array(
        'search'
    );
    
    /**
     * 业务通道设置（限流分流）
     * 
     * channelLevel       业务通道等级 1、2、3
     * limitFlow          限流（每分钟）
     * maxFlow            超过最大流量后的请求自动拒绝（每分钟）
     */
    public static $bizChannel = array(
        'search' => array(
            'channelLevel' => 1,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
        'search_shop' => array(
            'channelLevel' => 2,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
        'search_book' => array(
            'channelLevel' => 2,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
        'msearch_book' => array(
            'channelLevel' => 2,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
        'shop_interest' => array(
            'channelLevel' => 3,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
        'bq' => array(
            'channelLevel' => 3,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
        'mbq' => array(
            'channelLevel' => 3,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
        'item404' => array(
            'channelLevel' => 3,
            'limitFlow' => 1000,
            'maxFlow' => 2000,
        ),
    );
    
    /**
     * 业务引擎等信息
     * 
     * engine             搜索引擎 sphinx、elastic、man_Sph_spider_ES、man_ES_spider_Sph、man_ES_spider_none、none
     * unlimit            是否为不受限业务
     */
    public static $bizSets = array(
        'bq' => array( //标签（线上）
            'engine' => 'elastic',
        ),
        'script' => array( //脚本（线上）
            'engine' => 'elastic'
        ),
        'msite' => array( //M站（线上）
            'engine' => 'elastic'
        ),
        'booklib' => array( //图书资料库及www域作家专题出版社专题（线上）
            'engine' => 'elastic',
        ),
        'product_isbnv2' => array( //ISBN图书库调用商品搜索
            'engine' => 'elastic',
        ),
        'search' => array( //oldshop下search域访问（线上）
            'engine' => 'elastic',
        ),
        'search_other' => array( //oldshop下search域访问（线上）
            'engine' => 'elastic',
        ),
        'search_shop' => array( //oldshop下shop域访问（线上）
            'engine' => 'elastic',
        ),
        'search_book' => array( //oldshop下book域访问[分类浏览]（线上）
            'engine' => 'elastic',
        ),
        'search_book_z' => array( //oldshop下book域访问[book/author转过来的]（线上）
            'engine' => 'elastic',
        ),
        'app' => array( //APP调用（线上）
            'engine' => 'elastic',
        ),
        'shop' => array( //shop（线上）
            'engine' => 'elastic',
        ),
        'shop_interest' => array( //shop感兴趣图书（线上）
            'engine' => 'elastic',
        ),
        'shop_interest_full' => array( //shop感兴趣图书，全字段（线上）
            'engine' => 'elastic',
        ),
        'pm_shop_interest' => array( //拍卖区shop感兴趣图书（线上）
            'engine' => 'elastic',
        ),
        'shop_interest_script' => array( //shop感兴趣图书[脚本]（线上）
            'engine' => 'elastic',
        ),
        'zdnet' => array( //站群（线上）
            'engine' => 'elastic',
        ),
        'rss' => array( //订阅（线上）
            'engine' => 'elastic',
        ),
        'book' => array(
            'engine' => 'sphinx',
        ),
        'verify' => array( //审核
            'engine' => 'elastic',
        ),
        'verifylog' => array( //审核日志
            'engine' => 'elastic',
        ),
        'test' => array(
            'engine' => 'sphinx'
        ),
        'auctioncom' => array( //拍卖公司联盟
            'engine' => 'sphinx'
        ),
        'suggest' => array( //搜索建议（未使用此配置）
            'engine' => 'sphinx'
        ),
        'endauction' => array( //历史拍卖搜索
            'engine' => 'elastic'
        ),
        'member' => array( //会员搜索（选项只有elastic、none） 
            'engine' => 'elastic'
        ),
        'message' => array( //消息搜索（未使用此配置）
            'engine' => 'elastic'
        ),
        'mshop' => array( //mshop（线上）
            'engine' => 'elastic',
        ),
        'msearch' => array( //oldshop下msearch域访问（线上）
            'engine' => 'elastic',
        ),
        'msearch_shop' => array( //oldshop下mshop域访问（线上）
            'engine' => 'elastic',
        ),
        'msearch_book' => array( //oldshop下mbook域访问[分类浏览]（线上）
            'engine' => 'elastic',
        ),
        'mbook' => array( //mbook（线上）
            'engine' => 'elastic',
        ),
        'mbq' => array( //mbq（线上）
            'engine' => 'elastic',
        ),
        'mwww' => array( //mwww（线上）
            'engine' => 'elastic',
        ),
        'search_myRecommend' => array( //搜索推荐（线上）
            'engine' => 'elastic',
        ),
        'item404' => array( //404页面搜索调用（线上）
            'engine' => 'elastic',
        ),
        'study' => array( //书房搜索
            'engine' => 'elastic',
        ),
    );
    
    /*
     * 各业务sphinx相应配置信息
     * 
     * index              用户访问索引名
     * spiderIndex        爬虫访问索引名
     * isMakeOr           是否支持OR查询
     * pageSize           默认的每页数量
     * maxPageNum         正常情况下，允许的最大分页数
     * otherMaxPageNum    在用户点击获取更多后，允许的最大分页数
     * maxMatch           在使用sphinx情况下有maxMatch限制，最大10000。根据最大页码及每页数量做调整
     * otherMaxMatch      在使用sphinx情况下有maxMatch限制，最大10000。在用户点击获取更多后，根据最大页码及每页数量做调整
     * cacheKeyFix        定义缓存前缀以便区分业务
     * cacheType          定义缓存使用类型，目前支持ssdb/redis/memcached
     * cacheName          可以单独指定application.ini中的缓存名称，如：productCache
     * spiderCacheKeyFix  [爬虫]定义缓存前缀以便区分业务
     * spiderCacheType    [爬虫]定义爬虫缓存使用类型，目前支持ssdb/redis/memcached
     * spiderCacheName    [爬虫]可以单独指定application.ini中的缓存名称，如：productCache
     * forceSpider        是否强制走爬虫通道
     */
    public static $bizSphinxSets = array(
        'bq' => array( //标签
            'index' => 'product',
//            'spiderIndex' => 'seoproduct',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'bq',
            'cacheType' => 'ssdb',
            'cacheName' => 'bqSsdbCache'
        ),
        'script' => array( //脚本
            'index' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'script_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'msite' => array( //M站
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'msite',
            'cacheType' => 'ssdb',
            'cacheName' => 'msiteSsdbCache'
        ),
        'booklib' => array( //图书资料库及www域作家专题出版社专题
            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 50,
            'maxMatch' => 2500,
            'otherMaxMatch' => 2500,
            'cacheKeyFix' => 'booklibSearch_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'shop' => array(
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'redis'
        ),
        'shop_interest' => array( //shop感兴趣图书
//            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'pm_shop_interest' => array( //拍卖区shop感兴趣图书
//            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'pm_shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'shop_interest_script' => array( //shop感兴趣图书[脚本]
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'zdnet' => array( //站群
            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'zdnet_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'book' => array(
            'index' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'book_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'search' => array( //oldshop下search域访问
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis'
        ),
        'search_other' => array( //oldshop下search域访问
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis'
        ),
        'search_shop' => array( //oldshop下shop域访问（线上）
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'search_product',
            'cacheType' => 'redis',
            'cacheName' => 'productCache',
            'spiderCacheKeyFix' => 'search_product',
            'spiderCacheType' => 'ssdb',
            'spiderCacheName' => 'ssdbCache'
        ),
        'search_book' => array( //oldshop下book域访问
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis',
            'cacheName' => 'productCache',
            'spiderCacheKeyFix' => 'productSearch_',
            'spiderCacheType' => 'ssdb',
            'spiderCacheName' => 'ssdbCache'
        ),
        'search_book_z' => array( //oldshop下book域访问
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis',
            'cacheName' => 'productCache',
            'spiderCacheKeyFix' => 'productSearch_',
            'spiderCacheType' => 'ssdb',
            'spiderCacheName' => 'ssdbCache'
        ),
        'app' => array( //APP调用
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'app_',
            'cacheType' => 'redis'
        ),
        'rss' => array( //订阅
            'index' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis'
        ),
        'verify' => array(
            'index' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'verify',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'auctioncom' => array(
            'index' => 'auctioncom',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'auctioncom',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'suggest' => array(
            'index' => 'suggest',
            'isMakeOr' => '0',
            'pageSize' => 10,
            'maxPageNum' => 10,
            'otherMaxPageNum' => 20,
            'maxMatch' => 10,
            'otherMaxMatch' => 100,
            'cacheKeyFix' => 'suggest',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'endauction' => array(
            'index' => 'endauction',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'endauction',
            'cacheType' => 'redis',
            // 'cacheName' => 'ssdbCache'
        ),
        'test' => array(
            'index' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 20,
            'maxPageNum' => 10,
            'otherMaxPageNum' => 20,
            'maxMatch' => 200,
            'otherMaxMatch' => 400,
            'cacheKeyFix' => 'test_',
            'cacheType' => 'redis'
        ),
        'member' => array(
            'index' => 'member',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'member',
            'cacheType' => 'redis',
            // 'cacheName' => 'ssdbCache'
        )
    );
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /*
     * 各业务elastic相应配置信息
     * 
     * index              用户访问索引名
     * spiderIndex        爬虫访问索引名
     * pageSize           默认的每页数量
     * maxPageNum         正常情况下，允许的最大分页数
     * otherMaxPageNum    在用户点击获取更多后，允许的最大分页数
     * maxMatch           在使用sphinx情况下有maxMatch限制，最大10000。根据最大页码及每页数量做调整
     * otherMaxMatch      在使用sphinx情况下有maxMatch限制，最大10000。在用户点击获取更多后，根据最大页码及每页数量做调整
     * cacheKeyFix        定义缓存前缀以便区分业务
     * cacheType          定义缓存使用类型，目前支持ssdb/redis/memcached
     * cacheName          可以单独指定application.ini中的缓存名称，如：productCache
     * spiderCacheKeyFix  [爬虫]定义缓存前缀以便区分业务
     * spiderCacheType    [爬虫]定义爬虫缓存使用类型，目前支持ssdb/redis/memcached
     * spiderCacheName    [爬虫]可以单独指定application.ini中的缓存名称，如：productCache
     * forceSpider        是否强制走爬虫通道
     */
    public static $bizElasticSets = array(
        'bq' => array( //标签
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'bq',
            'cacheType' => 'ssdb',
            'cacheName' => 'bqSsdbCache'
        ),
        'script' => array( //脚本
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'script_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'msite' => array( //M站
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'msite',
            'cacheType' => 'ssdb',
            'cacheName' => 'msiteSsdbCache'
        ),
        'booklib' => array( ////图书资料库及www域作家专题出版社专题
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 50,
            'maxMatch' => 2500,
            'otherMaxMatch' => 2500,
            'cacheKeyFix' => 'booklibSearch_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'product_isbnv2' => array( //ISBN图书库调用商品搜索
//            'forceSpider' => 1, //强制走爬虫通道
//            'index' => 'product',
//            'spiderIndex' => 'seoproduct',
            'index' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 50,
            'maxMatch' => 2500,
            'otherMaxMatch' => 2500,
            'cacheKeyFix' => 'booklibSearch_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'shop' => array(
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'redis'
        ),
        'shop_interest' => array( //shop感兴趣图书
//            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'shop_interest_full' => array( //shop感兴趣图书，全字段
//            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'pm_shop_interest' => array( //拍卖区shop感兴趣图书
//            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'pm_shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'shop_interest_script' => array( //shop感兴趣图书[脚本]
            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'zdnet' => array( //站群
            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'zdnet_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'book' => array(
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'book_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'search' => array( //oldshop下search域访问
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis'
        ),
        'search_other' => array( //oldshop下search域访问
            'index' => 'product',
            'spiderIndex' => 'product',
            'isMakeOr' => '0',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis'
        ),
        'search_shop' => array( //oldshop下shop域访问
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'search_product',
            'cacheType' => 'redis',
            'cacheName' => 'productCache',
            'spiderCacheKeyFix' => 'search_product',
            'spiderCacheType' => 'ssdb',
            'spiderCacheName' => 'ssdbCache'
        ),
        'search_book' => array( //oldshop下book域访问
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis',
            'cacheName' => 'productCache',
            'spiderCacheKeyFix' => 'productSearch_',
            'spiderCacheType' => 'ssdb',
            'spiderCacheName' => 'ssdbCache'
        ),
        'search_book_z' => array( //oldshop下book域访问
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'app' => array( //APP调用
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'app_',
            'cacheType' => 'redis'
        ),
        'rss' => array( //订阅
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis'
        ),
        'verify' => array(
            'index' => 'product',
        ),
        'verifylog' => array(
            'index' => 'verifylog',
        ),
        'suggest' => array(
            'index' => 'suggest',
            'pageSize' => 10,
            'maxPageNum' => 10,
            'otherMaxPageNum' => 20,
            'maxMatch' => 10,
            'otherMaxMatch' => 100,
            'cacheKeyFix' => 'suggest',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
        'endauction' => array(
            'index' => 'endauction',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'endauction',
            'cacheType' => 'redis',
            // 'cacheName' => 'ssdbCache'
        ),
        'test' => array(
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'test_',
            'cacheType' => 'redis'
        ),
        'member' => array(
            'index' => 'member',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'member',
            'cacheType' => 'redis',
            // 'cacheName' => 'ssdbCache'
        ),
        'mshop' => array(
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'redis'
        ),
        'msearch' => array( //oldshop下msearch域访问
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis'
        ),
        'msearch_shop' => array( //oldshop下mshop域访问
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'search_product',
            'cacheType' => 'redis',
            'cacheName' => 'productCache',
            'spiderCacheKeyFix' => 'search_product',
            'spiderCacheType' => 'ssdb',
            'spiderCacheName' => 'ssdbCache'
        ),
        'msearch_book' => array( //oldshop下mbook域访问
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'productSearch_',
            'cacheType' => 'redis',
            'cacheName' => 'productCache',
            'spiderCacheKeyFix' => 'productSearch_',
            'spiderCacheType' => 'ssdb',
            'spiderCacheName' => 'ssdbCache'
        ),
        'mbook' => array( //mbook（线上）
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'msite',
            'cacheType' => 'ssdb',
            'cacheName' => 'msiteSsdbCache'
        ),
        'mbq' => array( //mbq（线上）
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'msite',
            'cacheType' => 'ssdb',
            'cacheName' => 'msiteSsdbCache'
        ),
        'mwww' => array( //mwww（线上）
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'msite',
            'cacheType' => 'ssdb',
            'cacheName' => 'msiteSsdbCache'
        ),
        'search_myRecommend' => array( //搜索推荐
            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'search_myRecommend_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'item404' => array( //404页面搜索调用
            'forceSpider' => 1, //强制走爬虫通道
            'index' => 'product',
            'spiderIndex' => 'seoproduct',
            'pageSize' => 50,
            'maxPageNum' => 100,
            'otherMaxPageNum' => 200,
            'maxMatch' => 5000,
            'otherMaxMatch' => 10000,
            'cacheKeyFix' => 'shop_',
            'cacheType' => 'ssdb',
            'cacheName' => 'interestSsdbCache'
        ),
        'study' => array(
            'index' => 'product',
            'pageSize' => 50,
            'maxPageNum' => 50,
            'otherMaxPageNum' => 100,
            'maxMatch' => 2500,
            'otherMaxMatch' => 5000,
            'cacheKeyFix' => 'study_',
            'cacheType' => 'ssdb',
            'cacheName' => 'ssdbCache'
        ),
    );

}
