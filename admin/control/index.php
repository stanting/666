<?php

namespace Admin\Control;

class index extends Admin
{
    public function index()
    {
        unset($this->_navs[1]['my-newtab']);
        
        foreach ($this->_navs[1] as $k => $v) {
            $this->_navs[2][$v['p']][$k] = $v;
        }
        
        unset($this->_navs[1]);
        
        $this->display();
        
        exit;
    }
    
    //后台登录
    public function login()
    {
        if (empty($_POST)) {
            $this->display('login.htm');
        } elseif (form_submit()) {
            $user = &$this->user;
            $username = R('username', 'P');
            $password = R('password', 'P');
            
            if ($message = $user->checkUsername($username)) {
                exit('{"name":"username", "message":"啊哦，'.$message.'"}');
            } elseif ($message = $user->checkPassword($password)) {
                exit('{"name":"password", "message":"啊哦，'.$message.'"}');
            }
            
            //防止IP暴力破解
            $ip = &$_ENV['_ip'];
            
            if (0) {
                
            }
        }
    }
}