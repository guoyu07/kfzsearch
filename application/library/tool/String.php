<?php

/**
 * 字符串处理函数
 * 
 * @author Shen Xi <shenxi@kongfz.com>
 */
class Tool_String
{

    /**
     * 过滤字符串中的非法字符
     * @access  public
     * @param   string  $str         需要过滤的字符串
     * @param   array   $wordFilter  禁用词列表
     * @param   int     $size        对禁用词进行分组的数量，以免正则表达式匹配长度超过限制
     * @return  string  $stopWord    违禁词
     *
     */
    public static function wordFilter($str, $wordFilter, $size = 100)
    {
        $stopWord = '';
        $pattern = '';
        $matches = null;
        if (is_array($wordFilter) && !empty($wordFilter)) {
            $wordsArr = array_chunk($wordFilter, $size);
            foreach ($wordsArr as $row) {
                $pattern = implode("|", $row);
                if (preg_match("/{$pattern}/i", $str, $matches)) {
                    $stopWord = $matches[0];
                    break;
                }
            }
        }
        return $stopWord;
    }

    /**
     * 生成ajax响应信息
     * @param boolean $status 执行状态
     * @param array   $data   返回的数据
     * @param string  $error  错误信息
     * @param array   $pager  分页相关参数
     * @return string
     */
    public static function getResponse($status, $data = array(), $error = "", array $pager = array(), $append = array(), $errType = '0')
    {
        $data = array(
            'status' => $status,
            'data' => $data,
            'message' => $error
        );
        if (!empty($pager)) {
            $data['pager'] = $pager;
        }
        if (!empty($append)) {
            $data['append'] = $append;
        }
        $data['errType'] = $errType;
        return json_encode($data);
    }

    /**
     * 计算UTF-8字符串长度
     * @param   string  $str      一个字符串
     * @return  integer           字符串长度
     */
    public static function strlenUtf8($str)
    {
        $i = 0;
        $count = 0;
        $len = strlen($str);
        while ($i < $len) {
            $ord = ord($str{$i});
            if ($ord < 192) {
                $i++;
            } elseif ($ord < 224) {
                $i += 2;
            } else {
                $i += 3;
            }
            $count++;
        }
        return $count;
    }

    /**
     * 产生一个指定长度的随机字符串,并返回给用户
     *
     * @access          public  
     * @param           int    $len         产生字符串的位数  
     * @return          string $str         随机字符串
     *
     * @author          zqshuai <zqshuai@163.com>
     */
    public static function randStr($len = 6)
    {
        /* 设置生成随机串的字符及初始化随机串变量 */
        $chars = 'ABDEFGHJKLMNPQRSTVWXYabdefghijkmnpqrstvwxy23456789';
        $str = '';
        $randLen = strlen($chars) - 1;

        while (strlen($str) < $len) {
            $str .= substr($chars, rand(0, $randLen), 1);
        }

        return $str;
    }

    /**
     * 截取字符串，utf-8
     * 
     * @param type $str
     * @param type $len
     * @param type $suffix
     * @return string
     */
    public static function substr($str, $len, $suffix = 0)
    {
        $returnStr = "";
        $i = 0;
        $n = 0;
        $strLength = strlen($str); //字符串的字节数  
        while (($n < $len) and ($i <= $strLength)) {
            $temp_str = substr($str, $i, 1);
            $ascnum = Ord($temp_str); //得到字符串中第$i位字符的ascii码  
            if ($ascnum >= 224) { //如果ASCII位高与224，  
                $returnStr = $returnStr . substr($str, $i, 3);
                //根据UTF-8编码规范，将3个连续的字符计为单个字符  
                $i = $i + 3; //实际Byte计为3  
                $n++; //字串长度计1  
            } elseif ($ascnum >= 192) { //如果ASCII位高与192，  
                $returnStr = $returnStr . substr($str, $i, 2);
                //根据UTF-8编码规范，将2个连续的字符计为单个字符  
                $i = $i + 2; //实际Byte计为2  
                $n++; //字串长度计1  
            } elseif ($ascnum >= 65 && $ascnum <= 90) {
                //如果是大写字母，  
                $returnStr = $returnStr . substr($str, $i, 1);
                $i = $i + 1; //实际的Byte数仍计1个  
                $n++; //但考虑整体美观，大写字母计成一个高位字符  
            } else { //其他情况下，包括小写字母和半角标点符号，  
                $returnStr = $returnStr . substr($str, $i, 1);
                $i = $i + 1; //实际的Byte数计1个  
                $n = $n + 0.5; //小写字母和半角标点等与半个高位字符宽…  
            }
        }
        if ($suffix != 0 && $i < $strLength) {
            $returnStr = $returnStr . "...";
            //超过长度时在尾处加上省略号  
        }
        return $returnStr;
    }

    /**
     * 将支付平台传来的序列化的字符串转换成数组
     * string $str
     * return $data
     */
    static public function unserializeStr($str)
    {
        if (!isset($str) || $str == '' || strlen($str) < 4) {
            return '';
        }

        $str = substr($str, 1, strlen($str) - 2);
        $str = str_replace('"', '', $str);
        $data = array();
        if (preg_match("/,/i", $str)) {
            $tmp_data = explode(",", $str);
            for ($i = 0; is_array($tmp_data) && count($tmp_data) > $i; $i++) {
                $dataKeyVal = explode(":", $tmp_data[$i]);
                if (is_array($dataKeyVal) && count($dataKeyVal) == 2) {
                    $key = $dataKeyVal[0];
                    $data[$key] = $dataKeyVal[1];
                }
            }
        } else {
            if (preg_match("/:/i", $str)) {
                $dataKeyVal = explode(":", $str);
                if (is_array($dataKeyVal) && count($dataKeyVal) == 2) {
                    $key = $dataKeyVal[0];
                    $data[$key] = $dataKeyVal[1];
                }
            }
        }
        return $data;
    }

    /**
     * 将数组打包成字符串返回
     *
     * Table Accessed:   用户数据表：member
     *
     * @author          xuxb <xuxiaobovip@gmail.com>
     */
    public static function listToJeson($list)
    {

        if (!isset($list) || !is_array($list) || empty($list)) {
            return "[]";
        }

        $result = array();

        for ($i = 0; $i < sizeof($list); $i++) {
            $jesonStr = self::infoToJeson($list [$i]);

            if ($jesonStr == '{}') {
                continue;
            }

            $result [] = $jesonStr;
        }

        return "[" . join(',', $result) . "]";
    }

    /**
     * 将数组打包成字符串返回
     *
     * Table Accessed:   用户数据表：member
     *
     * @author          xuxb <xuxiaobovip@gmail.com>
     */
    public static function infoToJeson($info)
    {

        if (!isset($info) || !is_array($info) || empty($info)) {
            return "{}";
        }

        $result = array();
        foreach ($info as $key => $val) {
            $result [] = "\"$key\":\"$val\"";
        }

        return "{" . join(',', $result) . "}";
    }

    /**
     * 解析json为数组
     * 
     * just handle json data such as [a,b,d] or [{"a":1,"b":2},{"a":3,"b":4}]...
     * 
     * @param data
     * @return array()
     * 
     * @author weiqin.zheng
     * @since 1.0 - 2011-2-23
     */
    public static function transDataFromJsonToArray($data)
    {
        $data = Kfz_Lib_Secure::quotesDecode($data);
        $retObj = null;

        if ($data == null || $data == "" || (substr($data, 0, 1) != "[" && substr($data, 0, 1) != "{"))
            $retObj = null;
        else {
            try {
                if (substr($data, 0, 1) == "[") {
                    $tmpRetList = array();

                    if (substr($data, 0, 2) == "[{") {
                        $data = str_replace(array("[{", "}]", "{"), '', $data);

                        $tmpArr = explode("},", $data);

                        if (is_array($tmpArr) && count($tmpArr) > 0) {
                            foreach ($tmpArr as $tmpStr) {
                                $sub_tmpArr = explode(",", $tmpStr);
                                if (is_array($sub_tmpArr) && count($sub_tmpArr) > 0) {
                                    $tmpMap = array();

                                    foreach ($sub_tmpArr as $sub_tmpStr) {
                                        $tmpMap [str_replace("\"", "", substr($sub_tmpStr, 0, strpos($sub_tmpStr, ":")))] = str_replace("\"", "", substr($sub_tmpStr, strpos($sub_tmpStr, ":") + 1));
                                    }

                                    array_push($tmpRetList, $tmpMap);
                                }
                            }
                        }
                    } else {
                        $data = str_replace(array("[", "]"), '', $data);

                        $tmpArr = explode(",", $data);
                        if (is_array($tmpArr) && count($tmpArr) > 0) {
                            foreach ($tmpArr as $tmpStr) {
                                array_push($tmpRetList, $tmpStr);
                            }
                        }
                    }

                    $retObj = $tmpRetList;
                } else
                if (substr($data, 0, 1) == "{") {

                    $tmpRetMap = array();
                    //...wait to implement...
                    //...
                    $data = str_replace(array("{", "}"), '', $data);
                    $tmpArr = explode(",", $data);
                    if (is_array($tmpArr) && count($tmpArr) > 0) {
                        foreach ($tmpArr as $tmpStr) {
                            $tmpRetMap [str_replace("\"", "", substr($tmpStr, 0, strpos($tmpStr, ":")))] = str_replace("\"", "", substr($tmpStr, strpos($tmpStr, ":") + 1));
                        }
                    }

                    $retObj = $tmpRetMap;
                }
            } catch (Exception $ex) {
                $retObj = null;
            }
        }

        return $retObj;
    }

    /**
     * 分析一个字符串中使用的各种字符集（ASCII、GBK、UTF-8）
     * 
     * @param   string  $contents  一个字符串
     * @return  array   $info      各字符集的字符数量
     */
    public static function analyzeCharset($contents)
    {
        $info = array('ascii' => 0, 'gbk' => 0, 'utf8' => 0, 'unknow' => 0);

        $i = 0;
        while ($i < strlen($contents)) {
            // ENG
            $s = substr($contents, $i, 1);
            if (strlen($s) == 1) {
                $asc = ord($s[0]);
                if (0x00 <= $asc && $asc <= 0x7F) {
                    $info['ascii']++;
                    $i += 1;
                    continue;
                }
            }
            // UTF-8
            $s = substr($contents, $i, 3);
            if (strlen($s) == 3) {
                if (substr(decbin(ord($s[0])), 0, 4) == '1110' && substr(decbin(ord($s[1])), 0, 2) == '10' && substr(decbin(ord($s[2])), 0, 2) == '10') {
                    $info['utf8']++;
                    $i += 3;
                    continue;
                }
            }
            // GBK
            $s = substr($contents, $i, 2);
            if (strlen($s) == 2) {
                $high = ord($s[0]);
                $low = ord($s[1]);
                if ((0x80 <= $high && $high <= 0xFF) && (0xA1 <= $low && $low <= 0xFF)) {
                    $info['gbk']++;
                    $i += 2;
                    continue;
                }
            }
            $info['unknow']++;
            $i++;
        }

        return $info;
    }
    
    /**
     * 去除字符产中的转义
     * @param mixed $data
     * @return mixed
     */
    public static function stripslashes($data)
    {

        if(is_array($data)){
            foreach ($data as $key=>$value){
                if(is_array($value)){
                    $data[$key]= self::stripslashes($value);
                }else if(is_string ($value)){
                    $data[$key] = stripslashes($value);
                }
            }
        }else if(is_string($data)){
            $data = stripslashes($data);
        }
        
        return $data;
    }
    
    /**
     * 生成ajax响应信息
     * @param boolean $status 执行状态
     * @param array   $data   返回的数据
     * @param string  $error  错误信息
     * @param array   $pager  分页相关参数
     * @return string
     */
    public static function getAjaxResponse($status, $data = array(), $error = "", array $pager = array(), $append = array(), $errType = '0')
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = array(
            'status' => $status,
            'data' => $data,
            'message' => $error
        );
        if (!empty($pager)) {
            $data['pager'] = $pager;
        }
        if (!empty($append)) {
            $data['append'] = $append;
        }
        $data['errType'] = $errType;
        return json_encode($data);
    }
    
    /**
     * 判断字符串长度是否超过限度
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-9 上午9:22:03
    */ 
    public static function checkStrLen($str,$len)
    {
    	return $len >= mb_strlen($str,'utf8') ? true : false;
    }
    
    /**
     * 判断是否是浮点数
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-9 下午2:58:11
    */ 
    public static function checkFloat($value, $num,$max=5)
    {
    	$pattern = '/^[0-9]{1,'.$max.'}[\.]{0,1}[0-9]{0,' . $num . '}$/';
    	return preg_match($pattern, $value);
    }
    
    /**
     * 检查整数是否在范围内
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-9 下午3:09:55
     */
    public static function checkInt($num, $min = 0, $max = 0)
    {
    	if($num != 0 && is_numeric($num)){
    		return  ($num >= $min && $num <= $max) ? TRUE : FALSE;
    	}else{
    		return false;
    	}
    }
    
    /**
     * 检测一个宽松的日期格式
     * @param   string  $date   要检查的日期字符串，如：2008,2008-08,2008-08-08或2008.8.8等等
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-9 下午3:09:55
     * @return  boolean
     */
    public static function checkLooseDate($date)
    {
    	$pattern = '/[\d]{4}|[\d]{4}[-\/\.]}[\d]{1,2}|[\d]{4}[-\/\.][\d]{1,2}[-\/\.][\d]{1,2}/';
    	return preg_match($pattern, $date);
    }
    
    /**
     * 取得规范化的日期
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-9 下午3:35:44
    */ 
    public static function getNormativeDate($date)
    {
    	$list = preg_split('/[-\/\.]/', trim($date));
    	$year = isset($list [0]) ? $list [0] : '0000';
    	$month = isset($list [1]) ? $list [1] : '00';
    	$day = isset($list [2]) ? $list [2] : '00';
    	$normaDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
    	return $normaDate;
    }

    
    /**
     * 检查是否为合法金额，整数部分最大为8位
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-9 下午4:38:45
    */ 
    public static function checkMoney($str)
	{
    	$str = trim($str);
		$pattern = '/^[0-9]{1,8}[.]{0,1}[0-9]{0,2}$/';
		$isOk = preg_match($pattern, $str) ? TRUE : FALSE;
		if($isOk){
			$isOk = (floor(floatval($str)) <= 99999999);
		}
    	return $isOk;
    }
    

    /**
     * 检查电话号码
     * @param  string    $str           待查电话号码(正确格式010-87786632或(010)32678878或32678878)
     * @param  bool      $required      是必要项
     * @return bool      $isOk          通过验证
     * @author wangkongming<komiles@163.com>
     * @date 2014-07-16
     */
    public static function checkTel($str, $required = FALSE)
    {
        $isOk = FALSE;
        $str = trim($str);
        if($str == '' && $required === FALSE) {
            $isOk = TRUE;
        }
        
        if($str != '') { 
            //国内外都可以验证
            $pattern = '/^[+]{0,1}(\d){1,3}[ ]?([-]?((\d)|[ ]){1,12})+$/';
            $isOk = preg_match($pattern, $str) ? TRUE : FALSE; 
        }

        return $isOk; 
    }
    
    /**
     * 过长字符串处理
     *
     * @param  array     $array  要处理的数据 数组
     * @param  string    $field  要处理的字段名称 
     * @param  int       $start  截取的开始位置
     * @param  int       $length 保留字符串长度
     * @param  string    $alias  被处理后的字段名($alias和$field不相等的时候保留原来字段和其值)
     * @return array     $data   返回的数组
     *
     * @author          wangkongming<komiles@163.com>
     */
    public static function extSubstr($array, $field, $start = 0, $length = 33, $alias = '')
    {
        if($alias == '') {
            $alias = $field;
        }

        if(count($array) == 0) {
            return $array;
        }
        if(function_exists('mb_substr')) {
            for($i = 0; $i < count($array); $i++) {
                if(isset($array [$i] [$field]) && strlen($array [$i] [$field]) > $length) {
                    $tmp_length = intval($length / 3);
                    $array [$i] [$alias] = (mb_substr($array [$i] [$field], 0, $tmp_length, "UTF-8")) . "...";
                } else {
                    $array [$i] [$alias] = $array [$i] [$field];
                }
            }
        } else {
            for($i = 0; $i < count($array); $i++) {
                if(isset($array [$i] [$field]) && strlen($array [$i] [$field]) > $length) {
                    $tmp_substr = substr($array [$i] [$field], $start, $length);
                    if($tmp_num = preg_match_all("/[\|\+\)\(\*\\\\$\!\=\}\{\]\[\:\?\/&%#@;.,-~><\'\"]| |[\w]/", $tmp_substr, $tmp_array)) {
                        $strNumCN = strlen($tmp_substr) - $tmp_num; // 汉字字符数量               
                        if($strNumCN % 3 != 0) {
                            $strNumCN = $strNumCN - ($strNumCN % 3);
                        }
                        $length = $strNumCN + $tmp_num;
                    } else {
                        if($length % 3 != 0) {
                            $length = $length - ($length % 3);
                        }
                    }
                    $tmp_substr = substr($tmp_substr, $start, $length);
                    $array [$i] [$alias] = $tmp_substr . "...";
                } else {
                    $array [$i] [$alias] = $array [$i] [$field];
                }
            }
        }

        if(isset($array)){
            return $array;
        }else{
            return FALSE;
        }

    }

    /**
     * 创建像这样的查询语句: "IN('a','b')";
     *
     * @param  array  $itemList     数组(一维)形式的集合
     * @return string $itemList     " IN('a','b','c')".
     * @author wangkongming <komiles@163.com>
     * @date 2014-07-16 17:26:00
     */
    public static function dbCreateIn($itemList)
    {
        if(empty($itemList)) {
            return " IN ('') ";
        } else {
            return " IN ('" . join("','", $itemList) . "') ";
        }

    }

    /**
     * 判断是否是图片系统的图片
     * @param           string $str         需要判断的url
     * @return          boolean
     * @author          wangkongming<komiles2163.com>
     * @date            2014-7-24 15:42:00
    */ 
    public static function isImageManageUrl($url)
    {
        return (strpos($url, 'G') === 0) ? true : false;
    }

    /**
     * 递归方式的转换\n为<br>
     * @param           string $str         需要转换的字符串
     * @return          string $newStr      转换后的字符串
     * @author          wangkongming <komiles@163.com>
     * @date            2014-07-28 14:29:00
     */
    public static function deepNl2br($str)
    {
        if(empty($str)) {
            return $str;
        } else {
            return is_array($str) ? array_map('deepNl2br', $str) : nl2br($str);
        }
    }

    /**
     * 把5到20个<br/>标签转化为一个<br/>标签
     * @param   string $str
     * @return  $str
     * @author  wangkongming<komiles@163.com>
     * @date    2014-07-28 14:32:00
     */
    public static function stripBrTag($str)
    {
        if(empty($str)) {
           $str = '';
        } else {
            $str = preg_replace("/(<br(\s*)\/>\s*){5,20}/","<br />", $str); 
        }
        return $str;
    }
    
    /**
     * 过滤字符串两端的空格，并转义字符串包含的引号
     * 
     * @param string $str
     * @author Shen Xi<shen_1421@163.com> 
     * @datetime 2014-09-10 09:59:53
     */
    public function filterSpaceAndQuotes($str)
    {
        if(!is_string($str)){
            return '';
        }
        
        return addslashes(trim($str));
    }
}