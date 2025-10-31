<?php

namespace Tourze\LockServiceBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[WithMonologChannel(channel: 'lock')]
readonly class LoggerProvider
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getLogger(): LoggerInterface
    {
        // 检查是否在测试环境中运行
        if ($this->isTestEnvironment()) {
            return new NullLogger();
        }

        return $this->logger;
    }

    private function isTestEnvironment(): bool
    {
        // 优先级1: 明确的测试环境变量
        if ('test' === ($_ENV['APP_ENV'] ?? null) || filter_var($_ENV['DISABLE_LOGGING_IN_TESTS'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        // 优先级2: PHPUnit 相关检测
        if (defined('PHPUNIT_VERSION')) {
            return true;
        }

        // 优先级3: 命令行参数检测（仅在没有明确环境变量时）
        if (isset($_SERVER['argv']) && str_contains(implode(' ', $_SERVER['argv']), 'phpunit')) {
            return true;
        }

        // 优先级4: 如果既不是明确的 prod/dev 环境，又检测到 PHPUnit 类被加载，可能是测试
        if (!in_array($_ENV['APP_ENV'] ?? 'dev', ['prod', 'dev'], true) && class_exists('\PHPUnit\Framework\TestCase', false)) {
            return true;
        }

        // 优先级5: 检查Paratest环境（通过TEST_TOKEN环境变量）
        if (($_ENV['TEST_TOKEN'] ?? '') !== '' || ($_ENV['UNIQUE_TEST_TOKEN'] ?? '') !== '') {
            return true;
        }

        return false;
    }
}
