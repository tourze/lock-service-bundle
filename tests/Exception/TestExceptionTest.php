<?php

namespace Tourze\LockServiceBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LockServiceBundle\Exception\TestException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * 测试异常类的测试
 *
 * @internal
 */
#[CoversClass(TestException::class)]
final class TestExceptionTest extends AbstractExceptionTestCase
{
    public function testDefaultConstructor(): void
    {
        $exception = new TestException();

        $this->assertSame('Test exception', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithMessage(): void
    {
        $message = 'Custom test message';
        $exception = new TestException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $message = 'Custom test message';
        $code = 500;
        $exception = new TestException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPrevious(): void
    {
        $previous = new \RuntimeException('Previous exception');
        $message = 'Custom test message';
        $code = 500;
        $exception = new TestException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testInheritsFromRuntimeException(): void
    {
        // 验证 TestException 继承自 RuntimeException
        $reflection = new \ReflectionClass(TestException::class);
        $this->assertTrue($reflection->isSubclassOf(\RuntimeException::class));
    }
}
