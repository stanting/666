<?php

namespace Test\Model;

use Core\Base\Model;

class user extends Model
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
        } elseif (\utf8::strlen($username) > 16) {
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
        } elseif (\utf8::strlen($password) < 6) {
            return '密码不能小于6位哦！';
        } elseif (\utf8::strlen($password) > 32) {
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

        return ($passwordError && $passwordError >= 8) ? true : false;
    }
    
    public function passwordError($ip)
    {
        $passwordError = (int) $this->runtime->get('passwordError' . $ip);
        $passwordError++;
        $this->runtime->set('passwordError' . $ip, $passwordError, 450);
    }
    
    public function format(&$user)
    {
        if (!$user) {
            return;
        }
        
        $user['regdate'] = empty($user['regdate']) ? '0000-00-00 00:00' : date('Y-m-d H:i', $user['regdate']);
        $user['regip'] = long2ip($user['regip']);
        $user['logindate'] = empty($user['logindate']) ? '0000-00-00 00:00' : date('Y-m-d H:i', $user['logindate']);
        $user['loginip'] = long2ip($user['loginip']);
        $user['lastdate'] = empty($user['lastdate']) ? '0000-00-00 00:00' : date('Y-m-d H:i', $user['lastdate']);
        $user['lastip'] = long2ip($user['lastip']);
    }
}