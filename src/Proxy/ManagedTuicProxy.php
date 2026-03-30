<?php declare(strict_types=1);

namespace PhpTuic\Proxy;

use PhpTuic\Support\Platform;

// 用子进程托管本地代理服务。
// 业务层只关心“要一个本地代理地址”，不需要自己管理生命周期。
final class ManagedTuicProxy
{
    /** @var resource|false|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $stderrBuffer = '';
    private readonly int $httpPort;
    private readonly int $socksPort;

    public function __construct(
        private readonly string $configPath,
        private readonly ?string $nodeName = null,
        ?int $httpPort = null,
        ?int $socksPort = null,
        private readonly float $startupTimeout = 10.0,
        private readonly ?string $phpBinary = null,
    ) {
        $this->httpPort = $httpPort ?? $this->reservePort();
        $this->socksPort = $socksPort ?? $this->reservePort();
    }

    public function start(): void
    {
        if ($this->isRunning()) {
            return;
        }

        // 这里拉起的是项目自带的 tuic-proxy-server.php。
        $command = [
            $this->phpBinary ?? PHP_BINARY,
            $this->projectRoot() . '/bin/tuic-proxy-server.php',
            '--config=' . $this->configPath,
            '--http-listen=127.0.0.1:' . $this->httpPort,
            '--socks-listen=127.0.0.1:' . $this->socksPort,
        ];

        if ($this->nodeName !== null && $this->nodeName !== '') {
            $command[] = '--node-name=' . $this->nodeName;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', Platform::nullDevice(), 'a'],
            2 => ['pipe', 'w'],
        ];

        $this->process = proc_open($command, $descriptors, $pipes, $this->projectRoot());
        if (!\is_resource($this->process)) {
            $this->process = null;
            throw new \RuntimeException('Failed to start local TUIC proxy process.');
        }

        $this->pipes = $pipes;
        fclose($this->pipes[0]);
        stream_set_blocking($this->pipes[2], false);

        // 通过读取子进程 stderr，等待两个监听端口真正起来。
        $deadline = microtime(true) + $this->startupTimeout;
        while (microtime(true) < $deadline) {
            $this->drainStderr();

            if (!$this->isRunning()) {
                $error = trim($this->stderrBuffer);
                $this->cleanupProcess();
                throw new \RuntimeException(
                    'Local TUIC proxy exited during startup.'
                    . ($error !== '' ? ' ' . $error : '')
                );
            }

            if (
                str_contains($this->stderrBuffer, 'HTTP proxy listening on')
                && str_contains($this->stderrBuffer, 'SOCKS5 proxy listening on')
            ) {
                return;
            }

            usleep(100_000);
        }

        $error = trim($this->stderrBuffer);
        $this->stop();
        throw new \RuntimeException(
            'Timed out waiting for the local TUIC proxy to start.'
            . ($error !== '' ? ' ' . $error : '')
        );
    }

    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        // 先尽量优雅关闭，超时后再强制结束。
        $status = proc_get_status($this->process);
        if (($status['running'] ?? false) === true) {
            $this->terminateProcess(force: false);

            $deadline = microtime(true) + 3.0;
            while (microtime(true) < $deadline) {
                $status = proc_get_status($this->process);
                if (($status['running'] ?? false) !== true) {
                    break;
                }
                usleep(100_000);
            }

            $status = proc_get_status($this->process);
            if (($status['running'] ?? false) === true) {
                $this->terminateProcess(force: true);
            }
        }

        $this->cleanupProcess();
    }

    public function getHttpProxyAddress(): string
    {
        return '127.0.0.1:' . $this->httpPort;
    }

    public function getHttpProxyUrl(): string
    {
        return 'http://' . $this->getHttpProxyAddress();
    }

    public function getSocksProxyAddress(): string
    {
        return '127.0.0.1:' . $this->socksPort;
    }

    public function getSocksProxyUrl(): string
    {
        return 'socks5h://' . $this->getSocksProxyAddress();
    }

    public function isRunning(): bool
    {
        if (!\is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);

        return (bool) ($status['running'] ?? false);
    }

    public function getStartupLog(): string
    {
        $this->drainStderr();

        return $this->stderrBuffer;
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function cleanupProcess(): void
    {
        $this->drainStderr();

        foreach ($this->pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->pipes = [];

        if (\is_resource($this->process)) {
            proc_close($this->process);
        }

        $this->process = null;
    }

    private function drainStderr(): void
    {
        if (!isset($this->pipes[2]) || !\is_resource($this->pipes[2])) {
            return;
        }

        $chunk = stream_get_contents($this->pipes[2]);
        if (\is_string($chunk) && $chunk !== '') {
            $this->stderrBuffer .= $chunk;
        }
    }

    private function reservePort(): int
    {
        // 先临时占一个本地随机端口，避免多个代理实例撞端口。
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($server === false) {
            throw new \RuntimeException("Failed to reserve a local port: {$error} ({$errno})");
        }

        $address = stream_socket_get_name($server, false);
        fclose($server);

        if ($address === false || !preg_match('/:(\d+)$/', $address, $matches)) {
            throw new \RuntimeException('Failed to detect the reserved proxy port.');
        }

        return (int) $matches[1];
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function terminateProcess(bool $force): void
    {
        if (!\is_resource($this->process)) {
            return;
        }

        // Windows 没有完全等价的 Unix signal，这里做了一层平台兼容。
        $signal = $force ? Platform::forceTerminateSignal() : Platform::gracefulTerminateSignal();
        if ($signal !== null) {
            @proc_terminate($this->process, $signal);

            return;
        }

        @proc_terminate($this->process);
    }
}
