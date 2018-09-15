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
 * Environment Interface
 *
 * @package Sinpe\Swoole
 * @since   3.0.0
 */
interface EnvironmentInterface
{
    public static function mock(array $settings = []);
}
