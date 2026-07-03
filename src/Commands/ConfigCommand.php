<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf\Commands;

use Hyperf\Command\Command as HyperfCommand;

class ConfigCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('ptoken:config');
    }

    public function configure(): void
    {
        $this->setDescription('将 PToken 默认配置文件发布到 config/autoload/ptoken.php');
    }

    public function handle(): int
    {
        $source = __DIR__ . '/../../../config/ptoken.php';
        $target = (defined('BASE_PATH') ? BASE_PATH : '') . '/config/autoload/ptoken.php';

        if (!file_exists($source)) {
            $this->error("源配置文件不存在：{$source}");
            return self::FAILURE;
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $this->error("无法创建目录：{$targetDir}");
            return self::FAILURE;
        }

        if (file_exists($target)) {
            $this->warn("配置文件已存在：{$target}");
            $this->warn('如需覆盖请先手动删除该文件');
            return self::FAILURE;
        }

        copy($source, $target);
        $this->info("配置文件已发布到：{$target}");

        return self::SUCCESS;
    }
}
