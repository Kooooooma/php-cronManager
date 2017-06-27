<?php

namespace PHPCronManager\Components;

use PHPCronManager\PHPCronManager;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigHelper
{
    public $manager    = null;
    public $task       = null;
    public $configFile = '';
    public $defaultWorkerProcessNum = 1;

    public function __construct($config)
    {
        if (isset($config['manager'])) {
            $this->manager = $config['manager'];
        }

        if (isset($config['task'])) {
            $this->task = $config['task'];
        }
    }

    public static function parseConfig($configFile)
    {
        if (!file_exists($configFile)) throw new \Exception("Config File Not Found");

        try {
            $config = Yaml::parse(file_get_contents($configFile));

            $config = new static($config);
            $config->setConfigFile($configFile);

            return $config;
        } catch (ParseException $e) {
            throw $e;
        }
    }

    public function isManagerOpenLog()
    {
        if (isset($this->manager['runtime_log']) && strtolower($this->manager['runtime_log']) == 'on') {
            return true;
        }

        return false;
    }

    public function getWorkerProcessNumber()
    {
        $workerNum = $this->defaultWorkerProcessNum;

        if (isset($this->manager['worker_processes'])) {
            $workerNum = intval($this->manager['worker_processes']) > 0
                        ? intval($this->manager['worker_processes']) : $workerNum;
        }

        return $workerNum;
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

    public function setConfigFile($configFile)
    {
        $this->configFile = $configFile;

        return $this;
    }

    public function getConfigFile()
    {
        return $this->configFile;
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