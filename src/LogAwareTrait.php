<?php
/*
 * This file is part of the long/slim package.
 *
 * (c) Sinpe <support@sinpe.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sinpe\Slim;

use Psr\Log\LoggerInterface;
use Sinpe\Support\Traits\LogAware;

/**
 * 扩展写日志能力.
 */
trait LogAwareTrait
{
    use LogAware;

    /**
     * 获取logger对象
     *
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->container->get(LoggerInterface::class);
    }
}
