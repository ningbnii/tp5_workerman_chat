<?php
/**
 * Created by PhpStorm.
 * User: ning
 * Date: 2017/8/25
 * Time: 13:50
 */

namespace app\push\controller;

use think\Cache;
use think\Log;
use think\worker\Server;

class Worker extends Server
{
    protected $socket = 'websocket://0.0.0.0:2346';
    protected static $global_uid = 0;

    /**
     * 收到信息
     * @Author: 296720094@qq.com chenning
     * @param $connection
     * @param $data
     */
    public function onMessage($connection, $data)
    {
        $data = json_decode($data, true);
        $type = $data['type'];
        if (!$type) {
            return $connection->send(json_encode(['status' => 400, 'msg' => '参数错误']));
        }
        switch ($type) {
            case 'login':
                $name = $data['name'];
                if (!$name) {
                    return $connection->send(json_encode(['status' => 400, 'msg' => '请填写昵称']));
                }
                $userlist = cache('userlist');
                if ($userlist && in_array($name, $userlist)) {
                    return $connection->send(json_encode(['status' => 400, 'msg' => '该昵称已经被抢占了，换一个吧！']));
                }
                $userinfo = cache('userinfo_' . $name);
                if (!$userinfo) {
                    cache('userinfo_' . $name, $data);
                }
                $userlist[] = $name;
                cache('userlist', $userlist);
                $connection->username = $name;
                $connection->send(json_encode(['status' => 200, 'msg' => '登录成功']));

                foreach ($this->worker->connections as $conn) {
                    if ($conn != $connection) {
                        $conn->send(json_encode(['status' => 500, 'msg' => $name]));
                    }
                    $conn->send(json_encode(['status' => 600, 'msg' => $userlist]));
                }
                break;
            case 'talk':
                $msg = $data['msg'];
                $from = $data['from'];
                $talkTo = $data['talkTo'];
                $userinfo = cache('userinfo_' . $from);
                if ($talkTo == 'all') {
                    foreach ($this->worker->connections as $conn) {
                        $conn->send(json_encode(['status' => 800, 'msg' => $msg, 'name' => $connection->username, 'r' => $userinfo['r'], 'g' => $userinfo['g'], 'b' => $userinfo['b']]));
                    }
                } else {
                    foreach ($this->worker->connections as $conn) {
                        if ($talkTo == $conn->username) {
                            $conn->send(json_encode(['status' => 900, 'msg' => $msg, 'name' => $connection->username, 'r' => $userinfo['r'], 'g' => $userinfo['g'], 'b' => $userinfo['b']]));
                            break;
                        }
                    }
                    $connection->send(json_encode(['status' => 1000, 'msg' => $msg, 'talkTo' => $talkTo, 'r' => $userinfo['r'], 'g' => $userinfo['g'], 'b' => $userinfo['b']]));
                }
                break;
        }

    }

    /**
     * 当连接建立时触发的回调函数
     * @Author: 296720094@qq.com chenning
     * @param $connection
     */
    public function onConnect($connection)
    {
        $connection->uid = ++self::$global_uid;
        Log::info(self::$global_uid);
    }

    /**
     * 当连接断开时触发的回调函数
     * @Author: 296720094@qq.com chenning
     * @param $connection
     */
    public function onClose($connection)
    {
        $userlist = cache('userlist');
        $key = array_search($connection->username, $userlist);
        if ($key !== false) {
            array_splice($userlist, $key, 1);
            cache('userlist', $userlist);
        }
        foreach ($this->worker->connections as $conn) {
            if ($conn != $connection) {
                $conn->send(json_encode(['status' => 700, 'msg' => $connection->username]));
                $conn->send(json_encode(['status' => 600, 'msg' => $userlist]));
            }
        }
    }

    /**
     * 当客户端的连接上发生错误时触发
     * @Author: 296720094@qq.com chenning
     * @param $connection
     * @param $code
     * @param $msg
     */
    public function onError($connection, $code, $msg)
    {
        echo "error $code $msg\n";
    }

    /**
     * 每个进程启动
     * @Author: 296720094@qq.com chenning
     * @param $worker
     */
    public function onWorkerStart($worker)
    {

    }

    public function onWorkerStop()
    {
        Cache::clear();
    }
}
