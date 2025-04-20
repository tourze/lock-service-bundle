<?php

namespace Tourze\LockServiceBundle\Store;

use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;
use Symfony\Component\Lock\Store\ExpiringStoreTrait;

/**
 * 兼容Redis集群，主要是阿里云那种
 */
class RedisClusterStore implements SharedLockStoreInterface
{
    use ExpiringStoreTrait;

    private bool $supportTime;

    /**
     * @param float $initialTtl The expiration delay of locks in seconds
     */
    public function __construct(
        private \Redis $redis,
        private float $initialTtl = 300.0,
    ) {
        if ($initialTtl <= 0) {
            throw new InvalidTtlException(sprintf('"%s()" expects a strictly positive TTL. Got %d.', __METHOD__, $initialTtl));
        }
    }

    public function save(Key $key): void
    {
        $script = '
            local uniqueToken = ARGV[2]
            local ttl = tonumber(ARGV[3])

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", KEYS[1]).ok == "string" then
                return false
            end

            ' . $this->getNowCode() . '

            -- Remove expired values
            redis.call("ZREMRANGEBYSCORE", KEYS[1], "-inf", now)

            -- is already acquired
            if redis.call("ZSCORE", KEYS[1], uniqueToken) then
                -- is not WRITE lock and cannot be promoted
                if not redis.call("ZSCORE", KEYS[1], "__write__") and redis.call("ZCOUNT", KEYS[1], "-inf", "+inf") > 1  then
                    return false
                end
            elseif redis.call("ZCOUNT", KEYS[1], "-inf", "+inf") > 0  then
                return false
            end

            redis.call("ZADD", KEYS[1], now + ttl, uniqueToken)
            redis.call("ZADD", KEYS[1], now + ttl, "__write__")

            -- Extend the TTL of the key
            local maxExpiration = redis.call("ZREVRANGE", KEYS[1], 0, 0, "WITHSCORES")[2]
            redis.call("PEXPIREAT", KEYS[1], maxExpiration)

            return true
        ';

        $key->reduceLifetime($this->initialTtl);
        if (!$this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key), (int) ceil($this->initialTtl * 1000)])) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    public function saveRead(Key $key): void
    {
        $script = '
            local uniqueToken = ARGV[2]
            local ttl = tonumber(ARGV[3])

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", KEYS[1]).ok == "string" then
                return false
            end

            ' . $this->getNowCode() . '

            -- Remove expired values
            redis.call("ZREMRANGEBYSCORE", KEYS[1], "-inf", now)

            -- lock not already acquired and a WRITE lock exists?
            if not redis.call("ZSCORE", KEYS[1], uniqueToken) and redis.call("ZSCORE", KEYS[1], "__write__") then
                return false
            end

            redis.call("ZADD", KEYS[1], now + ttl, uniqueToken)
            redis.call("ZREM", KEYS[1], "__write__")

            -- Extend the TTL of the key
            local maxExpiration = redis.call("ZREVRANGE", KEYS[1], 0, 0, "WITHSCORES")[2]
            redis.call("PEXPIREAT", KEYS[1], maxExpiration)

            return true
        ';

        $key->reduceLifetime($this->initialTtl);
        if (!$this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key), (int) ceil($this->initialTtl * 1000)])) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        $script = '
            local uniqueToken = ARGV[2]
            local ttl = tonumber(ARGV[3])

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", KEYS[1]).ok == "string" then
                return false
            end

            ' . $this->getNowCode() . '

            -- lock already acquired acquired?
            if not redis.call("ZSCORE", KEYS[1], uniqueToken) then
                return false
            end

            redis.call("ZADD", KEYS[1], now + ttl, uniqueToken)
            -- if the lock is also a WRITE lock, increase the TTL
            if redis.call("ZSCORE", KEYS[1], "__write__") then
                redis.call("ZADD", KEYS[1], now + ttl, "__write__")
            end

            -- Extend the TTL of the key
            local maxExpiration = redis.call("ZREVRANGE", KEYS[1], 0, 0, "WITHSCORES")[2]
            redis.call("PEXPIREAT", KEYS[1], maxExpiration)

            return true
        ';

        $key->reduceLifetime($ttl);
        if (!$this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key), (int) ceil($ttl * 1000)])) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    public function delete(Key $key): void
    {
        $script = '
            local uniqueToken = ARGV[1]

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", KEYS[1]).ok == "string" then
                return false
            end

            -- lock not already acquired
            if not redis.call("ZSCORE", KEYS[1], uniqueToken) then
                return false
            end

            redis.call("ZREM", KEYS[1], uniqueToken)
            redis.call("ZREM", KEYS[1], "__write__")

            local maxExpiration = redis.call("ZREVRANGE", KEYS[1], 0, 0, "WITHSCORES")[2]
            if nil ~= maxExpiration then
                redis.call("PEXPIREAT", KEYS[1], maxExpiration)
            end

            return true
        ';

        $this->evaluate($script, (string) $key, [$this->getUniqueToken($key)]);
    }

    public function exists(Key $key): bool
    {
        $script = '
            local uniqueToken = ARGV[2]

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", KEYS[1]).ok == "string" then
                return false
            end

            ' . $this->getNowCode() . '

            -- Remove expired values
            redis.call("ZREMRANGEBYSCORE", KEYS[1], "-inf", now)

            if redis.call("ZSCORE", KEYS[1], uniqueToken) then
                return true
            end

            return false
        ';

        return (bool) $this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key)]);
    }

    private function evaluate(string $script, string $resource, array $args): mixed
    {
        $this->redis->clearLastError();
        $result = $this->redis->eval($script, array_merge([$resource], $args), 1);
        if (null !== $err = $this->redis->getLastError()) {
            throw new LockStorageException($err);
        }

        return $result;
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }

    private function getNowCode(): string
    {
        if (!isset($this->supportTime)) {
            // Redis < 5.0 does not support TIME (not deterministic) in script.
            // https://redis.io/commands/eval#replicating-commands-instead-of-scripts
            // This code asserts TIME can be use, otherwise will fallback to a timestamp generated by the PHP process.
            $script = '
                local now = redis.call("TIME")
                redis.call("SET", KEYS[1], "1", "PX", 1)

	            return 1
            ';
            try {
                $this->supportTime = 1 === $this->evaluate($script, 'symfony_check_support_time', []);
            } catch (LockStorageException $e) {
                if (!str_contains($e->getMessage(), 'commands not allowed after non deterministic')) {
                    throw $e;
                }
                $this->supportTime = false;
            }
        }

        if ($this->supportTime) {
            return '
                local now = redis.call("TIME")
                now = now[1] * 1000 + math.floor(now[2] / 1000)
            ';
        }

        return '
            local now = tonumber(ARGV[1])
            now = math.floor(now * 1000)
        ';
    }
}
