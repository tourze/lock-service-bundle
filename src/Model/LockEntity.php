<?php

declare(strict_types=1);

namespace Tourze\LockServiceBundle\Model;

interface LockEntity
{
    /**
     * 获取锁id
     */
    public function retrieveLockResource(): string;
}
