<?php

/**
 * 测试脚本
 */
class TestController extends CliController
{

    /**
     * 执行父类的初始化方法
     */
    public function init()
    {
        parent::init();
    }

    /**
     * 测试接口
     * php-5.5 /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/decodeUrl/url/cat_8dis_1desc_2i1y2ce_0o123q3re_0/'
     */
    public function decodeUrlAction()
    {
        $url = '';
        $params = $this->request->getParams();
        if (!empty($params['url'])) {
            $url = $params['url'];
        } else {
            echo 'url is empty';
            exit;
        }
        $productSearchObj = new ProductSearchModel();
        var_dump($productSearchObj->str2unicode('测试'));
        $productSearchObj->setBizFlag('verify');
        $inteParamsArr = $productSearchObj->decodeUrl($url);
        echo '<pre>';
        print_r($inteParamsArr);
        exit;
    }

    /**
     * 测试接口
     * php-5.5 /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/search/'
     */
    public function searchAction()
    {
        $productSearchInter = new Interface_TestModel();
        $bizFlag = 'study';
        $agent = '';
        $realIp = '123.125.71.105';
        $requestParams = array('key' => array('key' => 'z', 'value' => '测试用书', 'nocode' => 1));
        $otherParams = array(
            'pageSize' => 18,
            'expire' => -1
        );
        $isFormatData = 0;
        $isFuzzySearch = 0;
        $isBuildSnippets = 0;
        $action = 'STUDY_searchSaledBooks';
        $data = $productSearchInter->testProductSearch($bizFlag, $realIp, $agent, $requestParams, $action, $isFormatData, $isFuzzySearch, $isBuildSnippets, $otherParams);
        echo '<pre>';
        print_r($data);
        exit;
        /*
        $bizFlag = 'shop';
        $agent = 'Mozilla/5.0 (compatible; Baiduspider/2.0; http://www.baidu.com/search/spider.html)';
        $realIp = '123.125.71.105';
        $requestParams = 'sk859bk58ebk573bfi_270299460';
        $otherParams = array(
            'pageSize' => 18,
            'expire' => -1
        );
        $isFormatData = 0;
        $isFuzzySearch = 0;
        $isBuildSnippets = 0;
        $action = 'SHOP_getInterestItems';
        $data = $productSearchInter->testProductSearch($bizFlag, $realIp, $agent, $requestParams, $action, $isFormatData, $isFuzzySearch, $isBuildSnippets, $otherParams);
        echo '<pre>';
        print_r($data);exit;
        
        

        $bizFlag = 'app';
        $agent = 'Lowell-Agent';
        $realIp = '192.168.2.95';
        $requestParams = array(
            'catNum' => array(
                'key' => 'cat_',
                'value' => ''
            ),
            'discount' => array(
                'key' => 'dis_',
                'value' => ''
            ),
            'addtime' => array(
                'key' => 'g',
                'value' => ''
            ),
            'isFuzzy' => array(
                'key' => 'j',
                'value' => ''
            ),
            'author' => array(
                'key' => 'l',
                'value' => ''
            ),
            'press' => array(
                'key' => 'm',
                'value' => ''
            ),
            'years' => array(
                'key' => 'n',
                'value' => ''
            ),
            'special1' => array(
                'key' => 'o',
                'value' => ''
            ),
            'special2' => array(
                'key' => 'p',
                'value' => ''
            ),
            'special3' => array(
                'key' => 'q',
                'value' => ''
            ),
            'shopName' => array(
                'key' => 'r',
                'value' => ''
            ),
            'itemname' => array(
                'key' => 's',
                'value' => ''
            ),
            'price' => array(
                'key' => 't',
                'value' => ''
            ),
            'location' => array(
                'key' => 'u',
                'value' => ''
            ),
            'order' => array(
                'key' => 'v',
                'value' => ''
            ),
            'exKey' => array(
                'key' => 'x',
                'value' => ''
            ),
            'status' => array(
                'key' => 'y',
                'value' => 0,
                'order' => 1
            ),
            'key' => array(
                'key' => 'z',
                'value' => 'k52a8k7269k533bk5b66',
                'fuzzy' => 1,
                'order' => 2
            ),
            'pageNum' => array(
                'key' => 'w',
                'value' => '3',
                'order' => 3
            )
        );
        $otherParams = array(
            'expire' => '3600'
        );
        $isFormatData = 0;
        $isFuzzySearch = 1;
        $isBuildSnippets = 0;
        $action = 'SHOP_getFPWithFilter';
        $data = $productSearchInter->testProductSearch($bizFlag, $realIp, $agent, $requestParams, $action, $isFormatData, $isFuzzySearch, $isBuildSnippets, $otherParams);
        echo '<pre>';
        print_r($data);exit;
        
        
        $bizFlag = 'test';
        $requestParams = 'cat_8y2v6zk73k61k64k61k73k66k64';
        $requestParams = array('catNum' => array('key' => 'cat_', 'value' => '8'), 'status' => array('key' => 'y', 'value' => '2'), 'order' => array('key' => 'v', 'value' => '6'), 'key' => array('key' => 'z', 'value' => 'k6e05k672bk6c11k56fd'), 'filteritemid' => array('key' => 'filteritemid', 'value' => '268018378h218537708h303730841h267991380h285737482h278465635h295684009'));
//        $requestParams = array('catNum' => array('key' => 'cat_', 'value' => '8'), 'status' => array('key' => 'y', 'value' => '2'), 'order' => array('key' => 'v', 'value' => '6'));
//        $requestParams = array('catNum' => array('key' => 'cat_', 'value' => '8'), 'status' => array('key' => 'y', 'value' => '2'), 'order' => array('key' => 'v', 'value' => '6'), 'key' => array('key' => 'z', 'value' => '清末民国', 'nocode' => 1));
//        $requestParams = array('catNum' => array('key' => 'cat_', 'value' => '8'), 'status' => array('key' => 'y', 'value' => '2'), 'order' => array('key' => 'v', 'value' => '6'));
//        $requestParams = array();
        $requestParams = array('key' => array('key' => 'z', 'value' => '光绪与珍妃', 'nocode' => 1));
        $otherParams['pageSize'] = 2;
        $action = 'getPWithFilter';
        $isFormatData = 1;
        $isFuzzySearch = 1;
        $isBuildSnippets = 1;
        $data = $productSearchInter->testProductSearch($bizFlag, $requestParams, $action, $isFormatData, $isFuzzySearch, $isBuildSnippets, $otherParams);
        echo '<pre>';
        print_r($data);exit;

        $productSearchObj = new ProductSearchModel();
        $bizFlag = 'shop';
        $requestParams = 'cat_8y2';
        if (is_string($requestParams)) {
            $requestParams = $productSearchObj->decodeUrl($requestParams);
        }
        $productSearchObj->setBizFlag($bizFlag);
        if(!$productSearchObj->init()) {
            die('error!');
        }
        $searchParams = $productSearchObj->translateParams($requestParams);
        $searchData = $productSearchObj->getFPWithFilter($searchParams);
        var_dump($searchData);*/
        exit;
    }

    /**
     * 测试接口
     * php-5.5 /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/endauction/'
     */
    public function endauctionAction()
    {
        $productSearchInter = new Interface_TestendauctionModel();
        $bizFlag = 'endauction';
        $agent = 'Mozilla/5.0 (compatible; Baiduspider/2.0; http://www.baidu.com/search/spider.html)';
        $realIp = '123.125.71.105';
        $requestParams = 'zk96c6k53e4k5370k8c31';
        $otherParams = array(
            'pageSize' => 18,
            'expire' => -1
        );
        $isFormatData = 0;
        $isFuzzySearch = 0;
        $isBuildSnippets = 0;
        $action = 'getFPWithFilter';
        $data = $productSearchInter->testEndauctionSearch($bizFlag, $realIp, $agent, $requestParams, $action, $isFormatData, $isFuzzySearch, $isBuildSnippets, $otherParams);
        echo '<pre>';
        print_r($data);
        exit;

    }

    /**
     * 测试接口
     * php-5.5 /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/message/'
     */
    public function messageAction()
    {
        $productSearchInter = new Interface_TestmessageModel();
        $action = 'getMessageList';
        $requestParams = array(
            'sender' => '12',
            'receiver' => '2162752',
            'sendernickname' => '孔夫子旧书网',
            'receivernickname' => '孔子书屋2011',
            'msgcontent' => '提醒',
            'sendtime' => '1326217154h1346217154',
            'pagenum' => '1'
        );
        $data = $productSearchInter->testMessageSearch($action, $requestParams);
        echo '<pre>';
        print_r($data);
        exit;

    }

    /**
     * 测试接口
     * php-5.5 /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/member/'
     */
    public function memberAction()
    {
        $productSearchInter = new Interface_TestmemberModel();
        $bizFlag = 'member';
        $agent = 'Mozilla/5.0 (compatible; Baiduspider/2.0; http://www.baidu.com/search/spider.html)';
        $realIp = '123.125.71.105';
        $requestParams = array(
//            'username' => 'webmaster',
//            'nickname' => '孔夫子旧书网'
            'username' => '人在江湖',
            'nickname' => '人在江湖'
        );
        $otherParams = array(
            'pageSize' => 18,
            'expire' => -1
        );
        $isFormatData = 0;
        $isFuzzySearch = 0;
        $isBuildSnippets = 0;
        $action = 'getUserList';
        $data = $productSearchInter->testMemberSearch($bizFlag, $realIp, $agent, $requestParams, $action, $isFormatData, $isFuzzySearch, $isBuildSnippets, $otherParams);
        echo '<pre>';
        print_r($data);
        exit;

    }

    /**
     * 测试接口
     * php-5.5 /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/footprint/'
     * php-5.5 /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/footprint/type/footprint_shop/userid/5315/'
     */
    public function footprintAction()
    {
        $params = $this->request->getParams();
        $agent = 'Mozilla/5.0 (compatible; Baiduspider/2.0; http://www.baidu.com/search/spider.html)';
        $realIp = '123.125.71.105';
//        $userId = isset($params['userid']) ? $params['userid'] : '5315';
        $userId = '201253';
//        $bizFlag = isset($params['type']) ? $params['type'] : 'footprint_pm';
        $bizFlag = 'footprint_shop';
        $productSearchInter = new Interface_TestfootprintModel();
//        $action = 'getFootprintRecommendForShopOrPm';
//        $action = 'getShopRecommend';
        $action = 'getShopFootprint';
        $requestParams = array(
            'userId' => $userId,
            'pageNum' => 1,
            'pageSize' => 50
        );
        $data = $productSearchInter->testFootprintSearch($bizFlag, $realIp, $agent, $action, $requestParams);
        echo '<pre>';
        print_r($data);
        exit;

    }

    /**
     * 测试创建product映射
     * php /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/testCreatemapping/'
     */
    public function testCreatemappingAction()
    {
        $fields = array(
            '_all' => array(
                'store' => 'true'
            ),
            'itemid' => array(
                'type' => 'integer'
            ),
            'biztype' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'userid' => array(
                'type' => 'integer'
            ),
            'catid' => array(
                'type' => 'long'
            ),
            'nickname' => array( //用于展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            '_nickname' => array( //用于搜索
                'type' => 'string',
                'analyzer' => 'ik'
            ),
            'shopname' => array( //用于展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            '_shopname' => array( //用于搜索
                'type' => 'string',
                'analyzer' => 'ik'
            ),
            'shopid' => array(
                'type' => 'integer'
            ),
            'area' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'class' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'shopstatus' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'itemname' => array( //用于展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            '_itemname' => array( //用于搜索
                'type' => 'string',
                'analyzer' => 'ik'
            ),
            'author' => array( //用于展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            'iauthor' => array( //用于聚类
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            '_author' => array( //用于搜索
                'type' => 'string',
                'analyzer' => 'ik'
            ),
            'author2' => array( //用于聚类展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            'press' => array( //用于展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            'ipress' => array( //用于聚类
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            '_press' => array( //用于搜索
                'type' => 'string',
                'analyzer' => 'ik'
            ),
            'press2' => array( //用于聚类展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            'price' => array(
                'type' => 'float',
                'include_in_all' => 'false'
            ),
            'pubdate' => array( //pubdate desc     如果没有出版时间，pubdate会为最小值所以降序时会在最后
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'pubdate2' => array( //pubdate2 asc'   如果没有出版时间，pubdate2会为最大值所以升序时会在最后
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'years' => array( //年代的中文表示
                'type' => 'string',
                'include_in_all' => 'false'
            ),
            'years2' => array( //用于聚类
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'discount' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'number' => array(
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'quality' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'addtime' => array(
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'updatetime' => array(
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'recertifystatus' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'imgurl' => array(
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            'tag' => array( //搜索关键词=标签  用于展示
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            '_tag' => array( //用于搜索
                'type' => 'string',
                'analyzer' => 'ik'
            ),
            'certifystatus' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'olreceivetype' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'approach' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'itemdesc' => array(
                'type' => 'string',
                'analyzer' => 'ik',
                'store' => 'false'
            ),
            'isbn' => array(
                'type' => 'string',
                'index' => 'not_analyzed'
            ),
            'params' => array(
                'type' => 'string',
                'include_in_all' => 'false',
                'index' => 'not_analyzed'
            ),
            'salestatus' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'isdeleted' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'rank' => array(
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'catid1' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'catid2' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'catid3' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'catid4' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'vcatid' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'vcatid1' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'vcatid2' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'vcatid3' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'vcatid4' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'hasimg' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'area1' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'area2' => array(
                'type' => 'long',
                'include_in_all' => 'false'
            ),
            'paper' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'printType' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'binding' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'sort' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'material' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'form' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            ),
            'trust' => array(
                'type' => 'integer',
                'include_in_all' => 'false'
            ),
            'flag' => array(
                'type' => 'short',
                'include_in_all' => 'false'
            )
        );
        $result = ElasticSearchModel::createMapping('192.168.6.29', '9200', 'item', 'product', $fields, 'ik', 'false');
        exit;
    }

    /**
     * 测试MsgPack
     * php /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/testMsgPack/'
     */
    public function testMsgPackAction()
    {
        $data = array(0 => 1, 1 => 2, 2 => 3);
        $msg = msgpack_pack($data);
        $data = msgpack_unpack($msg);
        var_dump($data);
        exit;
    }

    /**
     * 测试getAuctioncomList
     * php /data/webroot/kfzsearch/public/cli/cli.php request_uri='/cli/test/testGetAuctioncomList/'
     */
    public function testGetAuctioncomListAction()
    {
        $test = new Interface_TestauctioncomModel();
        $result = $test->getAuctioncomList();
        echo '<pre>';
        print_r($result);
    }
}
