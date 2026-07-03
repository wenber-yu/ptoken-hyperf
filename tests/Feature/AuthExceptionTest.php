<?php

declare(strict_types=1);

namespace PToken\Hyperf\Tests\Feature;

use Hyperf\Server\Exception\ServerException;
use PToken\Hyperf\Tests\TestCase;
use Wenbo\PToken\Hyperf\Exceptions\PTokenAuthException;

/**
 * PTokenAuthException 异常类测试。
 */
final class AuthExceptionTest extends TestCase
{
    public function testAuthExceptionExtendsServerException(): void
    {
        $exception = new PTokenAuthException('test');

        $this->assertInstanceOf(ServerException::class, $exception);
        $this->assertSame('test', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
    }

    public function testAuthExceptionDefaultMessage(): void
    {
        $exception = new PTokenAuthException();

        $this->assertSame('Authentication failed', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
    }
}
