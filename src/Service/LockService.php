<?php

namespace Tourze\LockServiceBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Contracts\Service\ResetInterface;
use Tourze\LockServiceBundle\Exception\LockAcquisitionException;
use Tourze\LockServiceBundle\Model\LockEntity;

/**
 * 跟请求上下文绑定的锁服务
 */
#[AutoconfigureTag(name: 'as-coroutine')]
class LockService implements ResetInterface
{
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @var array|LockInterface[]
     */
    private array $existLocks = [];

    private function getLockKey(LockEntity|string $entity): string
    {
        if ($entity instanceof LockEntity) {
            return $entity->retrieveLockResource();
        }

        return strval($entity);
    }

    /**
     * @var array<string, int> 记录当前正在执行的锁，用于减少重复的锁
     */
    private array $currentLocks = [];

    /**
     * 带锁执行指定逻辑
     * 当有同样锁的逻辑在执行时，系统会暂停等待
     *
     * @param LockEntity|string|array<LockEntity|string> $entity
     */
    public function blockingRun(LockEntity|string|array $entity, callable $callback): mixed
    {
        $resources = $this->prepareResources($entity);
        $resources = $this->filterExistingLocks($resources);

        if ([] === $resources) {
            return call_user_func($callback);
        }

        return $this->executeWithLocks($resources, $callback);
    }

    /**
     * 准备资源列表
     *
     * @param LockEntity|string|array<LockEntity|string> $entity
     *
     * @return array<string>
     */
    private function prepareResources(LockEntity|string|array $entity): array
    {
        $resources = [];

        if (is_array($entity)) {
            foreach ($entity as $item) {
                $k = $this->getLockKey($item);
                if ('' === $k) {
                    continue;
                }
                $resources[] = $k;
            }
            $resources = array_values(array_unique($resources));
            sort($resources);
        } else {
            $resources[] = $this->getLockKey($entity);
        }

        return $resources;
    }

    /**
     * 过滤掉已存在的锁
     *
     * @param array<string> $resources
     *
     * @return array<string>
     */
    private function filterExistingLocks(array $resources): array
    {
        foreach ($resources as $k => $resource) {
            if (isset($this->currentLocks[$resource])) {
                unset($resources[$k]);
            }
        }

        return $resources;
    }

    /**
     * 带锁执行回调
     *
     * @param array<string> $resources
     */
    private function executeWithLocks(array $resources, callable $callback): mixed
    {
        $lockArr = $this->acquireAllLocks($resources);

        try {
            $this->markLocksAsAcquired($resources);

            return call_user_func($callback);
        } finally {
            $this->releaseAllLocks($lockArr, $resources);
            $this->unmarkLocks($resources);
        }
    }

    /**
     * 获取所有需要的锁
     *
     * @param array<string> $resources
     *
     * @return array<string, LockInterface>
     */
    private function acquireAllLocks(array $resources): array
    {
        $lockArr = [];

        foreach ($resources as $resource) {
            $lock = $this->lockFactory->createLock($resource);
            $lockArr[$resource] = $lock;

            if (!$this->acquireLockWithRetry($lock)) {
                throw new LockAcquisitionException($resource, 3);
            }

            if (!filter_var($_ENV['DISABLE_LOGGING_IN_TESTS'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->logger->debug('加锁成功' . $resource);
            }
        }

        return $lockArr;
    }

    /**
     * 带重试机制获取锁
     */
    private function acquireLockWithRetry(LockInterface $lock): bool
    {
        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            if ($lock->acquire(true)) {
                return true;
            }
            ++$retryCount;
            if ($retryCount < $maxRetries) {
                usleep(100000); // 休眠 0.1 秒后重试
            }
        }

        return false;
    }

    /**
     * 标记锁为已获取
     *
     * @param array<string> $resources
     */
    private function markLocksAsAcquired(array $resources): void
    {
        foreach ($resources as $resource) {
            $this->currentLocks[$resource] = time();
        }
    }

    /**
     * 释放所有锁
     *
     * @param array<string, LockInterface> $lockArr
     * @param array<string>                $resources
     */
    private function releaseAllLocks(array $lockArr, array $resources): void
    {
        foreach ($lockArr as $resource => $acquiredLock) {
            try {
                if (!$acquiredLock->isAcquired()) {
                    continue;
                }
                $acquiredLock->release();
                if (!filter_var($_ENV['DISABLE_LOGGING_IN_TESTS'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $this->logger->debug('释放锁成功' . $resource);
                }
            } catch (LockReleasingException $e) {
                $this->logger->error('释放锁失败', [
                    'exception' => $e,
                    'lock' => $acquiredLock,
                    'resource' => $resource,
                    'resources' => $resources,
                ]);
            }
        }
    }

    /**
     * 取消锁标记
     *
     * @param array<string> $resources
     */
    private function unmarkLocks(array $resources): void
    {
        foreach ($resources as $resource) {
            unset($this->currentLocks[$resource]);
        }
    }

    /**
     * 请求级别加锁
     */
    public function acquireLock(string $key): LockInterface
    {
        $lock = $this->lockFactory->createLock($key, autoRelease: false);

        // 尝试获取锁，最多重试3次
        $maxRetries = 3;
        $retryCount = 0;
        $acquired = false;

        while ($retryCount < $maxRetries) {
            if ($lock->acquire(true)) {
                $acquired = true;
                break;
            }
            ++$retryCount;
            if ($retryCount < $maxRetries) {
                usleep(100000); // 休眠 0.1 秒后重试
            }
        }

        if (!$acquired) {
            throw new LockAcquisitionException($key, $maxRetries);
        }

        $this->existLocks[$key] = $lock;

        return $lock;
    }

    /**
     * 请求级别释放锁
     */
    public function releaseLock(string $key): void
    {
        $lock = $this->existLocks[$key] ?? $this->lockFactory->createLock($key, autoRelease: false);
        if ($lock->isAcquired()) {
            $lock->release();
        }
        unset($this->existLocks[$key]);
    }

    /**
     * 自动释放所有锁
     */
    #[AsEventListener(event: KernelEvents::TERMINATE, priority: 999)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE, priority: 999)]
    #[AsEventListener(event: WorkerStoppedEvent::class, priority: 999)]
    #[AsEventListener(event: WorkerRunningEvent::class, priority: 999)]
    public function reset(): void
    {
        $this->currentLocks = [];

        // 这里要强制释放喔
        foreach ($this->existLocks as $lock) {
            try {
                if ($lock->isAcquired()) {
                    $lock->release();
                }
            } catch (\Throwable $exception) {
                $this->logger->error('自动释放锁失败', [
                    'lock' => $lock,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
