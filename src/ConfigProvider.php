<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf;

use Wenbo\PToken\Hyperf\Commands\ConfigCommand;
use Wenbo\PToken\Hyperf\Commands\KeyCommand;
use Wenbo\PToken\PToken;
use Wenbo\PToken\PTokenConfig;
use Wenbo\PToken\Hyperf\CacheDrivers\PTokenCacheDriver;
use Psr\SimpleCache\CacheInterface;

/**
 * PToken Hyperf ConfigProvider。
 *
 * 遵循 Hyperf 约定自动注册：
 *   - PToken 依赖绑定（从 config/autoload/ptoken.php 读取配置）
 *   - 注解扫描路径（PTokenAuth 用于标记跳过认证的控制器/方法）
 *   - 命令注册
 *   - 配置文件发布
 */
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                PToken::class => function ($container) {
                    $configArray = $container
                        ->get(\Hyperf\Contract\ConfigInterface::class)
                        ->get('ptoken', []);

                    $config = PTokenConfig::fromArray($configArray);

                    /** @var CacheInterface $cache */
                    $cache  = $container->get(CacheInterface::class);
                    $driver = new PTokenCacheDriver($cache);

                    return new PToken($config, $driver);
                },
            ],

            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__ . '/../Annotations',
                    ],
                ],
            ],

            'commands' => [
                KeyCommand::class,
                ConfigCommand::class,
            ],

            'publish' => [
                [
                    'id'          => 'ptoken',
                    'description' => 'PToken configuration file',
                    'source'      => __DIR__ . '/../../config/ptoken.php',
                    'destination' => (defined('BASE_PATH') ? BASE_PATH : '') . '/config/autoload/ptoken.php',
                ],
            ],
        ];
    }
}
