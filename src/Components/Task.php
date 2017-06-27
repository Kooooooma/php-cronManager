<?php

namespace PHPCronManager\Components;

class Task
{
    private $uuid = '';
    private $scriptFile  = '';
    private $delayTime   = 0;
    private $lastRunTime = 0;

    public function __construct($config)
    {
        if ( !isset($config['script']) ) throw new \Exception("Task script file not set");
        $this->scriptFile = $config['script'];

        if ( !isset($config['delay']) ) throw new \Exception("Task delay time not set");
        $this->delayTime = $config['delay'];
    }

    public function run()
    {
        $this->setLastRunTime();
        exec($this->getScriptFile());
    }

    public function getDelayTime()
    {
        return $this->delayTime;
    }

    public function setLastRunTime()
    {
        $this->lastRunTime = time();

        return $this;
    }

    public function getLastRunTime()
    {
        return $this->lastRunTime;
    }

    public function getScriptFile()
    {
        return $this->scriptFile;
    }

    public function setUUID($uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getUUId()
    {
        return $this->uuid;
    }
}