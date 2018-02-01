<?php

version_compare(PHP_VERSION, '7.0.0', '>') || die('require PHP > 7.0.0 !');

define('INSTALL', dirname(__FILE__));
define('CORE', dirname(INSTALL));
define('ROOT', dirname(CORE));
define('APP_NAME', basename(CORE));

//error_reporting(0);
date_default_timezone_set('Asia/ShangHai');
header('Content-Type: text/html; charset=UTF-8');

//保护锁
if (is_file(CORE . '/config/config.inc.php')) {
    header("HTTP/1.0 404 Not Found");
    header("Status: 404 Not Found");
    
    include INSTALL . '/tpl/lock.php';
    exit;
}

include ROOT . '/core/base/base.php';
include INSTALL . '/function.php';

$do = isset($_GET['do']) && in_array($_GET['do'], ['license', 'check_env', 'check_db', 'complete']) ? $_GET['do'] : 'license';

if ($do == 'license') {
    include INSTALL . '/tpl/header.php';
    include INSTALL . '/tpl/license.php';
    include INSTALL . '/tpl/footer.php';
} elseif ($do == 'check_env') {
    include INSTALL . '/tpl/header.php';
    include INSTALL . '/tpl/check_db.php';
    include INSTALL . '/tpl/footer.php';
} elseif ($do == 'complete') {
    include INSTALL . '/tpl/header.php';
    echo '<div id="cont" class="content"></div><div class="button"></div>';
    include INSTALL . '/tpl/footer.php';
    
    if (!isset($_POST['dbhost'])) {
        jsBack('<u>非法访问！</u>');
    }
    
    $dbhost = isset($_POST['dbhost']) ? trim($_POST['dbhost']) : '';
    $dbuser = isset($_POST['dbuser']) ? trim($_POST['dbuser']) : '';
    $dbpass = isset($_POST['dbpass']) ? trim($_POST['dbpass']) : '';
    $dbname = isset($_POST['dbname']) ? trim($_POST['dbname']) : '';
    $tablepre = isset($_POST['dbpre']) ? trim($_POST['dbpre']) : '';
    $adm_user = isset($_POST['adm_user']) ? trim($_POST['adm_user']) : '';
    $adm_pass = isset($_POST['adm_pass']) ? trim($_POST['adm_pass']) : '';
    $charset  = 'UTF8';
    
    if (empty($dbhost)) {
        jsBack('<u>数据库主机不能为空！</u>');
    } elseif (empty($dbuser)) {
        jsBack('<u>数据库用户名不能为空！</u>');
    } elseif (!preg_match('/^\w+$/', $dbname)) {
        jsBack('<u>数据库名不正确！</u>');
    } elseif (empty($tablepre)) {
        jsBack('<u>数据库表前缀不能为空！</u>');
    } elseif (!preg_match('/^\w+$/', $tablepre)) {
        jsBack('<u>数据库表前缀不正确！</u>');
    } elseif (empty($adm_user)) {
        jsBack('<u>创始人用户名不能为空！</u>');
    } elseif (strlen($adm_pass) < 8) {
        jsBack('<u>密码不能小于8位数！</u>');
    }
    
    //连接数据库
    if (!extension_loaded('PDO')) {
        jsBack('未开启PDO模块！');
    }
    
    try {
        $link = new PDO("mysql:host={$dbhost};dbname={$dbname}", $dbuser, $dbpass);
    } catch (PDOException $e) {
        echo $e->getCode();
    }
    
    //创建数据表
    $file = INSTALL . '/data/mysql.sql';
    
    if (!is_file($file)) {
        jsBack('mysql.sql 文件 <u>丢失</u>');
    }
    
    $s = file_get_contents($file);
    $sqls = splitSql($s, $tablepre);
    
    foreach ($sqls as $sql) {
        $sql = str_replace("\n", '', trim($sql));
        $ret = $link->query($sql);
        
        if (substr($sql, 0, 6) == 'CREATE') {
            $name = preg_replace("/CREATE TABLE ([`a-z0-9_]+) .*/is", "\\1", $sql);
            
            if ($ret) {
                jsShow('创建数据表 ' . $name . ' ... <i>成功</i>');
            } else {
                jsBack('创建数据表 ' . $name . ' ... <u>失败</u>');
            }
        }
        
        if (!$ret) {
            jsBack('<u>创建数据表失败</u>');
        }
    }
    
    //创建基本数据
    $file = INSTALL . '/data/mysql_data.sql';
    
    if (!is_file($file)) {
        jsBack('mysql_data.sql 文件 <u>丢失</u>');
    }
    
    $s = file_get_contents($file);
    $sqls = splitSql($s, $tablepre);
    $ret = true;
    
    foreach ($sqls as $sql) {
        $sql = str_replace("\n", '', trim($sql));
        
        $link->query($sql) || $ret = false;
    }
    
    jsShow('创建基本数据...' . ($ret ? '<i>成功</i>' : '<u>失败</u>'));
    
    if (!$ret) {
        exit;
    }
    
    //创建创始人
    $salt = random(16, 3, '0123456789abcdefghijklmnopqrstuvwxyz~!@#$%^&*()_+<>,.');
    $password = md5(md5($adm_pass) . $salt);
    $ip = ip2long(ip());
    $time = time();
    $ret = $link->exec("INSERT INTO `{$tablepre}user` (`uid`, `username`, `password`, `salt`, `groupid`, `email`, `homepage`, `intro`, `regip`, `regdate`, `loginip`, `logindate`, `lastip`, `lastdate`, `contents`, `comments`, `logins`) VALUES (1, '{$adm_user}', '{$password}', '{$salt}', 1, '', '', '', {$ip}, {$time}, 0, 0, 0, 0, 0, 0, 0);");
    jsShow('创建创始人 ... ' . ($ret ? '<i>成功</i>' : '<u>失败</u>'));
    
    if (!$ret) {
        exit;
    }
    
    //初始网站设置
    $webdomain = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
    $webdir = getWebDir();
    $weburl = 'http://' . $webdomain . $webdir;
    
    $cfg = [
        'webname' => 'TEST',
        'webdomain' => $webdomain,
        'webdir' => $webdir,
        'webmail' => 'admin@admin.cn',
        'tongji' => '<script type="text/javascript">var _bdhmProtocol = (("https:" == document.location.protocol) ? " https://" : " http://");document.write(unescape("%3Cscript src=\'" + _bdhmProtocol + "hm.baidu.com/h.js%3F948\' type=\'text/javascript\'%3E%3C/script%3E"));</script>',
        'beian' => '京ICP备20121225号',
        'seo_title' => '让建站变的更简单！',
        'seo_keywords' => '通王CMS,TWCMS',
        'seo_description' => '通王CMS，让建站变的更简单！',
        
        'link_show' => '{cate_alias}/{id}.html',
        'link_show_type' => 2,
        'link_show_end' => '.html',
        'link_cate_page_pre' => '/page_',
        'link_cate_page_end' => '.html',
        'link_cate_end' => '/',
        'link_tag_pre' => 'tag/',
        'link_tag_end' => '.html',
        'link_comment_pre' => 'comment/',
        'link_comment_end' => '.html',
        'link_index_end' => '.html',
        
        'up_img_ext' => 'jpg,jpeg,gif,png',
        'up_img_max_size' => '3074',
        'up_file_ext' => 'zip,gz,rar,iso,xsl,doc,ppt,wps',
        'up_file_max_size' => '10240',
        'thumb_article_w' => 163,
        'thumb_article_h' => 124,
        'thumb_product_w' => 150,
        'thumb_product_h' => 150,
        'thumb_photo_w' => 150,
        'thumb_photo_h' => 150,
        'thumb_type' => 2,
        'thumb_quality' => 90,
        'watermark_pos' => 9,
        'watermark_pct' => 90,
    ];
    
    $settings = addslashes(json_encode($cfg));
    $ret = $link->exec("INSERT INTO {$tablepre}kv SET k='cfg',v='{$settings}',expiry='0'");
    jsShow('初始网站设置 ... ' . ($ret ? '<i>成功</i>' : '<u>失败</u>'));
    
    if (!$ret) {
        exit;
    }
    
    //清空缓存
    $runtime = CORE . '/runtime/';
    $file = $runtime . '_runtime.php';
    
    if (is_file($file)) {
        $ret = unlink($runtime . '_runtime.php');
        jsShow('清除 runtime/_runtime.php ... <i>完成</i>');
    }
    
    $tmpdir = ['_control', '_model', '_view'];
    
    foreach ($tmpdir as $dir) {
        $ret = _rmdir($runtime . $dir);
        jsShow('清除 runtime/' . $dir . ' ... <i>完成</i>');
    }
    
    foreach ($tmpdir as $dir) {
        if ($dir == '_model') {
            continue;
        }
        
        $ret = _rmdir($runtime . $dir);
        jsShow('清除runtime/' . $dir . ' ... <i>完成</i>');
    }
    
    //初始插件配置
    $file = INSTALL . '/plugin.sample.php';
    
    if (!is_file($file)) {
        jsBack('plugin.sample.php 文件 <u>丢失</u>');
    }
    
    $ret = file_put_contents(ROOT . '/test/config/plugin.php', file_get_contents($file));
    jsShow('设置config/plugin.php ... ' . ($ret ? '<i>成功</i>' : '<u>失败</u>'));
    
    if (!$ret) {
        exit;
    }
    
    //生成配置文件
    $file = INSTALL . '/config.sample.php';
    
    if (!is_file($file)) {
        jsBack('config.sample.php 文件 <u>丢失</u>');
    }
    
    $auth_key = random(32, 3);
    $cookie_pre = 'test' . random(5, 3) . '-';
    
    $s = file_get_contents($file);
    $s = preg_replace("/'auth_key' => '\w*',/", "'auth_key' => '" . addslashes($auth_key) . "',", $s);
    $s = preg_replace("/'cookie_pre' => '\w*',/", "'cookie_pre' => '" . addslashes($cookie_pre) . "',", $s);
    $s = preg_replace("/'host' => '\w*',/", "'host' => '" . addslashes($dbhost) . "',", $s);
    $s = preg_replace("/'user' => '\w*',/", "'user' => '" . addslashes($dbuser) . "',", $s);
    $s = preg_replace("/'password' => '\w*',/", "'password' => '" . addslashes($dbpass) . "',", $s);
    $s = preg_replace("/'name' => '\w*',/", "'name' => '" . addslashes($dbname) . "',", $s);
    $s = preg_replace("/'tablepre' => '\w*',/", "'tablepre' => '" . addslashes($tablepre) . "',", $s);
    $s = preg_replace("/'pre' => '\w*',/", "'pre' => '" . addslashes($tablepre) . "',", $s);
    
    $ret = file_put_contents(ROOT . '/test/config/config.php', $s);
    jsShow('设置config/config.php ... ' . ($ret ? '<i>成功</i>' : '<u>失败</u>'));
    
    if (!$ret) {
        exit;
    }
    
    //安装结束提示
    $s = '<div class="end"><h3>恭喜！您的网站已安装完成啦！</h3><p>';
    $s .= '首页地址：<a href="'.$weburl.'" target="_blank">'.$weburl.'</a><br>';
    $s .= '后台地址：<a href="'.$weburl.'admin/" target="_blank">'.$weburl.'admin/</a><br>';
    $s .= '用户名：'.$adm_user.'　<br>密　码：'.$adm_pass.'<br>';
    $s .= '亲，请牢记以上信息，您可以登陆后台修改密码及网站设置。^_^</p></div>';
    jsShow($s);
}