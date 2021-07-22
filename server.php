<?php
declare(strict_types = 1);

namespace Workerman;

/**
 * Created1 by PhpStorm.
 * User: fyf
 * Date: 2021/7/22
 * Time: 16:19
 */

use Workerman\Worker;

require_once __DIR__ . '/Autoloader.php';
require_once __DIR__ . '/helper.php';

// 初始化一个worker容器，监听1234端口
$worker = new Worker('websocket://0.0.0.0:1234');

/**
 * 注意这里进程数必须设置为1，否则会报端口占用错误
 * (php 7可以设置进程数大于1，前提是$inner_text_worker->reusePort=true)
 */

$worker->count = 1;

// worker进程启动后创建一个text Worker以便打开一个内部通讯端口
$worker->onWorkerStart = function ($worker) {

    // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
    $inner_text_worker = new Worker('text://0.0.0.0:5678');
    $inner_text_worker->onMessage = function ($connection, $buffer) {
        // $data数组格式，里面有uid，表示向那个uid的页面推送数据
        $data = json_decode($buffer, true);

        $fileName = 'push';
        $key = md5(time() . rand(1000000, 9999999) . $buffer);
        setLog('server', $fileName, ['msg' => '接收到的数据', 'key' => $key, 'data' => $data]);

        $uid = $data['uid'];

        // 通过workerman，向uid的页面推送数据
        $ret = sendMessageByUid($uid, $buffer);

        $res = $ret ? 'ok' : 'fail';

        setLog('server', $fileName, ['msg' => '返回结果', 'key' => $key, 'res' => $res]);

        // 返回推送结果
        $connection->send($res);
    };

    // ## 执行监听 ##
    $inner_text_worker->listen();
};

// 新增加一个属性，用来保存uid到connection的映射
$worker->uidConnections = [];

// 当有客户端发来消息时执行的回调函数
$worker->onMessage = function ($connection, $data) {
    global $worker;

    $fileName = 'receive';
    $key = md5(time() . rand(1000000, 9999999) . $data);
    $data = json_decode($data, true);

    setLog('client', $fileName, ['msg' => '接收到的数据', 'key' => $key, 'data' => $data]);

    // 安全性校验
    if (!isset($data['type']) || !in_array($data['type'], ['register', 'content'])) {
        return;
    }

    $uid = $data['uid'];

    // 判断当前客户端是否已经验证,既是否设置了uid
    if (!isset($connection->uid)) {

        // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
        $connection->uid = $uid;

        /**
         * 保存uid到connection的映射，这样可以方便的通过uid查找connection，
         * 实现针对特定uid推送数据
         */
        $worker->uidConnections[$connection->uid] = $connection;

        setLog('client', $fileName, ['msg' => '注册uid', 'key' => $key]);
    }
};

// 当有客户端连接断开时
$worker->onClose = function ($connection) {
    global $worker;
    if (isset($connection->uid)) {

        setLog('server', 'close', ['msg' => '当有客户端连接断开时', 'uid' => $connection->uid]);
        // 连接断开时删除映射
        unset($worker->uidConnections[$connection->uid]);
    }
};

// 向所有验证的用户推送数据
function broadcast($message)
{
    global $worker;

    setLog('server', 'allPush', ['msg' => '向所有验证的用户推送数据', 'message' => $message]);

    foreach ($worker->uidConnections as $connection) {
        $connection->send($message);
    }
}

// 针对uid推送数据
function sendMessageByUid($uid, $message)
{
    global $worker;

    setLog('server', 'push_uid', ['msg' => '针对uid推送数据', 'uid' => $uid, 'message' => $message]);

    if (isset($worker->uidConnections[$uid])) {
        $connection = $worker->uidConnections[$uid];
        $connection->send($message);

        setLog('server', 'push_uid', ['msg' => '针对uid推送数据-推送成功', 'uid' => $uid]);
        return true;
    } else {
        setLog('server', 'push_uid', ['msg' => '针对uid推送数据-uid不存在', 'uid' => $uid]);
        return false;
    }
}

// 运行所有的worker
Worker::runAll();