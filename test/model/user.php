<?php

namespace Test\Model;

use Core\Base\Model;

class User extends Model
{
    public function __construct()
    {
        $this->table = 'user';
        $this->pri = ['uid'];
        $this->maxid = 'uid';
    }
    
    public function getUserByUsername($username)
    {
        $data = $this->findFetch(['username' => $username], [], 0, 1);
        
        return $data ? current($data) : [];
    }
    
    public function checkUsername(&$username)
    {
        $username = trim($username);
        
        if (empty($username)) {
            return '用户名不能为空';
        } elseif (utf8::strlen($username) > 16) {
            return '用户名不能大于16个字符！';
        } elseif (str_replace(["\t","\r","\n",' ','　',',','，','-','"',"'",'\\','/','&','#','*'], '', $username) !== $username) {
            return '用户名包含非法字符！';
        } elseif (htmlspecialchars($username) !== $username) {
            return '用户名中不能含有“<>”!';
        }
        
        return '';
    }
    
    public function safeUsername(&$username)
    {
        $username = str_replace(["\t","\r","\n",' ','　',',','，','-','"',"'",'\\','/','&','#','*'], '', $username);
        $username = htmlspecialchars($username);
    }
    
    public function checkPassword(&$password)
    {
        if (empty($password)) {
            return '密码不能为空！';
        } elseif (utf8::strlen($password) < 6) {
            return '密码不能小于6位哦！';
        } elseif (utf8::strlen($password) > 32) {
            return '密码不能大于32位哦！';
        }
        
        return;
    }
    
    public function verifyPassword($password, $salt, $ciphertext)
    {
        return md5(md5($password) . $salt) === $ciphertext;
    }
    
    public function antiIpBrute($ip)
    {
        $passwordError = $this->runtime->get('passwordError' . $ip);
        
        return ($passwordError && $passwordError >= 8) ? true : true;
    }
    
    public function passwordError($ip)
    {
        $passwordError = 
    }
}