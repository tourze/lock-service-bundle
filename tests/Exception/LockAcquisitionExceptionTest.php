<?php

namespace Tourze\LockServiceBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LockServiceBundle\Exception\LockAcquisitionException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(LockAcquisitionException::class)]
final class LockAcquisitionExceptionTest extends AbstractExceptionTestCase
{
    public function testConstructWithoutPreviousException(): void
    {
        $resource = 'test-resource';
        $maxRetries = 3;

        $exception = new LockAcquisitionException($resource, $maxRetries);

        $expectedMessage = sprintf('无法获取资源 %s 的锁，已重试 %d 次', $resource, $maxRetries);
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructWithPreviousException(): void
    {
        $resource = 'test-resource';
        $maxRetries = 5;
        $previousException = new \RuntimeException('Previous error');

        $exception = new LockAcquisitionException($resource, $maxRetries, $previousException);

        $expectedMessage = sprintf('无法获取资源 %s 的锁，已重试 %d 次', $resource, $maxRetries);
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testInheritsFromRuntimeException(): void
    {
        // 验证 LockAcquisitionException 继承自 RuntimeException
        $reflection = new \ReflectionClass(LockAcquisitionException::class);
        $this->assertTrue($reflection->isSubclassOf(\RuntimeException::class));
    }
}
