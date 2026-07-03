<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf\Middleware;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wenbo\PToken\Hyperf\Annotations\PTokenAuth;
use Wenbo\PToken\Hyperf\Exceptions\PTokenAuthException;
use Wenbo\PToken\Hyperf\PTokenUser;
use Wenbo\PToken\PToken;

class PTokenMiddleware implements MiddlewareInterface
{
    private const string TOKEN_HEADER = 'Authorization';
    private const string TOKEN_PREFIX = 'Bearer ';

    public function __construct(
        private readonly PToken $ptoken,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldSkip($request)) {
            return $handler->handle($request);
        }

        $token = $this->extractToken($request);
        if ($token === null) {
            throw new PTokenAuthException('Token is missing');
        }

        $tokenData = $this->ptoken->get($token);
        if ($tokenData === null) {
            throw new PTokenAuthException('Token is invalid or expired');
        }

        $request = $this->injectTokenUser($request, $tokenData);

        return $handler->handle($request);
    }

    private function shouldSkip(ServerRequestInterface $request): bool
    {
        $dispatched = $request->getAttribute(Dispatched::class);

        // 无有效路由（如 404）→ 放行，由框架自行处理
        if ($dispatched === null || !$dispatched->isFound()) {
            return true;
        }

        // 注解标记跳过：方法或类上的 #[PTokenAuth(exclude: true)]
        if ($this->hasSkipAnnotation($dispatched)) {
            return true;
        }

        // 配置路径跳过
        if ($this->isPathSkipped($request)) {
            return true;
        }

        return false;
    }

    /**
     * 检查路由方法或类上是否有 #[PTokenAuth(exclude: true)] 注解。
     */
    private function hasSkipAnnotation(object $dispatched): bool
    {
        $callback = $dispatched->handler->callback;

        if (!is_array($callback) || count($callback) !== 2) {
            return false;
        }

        /** @var array{0: class-string, 1: string} $callback */
        [$class, $method] = $callback;

        if (!is_string($class) || !class_exists($class)) {
            return false;
        }

        $methodAnnotation = AnnotationCollector::getClassMethodAnnotation($class, $method);
        $classAnnotation  = AnnotationCollector::getClassAnnotation($class, PTokenAuth::class);

        return (isset($methodAnnotation[PTokenAuth::class]) && $methodAnnotation[PTokenAuth::class]->exclude)
            || ($classAnnotation instanceof PTokenAuth && $classAnnotation->exclude);
    }

    private function isPathSkipped(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        foreach ($this->ptoken->getConfig()->auth_exclude_paths as $excludePath) {
            if (str_starts_with($path, $excludePath)) {
                return true;
            }
        }

        return false;
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine(self::TOKEN_HEADER);

        if ($header !== '') {
            return str_starts_with($header, self::TOKEN_PREFIX)
                ? substr($header, strlen(self::TOKEN_PREFIX))
                : $header;
        }

        return $request->getQueryParams()['token'] ?? null;
    }

    private function injectTokenUser(ServerRequestInterface $request, array $tokenData): ServerRequestInterface
    {
        $config = $this->ptoken->getConfig();

        $tokenUser = new PTokenUser(
            $tokenData['tokenId'] ?? '',
            $tokenData['userKey'],
            $tokenData['data'],
            $tokenData['abilities'] ?? ['*'],
            $tokenData['createAt'],
            $tokenData['expireAt'],
            $config->user_model,
        );

        return $request
            ->withAttribute('ptokenUser', $tokenUser)
            ->withAttribute('ptokenUserKey', $tokenData['userKey'])
            ->withAttribute('ptokenData', $tokenData['data'])
            ->withAttribute('ptokenAbilities', $tokenData['abilities'] ?? ['*']);
    }
}
