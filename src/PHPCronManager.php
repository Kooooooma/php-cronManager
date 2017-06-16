<?php

namespace PHPCronManager;

use PHPCronManager\Components\CliHelper;
use PHPCronManager\Components\ConfigHelper;
use PHPCronManager\Components\Logger;
use PHPCronManager\Components\Task;
use PHPErrors\PHPErrors;
use Psr\Log\LogLevel;

class PHPCronManager
{
    const version = 'v1.0';
    const author  = 'Koma <komazhang@foxmail.com>';
    const processName = 'phpCronManager';

    /**
     * phpCronManager 进程Id，也是所有 task 的父进程
     *
     * @var int
     */
    private $pid = 0;
    private $pidFile = '';

    /**
     * 所有 phpCronManager 管理的 task 实体
     *
     * @var array
     */
    private $tasks = array();

    /**
     * phpCronManager 配置实体类
     * 包含 phpCronManager 和 task 的配置
     *
     * @var object
     */
    private $config = null;
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
        $this->log(LogLevel::INFO, CliHelper::startMessage());

        $errorHandler = PHPErrors::enable();
        $this->log(LogLevel::INFO, CliHelper::setErrorHandlerMessage());

        $args = CliHelper::parseArgs();
        if (empty($args)) CliHelper::printArgsErrorMessage();
        $this->log(LogLevel::INFO, CliHelper::parseArgsMessage($args));

        list($command, $taskUUId, $configFile) = $args;
        unset($args);

        if ($command == CliHelper::HELP) {
            CliHelper::printHelpMessage();
        }

        $this->config = ConfigHelper::parseConfig($configFile);
        if (empty($this->config->getTasks())) CliHelper::printNoTaskFoundMessage();
        $this->log(LogLevel::INFO, CliHelper::parseConfigMessage($this->config->toString()));

        if ( $this->config->isManagerOpenLog() ) {
            $this->logger = new Logger($this->config->getManagerLogFile());
            $errorHandler->setLogger($this->logger);
        }
        unset($errorHandler);

        $this->setPidFile();
        switch ($command) {
            case CliHelper::START:
                if ($this->checkProcessRunning($taskUUId)) CliHelper::printProcessAlreadyRunningMessage();
                $this->bootstrap($taskUUId);
                break;
            case CliHelper::STOP:
                if (!$this->checkProcessRunning($taskUUId)) CliHelper::printNoProcessRunningMessage();
                $this->stop($taskUUId);
                break;
            case CliHelper::RESTART:
                if (!$this->checkProcessRunning($taskUUId)) CliHelper::printNoProcessRunningMessage();
                break;
            case CliHelper::KILL:
                if (!$this->checkProcessRunning($taskUUId)) CliHelper::printNoProcessRunningMessage();
                break;
            case CliHelper::PS:
                if (!$this->checkProcessRunning($taskUUId)) CliHelper::printNoProcessRunningMessage();
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

        $this->log(LogLevel::INFO, CliHelper::startInitTaskProcess());
        //fork任务子进程
        foreach ( $this->config->getTasks() as $uuid => $taskConfig ) {
            $this->log(LogLevel::INFO, CliHelper::initTaskProcess($uuid));

            try {
                $task = new Task($taskConfig);
                $task->setUUID($uuid);
                $task->setLastRunTime();
            } catch (\Exception $e) {
                $this->log(LogLevel::WARNING, CliHelper::initTaskProcessErrorMessage($uuid, $e->getMessage()));
                continue;
            }
            $this->log(LogLevel::INFO, CliHelper::initTaskProcessSuccess($uuid));

            $pid = pcntl_fork();
            if ($pid == -1) {
                $this->log(LogLevel::ERROR, CliHelper::initTaskProcessErrorMessage($uuid));
                continue;
            }

            if ($pid > 0) {
                $task->setPid($pid);
                $this->tasks[$uuid] = $task;
                unset($task);
            } else {
                $task->setPid(posix_getpid());
                $info = array();

                $this->log(LogLevel::INFO, CliHelper::taskReadyMessage($uuid, $task->getPid()));
                while (pcntl_sigwaitinfo(array(SIGUSR1), $info)) {
                    $task->run();
                    $this->log(LogLevel::INFO, CliHelper::recordTaskRunningMessage($task->getUUId(), $task->getPid()));
                }
            }
            unset($pid);
        }

        $this->log(LogLevel::INFO, CliHelper::managerSetupSignalHandlerMessage());
        declare(ticks = 1);
        pcntl_signal(SIGUSR1, array($this, "restartHandler"));
        pcntl_signal(SIGUSR2, array($this, "stopHandler"));
        pcntl_signal(SIGINT, array($this, "killHandler"));

        $this->log(LogLevel::INFO, CliHelper::managerReadyMessage($this->getPid()));
        while (count($this->tasks) > 0) {
            foreach ($this->tasks as $uuid => $task) {
                if (time() - $task->getLastRunTime() > $task->getDelayTime()) {
                    posix_kill($task->getPid(), SIGUSR1);
                    $task->setLastRunTime();
                }

                $res = pcntl_waitpid($task->getPid(), $status, WNOHANG);
                if ($res == -1) { //an error occured
                } else if ($res > 0) { //child process exit successfully
                }
                $this->log(LogLevel::INFO, CliHelper::recordTaskRunningStatus($uuid, $task->getPid(), $res));
            }

            sleep(1);
        }
    }

    protected function stop($taskUUId = '')
    {
        if ( ($pid = $this->getPid()) > 0 ) {
            posix_kill($pid, SIGUSR2);
        }
    }

    protected function killHandler($signo)
    {

    }

    protected function restartHandler($signo)
    {

    }

    protected function stopHandler($signo)
    {
        foreach ($this->tasks as $uuid => $task) {
            posix_kill($task->getPid(), SIGKILL);
        }

        $this->clearPidFile();
        posix_kill($this->pid, SIGKILL);
    }

    protected function checkProcessRunning($taskUUId = '')
    {
        //需要检测对应的任务是否已启动
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
    }

    protected function clearPidFile()
    {
        if ( file_exists($this->pidFile) ) {
            unlink($this->pidFile);
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

    public function __destruct()
    {
        //异常退出时需要做清理工作,正常退出不走该流程
//        $this->clearPidFile();
    }
}