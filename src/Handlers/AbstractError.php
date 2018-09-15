<?php
/**
 * Swoole+ Framework (https://slimframework.com)
 *
 * @link      https://github.com/slimphp/Swoole
 * @copyright Copyright (c) 2011-2017 Josh Lockhart
 * @license   https://github.com/slimphp/Swoole/blob/3.x/LICENSE.md (MIT License)
 */
namespace Sinpe\Swoole\Handlers;

use Exception;

/**
 * Abstract application error handler
 */
abstract class AbstractError extends AbstractHandler
{
    /**
     * @var bool
     */
    protected $displayErrorDetails;

    /**
     * Constructor
     *
     * @param bool $displayErrorDetails Set to true to display full details
     */
    public function __construct($displayErrorDetails = false)
    {
        $this->displayErrorDetails = (bool) $displayErrorDetails;
    }

    /**
     * Write to the error log if displayErrorDetails is false
     *
     * @param Exception|\Throwable $throwable
     *
     * @return void
     */
    protected function writeToErrorLog($throwable)
    {
        if ($this->displayErrorDetails) {
            return;
        }

        $message = 'Application Error:' . PHP_EOL;
        $message .= $this->renderThrowableAsText($throwable);
        while ($throwable = $throwable->getPrevious()) {
            $message .= PHP_EOL . 'Previous error:' . PHP_EOL;
            $message .= $this->renderThrowableAsText($throwable);
        }

        $message .= PHP_EOL . 'View in rendered output by enabling the "displayErrorDetails" setting.' . PHP_EOL;

        $this->logError($message);
    }

    /**
     * Render error as Text.
     *
     * @param Exception|\Throwable $throwable
     *
     * @return string
     */
    protected function renderThrowableAsText($throwable)
    {
        $text = i18n('Type: %s' . PHP_EOL, get_class($throwable));

        if ($code = $throwable->getCode()) {
            $text .= i18n('Code: %s' . PHP_EOL, $code);
        }

        if ($message = $throwable->getMessage()) {
            $text .= i18n('Message: %s' . PHP_EOL, htmlentities($message));
        }

        if ($file = $throwable->getFile()) {
            $text .= i18n('File: %s' . PHP_EOL, $file);
        }

        if ($line = $throwable->getLine()) {
            $text .= i18n('Line: %s' . PHP_EOL, $line);
        }

        if ($trace = $throwable->getTraceAsString()) {
            $text .= i18n('Trace: %s', $trace);
        }

        return $text;
    }

    /**
     * Wraps the error_log function so that this can be easily tested
     *
     * @param $message
     */
    protected function logError($message)
    {
        error_log($message);
    }
}
