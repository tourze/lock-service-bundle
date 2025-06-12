<?php

namespace Tourze\LockServiceBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;
use Tourze\LockServiceBundle\LockServiceBundle;
use Tourze\LockServiceBundle\Model\LockEntity;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\LockServiceBundle\Store\SmartLockStore;

/**
 * LockService 集成测试
 */
class LockServiceIntegrationTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            \Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
            \Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
            \Snc\RedisBundle\SncRedisBundle::class => ['all' => true],
            LockServiceBundle::class => ['all' => true],
        ]);
    }

    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    /**
     * 测试容器中的服务注册 - 特别校验 snc_redis.lock 服务
     */
    public function test_container_hasExpectedServices(): void
    {
        $container = static::getContainer();

        // 验证主要服务
        $this->assertTrue($container->has(LockService::class));
        $this->assertTrue($container->has(RedisClusterStore::class));
        $this->assertTrue($container->has(SmartLockStore::class));

        // 重点：验证 snc_redis.lock 服务是否存在
        $this->assertTrue($container->has('snc_redis.lock'), 'snc_redis.lock 服务应该存在');

        // 验证其他相关服务
        $this->assertTrue($container->has('Symfony\Component\Lock\LockFactory'));
        $this->assertTrue($container->has('Symfony\Component\Lock\Store\FlockStore'));
        $this->assertTrue($container->has('Symfony\Component\Lock\Store\DoctrineDbalStore'));
    }

    /**
     * 测试可以获取 snc_redis.lock 服务实例
     */
    public function test_canGetRedisLockService(): void
    {
        $container = static::getContainer();

        $redisService = $container->get('snc_redis.lock');
        $this->assertInstanceOf(\Redis::class, $redisService);
    }

    /**
     * 测试 LockService 服务功能
     */
    public function test_lockService_functionalityWorks(): void
    {
        $container = static::getContainer();
        $lockService = $container->get(LockService::class);

        $this->assertInstanceOf(LockService::class, $lockService);

        $executed = false;

        // 测试阻塞执行
        $result = $lockService->blockingRun('test-resource', function () use (&$executed) {
            $executed = true;
            return 'success';
        });

        $this->assertTrue($executed);
        $this->assertEquals('success', $result);
    }

    /**
     * 测试使用实体对象作为锁
     */
    public function test_blockingRun_withLockEntity(): void
    {
        $container = static::getContainer();
        $lockService = $container->get(LockService::class);

        // 创建一个模拟的 LockEntity
        $entity = new class implements LockEntity {
            public function retrieveLockResource(): string
            {
                return 'entity-lock-key';
            }
        };

        $executed = false;

        $result = $lockService->blockingRun($entity, function () use (&$executed) {
            $executed = true;
            return 'entity-result';
        });

        $this->assertTrue($executed);
        $this->assertEquals('entity-result', $result);
    }

    /**
     * 测试多资源锁
     */
    public function test_blockingRun_withMultipleResources(): void
    {
        $container = static::getContainer();
        $lockService = $container->get(LockService::class);

        $executed = false;

        $result = $lockService->blockingRun(['resource1', 'resource2'], function () use (&$executed) {
            $executed = true;
            return 'multi-success';
        });

        $this->assertTrue($executed);
        $this->assertEquals('multi-success', $result);
    }

    /**
     * 测试请求级别的锁
     */
    public function test_requestLevelLocks(): void
    {
        $container = static::getContainer();
        $lockService = $container->get(LockService::class);

        // 获取锁
        $lock = $lockService->acquireLock('request-lock');
        $this->assertTrue($lock->isAcquired());

        // 释放锁
        $lockService->releaseLock('request-lock');
    }

    /**
     * 测试 RedisClusterStore 服务
     */
    public function test_redisClusterStore_isConfigured(): void
    {
        $container = static::getContainer();
        $store = $container->get(RedisClusterStore::class);

        $this->assertInstanceOf(RedisClusterStore::class, $store);
    }

    /**
     * 测试 SmartLockStore 服务
     */
    public function test_smartLockStore_isConfigured(): void
    {
        $container = static::getContainer();
        $store = $container->get(SmartLockStore::class);

        $this->assertInstanceOf(SmartLockStore::class, $store);
    }

    /**
     * 测试 Doctrine DBAL 连接是否正确配置
     */
    public function test_doctrineLockConnection_isConfigured(): void
    {
        $container = static::getContainer();

        // 验证 doctrine.dbal.lock_connection 服务存在
        $this->assertTrue($container->has('doctrine.dbal.lock_connection'));

        $connection = $container->get('doctrine.dbal.lock_connection');
        $this->assertInstanceOf(\Doctrine\DBAL\Connection::class, $connection);
    }
}
