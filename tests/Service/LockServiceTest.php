<?php

namespace Tourze\LockServiceBundle\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Tourze\LockServiceBundle\Model\LockEntity;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * LockService 测试
 */
class LockServiceTest extends TestCase
{
    /**
     * @var LockFactory|MockObject
     */
    private $lockFactory;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var LockService
     */
    private $lockService;

    /**
     * @var LockInterface|MockObject
     */
    private $lock;

    protected function setUp(): void
    {
        $this->lock = $this->createMock(LockInterface::class);

        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lockFactory->method('createLock')
            ->willReturn($this->lock);

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->lockService = new LockService($this->lockFactory, $this->logger);
    }

    /**
     * 测试阻塞执行方法 - 使用字符串资源
     */
    public function testBlockingRunWithStringResource(): void
    {
        // 配置模拟对象行为
        $this->lock->method('acquire')
            ->with(true)
            ->willReturn(true);

        $this->lock->method('isAcquired')
            ->willReturn(true);

        // 执行测试
        $result = $this->lockService->blockingRun('test-resource', function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    /**
     * 测试阻塞执行方法 - 使用 LockEntity 资源
     */
    public function testBlockingRunWithLockEntityResource(): void
    {
        // 创建一个 LockEntity 实现
        $lockEntity = $this->createMock(LockEntity::class);
        $lockEntity->method('retrieveLockResource')
            ->willReturn('entity-resource');

        // 配置锁的行为
        $this->lock->method('acquire')
            ->with(true)
            ->willReturn(true);

        $this->lock->method('isAcquired')
            ->willReturn(true);

        // 执行测试
        $result = $this->lockService->blockingRun($lockEntity, function () {
            return 'entity-success';
        });

        $this->assertEquals('entity-success', $result);
    }

    /**
     * 测试阻塞执行方法 - 使用资源数组
     */
    public function testBlockingRunWithMultipleResources(): void
    {
        // 创建多个锁模拟对象
        $lock1 = $this->createMock(LockInterface::class);
        $lock2 = $this->createMock(LockInterface::class);

        // 配置 lockFactory 返回不同的锁
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')
            ->willReturnOnConsecutiveCalls($lock1, $lock2);

        $lockService = new LockService($lockFactory, $this->logger);

        // 配置锁的行为
        $lock1->method('acquire')
            ->with(true)
            ->willReturn(true);

        $lock1->method('isAcquired')
            ->willReturn(true);

        $lock2->method('acquire')
            ->with(true)
            ->willReturn(true);

        $lock2->method('isAcquired')
            ->willReturn(true);

        // 执行测试
        $result = $lockService->blockingRun(['resource1', 'resource2'], function () {
            return 'multi-success';
        });

        $this->assertEquals('multi-success', $result);
    }

    /**
     * 测试请求级别锁获取
     */
    public function testAcquireLock(): void
    {
        $this->lock->method('acquire')
            ->with(true)
            ->willReturn(true);

        $result = $this->lockService->acquireLock('test-key');

        $this->assertSame($this->lock, $result);
    }

    /**
     * 测试请求级别锁释放
     */
    public function testReleaseLock(): void
    {
        // 先获取锁
        $this->lock->method('acquire')
            ->with(true)
            ->willReturn(true);

        $this->lockService->acquireLock('test-key');

        // 验证锁存在 
        $this->lock->expects($this->once())
            ->method('isAcquired')
            ->willReturn(true);

        // 释放锁
        $this->lockService->releaseLock('test-key');

        // 使用反射来验证私有属性中没有锁
        $reflection = new \ReflectionObject($this->lockService);
        $property = $reflection->getProperty('existLocks');
        $property->setAccessible(true);
        $existLocks = $property->getValue($this->lockService);

        $this->assertArrayNotHasKey('test-key', $existLocks);
    }

    /**
     * 测试重置方法
     */
    public function testReset(): void
    {
        // 先获取一个锁
        $this->lock->method('acquire')
            ->with(true)
            ->willReturn(true);

        $this->lockService->acquireLock('test-key');

        // 测试重置方法
        $this->lock->expects($this->once())
            ->method('isAcquired')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->lockService->reset();

        // 使用反射来验证私有属性被重置
        $reflection = new \ReflectionObject($this->lockService);

        $currentLocksProperty = $reflection->getProperty('currentLocks');
        $currentLocksProperty->setAccessible(true);
        $this->assertEmpty($currentLocksProperty->getValue($this->lockService));
    }
}
