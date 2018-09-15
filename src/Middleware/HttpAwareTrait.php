<?php

namespace Sinpe\Swoole\Middleware;

use Sinpe\Middleware\AwareTrait;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HttpAwareTrait
 */
trait HttpAwareTrait
{
    use AwareTrait;

    /**
     * 检查流入对象，利用参数声明校验，无其他额外校验
     *
     * @param ServerRequestInterface $request  请求
     * @param ResponseInterface      $response 响应
     *
     * @return boolean
     */
    protected function checkFeed(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        return true;
    }

    /**
     * 检查结果
     *
     * @param array $result 中间件返回结果.
     *
     * @return void
     */
    protected function checkReturn($result)
    {
        // 返回[ServerRequestInterface $req, ResponseInterface $res]
        if (!is_array($result) ||
            $result[0] instanceof ServerRequestInterface === false ||
            $result[1] instanceof ResponseInterface === false) {
            throw new \UnexpectedValueException(
                i18n(
                    'Middleware must return a array, the first element is %s and then second is %s.',
                    ServerRequestInterface::class,
                    ResponseInterface::class
                )
            );
        }
    }

}
