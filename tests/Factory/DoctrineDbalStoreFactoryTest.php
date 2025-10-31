<?php

namespace Tourze\LockServiceBundle\Tests\Factory;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Tourze\LockServiceBundle\Factory\DoctrineDbalStoreFactory;

/**
 * 测试 DoctrineDbalStoreFactory
 *
 * @internal
 */
#[CoversClass(DoctrineDbalStoreFactory::class)]
final class DoctrineDbalStoreFactoryTest extends TestCase
{
    /**
     * 测试创建 DoctrineDbalStore
     */
    public function testCreate(): void
    {
        /**
         * 使用具体类 Connection 而非接口的原因：
         * 1. Doctrine DBAL Connection 是基础设施组件，不需要抽象接口
         * 2. Factory 专门为 Connection 类型设计，使用具体类更符合实际用法
         * 3. 替代方案：使用真实的数据库连接，但会增加测试复杂度和依赖
         */
        $connection = $this->createMock(Connection::class);
        $factory = new DoctrineDbalStoreFactory($connection);

        $store = $factory->create();

        // 验证返回类型正确
        $reflection = new \ReflectionMethod($factory, 'create');
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame(DoctrineDbalStore::class, (string) $returnType);
    }
}
