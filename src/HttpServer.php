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
use Sinpe\Swoole\Http\EnvironmentInterface;
use Sinpe\Swoole\LogAwareTrait;
use Sinpe\Slim\Exception;

/**
 * App
 *
 * This is the primary class with which you instantiate,
 * configure, and run a Swoole+ Framework application.
 * The \Sinpe\Swoole\Application class also accepts Swoole+ Framework middleware.
 *
 * @property-read callable $errorHandler
 * @property-read callable $phpErrorHandler
 * @property-read callable $notFoundHandler function($request, $response)
 * @property-read callable $notAllowedHandler function($request, $response, $allowedHttpMethods)
 */
class HttpServer extends Server
{
    use HttpAwareTrait;

    /**
     * __construct
     *
     * @param EnvironmentInterface $environment
     * 
     * @throws InvalidArgumentException when no container is provided that implements ContainerInterface
     */
    final public function __construct(
        EnvironmentInterface $environment
    ) {
        // set_exception_handler(
        //     function ($e) use ($request, $response) {
        //         $response = $this->handleException($e, $request, $response);
        //         $this->respond($response);
        //     }
        // );

        parent::__construct($environment);

        $this->registerRoutes();
    }

    /**
     * 初始化路由
     *
     * @return void
     */
    protected function registerRoutes()
    {
    }

    /**
     * 添加中间件，调度时间点分在application的invoke之前或之后
     *
     * @param  callable|string    $callable The callback routine
     *
     * @return static
     */
    public function before($callable)
    {
        return $this->pushToBefore(new DeferredCallable($callable, $this->container));
    }

    /**
     * 添加中间件，调度时间点分在application的invoke之前或之后
     *
     * @param  callable|string    $callable The callback routine
     * @param  boolean    $after 是否在kernel执行体之后的中间件，默认是在kernel执行体之前
     *
     * @return static
     */
    public function after($callable)
    {
        return $this->pushToAfter(new DeferredCallable($callable, $this->container));
    }

    /**
     * Add GET route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return \Sinpe\Route\RouteInterface
     */
    public function get($pattern, $callable)
    {
        return $this->map(['GET'], $pattern, $callable);
    }

    /**
     * Add POST route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return \Sinpe\Route\RouteInterface
     */
    public function post($pattern, $callable)
    {
        return $this->map(['POST'], $pattern, $callable);
    }

    /**
     * Add PUT route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return \Sinpe\Route\RouteInterface
     */
    public function put($pattern, $callable)
    {
        return $this->map(['PUT'], $pattern, $callable);
    }

    /**
     * Add PATCH route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return \Sinpe\Route\RouteInterface
     */
    public function patch($pattern, $callable)
    {
        return $this->map(['PATCH'], $pattern, $callable);
    }

    /**
     * Add DELETE route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return \Sinpe\Route\RouteInterface
     */
    public function delete($pattern, $callable)
    {
        return $this->map(['DELETE'], $pattern, $callable);
    }

    /**
     * Add OPTIONS route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return \Sinpe\Route\RouteInterface
     */
    public function options($pattern, $callable)
    {
        return $this->map(['OPTIONS'], $pattern, $callable);
    }

    /**
     * Add route for any HTTP method
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return \Sinpe\Route\RouteInterface
     */
    public function any($pattern, $callable)
    {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $callable);
    }

    /**
     * Add route with multiple methods
     *
     * @param  string[] $methods  Numeric array of HTTP method names
     * @param  string   $pattern  The route URI pattern
     * @param  callable|string    $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function map(array $methods, $pattern, $callable)
    {
        if ($callable instanceof Closure) {
            $callable = $callable->bindTo($this->container);
        }

        $route = $this->container->get('router')->map($methods, $pattern, $callable);

        if (is_callable([$route, 'setContainer'])) {
            $route->setContainer($this->container);
        }

        if (is_callable([$route, 'setOutputBuffering'])) {
            $route->setOutputBuffering($this->container->get('settings')['outputBuffering']);
        }

        return $route;
    }

    /**
     * Add a route that sends an HTTP redirect
     *
     * @param string              $from
     * @param string|UriInterface $to
     * @param int                 $status
     *
     * @return RouteInterface
     */
    public function redirect($from, $to, $status = 302)
    {
        $handler = function ($request, ResponseInterface $response) use ($to, $status) {
            return $response->withHeader('Location', (string)$to)->withStatus($status);
        };

        return $this->get($from, $handler);
    }

    /**
     * Route Groups
     *
     * This method accepts a route pattern and a callback. All route
     * declarations in the callback will be prepended by the group(s)
     * that it is in.
     *
     * @param string   $pattern
     * @param callable $callable
     *
     * @return GroupInterface
     */
    public function group($pattern, $callable)
    {
        /** @var Route\Group $group */
        $group = $this->container->get('router')->pushGroup($pattern, $callable);
        $group->setContainer($this->container);
        $group();
        $this->container->get('router')->popGroup();
        return $group;
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
        if (
            $this->serverType() != Server::TYPE_WEB_SERVER && 
            $this->serverType() != Server::TYPE_WEB_SOCKET_SERVER
        ) {
            throw new Exception(
                i18n(
                    'Server type must be %s or %s.',
                    Server::TYPE_WEB_SERVER,
                    Server::TYPE_WEB_SOCKET_SERVER
                )
            );
        }

        // $dispatcher = new Dispatcher($controllerNameSpace);

        $register->set(
            $register::onRequest,
            function (\swoole_http_request $request, \swoole_http_response $response)use($dispatcher) {

                // $request_psr = new Request($request);
                // $response_psr = new Response($response);

                // try{
                //     EasySwooleEvent::onRequest($request_psr,$response_psr);
                //     $dispatcher->dispatch($request_psr,$response_psr);
                //     EasySwooleEvent::afterAction($request_psr,$response_psr);
                // } catch (\Throwable $throwable) {
                //     $handler = Di::getInstance()->get(SysConst::HTTP_EXCEPTION_HANDLER);
                //     if($handler instanceof ExceptionHandlerInterface){
                //         $handler->handle($throwable,$request_psr,$response_psr);
                //     }else{
                //         $response_psr->withStatus(Status::CODE_INTERNAL_SERVER_ERROR);
                //         $response_psr->write(nl2br($throwable->getMessage() ."\n". $throwable->getTraceAsString()));
                //     }
                // }
                
                // $response_psr->response();
                
                try {
                    ob_start();
                    $response = $this->process($request, $response);
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
        );

        $this->getServer()->start();
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
     * Helper method, which returns true if the provided response must not output a body and false
     * if the response could have a body.
     *
     * @see https://tools.ietf.org/html/rfc7231
     *
     * @param ResponseInterface $response
     * @return bool
     */
    protected function isEmptyResponse(ResponseInterface $response)
    {
        if (method_exists($response, 'isEmpty')) {
            return $response->isEmpty();
        }

        return in_array($response->getStatusCode(), [204, 205, 304]);
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
}
