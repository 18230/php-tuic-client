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
            ->setDescription('Start the local TUIC SOCKS5 proxy server')
            ->addOption('config', null, Option::VALUE_OPTIONAL, 'TUIC 配置文件路径', 'config/tuic.yaml')
            ->addOption('listen', null, Option::VALUE_OPTIONAL, '本地 SOCKS5 代理监听地址', '127.0.0.1:1080')
            ->addOption('allow-ip', null, Option::VALUE_OPTIONAL, '允许访问本地代理的来源 IP 或 CIDR', '127.0.0.1');
    }

    protected function execute(Input $input, Output $output): int
    {
        $script = root_path() . 'vendor/18230/php-tuic-client/bin/tuic-client';
        $config = $this->resolvePath((string) $input->getOption('config'));
        $listen = (string) $input->getOption('listen');
        $allowIp = (string) $input->getOption('allow-ip');

        if (!is_file($script)) {
            $output->writeln("<error>Package script not found: {$script}</error>");

            return self::FAILURE;
        }

        if (!is_file($config)) {
            $output->writeln("<error>Config file not found: {$config}</error>");

            return self::FAILURE;
        }

        $command = $this->buildCommand($script, $config, $listen, $allowIp);

        $output->writeln("Starting TUIC proxy with config: {$config}");
        $output->writeln("SOCKS5 listen: {$listen}");
        $output->writeln("Allow IP: {$allowIp}");

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
