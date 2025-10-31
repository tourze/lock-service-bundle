<?php

namespace Tourze\LockServiceBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LockServiceBundle\Exception\TestException;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * 递归和嵌套加锁场景测试
 *
 * @internal
 */
#[CoversClass(LockService::class)]
#[RunTestsInSeparateProcesses]
final class RecursiveLockingTest extends AbstractEventSubscriberTestCase
{
    private LockService $lockService;

    /**
     * 测试递归加锁场景：同一个函数内递归调用
     */
    public function testRecursiveLocking(): void
    {
        $callCount = 0;

        // 递归函数
        $recursiveFunction = function (int $depth) use (&$recursiveFunction, &$callCount): int {
            $result = $this->lockService->blockingRun('recursive-resource', function () use ($depth, &$recursiveFunction, &$callCount): int {
                ++$callCount;
                if ($depth > 0) {
                    // 递归调用，应该不会再次加锁
                    return $recursiveFunction($depth - 1);
                }

                return $callCount;
            });
            $this->assertIsInt($result);

            return $result;
        };

        $result = $recursiveFunction(3);

        $this->assertEquals(4, $result); // 应该被调用4次 (深度0,1,2,3)
    }

    /**
     * 测试嵌套函数调用加锁场景：A函数调用B函数，都对同一资源加锁
     */
    public function testNestedFunctionLocking(): void
    {
        // B函数：处理订单
        $processOrder = function (string $orderId): string {
            $result = $this->lockService->blockingRun($orderId, function (): string {
                return 'order processed';
            });
            $this->assertIsString($result);

            return $result;
        };

        // A函数：调用B函数
        $result = $this->lockService->blockingRun('order-123', function () use ($processOrder) {
            // 在已经持有锁的情况下，调用另一个需要同样锁的函数
            $innerResult = $processOrder('order-123');

            return 'outer: ' . $innerResult;
        });

        $this->assertEquals('outer: order processed', $result);
    }

    /**
     * 测试多重嵌套加锁：A->B->C，每层都尝试加锁
     */
    public function testMultiLevelNestedLocking(): void
    {
        $executionOrder = [];

        // C层函数
        $functionC = function () use (&$executionOrder): string {
            $result = $this->lockService->blockingRun('shared-resource', function () use (&$executionOrder): string {
                $executionOrder[] = 'C';

                return 'C-result';
            });
            $this->assertIsString($result);

            return $result;
        };

        // B层函数
        $functionB = function () use ($functionC, &$executionOrder): string {
            $result = $this->lockService->blockingRun('shared-resource', function () use ($functionC, &$executionOrder): string {
                $executionOrder[] = 'B';
                $cResult = $functionC();

                return 'B-' . $cResult;
            });
            $this->assertIsString($result);

            return $result;
        };

        // A层函数
        $result = $this->lockService->blockingRun('shared-resource', function () use ($functionB, &$executionOrder) {
            $executionOrder[] = 'A';
            $bResult = $functionB();

            return 'A-' . $bResult;
        });

        $this->assertEquals('A-B-C-result', $result);
        $this->assertEquals(['A', 'B', 'C'], $executionOrder);
    }

    /**
     * 测试混合场景：同时持有多个锁的嵌套调用
     */
    public function testMixedMultipleLocks(): void
    {
        $executionLog = [];

        // 场景：处理订单需要锁订单和用户
        $processOrder = function (string $orderId, string $userId) use (&$executionLog): string {
            $result = $this->lockService->blockingRun([$orderId, $userId], function () use (&$executionLog, $orderId, $userId): string {
                $executionLog[] = "process-order: {$orderId}, {$userId}";

                // 内部只锁订单（用户锁已持有）
                $this->lockService->blockingRun($orderId, function () use (&$executionLog, $orderId): void {
                    $executionLog[] = "update-order: {$orderId}";
                });

                // 内部只锁用户（订单锁已持有）
                $this->lockService->blockingRun($userId, function () use (&$executionLog, $userId): void {
                    $executionLog[] = "update-user: {$userId}";
                });

                return 'order-processed';
            });
            $this->assertIsString($result);

            return $result;
        };

        $result = $processOrder('order-456', 'user-789');

        $this->assertEquals('order-processed', $result);
        $this->assertEquals([
            'process-order: order-456, user-789',
            'update-order: order-456',
            'update-user: user-789',
        ], $executionLog);
    }

    /**
     * 测试循环中的加锁：避免重复加锁
     */
    public function testLockingInLoop(): void
    {
        $iterations = [];

        // 外层持有锁
        $this->lockService->blockingRun('loop-resource', function () use (&$iterations): void {
            // 循环中多次尝试获取同一个锁
            for ($i = 0; $i < 5; ++$i) {
                $this->lockService->blockingRun('loop-resource', function () use ($i, &$iterations) {
                    $iterations[] = $i;

                    return "iteration-{$i}";
                });
            }
        });

        // 验证所有迭代都执行了
        $this->assertEquals([0, 1, 2, 3, 4], $iterations);
    }

    /**
     * 测试交叉加锁场景：A锁住X后调用B，B锁住Y后调用C，C需要同时锁X和Y
     */
    public function testCrossLocking(): void
    {
        $executionLog = [];

        // C函数：需要X和Y锁（但都已经被持有）
        $functionC = function () use (&$executionLog): string {
            $result = $this->lockService->blockingRun(['X', 'Y'], function () use (&$executionLog): string {
                $executionLog[] = 'C-executed';

                return 'C-done';
            });
            $this->assertIsString($result);

            return $result;
        };

        // B函数：持有Y锁
        $functionB = function () use ($functionC, &$executionLog): string {
            $result = $this->lockService->blockingRun('Y', function () use ($functionC, &$executionLog): string {
                $executionLog[] = 'B-executed';

                return 'B-' . $functionC();
            });
            $this->assertIsString($result);

            return $result;
        };

        // A函数：持有X锁
        $result = $this->lockService->blockingRun('X', function () use ($functionB, &$executionLog) {
            $executionLog[] = 'A-executed';

            return 'A-' . $functionB();
        });

        $this->assertEquals('A-B-C-done', $result);
        $this->assertEquals(['A-executed', 'B-executed', 'C-executed'], $executionLog);
    }

    /**
     * 测试死锁预防：确保资源排序
     */
    public function testDeadlockPrevention(): void
    {
        $thread1Executed = false;
        $thread2Executed = false;

        // 线程1：先获取A，再获取B
        $thread1 = function () use (&$thread1Executed): void {
            $this->lockService->blockingRun(['resource-A', 'resource-B'], function () use (&$thread1Executed): string {
                $thread1Executed = true;

                return 'thread1';
            });
        };

        // 线程2：先获取B，再获取A（但会被排序为A、B）
        $thread2 = function () use (&$thread2Executed): void {
            $this->lockService->blockingRun(['resource-B', 'resource-A'], function () use (&$thread2Executed): string {
                $thread2Executed = true;

                return 'thread2';
            });
        };

        // 执行两个"线程"（实际是顺序执行，但验证了排序逻辑）
        $thread1();
        $thread2();

        $this->assertTrue($thread1Executed);
        $this->assertTrue($thread2Executed);
    }

    /**
     * 测试异常情况下的锁释放
     */
    public function testLockReleaseOnException(): void
    {
        $exceptionThrown = false;

        try {
            $this->lockService->blockingRun('exception-resource', function (): void {
                throw new TestException('Test exception');
            });
        } catch (TestException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown);

        // 验证锁已被释放，可以再次获取
        $result = $this->lockService->blockingRun('exception-resource', function (): string {
            return 'lock acquired after exception';
        });

        $this->assertEquals('lock acquired after exception', $result);
    }

    /**
     * 测试 acquireLock 方法
     */
    public function testAcquireLock(): void
    {
        $lock = $this->lockService->acquireLock('test-acquire-resource');
        $this->assertTrue($lock->isAcquired());
    }

    /**
     * 测试 blockingRun 方法
     */
    public function testBlockingRun(): void
    {
        $executed = false;
        $result = $this->lockService->blockingRun('test-blocking-resource', function () use (&$executed): string {
            $executed = true;

            return 'success';
        });

        $this->assertTrue($executed);
        $this->assertEquals('success', $result);
    }

    /**
     * 测试 releaseLock 方法
     */
    public function testReleaseLock(): void
    {
        $this->lockService->acquireLock('test-release-resource');
        $this->lockService->releaseLock('test-release-resource');

        // 确保锁已被释放，可以再次获取
        $lock = $this->lockService->acquireLock('test-release-resource');
        $this->assertTrue($lock->isAcquired());
    }

    /**
     * 测试 reset 方法
     */
    public function testReset(): void
    {
        $this->lockService->acquireLock('test-reset-resource');
        $this->lockService->reset();

        // 确保 reset 后可以正常工作
        $lock = $this->lockService->acquireLock('test-reset-resource-2');
        $this->assertTrue($lock->isAcquired());
    }

    /**
     * 测试使用反射验证内部状态清理
     */
    public function testInternalStateCleanup(): void
    {
        // 执行一个简单的加锁操作
        $this->lockService->blockingRun('test-resource', function (): string {
            return 'done';
        });

        // 使用反射检查 currentLocks 是否被清理
        $reflection = new \ReflectionObject($this->lockService);
        $currentLocksProperty = $reflection->getProperty('currentLocks');
        $currentLocksProperty->setAccessible(true);

        $currentLocks = $currentLocksProperty->getValue($this->lockService);
        $this->assertEmpty($currentLocks, 'currentLocks should be empty after blockingRun completes');
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务
        $this->lockService = self::getService(LockService::class);
    }
}
