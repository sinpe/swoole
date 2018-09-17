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


use EasySwoole\Config;
use EasySwoole\Core\Component\Pool\AbstractInterface\Pool;
use EasySwoole\Core\Swoole\Memory\TableManager;
use Swoole\Table;

class PoolManager
{
    private $poolTable = null;
    private $poolClassList = [];
    private $poolObjectList = [];

    const TYPE_ONLY_WORKER = 1;
    const TYPE_ONLY_TASK_WORKER = 2;
    const TYPE_ALL_WORKER = 3;

    /**
     * __construct
     */
    public function __construct()
    {
        // TODO 临时
        // TableManager::getInstance()->add('__PoolManager', [
        //     'createNum' => ['type' => Table::TYPE_INT, 'size' => 3]
        // ], 8192);
        // $this->poolTable = TableManager::getInstance()->get('__PoolManager');

        // $conf = Config::getInstance()->getConf('POOL_MANAGER');
        // if (is_array($conf)) {
        //     foreach ($conf as $class => $item) {
        //         $this->registerPool($class, $item['min'], $item['max'], $item['type']);
        //     }
        // }
    }

    /**
     * Undocumented function
     *
     * @param string $class
     * @param [type] $minNum
     * @param [type] $maxNum
     * @param [type] $type
     * @return void
     */
    public function registerPool(string $class, $minNum, $maxNum, $type = self::TYPE_ONLY_WORKER)
    {
        $ref = new \ReflectionClass($class);
        
        if ($ref->isSubclassOf(Pool::class)) {
            $this->poolClassList[$class] = [
                'min' => $minNum,
                'max' => $maxNum,
                'type' => $type
            ];
            return true;
        } else {
            throw new Exception($class . ' is not Pool class');
        }
    
        return false;
    }

    /**
     * Undocumented function
     *
     * @param string $class
     * @return Pool|null
     */
    public function getPool(string $class) : ? Pool
    {
        if (isset($this->poolObjectList[$class])) {
            return $this->poolObjectList[$class];
        } else {
            return null;
        }
    }

    /*
     * 为自定义进程预留
     */
    public function __workerStartHook($workerId)
    {
        $workerNum = Config::getInstance()->getConf('server.worker_number');
        foreach ($this->poolClassList as $class => $item) {
            if ($item['type'] === self::TYPE_ONLY_WORKER) {
                if ($workerId > ($workerNum - 1)) {
                    continue;
                }
            } else if ($item['type'] === self::TYPE_ONLY_TASK_WORKER) {
                if ($workerId <= ($workerNum - 1)) {
                    continue;
                }
            }
            $key = self::generateTableKey($class, $workerId);
            $this->poolTable->del($key);
            $this->poolObjectList[$class] = new $class($item['min'], $item['max'], $key);
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getPoolTable()
    {
        return $this->poolTable;
    }

    /**
     * Undocumented function
     *
     * @param string $class
     * @param integer $workerId
     * @return string
     */
    public static function generateTableKey(string $class, int $workerId) : string
    {
        return substr(md5($class . $workerId), 8, 16);
    }

}