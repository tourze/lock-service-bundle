<?php

namespace Tourze\LockServiceBundle\Store;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * 根据不同的配置，我们使用不同的锁实现，默认走redis
 *
 * @see https://symfony.com/doc/current/components/lock.html#blocking-locks
 */
#[Autoconfigure(lazy: true)]
class SmartLockStore implements PersistingStoreInterface
{
    private PersistingStoreInterface $inner;

    public function __construct(
        private readonly RedisClusterStore $redisClusterStore,
        private readonly DoctrineDbalStore $doctrineDbalStore,
        private readonly FlockStore $flockStore,
    ) {
        // 支持几种类型的锁：Redis / Redis集群 / 数据库 / 文件锁
        $lockType = $_ENV['APP_LOCK_TYPE'] ?? 'file';

        if ('redis' === $lockType || 'redis-cluster' === $lockType) {
            // 默认的RedisStore不能兼容阿里云的，只能自己覆盖一次
            $this->inner = $this->redisClusterStore;
        } elseif ('dbal' === $lockType) {
            $this->inner = $this->doctrineDbalStore;
        } else {
            $this->inner = $this->flockStore;
        }
    }

    public function save(Key $key): void
    {
        $this->inner->save($key);
    }

    public function delete(Key $key): void
    {
        $this->inner->delete($key);
    }

    public function exists(Key $key): bool
    {
        return $this->inner->exists($key);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->inner->putOffExpiration($key, $ttl);
    }
}
