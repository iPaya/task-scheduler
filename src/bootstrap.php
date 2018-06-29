<?php

define('ROOT_PATH', dirname(__DIR__));

require_once(ROOT_PATH . '/vendor/autoload.php');

$envFile = ROOT_PATH . '/.env';

if (file_exists($envFile)) {
    // 从 env.ini 加载环境变量
    $dotenv = new \Symfony\Component\Dotenv\Dotenv();
    $dotenv->load($envFile);
}
