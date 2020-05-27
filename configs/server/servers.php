<?php

declare(strict_types=1);

use Server\Events\WebSocket;
use Server\HttpServer;

return [
    'mode' => SWOOLE_BASE,
    'nano' => [
        'ip' => '0.0.0.0',
        'port' => 9501,
        'sock_type' => SWOOLE_SOCK_TCP,
        'callbacks' => [
        ],
        'class_name' => HttpServer::class,
        'settings' => [
            'worker_num' => 1,
            'max_request' => 0,
            'max_coroutine' => 10000,
            'open_tcp_nodelay' => true,
            'socket_buffer_size' => 2 * 1024 * 1024,
            'buffer_output_size' => 2 * 1024 * 1024,
        ]
    ],
    'http' => [
        'ip' => '0.0.0.0',
        'port' => 9501,
        'sock_type' => SWOOLE_SOCK_TCP,
        'callbacks' => [
        ],
        'settings' => [
            'worker_num' => 1,
            'max_request' => 0,
            'max_coroutine' => 10000,
            'open_tcp_nodelay' => true,
            'socket_buffer_size' => 2 * 1024 * 1024,
            'buffer_output_size' => 2 * 1024 * 1024,
        ]
    ],
    'ws' => [
        'ip' => '0.0.0.0',
        'port' => 9502,
        'sock_type' => SWOOLE_SOCK_TCP,
        'class_name' => \Server\WebSocket::class,
        'callbacks' => [
            "open" => [WebSocket::class, 'onOpen'],
            "message" => [WebSocket::class, 'onMessage'],
            "close" => [WebSocket::class, 'onClose'],
        ],
        'settings' => [
            'worker_num' => 1,
            'max_request' => 0,
            'max_coroutine' => 1000,
            'open_tcp_nodelay' => true,
            'open_websocket_protocol' => true,
            'socket_buffer_size' => 2 * 1024 * 1024,
            'buffer_output_size' => 2 * 1024 * 1024,
        ]
    ],
];
