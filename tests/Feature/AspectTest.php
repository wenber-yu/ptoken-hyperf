<?php

declare(strict_types=1);

namespace PToken\Hyperf\Tests\Feature;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PToken\Hyperf\Tests\TestCase;
use ReflectionClass;
use Wenbo\PToken\Hyperf\Annotations\PTokenAuth;
use Wenbo\PToken\PToken;
use Wenbo\PToken\PTokenConfig;
use Wenbo\PToken\Hyperf\Exceptions\PTokenAuthException;
use Wenbo\PToken\Hyperf\Middleware\PTokenMiddleware;

/**
 * PTokenAuth 注解及中间件注解跳过场景测试。
 */
final class AspectTest extends TestCase
{
    // ─── 注解类测试 ──────────────────────────────────────────────────

    public function testAnnotationCanBeInstantiatedWithDefaultValues(): void
    {
        $annotation = new PTokenAuth();

        $this->assertFalse($annotation->exclude);
    }

    public function testAnnotationWithExcludeTrue(): void
    {
        $annotation = new PTokenAuth(exclude: true);

        $this->assertTrue($annotation->exclude);
    }

    public function testAnnotationIsAnAttribute(): void
    {
        $ref = new ReflectionClass(PTokenAuth::class);
        $attrs = $ref->getAttributes();

        $this->assertNotEmpty($attrs);
        $this->assertSame(Attribute::class, $attrs[0]->getName());
    }

    public function testAnnotationExtendsAbstractAnnotation(): void
    {
        $annotation = new PTokenAuth();

        $this->assertInstanceOf(AbstractAnnotation::class, $annotation);
    }

    public function testExcludeTrueAnnotationSemantics(): void
    {
        $annotation = new PTokenAuth(exclude: true);
        $this->assertTrue($annotation->exclude);

        $annotation2 = new PTokenAuth(exclude: false);
        $this->assertFalse($annotation2->exclude);

        $annotation3 = new PTokenAuth();
        $this->assertFalse($annotation3->exclude);
    }

    // ─── 中间件注解跳过场景 ──────────────────────────────────────────

    private PTokenConfig $config;
    private PToken $ptoken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new PTokenConfig();
        $this->config->auth_exclude_paths = [];
        $this->config->timeout = 604800;
        $this->config->encrypt_key = '12345678901234567890123456789012';

        $this->ptoken = Mockery::mock(PToken::class);
        $this->ptoken->shouldReceive('getConfig')->andReturn($this->config);
    }

    /** @param array|string $callback */
    private function makeRoute(mixed $callback): object
    {
        $route = Mockery::mock();
        $route->shouldReceive('isFound')->andReturn(true);
        $route->handler = (object) ['callback' => $callback];
        return $route;
    }

    private function makeRequest(array $overrides = []): ServerRequestInterface
    {
        $header     = $overrides['header'] ?? '';
        $path       = $overrides['path'] ?? '/api/test';
        $queryParams = $overrides['queryParams'] ?? [];
        $routeAttr  = $overrides['route'] ?? $this->makeRoute(['FakeController', 'fakeMethod']);

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

    public function testCallbackNotArrayThrowsException(): void
    {
        // callback 不是 [class, method] 数组时，无法读注解，进入正常认证流程
        // 由于没有 token 且始终抛异常，这里应抛出 PTokenAuthException
        $request = $this->makeRequest([
            'header' => '',
            'route'  => $this->makeRoute('not_an_array'),
        ]);

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $middleware = new PTokenMiddleware($this->ptoken);

        $this->expectException(PTokenAuthException::class);
        $this->expectExceptionMessage('Token is missing');

        $middleware->process($request, $handler);
    }
}
