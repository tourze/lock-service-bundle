<?php

namespace Tourze\LockServiceBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
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

        $result = $provider->getLogger();
        self::assertInstanceOf(LoggerInterface::class, $result);
    }
}
