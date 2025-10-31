<?php

namespace Tourze\LockServiceBundle\Exception;

/**
 * 测试异常
 * 专门用于测试场景的异常类
 */
class TestException extends \RuntimeException
{
    public function __construct(string $message = 'Test exception', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
