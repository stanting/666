<?php
namespace Core\Base;

use Core\Base\Core;

class Model
{
    private $unique = []; //防止重复查询
    
    public $table; //表名
    public $pri = []; //主键名
    public $maxid; //自增字段
    
    public static $dbs = [];
    public static $caches = [];
    
    /**
     * 创建一次db/cache对象
     *
     * @param string $var 只能是db cache
     * @return object
     */
    public function __get($var)
    {
        switch ($var) {
            case 'db':
                return $this->db = $this->loadDb();
            case 'cache':
                return $this->cache = $this->loadCache();
            case 'db_conf':
                return $this->db_conf = &$_ENV['_config']['db'];
            case 'cache_conf':
                return $this->cache_conf = &$_ENV['_config']['cache'];
            default:
                return $this->$var = self::model($var);
        }
    }
    
    /**
     * 未定义的模型方法抛出异常
     * 
     * @param string $method 不存在的方法名
     */
    public function __call($method, $args)
    {
        throw new \Exception("方法 $method 不存在");
    }
    
    /**
     * 创建模型中的数据库操作对象
     *
     * @param string $model 类名或表名
     * @return object 数据库连接对象
     */
    public static function model($model)
    {
        $modelname = $model . '.php';
        
        if (isset($_ENV['_models'][$modelname])) {
            return $_ENV['_model'][$modelname];
        }
        
        $objfile = RUNTIME_MODEL . $modelname;
        
        //如果缓存文件不存在，则搜索原始文件，并编译写入缓存文件中
        if (DEBUG || !is_file($objfile)) {
            $modelfile = Core::getOriginalFile($modelname, MODEL_PATH);
            
            if (!$modelfile) {
                throw new \Exception("模型 $modelname 文件不存在");
            }

            $s = file_get_contents($modelfile);
            $s = preg_replace_callback('/\t*\/\/\s*hook\s+([\w\.]+)[\r\n]/', ['Core\Base\Core', 'parseHook'], $s);

            if (!FW($objfile, $s)) {
                throw new \Exception("写入model编译文件 $modelname 失败");
            }
            
            include $objfile;
            
            $model = ucfirst(APP_NAME) . '\\Model\\' . $model;
            $mod = new $model();
            
            $_ENV['_models'][$modelname] = $mod;
            
            return $mod;
        }
    }
    /**
     * 加载db对象
     *
     * @return object
     */
    public function loadDb()
    {
        $type = $this->db_conf['type'];

        if (isset($this->db_conf['master'])) {
            $c = $this->db_conf['master'];
            $id = $type . '-' . $c['host'] . '-' . $c['user'] . '-' . $c['password'] . '-' . $c['dbname'] . '-' . $c['tablepre'];
        } else {
            $id = $type;
        }
        
        if (isset(self::$dbs[$id])) {
            return self::$dbs[$id];
        } else {
            $db = 'Core\\Db\\' . ucfirst($type);

            self::$dbs[$id] = new $db($this->db_conf);
            
            return self::$dbs[$id];
        }
    }
    
    /**
     * 加载cache对象
     *
     * @return object
     */
    public static function loadCache()
    {
        $type = $this->cache_conf['type'];
        
        if (isset($this->cache_conf[$type])) {
            $c = $this->cache_conf[$type];
            $id = $type . '-' . $c['host'] . '-' . $c['port'];
        } else {
            $id = $type;
        }
        
        if (isset(self::$caches[$id])) {
            return self::$caches[$id];
        } else {
            $cache = 'cache_' . $type;
            self::$caches[$id] = new $cache($this->cache_conf);
            
            return self::$caches[$id];
        }
    }
    
    /**
     * 创建一条数据
     *
     * @param array $data 数据（不要包含自增字段）
     * @return bool
     */
    public function create($data)
    {
        //如果没有自增字段，则不统计count（）、maxid（）
        if (empty($this->maxid)) {
            $key = $this->pri2key($data);
        } else {
            $data[$this->maxid] = $this->maxid('+1');
            $key = $this->pri2key($data);
            $this->count('+1');
            
            if ($this->cacheDbSet($key, $data)) {
                return $data[$this->maxid];
            } else {
                $this->maxid('-1');
                $this->count('-1');
                
                return false;
            }
        }
    }
    
    /**
     * 写入一条数据
     *
     * @param array $key 键名数组
     * @param array $data 数据
     * @param int $life 缓存时间（默认永久）
     * @return bool
     */
    public function set($key, $data, $life = 0)
    {
        $key = $this->arr2key($key);
        $this->unique[$key] = $data;
        
        return $this->cacheDbSet($key, $data, $life);
    }
    
    /**
     * 读取一条数据
     *
     * @param string $arg1-4 参数1-4
     * @return array
     */
    public function read($arg1, $arg2 = false, $arg3 = false, $arg4 = false)
    {
        $arr = ($arg2 !== false) ? $this->arg2arr($arg1, $arg2, $arg3, $arg4) : (array)$arg1;
        
        return $this->get($arr);
    }
    
    /**
     * 读取一条数据
     *
     * @param array $arr 键名数组
     * @return array
     */
    public function get($arr)
    {
        $key = $this->arr2key($arr);
        
        if (!isset($this->unique[$key])) {
            $this->unique[$key] = $this->cacheDbGet($key);
        }
        
        return $this->unique[$key];
    }
    
    /**
     * 读取多条数据
     *
     * @param array $arr 多列键名数组
     * @return array
     */
    public function mget($arr)
    {
        $data = [];
        
        foreach ($arr as $k => &$v) {
            $key = $this->arr2key($key);
            
            if (isset($this->unique[$v])) {
                $data[$v] = $this->unique[$v];
                unset($arr[$k]);
            } else {
                $this->unique[$key] = $data[$key] = null;
            }
        }
        
        $data2 = $this->cacheDbMultiGet($arr);
        
        return array_merge($data, $data2);
    }
    
    /**
     * 更新一条数据
     *
     * @param array $data 数据
     * @param int $life 缓存时间（默认为永久）
     * @return bool
     */
    public function update($data, $life = false)
    {
        $key = $this->pri2key($data);
        
        $this->unique[$key] = $data;
        
        return $this->cacheDbUpdate($key, $data, $life);
    }
    
    /**
     * 删除一条数据
     *
     * @param string $arg1-4 参数1-4
     * @return array
     */
    public function delete($arg1, $arg2 = false, $arg3 = false, $arg4 = false)
    {
        $arr = ($arg2 !== false) ? $this->arg2arr($arg1, $arg2, $arg3, $arg4) : (array)$arg1;
        
        return $this->del($arr);
    }
    
    /**
     * 删除一条数据
     *
     * @param string $arr 键名数组
     * @return bool
     */
    public function del($arr)
    {
        $key = $this->arr2key($arr);
        
        $ret = $this->cacheDbDelete($key);
        
        if ($ret) {
            unset($this->unique[$key]);
            $this->count('-1');
        }
        
        return $ret;
    }
    
    public function truncate()
    {
        return $this->cacheDbTruncate();
    }
    
    /**
     * 根据条件读取数据
     *
     * @param array $where 条件
     * @param array $order 排序
     * @param int $start 开始位置
     * @param int $limit 读取条数
     * @param int $life 二级缓存时间（默认永久）
     * @return array
     */
    public function findFetch($where = [], $order = [], $start = 0, $limit = 0, $life = 0)
    {
        return $this->cacheDbFindFetch($this->table, $this->pri, $where, $order, $start, $limit, $life);
    }
    
    /**
     * 根据条件返回key数组
     *
     * @param array $where 条件
     * @param array $order 排序
     * @param int $start 开始位置
     * @param int $limit 读取几条
     * @param int $life 二级缓存时间
     * @return array
     */
    public function findFetchKey($where = [], $order = [], $start = 0, $limit = 0, $life = 0)
    {
        return $this->cacheDbFindFetchKey($this->table, $this->pri, $where, $order, $start, $limit, $life);
    }
    
    /**
     * 根据条件批量更新数据
     *
     * @param array $where 条件
     * @param array $lowprority 是否开启不锁定表
     * @return int 返回影响行数
     */
    public function findUpdate($where, $data, $lowprority = false)
    {
        $this->unique = [];
        
        if ($this->cache_conf['enable']) {
            $n = $this->findCount($where);
            
            if ($n > 2000) {
                $this->cache->truncate($this->table);
            } else {
                $keys = $this->findFetchKey($where);
                
                foreach ($keys as $key) {
                    $this->cache->delete($key);
                }
            }
        }
        
        return $this->db->findUpdate($this->table, $where, $data, $lowprority);
    }
    
    /**
     * 根据条件批量删除数据
     *
     * @param array $where 条件
     * @param bool $lowprority 是否开启不锁定表
     * @return int 返回影响条数
     */
    public function findDelete($where, $lowprority = false)
    {
        $this->unique = [];
        
        if ($this->cache_conf['enable']) {
            $n = $this->findCount($where);
            
            if ($n > 2000) {
                $this->cache->truncate($this->table);
            } else {
                $keys = $this->findFetchKey($where);
                
                foreach ($keys as $key) {
                    $this->cacheDbDelete($key);
                }
            }
        }
        
        $num = $this->db->findDelete($this->table, $where, $lowprority);
        
        if (!empty($this->maxid) && $num > 0) {
            $this->count('-' . $num);
        }
        
        return $num;
    }
    
    /**
     * 获取最大ID
     *
     * @param string $key 键名
     * @return int 返回ID
     */
    public function findMaxid()
    {
        return isset($this->maxid) ? $this->db->findMaxid($this->table . '-' . $this->maxid) : 0;
    }
    
    /**
     * 获取总条数
     *
     * @param array $where 条件
     * @return int 条数
     */
    public function findCount($where = [])
    {
        return $this->db->findCount($this->table, $where);
    }
    
    /**
     * 创建索引
     *
     * @param array $index 键名数组
     * @return int 返回ID
     */
    public function indexCreate($index)
    {
        return $this->db->indexCreate($this->table, $index);
    }
    
    /**
     * 删除索引
     *
     * @param array $index 键名数组
     * @return int 返回ID
     */
    public function indexDrop($index)
    {
        return $this->db->indexDrop($this->table, $index);
    }
    
    /**
     * 主键转key
     *
     * @param array $arr 关联数组
     * @return string 返回标准key
     */
    public function pri2key($arr)
    {
        $s = $this->table;
        
        foreach ($this->pri as $v) {
            $s .= "-$v-" . $arr[$v];
        }
        
        return $s;
    }
    
    /**
     * 数组转key
     *
     * @param array $arr 索引数组
     * @return string
     */
    public function arr2key($arr)
    {
        $arr = (array)$arr;
        $s = $this->table;
        
        foreach ($this->pri as $k => $v) {
            if (!isset($arr[$k])) {
                $err = [];
                
                foreach ($this->pri as $pk => $pv) {
                    $var = isset($arr[$pk]) ? $arr[$pk] : 'null';
                    $err[] = "'$pv => $var";
                }
                
                throw new \Exception('非法键名数组：array（' . implode(', ', $err) . ');');
            }
            
            $s .= "-$v-" . $arr[$k];
        }
        
        return $s;
    }
    
    /**
     * 多参数转数组
     *
     * @param int $arg1-4 参数1-4
     * @return array
     */
    public function arg2arr($arg1, $arg2, $arg3 = false, $arg4 = false)
    {
        $arr = (array)$arg1;
        array_push($arr, $arg2);
        
        $arg3 !== false && array_push($arr, $arg3);
        $arg4 !== false && array_push($arr, $arg4);
        
        return $arr;
    }
    
    /**
     * 读取、设置表的总行数
     *
     * @param string $val 设置值
     * @return int
     */
    public function count($val = false)
    {
        return $this->cacheDbCount($val);
    }
    
    /**
     * 读取设置表最大ID
     *
     * @param string $val 设置值
     * @return int
     */
    public function maxid($val = false)
    {
        return $this->cacheDbMaxid($val);
    }
    
    /**
     * 读取缓存中的一条数据
     *
     * @param string $key 键名
     * @return mixed
     */
    public function cacheDbGet($key)
    {
        if ($this->cache_conf['enable']) {
            $data = $this->cache->get($key);
            
            if (empty($data)) {
                $data = $this->db->get($key);
                $this->cache->set($key, $data);
            }
            
            return $data;
        } else {
            return $this->db->get($key);
        }
    }
    
    /**
     * 读取多条数据
     *
     * @param array $keys 键名数组
     * @return array
     */
    public function cacheDbMultiGet($keys)
    {
        if ($this->cache_conf['enable']) {
            $data = $this->cahce->multiGet($keys);
            
            if (empty($data)) {
                $data = $this->db->multiGet($keys);
                
                foreach ((array)$data as $k => $v) {
                    $this->cache->set($k, $v);
                }
            } else {
                foreach ($data as $k => &$v) {
                    if ($v === false) {
                        $v = $this->db->get($k);
                        $this->cache->set($k, $v);
                    }
                }
            }
            
            return $data;
        } else {
            return $this->db->multiGet($keys);
        }
    }
    
    /**
     * 缓存中写入一条数据
     *
     * @param string $key 键名
     * @param mixed $data 数据
     * @param int $life 缓存时间（默认永久）
     * @return bool
     */
    public function cacheDbSet($key, $data, $life = 0)
    {
        $this->cache_conf['enable'] && $this->cache->set($key, $data, $life);
        
        return $this->db->set($key, $data);
    }
    
    /**
     * 读取、设置表最大ID
     *
     * @param string $val 设置值 1.不填为读取（默认） 2.基础上增加 3.设置指定值
     * @return int
     */
    public function cacheDbMaxid($val = false)
    {
        $key = $this->table . '-' . $this->maxid;
        
        if ($this->cache_conf['enable']) {
            if ($val === false) {
                $maxid = $this->cache->maxid($key, $val);
                
                if (empty($maxid)) {
                    $maxid = $this->db->maxid($key, $val);
                    
                    if (empty($maxid)) {
                        $maxid = $this->db->maxid($key, $val);
                        $this->cache->maxid($key, $maxid);
                    }
                    
                    return $maxid;
                } else {
                    $maxid = $this->db->maxid($key, $val);
                    
                    return $this->cache->maxid($key, $maxid);
                }
            }
        } else {
            return $this->db->maxid($key, $val);
        }
    }
    
    /**
     * 读取、设置表的总行数
     *
     * @param string $val 设置值 1.不填为读取（默认） 2.基础上增加 3.设置指定值
     * @return int
     */
    public function cacheDbCount($val = false)
    {
        $key = $this->table;
        
        if ($this->cache_conf['enable']) {
            if ($val === false) {
                $rows = $this->cache->count($key, $val);
                
                if (empty($rows)) {
                    $rows = $this->db->count($key, $rows);
                    $this->cache->count($key, $rows);
                }
                
                return $rows;
            } else {
                $rows = $this->db->count($key, $val);
                
                return $this->cache->count($key, $rows);
            }
        } else {
            return $this->db->count($key, $val);
        }
    }
    
    /**
     * 更新一条数据
     *
     * @param string $key 键名
     * @param array $data 数据
     * @param int $life 缓存时间
     * @return bool
     */
    public function cacheDbUpdate($key, $data, $life = 0)
    {
        $this->cache_conf['enable'] && $this->cache->update($key, $data, $life);
        
        return $this->db->update($key, $data);
    }
    
    /**
     * 删除一条数据
     *
     * @param string $key 键名
     * @return bool
     */
    public function cacheDbDelete($key)
    {
        $this->cache_conf['enable'] && $this->cache->delete($key);
        
        return $this->db->delete();
    }
    
    /**
     * 清空数据
     *
     * @return bool
     */
    public function cacheDbTruncate()
    {
        $this->cache_conf['enable'] && $this->cache->truncate($this->table);
        
        return $this->db->truncate($this->table);
    }
    
    /**
     * 根据条件读取数据
     *
     * @param string $table 表名
     * @param array $pri 主键
     * @param array $where 条件
     * @param array $order 排序
     * @param int $start 开始位置
     * @param int $limit 读取条数
     * @param int $life 二级缓存时间（默认永久）
     * @return array
     */
    public function cacheDbFindFetch($table, $pri, $where = [], $order = [], $start = 0, $limit = 0, $life = 0)
    {
        if ($this->db_conf['type'] === 'mongodb') {
            return $this->db->findFetch($table, $pri, $where, $order, $start, $limit);
        } else {
            $key = $this->cacheDbFindFetchKey($table, $pri, $where, $order, $start, $limit, $life);
            
            return $this->cacheDbMultiGet($key);
        }
    }
    
    /**
     * 根据条件返回key数组
     *
     * @
     * @return array
     */
    public function cacheDbFindFetchKey($table, $pri, $where = [], $order = [], $start = 0, $limit = 0, $life = 0)
    {
        if ($this->cache_conf['enable'] && $this->cache_conf['l2_cache'] === 1) {
            $key = $table . '-' . md5(serialize([$pri, $where, $order, $start, $limit]));
            $keys = $this->cache->l2CacheGet($key);
            
            if (empty($keys)) {
                $keys = $this->db->findFetchKey($table, $pri, $where, $order, $start, $limit);
                
                $this->cache->l2CacheSet($key, $keys, $life);
            }
        } else {
            $keys = $this->db->findFetchKey($table, $pri, $where, $order, $start, $limit);
        }
        
        return $keys;
    }
}