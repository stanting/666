<?php

define('DEBUG', 2);
define('APP_NAME', 'admin');
define('FRONT_NAME', 'test');
define('ROOT_PATH', dirname(__DIR__) . '/');
define('APP_PATH', ROOT_PATH . FRONT_NAME . '/');
define('A_PATH', ROOT_PATH . 'admin/');
define('CORE_PATH', ROOT_PATH . 'core/');

//define('RUNTIME_MODEL', CORE_PATH . '/runtime/' . )
define('CONTROL_PATH', A_PATH . 'control/');
define('VIEW_PATH', A_PATH . 'view/');

require CORE_PATH . 'core.php';
