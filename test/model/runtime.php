<?php

namespace Test\Model;

use Core\Base\Model;

class Runtime extends Model
{
    private $data = [];
    private $changed = [];
    
    public function __construct()
    {
        $this->table = 'runtime'; //表名
        $this->pri = ['k']; //主键
        
        //
    }
    
    //读取缓存
    public function get($k)
    {
        $arr = parent::get($k);

        return !empty($arr) && (empty($arr['expiry']) || $arr['expiry'] > $_ENV['time']) ? _json_decode($arr['v']) : [];
    }
    
    //写入缓存
    public function set($k, $s, $life = 0)
    {
        $s = json_encode($s);
        
        $arr = [];
        $arr['k'] = $k;
        $arr['v'] = $s;
        $arr['expiry'] = $life ? $_ENV['_time'] + $life : 0;
        
        return parent::set($k, $arr);
    }
    
    //读取
    public function xget($key = 'cfg')
    {
        if (!isset($this->data[$key])) {
            $this->data[$key] = $this->get($key);
            
            if ($key == 'cfg' && empty($this->data[$key])) {
                $cfg = (array)$this->kv->get('cfg');
                
                empty($cfg['theme']) && $cfg['theme'] = 'default';
                
                $cfg['tpl'] = $cfg['webdir'] . (defined('F_APP_NAME') ? F_APP_NAME : APP_NAME) . '/view/' . $cfg['theme'] . '/';
                $cfg['webroot'] = 'http://' . $cfg['webdomain'];
                $cfg['weburl'] = 'http://' . $cfg['webdomain'] . $cfg['webdir'];
                
                $table_arr = $this->models->getTableArr();
                $cfg['table_arr'] = $table_arr;
                
                $mod_name = $this->models->getName();
                unset($mod_name[1]);
                $cfg['mod_name'] = $mod_name;
                
                $categorys = $this->category->getCategoryDb();
                $cate_arr = [];
                
                foreach ($categorys as $row) {
                    $cate_arr[$row['cid']] = $row['alias'];
                }
                
                $cfg['cate_arr'] = $cate_arr;
                
                $this->data[$key] = &$cfg;
                $this->set('cfg', $this->data[$key]);
            }
        }
        
        return $this->data[$key];
    }
    
    //修改
    public function xset($k, $v, $key = 'cfg')
    {
        if (!isset($this->data[$key])) {
            $this->data[$key] = $this->get($key);
        }
        
        if ($v && is_string($v) && ($v[0] == '+' || $v[0] == '-')) {
            $v = intval($v);
            $this->data[$key][$k] += $v;
        } else {
            $this->data[$key][$k] = $v;
        }
        
        $this->changed[$key] = 1;
    }
    
    //保存
    public function xsave($key = 'cfg')
    {
        $this->set($key, $this->data[$key]);
        $this->changed[$key] = 0;
    }
    
    //保存所有修改过的key
    public function saveChanged()
    {
        foreach ($this->changed as $key => $v) {
            $v && $this->xsave($key);
        }
    }
}