<?php

/**
 * product索引更新
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2014年9月9日12:26:20
 */
class ProductIndexUpdateModel extends Kfz_System_ModelAbstract
{

    private $updateClient;
    private $jobSevers;
    private $redis;
    private $user;
    private $password;

    /**
     * product索引更新
     */
    public function __construct()
    {
        $this->jobSevers     = Yaf\Registry::get('g_config')->index->product->server->jobServers;
        $this->redis         = Yaf\Registry::get('g_config')->index->product->server->redis;
        $this->user          = Yaf\Registry::get('g_config')->index->product->server->user;
        $this->password      = Yaf\Registry::get('g_config')->index->product->server->password;
        $this->updateClient  = new Interface_IndexclientModel($this->jobSevers, $this->redis, $this->user, $this->password);
    }
    
    public function update($id, $attr, $isAsync = TRUE, $where = '', $index = 'product', $type = 'shop')
    {
        return $this->updateClient->update($index, $type, $attr, $id, $where, $isAsync);
    }
    
    public function delete($id, $isAsync = TRUE, $where = '', $index = 'product', $type = 'shop')
    {
        return $this->updateClient->delete($index, $type, $id, $where, $isAsync);
    }

}
?>