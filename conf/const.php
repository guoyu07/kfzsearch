<?php
//基本url
define('BASE_URL', '/');

//根目录
define('ROOT_DIR', APP_PATH . "/public/");

//网站名称
define('BASE_NAME', '孔夫子搜索系统');

//定义业务类别，供框架代码使用
define('BIZ_TYPE','kfzsearch');

//定义第三方类库的根目录
define('PHPLIBS_DIR','/data/webroot/kongframework3.0/libs');

//没有商品图片时默认显示的商品图片
define('DEFAULT_ITEM_PIC',     '/images/none.jpg');

/* 日志文件路径 */
define('DATA_LOG',                '/data/logs/scripts/');
define('EXCEPTION_FILE_PATH',     DATA_LOG . 'kfzsearch/exception/'); //异常日志文件路径
define('PROJECT_LOG',             '/data/logs/scripts/kfzsearch/');

//cli 脚本运行日志
define('CLI_RUNTIME_LOG', DATA_LOG.'kfzsearch/cli/');

$fileDate = date('Y-m-d', time());
// 文件异常文件名称
define('EXCEPTION_FILE_NAME',     $fileDate . ".log");

/* 中文转换类相关 */
// 给中文转换类定义的包路径
define('TABLE_DIR', APP_PATH . '/application/library/tool/chinese_table');
// 中文转换类中是否使用系统存在的php内置编码转换函数
define('USEEXISTS', false);

// 图片后缀
define('ORI_JPG', '.jpg');// 表示原图的后缀，图片系统为 .jpg,旧系统为_b.jpg
define('S_JPG', '_s.jpg');
define('N_JPG', '_n.jpg');
define('B_JPG', '_b.jpg');

//发送邮件配置
//define('MAIL_HOST', 'localhost');
define('MAIL_HOST', 'mail.kongfz.cn');
define('MAIL_FROM', 'no-reply-2@kongfz.cn');
define('MAIL_USERNAME', 'no-reply-2@kongfz.cn');
define('MAIL_PASSWORD', 'kfzSMit$#hd');

//定义程序框架记录错误日志的类别
define('ERROR_SWITCH_FATAL', true);    //致命错误
define('ERROR_SWITCH_ERROR', true);    //普通错误
define('ERROR_SWITCH_WARN', true);     //警告信息
define('ERROR_SWITCH_INFO', true);     //运行信息
define('ERROR_SWITCH_DEBUG', false);   //调试信息
define('ERROR_SWITCH_TRACE', false);   //跟踪信息

//定义日志文件路径
define('INFO_FILE_PATH', DATA_LOG .'kfzsearch/info/');       //运行日志
define('DEBUG_FILE_PATH', DATA_LOG .'kfzsearch/debug/');     //调试日志
define('ERROR_FILE_PATH', DATA_LOG .'kfzsearch/error/');     //错误日志
define('FATAL_FILE_PATH', DATA_LOG .'kfzsearch/fatal/');     //致命错误日志
define('TRANCE_FILE_PATH', DATA_LOG .'kfzsearch/trance/');   //致命错误日志
define('WARNING_FILE_PATH', DATA_LOG .'kfzsearch/warning/'); //警告错误日志

define('ERROR_LOG_TYPE', 'logfile');   //logfile:写日志文件；logsys:日志系统

ini_set('default_socket_timeout', -1);