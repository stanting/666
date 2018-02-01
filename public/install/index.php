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
    $ret = true;
    
    foreach ($sqls as $sql) {
        $sql = str_replace("\n", '', trim($sql));
        
        if (!$link->query($sql)) {
            $ret = false;
        }
    }
    
    jsShow('创建基本数据...' . ($ret ? '<i>成功</i>' : '<u>失败</u>'));
    
    if (!$ret) {
        exit;
    }
}