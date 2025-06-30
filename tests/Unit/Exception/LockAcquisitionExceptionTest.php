<?php

namespace Tourze\LockServiceBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\LockServiceBundle\Exception\LockAcquisitionException;

class LockAcquisitionExceptionTest extends TestCase
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
    
    public function testIsRuntimeException(): void
    {
        $exception = new LockAcquisitionException('resource', 1);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}