<?php

namespace PHPCronManager;

use PHPCronManager\Components\CliHelper;
use PHPCronManager\Components\ConfigHelper;
use PHPCronManager\Components\IPCHelper;
use PHPCronManager\Components\Logger;
use PHPCronManager\Components\MsgHelper;
use PHPCronManager\Components\PipeHelper;
use PHPCronManager\Components\Task;
use PHPCronManager\Components\Worker;
use PHPErrors\PHPErrors;
use Psr\Log\LogLevel;

class PHPCronManager
{
    const version = 'v1.0';
    const author = 'Koma <komazhang@foxmail.com>';
    const processName = 'phpCronManager';
    const IPCKey = 'task-uuid';

    /**
     * phpCronManager Master 进程Id，也是所有 worker 的父进程
     *
     * @var int
     */
    private $pid = 0;
    private $pidFile = '';

    /**
     * Master 和 Worker 进程通信的管道
     *
     * @var object
     */
    private $pipe = null;

    /**
     * Master 和 Worker 进程通信的消息队列
     *
     * @var object
     */
    private $msgQueue = null;

    /**
     * 所有 phpCronManager 管理的 task 实体
     *
     * @var array
     */
    private $tasks = array();

    /**
     * phpCronManager worker 进程容器
     *
     * @var array
     */
    private $workers = array();

    /**
     * phpCronManager 配置实体类
     * 包含 phpCronManager 和 task 的配置
     *
     * @var object
     */
    private $config = null;

    /**
     * phpCronManager 日志类实体
     *
     * @var object
     */
    private $logger = null;

    private function __construct()
    {
    }

    public static function main()
    {
        $manager = new static();

        $manager->run();
    }

    protected function run()
    {
        $errorHandler = PHPErrors::enable();

        $args = CliHelper::parseArgs();
        if (empty($args)) CliHelper::printArgsErrorMessage();

        list($command, $taskUUId, $configFile) = $args;
        if ($command == CliHelper::HELP) {
            CliHelper::printHelpMessage();
        }

        $this->config = ConfigHelper::parseConfig($configFile);
        if (empty($this->config->getTasks())) CliHelper::printNoTaskFoundMessage();

        if ($this->config->isManagerOpenLog()) {
            $this->logger = new Logger($this->config->getManagerLogFile());
            $errorHandler->setLogger($this->logger);
        }
        unset($errorHandler);

        $this->log(LogLevel::INFO, CliHelper::startMessage());
        $this->log(LogLevel::INFO, CliHelper::setErrorHandlerMessage());
        $this->log(LogLevel::INFO, CliHelper::parseArgsMessage($args));
        $this->log(LogLevel::INFO, CliHelper::parseConfigMessage($this->config->toString()));
        unset($args);

        $this->setPidFile();
        switch ($command) {
            case CliHelper::START:
                if ($this->checkMasterRunning()) CliHelper::printMasterAlreadyRunningMessage();
                $this->bootstrap($taskUUId);
                break;
            case CliHelper::STOP:
                if (!$this->checkMasterRunning()) CliHelper::printMasterNotRunningMessage();
                $this->stop($taskUUId);
                break;
            case CliHelper::RESTART:
                if (!$this->checkMasterRunning()) CliHelper::printMasterNotRunningMessage();
                break;
            case CliHelper::KILL:
                if (!$this->checkMasterRunning()) CliHelper::printMasterNotRunningMessage();
                break;
            case CliHelper::PS:
                if (!$this->checkMasterRunning()) CliHelper::printMasterNotRunningMessage();
                break;
        }
    }

    protected function bootstrap($taskUUId = '')
    {
        $this->pid = $this->daemon();

        if ($this->pid < 0) {
            $this->log(LogLevel::ERROR, CliHelper::daemonErrorMessage());
            die(1);
        }

        if ($this->writePid() == false) {
            $this->log(LogLevel::ERROR, CliHelper::writeDaemonPidErrorMessage($this->pidFile));
            die(1);
        }

        try {
            $this->msgQueue = new MsgHelper(self::processName);
        } catch (\Exception $e) {
            $this->log(LogLevel::ERROR, CliHelper::createMsgQueueErrorMessage());
            die(1);
        }

        $this->log(LogLevel::INFO, CliHelper::startInitTasksMessage());
        foreach ($this->config->getTasks() as $uuid => $taskConfig) {
            $this->log(LogLevel::INFO, CliHelper::initTaskMessage($uuid));

            try {
                $task = new Task($taskConfig);
                $task->setUUID($uuid);
                $task->setLastRunTime();

                $this->tasks[$uuid] = $task;
                $this->log(LogLevel::INFO, CliHelper::initTaskSuccessMessage($uuid));
            } catch (\Exception $e) {
                $this->log(LogLevel::WARNING, CliHelper::initTaskErrorMessage($uuid, $e->getMessage()));
                continue;
            }
        }

        $this->log(LogLevel::INFO, CliHelper::endInitTasksMessage(count($this->tasks)));
        $this->log(LogLevel::INFO, CliHelper::startInitWorkerMessage());

        for ( $i = 0; $i < $this->config->getWorkerProcessNumber(); $i++ ) {
            $pid = pcntl_fork();

            if ($pid == -1) {
                $this->log(LogLevel::WARNING, CliHelper::forkWorkerProcessErrorMessage());
                continue;
            }

            if ($pid > 0) {
                $worker = new Worker();
                $worker->setPPId($this->getPid());
                $worker->setPId($pid);

                $this->workers[$pid] = $worker;
                unset($worker);
            } else {
                cli_set_process_title(self::processName . ': worker process');

                declare(ticks = 1);
                pcntl_signal(SIGUSR1, array($this, 'doTaskHandler'));

                $this->log(LogLevel::INFO, CliHelper::workerReadyMessage(posix_getpid()));
                while (true) {
                    sleep(30);
                }
                exit(0);
            }
        }

        $this->log(LogLevel::INFO, CliHelper::endInitWorkerMessage(count($this->workers)));
        $this->log(LogLevel::INFO, CliHelper::masterSetupSignalHandlerMessage());

        declare(ticks = 1);
        pcntl_signal(SIGUSR1, array($this, "restartHandler"));
        pcntl_signal(SIGUSR2, array($this, "stopHandler"));

        $this->log(LogLevel::INFO, CliHelper::masterReadyMessage($this->getPid()));
        while (count($this->workers) > 0) {
            foreach ($this->tasks as $uuid => $task) {
                if (time() - $task->getLastRunTime() > $task->getDelayTime()) {
                    $task->setLastRunTime();
                    $this->msgQueue->send($uuid);

                    foreach ($this->workers as $worker) {
                        if (!$worker->checkRunning()) {
                            $worker->setRunning(true);
                            posix_kill($worker->getPid(), SIGUSR1);
                            break;
                        }
                    }
                }
            }

            $ret = pcntl_waitpid(0, $status, WNOHANG);
            if (pcntl_wifexited($status)) { //正常退出
                if (isset($this->workers[$ret])) unset($this->workers[$ret]);
            } else if (pcntl_wifsignaled($status)) { //因未捕获信号退出
                if (isset($this->workers[$ret])) unset($this->workers[$ret]);
            }

            sleep(1);
        }

        $this->clearPidFile();
    }

    protected function stop($taskUUId = '')
    {
        if (($pid = $this->getPid()) > 0) {
            posix_kill($pid, SIGUSR2);
        }
    }

    protected function restartHandler($signo)
    {

    }

    protected function stopHandler($signo)
    {
        foreach ($this->workers as $worker) {
            posix_kill($worker->getPid(), SIGKILL);
        }
    }

    protected function doTaskHandler($signo)
    {
        if (($taskUUID = $this->msgQueue->receive()) && isset($this->tasks[$taskUUID])) {
            $task = $this->tasks[$taskUUID];
            $task->run();
        }
    }

    protected function checkMasterRunning()
    {
        return file_exists($this->pidFile);
    }

    protected function getPid()
    {
        if ( file_exists($this->pidFile) ) {
            return file_get_contents($this->pidFile);
        }

        return 0;
    }

    protected function writePid()
    {
        return file_put_contents($this->pidFile, $this->pid);
    }

    protected function setPidFile()
    {
        $this->pidFile = $this->config->getWorkDir().'/'.self::processName.'.pid';

        $this->log(LogLevel::INFO, CliHelper::writePidMessage($this->pidFile));
    }

    protected function clearPidFile()
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        if (is_object($this->msgQueue)) {
            $this->msgQueue->remove();
        }
    }

    /**
     * 设置进程为守护进程
     * 成功返回守护进程Id，失败返回-1
     *
     * @return int
     */
    protected function daemon()
    {
        $this->log(LogLevel::INFO, CliHelper::startToDaemonMasterMessage());

        //step1 让 daemon 进程在子进程中执行
        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            $this->log(LogLevel::ERROR, CliHelper::forkErrorMessage(1));
            return -1;
        }

        if ( $pid > 0 ) {
            exit(0);
        }

        //step2 使子进程脱离控制终端，登录会话和进程组并使子进程成为新的进程组组长
        $sid = posix_setsid();
        if ( $sid < 0 ) {
            $this->log(LogLevel::ERROR, CliHelper::setsidErrorMessage());
            return -1;
        }
        unset($sid);

        //step3 子进程重新 fork 出新的子进程，自己退出，新的子进程不再是进程组组长
        //从而禁止了进程可以重新打开控制终端
        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            $this->log(LogLevel::ERROR, CliHelper::forkErrorMessage(2));
            return -1;
        }

        if ( $pid > 0 ) {
            exit(0);
        }

        //step4 关闭从父进程继承来的已打开的资源，节省资源
        //close stdin, stdout, stderr

        //step５ 修改 daemon 进程的工作目录
        $workDir = $this->config->getWorkDir();
        if ( !is_dir($workDir) ) {
            $this->log(LogLevel::ERROR, CliHelper::workDirNotExistsMessage($workDir));
            return -1;
        }

        if ( !posix_access($workDir, POSIX_R_OK | POSIX_W_OK) ) {
            $this->log(LogLevel::ERROR, CliHelper::workDirPermissionErrorMessage());
            return -1;
        }
        chdir($workDir);

        //step6 重设 daemon 进程的文件创建掩码
        umask(0);

        //step7 根据需要 daemon 进程需要在之后的运行过程中
        //监控由它 fork 出来的子进程的退出状态
        //catch the SIGCHLD signal

        //step8 设置 Master 进程名称
        cli_set_process_title(self::processName.': master process ('.$this->config->getConfigFile().')');

        $this->log(LogLevel::INFO, CliHelper::endToDaemonMasterMessage());
        return posix_getpid();
    }

    protected function log($level, $message)
    {
        if ( is_array($message) ) $message = json_encode($message);
        if ( $this->logger ) {
            $this->logger->log($level, $message);
        } else {
            print '['.strtoupper($level).'] '.date('Y-m-d H:i:s', time())." {$message}\n";
        }
    }
}