<?php

define('DEBUG', 2); //调试模式。分三种：0 关闭调试；1 开启调试；2 开发调试    注意：开启调试模式会暴露绝对路径和表前缀。
define('APP_NAME', 'test'); //APP名称
define('FRONT_NAME', 'test');
define('ROOT_PATH', dirname(__DIR__) . '/'); //CORE路径
define('APP_PATH', ROOT_PATH . APP_NAME . '/'); //APP目录
define('CORE_PATH', ROOT_PATH . 'core/'); //框架目录

require CORE_PATH . 'core.php';
