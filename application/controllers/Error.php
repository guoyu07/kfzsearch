<?php

/*
 * 非捕获的异常处理
 * @author wangkongming<komiles@163.com>
 * @date 2014-07-22 16:58:00
 */

class ErrorController extends Kfz_System_ControllerAbstract
{
    public function init()
    {
        parent::init();

    }

    /**
     * 默认执行方法
     */
    public function errorAction($exception = null)
    {
        $env  = Yaf\Application::app()->environ();
        $site =  Yaf\Registry::get('g_config')->site;
        $this->view->assign('site',$site);
        $this->view->assign('staticId',time());
        if($exception === null){
            $this->display('error/missing.html');
        }else if('dev' == $env){
            $errMsg = array();

            $errMsg[] = $exception->getMessage();
            $errorStr = "";
            Kfz_Lib_Debug::_echo($exception);

            $message = $exception->__toString();
//                Kfz_Lib_Log::writeLog(EXCEPTION_FILE_PATH . EXCEPTION_FILE_NAME,$message,Kfz_Lib_Log::INFO);
            if(defined('ERROR_LOG_TYPE')){
                $message = $exception->__toString();
                $bizType = defined('BIZ_TYPE') ? BIZ_TYPE : '';
                $level   = isset($exception->level) ? $exception->level : 3;

                if(ERROR_LOG_TYPE == 'logsys'){//走日志系统
                    Kfz_Lib_LogUtils::writeLog($level, $bizType, $message);
                }else if(defined('EXCEPTION_FILE_PATH') && defined('EXCEPTION_FILE_NAME')){//写日志文件
                    $file    = EXCEPTION_FILE_PATH . EXCEPTION_FILE_NAME;
                    Kfz_Lib_Log::writeLog($file, $message, $level);
                }
            }
            echo $message; //die($exception->getMessage());
            exit;
            $this->view->assign('errMsg', $errMsg);
            $this->view->assign('errorStr', $errorStr);
            $this->display('./error/error.html');
        }else{
            $message = $exception->__toString();
            $level   = isset($exception->level) ? $exception->level : 3;
            
            if(defined('ERROR_LOG_TYPE')){
                $message = $exception->__toString();
                $bizType = defined('BIZ_TYPE') ? BIZ_TYPE : '';

                if(ERROR_LOG_TYPE == 'logsys'){//走日志系统
                    Kfz_Lib_LogUtils::writeLog($level, $bizType, $message);
                }else if(defined('EXCEPTION_FILE_PATH') && defined('EXCEPTION_FILE_NAME')){//写日志文件
                    $file    = EXCEPTION_FILE_PATH . EXCEPTION_FILE_NAME;
                    Kfz_Lib_Log::writeLog($file, $message, $level);
                }
            }
            exit;
            $this->display('./error/missing.html');
        }
        exit;
    }

}
