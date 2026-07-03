<?php

declare(strict_types=1);

namespace PToken\Hyperf\Tests\Feature;

use Mockery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PToken\Hyperf\Tests\TestCase;
use Wenbo\PToken\PToken;
use Wenbo\PToken\PTokenConfig;
use Wenbo\PToken\Hyperf\Exceptions\PTokenAuthException;
use Wenbo\PToken\Hyperf\Middleware\PTokenMiddleware;

/**
 * PTokenMiddleware 中间件单元测试。
 *
 * 使用 Mockery 模拟 PSR-7 接口，通过 RunTestsInCoroutine trait
 * 自动为每个测试提供 Swoole 协程上下文。
 */
final class MiddlewareTest extends TestCase
{
    private PTokenConfig $config;
    private PToken $ptoken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new PTokenConfig();
        $this->config->auth_exclude_paths = [];
        $this->config->timeout = 604800;
        $this->config->max_refresh = 86400;
        $this->config->encrypt_key = '12345678901234567890123456789012';

        $this->ptoken = Mockery::mock(PToken::class);
        $this->ptoken->shouldReceive('getConfig')->andReturn($this->config);
    }

    // ─── 辅助方法 ────────────────────────────────────────────────────

    private function makeFoundRoute(): object
    {
        $route = Mockery::mock();
        $route->shouldReceive('isFound')->andReturn(true);
        $route->handler = (object) ['callback' => 'not_an_array'];
        return $route;
    }

    private function makeRequest(array $overrides = []): ServerRequestInterface
    {
        $header = $overrides['header'] ?? '';
        $path = $overrides['path'] ?? '/api/test';
        $queryParams = $overrides['queryParams'] ?? [];
        $routeAttr = $overrides['route'] ?? $this->makeFoundRoute();

        $uri = Mockery::mock(UriInterface::class);
        $uri->shouldReceive('getPath')->andReturn($path);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')
            ->with('Hyperf\HttpServer\Router\Dispatched')
            ->andReturn($routeAttr);
        $request->shouldReceive('getHeaderLine')
            ->with('Authorization')
            ->andReturn($header);
        $request->shouldReceive('getQueryParams')->andReturn($queryParams);
        $request->shouldReceive('getUri')->andReturn($uri);

        return $request;
    }

    // ─── 测试用例 ────────────────────────────────────────────────────

    public function testNoAuthorizationHeaderThrowsException(): void
    {
        $request = $this->makeRequest(['header' => '']);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $middleware = new PTokenMiddleware($this->ptoken);

        $this->expectException(PTokenAuthException::class);
        $this->expectExceptionMessage('Token is missing');

        $middleware->process($request, $handler);
    }

    public function testInvalidTokenThrowsException(): void
    {
        $this->ptoken->shouldReceive('get')->with('bad-token')->andReturn(null);

        $request = $this->makeRequest(['header' => 'Bearer bad-token']);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $middleware = new PTokenMiddleware($this->ptoken);

        $this->expectException(PTokenAuthException::class);
        $this->expectExceptionMessage('Token is invalid or expired');

        $middleware->process($request, $handler);
    }

    public function testValidTokenCallsHandlerWithPtokenUserAttribute(): void
    {
        $tokenData = [
            'userKey'  => 'user_123',
            'data'     => ['role' => 'admin'],
            'createAt' => time(),
            'expireAt' => time() + 3600,
        ];

        $this->ptoken->shouldReceive('get')->with('valid-token')->andReturn($tokenData);

        $psrResponse = Mockery::mock(ResponseInterface::class);

        $request = $this->makeRequest(['header' => 'Bearer valid-token']);
        $request->shouldReceive('withAttribute')->andReturnSelf();

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with($request)
            ->andReturn($psrResponse);

        $middleware = new PTokenMiddleware($this->ptoken);
        $response = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $response);
    }

    public function testExcludedPathSkipsAuth(): void
    {
        $this->config->auth_exclude_paths = ['/api/public'];

        $request = $this->makeRequest(['header' => '', 'path' => '/api/public/info']);
        $psrResponse = Mockery::mock(ResponseInterface::class);

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturn($psrResponse);

        $middleware = new PTokenMiddleware($this->ptoken);
        $response = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $response);
    }

    public function testMultiLoginPassesThroughMiddlewareNormally(): void
    {
        $this->config->multi_login = true;

        $tokenData = [
            'userKey'  => 'user_456',
            'data'     => null,
            'createAt' => time(),
            'expireAt' => time() + 7200,
        ];

        $this->ptoken->shouldReceive('get')->with('multi-token')->andReturn($tokenData);

        $psrResponse = Mockery::mock(ResponseInterface::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturn($psrResponse);

        $request = $this->makeRequest(['header' => 'Bearer multi-token']);
        $request->shouldReceive('withAttribute')->andReturnSelf();

        $middleware = new PTokenMiddleware($this->ptoken);
        $response = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $response);
    }

    public function testNullRoutePassesThrough(): void
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')
            ->with('Hyperf\HttpServer\Router\Dispatched')
            ->andReturn(null);

        $psrResponse = Mockery::mock(ResponseInterface::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($request)->andReturn($psrResponse);

        $middleware = new PTokenMiddleware($this->ptoken);
        $response = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $response);
    }

    public function testTokenFromQueryString(): void
    {
        $tokenData = [
            'userKey'  => 'user_qs',
            'data'     => null,
            'createAt' => time(),
            'expireAt' => time() + 3600,
        ];

        $this->ptoken->shouldReceive('get')->with('qs-token')->andReturn($tokenData);

        $psrResponse = Mockery::mock(ResponseInterface::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturn($psrResponse);

        $request = $this->makeRequest(['header' => '', 'queryParams' => ['token' => 'qs-token']]);
        $request->shouldReceive('withAttribute')->andReturnSelf();

        $middleware = new PTokenMiddleware($this->ptoken);
        $response = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $response);
    }
}
