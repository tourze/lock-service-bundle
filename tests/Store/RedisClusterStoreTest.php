<?php

namespace Tourze\LockServiceBundle\Tests\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * RedisClusterStore 集成测试
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(RedisClusterStore::class)]
final class RedisClusterStoreTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 如果需要，可以在这里添加额外的初始化逻辑
    }

    /**
     * 测试构造函数抛出无效 TTL 异常
     *
     * @ignore 为了测试构造函数逻辑，需要直接实例化
     */
    public function testConstructorThrowsInvalidTtlException(): void
    {
        $this->expectException(InvalidTtlException::class);
        $this->expectExceptionMessage('expects a strictly positive TTL');

        // Mock Redis 服务
        $redis = $this->createMockRedis();
        self::getContainer()->set(\Redis::class, $redis);

        // 直接实例化以测试构造函数异常
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        new RedisClusterStore($redis, -1.0);
    }

    /**
     * 测试构造函数抛出零 TTL 异常
     *
     * @ignore 为了测试构造函数逻辑，需要直接实例化
     */
    public function testConstructorThrowsInvalidTtlExceptionForZero(): void
    {
        $this->expectException(InvalidTtlException::class);
        $this->expectExceptionMessage('expects a strictly positive TTL');

        // Mock Redis 服务
        $redis = $this->createMockRedis();
        self::getContainer()->set(\Redis::class, $redis);

        // 直接实例化以测试构造函数异常
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        new RedisClusterStore($redis, 0.0);
    }

    /**
     * 测试构造函数接受有效 TTL
     *
     * @ignore 为了测试构造函数逻辑，需要直接实例化
     */
    public function testConstructorAcceptsValidTtl(): void
    {
        // Mock Redis 服务
        $redis = $this->createMockRedis();
        self::getContainer()->set(\Redis::class, $redis);

        // 直接实例化以测试构造函数
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        $store = new RedisClusterStore($redis, 300.0);
        $this->assertNotNull($store);
    }

    /**
     * 创建 Mock Redis 服务的辅助方法
     *
     * @param array<string, mixed> $options
     */
    private function createMockRedis(array $options = []): \Redis
    {
        /** @var array<string, mixed> $defaultOptions */
        $defaultOptions = [
            'evalResult' => true,
            'lastError' => null,
        ];
        /** @var array<string, mixed> $mergedOptions */
        $mergedOptions = array_merge($defaultOptions, $options);

        // @phpstan-ignore-next-line
        return new class($mergedOptions) extends \Redis {
            /** @var array<string, mixed> */
            private array $options;

            /**
             * @param array<string, mixed> $options
             */
            public function __construct(array $options)
            {
                parent::__construct();
                $this->options = $options;
            }

            public function clearLastError(): bool
            {
                return true;
            }

            /**
             * @param array<int|string, mixed> $args
             * @param mixed $script
             * @param mixed $numKeys
             */
            public function eval($script, $args = [], $numKeys = 0): mixed
            {
                return $this->options['evalResult'];
            }

            public function getLastError(): ?string
            {
                $lastError = $this->options['lastError'];

                return \is_string($lastError) ? $lastError : null;
            }
        };
    }

    /**
     * 测试成功保存锁
     */
    public function testSaveSuccessfullyAcquiresLock(): void
    {
        // Mock Redis 服务
        $redis = $this->createMockRedis();
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        // 执行保存操作，应该不抛出异常
        $store->save($key);
        $this->assertTrue(true); // 显式断言
    }

    /**
     * 测试保存锁时发生冲突
     */
    public function testSaveThrowsLockConflictedException(): void
    {
        // Mock Redis 服务，返回 false 表示冲突
        $redis = $this->createMockRedis(['evalResult' => false, 'lastError' => null]);
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $this->expectException(LockConflictedException::class);

        $store->save($key);
    }

    /**
     * 测试保存锁时 Redis 错误
     */
    public function testSaveThrowsLockStorageException(): void
    {
        // Mock Redis 服务，返回错误
        $redis = $this->createMockRedis(['evalResult' => true, 'lastError' => 'Redis error occurred']);
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $this->expectException(LockStorageException::class);
        $this->expectExceptionMessage('Redis error occurred');

        $store->save($key);
    }

    /**
     * 测试成功保存读锁
     */
    public function testSaveReadSuccessfullyAcquiresReadLock(): void
    {
        // Mock Redis 服务
        $redis = $this->createMockRedis();
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $store->saveRead($key);
        $this->assertTrue(true); // 显式断言
    }

    /**
     * 测试保存读锁时发生冲突
     */
    public function testSaveReadThrowsLockConflictedException(): void
    {
        // Mock Redis 服务，返回 false 表示冲突
        $redis = $this->createMockRedis(['evalResult' => false, 'lastError' => null]);
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $this->expectException(LockConflictedException::class);

        $store->saveRead($key);
    }

    /**
     * 测试成功删除锁
     */
    public function testDeleteSuccessfullyDeletesLock(): void
    {
        // Mock Redis 服务
        $redis = $this->createMockRedis();
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $store->delete($key);
        $this->assertTrue(true); // 显式断言
    }

    /**
     * 测试检查锁存在
     */
    public function testExistsReturnsTrueWhenLockExists(): void
    {
        // Mock Redis 服务，返回 true 表示锁存在
        $redis = $this->createMockRedis(['evalResult' => true, 'lastError' => null]);
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $result = $store->exists($key);

        $this->assertTrue($result);
    }

    /**
     * 测试检查锁不存在
     */
    public function testExistsReturnsFalseWhenLockDoesNotExist(): void
    {
        // Mock Redis 服务，返回 false 表示锁不存在
        $redis = $this->createMockRedis(['evalResult' => false, 'lastError' => null]);
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $result = $store->exists($key);

        $this->assertFalse($result);
    }

    /**
     * 测试延长锁过期时间成功
     */
    public function testPutOffExpirationSuccessfullyExtendsLock(): void
    {
        // Mock Redis 服务
        $redis = $this->createMockRedis();
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $store->putOffExpiration($key, 600.0);
        $this->assertTrue(true); // 显式断言
    }

    /**
     * 测试延长锁过期时间失败
     */
    public function testPutOffExpirationThrowsLockConflictedException(): void
    {
        // Mock Redis 服务，返回 false 表示冲突
        $redis = $this->createMockRedis(['evalResult' => false, 'lastError' => null]);
        self::getContainer()->set('redis.lock_connection', $redis);

        $store = self::getService(RedisClusterStore::class);
        $key = new Key('test-resource');

        $this->expectException(LockConflictedException::class);

        $store->putOffExpiration($key, 600.0);
    }
}
