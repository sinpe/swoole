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

use Sinpe\Event\EventManager;

/**
 * 
 */
class DefaultEventsProvider
{
    /**
     * Undocumented variable
     *
     * @var ProcessManager
     */
    private $processManager;

    /**
     * Undocumented variable
     *
     * @var PoolManager
     */
    private $poolManager;

    /**
     * Undocumented function
     *
     * @param ProcessManager $processManager
     * @param PoolManager $poolManager
     */
    public function __construct(
        ProcessManager $processManager,
        PoolManager $poolManager
    ) {
        $this->processManager = $processManager;
        $this->poolManager = $poolManager;
    }

    /**
     * Register default services.
     *
     * @param 
     */
    public function register(Server $server, EventManager $eventManager)
    {
        $eventManager->attach(
            SwooleEvent::WORKER_START,
            function (\swoole_server $swooleServer, int $workerId) use ($server) {
                // $this->poolManager->__workerStartHook($workerId); // TODO
                $name = $server->workerNum();
                if (PHP_OS != 'Darwin') {
                    if ($workerId <= ($server->workerNum() - 1)) {
                        $name = "{$name}_Worker_" . $workerId;
                    } else {
                        $name = "{$name}_Task_Worker_" . $workerId;
                    }
                    cli_set_process_title($name);
                }
            }
        );

        $eventManager->clearListeners(SwooleEvent::TASK);

        $eventManager->attach(
            SwooleEvent::TASK,
            function (\swoole_server $server, $taskId, $fromWorkerId, $taskObj) {
                if (is_string($taskObj) && class_exists($taskObj)) {
                    $taskObj = new $taskObj;
                }
                if ($taskObj instanceof AbstractAsyncTask) {
                    try {
                        $ret = $taskObj->run($taskObj->getData(), $taskId, $fromWorkerId);
                        //在有return或者设置了结果的时候  说明需要执行结束回调
                        $ret = is_null($ret) ? $taskObj->getResult() : $ret;
                        if (!is_null($ret)) {
                            $taskObj->setResult($ret);
                            return $taskObj;
                        }
                    } catch (\Throwable $throwable) {
                        $taskObj->onException($throwable);
                    }
                } else if ($taskObj instanceof SuperClosure) {
                    try {
                        return $taskObj($server, $taskId, $fromWorkerId);
                    } catch (\Throwable $throwable) {
                        Trigger::throwable($throwable);
                    }
                }
                return null;
            }
        );

        $eventManager->clearListeners(SwooleEvent::FINISH);
        $eventManager->attach(
            SwooleEvent::FINISH,
            function (\swoole_server $server, $taskId, $taskObj) {
                //finish 在仅仅对AbstractAsyncTask做处理，其余处理无意义。
                if ($taskObj instanceof AbstractAsyncTask) {
                    try {
                        $taskObj->finish($taskObj->getResult(), $taskId);
                    } catch (\Throwable $throwable) {
                        $taskObj->onException($throwable);
                    }
                }
            }
        );

        // $eventManager->clearListeners(SwooleEvent::PIPE_MESSAGE);
        // $eventManager->attach(
        //     SwooleEvent::PIPE_MESSAGE,
        //     function (\swoole_server $server,$fromWorkerId,$data) {
        //         $message = unserialize($data);
        //         if($message instanceof Message){
        //             PipeMessageEventRegister::getInstance()->hook($message->getCommand(),$fromWorkerId,$message->getData());
        //         }else{
        //             Trigger::error("data :{$data} not packet by swoole_serialize or not a Message Instance");
        //         }
        //     }
        // );

        // // 数据库协程连接池
        // // @see https://www.easyswoole.com/Manual/2.x/Cn/_book/CoroutinePool/mysql_pool.html?h=pool
        // if (version_compare(phpversion('swoole'), '2.1.0', '>=')) {
        //     $this->poolManager->registerPool(MysqlPool2::class, 3, 10);
        // }

        // 普通事件注册 swoole 中的各种事件都可以按这个例子来进行注册
        // @see https://www.easyswoole.com/Manual/2.x/Cn/_book/Core/event_register.html
        $eventManager->attach(
            SwooleEvent::WORKER_START,
            function (\swoole_server $server, $workerId) {
                //为第一个进程添加定时器
                if ($workerId == 0) {
                    # 启动定时器
                    Timer::loop(10000, function () {
                        Logger::getInstance()->console('timer run');  # 写日志到控制台
                        $this->processManager->writeByProcessName('test', time());  # 向自定义进程发消息
                    });
                }
            }
        );

        // // 创建自定义进程 上面定时器中发送的消息 由 Test 类进行处理
        // // @see https://www.easyswoole.com/Manual/2.x/Cn/_book/Advanced/process.html
        // $this->processManager->addProcess('test', Test::class);
        // // 天天都在问的服务热重启 单独启动一个进程处理
        // $this->processManager->addProcess('autoReload', Inotify::class);

        // // WebSocket 以控制器的方式处理业务逻辑
        // // @see https://www.easyswoole.com/Manual/2.x/Cn/_book/Sock/websocket.html
        // EventHelper::registerDefaultOnMessage($eventManager, WebSock::class);

        // // 多端口混合监听
        // // @see https://www.easyswoole.com/Manual/2.x/Cn/_book/Event/main_server_create.html
        // // @see https://wiki.swoole.com/wiki/page/525.html
        // $tcp = $server->addServer('tcp', 9502);

        // # 第二参数为TCP控制器 和WS一样 都可以使用控制器方式来解析收到的报文并处理
        // # 第三参数为错误回调 可以不传入 当无法正确解析 或者是解析出来的控制器不在的时候会调用
        // EventHelper::registerDefaultOnReceive($tcp, Tcp::class, function ($errorType, $clientData, \EasySwoole\Core\Socket\Client\Tcp $client) {
        //     TaskManager::async(function () use ($client) {
        //         sleep(3);
        //         \EasySwoole\Core\Socket\Response::response($client, "Bye");
        //         $server->getServer()->close($client->getFd());
        //     });
        //     return "{$errorType} and going to close";
        // });

        // 自定义WS握手处理 可以实现在握手的时候 鉴定用户身份
        // @see https://wiki.swoole.com/wiki/page/409.html
        $eventManager->attach(
            SwooleEvent::HAND_SHAKE,
            function (\swoole_http_request $request, \swoole_http_response $response) {
                if (isset($request->cookie['token'])) {
                    $token = $request->cookie['token'];
                    if ($token == '123') {
                        // 如果取得 token 并且验证通过 则进入 ws rfc 规范中约定的验证过程
                        if (!isset($request->header['sec-websocket-key'])) {
                            // 需要 Sec-WebSocket-Key 如果没有拒绝握手
                            var_dump('shake fai1 3');
                            $response->end();
                            return false;
                        }
                        if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $request->header['sec-websocket-key'])
                            || 16 !== strlen(base64_decode($request->header['sec-websocket-key']))) {
                            //不接受握手
                            var_dump('shake fai1 4');
                            $response->end();
                            return false;
                        }

                        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                        $headers = array(
                            'Upgrade' => 'websocket',
                            'Connection' => 'Upgrade',
                            'Sec-WebSocket-Accept' => $key,
                            'Sec-WebSocket-Version' => '13',
                            'KeepAlive' => 'off',
                        );
                        foreach ($headers as $key => $val) {
                            $response->header($key, $val);
                        }
                        //接受握手  发送验证后的header   还需要101状态码以切换状态
                        $response->status(101);
                        var_dump('shake success at fd :' . $request->fd);
                        $response->end();
                    } else {
                        // 令牌不正确的情况 不接受握手
                        var_dump('shake fail 2');
                        $response->end();
                    }
                } else {
                    // 没有携带令牌的情况 不接受握手
                    var_dump('shake fai1 1');
                    $response->end();
                }
            }
        );
    }
}
