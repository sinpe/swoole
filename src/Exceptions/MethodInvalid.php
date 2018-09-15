<?php
/**
 * Swoole+ Framework (https://slimframework.com)
 *
 * @link      https://github.com/slimphp/Swoole
 * @copyright Copyright (c) 2011-2017 Josh Lockhart
 * @license   https://github.com/slimphp/Swoole/blob/3.x/LICENSE.md (MIT License)
 */
namespace Sinpe\Swoole\Exceptions;

use Psr\Http\Message\ServerRequestInterface;

class MethodInvalid extends \InvalidArgumentException
{
    protected $request;

    public function __construct(ServerRequestInterface $request, $method)
    {
        $this->request = $request;
        parent::__construct(i18n('Unsupported HTTP method "%s" provided', $method));
    }

    public function getRequest()
    {
        return $this->request;
    }
}
