<?php

namespace Tourze\LockServiceBundle\Tests\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Lock\Store\FlockStore;
use Tourze\LockServiceBundle\Store\RedisClusterStore;
use Tourze\LockServiceBundle\Store\SmartLockStore;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * SmartLockStore 集成测试
 *
 * @internal
 */
#[CoversClass(SmartLockStore::class)]
#[RunTestsInSeparateProcesses]
final class SmartLockStoreTest extends AbstractIntegrationTestCase
{
    /**
     * 测试设置前的准备工作
     */
    protected function onSetUp(): void
    {
        // 清理可能存在的环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 获取 mock 对象的调用记录
     * 此方法用于避免 PHPStan 对匿名类方法的类型检查错误
     *
     * @param object $mock
     *
     * @return array<array{method: string, args: array<mixed>}>
     */
    private function getCallsFrom(object $mock): array
    {
        // @phpstan-ignore method.notFound, return.type
        return $mock->getCalls();
    }

    /**
     * 设置 RedisClusterStore mock 的返回值
     * 此方法用于避免 PHPStan 对匿名类方法的类型检查错误
     *
     * @param object $mock
     */
    private function setMockReturnValue(object $mock, bool $value): void
    {
        // @phpstan-ignore method.notFound
        $mock->willReturn($value);
    }

    /**
     * 创建 RedisClusterStore 的测试替代类
     */
    private function createRedisClusterStoreMock(): object
    {
        return new /**
         * @method array<array{method: string, args: array<mixed>}> getCalls()
         * @method void willReturn(bool $value)
         */ class extends RedisClusterStore {
            /** @var array<array{method: string, args: array<mixed>}> */
            private array $calls = [];

            private bool $willReturnValue = true;

            public function __construct()
            {
                // 在集成测试中，我们可以创建一个基本的 Redis 连接
                // 如果 Redis 不可用，我们跳过测试
                try {
                    $redis = new \Redis();
                    $redis->connect('127.0.0.1', 6379, 0.1); // 短超时
                    $redis->select(0); // 使用默认数据库
                } catch (\Exception $e) {
                    // 如果连接失败，创建一个简单的 Redis 模拟
                    $redis = $this->createRedisMock();
                }
                parent::__construct($redis, 300.0);
            }

            private function createRedisMock(): \Redis
            {
                // 由于不能直接实例化 Redis，我们使用反射来创建一个实例
                $redis = new \ReflectionClass(\Redis::class);

                return $redis->newInstanceWithoutConstructor();
            }

            public function save(Key $key): void
            {
                $this->calls[] = ['method' => 'save', 'args' => [$key]];
            }

            public function delete(Key $key): void
            {
                $this->calls[] = ['method' => 'delete', 'args' => [$key]];
            }

            public function exists(Key $key): bool
            {
                $this->calls[] = ['method' => 'exists', 'args' => [$key]];

                return $this->willReturnValue;
            }

            public function putOffExpiration(Key $key, $ttl): void
            {
                $this->calls[] = ['method' => 'putOffExpiration', 'args' => [$key, $ttl]];
            }

            /**
             * @return array<array{method: string, args: array<mixed>}>
             */
            public function getCalls(): array
            {
                return $this->calls;
            }

            public function willReturn(bool $value): void
            {
                $this->willReturnValue = $value;
            }
        };
    }

    /**
     * 创建 DoctrineDbalStore 的测试替代类
     */
    private function createDoctrineDbalStoreMock(): object
    {
        return new /**
         * @method array<array{method: string, args: array<mixed>}> getCalls()
         */ class extends DoctrineDbalStore {
            /** @var array<array{method: string, args: array<mixed>}> */
            private array $calls = [];

            // @phpstan-ignore constructor.missingParentCall
            public function __construct()
            {
                // 不调用父类构造函数，避免数据库依赖
            }

            public function save(Key $key): void
            {
                $this->calls[] = ['method' => 'save', 'args' => [$key]];
            }

            public function delete(Key $key): void
            {
                $this->calls[] = ['method' => 'delete', 'args' => [$key]];
            }

            public function exists(Key $key): bool
            {
                $this->calls[] = ['method' => 'exists', 'args' => [$key]];

                return true;
            }

            public function putOffExpiration(Key $key, $ttl): void
            {
                $this->calls[] = ['method' => 'putOffExpiration', 'args' => [$key, $ttl]];
            }

            /**
             * @return array<array{method: string, args: array<mixed>}>
             */
            public function getCalls(): array
            {
                return $this->calls;
            }
        };
    }

    /**
     * 创建 FlockStore 的测试替代类
     */
    private function createFlockStoreMock(): object
    {
        return new /**
         * @method array<array{method: string, args: array<mixed>}> getCalls()
         */ class extends FlockStore {
            /** @var array<array{method: string, args: array<mixed>}> */
            private array $calls = [];

            // @phpstan-ignore constructor.missingParentCall
            public function __construct()
            {
                // 不调用父类构造函数，避免文件系统依赖
            }

            public function save(Key $key): void
            {
                $this->calls[] = ['method' => 'save', 'args' => [$key]];
            }

            public function delete(Key $key): void
            {
                $this->calls[] = ['method' => 'delete', 'args' => [$key]];
            }

            public function exists(Key $key): bool
            {
                $this->calls[] = ['method' => 'exists', 'args' => [$key]];

                return true;
            }

            public function putOffExpiration(Key $key, $ttl): void
            {
                $this->calls[] = ['method' => 'putOffExpiration', 'args' => [$key, $ttl]];
            }

            /**
             * @return array<array{method: string, args: array<mixed>}>
             */
            public function getCalls(): array
            {
                return $this->calls;
            }
        };
    }

    /**
     * 测试默认使用文件锁
     */
    public function testConstructorUsesFlockStoreByDefault(): void
    {
        /*
         * 使用具体类 RedisClusterStore 而非接口的原因：
         * 1. RedisClusterStore 是自定义锁存储实现，没有对应的接口抽象
         * 2. SmartLockStore 需要根据不同的存储类型选择具体实现
         * 3. 替代方案：为所有锁存储创建统一接口，但会增加架构复杂度
         */
        $redisClusterStore = $this->createRedisClusterStoreMock();
        /*
         * 使用具体类 DoctrineDbalStore 而非接口的原因：
         * 1. Symfony Lock 组件的 DoctrineDbalStore 是具体实现，没有接口
         * 2. SmartLockStore 需要与 Symfony 组件保持兼容性
         * 3. 替代方案：使用真实的数据库连接，但会增加测试复杂度
         */
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        /*
         * 使用具体类 FlockStore 而非接口的原因：
         * 1. Symfony Lock 组件的 FlockStore 是具体实现，没有接口
         * 2. 文件锁是操作系统级别的实现，使用具体类更符合实际情况
         * 3. 替代方案：使用真实的文件锁，但会增加测试环境依赖
         */
        $flockStore = $this->createFlockStoreMock();

        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $smartStore->save($key);

        // 验证 FlockStore 的 save 方法被调用
        $calls = $this->getCallsFrom($flockStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('save', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($redisClusterStore));
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));
    }

    /**
     * 测试使用 Redis 锁
     */
    public function testConstructorUsesRedisStoreForRedisType(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'redis';
        $_SERVER['APP_LOCK_TYPE'] = 'redis';
        putenv('APP_LOCK_TYPE=redis');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $smartStore->save($key);

        // 验证 RedisClusterStore 的 save 方法被调用
        $calls = $this->getCallsFrom($redisClusterStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('save', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));
        $this->assertEmpty($this->getCallsFrom($flockStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试使用 Redis 集群锁
     */
    public function testConstructorUsesRedisStoreForRedisClusterType(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'redis-cluster';
        $_SERVER['APP_LOCK_TYPE'] = 'redis-cluster';
        putenv('APP_LOCK_TYPE=redis-cluster');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $smartStore->save($key);

        // 验证 RedisClusterStore 的 save 方法被调用
        $calls = $this->getCallsFrom($redisClusterStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('save', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));
        $this->assertEmpty($this->getCallsFrom($flockStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试使用数据库锁
     */
    public function testConstructorUsesDbalStoreForDbalType(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'dbal';
        $_SERVER['APP_LOCK_TYPE'] = 'dbal';
        putenv('APP_LOCK_TYPE=dbal');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $smartStore->save($key);

        // 验证 DoctrineDbalStore 的 save 方法被调用
        $calls = $this->getCallsFrom($doctrineDbalStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('save', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($redisClusterStore));
        $this->assertEmpty($this->getCallsFrom($flockStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试删除锁
     */
    public function testDeleteDelegatesToInnerStore(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'redis';
        $_SERVER['APP_LOCK_TYPE'] = 'redis';
        putenv('APP_LOCK_TYPE=redis');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $smartStore->delete($key);

        // 验证 RedisClusterStore 的 delete 方法被调用
        $calls = $this->getCallsFrom($redisClusterStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('delete', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));
        $this->assertEmpty($this->getCallsFrom($flockStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试检查锁是否存在
     */
    public function testExistsDelegatesToInnerStore(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'redis';
        $_SERVER['APP_LOCK_TYPE'] = 'redis';
        putenv('APP_LOCK_TYPE=redis');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');

        // 设置 exists 方法返回 true
        $this->setMockReturnValue($redisClusterStore, true);

        $result = $smartStore->exists($key);

        $this->assertTrue($result);

        // 验证 RedisClusterStore 的 exists 方法被调用
        $calls = $this->getCallsFrom($redisClusterStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('exists', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));
        $this->assertEmpty($this->getCallsFrom($flockStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试延长锁过期时间
     */
    public function testPutOffExpirationDelegatesToInnerStore(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'redis';
        $_SERVER['APP_LOCK_TYPE'] = 'redis';
        putenv('APP_LOCK_TYPE=redis');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $ttl = 300.0;

        $smartStore->putOffExpiration($key, $ttl);

        // 验证 RedisClusterStore 的 putOffExpiration 方法被调用
        $calls = $this->getCallsFrom($redisClusterStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('putOffExpiration', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);
        $this->assertEquals($ttl, $calls[0]['args'][1]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));
        $this->assertEmpty($this->getCallsFrom($flockStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试 save 方法委托给内部存储
     */
    public function testSaveDelegatesToInnerStore(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'redis';
        $_SERVER['APP_LOCK_TYPE'] = 'redis';
        putenv('APP_LOCK_TYPE=redis');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $smartStore->save($key);

        // 验证 RedisClusterStore 的 save 方法被调用
        $calls = $this->getCallsFrom($redisClusterStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('save', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));
        $this->assertEmpty($this->getCallsFrom($flockStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }

    /**
     * 测试无效的锁类型回退到文件锁
     */
    public function testConstructorFallsBackToFlockStoreForInvalidType(): void
    {
        $redisClusterStore = $this->createRedisClusterStoreMock();
        $doctrineDbalStore = $this->createDoctrineDbalStoreMock();
        $flockStore = $this->createFlockStoreMock();

        $_ENV['APP_LOCK_TYPE'] = 'invalid-type';
        $_SERVER['APP_LOCK_TYPE'] = 'invalid-type';
        putenv('APP_LOCK_TYPE=invalid-type');

        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass, argument.type
        $smartStore = new SmartLockStore($redisClusterStore, $doctrineDbalStore, $flockStore);

        $key = new Key('test-key');
        $smartStore->save($key);

        // 验证 FlockStore 的 save 方法被调用（回退到文件锁）
        $calls = $this->getCallsFrom($flockStore);
        $this->assertCount(1, $calls);
        $this->assertEquals('save', $calls[0]['method']);
        $this->assertEquals($key, $calls[0]['args'][0]);

        // 验证其他存储没有被调用
        $this->assertEmpty($this->getCallsFrom($redisClusterStore));
        $this->assertEmpty($this->getCallsFrom($doctrineDbalStore));

        // 清理环境变量
        unset($_ENV['APP_LOCK_TYPE']);
        $_SERVER['APP_LOCK_TYPE'] = null;
        putenv('APP_LOCK_TYPE');
    }
}
