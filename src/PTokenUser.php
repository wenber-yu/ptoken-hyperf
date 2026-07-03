<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf;

/**
 * Hyperf 版 TokenUser，通过配置的 userModel 类名自动关联 User Model。
 *
 * 使用方式：
 *   1. 配置 'userModel' => \App\Model\User::class
 *   2. 控制器中：$request->getAttribute('ptokenUser')->getUser() 获取 User Model
 */
class PTokenUser extends \Wenbo\PToken\PTokenUser
{
    /**
     * 配置的 User Model 类名（FQCN），null 表示不自动关联。
     */
    private readonly ?string $userModelClass;

    /**
     * 缓存的 User Model 实例。
     */
    private mixed $resolvedUser = null;

    /**
     * 是否已尝试解析过 User Model。
     */
    private bool $resolved = false;

    /**
     * @param string        $token_id        Token 唯一标识
     * @param string        $user_key        用户标识
     * @param mixed         $data            用户关联数据
     * @param array<string> $abilities       Token 能力/作用域
     * @param int           $create_at       Token 创建时间
     * @param int           $expire_at       Token 过期时间
     * @param string|null   $userModelClass  User Model 类名（FQCN），null 时不自动关联
     * @param array{ip: string, user_agent: string, device_name: string}|null $device 设备信息
     */
    public function __construct(
        string $token_id,
        string $user_key,
        mixed $data,
        array $abilities,
        int $create_at,
        int $expire_at,
        ?string $userModelClass = null,
        ?array $device = null,
    ) {
        parent::__construct($token_id, $user_key, $data, $abilities, $create_at, $expire_at, $device);
        $this->userModelClass = $userModelClass;
    }

    /**
     * 获取关联的 User Model（懒加载）。
     *
     * 首次调用时通过 Hyperf Model findFromCache/find 查询数据库，后续调用返回缓存实例。
     *
     * @return mixed User Model 实例，未配置 userModel 或查询失败时返回 null
     */
    public function getUser(): mixed
    {
        if ($this->resolved) {
            return $this->resolvedUser;
        }

        $this->resolved = true;

        if ($this->userModelClass === null || !class_exists($this->userModelClass)) {
            return null;
        }

        $this->resolvedUser = $this->userModelClass::findFromCache($this->user_key)
                           ?? $this->userModelClass::find($this->user_key);

        return $this->resolvedUser;
    }

    /**
     * 手动设置 User Model 实例（用于自定义查询逻辑）。
     *
     * @param mixed $user User Model 实例
     */
    public function setUser(mixed $user): void
    {
        $this->resolvedUser = $user;
        $this->resolved     = true;
    }

    /**
     * 检查 User Model 是否已解析。
     */
    public function hasUser(): bool
    {
        return $this->resolved;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->resolved && $this->resolvedUser !== null && method_exists($this->resolvedUser, 'toArray')) {
            $data['user'] = $this->resolvedUser->toArray();
        }

        return $data;
    }
}
