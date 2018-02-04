<?php

namespace Test\Model;

use Core\Base\Model;

class group extends Model {
    function __construct() {
        $this->table = 'group';	// 表名
        $this->pri = array('groupid');	// 主键
        $this->maxid = 'groupid';		// 自增字段
    }   
}
