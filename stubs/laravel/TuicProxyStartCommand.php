<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TuicProxyStartCommand extends Command
{
    /**
     * 一行命令启动本地 TUIC 代理。
     *
     * 用法：
     * php artisan tuic:proxy-start
     */
    protected $signature = 'tuic:proxy-start
        {--config=config/tuic.yaml : TUIC 配置文件路径}
        {--listen=127.0.0.1:1080 : 本地 SOCKS5 代理监听地址}
        {--allow-ip=127.0.0.1 : 允许访问本地代理的来源 IP 或 CIDR}';

    protected $description = 'Start the local TUIC SOCKS5 proxy server';

    public function handle(): int
    {
        $script = base_path('vendor/18230/php-tuic-client/bin/tuic-client');
        $config = $this->resolvePath((string) $this->option('config'));
        $listen = (string) $this->option('listen');
        $allowIp = (string) $this->option('allow-ip');

        if (!is_file($script)) {
            $this->error("Package script not found: {$script}");

            return self::FAILURE;
        }

        if (!is_file($config)) {
            $this->error("Config file not found: {$config}");

            return self::FAILURE;
        }

        $command = $this->buildCommand($script, $config, $listen, $allowIp);

        $this->line("Starting TUIC proxy with config: {$config}");
        $this->line("SOCKS5 listen: {$listen}");
        $this->line("Allow IP: {$allowIp}");

        passthru($command, $exitCode);

        return $exitCode;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return base_path('config/tuic.yaml');
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function buildCommand(string $script, string $config, string $listen, string $allowIp): string
    {
        return implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            '--config=' . escapeshellarg($config),
            '--listen=' . escapeshellarg($listen),
            '--allow-ip=' . escapeshellarg($allowIp),
        ]);
    }
}
