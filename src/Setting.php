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

use Illuminate\Support\Collection;

/**
 * 设置
 */
class Setting extends Collection
{
    /**
     * The source data
     *
     * @var array
     */
    protected $items = [
        'responseChunkSize' => 4096,
        'outputBuffering' => 'append',
        'displayErrorDetails' => false,
        'addContentLengthHeader' => true,
        'routerCacheFile' => false,
    ];
}
