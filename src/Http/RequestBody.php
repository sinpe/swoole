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
 * Provides a PSR-7 implementation of a reusable raw request body
 */
class RequestBody extends Body
{
    /**
     * Create a new RequestBody.
     */
    public function __construct()
    {
        $stream = fopen('php://temp', 'w+');
        stream_copy_to_stream(fopen('php://input', 'r'), $stream);
        rewind($stream);

        parent::__construct($stream);
    }
}
