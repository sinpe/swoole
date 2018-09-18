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

class Swoole
{
    const VERSION = SWOOLE_VERSION;

    const RUNMODEL_SWOOLE_PROCESS = SWOOLE_PROCESS;
    const RUNMODEL_SWOOLE_BASE = SWOOLE_BASE;

    const SOCKTYPE_TCP = SWOOLE_TCP;
    const SOCKTYPE_UDP = SWOOLE_UDP;
    const SOCKTYPE_TCP6 = SWOOLE_TCP6;
    const SOCKTYPE_UDP6 = SWOOLE_UDP6;
    const SOCKTYPE_UNIX_STREAM = SWOOLE_UNIX_STREAM;
    const SOCKTYPE_UNIX_DGRAM = SWOOLE_UNIX_DGRAM;
    const SOCKTYPE_SSL = SWOOLE_SSL;

    const CLIENT_SOCK_TCP = SWOOLE_SOCK_TCP;
    const CLIENT_SOCK_UDP = SWOOLE_SOCK_UDP;
    const CLIENT_SOCK_TCP6 = SWOOLE_SOCK_TCP6;
    const CLIENT_SOCK_UDP6 = SWOOLE_SOCK_UDP6;
    const CLIENT_SOCK_SYNC = SWOOLE_SOCK_SYNC;
    const CLIENT_SOCK_ASYNC = SWOOLE_SOCK_ASYNC;

}