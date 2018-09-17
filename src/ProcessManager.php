<?php
/*
 * This file is part of the long/slim package.
 *
 * (c) Sinpe <support@sinpe.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sinpe\Swoole;

use EasySwoole\Core\Swoole\Memory\TableManager;
use Swoole\Table;


class ProcessManager
{
    private $server;

    private $processList = [];

    /**
     * __construct
     */
    public function __construct(
        ServerInterface $server
    ) {
        $this->server = $server;

        // TODO 临时
        // TableManager::getInstance()->add(
        //     'process_hash_map',
        //     [
        //         'pid' => [
        //             'type' => Table::TYPE_INT,
        //             'size' => 10
        //         ]
        //     ],
        //     256
        // );
    }

    /**
     * Undocumented function
     *
     * @param string $processName
     * @param string $processClass
     * @param array $args
     * @param boolean $async
     * @return boolean
     */
    public function addProcess(string $processName, string $processClass, array $args = [], $async = true) : bool
    {
        if ($this->server->isStart()) {
            trigger_error("you can not add a process {$processName}.{$processClass} after server start");
            return false;
        }

        $key = md5($processName);
        
        if (!isset($this->processList[$key])) {
            $process = new $processClass($processName, $args, $async);
            $this->processList[$key] = $process;
            return true;
        } else {
            trigger_error("you can not add the same name process : {$processName}.{$processClass}");
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $processName
     * @return AbstractProcess|null
     */
    public function getProcessByName(string $processName) : ? AbstractProcess
    {
        $key = md5($processName);
        if (isset($this->processList[$key])) {
            return $this->processList[$key];
        } else {
            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @param integer $pid
     * @return AbstractProcess|null
     */
    public function getProcessByPid(int $pid) : ? AbstractProcess
    {
        $table = TableManager::getInstance()->get('process_hash_map');
        foreach ($table as $key => $item) {
            if ($item['pid'] == $pid) {
                return $this->processList[$key];
            }
        }
        return null;
    }

    /**
     * Undocumented function
     *
     * @param string $processName
     * @param AbstractProcess $process
     * @return void
     */
    public function setProcess(string $processName, AbstractProcess $process)
    {
        $key = md5($processName);
        $this->processList[$key] = $process;
    }

    /**
     * Undocumented function
     *
     * @param string $processName
     * @return boolean
     */
    public function reboot(string $processName) : bool
    {
        $p = $this->getProcessByName($processName);
        if ($p) {
            \swoole_process::kill($p->getPid(), SIGTERM);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $data
     * @return boolean
     */
    public function writeByProcessName(string $name, string $data) : bool
    {
        $process = $this->getProcessByName($name);
        if ($process) {
            return (bool)$process->getProcess()->write($data);
        } else {
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param float $timeOut
     * @return string|null
     */
    public function readByProcessName(string $name, float $timeOut = 0.1) : ? string
    {
        $process = $this->getProcessByName($name);
        if ($process) {
            $process = $process->getProcess();
            $read = array($process);
            $write = [];
            $error = [];
            $ret = swoole_select($read, $write, $error, $timeOut);
            if ($ret) {
                return $process->read(64 * 1024);
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

}