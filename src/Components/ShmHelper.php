<?php

namespace PHPCronManager\Components;

class ShmHelper
{
    private $id = null;

    public function __construct($tmpFile)
    {
        $tmpFile = tempnam("/tmp", $tmpFile);
        if (!$tmpFile) {
            throw new \Exception("Create tmp file error");
        }

        $id = ftok($tmpFile, 'k');
        if ($id == -1) {
            throw new \Exception("Attach shm id error");
        }

        $this->id = shm_attach($id);
    }

    public function putVar($key, $val)
    {
        return shm_put_var($this->id, $key, $val);
    }

    public function getVar($key)
    {
        return shm_get_var($this->id, $key);
    }

    public function hasVar($key)
    {
        return shm_has_var($this->id, $key);
    }

    public function removeVar($key)
    {
        return shm_remove_var($this->id, $key);
    }

    public function detach()
    {
        return shm_detach($this->id);
    }
}