<?php

class BaseInterfaceController extends Kfz_System_ControllerAbstract
{
    /**
     * 接口传递的参数
     * 
     * @var array 
     */
    public $params = array();
	/**
	 * 卖家中心控制器的父类，用以初始化卖家中心控制器所需的公共属性和方法，对公共业务进行统一处理
	 *
	 */
	public function init()
	{
		//执行父类的初始化方法
		parent::init();
//        if(!$this->request->isPost()){
//            die('forbid access!');
//        }
	}
    
    /**
     * 输出执行结果给客户端
     */
    public function response($result, $error = '')
    {
        $resultSet = array('result' => $result, 'error' => $error);
        $serialContents = serialize($resultSet);
//        header('Content-Type:text/html;charset=UTF-8');
        #header('Content-Type:text/plain;charset=UTF-8');
        echo $serialContents;
        exit();
    }
    
    /**
     * 验证RPC服务
     */
    public function checkRpc()
    {
        $error = '';
        #禁止GET方法访问
        if(!$this->request->isPost()) {
            die('Error sign！');
        }

        #取得客户端发送的序列化数据
        $contents = $this->request->getPost('CONTENTS');
        //对contents数据做html解码操作,保证验证数据正确
        if($contents != ''){
            $contents = Kfz_Lib_Secure::specialCharsDecode($contents);
        }
        if(get_magic_quotes_gpc()) {
            $contents = stripslashes($contents);
        }

        #未接收到序列化数据
        if($contents == '') {
            $error = 'Does not receive the serialized data.';
            $this->response(NULL, $error);
        }

        #验证安全签名
        $secureSign = isset($_POST['SIGN']) ? $_POST['SIGN'] : '';
        if('' == $secureSign || ! Kfz_Lib_Secure::validSign($secureSign, $contents)) {
            $error = 'Invalid security signature.';
            $this->response(NULL, $error);
        }

        #反序列化
        $data = unserialize($contents);
        if(gettype($data) != 'array') {
            $error = 'Invalid serialized data:' . "\n" . $contents;
            $this->response(NULL, $error);
        }
    }
    
    /**
     * RPC服务
     */
    public function serviceAction()
    {
        $error = '';
        #禁止GET方法访问
        if (!$this->request->isPost()) {
            die('forbid access');
        }

        #取得客户端发送的序列化数据
        $contents = $this->request->getPost('CONTENTS');

        if (get_magic_quotes_gpc()) {
            $contents = stripslashes($contents);
        }

        #未接收到序列化数据
        if ($contents == '') {
            $error = 'Does not receive the serialized data.';
            $this->response(NULL, $error);
        }

        #验证安全签名
        $secureSign = $this->request->getPost('SIGN');
        if ('' == $secureSign || !Kfz_Lib_Secure::validSign($secureSign, $contents)) {
            $error = 'Invalid security signature.';
            $this->response(NULL, $error);
        }

        #反序列化
        $data = unserialize($contents);
        if (gettype($data) != 'array') {
            $error = 'Invalid serialized data:' . "\n" . $contents;
            $this->response(NULL, $error);
        }

        #调用绑定的方法, 判断要调用的方法是否存在
        $methodName = $data['METHOD'];
        $className = get_class($this);
        if (!in_array($methodName, get_class_methods($this))) {
            $error = 'Call to undefined remote method ' . $className . '::' . $methodName . '().';
            $this->response(NULL, $error);
        }

        #调用绑定的方法并取得执行结果
        $arguments = $data['ARGUMENTS'];
        $result = $this->$methodName($arguments);
        /*
        try {
            $result = $this->$methodName($arguments);
            //返回的结果默认是编码过的，为保证返回结果的正确性，做一次解码处理
        } catch (Exception $e) {
            #$error = 'Uncaught exception \''.get_class($e).'\' with message \''.$e->getMessage().'\' in '.$e->getFile().':'.$e->getLine();
            $error = '' . $e;
        }
        */

        $this->response($result, $error);
    }

}
