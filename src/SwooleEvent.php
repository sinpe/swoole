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

class SwooleEvent
{
    const START = 'start';
    const SHUTDOWN = 'shutdown';
    const WORKER_START = 'workerStart';
    const WORKER_STOP = 'workerStop';
    const WORKER_EXIT = 'workerExit';
    const TIMER = 'timer';
    const CONNECT = 'connect';
    const RECEIVE = 'receive';
    const PACKET = 'packet';
    const CLOSE = 'close';
    const BUFFER_FULL = 'bufferFull';
    const BUFFER_EMPTY = 'bufferEmpty';
    const TASK = 'task';
    const FINISH = 'finish';
    const PIPE_MESSAGE = 'pipeMessage';
    const WORKER_ERROR = 'workerError';
    const MANAGER_START = 'managerStart';
    const MANAGER_STOP = 'managerStop';
    const REQUEST = 'request';
    const HAND_SHAKE = 'handShake';
    const MESSAGE = 'message';
    const OPEN = 'open';
}