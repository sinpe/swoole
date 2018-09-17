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

use Closure;
use Exception;
use InvalidArgumentException;
use Throwable;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Sinpe\IOC\ContainerInterface;
use Sinpe\Middleware\CallableStrategies\Deferred as DeferredCallable;
use Sinpe\Middleware\HttpAwareTrait;
use Sinpe\Route\GroupInterface;
use Sinpe\Route\RouteInterface;
use Sinpe\Route\RouterInterface;
use Sinpe\Route\Dispatcher;

use Sinpe\Swoole\Exceptions\MethodInvalid;
use Sinpe\Swoole\Http\Response;
use Sinpe\Swoole\Exception as SwooleException;
use Sinpe\Swoole\Exceptions\MethodNotAllowed;
use Sinpe\Swoole\Exceptions\RouteNotFound;
use Sinpe\Swoole\Http\Uri;
use Sinpe\Swoole\Http\Headers;
use Sinpe\Swoole\Http\Body;
use Sinpe\Swoole\Http\Request;
use Sinpe\Swoole\LogAwareTrait;

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
class Server
{
    use LogAwareTrait;

    const TYPE_SERVER = 1;

    const TYPE_HTTP = 2;
    const TYPE_WEB_SOCKET = 3;

    /**
     * Container
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * 服务器类型
     *
     * @var string
     */
    private $serverType;

    /**
     * 运行参数
     *
     * @var array
     */
    private $options;

    private $servers = [];
    private $mainServer = null;
    private $isStart = false;

    /**
     * __construct
     *
     * @throws InvalidArgumentException when no container is provided that implements ContainerInterface
     */
    public function __construct(
        string $serverType
    ) {
        $this->serverType = $serverType;

        // set_exception_handler(
        //     function ($e) use ($request, $response) {
        //         $response = $this->handleException($e, $request, $response);
        //         $this->respond($response);
        //     }
        // );

        $this->showLogo();

        $container = $this->generateContainer();

        if (!$container instanceof ContainerInterface) {
            throw new InvalidArgumentException('Expected a ContainerInterface');
        }

        $this->container = $container;
        
        // 生命周期函数initialize
        $this->initialize();

        $this->createServer();

        // Cache::getInstance(); // TODO
        // Cluster::getInstance()->run();// TODO
        // CronTab::getInstance()->run();// TODO

        $this->attachListener();

        $this->isStart = true;
    }

    /**
     * 
     *
     * @return void
     */
    public function setOptions(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * 应用名称
     *
     * @return void
     */
    public function name()
    {
        return !empty($this->options['name']) ? $this->options['name'] : 'unknow';
    }

    /**
     * 主机
     *
     * @return void
     */
    public function host()
    {
        return !empty($this->options['host']) ? $this->options['host'] : '127.0.0.1';
    }

    /**
     * 端口
     *
     * @return void
     */
    public function port()
    {
        return !empty($this->options['port']) ? $this->options['port'] : '8080';
    }

    /**
     * worker数
     *
     * @return void
     */
    public function workerNum()
    {
        return !empty($this->options['worker_num']) ? $this->options['worker_num'] : 2;
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

        $this->createMainServer($options);
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
    }

    /**
     * create container
     * 
     * 需要替换默认的container，覆盖此方法
     *
     * @return ContainerInterface
     */
    protected function generateContainer()
    {
        return new Container();
    }

    /**
     * Enable access to the DI container by consumers of $app
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Run application
     *
     * This method traverses the application middleware stack and then sends the
     * resultant Response object to the HTTP client.
     *
     * @param bool|false $silent
     * @return ResponseInterface
     *
     * @throws Exception
     * @throws MethodNotAllowed
     * @throws RouteNotFound
     */
    public function run($silent = false)
    {
        $this->getServer()->start();

        $response = $this->response;

        try {
            ob_start();
            $response = $this->process($this->request, $response);
        } catch (MethodInvalid $e) {
            $response = $this->processInvalidMethod($e->getRequest(), $response);
        }
        finally {
            $output = ob_get_clean();
        }

        if (!empty($output) && $response->getBody()->isWritable()) {
            $outputBuffering = $this->container->get('settings')['outputBuffering'];
            if ($outputBuffering === 'prepend') {
                // prepend output buffer content
                $body = new Http\Body(fopen('php://temp', 'r+'));
                $body->write($output . $response->getBody());
                $response = $response->withBody($body);
            } elseif ($outputBuffering === 'append') {
                // append output buffer content
                $response->getBody()->write($output);
            }
        }

        $response = $this->finalize($response);

        if (!$silent) {
            $this->respond($response);
        }

        return $response;
    }

    /**
     * Pull route info for a request with a bad method to decide whether to
     * return a not-found error (default) or a bad-method error, then run
     * the handler for that error, returning the resulting response.
     *
     * Used for cases where an incoming request has an unrecognized method,
     * rather than throwing an exception and not catching it all the way up.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function processInvalidMethod(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $router = $this->container->get('router');
        if (is_callable([$request->getUri(), 'getBasePath']) && is_callable([$router, 'setBasePath'])) {
            $router->setBasePath($request->getUri()->getBasePath());
        }

        $request = $this->dispatchRouterAndPrepareRoute($request, $router);
        $routeInfo = $request->getAttribute('routeInfo', [RouterInterface::DISPATCH_STATUS => Dispatcher::NOT_FOUND]);

        if ($routeInfo[RouterInterface::DISPATCH_STATUS] === Dispatcher::METHOD_NOT_ALLOWED) {
            return $this->handleException(
                new MethodNotAllowed($request, $response, $routeInfo[RouterInterface::ALLOWED_METHODS]),
                $request,
                $response
            );
        }

        return $this->handleException(new RouteNotFound($request, $response), $request, $response);
    }

    /**
     * Process a request
     *
     * This method traverses the application middleware stack and then returns the
     * resultant Response object.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     *
     * @throws Exception
     * @throws MethodNotAllowed
     * @throws RouteNotFound
     */
    public function process(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        // Ensure basePath is set
        $router = $this->container->get('router');

        if (is_callable([$request->getUri(), 'getBasePath']) && is_callable([$router, 'setBasePath'])) {
            $router->setBasePath($request->getUri()->getBasePath());
        }

        // Dispatch router (note: you won't be able to alter routes after this)
        $request = $this->dispatchRouterAndPrepareRoute($request, $router);

        // Traverse middleware stack
        try {
            $response = $this->callMiddlewareStack($request, $response);
        } catch (Exception $e) {
            $response = $this->handleException($e, $request, $response);
        } catch (Throwable $e) {
            $response = $this->handlePhpError($e, $request, $response);
        }

        return $response;
    }

    /**
     * Send the response to the client
     *
     * @param ResponseInterface $response
     */
    public function respond(ResponseInterface $response)
    {
        // Send response
        if (!headers_sent()) {
            // Headers
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(i18n('%s: %s', $name, $value), false);
                }
            }

            // Set the status _after_ the headers, because of PHP's "helpful" behavior with location headers.
            // See https://github.com/slimphp/Swoole/issues/1730

            // Status
            header(i18n(
                'HTTP/%s %s %s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
        }

        // Body
        if (!$this->isEmptyResponse($response)) {
            $body = $response->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $settings = $this->container->get('settings');
            $chunkSize = $settings['responseChunkSize'];

            $contentLength = $response->getHeaderLine('Content-Length');
            if (!$contentLength) {
                $contentLength = $body->getSize();
            }


            if (isset($contentLength)) {
                $amountToRead = $contentLength;
                while ($amountToRead > 0 && !$body->eof()) {
                    $data = $body->read(min($chunkSize, $amountToRead));
                    echo $data;

                    $amountToRead -= strlen($data);

                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }
            } else {
                while (!$body->eof()) {
                    echo $body->read($chunkSize);
                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Invoke application
     *
     * This method implements the middleware interface. It receives
     * Request and Response objects, and it returns a Response object
     * after compiling the routes registered in the Router and dispatching
     * the Request object to the appropriate Route callback routine.
     *
     * @param  ServerRequestInterface $request  The most recent Request object
     * @param  ResponseInterface      $response The most recent Response object
     *
     * @return ResponseInterface
     * @throws MethodNotAllowed
     * @throws RouteNotFound
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        // Get the route info
        $routeInfo = $request->getAttribute('routeInfo');

        /** @var \Sinpe\Route\RouterInterface $router */
        $router = $this->container->get('router');

        // If router hasn't been dispatched or the URI changed then dispatch
        if (null === $routeInfo
            || ($routeInfo['request'] !== [$request->getMethod(), (string)$request->getUri()])) {
            $request = $this->dispatchRouterAndPrepareRoute($request, $router);
            $routeInfo = $request->getAttribute('routeInfo');
        }

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $route = $router->lookupRoute($routeInfo[1]);
            return $route->run($request, $response);
        } elseif ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            // if (!$this->container->has('notAllowedHandler')) {
            throw new MethodNotAllowed($request, $response, $routeInfo[1]);
            // }
            // /** @var callable $notAllowedHandler */
            // $notAllowedHandler = $this->container->get('notAllowedHandler');

            // return $notAllowedHandler($request, $response, $routeInfo[1]);
        }
        
        // if (!$this->container->has('notFoundHandler')) {
        throw new RouteNotFound($request, $response);
        // }
        
        // /** @var callable $notFoundHandler */
        // $notFoundHandler = $this->container->get('notFoundHandler');
        
        // return $notFoundHandler($request, $response);
    }

    /**
     * Perform a sub-request from within an application route
     *
     * This method allows you to prepare and initiate a sub-request, run within
     * the context of the current request. This WILL NOT issue a remote HTTP
     * request. Instead, it will route the provided URL, method, headers,
     * cookies, body, and server variables against the set of registered
     * application routes. The result response object is returned.
     *
     * @param  string            $method      The request method (e.g., GET, POST, PUT, etc.)
     * @param  string            $path        The request URI path
     * @param  string            $query       The request URI query string
     * @param  array             $headers     The request headers (key-value array)
     * @param  array             $cookies     The request cookies (key-value array)
     * @param  string            $bodyContent The request body
     * @param  ResponseInterface $response     The response object (optional)
     * @return ResponseInterface
     */
    public function subRequest(
        $method,
        $path,
        $query = '',
        array $headers = [],
        array $cookies = [],
        $bodyContent = '',
        ResponseInterface $response = null
    ) {
        $env = $this->container->get('environment');
        $uri = Uri::createFromEnvironment($env)->withPath($path)->withQuery($query);
        $headers = new Headers($headers);
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $body->write($bodyContent);
        $body->rewind();
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        if (!$response) {
            $response = $this->response;
        }

        return $this($request, $response);
    }

    /**
     * Dispatch the router to find the route. Prepare the route for use.
     *
     * @param ServerRequestInterface $request
     * @param RouterInterface        $router
     * @return ServerRequestInterface
     */
    protected function dispatchRouterAndPrepareRoute(ServerRequestInterface $request, RouterInterface $router)
    {
        $routeInfo = $router->dispatch($request);

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $routeArguments = [];
            foreach ($routeInfo[2] as $k => $v) {
                $routeArguments[$k] = urldecode($v);
            }

            $route = $router->lookupRoute($routeInfo[1]);
            $route->prepare($request, $routeArguments);

            // add route to the request's attributes in case a middleware or handler needs access to the route
            $request = $request->withAttribute('route', $route);
        }

        $routeInfo['request'] = [$request->getMethod(), (string)$request->getUri()];

        return $request->withAttribute('routeInfo', $routeInfo);
    }

    /**
     * Finalize response
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function finalize(ResponseInterface $response)
    {
        // stop PHP sending a Content-Type automatically
        ini_set('default_mimetype', '');

        if ($this->isEmptyResponse($response)) {
            return $response->withoutHeader('Content-Type')->withoutHeader('Content-Length');
        }

        // Add Content-Length header if `addContentLengthHeader` setting is set
        if (isset($this->container->get('settings')['addContentLengthHeader']) &&
            $this->container->get('settings')['addContentLengthHeader'] == true) {
            if (ob_get_length() > 0) {
                throw new \RuntimeException("Unexpected data in output buffer. " .
                    "Maybe you have characters before an opening <?php tag?");
            }
            $size = $response->getBody()->getSize();
            if ($size !== null && !$response->hasHeader('Content-Length')) {
                $response = $response->withHeader('Content-Length', (string)$size);
            }
        }

        return $response;
    }

    /**
     * 额外的
     * 
     * @param  Exception $e
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     *
     * @return void
     */
    protected function HandleYours(Exception $e, ServerRequestInterface $request, ResponseInterface $response)
    {
    }

    /**
     * Call relevant handler from the Container if needed. If it doesn't exist,
     * then just re-throw.
     *
     * @param  Exception $e
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     *
     * @return ResponseInterface
     * @throws Exception if a handler is needed and not found
     */
    protected function handleException(Exception $e, ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->HandleYours($e, $request, $response);

        if ($e instanceof MethodNotAllowed) {
            $handler = 'notAllowedHandler';
            $params = [$e->getRequest(), $e->getResponse(), $e->getAllowedMethods()];
        } elseif ($e instanceof RouteNotFound) {
            $handler = 'notFoundHandler';
            $params = [$e->getRequest(), $e->getResponse(), $e];
        } elseif ($e instanceof SwooleException) {
            // This is a Stop exception and contains the response
            return $e->getResponse();
        } else {
            // Other exception, use $request and $response params
            $handler = 'errorHandler';
            $params = [$request, $response, $e];
        }

        if ($this->container->has($handler)) {
            $callable = $this->container->get($handler);
            // Call the registered handler
            return call_user_func_array($callable, $params);
        }

        // No handlers found, so just throw the exception
        throw $e;
    }

    /**
     * Call relevant handler from the Container if needed. If it doesn't exist,
     * then just re-throw.
     *
     * @param  Throwable $e
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     * @return ResponseInterface
     * @throws Throwable
     */
    protected function handlePhpError(Throwable $e, ServerRequestInterface $request, ResponseInterface $response)
    {
        $handler = 'phpErrorHandler';
        $params = [$request, $response, $e];

        if ($this->container->has($handler)) {
            $callable = $this->container->get($handler);
            // Call the registered handler
            return call_user_func_array($callable, $params);
        }

        // No handlers found, so just throw the exception
        throw $e;
    }

    /**
     * 
     */
    public function addServer(
        string $name,
        int $port,
        int $type = SWOOLE_TCP,
        string $host = '0.0.0.0',
        array $setting = [
            "open_eof_check" => false,
        ]
    ) : Event {

        $eventRegister = new Event();

        $this->servers[$name] = [
            'port' => $port,
            'host' => $host,
            'type' => $type,
            'setting' => $setting,
            'eventRegister' => $eventRegister
        ];

        return $eventRegister;
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
     * Undocumented function
     *
     * @return void
     */
    private function attachListener() : void
    {
        $mainServer = $this->getServer();

        foreach ($this->servers as $name => $server) {

            $subPort = $mainServer->addlistener($server['host'], $server['port'], $server['type']);

            if ($subPort) {
                $this->servers[$name] = $subPort;

                if (is_array($server['setting'])) {
                    $subPort->set($server['setting']);
                }

                $events = $server['eventRegister']->all();

                foreach ($events as $event => $callback) {
                    $subPort->on($event, function () use ($callback) {
                        $ret = [];
                        $args = func_get_args();
                        foreach ($callback as $item) {
                            array_push($ret, Invoker::callUserFuncArray($item, $args));
                        }
                        if (count($ret) > 1) {
                            return $ret;
                        }
                        return array_shift($ret);
                    });
                }
            } else {
                throw new Exception("addListener with server name:{$name} at host:{$server['host']} port:{$server['port']} fail");
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param array $options
     * @return void
     */
    protected function createMainServer(array $options) : \swoole_server
    {
        $host = $options['host'];
        $port = $options['port'];
        $runModel = $options['run_model'];
        $sockType = $options['sock_type'];

        switch ($options['server_type']) {
            case self::TYPE_SERVER:
                $this->mainServer = new \swoole_server($host, $port, $runModel, $sockType);
                break;
            case self::TYPE_HTTP:
                $this->mainServer = new \swoole_http_server($host, $port, $runModel, $sockType);
                break;
            case self::TYPE_WEB_SOCKET:
                $this->mainServer = new \swoole_websocket_server($host, $port, $runModel, $sockType);
                break;
            default:
                throw new Exception(
                    i18n(
                        'Unknown server type "%s"',
                        $options['server_type']
                    )
                );
        }

        $this->mainServer->set($options['swoole_settings']);

        $defaultEvents = new DefaultEventsProvider();

        $defaultEvents->register($this, $this->eventManager);

        $reflect = new ReflectionClass(SwooleEvent::class);

        foreach ($reflect->getConstants() as $event) {
            $this->mainServer->on(
                $event,
                function () {
                    return $this->eventManager->fire($event, $this, func_get_args());
                }
            );
        }

        return $this->mainServer;
    }

    /**
     * @param string $name
     * @return null|\swoole_server|\swoole_server_port
     */
    public function getServer($name = null)
    {
        if ($this->mainServer) {
            if ($name === null) {
                return $this->mainServer;
            } else {
                if (isset($this->servers[$name])) {
                    return $this->servers[$name];
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
            $ret = Coroutine::getuid();
            if ($ret >= 0) {
                return $ret;
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

}
