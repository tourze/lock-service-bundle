<?php

namespace Tourze\LockServiceBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\LockServiceBundle\DependencyInjection\LockServiceExtension;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * 测试 LockServiceExtension
 */
class LockServiceExtensionTest extends TestCase
{
    /**
     * 测试加载服务
     */
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new LockServiceExtension();

        $extension->load([], $container);

        $this->assertTrue($container->hasDefinition(LockService::class));
    }
}
