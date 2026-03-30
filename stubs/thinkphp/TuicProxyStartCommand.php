<?php declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class TuicProxyStartCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('tuic:proxy-start')
            ->setDescription('Start the local TUIC HTTP and SOCKS5 proxy server')
            ->addOption('config', null, Option::VALUE_OPTIONAL, 'TUIC 配置文件路径', 'config/tuic.yaml')
            ->addOption('http-listen', null, Option::VALUE_OPTIONAL, '本地 HTTP 代理监听地址', '127.0.0.1:8080')
            ->addOption('socks-listen', null, Option::VALUE_OPTIONAL, '本地 SOCKS5 代理监听地址', '127.0.0.1:1080');
    }

    protected function execute(Input $input, Output $output): int
    {
        $script = root_path() . 'vendor/18230/php-tuic-client/bin/tuic-client';
        $config = $this->resolvePath((string) $input->getOption('config'));
        $httpListen = (string) $input->getOption('http-listen');
        $socksListen = (string) $input->getOption('socks-listen');

        if (!is_file($script)) {
            $output->writeln("<error>Package script not found: {$script}</error>");

            return self::FAILURE;
        }

        if (!is_file($config)) {
            $output->writeln("<error>Config file not found: {$config}</error>");

            return self::FAILURE;
        }

        $command = $this->buildCommand($script, $config, $httpListen, $socksListen);

        $output->writeln("Starting TUIC proxy with config: {$config}");
        $output->writeln("HTTP listen: {$httpListen}");
        $output->writeln("SOCKS5 listen: {$socksListen}");

        passthru($command, $exitCode);

        return $exitCode;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return root_path() . 'config/tuic.yaml';
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return root_path() . ltrim($path, '/\\');
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
