<?php

/**
 * 公用数据接口文件，所有应用通过本文件所提供的接口取得本系统公用数据。
 * 版权所有
 * @author      zqshuai <zqshuai@163.com>
 * @version     $Id: cls_common_data.php 532 2013-03-28 04:05:25Z xuxb $
 */

/**
 * 目前可以提供的接口如下：
 * getAll($type)    取得某一类型的所有数据（单极分类可用此接口）
 * getTop($type)    取得某一类型的顶级分类数据（单极分类不可使用）
 * getChildren($type, $id, $level)    取得某个分类的直接下级分类的数据
 * getOffspring($type, $id, $level)   取得某个分类的所有下级分类的数据，包括直接和非直接下级
 * getValueByCode($type, $isSingle, $dataCode)  根据数据编码取得数据值
 */
class Tool_CommonData
{
    static $type          = '';
    static $originDataArr = array();

    /**
     * 构造函数
     * 特别当$type=="area"或"industry"或"duty"等分级数据时必须先调用构造函数初始化$this->originData。
     *
     */
    function __construct($type = '')
    {
        
    }

    /**
     * 取得原始数据
     *
     * Called By:
     * @param               string $fileName    原始数据文件名
     * @return:             string $data        所取得的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getOriginData($type)
    {
        //return $this->$type;
        if(!empty(self::$originDataArr) && isset(self::$originDataArr[$type]) && !empty(self::$originDataArr[$type])){
            return self::$originDataArr[$type];
        }else{
            self::$type = $type;
            $key  = "{$type}_data";
            $path = APP_PATH . "/application/library";
            if(isset(Yaf_Application::app()->getConfig()->_config->application->library->directory)){
                $path = Yaf_Application::app()->getConfig()->_config->application->library->directory;
            }
            $file = $path . "/data/" . $type . '.php';
            if(file_exists($file)){
                include_once($file);
                self::$type = $type;
                $className = "Data_{$type}";
                if(class_exists($className)){
                    $data = $className::$data;
                    //判断数据变量格式 $data or $categoryData
                    if($data == null){
                        //首字母改小写
                        $varName =  lcfirst($type)."Data";
                        $data = $className::${$varName};
                    }
                    self::$originDataArr[$type] = $data;//$type;
                }else{
                    self::$originDataArr[$type] = $$type;
                }


            }else{
                throw new Kfz_System_Exception("the '{$file}' is not exist!");
            }
            return self::$originDataArr[$type];
        }

    }

    /**
     * 取得某一类型的所有数据
     *
     * Called By:
     * Table Accessed:
     * @param               string $type        公用数据的类型：area(地区)等
     * @return:             array  $allData     所取得某一类型的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getAll($type)
    {

        $allData = self::getOriginData($type);
        return $allData;

    }

    /**
     * 验证某数据是不是一类型数据成员
     *
     * Called By:
     * Table Accessed:
     * @param               string $type        公用数据的类型
     * @param               string $data        测试数据
     * @return:             array  $allData     所取得某一类型的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function isOriginData($type, $key)
    {
        $allData = self::getOriginData($type);

        if(isset($allData[$key]) && $allData[$key]){
            return TRUE;
        }

        return FALSE;

    }

    /**
     * 取得某一类型顶级分类的数据
     *
     * Called By:
     * Table Accessed:
     * @param               string $type        公用数据的类型：area(地区)等
     * @return:             array  $topData     所取得的顶级分类的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getTop($type)
    {
        // 取得原始数据，并返回数组
        $originData = self::getOriginData($type);

        foreach($originData as $key => $value){
            if($value['level'] == 1){
                $topData[] = $value;
            }
        }
        return $topData;

    }

    /**
     * 取得某个分类的直接下级分类的数据
     *
     * Called By:
     * Table Accessed:
     * @param               string $type        公用数据的类型：area(地区)等
     * @param               BigInt $id          分类的编号
     * @param               Int    $level       分类的级别
     * @return:             array  $topData     所取得的直接下级分类的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getChildren($type, $id, $level)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($type, $id);

        $childrenData = array();
        // 取得原始数据，并返回数组
        $originData = self::getOriginData($type);

        // 对原始数据根据要求作处理，并以数组的形式返回要求的数据
        switch($level++){
            case 1:
                $min = $id;
                $max = $id + 1000000000;
                break;
            case 2:
                $min = $id;
                $max = $id + 1000000;
                break;
            case 3:
                $min = $id;
                $max = $id + 1000;
                break;
            case 4:
                $min = $id - 1;
                $max = $id + 1;
                break;
        }

        foreach($originData as $key => $value){
            if(isset($min) && isset($max) && $value['id'] > $min && $value['id'] < $max && $value['level'] == $level){
                $childrenData[] = $value;
            }
        }
        return $childrenData;

    }

    /**
     * 取得某个分类的所有下级分类的数据，包括直接下级和非直接下级
     *
     * Called By:
     * Table Accessed:
     * @param               string $type        公用数据的类型：industry(行业)，duty(职能)，position(职位)等
     * @param               BigInt $id          分类的编号
     * @param               Int    $level       分类的级别
     * @return:             array  $topData     所取得的下级分类的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getOffspring($type, $id, $level)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($type, $id);

        // 取得原始数据，并返回数组
        $originData = array();
        $originData = self::getOriginData($type);

        // 对原始数据根据要求作处理，并以数组的形式返回要求的数据
        switch($level){
            case 1:
                $min = $id;
                $max = $id + 1000000000;
                break;
            case 2:
                $min = $id;
                $max = $id + 1000000;
                break;
            case 3:
                $min = $id;
                $max = $id + 1000;
                break;
            case 4:
                $min = $id;
                $max = $id + 1;
                break;
        }

        foreach($originData as $key => $value){
            if($min < $value['id'] && $value['id'] < $max){
                $offspringData[] = $value;
            }
        }
        return $offspringData;

    }

    /**
     * 根据该项数据的编码得到数据的完整信息
     * 该函数只适用于多极分类的数据
     * @access public
     * @param               string $type        公用数据的类型：area(地区)等
     * @param               String $id          某项数据的编号
     * @return:             array  $itemInfo    所取得的数据的信息数组
     */
    public static function getItemInfo($type, $id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($type, $id);

        $itemInfo = array();

        if($id !== 0 && !$id){
            return '';
        }

        // 取得原始数据，并返回数组
        $originData = array();
        $originData = self::getOriginData($type);
        $idKey      = '' . $id;
        $itemInfo   = isset($originData[$idKey]) ? $originData[$idKey] : "0";
        return $itemInfo;

    }

    /**
     * 根据该项数据的编码得到数据的完整信息
     * 该函数只适用于多极分类的数据
     * @access private
     * @param               string $type        公用数据的类型：area(地区)等
     * @param               String $id          某项数据的编号
     * @return:             array  $itemInfo    所取得的数据的信息数组
     */
    private static function getItemInfo4Private($type, $id)
    {
        $itemInfo = array();

        if($id !== 0 && !$id){
            return '';
        }

        // 取得原始数据，并返回数组
        $originData = array();
        $originData = self::getOriginData($type);

        $idKey    = '' . $id;
        $itemInfo = isset($originData[$idKey]) ? $originData[$idKey] : "";

        return $itemInfo;

    }

    /**
     * 根据该项数据的编码得到其父级数据的完整信息
     * 该函数只适用于多极分类的数据
     * @access public
     * @param               string $type        公用数据的类型：area(地区)等
     * @param               String $id          某项数据的编号
     * @return:             array  $parentInfo  所取得的数据的信息数组
     */
    public static function getParentInfo($type, $id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($type, $id);

        $parentInfo = array();

        if(!$type || ($id !== 0 && !$id)){
            return FALSE;
        }

        $itemInfo = self::getItemInfo($type, $id);
        if(is_array($itemInfo) && !empty($itemInfo)){
            // 取得父级数据编号
            switch($itemInfo['level']){
                case 1:
                    return FALSE;
                case 2:
                    $str        = substr($id, 0, -9);
                    $str .= '000000000';
                    $parentId   = $str + 0;
                    break;
                case 3:
                    $str        = substr($id, 0, -6);
                    $str .= '000000';
                    $parentId   = $str + 0;
                    break;
                case 4:
                    $str        = substr($id, 0, -3);
                    $str .= '000';
                    $parentId   = $str + 0;
                    break;
            }
            // 取得父级数据信息
            $parentInfo = self::getItemInfo($type, $parentId);
            if(is_array($parentInfo) && !empty($parentInfo) && $parentInfo['level'] > 1){
                $parentInfo['parent'] = self::getParentInfo($type, $parentId);
            }
        }

        return $parentInfo;

    }

    /**
     * 根据该项数据的编码得到其相邻右兄弟编号
     * @access public
     * @param               string $type        公用数据的类型：area(地区)等
     * @param               String $id          某项数据的编号
     * @return:             integer  $rightBrotherId  所取得的相邻右兄弟编号，如果编号不够用，取得的是其父节点的相邻右兄弟的编号
     */
    public static function getRightBrotherId($type, $id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($type, $id);

        if(!$type || ($id !== 0 && !$id)){
            return FALSE;
        }

        $itemInfo = self::getItemInfo($type, $id);

        if(is_array($itemInfo) && !empty($itemInfo)){
            // 取得右兄弟数据编号
            switch($itemInfo['level']){
                case 1:
                    $str            = substr($id, 0, -9);
                    $str            = $str + 1;
                    $str .= '000000000';
                    $rightBrotherId = $str + 0;
                    break;
                case 2:
                    $str            = substr($id, 0, -6);
                    $str            = $str + 1;
                    $str .= '000000';
                    $rightBrotherId = $str + 0;
                    break;
                case 3:
                    $str            = substr($id, 0, -3);
                    $str            = $str + 1;
                    $str .= '000';
                    $rightBrotherId = $str + 0;
                    break;
                case 4:
                    $rightBrotherId = $id + 1;
                    break;
            }
        }

        return $rightBrotherId;

    }

    /**
     * 根据该项数据的编码得到其所有父级数据值的数组（一维，按照由近到远排序）
     * 该函数只适用于多极分类的数据
     * @access public
     * @param               string $type             公用数据的类型：area(地区)等
     * @param               String $id               某项数据的编号
     * @return:             array  $parentValueList  所取得的数据的信息数组
     */
    public static function getParentValueList($type, $id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($type, $id);

        if(!$type || ($id !== 0 && !$id)){
            return FALSE;
        }

        $parentValueList = array();

        $itemInfo = self::getItemInfo($type, $id);

        if(is_array($itemInfo) && !empty($itemInfo)){
            // 取得父级数据编号
            switch($itemInfo['level']){
                case 1:
                    return FALSE;
                case 2:
                    $str      = substr($id, 0, -9);
                    $str .= '000000000';
                    $parentId = $str + 0;
                    break;
                case 3:
                    $str      = substr($id, 0, -6);
                    $str .= '000000';
                    $parentId = $str + 0;
                    break;
                case 4:
                    $str      = substr($id, 0, -3);
                    $str .= '000';
                    $parentId = $str + 0;
                    break;
            }

            // 取得父级数据信息
            $parentInfo        = self::getItemInfo($type, $parentId);
            $parentValueList[] = $parentInfo['name'];

            if(is_array($parentInfo) && !empty($parentInfo) && ($parentInfo['level'] + 0) > 1){
                $parentInfo['parent'] = self::getParentValueList($type, $parentInfo['id']);
                $parentValueList[]    = $$parentInfo['parent']['name'];
            }
        }

        return $parentValueList;

    }

    /**
     * 根据数据的编码取得数据的值
     *
     * Called By:
     * Table Accessed:
     * @param               string $type        公用数据的类型：area(地区)等
     * @param               bool   $isSingle    是只有一级分类的数据且是单选数据
     * @param               String $dataCode    数据的编码，如果是多条数据的组合，此处约定每条数据之间用","分隔。
     * @return:             String $dataValue   所取得的数据的值
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getValueByCode($type, $isSingle, $dataCode)
    {
        if(empty($dataCode)){
            return '';
        }

        // 取得原始数据，并返回数组
        $originData = self::getOriginData($type);

        $dataValue = '';
        $valueList = array();

        // 根据传过来的数据编码取得对应的数据值
        if($isSingle === TRUE){
            $dataValue = isset($originData[$dataCode]) ? $originData[$dataCode] : '';
        }else{
            $codeList = explode(',', $dataCode);
            $i        = 0;
            foreach($codeList as $k => $v){
                $itemInfo = self::getItemInfo($type, $v);
                if(!empty($itemInfo) && is_array($itemInfo)){
                    $valueList[$i++] = $itemInfo['name'];
                }
            }
            $dataValue       = implode(',', $valueList);
        }

        return $dataValue;

    }

    /**
     * area中2级数据转3级数据时涉及到一小部分数据的数据的原id转换为了新id,
     * 传入$oldId,如果存在Item就直接返回$oldId,如果没有则从对应数据中获取新的id（$newId）并返回。
     *
     * @param string $type 数据类别，
     * @param int $oldId 原地址id
     * @return int
     */
    public static function getFixedAreaId4Class3($type, $oldId)
    {
        $oldItemInfo = self::getItemInfo4Private($type, $oldId);
        if(is_array($oldItemInfo) && count($oldItemInfo) > 0){
            return $oldId;
        }else{
            $newId       = self::getValueByCode('AreaFix4Class3', true, $oldId);
            $newItemInfo = self::getItemInfo4Private($type, $newId);
            if(is_array($newItemInfo) && count($newItemInfo) > 0){
                return $newId;
            }
        }

        return 0;

    }

    /**
     * 根据值取key，适合一一对应的共用数据
     *
     * Called By:
     * Table Accessed:
     * @param               string $type        value
     * @return:             String $dataValue   所取得的数据的key
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getKeyByValue($type, $value)
    {
        $value = trim($value);

        $list = self::getAll($type);

        if(!isset($list) || !is_array($list) || empty($list)){
            return '0';
        }

        foreach($list as $key => $val){
            if($val == $value){
                return $key;
            }
        }

        return '0';

    }
    
    /**
     * 获取一级id
     * 
     * @param    int    $id           
     * @return   int    $topPatentId  
     * 
     * @author	 dongnan <dongyh@126.com>
     */
    public static function getTopParentId($id)
    {
        if($id < 1000) {
            return $id;
        }
        else {
            //数据中分级的数量/即最大分级
            $maxLevel = intval(ceil(strlen($id) / 3));
            
            $zero = ($maxLevel - 1) * 3;
            $topParentId = intval(substr($id, 0, - $zero)) * pow(10, $zero);
            
            return $topParentId;
        }
    }

}