<?php

namespace Test\Model;

use Core\Base\Model;

class Category extends Model
{
    private $data = [];
    
    public function __construct()
    {
        $this->table = 'category';
        $this->pri = ['cid'];
        $this->maxid = 'cid';
    }
    
    public function __get($var)
    {
        if ($var == 'cfg') {
            return $this->cfg = $this->runtime->xget();
        } else {
            return parent::__get($var);
        }
    }
    
    //检查基本参数是否设置
    public function checkBase(&$post)
    {
        if(empty($post['mid'])) {
            $name = 'mid';
            $msg = '请选择分类模型';
        } elseif (!isset($post['type'])) {
            $name = 'type';
            $msg = '请选择分类属性';
        } elseif (!isset($post['pid'])) {
            $name = 'pid';
            $msg = '请选择所属频道';
        } elseif (strlen($post['name']) < 1) {
            $name = 'name';
            $msg = '请填写分类名称';
        } elseif (strlen($post['alias']) < 1) {
            $name = 'alias';
            $msg = '请填写分类别名';
        } elseif (strlen($post['alias']) > 50) {
            $name = 'alias';
            $msg = '分类别名不能超过50个字符';
        } elseif (empty($post['cate_tpl'])) {
            $name = 'cate_tpl';
            $msg = '请填写分类页模板';
        } elseif ($post['mid'] > 1 && empty($post['show_tpl'])) {
            $name = 'show_tpl';
            $msg = '请填写内容页模板';
        }
        
        return empty($msg) ? false : ['name' => $name, 'msg' => $msg];
    }
    
    //检查别名是否被使用
    public function checkAlias($alias)
    {
        $msg = $this->only_alias->checkAlias($alias);
        
        return empty($msg) ? false : ['name' => 'alias', 'msg' => $msg];
    }
    
    //检查是否符合修改条件
    public function checkIsEdit($post, $data)
    {
        if($post['cid'] == $post['pid']) {
            $name = 'pid';
            $msg = '所属频道不能修改为自己';
        } elseif ($data['count'] > 0 && $post['mid'] != $data['mid']) {
            $name = 'mid';
            $msg = '分类中有内容，不允许修改分类模型，请先清空分类内容';
        } elseif ($data['count'] > 0 && $post['type'] != $data['type']) {
            $name = 'type';
            $msg = '分类中有内容，不允许修改分类属性，请先清空分类内容';
        } elseif ($data['type'] == 1 && $post['mid'] != $data['mid'] && $this->checkIsSon($data['cid'])) {
            $name = 'mid';
            $msg = '分类有下级分类，不允许修改分类模型';
        } elseif ($data['type'] == 1 && $post['type'] != $data['type'] && $this->checkIsSon($data['cid'])) {
            $name = 'type';
            $msg = '分类有下级分类，不允许修改分类类型';
        }
        
        return empty($msg) ? false : ['name' => $name, 'msg' => $msg];
    }
    
    //检查是否符合删除条件
    public function checkIsDel($data)
    {
        if($data['type'] == 1 && $this->checkIsSon($data['cid'])) {
            return '分类有下级分类，请先删除下级分类';
        }elseif($data['count'] > 0) {
            return '分类中有内容，请先删除内容';
        }
        
        return false;
    }
    
    //检查是否有下级分类
    public function checkIsSon($pid)
    {
        return $this->findFetchKey(['pid' => $pid], [], 0, 1) ? true : false;
    }
    
    //从数据库获取分类
    public function getCategoryDb()
    {
        if(isset($this->data['category_db'])) {
            return $this->data['category_db'];
        }

        $arr = array();
        $tmp = $this->findFetch([], ['orderby']);
        
        foreach($tmp as $v) {
            $arr[$v['cid']] = $v;
        }

        return $this->data['category_db'] = $arr;
    }
    
    //获取分类
    public function getCategoryTree()
    {
        if(isset($this->data['category_tree'])) {
            return $this->data['category_tree'];
        }

        $this->data['category_tree'] = [];
        $tmp = $this->getCategoryDb();

        // 格式化为树状结构 (会舍弃不合格的结构)
        foreach($tmp as $v) {
            $tmp[$v['upid']]['son'][$v['cid']] = &$tmp[$v['cid']];
        }
        
        $this->data['category_tree'] = isset($tmp['0']['son']) ? $tmp['0']['son'] : [];

        return $this->data['category_tree'];
    }
    
    // 获取分类 (二维数组)
    public function getCategory() {
        if(isset($this->data['category_array'])) {
            return $this->data['category_array'];
        }

        $arr = $this->getCategoryTree();
        
        return $this->data['category_array'] = $this->toArray($arr);
    }
    
    // 递归转换为二维数组
    public function toArray($data, $pre = 1) {
        static $arr = [];

        foreach($data as $k => $v) {
            $v['pre'] = $pre;
            
            if(isset($v['son'])) {
                $arr[$v['mid']][] = $v;
                self::toArray($v['son'], $pre+1);
            }else{
                $arr[$v['mid']][] = $v;
            }
        }

        return $arr;
    }
    
    // 获取模型下级所有列表分类的cid
    public function getCidsByMid($mid) {
        $k = 'cate_by_mid_'.$mid;
        
        if (isset($this->data[$k])) {
            return $this->data[$k];
        }

        $arr = $this->runtime->xget($k);
        
        if (empty($arr)) {
                $arr = $this->getCidsByPid(0, $mid);
                $this->runtime->set($k, $arr);
        }
        
        $this->data[$k] = $arr;
        
        return $arr;
    }
    
    // 获取频道分类下级列表分类的cid
    public function getCidsByPid($upid, $mid) {
        $arr = [];
        $tmp = $this->getCategoryDb();
        
        if ($upid != 0 && !isset($tmp[$upid])) {
            return false;
        }

        foreach ($tmp as $k => $v) {
            if ($v['mid'] == $mid) {
                $tmp[$v['upid']]['son'][$v['cid']] = &$tmp[$v['cid']];
            } else {
                unset($tmp[$k]);
            }
        }

        if (isset($tmp[$upid]['son'])) {
            foreach ($tmp[$upid]['son'] as $k => $v) {
                if ($v['type'] == 1) {
                    $arr[$k] = isset($v['son']) ? self::recursionCid($v['son']) : [];
                } elseif ($v['type'] == 0) {
                    $arr[$k] = 1;
                }
            }
        }

        return $arr;
    }
    
    // 递归获取下级分类全部 cid
    public function recursionCid(&$data) {
            $arr = [];
            
            foreach($data as $k => $v) {
                    if (isset($v['son'])) {
                        $arr2 = self::recursionCid($v['son']);
                        $arr = array_merge($arr, $arr2);
                    } else {
                        if($v['type'] == 0) {
                            $arr[] = intval($v['cid']);
                        }
                    }
            }
            
            return $arr;
    }
    
    // 获取分类下拉列表HTML (内容发布时使用)
    public function getCidhtmlByMid($_mid, $cid, $tips = '选择分类') {
        $category_arr = $this->getCategory();

        $s = '<select name="cid" id="cid">';
        
        if (empty($category_arr)) {
            $s .= '<option value="0">没有分类</option>';
        } else {
            $s .= '<option value="0">'.$tips.'</option>';
            
            foreach($category_arr as $mid => $arr) {
                if ($mid != $_mid) {
                    continue;
                }

                foreach ($arr as $v) {
                    $disabled = $v['type'] == 1 ? ' disabled="disabled"' : '';
                    $s .= '<option value="'.$v['cid'].'"'.($v['type'] == 0 && $v['cid'] == $cid ? ' selected="selected"' : '').$disabled.'>';
                    $s .= str_repeat("　", $v['pre']-1);
                    $s .= '|─'.$v['name'].($v['type'] == 1 ? '[频道]' : '').'</option>';
                }
            }
        }
        
        $s .= '</select>';
        
        return $s;
    }
    
    // 获取上级分类的 HTML 代码 (只显示频道分类)
    public function getCategoryPid($mid, $upid = 0, $noid = 0) {
        $category_arr = $this->getCategory();

        $s = '<option value="0">无</option>';
        
        if (isset($category_arr[$mid])) {
            foreach ($category_arr[$mid] as $v) {
                // 不显示列表的分类
                if ($mid> 1 && $v['type'] == 0) {
                    continue;
                }

                // 当 $noid 有值时，排除等于它和它的下级分类
                if ($noid) {
                    if (isset($pre)) {
                        if ($v['pre'] > $pre) {
                            continue;
                        } else {
                            unset($pre);
                        }
                    }
                    if ($v['cid'] == $noid) {
                        $pre = $v['pre'];
                        continue;
                    }
                }

                $s .= '<option value="'.$v['cid'].'"'.($v['cid'] == $upid ? ' selected="selected"' : '').'>';
                $s .= str_repeat("　", $v['pre']-1);
                $s .= '|─'.$v['name'].'</option>';
            }
        }

        return $s;
    }
    
    // 获取指定分类的 mid (如果 cid 为空，则读第一个分类的 mid)
    public function getMidByCid($cid) {
        if ($cid) {
            $arr = $this->read($cid);
        } else {
            $arr = $this->getCategory();
            
            if (empty($arr)) {
                return 2;
            }

            $arr = current($arr);
            $arr = current($arr);
        }
        
        return $arr['mid'];
    }
    
    // 获取分类当前位置
    public function getPlace($cid) {
        $p = [];
        $tmp = $this->getCategoryDb();

        while (isset($tmp[$cid]) && $v = &$tmp[$cid]) {
            array_unshift($p, [
                'cid'=> $v['cid'],
                'name'=> $v['name'],
                'url'=> $this->categoryUrl($v['cid'], $v['alias'])
            ]);

            $cid = $v['upid'];
        }

        return $p;
    }
    
    // 获取分类缓存合并数组
    public function getCache($cid) {
        $k = 'cate_'.$cid;
        
        if (isset($this->data[$k])) {
            return $this->data[$k];
        }

        $arr = $this->runtime->xget($k);
        
        if (empty($arr)) {
                $arr = $this->updateCache($cid);
        }
        
        $this->data[$k] = $arr;
        
        return $arr;
    }
    
    // 更新分类缓存合并数组
    public function updateCache($cid) {
        $k = 'cate_'.$cid;
        $arr = $this->read($cid);
        
        if (empty($arr)) {
            return FALSE;
        }

        $arr['place'] = $this->getPlace($cid);	// 分类当前位置
        $arr['topcid'] = $arr['place'][0]['cid'];	// 顶级分类CID
        $arr['table'] = $this->cfg['table_arr'][$arr['mid']];	// 分类模型表名

        // 如果为频道，获取频道分类下级CID
        if ($arr['type'] == 1) {
            $arr['son_list'] = $this->getCidsByPid($cid, $arr['mid']);
            $arr['son_cids'] = [];
            
            if (!empty($arr['son_list'])) {
                foreach ($arr['son_list'] as $c => $v) {
                    if (is_array($v)) {
                        $v && $arr['son_cids'] = array_merge($arr['son_cids'], $v);
                    } else {
                        $arr['son_cids'][] = $c;
                    }
                }
            }
        }

        $this->runtime->set($k, $arr);
        
        return $arr;
    }
    
    // 删除所有分类缓存 (最多读取2000条，如果缓存太大，需要手工清除缓存)
    public function deleteCache() {
        $key_arr = $this->runtime->findFetchKey([], [], 0, 2000);
        
        foreach ($key_arr as $v) {
            if (substr($v, 10, 5) == 'cate_') {
                $this->runtime->delete(substr($v, 10));
            }
        }
        
        return true;
    }
    
    // 分类链接格式化
    public function category_url(&$cid, &$alias, $page = FALSE) {
            // hook category_model_category_url_before.php

            if(empty($_ENV['_config']['twcms_parseurl'])) {
                    return $this->cfg['webdir'].'index.php?cate--cid-'.$cid.($page ? '-page-{page}' : '').$_ENV['_config']['url_suffix'];
            }else{
                    if($page) {
                            return $this->cfg['webdir'].$alias.$this->cfg['link_cate_page_pre'].'{page}'.$this->cfg['link_cate_page_end'];
                    }else{
                            return $this->cfg['webdir'].$alias.$this->cfg['link_cate_end'];
                    }
            }
    }
}