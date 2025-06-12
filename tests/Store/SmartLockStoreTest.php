<?php

namespace Tourze\LockServiceBundle\Tests\Store;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\FlockStore;
use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\LockServiceBundle\Store\SmartLockStore;

/**
 * SmartLockStore 单元测试
 */
class SmartLockStoreTest extends TestCase
{
    private RedisClusterStore&MockObject $redisClusterStore;
    private DoctrineDbalStore&MockObject $doctrineDbalStore;
    private FlockStore&MockObject $flockStore;

    protected function setUp(): void
    {
        $this->redisClusterStore = $this->createMock(RedisClusterStore::class);
        $this->doctrineDbalStore = $this->createMock(DoctrineDbalStore::class);
        $this->flockStore = $this->createMock(FlockStore::class);
    }

    /**
     * 测试默认使用文件锁
     */
    public function test_constructor_usesFlockStoreByDefault(): void
    {
        unset($_ENV['APP_LOCK_TYPE']);

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');

        $this->flockStore->expects($this->once())
            ->method('save')
            ->with($key);

        $smartStore->save($key);
    }

    /**
     * 测试使用 Redis 锁
     */
    public function test_constructor_usesRedisStoreForRedisType(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'redis';

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');

        $this->redisClusterStore->expects($this->once())
            ->method('save')
            ->with($key);

        $smartStore->save($key);

        unset($_ENV['APP_LOCK_TYPE']);
    }

    /**
     * 测试使用 Redis 集群锁
     */
    public function test_constructor_usesRedisStoreForRedisClusterType(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'redis-cluster';

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');

        $this->redisClusterStore->expects($this->once())
            ->method('save')
            ->with($key);

        $smartStore->save($key);

        unset($_ENV['APP_LOCK_TYPE']);
    }

    /**
     * 测试使用数据库锁
     */
    public function test_constructor_usesDbalStoreForDbalType(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'dbal';

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');

        $this->doctrineDbalStore->expects($this->once())
            ->method('save')
            ->with($key);

        $smartStore->save($key);

        unset($_ENV['APP_LOCK_TYPE']);
    }

    /**
     * 测试删除锁
     */
    public function test_delete_delegatesToInnerStore(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'redis';

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');

        $this->redisClusterStore->expects($this->once())
            ->method('delete')
            ->with($key);

        $smartStore->delete($key);

        unset($_ENV['APP_LOCK_TYPE']);
    }

    /**
     * 测试检查锁是否存在
     */
    public function test_exists_delegatesToInnerStore(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'redis';

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');

        $this->redisClusterStore->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(true);

        $result = $smartStore->exists($key);

        $this->assertTrue($result);

        unset($_ENV['APP_LOCK_TYPE']);
    }

    /**
     * 测试延长锁过期时间
     */
    public function test_putOffExpiration_delegatesToInnerStore(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'redis';

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');
        $ttl = 300.0;

        $this->redisClusterStore->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $ttl);

        $smartStore->putOffExpiration($key, $ttl);

        unset($_ENV['APP_LOCK_TYPE']);
    }

    /**
     * 测试无效的锁类型回退到文件锁
     */
    public function test_constructor_fallsBackToFlockStoreForInvalidType(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'invalid-type';

        $smartStore = new SmartLockStore(
            $this->redisClusterStore,
            $this->doctrineDbalStore,
            $this->flockStore
        );

        $key = new Key('test-key');

        $this->flockStore->expects($this->once())
            ->method('save')
            ->with($key);

        $smartStore->save($key);

        unset($_ENV['APP_LOCK_TYPE']);
    }
}
