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
    public function __construct($method)
    {
        parent::__construct(i18n('Unsupported HTTP method "%s" provided', $method));
    }

}
