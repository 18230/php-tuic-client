<?php declare(strict_types=1);

namespace PhpTuic\Tuic;

use Amp\DeferredFuture;
use Amp\Quic\Internal\Quiche\QuicheConnection as InternalQuicheConnection;
use Amp\Quic\QuicConnectionError;
use Amp\Quic\QuicClientConfig;
use Amp\Quic\QuicSocket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\TlsInfo;
use PhpTuic\Config\NodeConfig;
use PhpTuic\Crypto\TlsExporter;
use PhpTuic\Crypto\TlsKeylogReader;
use PhpTuic\Protocol\CommandEncoder;
use PhpTuic\Transport\UnidirectionalStreamFactory;
use function Amp\Quic\connect;

// TUIC 的底层连接封装。
// 负责建立 QUIC/TLS、做 TUIC 鉴权、再打开真正的转发流。
final class TuicClient
{
    private ?InternalQuicheConnection $connection = null;
    private ?DeferredFuture $connectFuture = null;
    private ?string $keylogFile = null;

    public function __construct(private readonly NodeConfig $node)
    {
    }

    public function connect(): void
    {
        if ($this->connection !== null && !$this->connection->isClosed()) {
            return;
        }

        // 如果已经有一个连接过程在跑，后来的调用直接等待，避免并发重复建连。
        if ($this->connectFuture !== null) {
            $this->connectFuture->getFuture()->await();
            return;
        }

        if ($this->connection !== null && $this->connection->isClosed()) {
            $this->close();
        }

        $future = $this->connectFuture = new DeferredFuture();

        try {
            // QUIC keylog 用来拿 TLS exporter secret，后面要据此推导 TUIC token。
            $this->keylogFile = $this->createKeylogPath();

            $peerName = $this->node->sni !== '' ? $this->node->sni : $this->node->server;
            $tls = (new ClientTlsContext($peerName))
                ->withPeerName($peerName)
                ->withApplicationLayerProtocols($this->node->alpn);

            if ($this->node->skipCertVerify) {
                $tls = $tls->withoutPeerVerification();
            }

            $config = (new QuicClientConfig($tls))
                ->withHostname($this->node->disableSni ? $this->node->server : $peerName)
                ->withKeylogFile($this->keylogFile)
                ->withPingPeriod(15.0);

            $connection = connect("{$this->node->server}:{$this->node->port}", $config);
            if (!$connection instanceof InternalQuicheConnection) {
                throw new \RuntimeException('Unexpected QUIC driver; this PoC expects the quiche-backed driver.');
            }

            // TUIC 鉴权不是简单明文密码，而是基于 TLS exporter 算出来的 token。
            $secret = (new TlsKeylogReader())->waitForExporterSecret($this->keylogFile);
            $token = TlsExporter::deriveTuicToken($secret, $this->node->uuid, $this->node->password);

            $authStream = UnidirectionalStreamFactory::create($connection);
            $authStream->write(CommandEncoder::authenticate($this->node->uuid, $token));
            $authStream->end();

            $this->connection = $connection;
            $future->complete();
        } catch (\Throwable $e) {
            $future->error($e);
            throw $e;
        } finally {
            $this->connectFuture = null;
        }
    }

    public function heartbeat(): void
    {
        $this->requireConnection()->send(CommandEncoder::heartbeat());
    }

    public function getTlsInfo(): ?TlsInfo
    {
        if ($this->connection === null) {
            return null;
        }

        return $this->connection->getTlsInfo();
    }

    public function isClosed(): bool
    {
        return $this->connection?->isClosed() ?? false;
    }

    public function getCloseReason(): ?QuicConnectionError
    {
        if ($this->connection === null || !$this->connection->isClosed()) {
            return null;
        }

        return $this->connection->getCloseReason();
    }

    public function tcpRequest(string $targetHost, int $targetPort, string $payload): string
    {
        // 一次性请求：打开流、发 payload、把返回完整读完。
        $stream = $this->openTcpStream($targetHost, $targetPort, $payload);

        $response = '';
        while (($chunk = $stream->read()) !== null) {
            $response .= $chunk;
        }

        return $response;
    }

    public function openTcpStream(string $targetHost, int $targetPort, string $initialData = ''): QuicSocket
    {
        $this->connect();

        // 先发 TUIC Connect 命令，再紧跟初始数据。
        $stream = $this->requireConnection()->openStream();
        $stream->write(CommandEncoder::connect($targetHost, $targetPort) . $initialData);

        return $stream;
    }

    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        if ($this->keylogFile !== null && is_file($this->keylogFile)) {
            @unlink($this->keylogFile);
            $this->keylogFile = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function requireConnection(): InternalQuicheConnection
    {
        if ($this->connection === null) {
            throw new \RuntimeException('TUIC connection has not been established yet.');
        }

        return $this->connection;
    }

    private function createKeylogPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'php-tuic-keylog-');
        if ($path === false) {
            throw new \RuntimeException('Failed to allocate a temporary key log file.');
        }

        // 这里删掉占位文件，只保留一个后续可写入的路径。
        @unlink($path);

        return $path;
    }
}
