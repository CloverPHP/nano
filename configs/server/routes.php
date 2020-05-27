<?php

declare(strict_types=1);

return [
    ['GET', '/', function ($request, $response) {
        $response->end('hello nano');
    }
    ],
    ['GET', '/hello[/{name}]', function ($request, $response, $name = '') {
        var_dump($name);
        $response->end("hello $name");
    }],

    ['GET', '/favicon.ico', function ($request, $response) {
        $response->end('');
    }],
];
