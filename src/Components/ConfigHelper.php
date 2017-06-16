<?php

namespace PHPCronManager\Components;

use PHPCronManager\PHPCronManager;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigHelper
{
    public $manager = null;
    public $task    = null;

    public function __construct($config)
    {
        if ( isset($config['manager']) ) {
            $this->manager = $config['manager'];
        }

        if ( isset($config['task']) ) {
            $this->task = $config['task'];
        }
    }

    public static function parseConfig($configFile)
    {
        if ( !file_exists($configFile) ) throw new \Exception("Config File Not Found");

        try {
            $config = Yaml::parse(file_get_contents($configFile));

            return new static($config);
        } catch (ParseException $e) {
            throw $e;
        }
    }

    public function isManagerOpenLog()
    {
        if ( isset($this->manager['runtime_log']) && strtolower($this->manager['runtime_log']) == 'on' ) {
            return true;
        }

        return false;
    }

    public function getManagerLogFile()
    {
        $logFile = '';

        if ( $this->isManagerOpenLog() ) {
            if ( isset($this->manager['log_file']) && trim($this->manager['log_file']) != '') {
                $logFile = trim($this->manager['log_file']);
            } else {
                $logFile = PHPCronManager::processName.'.log';
            }

            $logFile = $this->getWorkDir().'/'.$logFile;
        }

        return $logFile;
    }

    public function getWorkDir()
    {
        $workDir = '/tmp/phpcronmanager';

        if ( isset($this->manager['dir']) ) {
            $workDir = $this->manager['dir'];
        }

        return $workDir;
    }

    public function getTasks()
    {
        return $this->task;
    }

    public function getTaskById($taskId)
    {
        return isset($this->task[$taskId]) ? $this->task[$taskId] : null;
    }

    public function toString()
    {
        return json_encode(array(
            'manager' => $this->manager,
            'task' => $this->task
        ));
    }
}