<?php

namespace PHPCronManager\Components;

use PHPCronManager\PHPCronManager;

class IPCHelper
{
    private $key = 0;
    private static $ins = null;

    private function __construct()
    {
    }

    public static function putVar($key, $val)
    {
        $id = self::attach();
        if ( !$id ) return false;

        return shm_put_var($id, $key, $val);
    }

    public static function getVar($key)
    {
        $id = self::attach();
        if ( !$id ) return false;

        return shm_get_var($id, $key);
    }

    public static function hasVar($key)
    {
        $id = self::attach();
        if ( !$id ) return false;

        return shm_has_var($id, $key);
    }

    public static function removeVar($key)
    {
        $id = self::attach();
        if ( !$id ) return false;

        return shm_remove_var($id, $key);
    }

    public static function detach()
    {
        $id = self::attach();
        if ( !$id ) return false;

        return shm_detach($id);
    }

    public static function attach()
    {
        $ins = self::getInstance();

        if ( $ins->key === 0 ) {
            $tmpFile = tempnam('/tmp', PHPCronManager::processName);
            if ( !$tmpFile ) {
                return false;
            }

            $key = ftok($tmpFile, 'k');
            if ( $key == -1 ) {
                return false;
            }

            $ins->key = shm_attach($key);
        }

        return (integer)$ins->key;
    }

    public static function getInstance()
    {
        if ( ! self::$ins instanceof IPCHelper ) {
            self::$ins = new static();
        }

        return self::$ins;
    }
}