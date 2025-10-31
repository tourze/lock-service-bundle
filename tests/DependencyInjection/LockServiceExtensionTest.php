<?php

namespace Tourze\LockServiceBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\LockServiceBundle\DependencyInjection\LockServiceExtension;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\LockServiceBundle\Store\SmartLockStore;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * 测试 LockServiceExtension
 *
 * @internal
 */
#[CoversClass(LockServiceExtension::class)]
final class LockServiceExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private ContainerBuilder $container;

    private LockServiceExtension $extension;

    /**
     * 测试加载服务
     */
    public function testLoadRegistersExpectedServices(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
        $this->extension = new LockServiceExtension();

        $this->extension->load([], $this->container);

        // 验证主要服务被注册
        $this->assertTrue($this->container->hasDefinition(LockService::class));
        $this->assertTrue($this->container->hasDefinition(RedisClusterStore::class));
        $this->assertTrue($this->container->hasDefinition(SmartLockStore::class));
        $this->assertTrue($this->container->hasDefinition('Symfony\Component\Lock\Store\FlockStore'));
        $this->assertTrue($this->container->hasDefinition('Symfony\Component\Lock\Store\DoctrineDbalStore'));
        // 现在检查我们实际定义的服务
        $this->assertTrue($this->container->hasDefinition('lock_service.lock_factory'));
        // 检查别名是否存在
        $this->assertTrue($this->container->hasAlias('Symfony\Component\Lock\LockFactory'));
    }

    /**
     * 测试服务定义的标签
     */
    public function testLoadConfiguresServiceTags(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
        $this->extension = new LockServiceExtension();

        $this->extension->load([], $this->container);

        // 验证 Store 服务有 lock.store 标签
        $flockStoreDefinition = $this->container->getDefinition('Symfony\Component\Lock\Store\FlockStore');
        $this->assertTrue($flockStoreDefinition->hasTag('lock.store'));
        $this->assertTrue($flockStoreDefinition->isLazy());

        $redisStoreDefinition = $this->container->getDefinition(RedisClusterStore::class);
        $this->assertTrue($redisStoreDefinition->hasTag('lock.store'));
        // RedisClusterStore 已经通过 #[Autoconfigure(lazy: true)] 设置了 lazy

        $dbalStoreDefinition = $this->container->getDefinition('Symfony\Component\Lock\Store\DoctrineDbalStore');
        $this->assertTrue($dbalStoreDefinition->hasTag('lock.store'));
        $this->assertTrue($dbalStoreDefinition->isLazy());

        $smartStoreDefinition = $this->container->getDefinition(SmartLockStore::class);
        $this->assertTrue($smartStoreDefinition->hasTag('lock.store'));
        // SmartLockStore 已经通过 #[Autoconfigure(lazy: true)] 设置了 lazy
    }

    /**
     * 测试 LockService 服务配置
     */
    public function testLoadConfiguresLockService(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
        $this->extension = new LockServiceExtension();

        $this->extension->load([], $this->container);

        $lockServiceDefinition = $this->container->getDefinition(LockService::class);

        $this->assertTrue($lockServiceDefinition->isAutowired());
        $this->assertTrue($lockServiceDefinition->isAutoconfigured());
        $this->assertFalse($lockServiceDefinition->isPublic());
    }

    /**
     * 测试 LockFactory 服务配置
     */
    public function testLoadConfiguresLockFactory(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
        $this->extension = new LockServiceExtension();

        $this->extension->load([], $this->container);

        // 使用我们实际定义的服务名称
        $lockFactoryDefinition = $this->container->getDefinition('lock_service.lock_factory');

        // 检查服务是否正确配置
        $arguments = $lockFactoryDefinition->getArguments();
        $this->assertCount(1, $arguments);

        // 检查是否配置了 setLogger 调用
        $calls = $lockFactoryDefinition->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertNotEmpty($calls);
        $firstCall = $calls[0];
        $this->assertIsArray($firstCall);
        $this->assertNotEmpty($firstCall);
        $this->assertEquals('setLogger', $firstCall[0]);
    }

    /**
     * 测试在测试环境下的配置
     */
    public function testLoadInTestEnvironment(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
        $this->extension = new LockServiceExtension();

        $this->extension->load([], $this->container);

        // 在测试环境下，lock_service.logger 使用 LoggerProvider factory
        $loggerDefinition = $this->container->getDefinition('lock_service.logger');
        $this->assertEquals('Psr\Log\LoggerInterface', $loggerDefinition->getClass());

        // 检查 factory 配置是否正确
        $factory = $loggerDefinition->getFactory();
        $this->assertNotNull($factory);
        $this->assertEquals('Tourze\LockServiceBundle\Service\LoggerProvider', $factory[0]);
        $this->assertEquals('getLogger', $factory[1]);
    }
}
