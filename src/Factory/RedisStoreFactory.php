<?php

namespace Tourze\LockServiceBundle\Factory;

use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\RedisDedicatedConnectionBundle\Attribute\WithDedicatedConnection;

#[WithDedicatedConnection(channel: 'lock')]
readonly class RedisStoreFactory
{
    public function __construct(
        private \Redis $redis,
    ) {
    }

    public function create(): RedisClusterStore
    {
        return new RedisClusterStore($this->redis);
    }
}
