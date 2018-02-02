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
}