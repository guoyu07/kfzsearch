<?php
/**
 * cli脚本
 * 
 * @author Shen Xi<shenxi@kongfz.com>
 * @date 2013-09-23
 */
class CliController extends Kfz_System_ControllerAbstract
{
    /**
     * 控制器初始化方法
     */
    public function init()
    {
        parent::init();
//        if(!$this->request->isCli()){
//            die('Forbid accessing!');
//        }
    }
    
}
?>
