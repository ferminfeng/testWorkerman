<?php

require_once __DIR__ . '/helper.php';

$errno = 0;
$errmsg = '';

// 建立socket连接到内部推送端口
$client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 1);

// 推送的数据，包含uid字段，表示是给这个uid推送
$data = [
    'type' => 'one', // one : 针对单个uid发送消息 batch: 给一批uid发送消息 all : 给所有注册的uid发送消息
    'uid' => '100249_fermin',
    'content' => [
        'say' => '你好啊',
    ]
];

// 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
fwrite($client, json_encode($data) . "\n");

// 读取推送结果
echo fread($client, 8192);

