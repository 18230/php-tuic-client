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
        {--http-listen=127.0.0.1:8080 : 本地 HTTP 代理监听地址}
        {--socks-listen=127.0.0.1:1080 : 本地 SOCKS5 代理监听地址}';

    protected $description = 'Start the local TUIC HTTP and SOCKS5 proxy server';

    public function handle(): int
    {
        $script = base_path('vendor/18230/php-tuic-client/bin/tuic-client');
        $config = $this->resolvePath((string) $this->option('config'));
        $httpListen = (string) $this->option('http-listen');
        $socksListen = (string) $this->option('socks-listen');

        if (!is_file($script)) {
            $this->error("Package script not found: {$script}");

            return self::FAILURE;
        }

        if (!is_file($config)) {
            $this->error("Config file not found: {$config}");

            return self::FAILURE;
        }

        $command = $this->buildCommand($script, $config, $httpListen, $socksListen);

        $this->line("Starting TUIC proxy with config: {$config}");
        $this->line("HTTP listen: {$httpListen}");
        $this->line("SOCKS5 listen: {$socksListen}");

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

    private function buildCommand(string $script, string $config, string $httpListen, string $socksListen): string
    {
        return implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            '--config=' . escapeshellarg($config),
            '--http-listen=' . escapeshellarg($httpListen),
            '--socks-listen=' . escapeshellarg($socksListen),
        ]);
    }
}
