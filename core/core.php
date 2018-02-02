<?php
namespace Core;

use Core\Base\Core;
use Core\Base\Debug;
//框架入口文件
defined('CORE_PATH') || die('Error Accessing');

//version_compare(PHP_VERSION, '7.0.0', '>') || die('require PHP > 7.0.0 !');

//记录开始运行时间
$_ENV['_start_time'] = microtime(1);

//记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
if (MEMORY_LIMIT_ON) {
    $_ENV['_start_memory'] = memory_get_usage();
}

define('CORE_VERSION', '1.0.0'); //框架版本
defined('DEBUG') || define('DEBUG', 2); //调试模式
defined('CONFIG_PATH') || define('CONFIG_PATH', APP_PATH . 'config/'); //配置目录
defined('CONTROL_PATH') || define('CONTROL_PATH', APP_PATH . 'control/'); //控制器目录
defined('MODEL_PATH') || define('MODEL_PATH', APP_PATH . 'model/'); //模型目录
defined('VIEW_PATH') || define('VIEW_PATH', APP_PATH . 'view/'); //视图目录
defined('BLOCK_PATH') || define('BLOCK_PATH', APP_PATH . 'block/'); //模块目录
defined('LOG_PATH') || define('LOG_PATH', APP_PATH . 'log/'); //日志目录
defined('PLUGIN_PATH') || define('PLUGIN_PATH', APP_PATH . 'plugin/'); //插件目录
defined('RUNTIME_PATH') || define('RUNTIME_PATH', APP_PATH . 'runtime/'); //运行缓存目录
defined('RUNTIME_MODEL') || define('RUNTIME_MODEL', RUNTIME_PATH . APP_NAME . '_model/'); //模型缓存目录
defined('RUNTIME_CONTROL') || define('RUNTIME_CONTROL', RUNTIME_PATH . APP_NAME . '_control/');

include CONFIG_PATH . 'config.inc.php';

if (DEBUG) {
    include CORE_PATH . 'base/base.php';
    include CORE_PATH . 'base/debug.php';
    include CORE_PATH . 'base/log.php';
    include CORE_PATH . 'base/core.php';
    include CORE_PATH . 'base/model.php';
    include CORE_PATH . 'base/view.php';
    include CORE_PATH . 'base/control.php';
    include CORE_PATH . 'db/idb.php';
    include CORE_PATH . 'db/mysql.php';
    include CORE_PATH . 'cache/icache.php';
    include CORE_PATH . 'cache/memcache.php';
} else {
    $runfile = RUNTIME_PATH . '_runtime.php';
    
    if (!is_file($runfile)) {
        $s  = trim(php_strip_whitespace(CORE_PATH . 'base/base.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'base/debug.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'base/log.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'base/core.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'base/model.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'base/view.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'base/control.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'db/idb.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'db/mysql.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'cache/icache.php'), "<?ph>\r\n");
        $s .= trim(php_strip_whitespace(CORE_PATH . 'cache/memcache.php'), "<?ph>\r\n");
        $s  = str_replace('defined(\'CORE_PATH\') || exit;', '', $s);
        
        file_put_contents($runfile, '<?php ' . $s);
        unset($s);
    }
    
    include $runfile;
}

Core::start();

if (DEBUG > 1 && !R('ajax', 'R')) {
    Debug::sysTrace();
}
