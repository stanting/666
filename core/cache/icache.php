<?php
namespace Core\Cache;

interface Icache
{
    public function get($key);
    public function multiGet($keys);
    public function set($key, $data, $life = 0);
    public function update($key, $data, $life = 0);
    public function delete($key);
    public function maxid($table, $val = false);
    public function count($table, $val = false);
    public function truncate($pre = '');
    
    public function l2CacheGet($l2_key);
    public function l2CacheSet($l2_key, $keys, $life = 0);
}