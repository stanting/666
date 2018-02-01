<?php
namespace Core\Base;

use Core\Base\Core;
use Core\Base\View;
use Core\Base\Model;

class Control {

    public function __get($var)
    {
        if ($var == 'view') {
            return $this->view = new View();
        } elseif ($var == 'db') {
            $db = 'db_' . $_ENV['_config']['db']['type'];
            
            return $this->db = new $db($_ENV['_config']['db']);
        } else {
            return $this->$var = Model::model($var);
        }
    }
    
    public function assign($k, &$v)
    {
        $this->view->assign($k, $v);
    }
    
    public function assignValue($k, $v)
    {
        $this->view->assignValue($k, $v);
    }
    
    public function display($filename = null)
    {
        $this->view->display($filename);
    }
    
    public function message($status, $message, $jumpurl = '', $delay = 2)
    {
        if (R('ajax')) {
            echo json_encode([
                'status'    => $status,
                'message'   => $message,
                'jumpurl'   => $jumpurl,
                'delay'     => $delay
            ]);
        } else {
            if (empty($jumpurl)) {
                $jumpurl = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
            }
            
            include CORE_PATH . 'tpl/sys_message.php';
        }
        
        exit;
    }
    
    public function __call($method, $args)
    {
        if (DEBUG) {
            throw new \Exception('控制器没有找到：' . get_class($this) . '->' . $method . '(' . (empty($args) ? '' : var_export($args, 1)) . ')');
        } else {
            Core::error404();
        }
    }

}
