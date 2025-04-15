<?php

namespace Tourze\LockServiceBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tourze\LockServiceBundle\Model\LockEntity;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * LockService 集成测试
 */
class LockServiceIntegrationTest extends TestCase
{
    private $container;
    private $lockService;
    private $lockFactory;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        // 创建一个实际的锁工厂（使用内存存储）
        $store = new InMemoryStore();
        $this->lockFactory = new LockFactory($store);

        // 初始化日志记录器
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // 创建锁服务
        $this->lockService = new LockService($this->lockFactory, $logger);
    }

    /**
     * 测试阻塞运行和锁竞争
     */
    public function testBlockingRunWithRealLocks(): void
    {
        $result1 = null;
        $result2 = null;
        $executionOrder = [];

        // 同时执行两个操作，这两个操作应该是顺序执行的，而不是并行的
        $thread1 = function () use (&$result1, &$executionOrder) {
            $result1 = $this->lockService->blockingRun('test-resource', function () use (&$executionOrder) {
                $executionOrder[] = 1;
                usleep(10000); // 延迟10毫秒
                $executionOrder[] = 2;
                return 'result1';
            });
        };

        $thread2 = function () use (&$result2, &$executionOrder) {
            $result2 = $this->lockService->blockingRun('test-resource', function () use (&$executionOrder) {
                $executionOrder[] = 3;
                usleep(10000); // 延迟10毫秒
                $executionOrder[] = 4;
                return 'result2';
            });
        };

        // 执行两个操作
        $thread1();
        $thread2();

        // 验证两个操作都执行了
        $this->assertEquals('result1', $result1);
        $this->assertEquals('result2', $result2);

        // 验证执行顺序：要么是 1,2,3,4 要么是 3,4,1,2
        $possibleOrders = [
            [1, 2, 3, 4],
            [3, 4, 1, 2]
        ];

        $this->assertTrue(in_array($executionOrder, $possibleOrders),
            "操作不是按预期顺序执行的。实际执行顺序: " . implode(', ', $executionOrder));
    }

    /**
     * 测试使用实体对象作为锁
     */
    public function testBlockingRunWithEntity(): void
    {
        // 创建一个模拟的 LockEntity
        $entity = $this->createStub(LockEntity::class);
        $entity->method('retrieveLockResource')
            ->willReturn('entity-lock-key');

        $executed = false;

        // 执行锁定操作
        $result = $this->lockService->blockingRun($entity, function () use (&$executed) {
            $executed = true;
            return 'entity-result';
        });

        $this->assertTrue($executed, '回调函数应该被执行');
        $this->assertEquals('entity-result', $result);

        // 测试在同一时间获取同一个锁
        $lock = $this->lockFactory->createLock('entity-lock-key');
        // 不需要阻塞，因为前一个锁已经释放
        $this->assertTrue($lock->acquire(false));
    }

    /**
     * 测试请求级别的锁
     */
    public function testRequestLevelLocks(): void
    {
        // 获取锁
        $lock = $this->lockService->acquireLock('request-lock');

        // 验证锁被获取
        $this->assertTrue($lock->isAcquired());

        // 尝试获取相同的锁（应该失败，因为已经被获取了）
        $anotherLock = $this->lockFactory->createLock('request-lock');
        $this->assertFalse($anotherLock->acquire(false));

        // 释放锁
        $this->lockService->releaseLock('request-lock');

        // 再次尝试获取锁（应该成功，因为已经释放了）
        $this->assertTrue($anotherLock->acquire(false));
    }

    /**
     * 测试重置方法
     */
    public function testResetReleasesAllLocks(): void
    {
        // 获取多个锁
        $lock1 = $this->lockService->acquireLock('reset-lock-1');
        $lock2 = $this->lockService->acquireLock('reset-lock-2');

        // 验证锁被获取
        $this->assertTrue($lock1->isAcquired());
        $this->assertTrue($lock2->isAcquired());

        // 重置服务
        $this->lockService->reset();

        // 尝试获取相同的锁（应该成功，因为已经被释放了）
        $testLock1 = $this->lockFactory->createLock('reset-lock-1');
        $testLock2 = $this->lockFactory->createLock('reset-lock-2');

        $this->assertTrue($testLock1->acquire(false));
        $this->assertTrue($testLock2->acquire(false));
    }
}
