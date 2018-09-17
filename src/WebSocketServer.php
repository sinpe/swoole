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
class WebSocketServer extends Server
{
    use HttpAwareTrait;

    /**
     * __construct
     *
     * @param EnvironmentInterface $environment
     * 
     * @throws InvalidArgumentException when no container is provided that implements ContainerInterface
     */
    public function __construct(
        EnvironmentInterface $environment
    ) {
        // set_exception_handler(
        //     function ($e) use ($request, $response) {
        //         $response = $this->handleException($e, $request, $response);
        //         $this->respond($response);
        //     }
        // );

        parent::__construct($environment, Server::TYPE_WEB_SOCKET);

        $this->registerRoutes();
    }

    
}
