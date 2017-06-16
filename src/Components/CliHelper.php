<?php

namespace PHPCronManager\Components;

use PHPCronManager\PHPCronManager;

class CliHelper
{
    const HELP = 'help';
    const START = 'start';
    const STOP = 'stop';
    const KILL = 'kill';
    const PS = 'ps';
    const CONFIG = 'config';
    const RESTART = 'restart';

    public static $actionCommand = array(
        self::START,
        self::STOP,
        self::RESTART,
        self::KILL,
        self::PS
    );

    /**
     * Array(
     *    'command', 'task uuid', 'config file'
     * )
     *
     * @return array
     */
    public static function parseArgs()
    {
        $longOptions = self::getLongOptions();

        $args = getopt('', $longOptions);
        $argLen = count($args);
        unset($longOptions);

        $ret = array();
        if ($argLen == 2 && isset($args[self::CONFIG])) {
            $configFile = $args[self::CONFIG];
            unset($args[self::CONFIG]);

            list($command, $taskId) = each($args);
            if (in_array($command, self::$actionCommand)) {
                $ret = array(
                    $command,
                    $taskId,
                    $configFile
                );
            }

            unset($configFile, $command, $taskId);
        } else if ($argLen == 1 && isset($args[self::HELP])) {
            $ret = array(
                self::HELP, false, false
            );
        }
        unset($args, $argLen);

        return $ret;
    }

    public static function printMessage($message, $exit = false, $printVersionInfo = false)
    {
        if ($printVersionInfo) self::printVersionInfo();
        print $message . "\n\n";
        if ($exit) die(1);
    }

    public static function printVersionInfo($exit = false)
    {
        $message = 'PHP Cron Manager ' . PHPCronManager::version . ' by ' . PHPCronManager::author;

        self::printMessage($message, $exit);
    }

    public static function printProcessAlreadyRunningMessage()
    {
        $message = 'PHP Cron Manager 已经在运行';

        self::printMessage($message, true, true);
    }

    public static function printApplyIPCKeyErrorMessage()
    {
        $message = 'IPC Key 申请失败';

        self::printMessage($message, true, true);
    }

    public static function printNoProcessRunningMessage()
    {
        $message = '没有任务在运行';

        self::printMessage($message, true, true);
    }

    public static function printArgsErrorMessage()
    {
        $message = 'Error: 请查看帮助手册，帮助指令： --help';

        self::printMessage($message, true, true);
    }

    public static function printNoTaskFoundMessage()
    {
        $message = 'Info: 没有发现需要运行的任务，请检查配置文件';

        self::printMessage($message, true, true);
    }

    public static function printHelpMessage()
    {
        $message = <<<HELP
Usage: phpCronManager --config CONFIG_FILE ActionCommand [task-uuid]
       phpCronManager --help

Action Command:
    --start   启动 phpCronManager 管理的指定任务或者所有任务
    --stop    停止 phpCronManager 管理的指定任务或者所有任务
    --restart 重启 phpCronManager 管理的指定任务或者所有任务
    
    --kill    强制停止 phpCronManager 管理的某一指定任务
    --ps      列出当前 phpCronManager 管理的任务进程状态信息
    
Config Command:
    --config  指定 phpCronManager 配置文件
    
Help Command:
    --help  打印帮助信息
HELP;

        self::printMessage($message, true, true);
    }

    public static function getLongOptions()
    {
        return array(
            self::START . '::',
            self::STOP . '::',
            self::RESTART . '::',
            self::KILL . ':',
            self::CONFIG . ':',
            self::PS,
            self::HELP
        );
    }

    public static function startMessage()
    {
        return PHPCronManager::processName . " 启动";
    }

    public static function setErrorHandlerMessage()
    {
        return "设置PHP错误处理类，错误报告级别：" . self::error2string(error_reporting());
    }

    public static function parseArgsMessage($args)
    {
        return "解析运行指令参数：" . is_string($args) ? $args : json_encode($args);
    }

    public static function parseConfigMessage($config)
    {
        return "解析配置文件：" . is_string($config) ? $config : json_encode($config);
    }

    public static function forkErrorMessage($time = 1)
    {
        return "fork error, fork count: {$time}";
    }

    public static function setsidErrorMessage()
    {
        return "setsid error";
    }

    public static function workDirNotExistsMessage($workDir)
    {
        return "工作目录不存在: {$workDir}";
    }

    public static function workDirPermissionErrorMessage()
    {
        return "工作目录权限错误";
    }

    public static function daemonErrorMessage()
    {
        return "设置进程为守护进程失败";
    }

    public static function writeDaemonPidErrorMessage($pidFile)
    {
        return "写主进程pid文件失败，pid文件：{pidFile}";
    }

    public static function startInitTaskProcess()
    {
        return "开始初始化任务子进程";
    }

    public static function initTaskProcess($uuid)
    {
        return "开始初始化任务：{$uuid}";
    }

    public static function initTaskProcessErrorMessage($uuid, $msg = '')
    {
        return "子进程[{$uuid}]初始化失败，错误信息：{$msg}";
    }

    public static function taskReadyMessage($uuid, $pid)
    {
        return "子进程[{$uuid}]就绪，进程Id: {$pid}";
    }

    public static function initTaskProcessSuccess($uuid)
    {
        return "子进程[{$uuid}]初始化成功，开始创建子进程";
    }

    public static function managerSetupSignalHandlerMessage()
    {
        return "主进程开始安装信号处理器";
    }

    public static function managerReadyMessage($pid)
    {
        return "主进程就绪，进程Id: {$pid}";
    }

    public static function recordTaskRunningStatus($uuid, $pid, $status = 0)
    {
        return "子进程[{$uuid}]运行中，运行状态：{$status}，进程Id: {$pid}";
    }

    public static function recordTaskRunningMessage($uuid, $pid)
    {
        return "子进程[{$uuid}]收到调用信号，调用外部脚本执行，进程Id: {$pid}";
    }

    public static function error2string($value)
    {
        $levelNames = array(
            E_ERROR   => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE   => 'E_PARSE',
            E_NOTICE  => 'E_NOTICE',
            E_CORE_ERROR    => 'E_CORE_ERROR',
            E_CORE_WARNING  => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_USER_ERROR    => 'E_USER_ERROR',
            E_USER_WARNING  => 'E_USER_WARNING',
            E_USER_NOTICE   => 'E_USER_NOTICE',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING'
        );
        if ( defined('E_STRICT') ) $levelNames[E_STRICT] = 'E_STRICT';

        $levels = array();
        if ( ($value&E_ALL) == E_ALL ) {
            $levels[] = 'E_ALL';
            $value &= ~E_ALL;
        }

        foreach ($levelNames as $level => $name) {
            if ( ($value&$level) == $level ) $levels[] = $name;
        }

        return implode(' | ',$levels);
    }
}