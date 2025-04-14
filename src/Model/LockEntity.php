<?php

namespace Tourze\LockServiceBundle\Model;

interface LockEntity
{
    /**
     * 获取锁id
     */
    public function retrieveLockResource(): string;
}
