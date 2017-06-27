<?php

namespace PHPCronManager\Components;

class MsgHelper
{
    private $key;
    private $msgType;
    private $serialize;

    public function __construct($tmpFile, $msgType = 1, $serialize = true)
    {
        $tmpFile = tempnam("/tmp", $tmpFile);
        if (!$tmpFile) {
            throw new \Exception("Create tmp file error");
        }

        $key = ftok($tmpFile, 'k');
        if ($key == -1) {
            throw new \Exception("Attach msg id error");
        }

        $this->serialize = $serialize;
        $this->msgType = $msgType;
        $this->key = msg_get_queue($key);
    }

    public function send($message, $blocking = false)
    {
        return msg_send($this->key, $this->msgType, $message, $this->serialize, $blocking);
    }

    public function receive($desiredmsgtype = 0, $maxSize = 1024, $flags = 0)
    {
        if (msg_receive($this->key, $desiredmsgtype, $msgType, $maxSize, $message, $this->serialize, $flags)) {
            return $message;
        }

        return false;
    }

    public function remove()
    {
        return msg_remove_queue($this->key);
    }

    public function stat()
    {
        return msg_stat_queue($this->key);
    }
}