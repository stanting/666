<?php

namespace Core\Db;

interface Idb {
    public function get($key);
    public function multiGet($keys);
    public function set($key, $data);
    public function update($key, $data);
    public function delete($key);
    public function maxid($key, $val = false);
    public function count($key, $val = false);
    public function truncate($table);
    public function version();
    
    public function findFetch($table, $pri, $where = [], $order = [], $start = [], $limit = 0);
    public function findFetchKey($table, $pri, $where = [], $order = [], $start = 0, $limit = 0);
    public function findUpdate($table, $where, $data, $lowprority = false);
    public function findDelete($table, $where, $lowprority = false);
    public function findMaxid($key);
    public function findCount($table, $where = []);
    
    public function indexCreate($table, $index);
    public function indexDrop($table, $index);
}