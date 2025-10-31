<?php

namespace Tourze\LockServiceBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;
use Tourze\LockServiceBundle\Service\LoggerProvider;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(LoggerProvider::class)]
#[RunTestsInSeparateProcesses]
final class LoggerProviderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // AbstractIntegrationTestCase已经处理了容器设置
    }

    public function testGetLogger(): void
    {
        $provider = self::getService(LoggerProvider::class);

        // 在测试环境下应该返回 NullLogger
        $result = $provider->getLogger();
        self::assertInstanceOf(NullLogger::class, $result);
    }

    public function testGetLoggerReturnsNullLoggerInTestEnvironment(): void
    {
        $provider = self::getService(LoggerProvider::class);

        // 在测试环境下应该返回 NullLogger
        $result = $provider->getLogger();
        self::assertInstanceOf(NullLogger::class, $result);
    }
}
