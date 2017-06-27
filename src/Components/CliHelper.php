<?php

namespace PHPCronManager\Components;

use PHPCronManager\PHPCronManager;

class CliHelper
{
    const HELP    = 'help';
    const START   = 'start';
    const STOP    = 'stop';
    const KILL    = 'kill';
    const PS      = 'ps';
    const CONFIG  = 'config';
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

    public static function printMasterAlreadyRunningMessage()
    {
        $message = PHPCronManager::processName.' 已经在运行';

        self::printMessage($message, true, true);
    }

    public static function printApplyIPCKeyErrorMessage()
    {
        $message = 'IPC Key 申请失败';

        self::printMessage($message, true, true);
    }

    public static function printMasterNotRunningMessage()
    {
        $message = PHPCronManager::processName.' 还未启动';

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
            self::START   . '::',
            self::STOP    . '::',
            self::RESTART . '::',
            self::KILL    . ':',
            self::CONFIG  . ':',
            self::PS,
            self::HELP
        );
    }

    public static function startMessage()
    {
        return PHPCronManager::processName . " 开始启动";
    }

    public static function setErrorHandlerMessage()
    {
        return "设置PHP错误处理器，错误报告级别: " . self::error2string(error_reporting());
    }

    public static function parseArgsMessage($args)
    {
        return "解析运行指令参数: " . (is_string($args) ? $args : json_encode($args));
    }

    public static function parseConfigMessage($config)
    {
        return "解析配置文件: " . (is_string($config) ? $config : json_encode($config));
    }

    public static function writePidMessage($pidfile)
    {
        return "指定Master进程PID文件位置: {$pidfile}";
    }

    public static function startToDaemonMasterMessage()
    {
        return "开始将Master进程设置为Daemon模式";
    }

    public static function endToDaemonMasterMessage()
    {
        return "Master进入Daemon模式";
    }

    public static function forkErrorMessage($time = 1)
    {
        return "第{$time}次Fork失败";
    }

    public static function setsidErrorMessage()
    {
        return "重置会话Id错误";
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
        return "写主进程PID文件失败，PID文件: {$pidFile}";
    }

    public static function createPIPEErrorMessage($pipeFile)
    {
        return "创建Master-Worker通信管道失败，PIPE文件: {$pipeFile}";
    }

    public static function createMsgQueueErrorMessage()
    {
        return "创建Master-Worker通信消息队列失败";
    }

    public static function startInitTasksMessage()
    {
        return "开始初始化待执行任务";
    }

    public static function endInitTasksMessage($taskNumber)
    {
        return "待执行任务初始化完成，总任务数: {$taskNumber}";
    }

    public static function initTaskMessage($uuid)
    {
        return "开始初始化任务: {$uuid}";
    }

    public static function initTaskErrorMessage($uuid, $msg = '')
    {
        return "任务 {$uuid} 初始化失败，错误信息: {$msg}";
    }

    public static function initTaskSuccessMessage($uuid)
    {
        return "任务 {$uuid} 初始化成功";
    }

    public static function startInitWorkerMessage()
    {
        return "开始初始化Worker进程";
    }

    public static function forkWorkerProcessErrorMessage()
    {
        return "Fork worker进程失败，进入重试阶段";
    }

    public static function endInitWorkerMessage($workerNum)
    {
        return "初始化Worker进程完成，Worker进程数: {$workerNum}";
    }

    public static function workerReadyMessage($pid)
    {
        return "Worker进程就绪，进程Id: {$pid}";
    }

    public static function masterSetupSignalHandlerMessage()
    {
        return "Master开始安装信号处理器";
    }

    public static function masterReadyMessage($pid)
    {
        return "Master就绪，进程Id: {$pid}";
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