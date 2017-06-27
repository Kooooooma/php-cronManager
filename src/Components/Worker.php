<?php

namespace PHPCronManager\Components;

class Worker
{
    private $ppid;
    private $pid;
    private $running = false;

    public function __construct()
    {
    }

    public function setPPId($ppid)
    {
        $this->ppid = $ppid;

        return $this;
    }

    public function setPId($pid)
    {
        $this->pid = $pid;

        return $this;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function setRunning($running)
    {
        $this->running = $running;

        return $this;
    }

    public function checkRunning()
    {
        return $this->running;
    }
}