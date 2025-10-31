<?php

namespace Tourze\LockServiceBundle\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LockServiceBundle\LockServiceBundle;
use Tourze\LockServiceBundle\Model\LockEntity;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\LockServiceBundle\Store\SmartLockStore;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * LockService 集成测试
 *
 * @internal
 */
#[CoversClass(LockServiceBundle::class)]
#[RunTestsInSeparateProcesses]
final class LockServiceIntegrationTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 不需要额外的设置，因为 lock service 不使用数据库
    }

    /**
     * 测试容器中的服务注册
     */
    public function testContainerHasExpectedServices(): void
    {
        $container = self::getContainer();

        // 验证主要服务
        $this->assertTrue($container->has(LockService::class));
        $this->assertTrue($container->has(RedisClusterStore::class));
        $this->assertTrue($container->has(SmartLockStore::class));

        // 验证其他相关服务
        $this->assertTrue($container->has('Symfony\Component\Lock\LockFactory'));
        $this->assertTrue($container->has('Symfony\Component\Lock\Store\FlockStore'));
        $this->assertTrue($container->has('Symfony\Component\Lock\Store\DoctrineDbalStore'));
    }

    /**
     * 测试 LockService 服务功能
     */
    public function testLockServiceFunctionalityWorks(): void
    {
        $lockService = self::getService(LockService::class);

        $this->assertInstanceOf(LockService::class, $lockService);

        $executed = false;

        // 捕获所有输出以避免测试失败
        ob_start();

        try {
            // 测试阻塞执行
            $result = $lockService->blockingRun('test-resource', function () use (&$executed) {
                $executed = true;

                return 'success';
            });

            $this->assertTrue($executed);
            $this->assertEquals('success', $result);
        } finally {
            ob_end_clean(); // 清理输出缓冲区
        }
    }

    /**
     * 测试使用实体对象作为锁
     */
    public function testBlockingRunWithLockEntity(): void
    {
        $lockService = self::getService(LockService::class);

        // 创建一个模拟的 LockEntity
        $entity = new class implements LockEntity {
            public function retrieveLockResource(): string
            {
                return 'entity-lock-key';
            }
        };

        $executed = false;

        // 捕获所有输出以避免测试失败
        ob_start();

        try {
            $result = $lockService->blockingRun($entity, function () use (&$executed) {
                $executed = true;

                return 'entity-result';
            });

            $this->assertTrue($executed);
            $this->assertEquals('entity-result', $result);
        } finally {
            ob_end_clean(); // 清理输出缓冲区
        }
    }

    /**
     * 测试多资源锁
     */
    public function testBlockingRunWithMultipleResources(): void
    {
        $lockService = self::getService(LockService::class);

        $executed = false;

        // 捕获所有输出以避免测试失败
        ob_start();

        try {
            $result = $lockService->blockingRun(['resource1', 'resource2'], function () use (&$executed) {
                $executed = true;

                return 'multi-success';
            });

            $this->assertTrue($executed);
            $this->assertEquals('multi-success', $result);
        } finally {
            ob_end_clean(); // 清理输出缓冲区
        }
    }

    /**
     * 测试请求级别的锁
     */
    public function testRequestLevelLocks(): void
    {
        $lockService = self::getService(LockService::class);

        // 捕获所有输出以避免测试失败
        ob_start();

        try {
            // 获取锁
            $lock = $lockService->acquireLock('request-lock');
            $this->assertTrue($lock->isAcquired());

            // 释放锁
            $lockService->releaseLock('request-lock');
        } finally {
            ob_end_clean(); // 清理输出缓冲区
        }
    }

    /**
     * 测试 RedisClusterStore 服务
     */
    public function testRedisClusterStoreIsConfigured(): void
    {
        $store = self::getService(RedisClusterStore::class);

        $this->assertInstanceOf(RedisClusterStore::class, $store);
    }

    /**
     * 测试 SmartLockStore 服务
     */
    public function testSmartLockStoreIsConfigured(): void
    {
        $store = self::getService(SmartLockStore::class);

        $this->assertInstanceOf(SmartLockStore::class, $store);
    }

    /**
     * 测试 acquireLock 方法
     */
    public function testAcquireLock(): void
    {
        $lockService = self::getService(LockService::class);

        ob_start();
        try {
            $lock = $lockService->acquireLock('test-acquire-lock');
            $this->assertTrue($lock->isAcquired());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * 测试 releaseLock 方法
     */
    public function testReleaseLock(): void
    {
        $lockService = self::getService(LockService::class);

        ob_start();
        try {
            $lockService->acquireLock('test-release-lock');
            $lockService->releaseLock('test-release-lock');

            // 确保锁已被释放，可以再次获取
            $lock = $lockService->acquireLock('test-release-lock');
            $this->assertTrue($lock->isAcquired());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * 测试 reset 方法
     */
    public function testReset(): void
    {
        $lockService = self::getService(LockService::class);

        ob_start();
        try {
            $lockService->acquireLock('test-reset-lock');
            $lockService->reset();

            // 确保 reset 后可以正常工作
            $lock = $lockService->acquireLock('test-reset-lock-2');
            $this->assertTrue($lock->isAcquired());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * 测试 Doctrine DBAL 连接是否正确配置
     */
    public function testDoctrineLockConnectionIsConfigured(): void
    {
        $container = self::getContainer();

        // 验证 doctrine.dbal.lock_connection 服务存在
        $this->assertTrue($container->has('doctrine.dbal.lock_connection'));

        $connection = $container->get('doctrine.dbal.lock_connection');
        $this->assertInstanceOf(Connection::class, $connection);
    }
}
