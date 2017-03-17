<?php

/**
 * ElasticSearch操作类
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2014年9月23日13:59:22
 */
class ElasticSearchModel extends Kfz_System_ModelAbstract
{
    
    //超时时间
    static private $timeout = 10;
    
    //错误
    static private $errorInfo = '';

    /*
     * 从服务列表中获取一个节点
     * @param string/array $servers:可以是字符串（单个server）或数组（多个server），格式为: host[:port[:weight]]
     */
    static public function getServer($servers)
    {
        $parsedServers = array();
        if(is_string($servers)) {
            $parsedServers[0] = self::parseServer($servers);
        } else if(is_array($servers)) {
            foreach($servers as $i => $server) {
               $parsedServers[$i] = self::parseServer($server);
            }
        } else {
            return array();
        }
        
        $activeServers = self::getActiveServers($parsedServers);
        if(empty($activeServers)) {
            return array();
        }
        
        $lastServer = end($activeServers);
        $rand = mt_rand(1, $lastServer['max']);
        $serverID = 0;
        foreach($activeServers as $id => $server) {
            if ($rand >= $server['min'] && $rand <= $server['max']) {
                $serverID = $id;
                break;
            }
        }
        $host = $activeServers[$serverID]['host'];
        $port = $activeServers[$serverID]['port'];
        return array('host' => $host, 'port' => $port);
    }
    
    static private function parseServer($server) 
    {
        $p = explode(':', $server);
        $s['host'] = trim($p[0]);
        $s['port'] = 9306;
        $s['weight'] = 1;
        if(isset($p[1]) && !empty($p[1])) {
            $s['port'] = intval(trim($p[1]));
        } 
        if(isset($p[2]) && !empty($p[2])) {
            $s['weight'] = intval(trim($p[2]));
            if($s['weight'] <= 0) $s['weight'] = 1;
        }
        
        return $s;
    }

    static private function getActiveServers($servers)
    {
        $totalweigth = 0;
        foreach($servers as $server) {
           $totalweigth += $server['weight'];
        }
        
        $activeServers = array();
        $start = 1;
        foreach($servers as $id => $server) {
            $weight = $server['weight'];
            $server['min'] = $start;
            $server['max'] = intval($start + $weight/$totalweigth * 100 - 1);
            $activeServers[$id] = $server;
            $start = $server['max'] + 1;
        }
        
        return $activeServers;
    }
    
    static private function execCurl($url, $method, $data = '', $timeout = 0)
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $useTimeOut = $timeout ? $timeout : self::$timeout;
        curl_setopt($handle, CURLOPT_TIMEOUT, $useTimeOut);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        if($data) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        }
        return curl_exec($handle);
    }
    
    static private function encodeJson($array) { 
        self::arrayRecursive($array, 'urlencode', true);
        $json = json_encode($array); 
        $json = urldecode($json); 
        // ext需要不带引号的bool类型 
        $json = str_replace("\"false\"", "false", $json); 
        $json = str_replace("\"true\"", "true", $json); 
        return $json; 
    }
    
    static private function arrayRecursive(&$array, $function, $apply_to_keys_also = false) 
    { 
        static $recursive_counter = 0; 
        if (++$recursive_counter > 1000) { 
            die('possible deep recursion attack'); 
        } 
        foreach ($array as $key => $value) { 
            if (is_array($value)) { 
                self::arrayRecursive($array[$key], $function, $apply_to_keys_also); 
            } else { 
                $value = self::translateSpecial($value);
                $array[$key] = $function($value); 
            } 
            if ($apply_to_keys_also && is_string($key)) { 
                $new_key = $function($key); 
                if ($new_key != $key) { 
                    $array[$new_key] = $array[$key]; 
                    unset($array[$key]); 
                } 
            } 
        } 
        $recursive_counter--; 
    }
    
    /**
     * 转译特殊字符
     * 
     *   \" Standard JSON quote
     *   \\ Backslash (Escape char)
     *   \/ Forward slash
     *   \b Backspace (ascii code 08)
     *   \f Form feed (ascii code 0C)
     *   \n Newline
     *   \r Carriage return
     *   \t Horizontal Tab
     *   \u four-hex-digits
     */
    static public function translateSpecial($str)
    {
        $from = array ( '\'', "\r", "\n", "\r\n", "\b", "\f", "\t", "\u", chr(0), chr(1), chr(2), chr(3), chr(4), chr(5), chr(6), chr(7), chr(8), chr(9), chr(10), chr(11), chr(12), chr(13), chr(14), chr(15), chr(16), chr(17), chr(18), chr(19), chr(20), chr(21), chr(22), chr(23), chr(24), chr(25), chr(26), chr(27), chr(28), chr(29), chr(30), chr(31), '\\', '"', '/', '(', ')');
        $to   = array ( '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '\\\\', '\"', '\/', '（', '）');
        $returnStr = str_replace($from, $to, $str);
        return $returnStr;
    }
    
    /**
     * 创建一个索引
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     */
    static public function createIndex($ip, $port, $indexName)
    {
        $url       = "{$ip}:{$port}/{$indexName}";
        var_dump($url);return true;
        $result    = self::execCurl($url, 'PUT');
        $resultSet = json_decode($result, true);
        return $resultSet;
    }
    
    /**
     * 删除一个索引
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     */
    static public function deleteIndex($ip, $port, $indexName)
    {
        $url       = "{$ip}:{$port}/{$indexName}";
        var_dump($url);return true;
        $result    = self::execCurl($url, 'DELETE');
        $resultSet = json_decode($result, true);
        return $resultSet;
    }
    
    /**
     * 创建索引映射
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param array  $fields    字段
     *                      例：array('index_name' => '_all/..', 'type' => 'string/integer/float/date/long/short/byte/double', 'store' => 'true/false', 'index' => 'analyzed/not_analyzed', 'term_vector' => 'no/yes/with_offsets/with_positions/with_positions_offsets', 'analyzer' => 'ik/mmseg', 'index_analyzer' => 'ik/mmseg', 'search_analyzer' => 'ik/mmseg', 'include_in_all' => 'true/false')
     * @param string $segType   分词类型，目前支持ik/mmseg
     * @param string $dynamic   动态映射类型
     */
    static public function createMapping($ip, $port, $indexName, $indexType, $fields, $segType = '', $dynamic = '')
    {
        $url = "{$ip}:{$port}/{$indexName}/{$indexType}/_mapping/";
        if(!is_array($fields) || empty($fields)) {
            return false;
        }
        $doc = array();
        $doc[$indexType] = array();
        if($dynamic) {
            $doc[$indexType]['dynamic'] = $dynamic;
        }
        foreach($fields as $field => $set) {
            if($field == '_all') {
                if(isset($set['store']) && in_array($set['store'], array(true, false))) {
                    $doc[$indexType]['_all']['store'] = $set['store'];
                }
                if($segType && in_array($segType, array('ik', 'mmseg'))) {
                    $doc[$indexType]['_all']['analyzer'] = $segType;
                }
                if(isset($set['analyzer']) && in_array($set['analyzer'], array('ik', 'mmseg'))) {
                    $doc[$indexType]['_all']['analyzer'] = $field['analyzer'];
                }
                if(isset($set['index_analyzer']) && in_array($set['index_analyzer'], array('ik', 'mmseg'))) {
                    $doc[$indexType]['_all']['index_analyzer'] = $set['index_analyzer'];
                }
                if(isset($set['search_analyzer']) && in_array($set['search_analyzer'], array('ik', 'mmseg'))) {
                    $doc[$indexType]['_all']['search_analyzer'] = $set['search_analyzer'];
                }
            } else {
                if(!isset($doc[$indexType]['properties'])) {
                    $doc[$indexType]['properties'] = array();
                }
                $doc[$indexType]['properties'][$field] = array();
                if(isset($set['type'])) {
                    $doc[$indexType]['properties'][$field]['type'] = $set['type'];
                }
                if(isset($set['store']) && in_array($set['store'], array(true, false))) {
                    $doc[$indexType]['properties'][$field]['store'] = $set['store'];
                }
                if(isset($set['include_in_all']) && in_array($set['include_in_all'], array(true, false))) {
                    $doc[$indexType]['properties'][$field]['include_in_all'] = $set['include_in_all'];
                }
                if(isset($set['index']) && in_array($set['index'], array('analyzed', 'not_analyzed'))) {
                    $doc[$indexType]['properties'][$field]['index'] = $set['index'];
                }
                if(isset($set['analyzer']) && in_array($set['analyzer'], array('ik', 'mmseg'))) {
                    $doc[$indexType]['properties'][$field]['analyzer'] = $set['analyzer'];
                }
                if(isset($set['index_analyzer']) && in_array($set['index_analyzer'], array('ik', 'mmseg'))) {
                    $doc[$indexType]['properties'][$field]['index_analyzer'] = $set['index_analyzer'];
                }
                if(isset($set['search_analyzer']) && in_array($set['search_analyzer'], array('ik', 'mmseg'))) {
                    $doc[$indexType]['properties'][$field]['search_analyzer'] = $set['search_analyzer'];
                }
            }
        }
//        echo '<pre>';print_r($doc);exit;
        $jsonStr    = self::encodeJson($doc);
        var_dump($jsonStr);return true;
        $result     = self::execCurl($url, 'POST', $jsonStr);
        $resultSet  = json_decode($result, true);
        return $resultSet;
    }
    
    /**
     * 获取映射关系
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     */
    static public function getMapping($ip, $port, $indexName = '', $indexType = '')
    {
        if(!$indexName) {
            $url   = "{$ip}:{$port}/_mapping/";
        } else {
            if(!$indexType) {
                $url = "{$ip}:{$port}/{$indexName}/_mapping/";
            } else {
                $url = "{$ip}:{$port}/{$indexName}/{$indexType}/_mapping/";
            }
        }
        $result    = self::execCurl($url, 'GET');
        $resultSet = json_decode($result, true);
        return $resultSet;
    }
    
    /**
     * 索引一条记录
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param array  $doc       记录数组
     * @param int    $id        记录ID
     */
    static public function indexDocument($ip, $port, $indexName, $indexType, $doc, $id = 0)
    {
        $jsonStr    = self::encodeJson($doc);
//        var_dump($jsonStr);exit;
        if($id) {
            $url    = "{$ip}:{$port}/{$indexName}/{$indexType}/{$id}";
            $method = 'PUT';
        } else {
            $url    = "{$ip}:{$port}/{$indexName}/{$indexType}";
            $method = 'POST';
        }
        $result     = self::execCurl($url, $method, $jsonStr);
        $resultSet  = json_decode($result, true);
        return $resultSet;
    }
    
    /**
     * 删除一条记录
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param mixed  $key       指定删除条件 id/key
     * @param string $type      删除方式 1：id  2：key
     */
    static public function deleteDocument($ip, $port, $indexName, $indexType, $key, $type = 1)
    {
        if($type == 1) {
            if(!trim($key)) {
                return false;
            }
            $url        = "{$ip}:{$port}/{$indexName}/{$indexType}/{$key}";
            $result     = self::execCurl($url, 'DELETE');
            $resultSet  = json_decode($result, true);
            return $resultSet;
        } elseif ($type == 2) {
            if(!is_array($key) || !isset($key['key']) || !isset($key['value']) || !$key['key'] || !$key['value']) {
                return false;
            }
            $url        = "{$ip}:{$port}/{$indexName}/{$indexType}/_query";
            $doc = array(
                'query' => array(
                    'term' => array(
                        $key['key'] => $key['value']
                    )
                )
            );
            $jsonStr    = self::encodeJson($doc);
            if(!trim($jsonStr)) {
                return false;
            }
            $result     = self::execCurl($url, 'DELETE', $jsonStr);
            $resultSet  = json_decode($result, true);
            return $resultSet;
        }
    }
    
    /**
     * 更新一条记录
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param int    $id
     * @param array  $doc
     */
    static public function updateDocument($ip, $port, $indexName, $indexType, $id, $doc)
    {
        if(!trim($id)) {
            return false;
        }
        $url    = "{$ip}:{$port}/{$indexName}/{$indexType}/{$id}/_update";
        $doc_up = array(
            'doc' => $doc
        );
        $jsonStr    = self::encodeJson($doc_up);
        if(!trim($jsonStr)) {
            return false;
        }
        $result     = self::execCurl($url, 'POST', $jsonStr);
        $resultSet  = json_decode($result, true);
        return $resultSet;
    }
    
    /**
     * 查找指定记录
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param int    $id        指定查询id
     */
    static public function getDocument($ip, $port, $indexName, $indexType, $id)
    {
        if(!trim($id)) {
            return false;
        }
        $url        = "{$ip}:{$port}/{$indexName}/{$indexType}/{$id}";
        $result     = self::execCurl($url, 'GET');
        $resultSet  = json_decode($result, true);
        return $resultSet;
    }
    
    /*
     * 查询记录
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param int    $isDebug   调试模式
     * @param array  $fields    字段
     *                      例：array('field1', 'field2')
     * @param array  $query     匹配条件
     *                      例：array('key' => '', 'fields' => array(  array('field' => 'field1', 'weight' => '10'), array('field' => 'field2', 'weight' => '1')  ))
     * @param array  $filter    过滤条件
     *                      例：array(  'must_not/must' => array(  array('field' => 'field1', 'value' => '')  ), 'range_must/range_must_not' => array(  array('field' => 'field1', 'from' => '', 'to' => '', 'include_lower' => '', 'include_upper' => '')  ) )
     * @param array  $sort      排序
     *                      例：array( array('field' => 'field1', 'order' => 'desc') )
     * @param array  $limit     限制条件
     *                      例：array("from" => '', "size" => '')
     * @param array  $highlight 高亮
     *                      例：array( 'pre_tags' => array('<b>'), 'post_tags' => array('</b>'), 'fields' => array(array('field' => 'field1')) )
     * @param array  $facets    聚类
     *                      例：array('keyname' => array(array('field' => 'field1', 'size' => size1)), 'keyname2' => array('field2'), 'keyname3' => 'field3', 'keyname4' => array('field4', 'field5))
     * 
     * @param bool   $dfs_query_then_fetch  是否为严格相关度搜索(分布频度搜索)
     * 
     * @param example    {"query":{"bool":{"must":[{"match_phrase":{"_author":"路遥"}},{"match_phrase":{"_press":"云南人民出版社"}}],"should":{"dis_max":{"queries":[{"multi_match":{"query":"平凡的世界","fields":["_author^60","_press^50","_itemname^300","isbn^30"],"minimum_should_match":"90%"}}]}},"must_not":{"dis_max":{"queries":[{"multi_match":{"query":"  （712）","fields":["_author^60","_press^50","_itemname^300","isbn^30"],"minimum_should_match":"90%"}}]}}}},"filter":{"bool":{"must":[{"term":{"isdeleted":"0"}},{"term":{"shopstatus":"1"}},{"term":{"certifystatus":"1"}},{"term":{"salestatus":"0"}}]}},"sort":["_score",{"rank":{"order":"desc"}}],"size":"50","from":"0","highlight":{"pre_tags":["<b>"],"post_tags":["<\/b>"],"fields":{"_itemname":{"force_source":false},"_author":{"force_source":false},"_press":{"force_source":false}}},"facets":{"catid_facet":{"terms":[{"field":"catid1"}],"global":false,"facet_filter":{"bool":{"must":[{"term":{"isdeleted":"0"}},{"term":{"shopstatus":"1"}},{"term":{"certifystatus":"1"}},{"term":{"salestatus":"0"}}]}}}}}
     * 
     * ElasticSearchModel::findDocument(
                '192.168.6.29', 
                '9200', 
                'test3', 
                'test', 
                0, //isDebug
                array(), //fields
                array('key' => '西', 'fields' => array(  array('field' => 'itemname', 'weight' => '10'), array('field' => 'press')  )),  //query
                array(  'must_not' => array(  array('field' => 'itemname', 'value' => '曹操')  ), 'range_must' => array(  array('field' => 'price', 'from' => '0', 'to' => '50')  )),  //filter
                array( array('field' => 'itemId', 'order' => 'desc') ), //sort
                array("from" => '0', "size" => '100'), //limit
                array( 'pre_tags' => array('<b>'), 'post_tags' => array('</b>'), 'fields' => array(array('field' => 'itemname'), array('field' => 'press')) ), //highlight
                array('itemname_facet' => array(array('field' => 'itemname', 'size' => 3)), 'press_facet' => array('press'), 'price_facet' => 'price') //Terms Facet
          )
     */
    static public function findDocument($ip, $port, $indexName, $indexType, $isDebug = 0, $fields = array(), $query = array(), $filter = array(), $sort = array(), $limit = array(), $highlight = array(), $facets = array(), $timeout = 0, $dfs_query_then_fetch = false)
    {
        if($dfs_query_then_fetch) {
            $url    = "{$ip}:{$port}/{$indexName}/{$indexType}/_search?preference=_primary_first&search_type=dfs_query_then_fetch";
        } else {
            $url    = "{$ip}:{$port}/{$indexName}/{$indexType}/_search";
        }
        $doc    = array();
        
        if(is_array($fields) && !empty($fields)) {
            $doc['_source'] = array();
            foreach($fields as $field) {
                $doc['_source'][] = $field;
            }
        }

        if(is_array($query) && !empty($query)) {
            $doc['query'] = array();
            
            if(isset($query['type']) && $query['type'] == 'bool') { //原有搜索查询方式，无个性化相关排序
                $doc['query']['bool'] = array();
                if(isset($query['must']) && !empty($query['must'])) {
                    if(!isset($doc['query']['bool']['must'])) {
                        $doc['query']['bool']['must'] = array();
                    }
                    foreach($query['must'] as $fieldInfo) {
                        if(!isset($fieldInfo['field'])) {
                            continue;
                        }
                        if(isset($fieldInfo['type']) && $fieldInfo['type'] == 'phrase') {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                        } elseif (isset($fieldInfo['type']) && $fieldInfo['type'] == 'include') {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'])));
                            if(isset($fieldInfo['minimum_should_match'])) {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => $fieldInfo['minimum_should_match'])));
                            } else {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => '100%')));
                            }
                        } else {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                        }
                        $doc['query']['bool']['must'][] = $field;
                    }
                }
                if(isset($query['should']) && !empty($query['should'])) {
                    if(!isset($doc['query']['bool']['should'])) {
                        $doc['query']['bool']['should'] = array();
                    }
                    foreach($query['should'] as $fieldInfo) {
                        if(!isset($fieldInfo['field'])) {
                            continue;
                        }
                        if(isset($fieldInfo['type']) && $fieldInfo['type'] == 'phrase') {
                            if(isset($fieldInfo['slop'])) {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase', 'slop' => $fieldInfo['slop'])));
                            } else {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                            }
                        } elseif (isset($fieldInfo['type']) && $fieldInfo['type'] == 'include') {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'])));
                            if(isset($fieldInfo['minimum_should_match'])) {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => $fieldInfo['minimum_should_match'])));
                            } else {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => '100%')));
                            }
                        } else {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                        }
                        $doc['query']['bool']['should'][] = $field;
                    }
                }
                if(isset($query['should-dis_max']) && !empty($query['should-dis_max'])) {
                    if(isset($query['should-dis_max']['queries']) && !empty($query['should-dis_max']['queries'])) {
                        if(!isset($doc['query']['bool']['should'])) {
                            $doc['query']['bool']['should'] = array();
                        }
                        $should_dis_max_arr = array();
                        $should_dis_max_arr['dis_max'] = array();
                        $should_dis_max_arr['dis_max']['queries'] = array();
                        foreach($query['should-dis_max']['queries'] as $qvalue) {
                            if(isset($qvalue['isMulti']) && $qvalue['isMulti'] == 1) {
                                $singleq = array();
                                $singleq['multi_match'] = array();
                                $singleq['multi_match']['query'] = $qvalue['key'];
                                $singleq['multi_match']['fields'] = $qvalue['fields'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $singleq['multi_match']['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                if(isset($qvalue['tie_breaker']) && !empty($qvalue['tie_breaker'])) {
                                    $singleq['multi_match']['tie_breaker'] = $qvalue['tie_breaker'];
                                }
                                if(isset($qvalue['type']) && !empty($qvalue['type'])) { //cross_fields
                                    $singleq['multi_match']['type'] = $qvalue['type'];
                                }
                                $should_dis_max_arr['dis_max']['queries'][] = $singleq;
                            } else {
                                $singleq = array();
                                $singleq['match'] = array();
                                $field = $qvalue['fields'];
                                $tmp = array();
                                $tmp['query'] = $qvalue['key'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $tmp['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                $singleq['match'][$field] = $tmp;
                                $should_dis_max_arr['dis_max']['queries'][] = $singleq;
                            }
                        }
                        $doc['query']['bool']['should'][] = $should_dis_max_arr;
                    }
                }
                if(isset($query['must-dis_max']) && !empty($query['must-dis_max'])) {
                    if(isset($query['must-dis_max']['queries']) && !empty($query['must-dis_max']['queries'])) {
                        if(!isset($doc['query']['bool']['must'])) {
                            $doc['query']['bool']['must'] = array();
                        }
                        $must_dis_max_arr = array();
                        $must_dis_max_arr['dis_max'] = array();
                        $must_dis_max_arr['dis_max']['queries'] = array();
                        foreach($query['must-dis_max']['queries'] as $qvalue) {
                            if(isset($qvalue['isMulti']) && $qvalue['isMulti'] == 1) {
                                $singleq = array();
                                $singleq['multi_match'] = array();
                                $singleq['multi_match']['query'] = $qvalue['key'];
                                $singleq['multi_match']['fields'] = $qvalue['fields'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $singleq['multi_match']['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                if(isset($qvalue['tie_breaker']) && !empty($qvalue['tie_breaker'])) {
                                    $singleq['multi_match']['tie_breaker'] = $qvalue['tie_breaker'];
                                }
                                if(isset($qvalue['type']) && !empty($qvalue['type'])) { //cross_fields
                                    $singleq['multi_match']['type'] = $qvalue['type'];
                                }
                                $must_dis_max_arr['dis_max']['queries'][] = $singleq;
                            } elseif (isset($qvalue['type']) && $qvalue['type'] == 'prefix') {
                                $singleq = array();
                                $singleq['prefix'] = array();
                                $field = $qvalue['field'];
                                $singleq['prefix'][$field] = $qvalue['key'];
                                $must_dis_max_arr['dis_max']['queries'][] = $singleq;
                            } else {
                                $singleq = array();
                                $singleq['match'] = array();
                                $field = $qvalue['fields'];
                                $tmp = array();
                                $tmp['query'] = $qvalue['key'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $tmp['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                $singleq['match'][$field] = $tmp;
                                $must_dis_max_arr['dis_max']['queries'][] = $singleq;
                            }
                        }
                        $doc['query']['bool']['must'][] = $must_dis_max_arr;
                    }
                }
                if(isset($query['must_not-dis_max']) && !empty($query['must_not-dis_max'])) {
                    if(isset($query['must_not-dis_max']['queries']) && !empty($query['must_not-dis_max']['queries'])) {
                        if(!isset($doc['query']['bool']['must_not'])) {
                            $doc['query']['bool']['must_not'] = array();
                        }
                        $doc['query']['bool']['must_not']['dis_max'] = array();
                        $doc['query']['bool']['must_not']['dis_max']['queries'] = array();
                        foreach($query['must_not-dis_max']['queries'] as $qvalue) {
                            if(isset($qvalue['isMulti']) && $qvalue['isMulti'] == 1) {
                                $singleq = array();
                                $singleq['multi_match'] = array();
                                $singleq['multi_match']['query'] = $qvalue['key'];
                                $singleq['multi_match']['fields'] = $qvalue['fields'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $singleq['multi_match']['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                if(isset($qvalue['tie_breaker']) && !empty($qvalue['tie_breaker'])) {
                                    $singleq['multi_match']['tie_breaker'] = $qvalue['tie_breaker'];
                                }
                                $doc['query']['bool']['must_not']['dis_max']['queries'][] = $singleq;
                            } elseif (isset($qvalue['type']) && $qvalue['type'] == 'match_phrase') {
                                $singleq = array();
                                $singleq['match_phrase'] = array();
                                $field = $qvalue['field'];
                                $singleq['match_phrase'][$field] = $qvalue['key'];
                                $doc['query']['bool']['must_not']['dis_max']['queries'][] = $singleq;
                            } else {
                                $singleq = array();
                                $singleq['match'] = array();
                                $field = $qvalue['fields'];
                                $tmp = array();
                                $tmp['query'] = $qvalue['key'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $tmp['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                $singleq['match'][$field] = $tmp;
                                $doc['query']['bool']['must_not']['dis_max']['queries'][] = $singleq;
                            }
                        }
                    }
                }
            } elseif(isset($query['type']) && $query['type'] == 'bool_function_score') { //可进行定制化排序
             
                $boolQuery = array();
                if(isset($query['must']) && !empty($query['must'])) {
                    if(!isset($boolQuery['must'])) {
                        $boolQuery['must'] = array();
                    }
                    foreach($query['must'] as $fieldInfo) {
                        if(!isset($fieldInfo['field'])) {
                            continue;
                        }
                        if(isset($fieldInfo['type']) && $fieldInfo['type'] == 'phrase') {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                        } elseif (isset($fieldInfo['type']) && $fieldInfo['type'] == 'include') {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'])));
                            if(isset($fieldInfo['minimum_should_match'])) {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => $fieldInfo['minimum_should_match'])));
                            } else {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => '100%')));
                            }
                        } else {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                        }
                        $boolQuery['must'][] = $field;
                    }
                }
                if(isset($query['should']) && !empty($query['should'])) {
                    if(!isset($boolQuery['should'])) {
                        $boolQuery['should'] = array();
                    }
                    foreach($query['should'] as $fieldInfo) {
                        if(!isset($fieldInfo['field'])) {
                            continue;
                        }
                        if(isset($fieldInfo['type']) && $fieldInfo['type'] == 'phrase') {
                            if(isset($fieldInfo['slop'])) {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase', 'slop' => $fieldInfo['slop'])));
                            } else {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                            }
                        } elseif (isset($fieldInfo['type']) && $fieldInfo['type'] == 'include') {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'])));
                            if(isset($fieldInfo['minimum_should_match'])) {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => $fieldInfo['minimum_should_match'])));
                            } else {
                                $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'minimum_should_match' => '100%')));
                            }
                        } else {
                            $field = array('match' => array($fieldInfo['field'] => array('query' => $fieldInfo['value'], 'type' => 'phrase')));
                        }
                        $boolQuery['should'][] = $field;
                    }
                }
                if(isset($query['should-dis_max']) && !empty($query['should-dis_max'])) {
                    if(isset($query['should-dis_max']['queries']) && !empty($query['should-dis_max']['queries'])) {
                        if(!isset($boolQuery['should'])) {
                            $boolQuery['should'] = array();
                        }
                        $should_dis_max_arr = array();
                        $should_dis_max_arr['dis_max'] = array();
                        $should_dis_max_arr['dis_max']['queries'] = array();
                        foreach($query['should-dis_max']['queries'] as $qvalue) {
                            if(isset($qvalue['isMulti']) && $qvalue['isMulti'] == 1) {
                                $singleq = array();
                                $singleq['multi_match'] = array();
                                $singleq['multi_match']['query'] = $qvalue['key'];
                                $singleq['multi_match']['fields'] = $qvalue['fields'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $singleq['multi_match']['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                if(isset($qvalue['tie_breaker']) && !empty($qvalue['tie_breaker'])) {
                                    $singleq['multi_match']['tie_breaker'] = $qvalue['tie_breaker'];
                                }
                                if(isset($qvalue['type']) && !empty($qvalue['type'])) { //cross_fields
                                    $singleq['multi_match']['type'] = $qvalue['type'];
                                }
                                $should_dis_max_arr['dis_max']['queries'][] = $singleq;
                            } else {
                                $singleq = array();
                                $singleq['match'] = array();
                                $field = $qvalue['fields'];
                                $tmp = array();
                                $tmp['query'] = $qvalue['key'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $tmp['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                $singleq['match'][$field] = $tmp;
                                $should_dis_max_arr['dis_max']['queries'][] = $singleq;
                            }
                        }
                        $boolQuery['should'][] = $should_dis_max_arr;
                    }
                }
                if(isset($query['must-dis_max']) && !empty($query['must-dis_max'])) {
                    if(isset($query['must-dis_max']['queries']) && !empty($query['must-dis_max']['queries'])) {
                        if(!isset($boolQuery['must'])) {
                            $boolQuery['must'] = array();
                        }
                        $must_dis_max_arr = array();
                        $must_dis_max_arr['dis_max'] = array();
                        $must_dis_max_arr['dis_max']['queries'] = array();
                        foreach($query['must-dis_max']['queries'] as $qvalue) {
                            if(isset($qvalue['isMulti']) && $qvalue['isMulti'] == 1) {
                                $singleq = array();
                                $singleq['multi_match'] = array();
                                $singleq['multi_match']['query'] = $qvalue['key'];
                                $singleq['multi_match']['fields'] = $qvalue['fields'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $singleq['multi_match']['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                if(isset($qvalue['tie_breaker']) && !empty($qvalue['tie_breaker'])) {
                                    $singleq['multi_match']['tie_breaker'] = $qvalue['tie_breaker'];
                                }
                                if(isset($qvalue['type']) && !empty($qvalue['type'])) { //cross_fields
                                    $singleq['multi_match']['type'] = $qvalue['type'];
                                }
                                $must_dis_max_arr['dis_max']['queries'][] = $singleq;
                            } elseif (isset($qvalue['type']) && $qvalue['type'] == 'prefix') {
                                $singleq = array();
                                $singleq['prefix'] = array();
                                $field = $qvalue['field'];
                                $singleq['prefix'][$field] = $qvalue['key'];
                                $must_dis_max_arr['dis_max']['queries'][] = $singleq;
                            } else {
                                $singleq = array();
                                $singleq['match'] = array();
                                $field = $qvalue['fields'];
                                $tmp = array();
                                $tmp['query'] = $qvalue['key'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $tmp['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                $singleq['match'][$field] = $tmp;
                                $must_dis_max_arr['dis_max']['queries'][] = $singleq;
                            }
                        }
                        $boolQuery['must'][] = $must_dis_max_arr;
                    }
                }
                if(isset($query['must_not-dis_max']) && !empty($query['must_not-dis_max'])) {
                    if(isset($query['must_not-dis_max']['queries']) && !empty($query['must_not-dis_max']['queries'])) {
                        if(!isset($boolQuery['must_not'])) {
                            $boolQuery['must_not'] = array();
                        }
                        $boolQuery['must_not']['dis_max'] = array();
                        $boolQuery['must_not']['dis_max']['queries'] = array();
                        foreach($query['must_not-dis_max']['queries'] as $qvalue) {
                            if(isset($qvalue['isMulti']) && $qvalue['isMulti'] == 1) {
                                $singleq = array();
                                $singleq['multi_match'] = array();
                                $singleq['multi_match']['query'] = $qvalue['key'];
                                $singleq['multi_match']['fields'] = $qvalue['fields'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $singleq['multi_match']['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                if(isset($qvalue['tie_breaker']) && !empty($qvalue['tie_breaker'])) {
                                    $singleq['multi_match']['tie_breaker'] = $qvalue['tie_breaker'];
                                }
                                $boolQuery['must_not']['dis_max']['queries'][] = $singleq;
                            } elseif (isset($qvalue['type']) && $qvalue['type'] == 'match_phrase') {
                                $singleq = array();
                                $singleq['match_phrase'] = array();
                                $field = $qvalue['field'];
                                $singleq['match_phrase'][$field] = $qvalue['key'];
                                $boolQuery['must_not']['dis_max']['queries'][] = $singleq;
                            } else {
                                $singleq = array();
                                $singleq['match'] = array();
                                $field = $qvalue['fields'];
                                $tmp = array();
                                $tmp['query'] = $qvalue['key'];
                                if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                    $tmp['minimum_should_match'] = $qvalue['minimum_should_match'];
                                }
                                $singleq['match'][$field] = $tmp;
                                $boolQuery['must_not']['dis_max']['queries'][] = $singleq;
                            }
                        }
                    }
                }
                if($query['type'] == 'bool') {
                    $doc['query']['bool'] = $boolQuery;
                } elseif ($query['type'] == 'bool_function_score') {
                    $doc['query']['function_score'] = array();
                    $doc['query']['function_score']['query'] = array();
                    $doc['query']['function_score']['query']['bool'] = $boolQuery;
                    $doc['query']['function_score']['field_value_factor'] = array();
                    $doc['query']['function_score']['field_value_factor']['field'] = 'rank100';
                    $doc['query']['function_score']['field_value_factor']['modifier'] = 'ln2p';
                    $doc['query']['function_score']['field_value_factor']['factor'] = 1000;
                    $doc['query']['function_score']['boost_mode'] = 'sum';
                }
                
                //end if(isset($query['type']) && $query['type'] == 'bool_function_score')
                
            } elseif(isset($query['type']) && $query['type'] == 'dis_max') {
                
                if(isset($query['queries']) && !empty($query['queries'])) {
                    $doc['query']['dis_max'] = array();
                    $doc['query']['dis_max']['queries'] = array();
                    foreach($query['queries'] as $qvalue) {
                        if(isset($qvalue['isMulti']) && $qvalue['isMulti'] == 1) {
                            $singleq = array();
                            $singleq['multi_match'] = array();
                            $singleq['multi_match']['query'] = $qvalue['key'];
                            $singleq['multi_match']['fields'] = $qvalue['fields'];
                            if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                $singleq['multi_match']['minimum_should_match'] = $qvalue['minimum_should_match'];
                            }
                            if(isset($qvalue['tie_breaker']) && !empty($qvalue['tie_breaker'])) {
                                $singleq['multi_match']['tie_breaker'] = $qvalue['tie_breaker'];
                            }
                            $doc['query']['dis_max']['queries'][] = $singleq;
                        } else {
                            $singleq = array();
                            $singleq['match'] = array();
                            $field = $qvalue['fields'];
                            $tmp = array();
                            $tmp['query'] = $qvalue['key'];
                            if(isset($qvalue['minimum_should_match']) && !empty($qvalue['minimum_should_match'])) {
                                $tmp['minimum_should_match'] = $qvalue['minimum_should_match'];
                            }
                            $singleq['match'][$field] = $tmp;
                            $doc['query']['dis_max']['queries'][] = $singleq;
                        }
                    }
                }
                
            } else {
                
                if(isset($query['key']) && $query['key'] && isset($query['fields']) && !empty($query['fields'])) {
                    $doc['query']['multi_match'] = array();
                    $doc['query']['multi_match']['query'] = $query['key'];
                    $doc['query']['multi_match']['fields'] = array();
                    foreach($query['fields'] as $fieldInfo) {
                        if(!isset($fieldInfo['field'])) {
                            continue;
                        }
                        $field = $fieldInfo['field'];
                        if(isset($fieldInfo['weight'])) {
                            $field .= "^". $fieldInfo['weight'];
                        }
                        $doc['query']['multi_match']['fields'][] = $field;
                    }
                    if(isset($query['type']) && !empty($query['type'])) {
                        $doc['query']['multi_match']['type'] = $query['type'];
                    }
                    if(isset($query['tie_breaker']) && !empty($query['tie_breaker'])) {
                        $doc['query']['multi_match']['tie_breaker'] = $query['tie_breaker'];
                    }   
                    if(isset($query['minimum_should_match']) && !empty($query['minimum_should_match'])) {
                        $doc['query']['multi_match']['minimum_should_match'] = $query['minimum_should_match'];
                    }
                }
                
            }
            if(empty($doc['query'])) {
                $doc['query']['match_all'] = array();
            }
        } else {
            $doc['query']['match_all'] = array();
        }

        if(is_array($filter) && !empty($filter)) {
            $doc['filter'] = array();
            $doc['filter']['bool'] = array();
            if(isset($filter['must']) && !empty($filter['must'])) {
                $doc['filter']['bool']['must'] = array();
                foreach($filter['must'] as $fieldInfo) {
                    if(!isset($fieldInfo['field'])) {
                        continue;
                    }
                    $field = array('term' => array($fieldInfo['field'] => $fieldInfo['value']));
                    $doc['filter']['bool']['must'][] = $field;
                }
            }
            if(isset($filter['must_not']) && !empty($filter['must_not'])) {
                $doc['filter']['bool']['must_not'] = array();
                foreach($filter['must_not'] as $fieldInfo) {
                    if(!isset($fieldInfo['field'])) {
                        continue;
                    }
                    $field = array('term' => array($fieldInfo['field'] => $fieldInfo['value']));
                    $doc['filter']['bool']['must_not'][] = $field;
                }
            }
            if(isset($filter['must_in']) && !empty($filter['must_in'])) {
                if(!isset($doc['filter']['bool']['must'])) {
                    $doc['filter']['bool']['must'] = array();
                }
                $must_or = array('or' => array());
                foreach($filter['must_in'] as $fieldInfo) {
                    if(!isset($fieldInfo['field'])) {
                        continue;
                    }
                    $values = explode(',', $fieldInfo['value']);
                    foreach($values as $value) {
                        $field = array('term' => array($fieldInfo['field'] => $value));
                        $must_or['or'][] = $field;
                    }
                }
                $doc['filter']['bool']['must'][] = $must_or;
            }
            if(isset($filter['must_or']) && !empty($filter['must_or'])) {
                if(!isset($doc['filter']['bool']['must'])) {
                    $doc['filter']['bool']['must'] = array();
                }
                $must_or = array('or' => array());
                foreach($filter['must_or'] as $fieldInfo) {
                    if(!isset($fieldInfo['field'])) {
                        continue;
                    }
                    $must_or['or'][] = array('term' => array($fieldInfo['field'] => $fieldInfo['value']));
                }
                $doc['filter']['bool']['must'][] = $must_or;
            }
            if(isset($filter['must_or_s']) && !empty($filter['must_or_s'])) { //(sender=$sender and receiver=$receiver) or (sender=$receiver and receiver=$sender)
                if(!isset($doc['filter']['bool']['must'])) {
                    $doc['filter']['bool']['must'] = array();
                }
                foreach($filter['must_or_s'] as $fieldInfos) {
                    $must_or = array('or' => array());
                    foreach($fieldInfos as $fieldInfo) {
                        if(!isset($fieldInfo['field'])) {
                            continue;
                        }
                        $must_or['or'][] = array('term' => array($fieldInfo['field'] => $fieldInfo['value']));
                    }
                    $doc['filter']['bool']['must'][] = $must_or;
                }
            }
            if(isset($filter['must_not_in']) && !empty($filter['must_not_in'])) {
                if(!isset($doc['filter']['bool']['must_not'])) {
                    $doc['filter']['bool']['must_not'] = array();
                }
                foreach($filter['must_not_in'] as $fieldInfo) {
                    if(!isset($fieldInfo['field'])) {
                        continue;
                    }
                    $values = explode(',', $fieldInfo['value']);
                    foreach($values as $value) {
                        $field = array('term' => array($fieldInfo['field'] => $value));
                        $doc['filter']['bool']['must_not'][] = $field;
                    }
                }
            }
            if(isset($filter['range_must']) && !empty($filter['range_must'])) {
                if(!isset($doc['filter']['bool']['must'])) {
                    $doc['filter']['bool']['must'] = array();
                }
                foreach($filter['range_must'] as $rangeInfo) {
                    if(!isset($rangeInfo['field']) || (!isset($rangeInfo['from']) && !isset($rangeInfo['to']))) {
                        continue;
                    }
                    $range = array();
                    $range['range'] = array();
                    $field = $rangeInfo['field'];
                    $range['range'][$field] = array();
                    if(isset($rangeInfo['from'])) {
                        $range['range'][$field]['from'] = $rangeInfo['from'];
                    }
                    if(isset($rangeInfo['to'])) {
                        $range['range'][$field]['to'] = $rangeInfo['to'];
                    }
                    if(isset($rangeInfo['include_lower'])) {
                        $range['range'][$field]['include_lower'] = $rangeInfo['include_lower'];
                    }
                    if(isset($rangeInfo['include_upper'])) {
                        $range['range'][$field]['include_upper'] = $rangeInfo['include_upper'];
                    }
                    array_push($doc['filter']['bool']['must'], $range);
                }
            }
            if(isset($filter['range_must_not']) && !empty($filter['range_must_not'])) {
                if(!isset($doc['filter']['bool']['must_not'])) {
                    $doc['filter']['bool']['must_not'] = array();
                }
                
                foreach($filter['range_must_not'] as $rangeInfo) {
                    if(!isset($rangeInfo['field']) || (!isset($rangeInfo['from']) && !isset($rangeInfo['to']))) {
                        continue;
                    }
                    $range = array();
                    $range['range'] = array();
                    $field = $rangeInfo['field'];
                    $range['range'][$field] = array();
                    if(isset($rangeInfo['from'])) {
                        $range['range'][$field]['from'] = $rangeInfo['from'];
                    }
                    if(isset($rangeInfo['to'])) {
                        $range['range'][$field]['to'] = $rangeInfo['to'];
                    }
                    if(isset($rangeInfo['include_lower'])) {
                        $range['range'][$field]['include_lower'] = $rangeInfo['include_lower'];
                    }
                    if(isset($rangeInfo['include_upper'])) {
                        $range['range'][$field]['include_upper'] = $rangeInfo['include_upper'];
                    }
                    array_push($doc['filter']['bool']['must_not'], $range);
                }
            }
        }
        
        if(is_array($sort) && !empty($sort)) {
            $doc['sort'] = array();
            foreach($sort as $sortInfo) {
                if(!is_array($sortInfo)) {
                    $doc['sort'][] = $sortInfo;
                    continue;
                }
                if(!isset($sortInfo['field']) || !in_array(strtolower($sortInfo['order']), array('asc', 'desc'))) {
                    continue;
                }
                $key = $sortInfo['field'];
                $value = strtolower($sortInfo['order']);
                $sortItem = array($key => array('order' => $value));
                $doc['sort'][] = $sortItem;
            }
        }
        
        if(is_array($limit) && !empty($limit) && isset($limit['size'])) {
            $doc['size'] = $limit['size'];
            if(isset($limit['from']) && intval($limit['from']) >= 0) {
                $doc['from'] = $limit['from'];
            }
        }
        
        if(is_array($highlight) && !empty($highlight)) {
            $doc['highlight'] = array();
            if(isset($highlight['pre_tags']) && !empty($highlight['pre_tags'])) {
                $doc['highlight']['pre_tags'] = array();
                foreach($highlight['pre_tags'] as $pre) {
                    $doc['highlight']['pre_tags'][] = $pre;
                }
            }
            if(isset($highlight['post_tags']) && !empty($highlight['post_tags'])) {
                $doc['highlight']['post_tags'] = array();
                foreach($highlight['post_tags'] as $post) {
                    $doc['highlight']['post_tags'][] = $post;
                }
            }
            if(isset($highlight['fields']) && !empty($highlight['fields'])) {
                $doc['highlight']['fields'] = array();
                foreach($highlight['fields'] as $fieldInfo) {
                    $field = $fieldInfo['field'];
                    $doc['highlight']['fields'][$field] = array('force_source' => 'false');
                }
            }
        }
        
        if(is_array($facets) && !empty($facets)) {
            $doc['facets'] = array();
            foreach($facets as $key => $value) {
                $doc['facets'][$key] = array();
                if(is_array($value)) {
                    if(isset($value['type']) && $value['type'] == 'terms_stats') {
                        $doc['facets'][$key]['terms_stats'] = array();
                        $tmp = array();
                        $tmp['key_field'] = $value['key_field'];
                        $tmp['value_field'] = $value['value_field'];
                        if(isset($value['size'])) {
                                $tmp['size'] = $value['size'];
                        }
                        $doc['facets'][$key]['terms_stats'][] = $tmp;
                    } else {
                        $doc['facets'][$key]['terms'] = array();
                        foreach($value as $v) {
                            if(is_array($v)) {
                                $tmp = isset($v['size']) ? array('field' => $v['field'], 'size' => $v['size']) : array('field' => $v['field']);
                                $doc['facets'][$key]['terms'][] = $tmp;
                            } else {
                               $doc['facets'][$key]['terms'][] = array('field' => $v); 
                            }
                        }
                    }
                } else {
                    $doc['facets'][$key]['terms'][] = array('field' => $value);
                }
                $doc['facets'][$key]['global'] = 'false';
                if(isset($doc['filter']) && !empty($doc['filter'])) {
                    $doc['facets'][$key]['facet_filter'] = $doc['filter'];
                }
            }
        }
        
        $jsonStr    = self::encodeJson($doc);
//        file_put_contents('/tmp/kfzsearch.log', "\n". $jsonStr, FILE_APPEND);
        
        if($isDebug) {
            echo '<pre>';print_r($doc);
            echo "<hr/>";
            echo $jsonStr;exit;
        }
        
        if(!trim($jsonStr)) {
            return false;
        }
        $result     = self::execCurl($url, 'POST', $jsonStr, $timeout);
        $resultSet  = json_decode($result, true);
//        var_dump($resultSet);exit;
        return $resultSet;
    }
    
    /*
     * 查询记录(json格式传参)
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param array  $queryStr  查询json语句
     */
    static public function findDocumentByJson($ip, $port, $indexName, $indexType, $queryStr, $timeout = 0, $dfs_query_then_fetch = false)
    {
        if($dfs_query_then_fetch) {
            $url    = "{$ip}:{$port}/{$indexName}/{$indexType}/_search?preference=_primary_first&search_type=dfs_query_then_fetch";
        } else {
            $url    = "{$ip}:{$port}/{$indexName}/{$indexType}/_search";
        }
        
        if(!trim($queryStr)) {
            return false;
        }
        $result     = self::execCurl($url, 'POST', trim($queryStr), $timeout);
        $resultSet  = json_decode($result, true);
//        var_dump($resultSet);exit;
        return $resultSet;
    }
    
    /**
     * 格式化查询结果
     * 
     * @param array $findResult
     * @return array
     */
    static public function trunslateFindResult($findResult)
    {
        $result = array(
            'total' => 0,
            'data'  => array(),
            'status' => TRUE
        );
        if(!$findResult) {
            return $result;
        }
        if(!isset($findResult['hits']) || !isset($findResult['hits']['total'])) {
            self::$errorInfo = 'error!';
            $result['status'] = FALSE;
        }
        $result['total'] = isset($findResult['hits']) ? $findResult['hits']['total'] : 0;
        if($result['total'] == 0) {
            return $result;
        }
        foreach($findResult['hits']['hits'] as $hitRow) {
            $tRow = array();
            $tRow['id'] = $hitRow['_id'];
            foreach($hitRow['_source'] as $k => $v) {
                $tRow[$k] = $v;
            }
            if(isset($hitRow['highlight'])) {
                foreach($hitRow['highlight'] as $k => $v) {
                    $key = $k. '_highlight';
                    $tRow[$key] = $v[0];
                }
            }
            $result['data'][] = $tRow;
        }
        return $result;
    }
    
    /**
     * 查询记录
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     * @param string $indexType 索引类型
     * @param array  $fields    
     *                      例：array('field1', 'field2')
     * @param array  $whereData 
     *                      例：
     * array(
            //key1 LIKE '%value1 value2%' => {"query":{"match_phrase":{"key1":"value1 value2"}}}
            array(
                array('k' => 'key1', 'v' => 'value1 value2')
            ),
            //key1=20   =>  {"query":{"match":{"key1":"20"}}}
            array(
                array('k' => 'key1', 'v' => 20)
            ),
            //key1 LIKE '%value1%' OR key1 LIKE '%value2%' => {"query":{"match":{"key1":"value1 value2"}}}
            array(
                array('k' => 'key1', 'v' => 'value1'),
                array('k' => 'key1', 'v' => 'value2'),
                's' => 'or'
            ),
            //key1 LIKE '%value1%' AND key1 LIKE '%value2%' => {"query":{"bool":{"must":[{"match":{"key1":"value1"}},{"match":{"key1":"value2"}}]}}}
            array(
                array('k' => 'key1', 'v' => 'value1'), 
                array('k' => 'key1', 'v' => 'value2'),
                's' => 'bool_and'
            ),
            //key1 LIKE '%value1%' OR key1 LIKE '%value2%' => {"query":{"bool":{"should":[{"match":{"key1":"value1"}},{"match":{"key1":"value2"}}]}}}
            array(
                array('k' => 'key1', 'v' => 'value1'),
                array('k' => 'key1', 'v' => 'value2'),
                's' => 'bool_or'
            ),
            //key1 NOT LIKE '%value1%' AND key1 NOT LIKE '%value2%' => {"query":{"bool":{"must_not":[{"match":{"key1":"value1"}},{"match":{"key1":"value2"}}]}}}
            array(
                array('k' => 'key1', 'v' => 'value1'), 
                array('k' => 'key1', 'v' => 'value2'),
                's' => 'bool_and_not'
            ),
            //key1 LIKE '%value1%' AND key2 NOT LIKE '%value2%' => {"query":{"bool":{"must":[{"match":{"key1":"value1"}}],"must_not":[{"match":{"key2":"value2"}}]}}}
            array(
                array('k' => 'key1', 'v' => 'value1', 's' => '='), 
                array('k' => 'key2', 'v' => 'value2', 's' => '!='),
                's' => 'bool_x'
            )
            //key1>=20 AND key1<=30  =>  {"query":{"filtered":{"query":{"match_all":{}},"filter":{"range":{"key1":{"gte":20,"lte":30}}}}}
            array(
                array('k' => 'key1', 'v' => 20, 's' => 'gte'),
                array('k' => 'key1', 'v' => 30, 's' => 'lte'),
                's' => 'range'
            )
        )
     * @param array  $orderData
     *                      例：array("key" => "key1", "value" => "desc")
     * @param array  $limitData
     *                      例：array("from" => '', "size" => '')
     */
    /*
    static public function findDocument($ip, $port, $indexName, $indexType, $fields = array(), $whereData = array(), $orderData = array(), $limitData = array())
    {
        $url    = "{$ip}:{$port}/{$indexName}/{$indexType}/_search";
        $doc    = array();
        if(is_array($fields) && !empty($fields)) {
            $doc['_source'] = array();
            foreach($fields as $field) {
                $doc['_source'][] = $field;
            }
        }
        if(is_array($whereData) && !empty($whereData)) {
            $doc['query'] = array();
            foreach($whereData as $cell) {
                if(!is_array($cell)) {
                    continue;
                }
                if(!isset($cell['s'])) {
                    $doc['query']['match_phrase'] = array(
                        $cell['k'] => $cell['v']
                    );
                } else {
                    switch($cell['s']) {
                        case 'or':
                            if(!isset($doc['query']['filtered']) || !isset($doc['query']['filtered']['filter']) || !isset($doc['query']['filtered']['filter']['or'])) {
                                $doc['query']['filtered']['filter']['or'] = array();
                            }
                            foreach($cell as $w) {
                                if(!is_array($w)) {
                                    continue;
                                }
                                if(!isset($doc['query']['filtered']['filter']['or']['filters'])) {
                                    $doc['query']['filtered']['filter']['or']['filters'] = array();
                                }
                                $doc['query']['filtered']['filter']['or']['filters'][] = array('term' => array($w['k'] => $w['v']));
                            }
                            break;
                        case 'bool_and':
                            foreach($cell as $w) {
                                if(!is_array($w)) {
                                    continue;
                                }
                                $doc['query']['bool']['must'][] = array('match' => array($w['k'] => $w['v']));
                            }
                            break;
                        case 'bool_or':
                            foreach($cell as $w) {
                                if(!is_array($w)) {
                                    continue;
                                }
                                $doc['query']['bool']['should'][] = array('match' => array($w['k'] => $w['v']));
                            }
                            break;
                        case 'bool_and_not':
                            foreach($cell as $w) {
                                if(!is_array($w)) {
                                    continue;
                                }
                                $doc['query']['bool']['must_not'][] = array('match' => array($w['k'] => $w['v']));
                            }
                            break;
                        case 'bool_x':
                            foreach($cell as $w) {
                                if(!is_array($w)) {
                                    continue;
                                }
                                if($w['s'] == '=') {
                                    $doc['query']['bool']['must'][] = array('match' => array($w['k'] => $w['v']));
                                }elseif($w['s'] == '!=') {
                                    $doc['query']['bool']['must_not'][] = array('match' => array($w['k'] => $w['v']));
                                }
                            }
                            break;
                        case 'range':
                            if(!isset($doc['query']['filtered']) || !isset($doc['query']['filtered']['filter']) || !isset($doc['query']['filtered']['filter']['range'])) {
                                $doc['query']['filtered']['filter']['range'] = array();
                            }
                            foreach($cell as $w) {
                                if(!is_array($w)) {
                                    continue;
                                }
                                $k = $w['k'];
                                if(!isset($doc['query']['filtered']['filter']['range'][$k])) {
                                    $doc['query']['filtered']['filter']['range'][$k] = array();
                                }
                                if($w['s'] == 'gte') {
                                    $doc['query']['filtered']['filter']['range'][$k]['gte'] = $w['v'];
                                }elseif($w['s'] == 'lte') {
                                    $doc['query']['filtered']['filter']['range'][$k]['lte'] = $w['v'];
                                }
                            }
                            break;
                    }
                }
            }
            if(empty($doc['query'])) {
                $doc['query']['match_all'] = array();
            }
        } else {
            $doc['query']['match_all'] = array();
        }
        if(is_array($orderData) && !empty($orderData) && isset($orderData['key']) && $orderData['key'] && isset($orderData['value']) && $orderData['value'] && in_array(strtoupper($orderData['value']), array('DESC', 'ASC'))) {
//            $doc['track_scores'] = true;
            $k = $orderData['key'];
            $v = $orderData['value'];
            $doc['sort'][$k] = array("order" => $v);
        }
        if(is_array($limitData) && !empty($limitData) && isset($limitData['size']) && intval($limitData['size']) > 0) {
            $doc['size'] = $limitData['size'];
            if(isset($limitData['from']) && intval($limitData['from']) >= 0) {
                $doc['from'] = $limitData['from'];
            }
        }
//        echo '<pre>';print_r($doc);exit;
        $jsonStr    = self::encodeJson($doc);
        echo $jsonStr;exit;
        if(!trim($jsonStr)) {
            return false;
        }
        $result     = self::execCurl($url, 'POST', $jsonStr);
        $resultSet  = json_decode($result, true);
//        var_dump($resultSet);exit;
        if(is_array($resultSet) && isset($resultSet['hits'])) {
            return $resultSet;
        } else {
            return false;
        }
    }
    */
    
    /*
     * 刷新索引
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     * @param string $indexName 索引名
     */
    static public function flushIndex($ip, $port, $indexName)
    {
        $url       = "{$ip}:{$port}/{$indexName}/_flush";
        $result    = self::execCurl($url, 'POST');
        $resultSet = json_decode($result, true);
        if(is_array($resultSet) && isset($resultSet['_shards'])) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 获取负载信息
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     */
    static public function getLoadInfo($ip, $port)
    {
        $url = "{$ip}:{$port}/_cat/nodes?h=load";
        $result    = self::execCurl($url, 'GET');
        return $result;
    }
    
    /**
     * 获取线程池信息
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     */
    static public function getThreadPool($ip, $port, $isFormat = false)
    {
        $url = "{$ip}:{$port}/_cat/thread_pool?v";
        $result    = self::execCurl($url, 'GET');
        if($result && $isFormat) {
            $threadTmpArr = explode(' ', $result);
            $threadKeys = array('host', 'ip', 'bulk.active', 'bulk.queue', 'bulk.rejected', 'index.active', 'index.queue', 'index.rejected', 'search.active', 'search.queue', 'search.rejected');
            $threadPoolResult = array();
            $threadPoint = 0;
            foreach($threadTmpArr as $item) {
                if($item === '') {
                    continue;
                }
                if($threadPoint == 0) {
                    $singleResult = array();
                } 
                $key = $threadKeys[$threadPoint];
                $singleResult[$key] = trim($item);
                ++$threadPoint;
                if ($threadPoint == count($threadKeys)) {
                    $threadPoolResult[] = $singleResult;
                    $threadPoint = 0;
                }
            }
            $result = $threadPoolResult;
        }
        return $result;
    }
    
    /**
     * 获取各节点信息
     * 
     * @param int    $ip        IP
     * @param int    $port      端口
     */
    static public function getNodesInfo($ip, $port, $isFormat = false)
    {
        $url = "{$ip}:{$port}/_cat/nodes?v";
        $result    = self::execCurl($url, 'GET');
        if($result && $isFormat) {
            $nodesTmpArr = explode(' ', $result);
            $nodeKeys = array('host', 'ip', 'heap.percent', 'ram.percent', 'load', 'node.role', 'master', 'name');
            $nodesResult = array();
            $nodePoint = 0;
            foreach($nodesTmpArr as $item) {
                if($item === '') {
                    continue;
                }
                if($nodePoint == 0) {
                    $singleResult = array();
                } 
                $key = $nodeKeys[$nodePoint];
                $singleResult[$key] = trim($item);
                ++$nodePoint;
                if ($nodePoint == count($nodeKeys)) {
                    $nodesResult[] = $singleResult;
                    $nodePoint = 0;
                }
            }
            $result = $nodesResult;
        }
        return $result;
    }
    
    /**
     * 获取分词结果
     * 
     * @param string $ip
     * @param int    $port
     * @param string $index
     * @param string $text
     * @return json
     */
    static public function getSegwords($ip, $port, $index, $text)
    {
        $url = "{$ip}:{$port}/{$index}/_analyze?analyzer=mmseg";
        $result    = self::execCurl($url, 'GET', $text);
        return $result;
    }
    
    static public function optimize($ip, $port, $index, $action)
    {
        if($action == 'o') {
            $url       = "{$ip}:{$port}/{$index}/_optimize";
        } elseif ($action == 'd') {
            $url       = "{$ip}:{$port}/{$index}/_optimize?only_expunge_deletes=true";
        } elseif ($action == 's') {
            $url       = "{$ip}:{$port}/{$index}/_optimize?max_num_segments=1";
        } else {
            return false;
        }
        $result    = self::execCurl($url, 'POST', '', '86400');
        $resultSet = json_decode($result, true);
        if(is_array($resultSet) && isset($resultSet['_shards'])) {
            return true;
        } else {
            return false;
        }
    }
    
    static public function refresh($ip, $port, $index)
    {
        $url = "{$ip}:{$port}/{$index}/_refresh";
        $result    = self::execCurl($url, 'GET', '', '86400');
        $resultSet = json_decode($result, true);
        if(is_array($resultSet) && isset($resultSet['_shards']) && $resultSet['_shards']['total'] == $resultSet['_shards']['successful']) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 获取拼音结果
     * 
     * @param string $ip
     * @param int    $port
     * @param string $index
     * @param string $text
     * @return json
     */
    static public function getPinyin($ip, $port, $index, $text)
    {
        $url = "{$ip}:{$port}/{$index}/_analyze?analyzer=pinyin";
        $result    = self::execCurl($url, 'GET', $text);
        return $result;
    }
    
    //获取错误信息
    static public function getErrorInfo()
    {
        return self::$errorInfo;
    }
    
}