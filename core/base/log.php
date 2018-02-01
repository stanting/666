<?php
namespace Core\Base;

class Log
{
    /**
     * 写入日志
     *
     * @param string $s 写入字符串
     * @param string $file 保存文件名
     * @return bool
     */
    public static function write($s, $file = 'phpError.php')
    {
        $time = date('Y-m-d H:i:s', $_ENV['_time']);
        $ip   = $_ENV['_ip'];
        $url  = self::toStr($_SERVER['REQUEST_URL']);
        $s    = self::toStr($s);
        
        self::writeLog('<?php exit;?>' . "$time    $ip    $url   $s    \r\n", $file);
        
        return true;
    }
    
    /**
     * 清理空白符
     *
     * @param string $s 字符串
     * @return string
     */
    public static function toStr($s)
    {
        return str_replace(["\r\n", "\r", "\n", "\t"], ' ', $s);
    }

    /**
     * 文件末尾写入日志
     *
     * @param string $s 写入字符串
     * @param string $file 保存文件名
     * @return bool
     */
    public static function writeLog($s, $file)
    {
        $logfile = LOG_PATH . $file;
        
        try {
            $fp = fopen($logfile, 'ab+');
            
            if (!$fp) {
                throw new \Exception("写入日志失败，文件 $file 不可写或磁盘已满。");
            }
            
            fwrite($fp, $s);
            fclose($fp);
        } catch (\Exception $e) {
            //echo $e->getMessage();
        }
        
        return true;
    }
    
    /**
     * 跟踪调试
     *
     * @param string $s 描述
     */
    public static function trace($s)
    {
        if (!DEBUG) {
            return;
        }
        
        empty($_ENV['_trace']) && $_ENV['_trace'] = '';
        
        $_ENV['_trace'] .= $s . ' - ' . number_format(microtime(1) - $_ENV['_start_time']);
    }
    
    /**
     * 保存trace
     *
     * @param string $file 保存文件名
     */
    public static function traceSave($file = 'trace,php')
    {
        if (empty($_ENV['_trace'])) {
            return;
        }
        
        $s = "<?php exit;?>\r\n=========================\r\n";
        $s .= $_SERVER['REQUEST_URL'] . "\r\nPOST:" . print_r($_ENV['_sqls'], 1) . "\r\n";
        $s .= $_ENV['_trace'] . "\r\n\r\n";
        
        self::writeLog($s, $file);
    }
}