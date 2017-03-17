<?php
namespace Tool;

class MemcacheSession
{
    static $mSessSavePath;
    static $mSessName;
    static $mMemcacheObj;
    static $domain;
    static $lifetime;

    /**
     * 构造函数
     *
     * @param string $host
     * @param int $port
     * @param string $domain
     * @param int $lifetime
     */
    public function __construct($host, $port, $domain, $lifetime)
    {
        //我的memcache是以php模块的方式编译进去的，可以直接调用
        //如果没有，就请自己包含 Memcache-client.php 文件
        if(!class_exists('Memcache') || !function_exists('memcache_connect')){
            //die('Fatal Error:Can not load Memcache extension!');
            return false;
        }

        if(!empty(self::$mMemcacheObj) && is_object(self::$mMemcacheObj)){
            return false;
        }

        self::$mMemcacheObj = new \Memcache();

        if(!self::$mMemcacheObj->connect($host, $port)){
            //die('Fatal Error: Can not connect to memcache host '. MEMCACHE_HOST .':'. MEMCACHE_PORT);
            return false;
        }

        self::$domain = $domain;
        self::$lifetime = $lifetime;

        return TRUE;

    }

    /**
     *
     * @param   String  $pSavePath
     * @param   String  $pSessName
     *
     * @return  Bool    TRUE/FALSE
     */
    public static function sessOpen($pSavePath = '', $pSessName = '')
    {
        self::$mSessSavePath = $pSavePath;
        self::$mSessName = $pSessName;

        return TRUE;

    }

    /**
     *
     * @param   NULL
     *
     * @return  Bool    TRUE/FALSE
     */
    public static function sessClose()
    {
        return TRUE;

    }

    /**
     *
     * @param   String  $wSessId
     *
     * @return  Bool    TRUE/FALSE
     */
    public static function sessRead($wSessId = '')
    {
        $wData = self::$mMemcacheObj->get($wSessId);

        //先读数据，如果没有，就初始化一个
        if(!empty($wData)){
            return $wData;
        }else{
            //初始化一条空记录
            $ret = self::$mMemcacheObj->set($wSessId, '', 0, self::$lifetime);

            if(TRUE != $ret){
                //die("Fatal Error: Session ID $wSessId init failed!");

                return FALSE;
            }

            return TRUE;
        }

    }

    /**
     *
     * @param   String  $wSessId
     * @param   String  $wData
     *
     * @return  Bool    TRUE/FALSE
     */
    public static function sessWrite($wSessId = '', $wData = '')
    {
        $ret = self::$mMemcacheObj->replace($wSessId, $wData, 0, self::$lifetime);

        if(TRUE != $ret){
            //die("Fatal Error: SessionID $wSessId Save data failed!");


            return FALSE;
        }

        return TRUE;

    }

    /**
     *
     * @param   String  $wSessId
     *
     * @return  Bool    TRUE/FALSE
     */
    public static function sessDestroy($wSessId = '')
    {
        self::sessWrite($wSessId);

        return FALSE;

    }

    /**
     *
     * @param   NULL
     *
     * @return  Bool    TRUE/FALSE
     */
    public static function sessGc()
    {
        //无需额外回收,memcache有自己的过期回收机制


        return TRUE;

    }

    /**
     *
     * @param   NULL
     *
     * @return  Bool    TRUE/FALSE
     */
    public function initSess($name = "")
    {
        //不使用 GET/POST 变量方式
        ini_set('session.use_trans_sid', 0);

        //设置垃圾回收最大生存时间
        ini_set('session.gc_maxlifetime', self::$lifetime);

        //使用 COOKIE 保存 SESSION ID 的方式
        ini_set('session.use_cookies', 1);
        ini_set('session.cookie_path', '/');

        //多主机共享保存 SESSION ID 的 COOKIE
        ini_set('session.cookie_domain', self::$domain);

        //将 session.save_handler 设置为 user，而不是默认的 files
        $name = $name == "" ? 'user' : $name;
        session_module_name($name);

        //定义 SESSION 各项操作所对应的方法名：
        //对应于静态方法 My_Sess::open()，下同。
        session_set_save_handler(array('Tool\MemcacheSession', 'sessOpen'), array('Tool\MemcacheSession', 'sessClose'), array('Tool\MemcacheSession', 'sessRead'), array('Tool\MemcacheSession', 'sessWrite'), array('Tool\MemcacheSession', 'sessDestroy'), array('Tool\MemcacheSession', 'sessGc'));

    }

}