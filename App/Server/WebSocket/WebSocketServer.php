<?php
/**
 * Created by PhpStorm.
 * User: zouyanan
 * Date: 2019/3/13
 * Time: 上午10:07
 */
namespace App\Server\WebSocket;

class WebSocketServer
{
    protected $server;

    protected $redis;

    private $userKey = "user_key:";

    private $roomKey = "room";

    //房间用户信息
    private $roomUser = 'room_user_';

    //把fd name 关联到roomID ,链接close时把用户从房间剔除
    private $roomUserKey = "room_user:";

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
    }

    public function run(){
        $this->server = new \Swoole\WebSocket\Server("0.0.0.0", 9501);
        $this->server->on('open',[$this,'open']);
        $this->server->on('message',[$this,'message']);
        $this->server->on('close',[$this,'close']);
        $this->server->start();
    }

    public function open(\Swoole\WebSocket\Server $server,$request){
        $data['fd'] = $request->fd;
        $data['name'] = $request->get['name'];
        $data['avatar'] = $request->get['avatar'];
        $data['roomId'] = isset($request->get['room']) ? $request->get['room'] : 0;
        //关联fd和用户name，close时从在线用户组中踢出
        $this->redis->set($request->fd,$data['name']);
        //聊天室用户
        if (!empty($data['roomId'])){
            //关联fd和用户name 房间id，close时从房间中踢出
            $this->redis->set($this->roomUserKey.$request->fd,$data['roomId'].'-'.$data['name']);
            //用户fd关联到指定房间，close时踢出
            $this->bindRoomUsers($this->roomKey.$data['roomId'],$request->fd);
            //用户身份信息关联到指定房间,close时踢出
            $this->bindUsers($data,$this->roomUser.$data['name']);
            //房间在线用户
            $roomUsers = $this->roomUsers($this->roomKey.$data['roomId']);
            $roomUsersInfo = $this->getKeyBindUsers($this->roomUser.'*');
            $data['count'] = count($roomUsersInfo);
            $data['type'] = 'onlineList';
            $data['message'] = $roomUsersInfo;
            $this->pushMessageToRoom($roomUsers,$data,$server,$request->fd,true);
        }

    }

    public function message(\Swoole\WebSocket\Server $server,$frame){
        $data = json_decode($frame->data,true);
        $data['time'] = date('h:i:s');
        //聊天室
        if (isset($data['type']) && $data['type'] == 'talkToRoom'){
            $roomUsers = $this->roomUsers($this->roomKey.$data['roomId']);
            $this->pushMessageToRoom($roomUsers,$data,$server,$frame->fd,false);
        }
    }

    public function close($ser, $fd){
        $name = $this->redis->get($fd);
        //从在线列表中踢出
        $this->redis->del($this->roomUser.$name);
        $roomUserKey = $this->redis->get($this->roomUserKey.$fd);
        if (!empty($roomUserKey)){
            $nameAndRoomId = explode('-',$roomUserKey);
            $roomId = $nameAndRoomId[0];
            $this->redis->sRem($this->roomKey.$roomId,$fd);
            $roomUsers = $this->roomUsers($this->roomKey.$roomId);
            $roomUsersInfo = $this->getKeyBindUsers($this->roomUser.'*');
            $data['count'] = count($roomUsersInfo);
            $data['type'] = 'offLine';
            $data['name'] = $name;
            $data['message'] = $roomUsersInfo;
            $this->pushMessageToRoom($roomUsers,$data,$ser,$fd,false);
        }

    }


    /**
     * 查询指定key用户
     * @param $key
     * @return array
     */
    private function getKeyBindUsers($key){
      $keys =  $this->redis->keys($key);
      $users = [];
      foreach ($keys as $key =>$val){
          $users[$key]['fd'] = $this->redis->hGet($val,'fd');
          $users[$key]['name'] = $this->redis->hGet($val,'name');
          $users[$key]['avatar'] = $this->redis->hGet($val,'avatar');
      }
      return $users;
    }

    /**
     * @param $key
     * @param $fd
     * @return bool
     */
    private function bindRoomUsers($key,$fd){
        $this->redis->sAdd($key,$fd);
        return true;
    }

    /**
     * 绑定key对应的用户
     * @param $data
     * @param $key
     * @return bool
     */
    private function bindUsers($data,$key){
        foreach ($data as $field => $val){
            $this->redis->hSet($key,$field,$val);
        }
        return true;
    }

    /**
     * 查询指定房间用户
     * @param $key
     * @return array
     */
    private function roomUsers($key){
        $users = $this->redis->sMembers($key);
        return $users;
    }

    /**
     * 发送消息到指定房间
     * @param $roomUsers
     * @param $data
     * @param \Swoole\WebSocket\Server $server
     * @param $fd
     * @param $self '是否发送给自己'
     * @return bool
     */
    private function pushMessageToRoom($roomUsers,$data,\Swoole\WebSocket\Server $server,$fd,$self = false){
        foreach ($roomUsers as $user){
            if (!$self){
                if ($user != $fd){
                    if ($server->isEstablished($user)){
                        $server->push($user,json_encode($data));
                    }
                }
            }else{
                if ($server->isEstablished($user)){
                    $server->push($user,json_encode($data));
                }
            }
        }
        return true;
    }
}