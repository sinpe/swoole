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

use Sinpe\Container\Container as Base;

/**
 * Default DI container.
 *
 * with these service keys configured and ready for use:
 *
 *  - settings: an array or instance of \ArrayAccess
 *  - environment: an instance of \Swoole\Http\EnvironmentInterface
 *  - request: an instance of \Psr\Http\Message\ServerRequestInterface
 *  - response: an instance of \Psr\Http\Message\ResponseInterface
 *  - errorHandler: a callable with the signature: function($request, $response, $exception)
 *  - notFoundHandler: a callable with the signature: function($request, $response)
 *  - notAllowedHandler: a callable with the signature: function($request, $response, $allowedHttpMethods)
 *  - callableResolver: an instance of \Sinpe\Middleware\CallableResolverInterface
 *
 * @property-read \Swoole\Http\EnvironmentInterface environment
 * @property-read \Psr\Http\Message\ServerRequestInterface request
 * @property-read \Psr\Http\Message\ResponseInterface response
 * @property-read callable errorHandler
 * @property-read callable notFoundHandler
 * @property-read callable notAllowedHandler
 * @property-read \Sinpe\Middleware\CallableResolverInterface callableResolver
 */
class Container extends Base
{
    /**
     * This function registers the default services that needs to work.
     *
     * All services are shared - that is, they are registered such that the
     * same instance is returned on subsequent calls.
     *
     * @param array $userSettings Associative array of application settings
     *
     * @return void
     */
    protected function registerDefaults()
    {
        $this->register(new DefaultServicesProvider());
    }

}
