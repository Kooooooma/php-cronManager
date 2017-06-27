<?php

namespace PHPCronManager\Components;

use Monolog\Handler\StreamHandler;
use PHPCronManager\PHPCronManager;

class Logger
{
    public $logger = null;

    public function __construct($logFile)
    {
        $this->logger = new \Monolog\Logger(PHPCronManager::processName);
        $this->logger->pushHandler(new StreamHandler($logFile));
    }

    public function log($level, $message)
    {
        $this->logger->log($level, $message);
    }
}