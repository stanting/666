<?php

//递归检测目录、文件是否可写
function dirWrite($dir, $clear = false)
{
    static $ret = [];
    
    if ($clear) {
        $ret = [
            'yes' => [],
            'no'  => []
        ];
    }
    
    if (!is_dir($dir) || noWritable($dir) || !$dh = opendir($dir)) {
        $ret['no'][] = [$dir, substr(sprintf('%o', fileperms($dir)), -4)];
    } else {
        $ret['yes'][] = [$dir, substr(sprintf('%o', fileperms($dir)), -4)];
        
        while (($file = readdir($dh)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $fileson = $dir . '/' . $file;
                
                if (is_dir($fileson)) {
                    dirWrite($fileson); //递归检测
                } elseif (is_file($fileson)) {
                    if (noWritable($fileson)) {
                        $ret['no'][] = [$fileson, substr(sprintf('%o', fileperms($fileson)), -4)];
                    } else {
                        $ret['yes'][] = [$fileson, substr(sprintf('%o', fileperms($fileson)), -4)];
                    }
                }
            }
        }
        
        closedir($dh);
    }
    
    return $ret;
}

//测试不可写
function noWritable($dir)
{
    if (isWritable($dir)) {
        return false;
    } else {
        chmod($file, 0777);
        
        return !isWritable($dir);
    }
}

//获取所在目录
function getWebDir()
{
    $str = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
    
    if ($str == '\\') {
        return '/';
    }
    
    if (strlen($str) > 1) {
        return $str . '/';
    } else {
        return '/';
    }
}

//分割SQL语句
function splitSql($sql, $tablepre)
{
    $sql = str_replace('pre_', $tablepre, $sql);
    $sql = str_replace("\r", '', $sql);
    
    $ret = [];
    $num = 0;
    
    $queriesarray = explode(";\n", trim($sql));
    unset($sql);
    
    foreach ($queriesarray as $query) {
        $ret[$num] = isset($ret[$num]) ? $ret[$num] : '';
        $queries = explode("\n", trim($query));

        foreach ($queries as $query) {
            $ret[$num] .= isset($query[0]) && $query[0] == "#" ? '' : trim(preg_replace('/\#.*/', '', $query));
        }
        
        $num++;
    }

    return $ret;
}

//JS输出
function jsShow($s)
{
    echo '<script type="text/javascript">jsShow(\'' . addslashes($s) . '\');</script>' . "\r\n";
    flush();
    ob_flush();
}

//JS返回
function jsBack($s)
{
    jsShow($s . ' <a href="javascript:history.back();">返回</a>');
    exit;
}