<?php

namespace Admin\Control;

class my extends Admin
{
    public function index()
    {
        $this->user->format($this->_user);
        
        $used_array = $this->getUsed();
        
        $info = [];
        $is_ini_get = function_exists('ini_get');	// 考虑禁用 ini_get 的服务器
        $info['os'] = function_exists('php_uname') ? php_uname() : '未知';
        $info['software'] = R('SERVER_SOFTWARE', 'S');
        $info['php'] = PHP_VERSION;
        $info['mysql'] = $this->user->db->version();
        $info['filesize'] = $is_ini_get ? ini_get('upload_max_filesize') : '未知';
        $info['exectime'] = $is_ini_get ? ini_get('max_execution_time') : '未知';
        $info['url_fopen'] = $is_ini_get ? (ini_get('allow_url_fopen') ? 'Yes' : 'No') : '未知';
        $info['other'] = $this->getOther();
        
        $stat = [];
        $stat['user'] = $this->user->count();
        $stat['space'] = get_byte(disk_free_space(ROOT_PATH));
        
        $this->assign('used_array', $used_array);
        $this->assign('info', $info);
        $this->assign('stat', $stat);
        
        $this->display();
    }
    
    private function getUsed()
    {
        $arr = [
            ['name'=>'发布文章', 'url'=>'article-add', 'imgsrc'=>'admin/ico/article_add.jpg'],
            ['name'=>'文章管理', 'url'=>'article-index', 'imgsrc'=>'admin/ico/article_index.jpg'],
            ['name'=>'发布产品', 'url'=>'product-add', 'imgsrc'=>'admin/ico/product_add.jpg'],
            ['name'=>'产品管理', 'url'=>'product-index', 'imgsrc'=>'admin/ico/product_index.jpg'],
            ['name'=>'发布图集', 'url'=>'photo-add', 'imgsrc'=>'admin/ico/photo_add.jpg'],
            ['name'=>'图集管理', 'url'=>'photo-index', 'imgsrc'=>'admin/ico/photo_index.jpg'],
            ['name'=>'评论管理', 'url'=>'comment-index', 'imgsrc'=>'admin/ico/comment_index.jpg'],
            ['name'=>'分类管理', 'url'=>'category-index', 'imgsrc'=>'admin/ico/category_index.jpg']
        ];
        
        return $arr;
    }
    
    private function getOther()
    {
        $s = '';
        
        if (extension_loaded('gd')) {
            function_exists('imagepng') && $s .= 'png';
            function_exists('imagejpeg') && $s .= 'jpg ';
            function_exists('imagegif') && $s .= 'gif ';
        }
        
        extension_loaded('iconv') && $s .= 'iconv ';
        extension_loaded('mbstring') && $s .= 'mbstring ';
        extension_loaded('zlib') && $s .= 'zlib ';
        extension_loaded('ftp') && $s .= 'ftp ';
        function_exists('fsockopen') && $s .= 'fsockopen';
        
        return $s;
    }
}