<?php

/**
 * 缓存工具类
 *
 * @author DongNan <dongyh@126.com>
 * @date   2013-10-21
 */
class Tool_Cache
{

	public static function getInstance($poolName, $cacheType = 'memcached', $nameSpace = 'shop')
	{
		$g_cache = Yaf\Registry::get('g_cache');
		return Kfz_Cache_Manager::getInstance($g_cache[$cacheType][$poolName], $nameSpace, $cacheType);
	}

	/**
	 * 获取缓存key
	 *
	 * @param string $bizName
	 * @param string $str
	 *
	 * @return string
	 */
	public static function getKey($bizName, $str)
	{
		if(stripos($bizName, Conf_Cache::SQL) === 0){
			return $bizName . md5($str);
		}else{
			return $bizName . $str;
		}
	}

	/**
	 * 获取缓存生存周期
	 *
	 * @param string $bizName
	 * @param string $str
	 *
	 * @return string
	 */
	public static function getTime($bizName)
	{
		$time = Conf_Cache::$TIME;
		if(isset($time[$bizName])){
			return $time[$bizName];
		}else{
			return 0;
		}
	}

}

?>