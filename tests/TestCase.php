<?php

declare(strict_types=1);

namespace PToken\Hyperf\Tests;

use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use Mockery;
use Throwable;

/**
 * PToken Hyperf 测试基类。
 *
 * 使用官方 hyperf/testing 组件的 RunTestsInCoroutine trait，
 * 每个测试方法自动运行在 Swoole 协程上下文中（如果 swoole 扩展已加载）。
 * 同时自动在 tearDown 中清理 Mockery。
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use RunTestsInCoroutine;

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } catch (Throwable) {
        }

        parent::tearDown();
    }
}
