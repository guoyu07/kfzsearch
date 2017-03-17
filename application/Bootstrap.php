<?php

/**
 * 初始化类
 * 该类执行应用项目所需的初始化工作，会在调用控制器之前执行，方法名称开始部分必须包含“_init”
 *
 * @author shenxi <shenxi@kongfz.com>
 * @date 2013-03-13
 */

class Bootstrap extends Yaf\Bootstrap_Abstract
{
    /**
     * 注册配置对象
     */
    public function _initBootstrap()
    {
        Yaf\Registry::set('g_config', Yaf\Application::app()->getConfig());

    }
    /**
     * 注册插件
     * @param Yaf\Dispatcher $dispatcher
     */
    public function _initPlugin(Yaf\Dispatcher $dispatcher) {
        //注册一个插件
//        $objSellerPlugin = new DomainPlugin();
//        $dispatcher->registerPlugin($objSellerPlugin);
    }

    /**
     * 初始化路由协议
     * @param Yaf\Dispatcher $dispatcher
     */
    public function _initRoute(Yaf\Dispatcher $dispatcher)
    {
        $router = $dispatcher->getRouter();
        $router->addConfig(Yaf\Application::app()->getConfig()->routes);
    }

    /**
     * 初始化模板引擎
     * 默认使用yaf框架提供的简单模板引擎，Core_System_View是对Yaf\View_Simple的封装
     */
    public function _initView(Yaf\Dispatcher $dispatcher)
    {
        //是否关闭模板自动渲染
        if(isset(Yaf\Application::app()->getConfig()->view->disableView) && Yaf\Application::app()->getConfig()->view->disableView == 1){
            $dispatcher->disableView();
        }
        
        $view = new Kfz_System_View(null);

        $dispatcher->setView($view);

    }

    /**
     * 注册公共对象
     * @param Yaf\Dispatcher $dispatcher
     */
    public function _initRegistry(Yaf\Dispatcher $dispatcher)
    {
        //将数据库参数注册成db对象
        if(isset(Yaf\Application::app()->getConfig()->db)){
            Yaf\Registry::set('g_db', Yaf\Application::app()->getConfig()->db->toArray());
        }

        //注册缓存信息
        if(isset(Yaf\Application::app()->getConfig()->cache)){
            Yaf\Registry::set('g_cache', Yaf\Application::app()->getConfig()->cache->toArray());
        }
    }

    /**
     * 初始化session
     */
    public function _initSession(Yaf\Dispatcher $dispatcher)
    {
//        if (!$dispatcher->getRequest()->isCli()) {
//            //判断是否时综合管理后台的业务模块
//            $moduleName = $dispatcher->getRequest()->getModuleName();
//            $controllerName = $dispatcher->getRequest()->getControllerName();
//            if(empty($moduleName)){
//                $moduleName = $dispatcher->getRequest()->get('m');
//            }
//            if(empty($moduleName)){
//                $requestUri = $dispatcher->getRequest()->getRequestUri();
//                if($requestUri != ''){
//                    $params = explode('/', $requestUri);
//                    $moduleName = isset($params[1]) ? $params[1] : '';
//                    $controllerName = isset($params[2]) ? $params[2] : '';
//                }
//            }
//            if(is_string($moduleName) && $moduleName != ''){
//                $moduleName = strtolower($moduleName);
//                $controllerName = strtolower($controllerName);
//            }
//
//            $curDomain = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
//            $searchAdmin = Yaf\Application::app()->getConfig()->site->searchAdmin;
//            if($moduleName == 'admin' || stripos($searchAdmin, $curDomain) !== FALSE){
//                $session = Yaf\Application::app()->getConfig()->cache->memcache->adminSession->toArray();
//            }else{
//                $session = Yaf\Application::app()->getConfig()->cache->memcache->session->toArray();
//                $session[0]['domain'] = Yaf\Application::app()->getConfig()->application->session->domain;
//            }
//
//            $memSess = new Tool\MemcacheSession($session[0]['host'], $session[0]['port'], $session[0]['domain'], $session[0]['leftTime']);
//            $memSess->initSess();
//            session_cache_limiter('private, must-revalidate');
//            session_start();
//        }

    }

}
