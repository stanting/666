<?php

namespace Test\Control;

use Core\Base\Control;

class index extends Control
{
    public $_cfg = [];
    public $_var = [];
    
    public function index()
    {
        $this->_cfg = $this->runtime->xget();
        $this->_cfg['titles'] = $this->_cfg['webname'] . (empty($this->_cfg['seo_title']) ? '' : '-' . $this->_cfg['seo_title']);
        $this->_var['topcid'] = 0;
        
        $this->assign('cfg', $this->_cfg);
        $this->assign('var', $this->_var);
        
        $GLOBALS['run'] = &$this;
        
        $_ENV['_theme'] = &$this->_cfg['theme'];
        
        $this->display('index.htm');
    }

}