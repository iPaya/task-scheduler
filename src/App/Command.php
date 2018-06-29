<?php


namespace App;


class Command extends \Symfony\Component\Console\Command\Command
{
    public function log($logs)
    {
        list($micro, $sec) = explode(' ', microtime());
        $micro = str_pad(ceil($micro * 1000), 3, STR_PAD_LEFT);
        $time = '[' . date('Y-m-d H:i:s') . '.' . $micro . '] ';

        if (is_string($logs)) {
            echo $time . $logs . PHP_EOL;
        } else if (is_array($logs)) {
            foreach ($logs as $log) {
                echo $time . $log . PHP_EOL;
            }
        } else {
            throw new \InvalidArgumentException('Unsupported argument $logs.');
        }
    }
}
