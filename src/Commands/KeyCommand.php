<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf\Commands;

use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputOption;

class KeyCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('ptoken:key');
    }

    public function configure(): void
    {
        $this->setDescription('生成一个 32 字节的随机加密密钥，用于 PToken AES-256-CBC 加密');
        $this->addOption('env', null, InputOption::VALUE_NONE, '同时更新 .env 文件中的 PTOKEN_ENCRYPT_KEY');
    }

    public function handle(): int
    {
        $key = bin2hex(random_bytes(32));

        $this->line("<info>生成的密钥（32 字节）：</info>");
        $this->line("  <comment>{$key}</comment>");

        if ($this->input->getOption('env')) {
            $envPath = BASE_PATH . '/.env';

            if (!file_exists($envPath)) {
                $this->error('.env 文件不存在');
                return self::FAILURE;
            }

            $envContent = file_get_contents($envPath);

            if (str_contains($envContent, 'PTOKEN_ENCRYPT_KEY=')) {
                $envContent = preg_replace(
                    '/^PTOKEN_ENCRYPT_KEY=.*/m',
                    "PTOKEN_ENCRYPT_KEY={$key}",
                    $envContent
                );
                $this->info('.env 中的 PTOKEN_ENCRYPT_KEY 已更新');
            } else {
                $envContent .= "\nPTOKEN_ENCRYPT_KEY={$key}\n";
                $this->info('.env 中已追加 PTOKEN_ENCRYPT_KEY');
            }

            file_put_contents($envPath, $envContent);
        } else {
            $this->warn('提示：添加 --env 参数可自动更新 .env 文件');
        }

        return self::SUCCESS;
    }
}
