<?php

namespace Test\Model;

use Core\Base\Model;

class Models extends Model
{
    private $data = [];
    
    public function __construct()
    {
        $this->table = 'models';
        $this->pri = ['mid'];
        $this->maxid = 'mid';
    }
    
    //获取所有模型
    public function getModels()
    {
        if (isset($this->data['models'])) {
            return $this->data['models'];
        }
        
        return $this->data['models'] = $this->findFetch();
    }
    
    //获取所有模型的名称
    public function getName()
    {
        if (isset($this->data['name'])) {
            return $this->data['name'];
        }
        
        $models_arr = $this->getModels();
        $arr = [];
        
        foreach ($models_arr as $v) {
            $arr[$v['mid']] = $v['name'];
        }
        
        return $this->data['name'] = $arr;
    }
    
    //获取所有模型的表名
    public function getTableArr()
    {
        if (isset($this->data['table_arr'])) {
            return $this->data['table_arr'];
        }
        
        $models_arr = $this->getModels();
        unset($models_arr[1]);
        $arr = [];
        
        foreach ($models_arr as $v) {
            $arr[$v['mid']] = $v['tablename'];
        }
        
        return $this->data['table_arr'] = $arr;
    }
    
    //根据mid获取模型的表名
    public function getTable($mid)
    {
        $data = $this->get($mid);
        
        return isset($data['tablename']) ? $data['tablename'] : 'article';
    }
}