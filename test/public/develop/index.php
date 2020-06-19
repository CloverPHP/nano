<?php

use Clover\Nano\Bootstrap;

require __DIR__ . '/../../utils/autoload.php';

try {
    $boot = new Start('nano', 'develop', false);
    $boot->__invoke();
} catch (Throwable $e) {
    header('Content-type: text/html; charset=UTF-8', true, 500);
    die($e->getMessage());
} catch (Exception $e) {
    header('Content-type: text/html; charset=UTF-8', true, 500);
    die($e->getMessage());
}