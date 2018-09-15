<?php
/**
 * Swoole+ Framework (https://slimframework.com)
 *
 * @link      https://github.com/slimphp/Swoole
 * @copyright Copyright (c) 2011-2017 Josh Lockhart
 * @license   https://github.com/slimphp/Swoole/blob/3.x/LICENSE.md (MIT License)
 */
namespace Sinpe\Swoole\Http;

/**
 * Headers Interface
 *
 * @package Sinpe\Swoole
 * @since   3.0.0
 */
interface HeadersInterface
{
    public function add($key, $value);

    public function normalizeKey($key);
}
