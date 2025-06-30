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
    ) {}

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
     * @param LockEntity|string|array<mixed> $entity
     */
    public function blockingRun(LockEntity|string|array $entity, callable $callback): mixed
    {
        $resources = [];
        if (is_array($entity)) {
            foreach ($entity as $item) {
                $k = $this->getLockKey($item);
                if (empty($k)) {
                    continue;
                }
                $resources[] = $k;
            }
            $resources = array_values(array_unique($resources));
            sort($resources);
        } else {
            $resources[] = $this->getLockKey($entity);
        }

        // 如果这个锁已经被当前业务拿了，那我们不要继续锁
        foreach ($resources as $k => $resource) {
            if (isset($this->currentLocks[$resource])) {
                unset($resources[$k]);
            }
        }

        // 没有锁就直接执行
        if (empty($resources)) {
            return call_user_func($callback);
        }

        // 有多个锁，那么我们要同时锁了
        /** @var LockInterface[] $lockArr */
        $lockArr = [];

        try {
            foreach ($resources as $resource) {
                $lockArr[$resource] = $lock = $this->lockFactory->createLock($resource);

                // 尝试获取锁，最多重试3次
                $maxRetries = 3;
                $retryCount = 0;
                $acquired = false;

                while ($retryCount < $maxRetries) {
                    if ($lock->acquire(true)) {
                        $acquired = true;
                        break;
                    }
                    $retryCount++;
                    if ($retryCount < $maxRetries) {
                        usleep(100000); // 休眠 0.1 秒后重试
                    }
                }

                if (!$acquired) {
                    throw new LockAcquisitionException($resource, $maxRetries);
                }
                $this->logger->debug('加锁成功' . $resource);
            }

            // 执行到这里，代表所有锁都拿到了
            foreach ($resources as $resource) {
                $this->currentLocks[$resource] = time();
            }

            return call_user_func($callback);
        } finally {
            foreach ($lockArr as $resource => $acquiredLock) {
                try {
                    if (!$acquiredLock->isAcquired()) {
                        continue;
                    }
                    $acquiredLock->release();
                    $this->logger->debug('释放锁成功' . $resource);
                } catch (LockReleasingException $e) {
                    $this->logger->error('释放锁失败', [
                        'exception' => $e,
                        'lock' => $acquiredLock,
                        'resource' => $resource,
                        'resources' => $resources,
                    ]);
                }
            }

            // 去除当前锁
            foreach ($resources as $resource) {
                unset($this->currentLocks[$resource]);
            }
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
            $retryCount++;
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
