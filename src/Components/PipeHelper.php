<?php

namespace PHPCronManager\Components;

class PipeHelper
{
    private $pipePath = '';
    private $block    = false;
    private $read     = null;
    private $write    = null;

    public function __construct($pipePath = '', $mode = 0666, $block = false)
    {
        if (!posix_mkfifo($pipePath, $mode) || filetype($pipePath) != 'fifo') {
            throw new \Exception("Create pipe error");
        }

        $this->pipePath = $pipePath;
        $this->block = $block;
    }

    public function read($size = 1024)
    {
        if (!is_resource($this->read)) {
            $this->read = fopen($this->pipePath, 'r+');

            if (!is_resource($this->read) || stream_set_blocking($this->read, $this->block ? 1 : 0)) {
                return false;
            }
        }

        return fread($this->read, $size);
    }

    public function write($data)
    {
        if (!is_resource($this->write)) {
            $this->write = fopen($this->pipePath, 'w+');

            if (!is_resource($this->write) || stream_set_blocking($this->write, $this->block ? 1 : 0)) {
                return false;
            }
        }

        return fwrite($this->write, $data);
    }

    public function close($remove = false)
    {
        if (is_resource($this->read)) {
            fclose($this->read);
        }

        if (is_resource($this->write)) {
            fclose($this->write);
        }

        if ($remove && file_exists($this->pipePath) ) {
            unlink($this->pipePath);
        }
    }
}