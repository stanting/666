<?php

namespace Test\Model;

use Core\Base\Model;

class Kv extends Model
{
    private $data = [];
    private $changed = [];
    
    public function __construct()
    {
        $this->table = 'kv'; //表名
        $this->pri = ['k'];  //主键
    }
    
    //读取kv值
    public function get($k)
    {
        $arr = parent::get($k);
        
        return !empty($arr) && (empty($arr['expiry']) || $arr['expiry'] > $_ENV['_time']) ? _json_decode($arr['v']) : [];
    }
    
    //写入kv值
    public function set($k, $s, $life = 0)
    {
        $s = json_decode($s);
        
        $arr = [];
        $arr['k'] = $k;
        $arr['v'] = $s;
        $arr['expiry'] = $life ? $_ENV['_time'] + $life : 0;
        
        return parent::set($k, $arr);
    }
    
    //读取
    public function xget($key = 'cfg')
    {
        $this->data[$key] = $this->get($key);
        
        return $this->data[$key];
    }
    
    //修改
    public function xset($k, $v, $key = 'cfg')
    {
        if (!isset($this->data[$key])) {
            $this->data[$key] = $this->get($key);
        }
        
        $this->data[$key][$k] = $v;
        $this->changed[$key] = 1;
    }
    
    //保存
    public function xsave($key = 'cfg')
    {
        $this->set($key, $this->data[$key]);
        $this->changed[$key] = 0;
    }
    
    //保存所有修改过的key
    public function saveChange()
    {
        foreach ($this->changed as $k => $v) {
            $v && $this->xsave($key);
        }
    }
}