<?php
/**
 * 该文件定义应用的根目录，并初始化Yaf\Application对象
 * 主要实现一下几个操作：
 * 1、定义了应用的根目录为本文件的上一目录
 * 2、初始化Yaf\Application对象时，将配置文件application.ini加载到内存
 * 3、Yaf\Application::bootstrap()方法初始化应用本身定义的功能
 * 4、Yaf\Application::run()方法运行一个Yaf\Application, 开始接受并处理请求
 */
//set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors',1);

/* 调整默认的http useragent信息 */
ini_set('user_agent', 'kfzagent');

/* 设置输出流字符编码 */
header('Content-type: text/html;charset=utf-8');

/* 设置时区 */
date_default_timezone_set('Asia/Shanghai');

define("APP_PATH",  realpath(dirname(__FILE__) . '/../')); /* 指向public的上一级 */
include (APP_PATH . "/conf/const.php");
$app     = new Yaf\Application(APP_PATH . "/conf/application.ini");
$app->bootstrap()->run();
