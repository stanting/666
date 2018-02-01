<?php
$_ENV['_config'] = [
    'plugin_disable' => 0, //禁止插件
    'time_zone' => 'Asia/ShangHai', //时区
    'gzip' => 1, //开启GZIP压缩
    'auth_key' => 'UvM6qNwOhRZekOasJHd6fXuYfrDVyyI1', //认证KEY
    
    'url_rewrite' => 0, //开启伪静态
    
    'cookie_pre' => 'cook_',
    'cookie_path' => '/',
    'cookie_domain' => '',
    
    //type为默认数据库类型，可以支持多种数据库
    'db' => [
        'type' => 'mysql',
        //主数据库
        'master' => [
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'admin',
            'dbname' => 'git',
            'charset' => 'utf8',
            'tablepre' => 'tw_',
            'engine' => 'MyISAM'
        ]
    ],
    
    //缓存配置
    'cache' => [
        'enable' => 0,
        'l2_cache' => 1,
        'type' => 'memcache',
        'pre' => 't_',
        'memcache' => [
            'multi' => 1,
            'host' => '127.0.0.1',
            'port' => '11211'
        ]
    ],
    
    //前后台静态资源
    'front_static' => 'static/',
    'backend_static' => '../static/',
    
    'url_sufix' => '.html',
    'version' => '1.0.0',
    'release' => '20180121'
];