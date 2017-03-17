<?php
/**
 * 脚本程序入口
 * 执行方式：php-5.3 /data/webroot/pmgsyaf/cgi/cli.php request_uri="/cli/batchwatermark"
 */
date_default_timezone_set('Asia/Shanghai');
define("APP_PATH",  realpath(dirname(__FILE__) . '/../../')); /* 指向public的上一级 */
include (APP_PATH . "/conf/const.php");
$app = new Yaf\Application(APP_PATH . "/conf/application.ini");
//Yaf_Loader::getInstance()->setLibraryPath("/data/webroot/kongframework",true);
$app->bootstrap();
$app->getDispatcher()->dispatch(new Yaf\Request\Simple());
$app->run();
