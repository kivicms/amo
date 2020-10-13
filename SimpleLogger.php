<?php


class SimpleLogger
{
    private $logFile = __DIR__ . '/runtime/log.json';

    public function saveQuery()
    {
        $data = ['get' => $_GET, 'post' => $_POST];
        file_put_contents($this->logFile, json_encode($data));
    }
}
