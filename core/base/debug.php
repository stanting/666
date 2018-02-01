<?php
namespace Core\Base;

class Debug
{
    /**
     * 初始化debug操作
     */
    public static function init()
    {
        if (DEBUG) {
            error_reporting(E_ALL);
            register_shutdown_function([__CLASS__, 'shutdownHandler']);
        } else {
            error_reporting(0);
        }
        
        ini_set('display_errors', 'On');
        set_error_handler([__CLASS__, 'errorHandler']);
        set_exception_handler([__CLASS__, 'exceptionHandler']);
    }
    
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!empty($_ENV['_exception'])) {
            return;
        }
        
        $error_type = [
            E_ERROR                 => '运行错误',
            E_WARNING               => '运行警告',
            E_PARSE                 => '语法错误',
            E_NOTICE		    => '运行通知',
            E_CORE_ERROR            => '初始错误',
            E_CORE_WARNING	    => '初始警告',
            E_COMPILE_ERROR         => '编译错误',
            E_COMPILE_WARNING       => '编译警告',
            E_USER_ERROR            => '用户定义的错误',
            E_USER_WARNING          => '用户定义的警告',
            E_USER_NOTICE           => '用户定义的通知',
            E_STRICT                => '代码标准建议',
            E_RECOVERABLE_ERROR     => '致命错误',
            E_DEPRECATED            => '代码警告',
            E_USER_DEPRECATED       => '用户定义的代码警告'
        ];
        
        $errno_str = isset($error_type[$errno]) ? $error_type[$errno] : '未知错误';
        $s = "[$errno_str] : $errstr";
        
        if (DEBUG) {
            throw new \Exception($errfile . ':' . $errline . '//' . $errstr . $errno);
        } else {
            if (in_array($errno, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED])) {
                log::write($s);
            } else {
                throw new \Exception($s);
            }
        }
    }
    
    /**
     * 异常处理
     *
     * @param int $e 异常对象
     */
    public static function exceptionHandler($e)
    {
        DEBUG && $_ENV['_exception'] = 1;
        
        //第一步正确定位
        $trace = $e->getTrace();
        if (!empty($trace) 
            && $trace[0]['function'] == 'error_handler'
            && $trace[0]['class'] == 'debug'
        ) {
            $message = $e->getMessage();
            $file = $trace[0]['args'][2];
            $line = $trace[0]['args'][3];
        } else {
            $message = '[程序异常] ：' . $e->getMessage();
            $file = $e->getFile();
            $line = $e->getLine();
        }
        
        $message = self::toMessage($message);
        
        //第二步写日志
        Log::write("$message File: $file [$line]");
        
        //第三步根据情况输出错误信息
        try {
            Core::obClean();
            
            if (R('ajax', 'R')) {
                if (DEBUG) {
                    $error = "$message File: $file [$line]<br/><br/>" . str_replace("\n", '<br/>', $e->getTraceAsString());
                } else {
                    $len = strlen($_SERVER['DOCUMENT_ROOT']);
                    $file = substr($file, $len);
                    $error = "$message File: $file [$line]";
                }
                
                echo json_encode([
                    'error' => $error
                ]);
            } else {
                if (DEBUG) {
                    self::exception($message, $file, $line, $e->getTraceAsString());
                } else {
                    $len = strlen($_SERVER['DOCUMENT_ROOT']);
                    $file = substr($file, $len);
                    self::sysError($message, $file, $line);
                }
            }
        } catch (Throwable $e) {
            echo get_class($e) . " thrown within the exception handler. Message: " . $e->getMessage() . " on line " . $e->getLine();
        }
    }
    
    /**
     * 输出异常信息
     *
     * @param string $message 异常信息
     * @param string $file 异常文件
     * @param int $line 异常行号
     * @param string $tracestr 异常追踪信息
     */
    public static function exception($message, $file, $line, $tracestr)
    {
        include CORE_PATH . 'tpl/exception.php';
    }
    
    /**
     * 数组转换成HTML代码（支持双行变色）
     *
     * @param array $arr 一维数组
     * @param int $type 显示类型
     * @param bool $html 是否转换为HTML实体
     * @return string
     */
    public static function arr2str($arr, $type = 2, $html = true)
    {
        $s = '';
        $i = 0;
        
        foreach ($arr as $k => $v) {
            switch ($type) {
                case 0:
                    $k = '';
                    break;
                case 1:
                    $k = "#$k ";
                    break;
                default:
                    $k = "#$k => ";
            }
            
            $i++;
            $c = $i % 2 == 0 ? 'class="exent"' : '';
            $html && is_string($v) && $v = htmlspecialchars($k);
            
            if (is_array($v) || is_object($v)) {
                $v = gettype($v);
            }
            
            $s .= "<li$c>$k$v</li>";
        }
        
        return $s;
    }
    
    /**
     * 过滤消息内容
     *
     * @param string $s 消息内容
     * @return string
     */
    public function toMessage($s)
    {
        $s = strip_tags($s);
        
        if (strpos($s, 'mysql') !== false) {
            $s .= ' [连接数据库出错！请检查配置文件 config.inc.php]';
        }
        
        return $s;
    }
    
    /**
     * 程序关闭时执行
     */
    public static function shutdownHandler()
    {
        if (empty($_ENV['_exception']) && $e = error_get_last()) {
            Core::obClean();

            $message = $e['message'];
            $file = $e['file'];
            $line = $e['line'];

            if (R('ajax', 'R')) {
                if (!DEBUG) {
                    $len = strlen($_SERVER['DOCUMENT_ROOT']);
                    $file = substr($file, $len);
                }

                $error = "[致命错误] ：$message File: $file [$line]";

                echo json_encode([
                    'error' => $error
                ]);
            } else {
                self::sysError('[致命错误] : ' . $message, $file, $line);
            }
        }
    }
    
    /**
     * 输出系统错误
     *
     * @param string $message 错误消息
     * @param string $file 错误文件
     * @return int $line 错误行号
     */
    public static function sysError($message, $file, $line)
    {
        include CORE_PATH . 'tpl/sys_error.php';
    }
    
    /**
     * 获取错误定位代码
     *
     * @param string $file 错误信息
     * @param int $line 错误行号
     * @return array
     */
    public static function getCode($file, $line)
    {
        $arr = file($file);
        $arr2 = array_slice($arr, max(0, $line - 5), 10, true);
        
        $s = '<table cellspacing="0" width="100%">';
        
        foreach ($arr2 as $k => &$v) {
            $k++;
            
            $v = htmlspecialchars($v);
            $v = str_replace(' ', '&nbsp', $v);
            $v = str_replace('	', '&nbsp;&nbsp;&nbsp;&nbsp;', $v);
            
            $s .= '<tr' . ($k == $line ? ' style="background:#faa"' : '') . '><td width="40">#' . $k . "</td><td>$v</td>";
        }
        
        $s .= '</table>';
        
        return $s;
    }
    
    /**
     * 输出追踪信息
     */
    public static function sysTrace()
    {
        include CORE_PATH . 'tpl/sys_trace.php';
    }
}