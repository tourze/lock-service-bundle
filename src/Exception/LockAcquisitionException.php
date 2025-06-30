<?php

namespace Tourze\LockServiceBundle\Exception;

/**
 * 锁获取异常
 * 当无法获取锁时抛出此异常
 */
class LockAcquisitionException extends \RuntimeException
{
    public function __construct(string $resource, int $maxRetries, ?\Throwable $previous = null)
    {
        $message = sprintf('无法获取资源 %s 的锁，已重试 %d 次', $resource, $maxRetries);
        parent::__construct($message, 0, $previous);
    }
} 