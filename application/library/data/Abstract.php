<?php

/*
 * 数据抽象类
 * 用于定义数据处理的的公共属性和方法
 * 
 * @author Shen Xi<shenxi@kongfz.com>
 * @date   2013-07-03
 */

class Data_Abstract
{

    /**
     * 可供外部和类本身调用的数据变量
     * @var array 
     */
    public static $data = null;

    /**
     * 语言包类名
     * @var string 
     */
    public static $dataName = "";

    /**
     * 根据该项数据的编码得到数据的完整信息
     * 该函数只适用于多极分类的数据
     * @access public
     * @param               String $id          某项数据的编号
     * @return:             array  $itemInfo    所取得的数据的信息数组
     */
    public static function getItemInfo($id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($id);

        if(empty($id)){
            return null;
        }

        return self::get($id);
    }

    /**
     * 根据该项数据的编码得到数据的完整信息
     * 该函数只适用于多极分类的数据
     * @access private
     * @param               String $id          某项数据的编号
     * @return:             array  $itemInfo    所取得的数据的信息数组
     */
    private static function getItemInfo4Private($id)
    {
        $itemInfo = array();

        if(empty($id)){
            return null;
        }

        // 取得原始数据，并返回数组
//        $data = Data_ItemCategory::get();
        $data = self::get();
        if(isset($data[$id])){
            $itemInfo = $data[$id];
        }else{
            // 根据传过来的数据编码取得数据信息
            foreach($data as $value){
                if($value['id'] == $id){
                    $itemInfo = $value;
                    break;
                }
            }
        }
        return $itemInfo;
    }

    /**
     * 根据数据的编码取得数据的值
     *
     * Called By:
     * Table Accessed:
     * @param               bool   $isSingle    是只有一级分类的数据且是单选数据
     * @param               String $dataCode    数据的编码，如果是多条数据的组合，此处约定每条数据之间用","分隔。
     * @return:             String $dataValue   所取得的数据的值
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getValueByCode($isSingle, $dataCode)
    {
        if(empty($dataCode)){
            return null;
        }

        $dataValue = '';
        $valueList = array();

        // 根据传过来的数据编码取得对应的数据值
        if($isSingle === TRUE){
            $dataValue = self::get($dataCode);
        }else{
            $codeList = explode(',', $dataCode);
            $i = 0;
            foreach($codeList as $v){
                $itemInfo = self::getItemInfo($v);
                if(!empty($itemInfo) && is_array($itemInfo)){
                    $valueList[$i++] = $itemInfo['name'];
                }
            }
            $dataValue = implode(',', $valueList);
        }

        return $dataValue;
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
    public static function getKeyByValue($value)
    {
        $value = trim($value);
        
        $list = self::get();
        
        if(! isset($list) || ! is_array($list) || empty($list)) {
            return '0';
        }
        
        foreach($list as $key => $val) {
            if($val == $value) {
                return $key;
            }
        }
        
        return '0';
    }

    /**
     * area中2级数据转3级数据时涉及到一小部分数据的数据的原id转换为了新id,
     * 传入$oldId,如果存在Item就直接返回$oldId,如果没有则从对应数据中获取新的id（$newId）并返回。
     *
     * @param int $oldId 原地址id
     * @return int
     */
    public static function getFixedAreaId4Class3($oldId)
    {
        $oldItemInfo = self::getItemInfo4Private($oldId);
        if(is_array($oldItemInfo) && count($oldItemInfo) > 0){
            return $oldId;
        }else{
            $data = array(
                '8014000000' => '8003003000',
                '18001000000' => '18007007000',
                '18003000000' => '18007009000',
                '18008000000' => '18007011000',
                '18017000000' => '18007010000',
                '18018000000' => '18007008000',
                '30001000000' => '30014001000',
                '29016000000' => '29015002000',
                '29017000000' => '29015003000',
                '29019000000' => '29004001000',
                '29020000000' => '29004002000',
                '29022000000' => '29021002000'
            );
            $newId = isset($data[$oldId]) ? $data[$oldId] : '';
            if(!empty($newId)){
                $newItemInfo = self::getItemInfo4Private($newId);
                if(is_array($newItemInfo) && count($newItemInfo) > 0){
                    return $newId;
                }
            }
        }

        return 0;
    }

    /**
     * 取得某一类型顶级分类的数据
     *
     * Called By:
     * Table Accessed:
     * @return:             array  $topData     所取得的顶级分类的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getTop()
    {
        $topData = array();

        $data = self::get();
        if(is_array($data)){
            foreach($data as $row){
                if(isset($row['level']) && $row['level'] == 1){
                    $topData[] = $row;
                }
            }
        }
        return $topData;
    }

    /**
     * 获取父级id
     * 
     * @param    int    $id       
     * @param    int    $level    
     * @return   int    $parentId
     * 
     * @author	 dongnan <dongyh@126.com>
     */
    public static function getParentId($id, $level)
    {
        //数据中分级的数量/即最大分级
        $maxLevel = intval(ceil(strlen($id) / 3));
        if($level == 1){
            return FALSE;
        }else{
            $zero = ($maxLevel + 1 - $level) * 3;
            $parentId = intval(substr($id, 0, - $zero)) * pow(10, $zero);
        }

        return sprintf("%.0f",$parentId);
    }

    /**
     * 获取顶级父级id
     * 
     * @param    int    $id           
     * @return   int    $topPatentId  
     * 
     * @author	 dongnan <dongyh@126.com>
     */
    public static function getTopParentId($id)
    {
        if($id < 1000){
            return $id;
        }else{
            //数据中分级的数量/即最大分级
            $maxLevel = intval(ceil(strlen($id) / 3));
            $zero = ($maxLevel - 1) * 3;
            $topParentId = intval(substr($id, 0, - $zero)) * pow(10, $zero);

            return sprintf("%.0f",$topParentId);
        }
    }

    /**
     * 根据该项数据的编码得到其顶级父级数据的信息
     * 该函数只适用于多极分类的数据
     * @access public
     * @param  string $id              某项数据的编号
     * @return array  $topParentInfo   所取得的数据的信息数组
     */
    public static function getTopParentInfo($id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($id);
        if(empty($id)){
            return null;
        }

        // 取得顶级父级id
        $topParentId = self::getTopParentId($id);
        // 取得父级数据信息
        $topParentInfo = self::getItemInfo($topParentId);

        return $topParentInfo;
    }

    /**
     * 根据该项数据的编码得到其父级数据的完整信息
     * 该函数只适用于多极分类的数据
     * @access public
     * @param  string $id          某项数据的编号
     * @return array  $parentInfo  所取得的数据的信息数组
     */
    public static function getParentInfo($id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($id);

        $parentInfo = array();

        if(empty($id)){
            return null;
        }

        $itemInfo = self::getItemInfo($id);
        if(is_array($itemInfo) && !empty($itemInfo)){
            // 取得父级数据编号
            $parentId = self::getParentId($id, $itemInfo['level']);
            if($parentId === FALSE){
                return FALSE;
            }

            // 取得父级数据信息
            $parentInfo = self::getItemInfo($parentId);
            if(is_array($parentInfo) && !empty($parentInfo) && $parentInfo['level'] > 1){
                $parentInfo['parent'] = self::getParentInfo($parentId);
            }
        }

        return $parentInfo;
    }

    /**
     * 取得某个分类的直接下级分类的数据
     *
     * Called By:
     * Table Accessed:
     * @param               BigInt $id          分类的编号
     * @param               Int    $level       分类的级别
     * @return:             array  $topData     所取得的直接下级分类的数据
     *
     * @author              zqshuai <zqshuai@163.com>
     */
    public static function getChildren($id, $level)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($id);
        $childrenData = array();

        // 对原始数据根据要求作处理，并以数组的形式返回要求的数据
        $min = 0;
        $max = 0;
        $result = self::setBoundaryId($id, $level++);
        if(isset($result['min'])){
            $min = $result['min'];
        }
        if(isset($result['max'])){
            $max = $result['max'];
        }
        $data = self::get();
        foreach($data as $value){
            if(isset($min) && isset($max) && $value['id'] > $min && $value['id'] < $max && $value['level'] == $level){
                $childrenData[] = $value;
            }
        }
        return $childrenData;
    }

    /**
     * 根据该项数据的编码得到其相邻右兄弟编号
     * @access public
     * @param               string $type        公用数据的类型：area(地区)等
     * @param               String $id          某项数据的编号
     * @return:             integer  $rightBrotherId  所取得的相邻右兄弟编号，如果编号不够用，取得的是其父节点的相邻右兄弟的编号
     */
    public static function getRightBrotherId($id)
    {
        $rightBrotherId = 0;

        if(empty($id)){
            return FALSE;
        }

        $itemInfo = self::getItemInfo($id);
        if(is_array($itemInfo) && !empty($itemInfo)){
            //数据中分级的数量/即最大分级
            $maxLevel = intval(ceil(strlen($id) / 3));
            // 取得右兄弟数据编号
            $rightBrotherId = $id + pow(1000, $maxLevel - $itemInfo['level']);
        }

        return sprintf("%.0f",$rightBrotherId);
    }

    /**
     * 根据该项数据的编码得到其所有父级数据值的数组（一维，按照由近到远排序）
     * 该函数只适用于多极分类的数据
     * @access public
     * @param               string $type             公用数据的类型：area(地区)等
     * @param               String $id               某项数据的编号
     * @return:             array  $parentValueList  所取得的数据的信息数组
     */
    public static function getParentValueList($id)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($id);

        if(empty($id)){
            return null;
        }

        $parentValueList = array();

        $itemInfo = self::getItemInfo($id);
        if(is_array($itemInfo) && !empty($itemInfo)){
            $parentId = self::getParentId($id, $itemInfo['level']);

            // 取得父级数据信息
            $parentInfo = self::getItemInfo($parentId);
            $parentValueList[] = $parentInfo['name'];

            if(is_array($parentInfo) && !empty($parentInfo) && ($parentInfo['level'] + 0) > 1){
                $parentInfo['parent'] = self::getParentValueList($parentInfo['id']);
                $parentValueList = array_merge($parentValueList, $parentInfo['parent']);
            }
        }

        return $parentValueList;
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
    public static function getOffspring($id, $level)
    {
        //added by weiqin on 2011-02-23 for FixedAreaId...
        $id = self::getFixedAreaId4Class3($id);
        $offspringData = array();
        // 对原始数据根据要求作处理，并以数组的形式返回要求的数据
        switch($level){
            case 1:
                $min = $id;
                $max = $id + 1000000000;
//                $max = sprintf("%.0f",$id + 1000000000);
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
        $data = self::get();
        foreach($data as $value){
            if($min < $value['id'] && $value['id'] < $max){
                $offspringData[] = $value;
            }
        }
        return $offspringData;
    }

    /**
     * 该函数用于确定DATA数据的语言包所在的类
     */
    public static function init()
    {
        //语言包名称
        $lang = "Sc";
        if(defined('LANGUAGE_TYPE')){
            switch(LANGUAGE_TYPE){
                case 'Traditional_Chinese':  //繁体中文语言包
                    $lang = "Tc";
                    break;
                case 'English':  //英文语言包
                    $lang = "En";
                    break;
                default:   //默认为简体中文
                    $lang = "Sc";
                    break;
            }
        }

        $className = get_called_class();
        $arr = explode('_', $className);
        $name = array_pop($arr);
        self::$dataName = "Language_{$lang}_Data_{$name}";
    }

    /**
     * 根据键获取语言包对应的数据，如果没有对应的语言包，则取自身定义的数据
     * @param  string $key  语言包中data数据的键名
     * @return mixed
     */
    public static function get($key = "")
    {
        self::init();

        $value = null;

        $dataCalssName = self::$dataName;
        $className = get_called_class();

        if($dataCalssName != "" && class_exists($dataCalssName)){
            if($key == "" && isset($dataCalssName::$data)){
                $value = &$dataCalssName::$data;
            }elseif(isset($dataCalssName::$data[$key])){
                $value = $dataCalssName::$data[$key];
            }
        }elseif(isset($className::$data)){
            if($key == ""){
                $value = &$className::$data;
            }elseif(isset($className::$data[$key])){
                $value = $className::$data[$key];
            }
        }

        return $value;
    }
    
    /**
     * 设定指定级别的边界id,$min表示左边界id，$max表示右边界id
     * 
     * @param    int    $id 
     * @param    int    $level  等级
     * @return   void
     * 
     * @author   dongnan <dongyh@126.com>
     */
    private static function setBoundaryId($id, $level)
    {
        //数据中分级的数量/即最大分级
        $maxLevel = intval(ceil(strlen($id) / 3));
        $min = 0;
        $max = 0;
        if($level === $maxLevel) {
            $min = $id - 1;
            $max = $id + 1;
        }
        else {
            $min = $id;
            $max = $id + pow(1000, $maxLevel - $level);
        }
        return array('min' => $min, 'max' => $max);
    }

    /**
     * 通过拍品的分类id，获取拍品对应的模板id
     * $catId 分类Id，
     * return $tplId   模板对应的Id
     * @author wangkongming<komiles@163.com>
     */
    public static function getItemTplId($catId)
    {
        if(!empty($catId)) {
            //获取全部分类信息
            $ItemCateGory  = Data_ItemCategory::$data;
            //返回模板id
            return $ItemCateGory[$catId]['tpl'];   
        } else {
            return 0;
        }
    }
}

?>