<?php

/**
 * 导航模块
 *
 * @return array
*/
function navigate($conf)
{
    global $run;

    $nav_arr = $run->kv->xget('navigate');
    
    foreach ($nav_arr as &$v) {
        if ($v['cid']) {
            $v['url'] = $run->categoryUrl($v['cid'], $v['alias']);
        }
        
        if (!empty($v['son'])) {
            foreach ($v['son'] as &$v2) {
                if ($v2['cid']) {
                    $v2['url'] = $run->category->categoryUrl($v2['cid'], $v2['alias']);
                }
            }
        }
    }
    
    return $nav_arr;
}