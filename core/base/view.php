<?php
namespace Core\Base;

use Core\Base\Core;

class View {
    private $vars = []; //模板变量集合
    private $head_arr = []; //模板头部代码数组
    
    public function __construct()
    {
        $_ENV['_theme'] = 'default'; //主题目录
        $_ENV['_view_diy'] = false;
    }
    
    public function assign($k, &$v)
    {
        $this->vars[$k] = &$v;
    }

    public function assisnValue($k, $v)
    {
        $this->vars[$k] = $v;
    }
    
    public function display($filename = null)
    {
        $_ENV['_tplname'] = is_null($filename) ? $_GET['control'] . '_' . $_GET['action'] . '.htm' : $filename;
        extract($this->vars, EXTR_REFS);
        include $this->getTplfile($_ENV['_tplname']);
    }
    
    private function getTplfile($filename)
    {
        $view_dir = APP_NAME . ($_ENV['_view_diy'] ? '_view_diy' : '_view') . '/';
        $php_file = RUNTIME_PATH . $view_dir . $_ENV['_theme'] . '/' . $filename . '.php';
        
        if (!is_file($php_file) || DEBUG) {
            $tpl_file = Core::getOriginalFile($filename, VIEW_PATH . $_ENV['_theme'] . '/');
            
            if (!$tpl_file) {
                throw new \Exception('模板文件 ' . $_ENV['_theme'] . '/' . $filename . ' 不存在');
            }
            
            if (FW($php_file, $this->tplParse($tpl_file)) === false) {
                throw new \Exception("写入模板编译文件 $filename 失败");
            }
        }
        
        return $php_file;
    }
    
    private function tplParse($tpl_file)
    {
        $reg_arr = '[a-zA-Z]\w*(?:\[\w+]|\[\'\w+\'\]|\[\$[a-zA-Z]\w*\])*';
        
        $s = file_get_contents($tpl_file);
        
        //第一步，包含inc模板
        $s = preg_replace_callback('/\{inc\:([\w\.]+)\}/', [__CLASS__, 'parseInc'], $s);
        
        //第二步，解析模板hook
        $s = preg_replace_callback('/\{hook\:([\w\.]+)\}/', ['\Core\Base\Core', 'parseHook'], $s);

        //第三步，解析php代码
        $s = preg_replace('/(?:\<\?.*?\?\>|\<\?.*)/s', '', $s); //清理php标记
        $s = preg_replace('/\{php\}(.*?)\{\/php\}/s', '<?php \\1 ?>', $s);
        
        //第四步，包含block
        $s = preg_replace_callback('/\{block\:([a-zA-Z_]\w*)\040?([^\n\}]*?)\}(.*?){\/block}/s', [$this, 'parseBlock'], $s);
        
        //第五步，解析loop
        while (preg_match('/\{loop\:\$' . $reg_arr . '(?:\040\$[a-zA-Z]\w*){1,2}\}.*?\{\/loop\}/s', $s)) {
            $s = preg_replace_callback('/\{loop\:(\$' . $reg_arr . '(?:\040\$[a-zA-Z]\w*){1,2})\}(.*?)\{\/loop\}/s', [$this, 'parseLoop'], $s);
        }

        //第六步，解析if
        while (preg_match('/\{if\:[^\n\}]+\}.*?\{\/if\}/s', $s)) {
            $s = preg_replace_callback('/\{if\:([^\n\}]+)\}(.*?)\{\/if\}/s', [__CLASS__, 'parseIf'], $s);
        }
        
        //第七步，解析变量
        $s = preg_replace('/\{\@([^\}]+)\}/', '<?php echo(\\1); ?>', $s);
        $s = preg_replace_callback('/\{(\$' . $reg_arr . ')\}/', [__CLASS__, 'parseVars'], $s);
        
        //压缩HTML代码
        $s = str_replace(["\r\n", "\n", "\t"], '', $s);
        
        //第八步，组合模板代码
        $head_str = empty($this->head_arr) ? '' : implode("\r\n", $this->head_arr);
        $s = "<?php defined('APP_NAME') || exit('Access Denied'); $head_str\r\n?>$s";
        $s = str_replace('?><?php ', '', $s);
        
        return $s;
    }
    
    private function parseInc($matches)
    {
        $filename = $matches[1];
        $tpl_file = Core::getOriginalFile($filename, VIEW_PATH . $_ENV['_theme'] . '/');
        
        if (!$tpl_file) {
            throw new \Exception('模板文件 ' . $_ENV['_theme'] . '/' . $filename . '不存在');
        }
        
        return file_get_contents($tpl_file);
    }
    
    private function parseBlock($matches)
    {
        var_dump($matches);
        $func   = $matches[1];
        $config = $matches[2];
        $s      = $matches[3];
        
        $lib_file = Core::getOriginalFile($func . '.php', BLOCK_PATH);
        
        if (!is_file($lib_file)) {
            return '';
        }
        
        //为了减少IO，把需要用到的函数代码放到模板解析代码头部
        $lib_str = file_get_contents($lib_file);
        $lib_str = preg_replace_callback('/\t*\/\/\s*hook\s+([\w\.]+)[\r\n]/', ['Core\Base\Core', 'parseHook'], $lib_str);
        
        if (!DEBUG) {
            $lib_str = _strip_whitespace($lib_str);
        }
        
        $lib_str = Core::clearCode($lib_str);
        $this->head_arr['block_' . $func] = $lib_str;
        
        $s = $this->repDouble($s);
        $config = $this->repDouble($config);
        
        //解析设置数组并生成执行函数
        $config_arr = [];
        preg_match_all('/([a-zA-Z_]\w*)="(.*?)" /', $config . ' ', $m);
        
        foreach ($m[2] as $k => $v) {
            if (isset($v)) {
                $config_arr[strtolower($m[1][$k])] = addslashes($v);
            }
        }
        
        unset($m);
        
        $func_str = $func . '(' . var_export($config_arr, 1) . ');';
        
        //定义转换后的首尾代码
        $before = $after = '';
        
        if (substr($func, 0, 7) == 'global_') {
            $this->head_arr[$func] = '$gdata = ' . $func_str;
        } else {
            $before .= '<?php $data = ' . $func_str . ' ?>';
            $after  .= '<?php unset($data); ?>';
        }
        
        //DIY模板时才能用到
        if ($_ENV['_view_diy']) {
            $this->block_id++;
            
            $before .= '<span block_diy="before" block_id="' . $this->block_id . '"></span>';
            $after  .= '<span block_diy="after" block_id="' . $this->block_id . '"></span>';
        }
        
        return $before . $after;
    }
    
    //严格要求格式
    private function parseLoop($matches)
    {
        $args = explode(' ', $this->repDouble($matches[1]));
        $s = $this->repDouble($matches[2]);
        
        $arr = $this->repVars($args[0]);
        
        $v = empty($args[1]) ? '$v' : $args[1];
        $k = empty($args[2]) ? '' : $args[2] . '=>';
        
        return "<?php if(isset($arr) && is_array($arr)) { foreach($arr as $k&$v) { ?>$s<?php }} ?>";
    }
    
    private function parseIf($matches)
    {
        $expr = $this->repDouble($matches[1]);
        $expr = $this->repVars($expr);
        
        $s = preg_replace_callback('/\{elseif\:([^\n\}]+)\}/', [$this, 'repElseif'], $this->repDouble($matches[2]));
        $s = str_replace('{else}', '<?php }else{ ?>', $s);
        
        return "<?php if ($expr) { ?>$s<?php } ?>";
    }
    
    private function repElseif($matches)
    {
        $expr = $this->repDouble($matches[1]);
        $expr = $this->repVars($expr);
        
        return "<?php } elseif ($expr) { ?>";
    }
    
    private function parseVars($matches)
    {
        var_dump($matches);
        $vars = $this->repDouble($matches[1]);
        $vars = $this->repVars($vars);
        
        return "<?php echo(isset($vars) ? $vars : ''); ?>";
    }
    
    //替换 " 号
    private function repDouble($s)
    {
        return str_replace('\"', '"', $s);
    }
    
    //数组格式化
    private function repVars($s)
    {
        $s = preg_replace('/\[(\w+)\]/', "['\\1']", $s);
        $s = preg_replace('/\[\"(\w+)\"\]/', "['\\1']", $s);
        $s = preg_replace('/\[\'(\d+)\'\]/', '[\\1]', $s);
        
        return $s;
    }
}