<?php
namespace Core\Base;

use Core\Base\Debug;

class Core
{
    /**
     * 开始加载框架
     */
    public static function start()
    {
        Debug::init();
        self::obStart();
        self::initSet();
        self::initGet();
        self::initControl();
    }

    /**
     * 打开输出控制缓冲
     */
    public static function obStart()
    {
        ob_start([__CLASS__, 'obGzip']);
    }
    
    /**
     * GZIP压缩处理
     *
     * @param string $s 数据
     * @return string
     */
    public static function obGzip($s)
    {
        $gzip = $_ENV['_config']['gzip'];
        $isfirst = empty($_ENV['_isgzip']);
        
        if ($gzip) {
            if (ini_get('zlib.output_compression')) {
                $isfirst && header('Content-Encoding: gzip');
            } elseif (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
                $s = gzencode($s, 5);
                
                if ($isfirst) {
                    header("Content-Encoding: gzip");
                }
            }
        } elseif ($isfirst) {
            header("Content-Encoding: none");
        }
        
        $isfirst && $_ENV['_isgzip'] = 1;
        
        return $s;
    }
    
    /**
     * 清空输出缓冲区
     */
    public static function obClean()
    {
        !empty($_ENV['_isgizp']) && ob_clean();
    }
    
    /**
     * 清空缓冲区并关闭输出缓冲
     */
    public static function obEndClean()
    {
        !empty($_ENV['_isgzip']) && ob_end_clean();
    }
    
    /**
     * 初始化基本设置
     */
    public static function initSet()
    {
        date_default_timezone_set($_ENV['_config']['time_zone']);
        
        spl_autoload_register([__CLASS__, 'autoloadHandler']);
        
        //初始化全局变量
        $_ENV['_sqls'] = [];
        $_ENV['_include'] = [];
        $_ENV['_time'] = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $_ENV['ip'] = ip();
        $_ENV['_sqlnum'] = 0;
        
        //输出header头
        header("Expires: 0");
        header("Cache-Control: private, post-check=0, pre-check=0, max-age=0");
        header("Pragma: no-cache");
        header('Content-Type: text/html; charset=UTF-8');
    }
    
    /**
     * 自动包含类文件
     *
     * @param string $classname 类名
     * @return bool
     */
    public static function autoloadHandler($classname)
    {
        if (substr($classname, 0, 3) == 'db_') {
            include CORE_PATH . 'db/' . $classname . '.php';
        } elseif (substr($classname, 0, 6) == 'cache_') {
            include CORE_PATH . 'cache/' . $classname . '.php';
        } elseif (is_file(CORE_PATH . 'ext/' . $classname . '.php')) {
            include CORE_PATH . 'ext/' . $classname . '.php';
        } else {
            throw new \Exception("类 $classname 不存在");
        }
        
        DEBUG && $_ENV['_include'][] = $classname . '类';
        
        return class_exists($classname, false);
    }
    
    /**
     * 初始化$_GET变量
     */
    public static function initGet()
    {
        if (!empty($_ENV['_config']['url_rewrite'])) {
            self::urlRewrite();
        } else {
            if (isset($_GET['u'])) {
                $u = $_GET['u'];
                unset($_GET['u']);
            } elseif (!empty($_SERVER['PATH_INFO'])) {
                $u = $_SERVER['PATH_INFO'];
            } else {
                $_GET = [];
                $u = $_SERVER['QUERY_STRING'];
            }
            
            //清除URL后缀
            $url_suffix = C('url_suffix');
            
            if ($url_suffix) {
                $suf_len = strlen($url_suffix);
                
                if (substr($u, -($suf_len)) == $url_suffix) {
                    $u = substr($u, 0, -$suf_len);
                }
            }
            
            $uarr = explode('-', $u);
            
            if (isset($uarr[0])) {
                $_GET['control'] = $uarr[0];
                array_shift($uarr);
            }
            
            if (isset($uarr[0])) {
                $_GET['action'] = $uarr[0];
                array_shift($uarr);
            }
            
            $num = count($uarr);
            
            for ($i = 0; $i < $num; $i += 2) {
                isset($uarr[$i + 1]) && $_GET[$uarr[$i]] = $uarr[$i + 1];
            }
        }
        
        $_GET['control'] = isset($_GET['control']) && preg_match('/^\w+$/', $_GET['control']) ? $_GET['control'] : 'index';
        $_GET['action']  = isset($_GET['action'])  && preg_match('/^\w+$/', $_GET['action'])  ? $_GET['action']  : 'index';
        
        //限制访问特殊控制器，直接转为404错误
        if (in_array($_GET['control'], ['error404'])) {
            $_GET['control'] = 'error404';
            $_GET['action'] = 'index';
        }
    }
    
    /**
     * 执行解析URL为$_GET的控制器
     */
    public static function urlRewrite()
    {
        $controlname = 'urlRewrite.php';
        $objfile = RUNTIME_CONTROL . $controlname;
        
        if (DEBUG || !is_file($objfile)) {
            $controlfile = self::getOriginalFile($controlname, CONTROL_PATH);
            
            if (!$controlfile) {
                $_GET['control'] = 'parseurl';
                throw new \Exception("访问的URL不正确， $controlname 文件不存在");
            }
        }
    }
    
    /**
     * 初始化控制器，并实例化
     */
    public static function initControl()
    {
        $control = $_GET['control'];
        $action = $_GET['action'];
        $controlname = $control . '.php';
        $objfile = RUNTIME_CONTROL . $controlname;
        
        //如果缓存文件不存在，则搜索原始文件，编译后写入缓存文件
        if (DEBUG || !is_file($objfile)) {
            $controlfile = self::getOriginalFile($controlname, CONTROL_PATH);
            
            if ($controlfile) {
                self::parseAll($controlfile, $objfile, "写入control编译文件 $controlname 失败");
            } elseif (DEBUG > 0) {
                throw new \Exception("访问的URL不正确，$controlname 文件不存在");
            } else {
                self::error404();
            }
        }
        
        include $objfile;
        
        $control = ucfirst(APP_NAME) . '\\Control\\' . $control;
        $obj = new $control();
        $obj->$action();
    }
    
    /**
     * 获取原始文件路径
     *
     * @param string $filename 文件名
     * @param string $path 绝对路径
     * @return string|false 获取成功返回路径，获取失败返回false
     */
    public static function getOriginalFile($filename, $path)
    {
        if (empty($_ENV['_config']['plugin_disable'])) {
            $plugins = self::getPlugins();
            
            if (isset($plugins['enable']) && is_array($plugins['enable'])) {
                $plugin_enable = array_keys($plugins['enable']);
                
                foreach ($plugin_enable as $p) {
                    //第一步 查找 plugin/xxx/APP_NAME/xxx.(php|htm)
                    if (is_file(PLUGIN_PATH . $p . '/' . APP_NAME . '/' . $filename)) {
                        return PLUGIN_PATH . $p . '/' . APP_NAME . '/' . $filename;
                    }
                    
                    //第二步 查找plugin/xxx/xxx.(php|htm)
                    if (is_file(PLUGIN_PATH . $p . '/' . $filename)) {
                        return PLUGIN_PATH . $p . '/' . $filename;
                    }
                }
            }
        }
        
        //第三步 查找（block|control|model|view）/xxx.(php|htm)
        if (is_file($path . $filename)) {
            return $path . $filename;
        }
        
        return false;
    }
    
    /**
     * 获取所有插件
     *
     * @param bool $force 强制重新获取
     * @return arry('not_install', 'disable', 'enable')
     */
    public static function getPlugins($force = 0)
    {
        static $plugins = [];
        
        if (!empty($plugins) && !$force) {
            return $plugins;
        }
        
        if (!is_dir(PLUGIN_PATH)) {
            return [];
        }
        
        $plugin_dirs = get_dirs(PLUGIN_PATH);
        $plugin_arr = is_file(CONFIG_PATH . 'plugin.php') ? (array)include(CONFIG_PATH . 'plugin.php') : [];
        
        foreach ($plugin_dirs as $dir) {
            $cfg = is_file(PLUGIN_PATH . $dir . '/conf.php') ? (array)include(PLUGIN_PATH . $dir . '/conf.php') : [];
            
            $cfg['rank'] = isset($cfg['rank']) ? $cfg['rank'] : 100;
            
            if (empty($plugin_arr[$dir])) {
                $plugins['not_install'][$dir] = $cfg;
            } elseif (empty($plugin_arr[$dir]['enable'])) {
                $plugins['disable'][$dir] = $cfg;
            } else {
                $plugins['enable'][$dir] = $cfg;
            }
        }
        
        //排序规则： rank升序、名称升序
        _array_multisort($plugins['enable'], 'rank');
        _array_multisort($plugins['disable'], 'rank');
        _array_multisort($plugins['not_install'], 'rank');
        
        return $plugins;
    }
    
    /**
     * 将原始程序代码解析并写入缓存文件中
     *
     * @param string $readfile 原始路径
     * @param string $writefile 缓存路径
     * @param string $errorstr 写入出错提示
     */
    public static function parseAll($readfile, $writefile, $errorstr)
    {
        $s = file_get_contents($readfile);
        $s = self::parseExtends($s);
        $s = preg_replace_callback('/\s*\/\/\s*hook\s+([\w\.]+)[\r\n]/', [__CLASS__, 'parseHook'], $s); //处理hook
        
        if (!FW($writefile, $s)) {
            throw new \Exception($errorstr);
        }
    }
    
    /**
     * 递归解析继承的控制器类
     *
     * @param string $s 文件内容
     * @return string
     */
    public static function parseExtends($s)
    {
        if (preg_match('/class\s+\w+\s+extends\s+(\w+)\s*/', $s, $m)) {
            if ($m[1] !== 'Control') {
                $controlname = $m[1] . '.php';
                $realfile = CONTROL_PATH . $controlname;
                
                if (is_file($realfile)) {
                    $objfile = RUNTIME_CONTROL . $controlname;
                    self::parseAll($realfile, $objfile, "写入继承的类的编译文件 $controlname 失败");
                    $s = str_replace_once($m[0], 'include RUNTIME_CONTROL . \'' . $controlname . "';" . $m[0], $s);
                } else {
                    throw new \Exception("你继承的类文件 $controlname 不存在");
                }
            }
        }
        
        return $s;
    }
    
    /**
     * 解析是否有hook
     *
     * @param array $matches 参数数组
     * @return string
     */
    public static function parseHook($matches)
    {
        $str = "\n";
        
        if (!is_dir(PLUGIN_PATH) || !empty($_ENV['_config']['plugin_disable'])) {
            return $str;
        }
        
        $plugins = self::getPlugins();
        
        if (empty($plugins['enable'])) {
            return $str;
        }
        
        $plugins_enable = array_keys($plugins['enable']);
        
        foreach ($plugins_enable as $p) {
            $file = PLUGIN_PATH . $p . '/' . $matches[1];
            
            if (!is_file($file)) {
                continue;
            }
            
            $s = file_get_contents($file);
            $str .= self::clearCode($s);
        }
        
        return $str;
    }
    
    /**
     * 清除头尾不需要的代码
     *
     * @param array $s 字符串
     * @return string
     */
    public static function clearCode($s)
    {
        $s = trim($s);
        
        if (substr($s, 0, 5) == '<?php') {
            $s = substr($s, 5);
        }
        
        $s = ltrim($s);
        
        if (substr($s, 0, 29) == 'defined(\'CORE_PATH || exit;') {
            $s = substr($s, 29);
        }
        
        if (substr($s, -2, 2) == '?>') {
            $s = substr($s, 0, -2);
        }
        
        return $s;
    }
    
    /**
     * 执行错误显示404
     */
    public static function error404()
    {
        log::write('404错误，访问的URL不存在', 'php_error404.php');
        
        $errorname = 'error404.php';
        $objfile = RUNTIME_CONTROL . $errorname;
        
        if (DEBUG || !is_file($objfile)) {
            $errorfile = self::getOriginalFile($errorname, CONTROL_PATH);
            
            if (!$errorfile) {
                throw new \Exception("控制器加载失败，$errorname 文件不存在");
            }
            
            self::parseAll($errorfile, $objfile, "写入control编译文件 $errorname 失败");
        }
        
        include $objfile;
        $obj = new error404();
        $obj->index();
        
        exit();
    }
}