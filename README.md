# PToken Hyperf

PToken 的 Hyperf 集成包，为 Hyperf 应用提供服务端 Token 认证能力。支持中间件认证、`#[PTokenAuth(exclude: true)]` 排除标记、User Model 自动关联等功能。

## 环境要求

- PHP >= 8.3
- Hyperf >= 3.0
- `wenber-yu/ptoken-core`

## 安装

```bash
composer require wenber-yu/ptoken-hyperf
```

## ConfigProvider 自动发现

包内置 `ConfigProvider`，Hyperf 启动时自动完成以下注册，无需手动配置：

- **依赖注入**：`PToken::class` 绑定（从 `config/autoload/ptoken.php` 读取配置，注入 `CacheDriver`）
- **注解扫描**：自动扫描 `Annotations` 目录（用于 `#[PTokenAuth(exclude: true)]` 排除标记）
- **命令注册**：`ptoken:key`、`ptoken:config`
- **配置发布**：将默认配置发布到 `config/autoload/ptoken.php`

## 生成密钥

```bash
# 仅生成并显示密钥
php bin/hyperf.php ptoken:key

# 生成密钥并自动写入 .env 文件
php bin/hyperf.php ptoken:key --env
```

`.env` 中配置：

```
PTOKEN_ENCRYPT_KEY=your-32-bytes-hex-key-here
```

## 发布配置

```bash
php bin/hyperf.php ptoken:config
```

配置文件将发布到 `config/autoload/ptoken.php`。若文件已存在则不会覆盖。

## 配置说明

配置文件 `config/autoload/ptoken.php` 关键项：

```php
return [
    'cache_pre_key'   => 'ptoken:',         // 缓存键前缀（使用 Hyperf 缓存）
    'timeout'         => 604800,            // Token 有效期（秒），默认 7 天
    'max_refresh'     => 86400,             // 自动续期窗口（秒）
    'encrypt_key'     => env('PTOKEN_ENCRYPT_KEY', '...'),
    'multi_login'     => false,             // 多端登录
    'user_model'      => null,              // User Model 类名，如 'App\Model\User'
    'auth_exclude_paths' => [
        '/api/login',
        '/api/register',
    ],
];
```

## 中间件认证

通过 `PTokenMiddleware` 全局拦截请求，自动校验 Token。

在 `config/autoload/middlewares.php` 中注册全局中间件：

```php
return [
    'http' => [
        \Wenbo\PToken\Hyperf\Middleware\PTokenMiddleware::class,
    ],
];
```

## 排除公开接口

有以下两种方式排除不需要认证的接口（如登录、注册）。

### 方式一：`#[PTokenAuth(exclude: true)]` 注解

在方法或类上标注排除：

```php
namespace App\Controller;

use Wenbo\PToken\Hyperf\Annotations\PTokenAuth;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;

#[Controller(prefix: '/api')]
class AuthController
{
    // 登录接口，免认证
    #[PTokenAuth(exclude: true)]
    #[RequestMapping(path: 'login', methods: 'post')]
    public function login()
    {
        // ...
    }
}

// 也可在类级别标记，整个控制器的所有方法都免认证
#[PTokenAuth(exclude: true)]
#[Controller(prefix: '/api/public')]
class PublicController
{
    // 所有方法均跳过认证
}
```

### 方式二：配置 `auth_exclude_paths`

在 `config/autoload/ptoken.php` 中配置路径前缀：

```php
'auth_exclude_paths' => [
    '/api/login',
    '/api/register',
],
```

> 中间件会同时检查注解排除和路径排除，任一匹配即跳过认证。

## 完整示例

### 登录

```php
#[PTokenAuth(exclude: true)]
#[RequestMapping(path: 'login', methods: 'post')]
public function login()
{
    $request = \Hyperf\Context\Context::get(\Psr\Http\Message\ServerRequestInterface::class);
    $body = $request->getParsedBody();

    // 验证用户名密码...
    $user = User::where('email', $body['email'])->first();
    if (!$user || !password_verify($body['password'], $user->password)) {
        return ['code' => 401, 'message' => '认证失败'];
    }

    $ptoken = \Hyperf\Context\ApplicationContext::getContainer()->get(\Wenbo\PToken\PToken::class);
    $token = $ptoken->generate((string)$user->id, [
        'name'  => $user->name,
        'email' => $user->email,
    ]);

    return ['token' => $token];
}
```

### 获取当前用户

```php
#[RequestMapping(path: 'me', methods: 'get')]
public function me()
{
    $request = \Hyperf\Context\Context::get(\Psr\Http\Message\ServerRequestInterface::class);
    $tokenUser = $request->getAttribute('ptokenUser');

    // 方式一：直接从 data 中取
    $data = $request->getAttribute('ptokenData');

    // 方式二：自动关联 User Model（需配置 user_model）
    $user = $tokenUser->getUser();

    return [
        'userKey' => $request->getAttribute('ptokenUserKey'),
        'data'    => $data,
        'user'    => $user,
    ];
}
```

### 登出

```php
#[RequestMapping(path: 'logout', methods: 'post')]
public function logout()
{
    $request = \Hyperf\Context\Context::get(\Psr\Http\Message\ServerRequestInterface::class);
    $ptoken = \Hyperf\Context\ApplicationContext::getContainer()->get(\Wenbo\PToken\PToken::class);

    $header = $request->getHeaderLine('Authorization');
    $token = str_replace('Bearer ', '', $header);

    $ptoken->destroy($token);

    return ['message' => '已登出'];
}
```

## 认证失败处理

认证失败时，中间件抛出 `PTokenAuthException`，可在全局异常处理器中统一捕获：

```php
use Wenbo\PToken\Hyperf\Exceptions\PTokenAuthException;

// app/Exception/Handler/AppExceptionHandler.php
public function handle(Throwable $throwable, ResponseInterface $response)
{
    if ($throwable instanceof PTokenAuthException) {
        return $response->withStatus(401)->withBody(
            new \Hyperf\HttpMessage\Stream\SwooleStream(json_encode([
                'code'    => 401,
                'message' => $throwable->getMessage(),
            ]))
        );
    }

    return $response;
}
```

## User Model 关联

配置 `user_model` 后，认证通过时自动通过 Hyperf Container 查询关联 User Model：

```php
// config/autoload/ptoken.php
'user_model' => \App\Model\User::class,

// 控制器中
$tokenUser = $request->getAttribute('ptokenUser');
$user = $tokenUser->getUser();  // App\Model\User 实例（懒加载）
```

`getUser()` 首次调用时通过 Container 获取 Model 实例并执行 `find($userKey)`，后续调用直接返回缓存实例。需要安装 `hyperf/database`：

```bash
composer require hyperf/database
```

也可手动设置：

```php
$tokenUser->setUser($customUser);
```

## 命令行工具

| 命令 | 说明 |
| --- | --- |
| `php bin/hyperf.php ptoken:key` | 生成 32 字节加密密钥 |
| `php bin/hyperf.php ptoken:key --env` | 生成密钥并自动写入 `.env` |
| `php bin/hyperf.php ptoken:config` | 发布配置文件到 `config/autoload/ptoken.php` |

## 安全建议

1. **生产环境必须更换 `encrypt_key`**，使用 `php bin/hyperf.php ptoken:key --env` 生成
2. 将 `PTOKEN_ENCRYPT_KEY` 写入 `.env`，不要硬编码在配置文件中
3. 启用 HTTPS 防止 Token 被窃取
4. 根据安全策略合理设置 `timeout` 和 `max_refresh`
5. `auth_exclude_paths` 中确保登录、注册等公开接口正确配置

## 相关链接

- [PToken Core](https://github.com/wenber-yu/ptoken-core) — 核心库文档
- [PToken Laravel](https://github.com/wenber-yu/ptoken-laravel) — Laravel 集成包

## 许可证

MIT
