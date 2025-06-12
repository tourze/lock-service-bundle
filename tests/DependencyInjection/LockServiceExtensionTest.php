<?php

namespace Tourze\LockServiceBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\LockServiceBundle\DependencyInjection\LockServiceExtension;
use Tourze\LockServiceBundle\Service\LockService;
use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\LockServiceBundle\Store\SmartLockStore;

/**
 * 测试 LockServiceExtension
 */
class LockServiceExtensionTest extends TestCase
{
    private ContainerBuilder $container;
    private LockServiceExtension $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->extension = new LockServiceExtension();
    }

    /**
     * 测试加载服务
     */
    public function testLoad_registersExpectedServices(): void
    {
        $this->extension->load([], $this->container);

        // 验证主要服务被注册
        $this->assertTrue($this->container->hasDefinition(LockService::class));
        $this->assertTrue($this->container->hasDefinition(RedisClusterStore::class));
        $this->assertTrue($this->container->hasDefinition(SmartLockStore::class));
        $this->assertTrue($this->container->hasDefinition('Symfony\Component\Lock\Store\FlockStore'));
        $this->assertTrue($this->container->hasDefinition('Symfony\Component\Lock\Store\DoctrineDbalStore'));
        $this->assertTrue($this->container->hasDefinition('Symfony\Component\Lock\LockFactory'));
    }

    /**
     * 测试 prepend 方法配置 Redis
     */
    public function testPrepend_configuresRedisClient(): void
    {
        $this->extension->prepend($this->container);

        $redisConfig = $this->container->getExtensionConfig('snc_redis');

        $this->assertNotEmpty($redisConfig);
        $this->assertArrayHasKey('clients', $redisConfig[0]);
        $this->assertArrayHasKey('lock', $redisConfig[0]['clients']);

        $lockClientConfig = $redisConfig[0]['clients']['lock'];
        $this->assertEquals('phpredis', $lockClientConfig['type']);
        $this->assertEquals('lock', $lockClientConfig['alias']);
        $this->assertFalse($lockClientConfig['logging']);
    }

    /**
     * 测试 prepend 方法配置 Doctrine DBAL
     */
    public function testPrepend_configuresDoctrineConnection(): void
    {
        $this->extension->prepend($this->container);

        $doctrineConfig = $this->container->getExtensionConfig('doctrine');

        $this->assertNotEmpty($doctrineConfig);
        $this->assertArrayHasKey('dbal', $doctrineConfig[0]);
        $this->assertArrayHasKey('connections', $doctrineConfig[0]['dbal']);
        $this->assertArrayHasKey('lock', $doctrineConfig[0]['dbal']['connections']);

        $lockConnectionConfig = $doctrineConfig[0]['dbal']['connections']['lock'];
        $this->assertTrue($lockConnectionConfig['use_savepoints']);
        $this->assertTrue($lockConnectionConfig['profiling_collect_backtrace']);
        $this->assertArrayHasKey('mapping_types', $lockConnectionConfig);
        $this->assertEquals('string', $lockConnectionConfig['mapping_types']['enum']);
    }

    /**
     * 测试服务定义的标签
     */
    public function testLoad_configuresServiceTags(): void
    {
        $this->extension->load([], $this->container);

        // 验证 Store 服务有 lock.store 标签
        $flockStoreDefinition = $this->container->getDefinition('Symfony\Component\Lock\Store\FlockStore');
        $this->assertTrue($flockStoreDefinition->hasTag('lock.store'));
        $this->assertTrue($flockStoreDefinition->isLazy());

        $redisStoreDefinition = $this->container->getDefinition(RedisClusterStore::class);
        $this->assertTrue($redisStoreDefinition->hasTag('lock.store'));
        $this->assertTrue($redisStoreDefinition->isLazy());

        $dbalStoreDefinition = $this->container->getDefinition('Symfony\Component\Lock\Store\DoctrineDbalStore');
        $this->assertTrue($dbalStoreDefinition->hasTag('lock.store'));
        $this->assertTrue($dbalStoreDefinition->isLazy());

        $smartStoreDefinition = $this->container->getDefinition(SmartLockStore::class);
        $this->assertTrue($smartStoreDefinition->hasTag('lock.store'));
        $this->assertTrue($smartStoreDefinition->isLazy());
    }

    /**
     * 测试 LockService 服务配置
     */
    public function testLoad_configuresLockService(): void
    {
        $this->extension->load([], $this->container);

        $lockServiceDefinition = $this->container->getDefinition(LockService::class);

        $this->assertTrue($lockServiceDefinition->isAutowired());
        $this->assertTrue($lockServiceDefinition->isAutoconfigured());
        $this->assertFalse($lockServiceDefinition->isPublic());
    }

    /**
     * 测试 LockFactory 服务配置
     */
    public function testLoad_configuresLockFactory(): void
    {
        $this->extension->load([], $this->container);

        $lockFactoryDefinition = $this->container->getDefinition('Symfony\Component\Lock\LockFactory');

        $this->assertTrue($lockFactoryDefinition->isLazy());

        $arguments = $lockFactoryDefinition->getArguments();
        $this->assertCount(1, $arguments);
    }
}
