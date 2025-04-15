<?php

namespace Tourze\LockServiceBundle\Tests\Model;

use Tourze\LockServiceBundle\Model\LockEntity;

/**
 * 测试用的 LockEntity 实现
 */
class TestLockEntity implements LockEntity
{
    private string $resource;

    public function __construct(string $resource)
    {
        $this->resource = $resource;
    }

    public function retrieveLockResource(): string
    {
        return $this->resource;
    }
}
