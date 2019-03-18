<?php
/**
 * Created by PhpStorm.
 * User: zouyanan
 * Date: 2019/3/13
 * Time: 下午4:30
 */

namespace App\Utility;


class Redis
{
    private static $instance;

    private $redis;

    private function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
        return $this->redis;
    }

    public static function getInstance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function hSet($key,$data){
        if (is_array($data) && !empty($data)){
            foreach ($data as $field => $val){
                $this->redis->hSet($key,$field,$val);
            }
            return true;
        }
        return false;
    }

    public function hGet($key,$field){
        if (empty($key) || empty($field)){
            return false;
        }
        return $this->redis->hGet($key,$field);
    }

    public function hKeys($key){
        if (empty($key)){
            return false;
        }
        return $this->redis->hKeys($key);
    }
}