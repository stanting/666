<?php

namespace Admin\Control;

use Core\Base\Control;
use Core\Base\Log;

class Admin extends Control
{
    public $_user = [];
    public $_group = [];
    public $_navs = [];
    public $_pkey = '';
    public $_ckey = '';
    public $_title = '';
    public $_place = '';
    
    public function __construct()
    {
        $_ENV['_config']['FORM_HASH'] = form_hash();
        $this->assign('C', $_ENV['_config']);
        $this->assignValue('core', FRONT_NAME);
        
        $admauth = R($_ENV['_config']['cookie_pre'] . 'admauth', 'R');
        
        $err = 0;
        
        if (empty($admauth)) {
            $err = 1;
        } else {
            $admauth = str_auth($admauth);

            if (empty($admauth)) {
                $err = 1;
            } else {
                $arr = explode("\t", $admauth);

                if (count($arr) < 5) {
                    $err = 1;
                } else {
                    $uid      = $arr[0];
                    $username = $arr[1];
                    $password = $arr[2];
                    $groupid  = $arr[3];
                    $ip       = $arr[4];

                    $user = &$this->user;
                    $user_group = &$this->user_group;

                    $this->_user = $user->get($uid);
                    $this->_group = $user_group->get($groupid);

                    if (empty($this->_group)) {
                        $err = 1;
                    } elseif ($this->_user['password'] !== $password 
                        || $this->_user['username'] !== $username
                        || $this->_user['groupid'] !== $groupid
                    ) {
                        $err = 1;
                    } elseif ($_ENV['_ip'] !== $ip) {
                        _setcookie('admauth', '', 1);

                        $this->message(0, '您的IP已经改变，请重新登录！', 'admin.php?u=index-login');
                    } else {
                        //初始化导航数组
                        $this->initNavigation();

                        //检查用户组权限
                        $this->checkUserGroup();

                        //初始化标题、位置
                        $this->initTitlePlace();

                        $this->assign('_user', $this->_user);
                        $this->assign('_group', $this->_group);
                        $this->assign('_navs', $this->_navs);
                        $this->assign('_pkey', $this->_pkey);
                        $this->assign('_ckey', $this->_ckey);
                        $this->assign('_title', $this->_title);
                        $this->assign('_place', $this->_place);
                    }
                }
            }
        }
        
        if (R('control') == 'index' && R('action') == 'login') {
            if (!$err) {
                exit('<html><body><script>top.location="./"</script></body></html>');
            }
        } elseif ($err) {
            if (R('ajax')) {
                $this->message(0, '请登录后再试！', 'admin.php?u=index-login');
            }

            exit('<html><body><script>top.location="admin.php?u=index-login"</script></body></html>');
        }
    }
    
    public function initNavigation()
    {
        $this->_navs = [
            [
                'my' => '我的',
                'setting' => '设置',
                'category' => '分类',
                'content' => '内容',
                'theme' => '主题',
                'plugin' => '插件',
                'user' => '用户',
                'tool' => '工具'
            ], 
            [
                'my-index' => ['name' => '后台首页', 'p' => 'my'],
                'my-password' => ['name' => '修改密码', 'p' => 'my'],
                'my-newtab' => ['name' => '新标签页', 'p' => 'my'],
                
                'setting-index' => ['name'=>'基本设置', 'p'=>'setting'],
                'setting-seo' => ['name' => 'SEO设置', 'p' => 'setting'],
                'setting-link' => ['name' => '链接设置', 'p' => 'setting'],
                'setting-attach' => ['name' => '上传设置', 'p' => 'setting'],
                'setting-image' => ['name' => '图片设置', 'p' => 'setting'],
                
                'category-index' => ['name' => '分类管理', 'p' => 'category'],
                'navigate-index' => ['name' => '导航管理', 'p' => 'category'],
                
                'article-index' => ['name' => '文章管理', 'p' => 'content'],
                'product-index' => ['name' => '产品管理', 'p' => 'content'],
                'photo-index'   => ['name' => '图集管理', 'p' => 'content'],
                'comment-index' => ['name' => '评论管理', 'p' => 'content'],
                'tag-index'     => ['name' => '标签管理', 'p' => 'content'],
                
                'theme-index' => ['name' => '主题管理', 'p' => 'theme'],
                
                'plugin-index' => ['name' => '插件管理', 'p' => 'plugin'],
                
                'user-index' => ['name' => '用户管理', 'p' => 'user'],
                'user_group-index' => ['name' => '用户组管理', 'p' => 'user'],
                
                'tool-index' => ['name' => '清除缓存', 'p' => 'tool'],
                'tool-rebuild' => ['name' => '重新统计', 'p' => 'tool'],
            ]
        ];
    }
    
    public function checkUserGroup()
    {
        if ($this->_group['groupid'] == 1) {
            return;
        }
        
        if ($this->_group['groupid'] > 5) {
            Log::write('非管理用户组登录后台', 'login.php');
            
            $this->message(0, '您所在的用户组无权访问！', -1);
        } else {
            $purviews = _json_decode($this->_group['purviews']);
            
            isset($purviews['navs']) && $this->_navs = $purviews['navs'];
            
            $control = &$_GET['control'];
            $action  = &$_GET['action'];
            
            if ($control !== 'index' && $control !== 'my' && !isset($purviews['whitelist'][$control][$action])) {
                $this->message(0, '您所在的用户组无权访问！', -1);
            }
        }
    }
    
    public function initTitlePlace()
    {
        $this->_ckey = $_GET['control'] . '-' . $_GET['action'];
        
        if (!isset($this->_navs[1][$this->_ckey])) {
            return;
        }
        
        $this->_pkey = $this->_navs[1][$this->_ckey]['p'];
        $this->_title = $this->_navs[1][$this->_ckey]['name'];
        $this->_place = $this->_navs[0][$this->_pkey] . ' &#187' . $this->_title;
    }
    
    protected function checkIsadmin()
    {
        if ($this->_group['groupid'] !== 1) {
            $this->message(0, '您无权访问！', -1);
        }
    }
    
    public function clearCache()
    {
        $this->runtime->truncate();
        
        try {
            unlink(RUNTIME_PATH . '_runtime.php');
        } catch (Exception $e) {
            
        }
        
        $tmpdir = ['_control', '_model', '_view'];
        
        foreach ($tmpdir as $dir) {
            _rmdir(RUNTIME_PATH . APP_NAME . $dir);
        }
        
        foreach ($tmpdir as $dir) {
            _rmdir(RUNTIME_PATH . FRONT_NAME . $dir);
        }
        
        return true;
    }
}