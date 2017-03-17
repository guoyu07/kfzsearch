<?php

/**
 * 分类业务类
 * 处理我的分类，常用分类等分类相关业务
 * @author liuty <liuty1986@163.com>
 * @date 2014-7-7 下午2:20:42
*/ 
class CategoryModel extends Kfz_System_ModelAbstract
{

    /**
     * 最大分类级别
     */
    const MAXLEVEL = 6;

    /**
     * 常用分类 oftenUsedCat 表对应DAO
     * @var Dao_OftenUsedCatModel
     */
    private $oftenUsedCatDao;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->oftenUsedCatDao = new Dao_OftenUsedCatModel();
    }

    /**
     * 根据短id获取完整长度的catId
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-7 下午2:28:11
    */ 
    public static function getFullCatId($catId)
    {
        if(empty($catId)){
            return false;
        }
        //数据中分级的数量/即最大分级
        $maxLevel = intval(ceil(strlen(trim($catId)) / 3));
        if($maxLevel < self::MAXLEVEL){
            $catId = $catId * pow(1000, self::MAXLEVEL - $maxLevel);
        }

        return $catId;
    }

    /**
     * 根据完整id获取短的catId
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-7 下午2:28:23
    */ 
    public static function getShortCatId($catId)
    {
        if(empty($catId)){
            return false;
        }
        $catId = preg_replace('/(000)*$/', '', trim($catId));

        return $catId;
    }

    /**
     * 添加 常用拍品分类
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-7 下午3:03:44
    */ 
    public function addOftenUsedCat($userId, $catId, $limit = 20)
    {
    	$status = false;
        //bug:15025 综合其他 不添加到常用分类
        if($catId == '20000000000000000'){
            return true;
        }
        // 删除限制条数之外的记录
        $list = $this->oftenUsedCatDao->getAll(array('catId'), array('userId' => $userId), array(), array('sortOrder' => 'ASC', 'lastUsedTime' => 'DESC'), $limit, 10);
        if(!empty($list)){
        	$deleteIds = array();
            foreach($list as $item){
            	$deleteIds[] = $item['catId'];
			}
            $this->oftenUsedCatDao->delete(array('userId' => $userId, 'catId' => $deleteIds));
		}
        // 插入或更新记录
        $entity = $this->oftenUsedCatDao->getRow(array('userId', 'catId', 'sortOrder'), array('userId' => $userId, 'catId' => $catId));
        if(empty($entity)){
        	$whereData = array('userId' => $userId);
            $sql = 'SELECT max(sortOrder) FROM oftenUsedCat WHERE userId=:userId';
            $sortOrder = $this->oftenUsedCatDao->getOneBySql($sql, $whereData);
            if($sortOrder === false){
            	$sortOrder = 0;
			}else{
            	$sortOrder++;
			}
            $entity['userId'] = $userId;
            $entity['catId'] = $catId;
            $entity['sortOrder'] = $sortOrder;
            $entity['lastUsedTime'] = time();
            $sql = 'REPLACE INTO oftenUsedCat VALUES (:userId,:catId,:sortOrder,:lastUsedTime)';
            $status = $this->oftenUsedCatDao->insertBySql($sql, $entity,false);
		}else{
        	$entity['lastUsedTime'] = time();
            $status = $this->oftenUsedCatDao->update($entity, array('userId' => $userId, 'catId' => $catId));
		}

        return $status;
    }

    /**
     * 获取常用分类数据
     * 
     * @param int $userId
     * @return false|array
     */
    public function getOftenUsedCatList($userId)
    {
        if(empty($userId)){
            return false;
        }
        $selectFields = array('catId', 'sortOrder','lastUsedTime');
        $whereData = array();
        $whereData['userId'] = $userId;
        $orderBy = array('sortOrder' => 'ASC', 'lastUsedTime' => 'DESC');
        $catList = $this->oftenUsedCatDao->getAll($selectFields, $whereData, array(), $orderBy);
        foreach ($catList as &$row){
            $catInfo = self::getAnalyzedItemCat($row['catId']);
            $row['catSite'] = empty($catInfo['catSite'])?'':$catInfo['catSite'];
        }
        return $catList;
    }

    /**
     * 分析拍品分类,取得顶级分类,和分类信息
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-7 下午3:30:24
    */ 
    public static function getAnalyzedItemCat($catId)
    {
    	if(empty($catId)){
    		return false;
    	}
    	$catResult = array();
    	$catId = self::getFullCatId($catId);
    	$catInfo = Data_ItemCategory::getItemInfo($catId);
    	if(is_array($catInfo) && isset($catInfo['level']) && $catInfo['level'] > 1){
    		$parentList = Data_ItemCategory::getParentValueList($catId);
    		$catNames = array_reverse(array_merge(array($catInfo['name']), $parentList));
    		$catResult['catId'] = $catId;
    		$catResult['level'] = $catInfo['level'];
    		$catResult['tpl'] = $catInfo['tpl'];
    		$catResult['catName'] = $catInfo['name'];
    		$catResult['catNames'] = $catNames;
    		$catResult['catSite'] = implode(' > ', $catNames);
    	}elseif(is_array($catInfo) && isset($catInfo['level']) && $catInfo['level'] == 1){
    		$catResult['catId'] = $catId;
    		$catResult['level'] = $catInfo['level'];
    		$catResult['tpl'] = $catInfo['tpl'];
    		$catResult['catName'] = $catInfo['name'];
    		$catResult['catNames'] = array($catInfo['name']);
    		$catResult['catSite'] = $catInfo['name'];
    	}
    
    	return $catResult;
    }
    
    /**
     * 获取最后使用的常用分类编号
     * @param int $userId
     * @return int
     */
    public function getLastUsedCatId($userId)
    {
        $whereData = array();
        $whereData['userId'] = $userId;
        $orderBy = array('lastUsedTime' => 'DESC');
        $row = $this->oftenUsedCatDao->getRow(array('catId'), $whereData, array(), $orderBy);

        return isset($row['catId']) ? $row['catId'] : 0;
    }

    /**
     * 删除常用分类
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-7 下午3:13:46
    */ 
    public function deleteOftenUsedCat($userId, $catIds)
    {
        if(empty($userId) || empty($catIds)){
            return false;
        }

        $whereData = array('catId' => $catIds, 'userId' => $userId);
        $status = $this->oftenUsedCatDao->delete($whereData);

        return $status;
    }

    /**
     * 修改常用分类排序编号
     * @author liuty <liuty1986@163.com>
     * @date 2014-7-7 下午3:32:13
    */ 
    public function changeOftenUsedCatSortOrder($userId, $catId, $sortOrder)
    {
        $status = false;
        if(empty($userId) || empty($catId)){
            return false;
        }
        $entity = array();
		$entity['sortOrder'] = $sortOrder;
		$whereData = array('userId' => $userId, 'catId' => $catId);
		$status = $this->oftenUsedCatDao->update($entity, $whereData);

        return $status;
    }

    /**
     * 根据分类拼音获取分类id
     * author wangkongming<komiles@163.com>
     */
    static public function getCatInfoByPinyin($pinyin,$level,$fid= '')
    {
        $itemInfo = array();
        $itemCategory = Data_ItemCategory::get();
        switch ($level) {
            case 1:
                foreach ($itemCategory as $row) {
                        if($row['level'] == 1 && $row['pinyin'] == $pinyin) {
                            $itemInfo = $row;
                        }
                    }
                break;
            case 2:
                $itemCategory = self::getCatSonId($fid);
                foreach ($itemCategory as $row) {
                        if($row['level'] == 2 && $row['pinyin'] == $pinyin) {
                            $itemInfo = $row;
                        }
                    }
                    
                break;
            case 3:
                foreach ($itemCategory as $row) {
                    if($row['level'] == 3 && $row['pinyin'] == $pinyin) {
                        $itemInfo = $row;
                        }
                    }
                break;
        }
        return $itemInfo;
    }
    /**
     * 通过id，获取该分类下面所有的分类
     * @param type $catId
     * @param type $level
     */
    static public function getCatSonId($catId,$levle='')
    {
        $offspringData = array();
        $rightCatId = Data_ItemCategory::getRightBrotherId($catId);
        $data =  Data_ItemCategory::get();
        foreach($data as $value){
            if($catId < $value['id'] && $value['id'] < $rightCatId){
                if(empty($levle)) {
                    $offspringData[] = $value;  
                } else {
                    if($value['level'] == $levle){
                        $offspringData[] = $value;       
                    }
                }

            }
        }
        return $offspringData;   
    }
    /**
     * 获取拍品的长分类
     * @param type $catId
     * @param type $type   
     * @return type
     * @author wangkongming <komiles@163.com>
     */
    public static function getLongCatId($catId)
    {
        $catId = trim($catId);
        if(!empty($catId)) {
            //数据中分级的数量/即最大分级
            $maxLevel = intval(ceil(strlen($catId) / 3));
            if($maxLevel < Conf_Item::MAX_LEVEL) {
                $catId = $catId * pow(1000, Conf_Item::MAX_LEVEL - $maxLevel);
            }  
        }
        return sprintf("%.0f", $catId);
    }
    
    /**
     * 根据catId，获取该分类的名称，拼音，短分类
     *$type ，需要获取的类型 可选值为pinyin,name,shortCatId
     */
    static public  function getCatInfoByType($catId, $type)
    {
        $catResult = array();
        if($catId) {
            $catInfo = Data_ItemCategory::get($catId);
            if(is_array($catInfo) && isset($catInfo['level']) && $catInfo['level'] == 1) {
                $catResult['catName'] = $catInfo['name'];
                $catResult['catNamePinyin'] = $catInfo['pinyin'];
                $catResult['topCatInfo'] = $catInfo;
            } elseif (is_array($catInfo) && isset($catInfo['level']) && $catInfo['level'] == 2) {
                //顶级分类Id
                $topCatId = Data_Abstract::getParentId($catId, $catInfo['level']);
                $topCatIdInfo = Data_ItemCategory::get($topCatId);
                //顶级分类拼音
                $topCatPinyin = $topCatIdInfo['pinyin'];
                $catResult['catName'] = $catInfo['name'];
                $catResult['catNamePinyin'] = $topCatPinyin.'/'.$catInfo['pinyin'];
                $catResult['topCatInfo'] = $topCatIdInfo;
                $catResult['secondCatInfo'] = $catInfo;
            } elseif (is_array($catInfo) && isset($catInfo['level']) && $catInfo['level'] == 3) {
                //获取二级分类
                $secondCatId = Data_Abstract::getParentId($catId, $catInfo['level']);
                $secondCatIdInfo = Data_ItemCategory::get($secondCatId);
                //二级分类拼音
                $secondCatPinyin = $secondCatIdInfo['pinyin'];
                //获取顶级分类
                $topCatId = Data_Abstract::getTopParentId($catId);
                $topCatIdInfo = Data_ItemCategory::get($topCatId);
                //顶级分类拼音
                $topCatPinyin = $topCatIdInfo['pinyin'];
                $catResult['catName'] = $catInfo['name'];
                $catResult['catNamePinyin'] = $topCatPinyin.'/'.$secondCatPinyin.'-'.$catInfo['pinyin'];
                $catResult['topCatInfo'] = $topCatIdInfo;
                $catResult['secondCatInfo'] = $secondCatIdInfo;
                $catResult['thirdCatInfo'] = $catInfo;
            }
                $catResult['shortCatId'] = CategoryModel::getShortCatId($catId);
        } 
        if(isset($type) && $type == 'name') {
            return isset($catResult['catName']) ? $catResult['catName'] : '';
        } elseif(isset($type) && $type == 'pinyin') {
            return isset($catResult['catNamePinyin']) ? $catResult['catNamePinyin'] : '';
        } elseif(isset ($type) && $type == 'shortCatId'){
            return isset($catResult['shortCatId']) ? $catResult['shortCatId'] : '';
        }else {
            return $catResult; 
        }
    }
}
?>