<?php

namespace Tourze\LockServiceBundle\Tests\Store;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Tourze\LockServiceBundle\Store\RedisClusterStore;

/**
 * RedisClusterStore 单元测试
 */
class RedisClusterStoreTest extends TestCase
{
    private \Redis&MockObject $redis;
    private RedisClusterStore $store;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(\Redis::class);
        $this->store = new RedisClusterStore($this->redis);
    }

    /**
     * 测试构造函数抛出无效 TTL 异常
     */
    public function test_constructor_throwsInvalidTtlException(): void
    {
        $this->expectException(InvalidTtlException::class);
        $this->expectExceptionMessage('expects a strictly positive TTL');

        new RedisClusterStore($this->redis, -1.0);
    }

    /**
     * 测试构造函数抛出零 TTL 异常
     */
    public function test_constructor_throwsInvalidTtlExceptionForZero(): void
    {
        $this->expectException(InvalidTtlException::class);
        $this->expectExceptionMessage('expects a strictly positive TTL');

        new RedisClusterStore($this->redis, 0.0);
    }

    /**
     * 测试构造函数接受有效 TTL
     */
    public function test_constructor_acceptsValidTtl(): void
    {
        $store = new RedisClusterStore($this->redis, 300.0);
        $this->assertInstanceOf(RedisClusterStore::class, $store);
    }

    /**
     * 测试成功保存锁
     */
    public function test_save_successfullyAcquiresLock(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(true);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $this->store->save($key);
    }

    /**
     * 测试保存锁时发生冲突
     */
    public function test_save_throwsLockConflictedException(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(false);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $this->expectException(LockConflictedException::class);

        $this->store->save($key);
    }

    /**
     * 测试保存锁时 Redis 错误
     */
    public function test_save_throwsLockStorageException(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(true);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn('Redis error occurred');

        $this->expectException(LockStorageException::class);
        $this->expectExceptionMessage('Redis error occurred');

        $this->store->save($key);
    }

    /**
     * 测试成功保存读锁
     */
    public function test_saveRead_successfullyAcquiresReadLock(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(true);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $this->store->saveRead($key);
    }

    /**
     * 测试保存读锁时发生冲突
     */
    public function test_saveRead_throwsLockConflictedException(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(false);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $this->expectException(LockConflictedException::class);

        $this->store->saveRead($key);
    }

    /**
     * 测试成功删除锁
     */
    public function test_delete_successfullyDeletesLock(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(true);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $this->store->delete($key);
    }

    /**
     * 测试检查锁存在
     */
    public function test_exists_returnsTrueWhenLockExists(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(true);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $result = $this->store->exists($key);

        $this->assertTrue($result);
    }

    /**
     * 测试检查锁不存在
     */
    public function test_exists_returnsFalseWhenLockDoesNotExist(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(false);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $result = $this->store->exists($key);

        $this->assertFalse($result);
    }

    /**
     * 测试延长锁过期时间成功
     */
    public function test_putOffExpiration_successfullyExtendsLock(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(true);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $this->store->putOffExpiration($key, 600.0);
    }

    /**
     * 测试延长锁过期时间失败
     */
    public function test_putOffExpiration_throwsLockConflictedException(): void
    {
        $key = new Key('test-resource');

        $this->redis->expects($this->atLeastOnce())
            ->method('clearLastError');

        $this->redis->expects($this->atLeastOnce())
            ->method('eval')
            ->willReturn(false);

        $this->redis->expects($this->atLeastOnce())
            ->method('getLastError')
            ->willReturn(null);

        $this->expectException(LockConflictedException::class);

        $this->store->putOffExpiration($key, 600.0);
    }
}
