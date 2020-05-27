<?php

return [
    'timezone' => 'PRC',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'pass' => '',
        'name' => 'simulator',
        'charset' => 'UTF8MB4',
        'collate' => 'UTF8MB4_UNICODE_CI',
        'timeout' => 3,
        'persistent' => false,
        'transaction' => true
    ],
    'redis' => [
        'db' => 0,
        'authPass' => '',
        'postHash' => '',
        'timeout' => 3,
        'rwTimeout' => 3,
        'enable' => false,
        'instances' => [
            [
                ['127.0.0.1', 6379],
            ],
        ]
    ],
    'hook' => [

    ],
    'logger' => [
        'mode' => 'file',
        'level' => 511,
        'path' => '/tmp/',
        'file' => '',
    ],
    'profiler' => [
        'enable' => true,
        'queryLogDb' => true,
        'queryLogRedis' => 100,
        'maxLog' => 100,
    ]
];