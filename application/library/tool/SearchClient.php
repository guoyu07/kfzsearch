<?php

//require_once 'unihan.php';
//require_once 'searchcache.php';

/**
 * 搜索客户端类， author: liuxingzhi @2013.9
 */

class Tool_SearchClient 
{
    private $servers;
    private $connobj;
    private $queries;
    private $isPersistent;
    private $errorInfo;
    private $scws;
    private $cache;
    private $queryRestriction;
    private $isQueryRest;
    
    /**
     * 搜索服务的客户端类
     * @param string/array $servers:可以是字符串（单个server）或数组（多个server），格式为: host[:port[:weight]] port默认为9306
     * @param bool $isPersistent:   指明是否采用持久连接。
     * @param array  $cacheServers  memcached服务地址，格式为：
     *                              $cacheServers = array(
     *                                         array('host'=>'127.0.0.1','port'=>11211,'weight'=>1), 
     *                                         array('host'=>'127.0.0.1','port'=>11212,'weight'=>1))
     * @param string  $keyPrefix    key的前缀。
     * @param boolean $distribution 是否采用分布式cache。
     * @param boolean $isQueryRest  是否做查询限制
     * @param string  $cacheType    缓存类型
     */
    public function __construct($servers, $isPersistent=false, $cacheServers='', $keyPrefix='', $distribution=true, $isQueryRest=true, $cacheType = 'ssdb') 
    {
        if(is_string($servers)) {
            $this->servers[0] = $this->parseServer($servers);
        } else if(is_array($servers)) {
            foreach($servers as $i => $server) {
               $this->servers[$i] = $this->parseServer($server);
            }
        } else {
            $this->servers[0]['host'] = '127.0.0.1';
            $this->servers[0]['port'] = 9306;
            $this->servers[0]['weight'] = 1;
        }
        
        $this->isPersistent = $isPersistent;
        $this->connobj = NULL;
        $this->queries = array();
        $this->errorInfo = '';
        
        // 分词初始化...
        $charset = ini_get('scws.default.charset');
        if($charset === false || $charset == 'utf-8')
            $charset = 'utf8';

        $dictdir = ini_get('scws.default.fpath');
        if($dictdir === false)
            $dictdir = '/usr/local/etc';

        $dict = $dictdir . "/kfz_dict.xdb";
        //$rule = $dictdir . "/kfz_rules.ini";

        if(($sh = scws_open()) === false) {
            $this->errorInfo = "scws open error.";
            throw new Exception($this->errorInfo);
        }
        
        scws_set_charset($sh, $charset);        
        if(scws_add_dict($sh,$dict,SCWS_XDICT_XDB) === false) {
            scws_close($sh);
            $this->errorInfo = "scws add dict error.";
            throw new Exception($this->errorInfo);
        }
        
        //scws_set_multi($sh, SCWS_MULTI_SHORT);
        $this->scws = $sh;
        
        // search cache初始化
        $this->cache = NULL;
        if(!empty($cacheServers)) {
            $this->cache = new Tool_SearchCache($cacheServers,$cacheType,$keyPrefix,$distribution);
            if($this->cache->getConnectStatus() === false) {
                $this->cache = null;
            }
        }
        
        //查询限制LIMIT
        $this->isQueryRest = $isQueryRest;
        if($isQueryRest) {
            $this->queryRestriction = array(
                'offset' => '10000',
                'max' => '200'
            );
        }
    }
    
    public function __destruct() 
    {
        scws_close($this->scws);
        if($this->isPersistent === false)
            $this->connobj = NULL;
    }
    
    private function parseServer($server) 
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

    public function getErrorInfo()
    {
        return $this->errorInfo;
    }
    
    /** 
     * 设置语句选项
     * @param array $option: 关联数组，optionName => optionValue
     * @param int/array $stmtID: 指明语句ID，可选，默认为0。如果想为多个查询语句设置相同的选项，可以设置stmtID为数组。
     * @return boolean
     */
    public function setStmtOption($option,$stmtID=0) 
    {
        $validOptions = array('agent_query_timeout', 'boolean_simplify', 'comment', 'cutoff', 'field_weights',
                              'global_idf', 'idf', 'index_weights', 'max_matches', 'max_query_time', 'ranker',
                              'retry_count', 'retry_delay', 'reverse_scan', 'sort_method');
        
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                foreach($option as $optname => $optvalue) {
                    if(!in_array($optname, $validOptions)) {
                        $this->errorInfo = 'Option [{$optname}] is invalid';
                        return false;
                    }
                    $this->queries[$id]['option'][$optname] = $optvalue; 
                }
            }
        } else {
           foreach($option as $optname => $optvalue) {
                if(!in_array($optname, $validOptions)) {
                    $this->errorInfo = 'Option [{$optname}] is invalid';
                    return false;
                }
                $this->queries[$stmtID]['option'][$optname] = $optvalue; 
            }
        }
       
        return true;
    }
    
    /**
     * 设置select语句中Column list 
     * @param string $colList: 逗号分隔的select表达式列表，比如: id, group_id*123+456 AS expr1
     * @param int/array $stmtID
     */
    public function setStmtColumnList($colList,$stmtID=0)
    {
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $this->queries[$id]['select'] = $colList; 
            }
        } else {
            $this->queries[$stmtID]['select'] = $colList; 
        }
    }
    
    /**
     * 设置查询索引
     * @param string $index: 逗号分隔的索引列表，比如: index1,index2,...
     * @param int/array $stmtID
     */
    public function setStmtQueryIndex($index,$stmtID=0)
    {
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
               $this->queries[$id]['index'] = $index;  
            }
        } else {
            $this->queries[$stmtID]['index'] = $index; 
        }
    }
    
    /**
     * 设置过滤表达式，用于对搜索结果进行过滤，支持过滤操作符: =, !=, <, >, <=, >=, IN, AND, NOT, BETWEEN
     * @param string $where: 用于过滤的where条件。
     * @param int/array $stmtID
     */
    public function setStmtFilter($where, $stmtID=0) 
    {
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $this->queries[$id]['where'] = $where; 
            }
        } else {
            $this->queries[$stmtID]['where'] = $where; 
        }
    }
    
    // 此方法依赖于mbstring扩展。
    private function fan2jian($value)
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
     * 分词方法，用于对用户输入的查询内容进行切分和特殊字符转义表示。
     */
    public function segwords($query, $mode = 0)
    {
        $from = array ( '\\', '(',')','|','-','!','@','~','"','&', '/', '^', '$', '=' , '<', '?');
        $to   = array ( '\\\\', '\(','\)','\|','\-','\!','\@','\~','\"', '\&', '\/', '\^', '\$', '\=', '\<',' ' );
       
        if($query === '' || $query === NULL)
            return '';
        
        // 把繁体转换为简体
        $query = $this->fan2jian($query);
        
        // 进行分词处理...
        if($mode == 1) { //单字模式
            $ulen = mb_strlen($query, 'UTF-8');
            $arr = array();
            $tmp = '';
            for ($i = 0; $i < $ulen; $i++) {
                $s = mb_substr($query, $i, 1, 'UTF-8');
                if (preg_match('/[0-9a-z]/is', $s)) {
                    $tmp .= $s;
                    continue;
                } else {
                    if ($tmp) {
                        $arr[] = $tmp;
                        $tmp = '';
                    }
                    $arr[] = $s;
                }
            }
            $r = implode(' ', $arr);
        } else {
            scws_send_text($this->scws, $query);
            $r = '';
            while ($words = scws_get_result($this->scws)) {
              foreach($words as $word) {
                $r .= $word['word'];
                $r .= ' ';
              }
            }
        }
        $query = rtrim($r);
        
        // 再对其中的特殊字符进行转义表示
        $query = str_replace($from, $to, $query);
        return $query;
    }
    
    /**
     * 设置查询表达式，查询表达式中关键词需要进行分词、特殊字符转义等处理。
     * @param string $qryexpr: 查询表达式
     * @param int/array $stmtID
     */
    public function setStmtQuery($qryexpr, $stmtID=0)
    {
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $this->queries[$id]['query'] = $qryexpr; 
            }
        } else {
            $this->queries[$stmtID]['query'] = $qryexpr; 
        }
    }
    
    /**
     * 设置分组
     * @param string $colname: 用于分组的列。
     * @param string $within: 设置group内部的排序方式，格式为: colname DESC/ASC
     * @param int/array $stmtID
     */
    public function setStmtGroupBy($colname, $within='', $stmtID=0)
    {
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $this->queries[$id]['groupby'] = $colname;
                if(!empty($within))
                    $this->queries[$id]['within'] = $within;
            }
        } else {
            $this->queries[$stmtID]['groupby'] = $colname;
            if(!empty($within))
                $this->queries[$stmtID]['within'] = $within;
        }
    }
    
    /**
     * 设置排序方式
     * @param string $sort: 设置排序方式，可以指定多列排序，格式为: col1 DESC/ASC, col2 DESC/ASC, ...
     * @param int/array $stmtID
     */
    public function setStmtOrderBy($sort, $stmtID=0)
    {
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $this->queries[$id]['orderby'] = $sort; 
            }
        } else {
            $this->queries[$stmtID]['orderby'] = $sort; 
        }
    }
    
    /**
     * 设置查询结果limit
     * @param int $offset
     * @param int $max
     * @param int/array $stmtID
     */
    public function setStmtLimit($offset=0, $max=10, $stmtID=0)
    {
        if($this->isQueryRest) {
            $queryRest = $this->queryRestriction;
            if($offset > $queryRest['offset'] || $max > $queryRest['max']) {
                $this->errorInfo = 'Limit [{$offset}, {$max}] is invalid';
            }
            $offset = $offset > $queryRest['offset'] ? $queryRest['offset'] : $offset;
            $max = $max > $queryRest['max'] ? $queryRest['max'] : $max;
        }
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $this->queries[$id]['offset'] = $offset;
                $this->queries[$id]['max'] = $max;    
            }
        } else {
            $this->queries[$stmtID]['offset'] = $offset;
            $this->queries[$stmtID]['max'] = $max;    
        }
    }
    
    /**
     * 设置查询语句的结果的缓存时间，如果没有设置则采用构造函数中全局设置。
     * 暂不能使用。
     */
    public function setStmtExpire($expire, $stmtID=0)
    {
        if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $this->queries[$id]['expire'] = intval($expire);  
            }
        } else {
            $this->queries[$stmtID]['expire'] = intval($expire); 
        }
    }
    
    /**
     * 重置指定语句的设置。
     * @param int/string/array $stmtID: 取值为'*'表示重置所有语句。
     */
    public function resetStmt($stmtID=0)
    {
        if(is_array($stmtID)) {
             foreach ($stmtID as $id) {
                 $this->queries[$id] = array();
             }
        } else if($stmtID == '*') {
            $this->queries = array();
        } else {
            $this->queries[$stmtID] = array();
        }
    }
    
    private function getSelectStmt($query) 
    {
        $defaultOptions = array('max_matches' => 1000, 'ranker' => 'sph04');
        foreach($defaultOptions as $optname => $optvalue) {
            if(!isset($query['option'][$optname])) {
                $query['option'][$optname] = $optvalue; 
            }
        }
        
        $sql = "SELECT {$query['select']} FROM {$query['index']}";
        
        if(isset($query['where']) || isset($query['query'])) {
            $sql .= ' WHERE ';
            if(isset($query['query'])) {
                $sql .= "MATCH({$query['query']})";
                if(isset($query['where'])) {
                    $sql .= ' AND ';
                }
            }

            if(isset($query['where'])) {
                $sql .= $query['where'];
            }
        }
        
        if(isset($query['groupby'])) {
            $sql .= " GROUP BY {$query['groupby']}";
            if(isset($query['within'])) {
                $sql .= " WITHIN GROUP ORDER BY {$query['within']}";
            }
        }
        
        if(isset($query['orderby'])) {
            $sql .= " ORDER BY {$query['orderby']}";
        }
        
        if(isset($query['offset']) && isset($query['max'])) {
            $sql .= " limit {$query['offset']}, {$query['max']}";
        }
        
        if(isset($query['option'])) {
            $sql .= ' OPTION ';
            foreach($query['option'] as $optname => $optvalue) {
                $sql .= "{$optname} = {$optvalue},";
            }
            
            $sql = trim($sql, ',');
        }
        
        return $sql;
    }
    
    private function getActiveServers()
    {
        $totalweigth = 0;
        foreach($this->servers as $server) {
            if(isset($server['status']) && $server['status'] === 'failed') {
                continue;
            }
           $totalweigth += $server['weight'];
        }
        
        $activeServers = array();
        $start = 1;
        foreach($this->servers as $id => $server) {
            if(isset($server['status']) && $server['status'] === 'failed') {
                continue;
            }
            
            $weight = $server['weight'];
            $server['min'] = $start;
            $server['max'] = intval($start + $weight/$totalweigth * 100 - 1);
            $activeServers[$id] = $server;
            $start = $server['max'] + 1;
        }
        
        return $activeServers;
    }
    
    // 从活动的search servers中根据权重选择一个server进行连接
    private function connect()
    {
        $activeServers = $this->getActiveServers();
        if(empty($activeServers)) {
            $this->errorInfo = 'all search servers is down';
            return NULL;
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
        $dsn = "mysql:host={$host}; port={$port}";
        
        $connectNum = 2; //连接失败后再重连一次，最多两次。
        do {
            try {
                if($this->isPersistent)
                    $this->connobj = new PDO($dsn, '', '', array(PDO::ATTR_PERSISTENT => true));
                else
                    $this->connobj = new PDO($dsn, '', '', array(PDO::ATTR_PERSISTENT => false));
                $this->connobj->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
                $this->connobj->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); 
                return true;
            } catch (PDOException $e) {
                $connectNum--;
                if($connectNum == 0) {
                    $this->servers[$serverID]['status'] = 'failed';
                    $this->errorInfo = $e->getMessage();
                    return false;
                }
            }
        } while($connectNum > 0);
    }
    
    /**
     * 查询，支持多语句查询。
     * @param int/string/array $stmtID: 指明查询哪些语句，取值为*表示一次执行所有查询语句。
     * @param int $expire: 指明是否缓存该语句的查询结果，以及缓存时间，默认为-1，不使用缓存，0表示永不过期。
     * @return array: 正确返回结果集，如果多语句查询则返回多维结果集数组(下标为语句ID)，错误返回false。
     * 结果集中 total表示实际返回的结果数、totol_found表示搜索结果总数、time表示搜索时间。
     */
    public function query($stmtID=0, $expire=-1) 
    {
        // 和搜索服务器建立连接
        if($this->connobj === NULL) {
            do {
                $r = $this->connect();
                if($r === true) break;
                if($r === NULL) return false;
            } while($r === false);
        }
        
        // 构造select查询语句
        $stmt = '';
        if(is_string($stmtID) && $stmtID == '*') {
            foreach($this->queries as $query) {
                if(empty($query)) continue;
                if(isset($query['query'])) {
                    $query['query'] = $this->connobj->quote($query['query']);  // 对用户查询内容进行引用和转义表示
                }
                $stmt .= $this->getSelectStmt($query);
                $stmt .= ';SHOW META;';
            }
            $stmt = trim($stmt, ';');
        } else if(is_array($stmtID)) {
            foreach ($stmtID as $id) {
                $query = $this->queries[$id];
                if(isset($query['query'])) {
                    $query['query'] = $this->connobj->quote($query['query']);
                }
                $stmt .= $this->getSelectStmt($query);
                $stmt .= ';SHOW META;';
            }
            $stmt = trim($stmt, ';');
        } else if(is_int($stmtID)){
            $query = $this->queries[$stmtID];
            if(isset($query['query'])) {
                $query['query'] = $this->connobj->quote($query['query']);
            }
            $stmt = $this->getSelectStmt($query);
            $stmt .= ';SHOW META';
        } else {
            $this->errorInfo = 'query statement ID type error';
            $this->connobj = NULL;
            return false;
        }
       // var_dump($stmt);exit;
//        file_put_contents('/tmp/kfzsearch.log', "【". date("Y-m-d H:i:s"). "】". $stmt. "\r\n", FILE_APPEND);
        
        // 先从cache里取
        if($this->cache !== NULL && $expire >= 0) {
            $key = sha1($stmt);
            $r = unserialize($this->cache->get($key));
            if($r) return $r;
        }
        
        // 执行查询
        $result = $this->connobj->query($stmt);
        if($result === false) {
            $e = $this->connobj->errorInfo();
            $this->errorInfo = $e[2];
            return false;
        }
        
        $resultIsEmpty = 0;
        $resultsets = array();
        do {
            $resultset = $result->fetchAll(PDO::FETCH_ASSOC);
            if(empty($resultset)) $resultIsEmpty = 1;
            if($result->nextRowset()) {
                $meta = $result->fetchAll(PDO::FETCH_ASSOC);
                foreach($meta as $info) {
                    if($info['Variable_name'] == 'total') 
                        $resultset['total'] = $info['Value'];
                    else if($info['Variable_name'] == 'total_found') 
                        $resultset['total_found'] = $info['Value'];
                    else if($info['Variable_name'] == 'time')
                        $resultset['time'] = $info['Value'];
                }
            } else {
                $this->errorInfo = "multi-query with meta error";
                return false;
            }
            array_push($resultsets, $resultset);
        } while($result->nextRowset()); // 多语句查询时指向下一个语句的结果集。如果返回false表示没有下一个结果集了。
        
        $r = array();
        if(is_int($stmtID)) {          // 单语句查询就直接返回结果集
            $r = array_pop($resultsets);
        } else if(is_array($stmtID)) { // 多语句查询则返回结果集数组，下标为stmtID
            foreach($stmtID as $i => $id) {
                $r[$id] = $resultsets[$i];
            }
        } else {                       // 所有语句查询返回结果集数组，下标为stmtID
            $j = 0;
            foreach($this->queries as $i => $query) {
                if(empty($query)) continue;
                $r[$i] = $resultsets[$j++];
            }
        }
        
        if($this->cache !== NULL && $expire >= 0 && !$resultIsEmpty) { // 如果没有空的结果集则缓存
            $this->cache->set($key, serialize($r), $expire); 
        }
        
        return $r;
    }
    
    public function execute($stmt, $expire=-1)
    {
        // 先从cache里取
        if($this->cache !== NULL && $expire >= 0) {
            $key = sha1($stmt);
            $r = $this->cache->get($key);
            if($r) return $r;
        }
        
        // 和搜索服务器建立连接       
        if($this->connobj === NULL) {
            do {
                $r = $this->connect();
                if($r === true) break;
                if($r === NULL) return false;
            } while($r === false);
        }
        
        // 执行查询
        $result = $this->connobj->query($stmt);
        if($result === false) {
            $e = $this->connobj->errorInfo();
            $this->errorInfo = $e[2];
            return false;
        }
        
        $resultIsEmpty = 0;
        $resultsets = array();
        do {
            $resultset = $result->fetchAll(PDO::FETCH_ASSOC);
            if(empty($resultset)) $resultIsEmpty = 1;
            array_push($resultsets, $resultset);
        } while($result->nextRowset()); // 多语句查询时指向下一个语句的结果集。如果返回false表示没有下一个结果集了。
        
        if($this->cache !== NULL && $expire >= 0 && !$resultIsEmpty) {
            $this->cache->set($key, $resultsets, $expire);
        }
        
        return $resultsets;
    }

    /**
     * 构建摘要并对查询关键词进行高亮
     * @param array/string $docs    需要进行摘要的内容
     * @param string $words         需要进行高亮的关键词（需要经过segwords()方法的处理）
     * @param array $opts           摘要选项: array(limit => 256, around => 256)
     * return string/array          摘要结果，关键词通过<b>...</b>表示高亮
     */
    public function buildSnippets($docs, $words, $opts=array()) 
    {
        // 如果没有连接则连接搜索服务器
        if($this->connobj === NULL) {
            do {
                $r = $this->connect();
                if($r === true) break;
                if($r === NULL) return false;
            } while($r === false);
        }
        
        // 构造摘要语句
        if(is_array($docs)) {
            $data = '(';
            foreach($docs as $doc) {
                if($doc === NULL) $doc = '';
                $data .= $this->connobj->quote($doc);
                $data .= ',';
            }
            $data = rtrim($data, ',');
            $data .= ')';
        } else {
            if($docs === NULL) $docs = '';
            $data = $this->connobj->quote($docs);
        }
        
        $limit = 256;
        $around = 256;
        if(!empty($opts)) {
            if(isset($opts['limit']) && !empty($opts['limit']))
                $limit = $opts['limit'];
            if(isset($opts['around']) && !empty($opts['around']))
                $around = $opts['around'];
        } 
        $snipopts = "$around AS around, $limit AS limit, 0 AS query_mode"; 
        
        $qwords = $this->connobj->quote($words);
        $stmt = "CALL SNIPPETS({$data}, 'snippet', {$qwords}, {$snipopts})";
        
        // 执行摘要语句
        $result = $this->connobj->query($stmt);
        if($result === false) {
            $e = $this->connobj->errorInfo();
            $this->errorInfo = $e[2];
            return false;
        }
        
        $resultset = $result->fetchAll(PDO::FETCH_ASSOC);
        if(empty($resultset)) { //返回的结果为空。
            $this->errorInfo = 'build snippet result is empty';
            return false;
        }
        
        $snippets = array();
        foreach($resultset as $row) {
            array_push($snippets, $row['snippet']);
        }
        
        $snippets = $this->rebuildSnippets($snippets, $words);
        if(is_array($docs))
            return $snippets;
        else
            return array_pop($snippets);
    }
    
    /**
     * 过滤掉摘要中单字的高亮，除了查询里的单字之外。
     * <b>x</b><b>x</b><b>x</b>...<b>x</b>...<b>x</b><b>x</b>...
     */
    private function rebuildSnippets($docs, $words) 
    {
        // 从分词之后的查询中找出需要高亮的单字符(汉字、字母、数字...)
        $hz = array();
        $words = explode(' ', $words);
        foreach($words as $word) {
            $wlen = mb_strlen($word,'UTF-8');
            if($wlen == 1) {
                array_push ($hz, $word);
            }
        }
       
        $result = array();
        foreach($docs as $doc) {
            if(empty($doc) || strpos($doc, '<b>') === false) { //检查doc是否为空，或者是否有高亮
                array_push($result, $doc);
                continue;
            } 
            
            // 以<b> </b>对doc进行切分
            $r = preg_split('/(<\/?b>)/', $doc, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $n = count($r);
            $doc = '';
            for($k=0; $k<$n; $k++) {
                if($r[$k] === '<b>' && ( 
                  ($k-1 >= 0 && $r[$k-1] !== '</b>' && $k+3 < $n && $r[$k+3] !== '<b>') ||
                  ($k-1 < 0  && $k+3 < $n  && $r[$k+3] !== '<b>')   ||
                  ($k-1 >= 0 && $k+3 >= $n && $r[$k-1] !== '</b>' ) ||
                  ($k-1 < 0  && $k+3 >= $n))) { // 不是连续的高亮词...
                    // 检查高亮词的长度，不是单个字符的则可以高亮，或单个字母数字，比如: 0.6 s.h.e
                    if(mb_strlen($r[$k+1],'UTF-8') > 1 || ctype_alnum($r[$k+1])) { // <b>java</b>编程思想
                        $doc .= $r[$k];
                        continue;
                    }
                    
                    if(empty($hz)) {  
                        $doc .= $r[$k+1];
                        $k += 2; 
                        continue;
                    }
                    $isquery = 0;
                    foreach($hz as $v) {
                        if($r[$k+1] === $v) {
                            $isquery = 1;
                            break;
                        }
                    }
                    if($isquery) {
                        $doc .= $r[$k];
                    } else {
                        $doc .= $r[$k+1];
                        $k += 2; 
                    }
                } else {
                    $doc .= $r[$k];
                }
            }
            
            array_push($result, $doc);
        }
        
        return $result;
    }
    
}
?>
