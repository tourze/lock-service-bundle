<?php

namespace Tourze\LockServiceBundle\Tests\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Lock\Key;
use Tourze\LockServiceBundle\Store\SmartLockStore;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * SmartLockStore 集成测试
 *
 * 测试策略：
 * - 从容器获取 SmartLockStore 服务
 * - 通过环境变量控制使用的存储类型
 * - 验证锁的实际行为（save/delete/exists/putOffExpiration）
 * - 不依赖 Mock，测试真实的集成场景
 *
 * @internal
 */
#[CoversClass(SmartLockStore::class)]
#[RunTestsInSeparateProcesses]
final class SmartLockStoreTest extends AbstractIntegrationTestCase
{
    /**
     * 测试设置前的准备工作
     */
    protected function onSetUp(): void
    {
        // 清理可能存在的环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试默认使用文件锁的保存和删除功能
     */
    public function testSaveAndDeleteWithFlockStoreByDefault(): void
    {
        // 不设置 APP_LOCK_TYPE，默认使用文件锁
        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-flock-' . uniqid());

        // 测试保存锁
        $store->save($key);
        $this->assertTrue($store->exists($key));

        // 测试删除锁
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    /**
     * 测试使用 Redis 锁的保存和删除功能
     */
    public function testSaveAndDeleteWithRedisStore(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'redis';
        $_SERVER['APP_LOCK_TYPE'] = 'redis';
        putenv('APP_LOCK_TYPE=redis');

        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-redis-' . uniqid());

        try {
            // 测试保存锁
            // 注意：如果 Redis 未运行或配置有问题，可能会抛出异常
            $store->save($key);
            $this->assertTrue($store->exists($key));

            // 测试删除锁
            $store->delete($key);
            $this->assertFalse($store->exists($key));
        } catch (\Symfony\Component\Lock\Exception\LockConflictedException $e) {
            // Redis 可能未运行，测试跳过
            self::markTestSkipped('Redis is not available: ' . $e->getMessage());
        } catch (\RedisException $e) {
            // Redis 连接失败，测试跳过
            self::markTestSkipped('Redis connection failed: ' . $e->getMessage());
        } finally {
            // 清理环境变量
            unset($_ENV['APP_LOCK_TYPE']);
            $_SERVER['APP_LOCK_TYPE'] = null;
            putenv('APP_LOCK_TYPE');
        }
    }

    /**
     * 测试使用 Redis 集群锁的保存和删除功能
     */
    public function testSaveAndDeleteWithRedisClusterStore(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'redis-cluster';
        $_SERVER['APP_LOCK_TYPE'] = 'redis-cluster';
        putenv('APP_LOCK_TYPE=redis-cluster');

        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-redis-cluster-' . uniqid());

        try {
            // 测试保存锁
            // 注意：如果 Redis 未运行或配置有问题，可能会抛出异常
            $store->save($key);
            $this->assertTrue($store->exists($key));

            // 测试删除锁
            $store->delete($key);
            $this->assertFalse($store->exists($key));
        } catch (\Symfony\Component\Lock\Exception\LockConflictedException $e) {
            // Redis 可能未运行，测试跳过
            self::markTestSkipped('Redis is not available: ' . $e->getMessage());
        } catch (\RedisException $e) {
            // Redis 连接失败，测试跳过
            self::markTestSkipped('Redis connection failed: ' . $e->getMessage());
        } finally {
            // 清理环境变量
            unset($_ENV['APP_LOCK_TYPE']);
            $_SERVER['APP_LOCK_TYPE'] = null;
            putenv('APP_LOCK_TYPE');
        }
    }

    /**
     * 测试使用数据库锁的保存和删除功能
     */
    public function testSaveAndDeleteWithDbalStore(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'dbal';
        $_SERVER['APP_LOCK_TYPE'] = 'dbal';
        putenv('APP_LOCK_TYPE=dbal');

        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-dbal-' . uniqid());

        try {
            // 测试保存锁
            $store->save($key);
            $this->assertTrue($store->exists($key));

            // 测试删除锁
            $store->delete($key);
            $this->assertFalse($store->exists($key));
        } finally {
            // 清理环境变量
            unset($_ENV['APP_LOCK_TYPE']);
            $_SERVER['APP_LOCK_TYPE'] = null;
            putenv('APP_LOCK_TYPE');
        }
    }

    /**
     * 测试延长锁过期时间
     */
    public function testPutOffExpiration(): void
    {
        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-expiration-' . uniqid());

        // 测试保存锁
        $store->save($key);
        $this->assertTrue($store->exists($key));

        // 测试延长过期时间（此方法不应抛出异常）
        $ttl = 300.0;
        $store->putOffExpiration($key, $ttl);

        // 验证锁仍然存在
        $this->assertTrue($store->exists($key));

        // 清理
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    /**
     * 测试锁不存在时的 exists 方法
     */
    public function testExistsReturnsFalseForNonExistentKey(): void
    {
        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-non-existent-' . uniqid());

        // 验证不存在的锁返回 false
        $this->assertFalse($store->exists($key));
    }

    /**
     * 测试无效的锁类型回退到文件锁
     */
    public function testFallsBackToFlockStoreForInvalidType(): void
    {
        $_ENV['APP_LOCK_TYPE'] = 'invalid-type';
        $_SERVER['APP_LOCK_TYPE'] = 'invalid-type';
        putenv('APP_LOCK_TYPE=invalid-type');

        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-invalid-' . uniqid());

        try {
            // 测试保存锁（应该回退到文件锁）
            $store->save($key);
            $this->assertTrue($store->exists($key));

            // 测试删除锁
            $store->delete($key);
            $this->assertFalse($store->exists($key));
        } finally {
            // 清理环境变量
            unset($_ENV['APP_LOCK_TYPE']);
            $_SERVER['APP_LOCK_TYPE'] = null;
            putenv('APP_LOCK_TYPE');
        }
    }

    /**
     * 测试多个锁的并发操作
     */
    public function testMultipleLocksConcurrently(): void
    {
        $store = self::getService(SmartLockStore::class);
        $key1 = new Key('test-concurrent-1-' . uniqid());
        $key2 = new Key('test-concurrent-2-' . uniqid());

        // 保存两个锁
        $store->save($key1);
        $store->save($key2);

        // 验证两个锁都存在
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        // 删除第一个锁
        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2)); // 第二个锁应该仍然存在

        // 删除第二个锁
        $store->delete($key2);
        $this->assertFalse($store->exists($key2));
    }

    /**
     * 测试重复保存同一个锁
     */
    public function testSaveSameKeyMultipleTimes(): void
    {
        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-duplicate-' . uniqid());

        // 多次保存同一个锁
        $store->save($key);
        $store->save($key);
        $store->save($key);

        // 验证锁存在
        $this->assertTrue($store->exists($key));

        // 清理
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    /**
     * 测试删除不存在的锁不会抛出异常
     */
    public function testDeleteNonExistentKeyDoesNotThrow(): void
    {
        $store = self::getService(SmartLockStore::class);
        $key = new Key('test-non-existent-delete-' . uniqid());

        // 删除不存在的锁应该不抛出异常
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }
}
