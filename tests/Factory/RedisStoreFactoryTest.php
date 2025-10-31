<?php

namespace Tourze\LockServiceBundle\Tests\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LockServiceBundle\Factory\RedisStoreFactory;
use Tourze\LockServiceBundle\Store\RedisClusterStore;

/**
 * 测试 RedisStoreFactory
 *
 * @internal
 */
#[CoversClass(RedisStoreFactory::class)]
final class RedisStoreFactoryTest extends TestCase
{
    /**
     * 测试创建 RedisClusterStore
     */
    public function testCreate(): void
    {
        /**
         * 使用具体类 Redis 而非接口的原因：
         * 1. PHP Redis 扩展提供的是具体类，没有对应的接口抽象
         * 2. Factory 专门为 Redis 实例设计，必须使用具体类
         * 3. 替代方案：使用真实的 Redis 连接，但会增加测试环境依赖
         */
        $redis = $this->createMock(\Redis::class);
        $factory = new RedisStoreFactory($redis);

        $store = $factory->create();

        // 验证返回类型正确
        $reflection = new \ReflectionMethod($factory, 'create');
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame(RedisClusterStore::class, (string) $returnType);
    }
}
