<?php
namespace Core\Cache;

class Memcache implements Icache
{
    private $conf;
    private $is_getmulti = false; //是否支持getMulti方法
    public $pre;
    
    public function __construct(&$conf)
    {
        $this->conf = &$conf;
        $this->pre = $conf['pre'];
    }
    
    public function __get($var)
    {
        $c = $this->conf['memcache'];
        
        if ($var == 'memcache') {
            if (extension_loaded('Memcache')) {
                $this->memcache = new \Memcache();
            } else {
                throw new Exception('Memcache Extension not loaded.');
            }
            
            if (!$this->memcache) {
                throw new Exception('php.ini Error : Memcache extension not loaded.');
            }
            
            if ($this->memcache->connect($c['host'], $c['port'])) {
                if (!empty($c['multi'])) {
                    $this->is_getmulti = method_exists($this->memcache, 'getMulti');
                }
                
                return $this->memcache;
            } else {
                throw new Exception('Can not connect to Memcached host.');
            }
        }
    }
    
    /**
     * 读取一条数据
     *
     * @param string $key 键名
     * @return array
     */
    public function get($key)
    {
        return $this->memcache->get($this->pre . $key);
    }
    
    /**
     * 读取多条数据
     *
     * @param array $keys 键名数组
     * @return array
     */
    public function multiGet($keys)
    {
        $data = [];
        
        if ($this->is_getmulti) {
            $m_keys = [];
            
            foreach ($keys as $i => $k) {
                $m_keys[$i] = $this->pre . $k;
            }
            
            $m_data = $this->memcache->getMulti($m_keys);
            
            foreach ($keys as $k) {
                if (empty($m_data[$this->pre . $k])) {
                    $data[$k] = false;
                } else {
                    $data[$k] = $m_data[$this->pre . $k];
                }
            }
        } else {
            foreach ($keys as $k) {
                $arr = $this->memcache->get($this->pre . $k);
                
                if (empty($arr)) {
                    $data[$k] = false;
                } else {
                    $data[$k] = $arr;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * 写入一条数据
     *
     * @param string $key 键名
     * @param array $data 数据
     * @param int $life 缓存时间
     * @return bool
     */
    public function set($key, $data, $life = 0)
    {
        if ($this->conf['l2_cache'] === 1) {
            $this->memcache->delete($this->pre . '_l2_cache_time');
        }
        
        return $this->memcache->set($this->pre . $key, $data, $life);
    }
    
    /**
     * 更新一条数据
     *
     * @param string $key 键名
     * @param array $data 数据
     * @param int $life 缓存时间
     * @return bool
     */
    public function update($key, $data, $life = 0)
    {
        $key = $this->pre . $key;
        $arr = $this->get($key);
        
        if ($arr !== false) {
            is_array($arr) && is_array($data) && $arr = array_merge($arr, $data);
            
            return $this->set($key, $arr, $life);
        }
        
        return false;
    }
    
    /**
     * 删除一条数据
     *
     * @param string $key 键名
     * @return bool
     */
    public function delete($key)
    {
        if ($this->conf['l2_cache'] === 1) {
            $this->memcache->delete($this->pre . '_l2_cache_time');
        }
        
        return $this->memcache->delete($this->pre . $key);
    }
    
    /**
     * 获取、设置最大ID
     *
     * @param string $table 表名
     * @param bool|int $val 值
     * @return int
     */
    public function maxid($table, $val = false)
    {
        $key = $table . '-Auto_increment';
        
        if ($val === false) {
            return intval($this->get($key));
        } else {
            $this->set($key, $val);
            
            return $val;
        }
    }
    
    /**
     * 获取、设置总条数
     *
     * @param string $table 表名
     * @param int|bool $val 值
     * @return int
     */
    public function count($table, $val = false)
    {
        $key = $table . '-Rows';
        
        if ($val === false) {
            return intval($this->get($key));
        } else {
            $this->set($key, $val);
            
            return $val;
        }
    }
    
    /**
     * 清空缓存
     *
     * @param string $pre 前缀
     * @return bool
     */
    public function truncate($pre = '')
    {
        return $this->memcache->flush();
    }
    
    /**
     * 读取二级缓存
     *
     * @param string $l2_key 二级缓存键名
     * @return bool
     */
    public function l2CacheGet($l2_key)
    {
        $l2_cache_time = $this->get('_l2_cache_time');
        $l2_key_time = $this->get($l2_key . '_time');
        
        if ($l2_cache_time && $l2_cache_time == $l2_key_time) {
            return $this->get($l2_key);
        }
        
        return false;
    }
    
    /**
     * 写入一条二级缓存
     *
     * @param string $l2_key 二级缓存键名
     * @param string $keys 键名数组
     * @return bool
     */
    public function l2CacheSet($l2_key, $keys, $life = 0)
    {
        $l2_cache_time = $this->get('_l2_cache_time');
        
        if (empty($l2_cache_time)) {
            $l2_cache_time = microtime(1);
            $this->memcache->set($this->pre . '_l2_cache_time', $l2_cache_time, 0);
        }
        
        $this->memcache->set($this->pre . $l2_key . '_time', $l2_cache_time, 0);
        return $this->memcache->set($this->pre . $l2_key, $keys, 0);
    }

}