<?php
namespace Core\Db;

class Mysql implements Idb
{
    private $conf;
    public $tablepre;
    
    public function __construct(&$conf)
    {
        $this->conf = &$conf;
        $this->tablepre = $conf['master']['tablepre'];
    }
    
    /**
     * 创建数据库连接
     *
     * @param string $val 数据库连接名
     * @return resource
     */
    public function __get($var)
    {
        //主数据库（写）
        if ($var == 'wlink') {
            $cfg = $this->conf['master'];
            
            empty($cfg['engine']) && $cfg['engine'] = '';
            
            $this->wlink = $this->connect(
                $cfg['host'],
                $cfg['user'],
                $cfg['password'],
                $cfg['dbname'],
                $cfg['charset'],
                $cfg['engine']
            );
            
            return $this->wlink;
            
        //从数据库群（读）
        } elseif ($var == 'rlink') {
            if (empty($this->conf['slaves'])) {
                $this->rlink = $this->wlink;
                
                return $this->rlink;
            }
            
            $n = rand(0, count($this->conf['slaves']) - 1);
            $cfg = $this->conf['slaves'][$n];
            empty($cfg['engine']) && $cfg['engine'] = '';
            $this->rlink = $this->connect($cfg['host'], $cfg['user'], $cfg['password'], $cfg['dbname'], $cfg['charset'], $cfg['engine']);
            
            return $this->rlink;
            
        //单点分发数据库（负责所有表的maxid、count读写）
        } elseif ($var == 'xlink') {
            if (empty($this->conf['arbiter'])) {
                $this->xlink = $this->wlink;
                
                return $this->xlink;
            }
            
            $cfg = $this->conf['arbiter'];
            empty($cfg['engine']) && $cfg['engine'] = '';
            $this->xlink = $this->connect($cfg['host'], $cfg['user'], $cfg['password'], $cfg['dbname'], $cfg['charset'], $cfg['engine']);
            
            return $this->wlink;
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
        list($table, $keyarr, $keystr) = $this->key2arr($key);
        $query = $this->query("select * from {$this->tablepre}$table where $keystr limit 1", $this->rlink)->fetch(\PDO::FETCH_ASSOC);
        
        return $query;
    }
    
    /**
     * 读取多条数据
     *
     * @param array $keys 键名数组
     * @return array
     */
    public function multiGet($keys)
    {
        $sql = '';
        $ret = [];
        
        foreach ($keys as $k) {
            $ret[$k] = [];
            list($table, $keyarr, $keystr) = $this->key2arr($k);
            $sql .= "$keystr or ";
        }
        
        $sql = substr($sql, 0, -4);
        
        if ($sql) {
            $query = $this->query("select * from {$this->tablepre}$table where $sql", $this->rlink);
            
            while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $keyname = $table;
                
                foreach ($keyarr as $k => $v) {
                    $keyname .= "-$k-" . $row[$k];
                }
                
                $ret[$keyname] = $row;
            }
        }
        
        return $ret;
    }
    
    /**
     * 写入一条数据
     *
     * @param string $key 键名
     * @param array $data 数据
     * @return bool
     */
    public function set($key, $data)
    {
        if (!is_array($data)) {
            return false;
        }
        
        list($table, $keyarr) = $this->key2arr($key);
        $data += $keyarr;
        $s = $this->arr2sql($data);
        
        $exits = $this->get($key);
        
        if (empty($exits)) {
            return $this->query("insert into {$this->tablepre}$table set $s", $this->wlink);
        } else {
            return $this->update($key, $data);
        }
    }
    
    /**
     * 更新一条数据
     *
     * @param string $key 键名
     * @param array $data 数据
     * @return bool
     */
    public function update($key, $data)
    {
        list($table, $keyarr, $keystr) = $this->key2arr($key);
        $s = $this->arr2sql($data);
        
        return $this->query("update {$this->tablepre}$table set $s where $keystr limit 1", $this->wlink);
    }
    
    /**
     * 删除一条数据
     *
     * @param string $key 键名
     * @return bool
     */
    public function delete($key)
    {
        list($table, $keyarr, $keystr) = $this->key2arr($key);
        
        return $this->query("delete from {$this->tablepre}$table where $keystr limit 1", $this->wlink);
    }
    
    /**
     * 读取、设置表最大ID
     *
     * @param string $key 键名
     * @param bool|int $val 设置值 1.不填读取 2.基础上增加 3.设置指定值
     * @return int
     */
    public function maxid($key, $val = false)
    {
        list($table, $col) = explode('-', $key);
        $maxid = $this->tableMaxid($key);
        
        if ($val === false) {
            return $maxid;
        } elseif (is_string($val)) {
            $val = max(0, $maxid + intval($val));
        }
        
        $this->query("update {$this->tablepre} framework_maxid set maxid='$val' where name='$table' limit 1", $this->xlink);
        
        return $val;
    }
    
    /**
     * 读取表最大ID
     *
     * @param string $key 键名
     * @return int
     */
    public function tableMaxid($key)
    {
        list($table, $col) = explode('-', $key);
        
        $maxid = false;
        $query = $this->query("select maxid from {$this->tablepre}framework_maxid where name='$table' limit 1", $this->xlink, false);
        
        if ($query) {
            $maxid = $this->result($query, 0);
        } else {
            throw new Exception('framework_maxid error');
        }
        
        if ($maxid === false) {
            $query = $this->query("select max($col) from {$this->tablepre}$table", $this->wlink);
            $maxid = $this->result($query, 0);
            $this->query("insert into {$this->tablepre}framework_maxid set name='$table', maxid='$maxid'", $this->xlink);
        }
        
        return $maxid;
    }
    
    /**
     * 读取、设置表的总行数
     *
     * @param string $table 表名
     * @param bool|int $val 设置值
     * @return int
     */
    public function count($table, $val = false)
    {
        $count = $this->tableCount($table);
        
        if ($val === false) {
            return $count;
        } elseif (is_string($val)) {
            if ($val[0] == '+') {
                $val = $count + intval($val);
            } elseif ($val[0] == '-') {
                $val = max(0, $count + intval($val));
            }
        }
        
        $this->query("update {$this->tablepre}table_count set count='$val' where name='$table' limit 1", $this->xlink);
        
        return $val;
    }
    
    /**
     * 读取表的总行数
     *
     * @param string $table 表名
     * @return int
     */
    public function tableCount($table)
    {
        $count = false;
        $query = $this->query("select count from {$this->tablepre}table_count where name='$table' limit 1", $this->xlink, false);
        
        if ($query) {
            $count = $this->result($query, 0);
        } else {
            throw new Exception('table_count error');
        }
        
        if ($count === false) {
            $query = $this->query("select count(*) from {$this->tablepre}$table", $this->wlink);
            $count = $this->result($query, 0);
            $this->query("insert into {$this->tablepre}table_count set name='$table', count='$count'", $this->xlink);
        }
        
        return $count;
    }
    
    /**
     * 清空表
     *
     * @param string $table 表名
     * @return int
     */
    public function truncate($table)
    {
        try {
            $this->query("truncate {$this->tablepre}$table");
            
            return true;
        } catch (Exception $e) {
            return false;
        }
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
     * @return array
     */
    public function findFetch($table, $pri, $where = [], $order = [], $start = 0, $limit = 0)
    {
        $key_arr = $this->findFetchKey($table, $pri, $where, $order, $start, $limit);
        
        if (empty($key_arr)) {
            return [];
        }
        
        return $this->multiGet($key_arr);
    }
    
    /**
     * 根据条件返回key数组
     *
     * @param string $table 表名
     * @param array $pri 主键
     * @param array $where 条件
     * @param array $order 排序
     * @param int $start 开始位置
     * @param int $limit 读取条数
     * @return array
     */
    public function findFetchKey($table, $pri, $where = [], $order = [], $start = 0, $limit = 0)
    {
        $pris = implode(',', $pri);
        $s = "select $pris from {$this->tablepre}$table";
        $s .= $this->arr2where($where);
        
        if (!empty($order)) {
            $s .= ' order by ';
            $comma = '';
            
            foreach ($order as $k => $v) {
                $s .= $comma . "$k " . ($v == 1 ? ' ASC ' : ' DESC ');
                $comma = ',';
            }
        }
        
        $s .= ($limit ? " limit $start,$limit" : '');
        
        $ret = [];
        $query = $this->query($s, $this->rlink);
        
        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
            $keystr = '';
            
            foreach ($pri as $k) {
                $keystr .= "-$k-" . $row[$k];
            }
            
            $ret[] = $table . $keystr;
        }
        
        return $ret;
    }
    
    /**
     * 根据条件批量更新数据
     *
     * @param string $table 表名
     * @param array $where 条件
     * @param array $lowprority 是否开启不锁定表
     * @return int
     */
    public function findUpdate($table, $where, $data, $lowprority = false)
    {
        $where = $this->arr2where($where);
        $data = $this->arr2sql($data);
        $lowprority = $lowprority ? 'LOW_PRIORITY' : '';
        
        $rows = $this->wlink->exec("update $lowprority {$this->tablepre}$table set $data $where");
        
        return $rows;
    }
    
    /**
     * 根据条件批量删除数据
     *
     * @param string $table 表名
     * @param array $where 条件
     * @param array $lowprority 是否开始不锁定表
     * @return int
     */
    public function findDelete($table, $where, $lowprority = false)
    {
        $where = $this->arr2where($where);
        $lowprority = $lowprority ? 'LOW_PRIORITY' : '';
        
        $rows = $this->wlink->exec("delete $lowprority from {$this->tablepre}$table $where", $this->wlink);
        
        return $rows;
    }
    
    /**
     * 准确获取最大ID
     *
     * @param string $key 键名
     * @return int
     */
    public function findMaxid($key)
    {
        list($table, $maxid) = explode('-', $key);
        $arr = $this->fetchFirst("select max($maxid) as num from {$this->tablepre}$table");
        
        return isset($arr['num']) ? intval($arr['num']) : 0;
    }
    
    /**
     * 准确获取总条数
     *
     * @param sting $table 表名
     * @param array $where 条件
     * @return int
     */
    public function findCount($table, $where = [])
    {
        $where = $this->arr2where($where);
        $arr = $this->fetchAll("select count(*) as num from {$this->tablepre}$table $where");
        
        return isset($arr['num']) ? intval($arr['num']) : 0;
    }
    
    /**
     * 创建索引
     *
     * @param string $table 表名
     * @param array $index 键名数组
     * @return bool
     */
    public function indexCreate($table, $index)
    {
        $keys = implode(',', array_keys($index));
        $keyname = implode('_', array_keys($index));
        
        return $this->query("alter table {$this->tablepre}$table add index $keyname($keys)", $this->wlink);
    }
    
    /**
     * 删除索引
     *
     * @param string $table 表名
     * @param array $index 键名数组
     * @return bool
     */
    public function indexDrop($table, $index)
    {
        $key = implode(',', array_keys($index));
        $keyname = implode('_', array_keys($index));
        
        return $this->query("alter table {$this->tablepre}$table drop index $keyname", $this->wlink);
    }
    
    /**
     * 连接数据库服务器
     *
     * @param string $host 主机
     * @param string $username 用户名密码
     * @param string $password 密码
     * @param string $dbname 数据库名称
     * @param string $charset 字符集
     * @param string $engine 数据库引擎
     * @return resource
     */
    public function connect($host, $username, $password, $dbname, $charset = 'utf8', $engine = '')
    {
        try {
            $link = new \PDO('mysql:host=' . $host . ';dbname=' . $dbname, $username, $password);
        } catch (\PDOException $e) {
            throw new \Exception($e->getMessage());
        }
        
        return $link;
    }
    
    /**
     * 数据库查询
     *
     * @param string $sql SQL语句
     * @param string $link 打开的连接
     * @param bool $isthrow 错误时是否抛出
     * @return resource
     */
    public function query($sql, $link = null, $isthrow = true)
    {
        empty($link) && $link = $this->wlink;
        
        if (defined('DEBUG') && DEBUG && isset($_ENV['_sqls']) && count($_ENV['_sqls']) < 1000) {
            $start = microtime(1);
            $result = $link->query($sql);
            $runtime = number_format(microtime(1) - $start, 4);
            
            if (!$result) {
                $e = $link->errorinfo();
                var_dump($e);
            }
            //explain分析select语句
            $explain_str = '';
            
            if (substr($sql, 0, 6) == 'select') {
                $query = $link->query("explain $sql")->fetch(\PDO::FETCH_ASSOC);
                
                if ($query !== false) {
                    $explain_str = $query;
                    $explain_str = ' <font color="blue">[explain type: ' . $explain_str['type'] . ' | rows: ' . $explain_str['rows'] . ']</font>';
                }
            }
            
            $_ENV['_sqls'][] = ' <font color="red">[time:' . $runtime . 's]</font> ' . htmlspecialchars(stripslashes($sql)) . $explain_str;
        } else {
            $result = $link->query($sql);
        }
        
        if (!$result && $isthrow) {
            $s = 'MySQL Query Error: <b>' . $sql . '</b>';
            
            if (defined('DEBUG') && !DEBUG) {
                $s = str_replace($this->tablepre, '***', $s);
            }
            
            throw new \Exception($s);
        }
        
        $_ENV['_sqlnum']++;
        
        return $result;
    }
    
    /**
     * 将键名转换为数组
     *
     * @param string $key 键名
     * @return array
     */
    private function key2arr($key)
    {
        $arr = explode('-', $key);
        
        if (empty($arr[0])) {
            throw new Exception('table name is empty');
        }
        
        $table = $arr[0];
        $keyarr = [];
        $keystr = '';
        $len = count($arr);
        
        for ($i = 1; $i < $len; $i += 2) {
            if (isset($arr[$i + 1])) {
                $v = $arr[$i + 1];
                $keyarr[$arr[$i]] = is_numeric($v) ? intval($v) : $v;
                $keystr .= ($keystr ? ' and ' : '') . $arr[$i] . "='" . addslashes($v) . "'";
            } else {
                $keyarr[$arr[$i]] = null;
            }
        }
        
        if (empty($keystr)) {
            throw new Exception('keystr name is empty');
        }
        
        return [$table, $keyarr, $keystr];
    }
    
    /**
     * 将数组转换为SQL语句
     *
     * @param array $arr 数组
     * @return string
     */
    private function arr2sql($arr)
    {
        $s = '';
        
        foreach ($arr as $k => $v) {
            $v = addslashes($v);
            $s .= "$k='$v',";
        }
        
        return rtrim($s, ',');
    }
    
    /**
     * 数组转为where语句
     *
     * @param array $arr 数组
     * @return string
     */
    private function arr2where($arr)
    {
        $s = '';
        
        if (!empty($arr)) {
            foreach ($arr as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        if (is_array($v)) {
                            if ($k === 'in' && $v) {
                                foreach ($v as $i) {
                                    $i = addslashes($i);
                                    $s .= "$key='$i' or ";
                                }
                                
                                $s = substr($s, 0, -4) . ' and ';
                            }
                        } else {
                            $v = addslashes($v);
                            
                            if ($k === 'like') {
                                $s .= "$key like '%$v%' and ";
                            } else {
                                $s .= "$key$v'$v' and ";
                            }
                        }
                    }
                } else {
                    $val = addslashes($val);
                    $s .= "$key='$val' and ";
                }
            }
            
            $s && $s = ' where ' . substr($s, 0, -5);
        }
        
        return $s;
    }
    
    /**
     * 获取结果数据
     *
     * @param resource $query 查询结果集
     * @param int $row 第几列
     * @return int
     */
    public function result($query, $row)
    {
        
    }
    
    /**
     * 获取第一条数据
     *
     * @param string $sql SQL语句
     * @param string $link 打开的连接
     * @return array
     */
    public function fetchFirst($sql, $link = null)
    {
        empty($link) && $link = $this->rlink;
        $query = $this->query($sql, $link);
        
        return $query;
    }
    
    /**
     * 获取多条数据
     *
     * @param string $sql SQL语句
     * @param string $link 打开的连接
     * @return array
     */
    public function fetchAll($sql, $link = null)
    {
        empty($link) && $link = $link->rlink;
        $query = $this->query($sql, $link);
        
        return $query;       
    }
    
    /**
     * 获取MySQL版本
     *
     * @return string
     */
    public function version()
    {
        $version = $this->query("select version() as version", $this->rlink)->fetch(\PDO::FETCH_ASSOC);
        
        return $version['version'];
    }
}