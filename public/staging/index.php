<?php

use Clover\Nano\Bootstrap;

require __DIR__ . '/../../vendor/autoload.php';

try {
    $boot = new Bootstrap('nano', 'staging', true);
    $boot->__invoke();
} catch (Throwable $e) {
    header('Content-type: text/html; charset=UTF-8', true, 500);
    die($e->getMessage());
} catch (Exception $e) {
    header('Content-type: text/html; charset=UTF-8', true, 500);
    die($e->getMessage());
}