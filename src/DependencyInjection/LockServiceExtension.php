<?php

namespace Tourze\LockServiceBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class LockServiceExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependRedis($container);
        $this->prependDoctrine($container);
    }

    /**
     * Redis配置
     */
    private function prependRedis(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('snc_redis', [
            'clients' => [
                'lock' => [
                    'type' => 'phpredis',
                    'alias' => 'lock',
                    'dsn' => $_ENV['LOCK_REDIS_URL'] ?? $_ENV['REDIS_URL'] ?? 'redis://127.0.0.1:6379',
                    'logging' => false,
                ],
            ],
        ]);
    }

    /**
     * DBAL配置
     */
    private function prependDoctrine(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'connections' => [
                    'lock' => [
                        'url' => $_ENV['LOCK_DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? 'sqlite:///:memory:',

                        // When true, queries are logged to a "doctrine" monolog channel
                        'logging' => $container->resolveEnvPlaceholders("%kernel.debug%"),
                        'profiling' => $container->resolveEnvPlaceholders("%kernel.debug%"),

                        // When true, profiling also collects a backtrace for each query
                        'profiling_collect_backtrace' => true,

                        //  When true, profiling also collects schema errors for each query
                        'profiling_collect_schema_errors' => true,

                        'use_savepoints' => true,
                        'mapping_types' => [
                            'enum' => 'string',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
