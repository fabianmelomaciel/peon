<?php
header('Content-Type: application/json');
$res = [
    'ftp_enabled' => function_exists('ftp_connect'),
    'openssl_enabled' => function_exists('openssl_encrypt'),
    'php_version' => PHP_VERSION,
    'loaded_extensions' => get_loaded_extensions()
];
echo json_encode($res, JSON_PRETTY_PRINT);
