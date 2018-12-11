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

use Exception;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

use Swoole\Exception as SwooleException;
use Psr\Container\ContainerInterface;

use Sinpe\Event\EventManager;
use Sinpe\Event\EventManagerInterface;

/**
 * App
 *
 * This is the primary class with which you instantiate and run a application.
 * The \Sinpe\Swoole\Server class also accepts middlewares and router.
 *
 * @property-read callable $errorHandler
 * @property-read callable $phpErrorHandler
 * @property-read callable $notFoundHandler function($request, $response)
 * @property-read callable $notAllowedHandler function($request, $response, $allowedHttpMethods)
 */
class Server implements ServerInterface
{
    const TYPE_SERVER = 1;
    const TYPE_HTTP = 2;
    const TYPE_WEB_SOCKET = 3;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     * 服务器类型
     *
     * @var string
     */
    private $serverType;

    /**
     * Swoole主服务
     *
     * @var \swoole_server
     */
    private $swooleServer;

    private $isStart = false;

    /**
     * 
     *
     * @var \swoole_server[]
     */
    private $moreSwooleServers = [];

    /**
     * __construct
     *
     * @throws InvalidArgumentException when no container is provided that implements ContainerInterface
     */
    public function __construct(string $serverType)
    {
        $this->serverType = $serverType;

        // set_exception_handler(
        //     function ($e) {
        //         $this->handleException($e);
        //     }
        // );

        $container = $this->generateContainer();

        $container->set(ServerInterface::class, $this);

        if (!$container instanceof ContainerInterface) {
            throw new InvalidArgumentException(
                i18n(
                    'Expected a %s.',
                    ContainerInterface::class
                )
            );
        }

        $container->set('setting', new Setting());

        $this->container = $container;
        $this->eventManager = $this->generateEventManager();
        
        // 生命周期函数initialize
        $this->initialize();

        $this->swooleServer = $this->createServer();
        
        // Cache::getInstance(); // TODO
        // Cluster::getInstance()->run();// TODO
        // CronTab::getInstance()->run();// TODO

        $this->addSwooleListener();

        $this->isStart = true;
    }

    /**
     * 设置、修改默认设置项的值
     *
     * @return void
     */
    protected function setSetting(string $key, $value)
    {
        $this->container->get('setting')->put($key, $value);
    }
    
    /**
     * Server Type
     *
     * @return void
     */
    public function serverType()
    {
        return $this->serverType;
    }

    /**
     * 创建swoole
     *
     * @return void
     */
    protected function createServer()
    {
        // TODO add options here
        throw new Exception('Please override me and add options here.');

        return $this->createSwooleServer($host, $port, $runModel, $sockType, $settings);
    }

    /**
     * 打印logo
     *
     * @return void
     */
    protected function showLogo()
    {
        echo file_get_contents('../LOGO');
    }

    /**
     * initialize
     * 
     * 需要额外的初始化，覆盖此方法
     *
     * @return void
     */
    protected function initialize()
    {
        // TODO 子类扩展
    }

    /**
     * 
     * 
     * @return void
     */
    protected function displayServerInfo()
    {
    }

    /**
     * 穿件注入容器，如果采用其他实现，可以在子类覆盖此方法
     * 
     * @return ContainerInterface
     */
    protected function generateContainer()
    {
        return Container::getInstance();
    }

    /**
     * 创建事件管理器，如果采用其他实现，可以在子类覆盖此方法
     * 
     * @return EventManagerInterface
     */
    protected function generateEventManager()
    {
        $eventManager = new EventManager();

        $this->container->set(EventManagerInterface::class, $eventManager);

        return $eventManager;
    }

    /**
     * 
     */
    public function addSwooleServer(
        string $name,
        int $port,
        int $type = Swoole::SOCKTYPE_TCP,
        string $host = '0.0.0.0',
        array $setting = [
            "open_eof_check" => false,
        ]
    ) {

        $eventManager = new EventManager();

        $this->moreSwooleServers[$name] = [
            'port' => $port,
            'host' => $host,
            'type' => $type,
            'setting' => $setting,
            'eventManager' => $eventManager
        ];

        return $eventManager;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    private function addSwooleListener() : void
    {
        foreach ($this->moreSwooleServers as $name => $server) {

            $subPort = $this->swooleServer->addlistener(
                $server['host'],
                $server['port'],
                $server['type']
            );

            if ($subPort) {

                $this->moreSwooleServers[$name] = $subPort;

                if (is_array($server['setting'])) {
                    $subPort->set($server['setting']);
                }

                $eventManager = $server['eventManager'];

                $reflect = new ReflectionClass(SwooleEvent::class);

                foreach ($reflect->getConstants() as $event) {
                    $subPort->on(
                        $event,
                        function () use ($eventManager) {
                            return $eventManager->fire($event, $this, func_get_args());
                        }
                    );
                }

            } else {
                throw new Exception(
                    i18n(
                        'addListener with server name:%s at host:%s port:%s fail.',
                        $name,
                        $server['host'],
                        $server['port']
                    )
                );
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param array $options
     * @return void
     */
    protected function createSwooleServer(
        string $host,
        string $port,
        string $runModel,
        string $sockType,
        array $settings
    ) {

        switch ($this->serverType()) {
            case self::TYPE_SERVER:
                $swooleServer = new \swoole_server(
                    $host,
                    $port,
                    $runModel,
                    $sockType
                );
                break;
            case self::TYPE_HTTP:
                $swooleServer = new \swoole_http_server(
                    $host,
                    $port,
                    $runModel,
                    $sockType
                );
                break;
            case self::TYPE_WEB_SOCKET:
                $swooleServer = new \swoole_websocket_server(
                    $host,
                    $port,
                    $runModel,
                    $sockType
                );
                break;
            default:
                throw new Exception(
                    i18n(
                        'Unknown server type "%s"',
                        $this->serverType()
                    )
                );
        }

        $swooleServer->set($settings);

        // 默认事件
        $this->container->make(DefaultEventsProvider::class)
            ->register($this, $this->eventManager);

        $reflect = new ReflectionClass(SwooleEvent::class);

        foreach ($reflect->getConstants() as $event) {
            $swooleServer->on(
                $event,
                function () {
                    return $this->eventManager->fire($event, $this, func_get_args());
                }
            );
        }

        return $swooleServer;
    }

    /**
     * @param string $name
     * @return null|\swoole_server|\swoole_server_port
     */
    public function getSwooleServer($name = null)
    {
        if ($this->swooleServer) {
            if ($name === null) {
                return $this->swooleServer;
            } else {
                if (isset($this->moreSwooleServers[$name])) {
                    return $this->moreSwooleServers[$name];
                }
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @return integer|null
     */
    public function coroutineId() : ? int
    {
        if (class_exists('Swoole\Coroutine')) {
            //进程错误或不在协程中的时候返回-1
            $result = \Swoole\Coroutine::getuid();

            if ($result >= 0) {
                return $result;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isCoroutine() : bool
    {
        if ($this->coroutineId() !== null) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isStart() : bool
    {
        return $this->isStart;
    }

    /**
     * __get
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (in_array($name, ['container', 'eventManager'])) {
            return $this->{$name};
        }

        throw new Exception(
            i18n(
                'Property %s::%s not exist.',
                get_class($this),
                $name
            )
        );
    }

    /**
     * 额外的
     * 
     * @param  Throwable $e
     *
     * @return void
     */
    protected function HandleYours(Throwable $e)
    {
    }

    /**
     * Call relevant handler from the Container if needed. If it doesn't exist,
     * then just re-throw.
     *
     * @param  Throwable $e
     *
     * @throws Exception if a handler is needed and not found
     */
    protected function handleException(Throwable $e)
    {
        $this->HandleYours($e);

        echo $e->getMessage() . '(' . $e->getCode() . ")\r\n\r\n";

        // // Other exception, use $request and $response params
        // $handler = 'errorHandler';
        // $params = [$e];

        // $container = $this->container;

        // if ($container->has($handler)) {
        //     $callable = $container->get($handler);
        //     // Call the registered handler
        //     return call_user_func_array($callable, $params);
        // }

        // // No handlers found, so just throw the exception
        // throw $e;
    }

}
