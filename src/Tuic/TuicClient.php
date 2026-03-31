<?php declare(strict_types=1);

namespace PhpTuic\Tuic;

use PhpTuic\Config\NodeConfig;
use PhpTuic\Crypto\TlsExporter;
use PhpTuic\Crypto\TlsKeylogReader;
use PhpTuic\Native\Quiche\QuicheBindings;
use PhpTuic\Native\Quiche\SocketAddress;
use PhpTuic\Protocol\CommandEncoder;
use PhpTuic\Runtime\RunOptions;
use PhpTuic\Runtime\RuntimeLogger;
use PhpTuic\Runtime\ServerStats;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class TuicClient
{
    private readonly LoopInterface $loop;
    private ?QuicheBindings $bindings = null;
    /** @var \FFI|null */
    private ?\FFI $ffi = null;
    /** @var \FFI\CData|null */
    private ?\FFI\CData $config = null;
    /** @var \FFI\CData|null */
    private ?\FFI\CData $connection = null;
    /** @var resource|null */
    private $udpSocket = null;
    private ?SocketAddress $localAddress = null;
    private ?SocketAddress $peerAddress = null;
    private ?TimerInterface $timeoutTimer = null;
    private ?TimerInterface $authTimer = null;
    private ?TimerInterface $handshakeTimer = null;
    private ?string $keylogPath = null;
    private bool $authenticated = false;
    private bool $authCommandSent = false;
    private bool $authResultRecorded = false;
    private int $nextBidiStreamId = 0;
    private int $nextUniStreamId = 2;
    private string $resolvedPeerIp = '';

    /**
     * @var array<int, array{
     *     local: resource,
     *     host: string,
     *     port: int,
     *     onConnected: callable,
     *     onError: callable,
     *     upstream: string,
     *     downstream: string,
     *     remote_fin: bool,
     *     local_fin_pending: bool,
     *     local_fin_sent: bool
     * }>
     */
    private array $streams = [];

    /**
     * @var list<array{
     *     local: resource,
     *     host: string,
     *     port: int,
     *     initialData: string,
     *     onConnected: callable,
     *     onError: callable
     * }>
     */
    private array $pendingRelays = [];

    public function __construct(
        private readonly NodeConfig $node,
        ?LoopInterface $loop = null,
        private readonly RunOptions $options = new RunOptions(
            listenAddress: '127.0.0.1:1080',
            maxConnections: 1024,
            allowIps: [],
            connectTimeout: 10.0,
            idleTimeoutSeconds: 300,
            handshakeTimeout: 15.0,
            statusFile: null,
            statusInterval: 10,
            logFile: null,
            pidFile: null,
            verbose: false,
        ),
        private readonly ?ServerStats $stats = null,
        private readonly ?RuntimeLogger $logger = null,
        private readonly ?string $quicheLibrary = null,
    ) {
        $this->loop = $loop ?? Loop::get();
    }

    public function connect(): void
    {
        $this->start();
    }

    public function start(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $this->bindings = new QuicheBindings($this->quicheLibrary);
        $this->ffi = $this->bindings->ffi();
        $this->resolvedPeerIp = $this->resolvePeerIp($this->node->server);
        $this->udpSocket = $this->openUdpSocket($this->resolvedPeerIp, $this->node->port);
        $this->peerAddress = SocketAddress::fromEndpoint($this->bindings, $this->resolvedPeerIp, $this->node->port);
        $this->localAddress = SocketAddress::fromStreamName(
            $this->bindings,
            (string) stream_socket_get_name($this->udpSocket, false),
        );

        $this->config = $this->createConfig();
        $this->connection = $this->createConnection();
        $this->keylogPath = $this->createKeylogPath();
        $this->ffi->quiche_conn_set_keylog_path($this->connection, $this->keylogPath);

        stream_set_blocking($this->udpSocket, false);
        $this->loop->addReadStream($this->udpSocket, function (): void {
            $this->onUdpReadable();
        });
        $this->flushEgress();
        $this->armTimeout();
        $this->armHandshakeTimeout();
        $this->startAuthenticationPolling();
    }

    /**
     * @param resource $local
     */
    public function queueTcpRelay(
        $local,
        string $host,
        int $port,
        callable $onConnected,
        callable $onError,
        string $initialData = '',
    ): void {
        $this->start();

        $relay = [
            'local' => $local,
            'host' => $host,
            'port' => $port,
            'initialData' => $initialData,
            'onConnected' => $onConnected,
            'onError' => $onError,
        ];

        if ($this->authenticated) {
            $this->openRelay($relay);

            return;
        }

        $this->pendingRelays[] = $relay;
    }

    public function close(): void
    {
        foreach (array_keys($this->streams) as $streamId) {
            $this->closeRelay($streamId, 'TUIC client stopping.');
        }

        foreach ($this->pendingRelays as $relay) {
            if (is_resource($relay['local'])) {
                fclose($relay['local']);
            }
            $this->stats?->recordConnectionClosed();
        }

        $this->pendingRelays = [];
        $this->authenticated = false;
        $this->authCommandSent = false;
        $this->authResultRecorded = false;

        if ($this->timeoutTimer !== null) {
            $this->loop->cancelTimer($this->timeoutTimer);
            $this->timeoutTimer = null;
        }

        if ($this->authTimer !== null) {
            $this->loop->cancelTimer($this->authTimer);
            $this->authTimer = null;
        }

        if ($this->handshakeTimer !== null) {
            $this->loop->cancelTimer($this->handshakeTimer);
            $this->handshakeTimer = null;
        }

        if (is_resource($this->udpSocket)) {
            $this->loop->removeReadStream($this->udpSocket);
            fclose($this->udpSocket);
            $this->udpSocket = null;
        }

        if ($this->connection !== null && $this->ffi !== null) {
            $this->ffi->quiche_conn_free($this->connection);
            $this->connection = null;
        }

        if ($this->config !== null && $this->ffi !== null) {
            $this->ffi->quiche_config_free($this->config);
            $this->config = null;
        }

        if ($this->keylogPath !== null && is_file($this->keylogPath)) {
            @unlink($this->keylogPath);
            $this->keylogPath = null;
        }

        $this->localAddress = null;
        $this->peerAddress = null;
        $this->bindings = null;
        $this->ffi = null;
        $this->resolvedPeerIp = '';
    }

    public function __destruct()
    {
        $this->close();
    }

    public function heartbeat(): void
    {
        // Heartbeat over QUIC DATAGRAM is not wired yet in this FFI runtime.
    }

    private function createConfig(): \FFI\CData
    {
        $config = $this->ffi?->quiche_config_new(QuicheBindings::PROTOCOL_VERSION);
        if ($config === null) {
            throw new \RuntimeException('Failed to create a quiche config object.');
        }

        $this->ffi->quiche_config_verify_peer($config, !$this->node->skipCertVerify);
        $this->ffi->quiche_config_log_keys($config);
        $this->ffi->quiche_config_set_max_idle_timeout($config, $this->options->idleTimeoutSeconds * 1000);
        $this->ffi->quiche_config_set_max_recv_udp_payload_size($config, 1_350);
        $this->ffi->quiche_config_set_max_send_udp_payload_size($config, 1_350);
        $this->ffi->quiche_config_set_initial_max_data($config, 10_000_000);
        $this->ffi->quiche_config_set_initial_max_stream_data_bidi_local($config, 1_000_000);
        $this->ffi->quiche_config_set_initial_max_stream_data_bidi_remote($config, 1_000_000);
        $this->ffi->quiche_config_set_initial_max_stream_data_uni($config, 1_000_000);
        $this->ffi->quiche_config_set_initial_max_streams_bidi($config, 128);
        $this->ffi->quiche_config_set_initial_max_streams_uni($config, 32);
        $this->ffi->quiche_config_enable_dgram($config, true, 128, 128);

        $alpnWire = '';
        foreach ($this->node->alpn as $protocol) {
            $alpnWire .= chr(strlen($protocol)) . $protocol;
        }

        $protoBuffer = $this->copyToCBuffer($alpnWire);
        $result = $this->ffi->quiche_config_set_application_protos($config, $protoBuffer, strlen($alpnWire));
        if ($result < 0) {
            throw new \RuntimeException('Failed to configure ALPN on the quiche config.');
        }

        $cc = strtolower($this->node->congestionControl);
        $ccResult = $this->ffi->quiche_config_set_cc_algorithm_name($config, $cc);
        if ($ccResult < 0) {
            $this->log("Unsupported congestion control '{$cc}', falling back to quiche default.");
        }

        return $config;
    }

    private function createConnection(): \FFI\CData
    {
        $serverName = $this->node->disableSni ? null : ($this->node->sni !== '' ? $this->node->sni : $this->node->server);
        $scid = random_bytes(16);
        $scidBuffer = $this->copyToCBuffer($scid);
        $connection = $this->ffi?->quiche_connect(
            $serverName,
            $scidBuffer,
            strlen($scid),
            $this->localAddress?->asConstSockaddrPointer(),
            $this->localAddress?->length ?? 0,
            $this->peerAddress?->asConstSockaddrPointer(),
            $this->peerAddress?->length ?? 0,
            $this->config,
        );

        if ($connection === null) {
            throw new \RuntimeException('Failed to create the quiche client connection.');
        }

        return $connection;
    }

    private function onUdpReadable(): void
    {
        if (!is_resource($this->udpSocket) || $this->connection === null) {
            return;
        }

        while (true) {
            $packet = @fread($this->udpSocket, 65535);
            if ($packet === false || $packet === '') {
                break;
            }

            $recvInfo = $this->ffi->new('quiche_recv_info');
            $recvInfo->from = $this->peerAddress?->asMutableSockaddrPointer();
            $recvInfo->from_len = $this->peerAddress?->length ?? 0;
            $recvInfo->to = $this->localAddress?->asMutableSockaddrPointer();
            $recvInfo->to_len = $this->localAddress?->length ?? 0;

            $buffer = $this->copyToCBuffer($packet);
            $result = $this->ffi->quiche_conn_recv($this->connection, $buffer, strlen($packet), \FFI::addr($recvInfo));
            if ($result < 0 && $result !== QuicheBindings::ERR_DONE) {
                $this->shutdownAllStreams("quiche recv failed: {$result}");

                return;
            }

            $this->drainReadableStreams();
        }

        $this->flushPendingStreamWrites();
        $this->flushEgress();
        $this->armTimeout();
        $this->checkConnectionClosed();
    }

    private function drainReadableStreams(): void
    {
        if ($this->connection === null) {
            return;
        }

        $iterator = $this->ffi->quiche_conn_readable($this->connection);
        if ($iterator === null) {
            return;
        }

        $streamId = $this->ffi->new('uint64_t[1]');
        try {
            while ($this->ffi->quiche_stream_iter_next($iterator, $streamId)) {
                $id = (int) $streamId[0];
                if (!isset($this->streams[$id])) {
                    $this->drainUnknownStream($id);
                    continue;
                }

                $this->drainStream($id);
            }
        } finally {
            $this->ffi->quiche_stream_iter_free($iterator);
        }
    }

    private function drainUnknownStream(int $streamId): void
    {
        while (true) {
            $buffer = $this->ffi->new('uint8_t[65535]');
            $fin = $this->ffi->new('bool[1]');
            $error = $this->ffi->new('uint64_t[1]');
            $read = $this->ffi->quiche_conn_stream_recv($this->connection, $streamId, $buffer, 65535, $fin, $error);

            if ($read <= 0) {
                break;
            }
        }
    }

    private function drainStream(int $streamId): void
    {
        while (true) {
            $buffer = $this->ffi->new('uint8_t[65535]');
            $fin = $this->ffi->new('bool[1]');
            $error = $this->ffi->new('uint64_t[1]');
            $read = $this->ffi->quiche_conn_stream_recv($this->connection, $streamId, $buffer, 65535, $fin, $error);

            if ($read === QuicheBindings::ERR_DONE) {
                break;
            }

            if ($read < 0) {
                $this->closeRelay($streamId, "quiche stream recv failed: {$read}");

                return;
            }

            $chunk = \FFI::string($buffer, $read);
            if ($chunk !== '') {
                $this->streams[$streamId]['downstream'] .= $chunk;
                $this->loop->addWriteStream(
                    $this->streams[$streamId]['local'],
                    fn () => $this->flushLocalDownstream($streamId),
                );
            }

            if ($fin[0]) {
                $this->streams[$streamId]['remote_fin'] = true;
                $this->loop->addWriteStream(
                    $this->streams[$streamId]['local'],
                    fn () => $this->flushLocalDownstream($streamId),
                );
                break;
            }
        }
    }

    private function flushPendingStreamWrites(): void
    {
        foreach (array_keys($this->streams) as $streamId) {
            $this->flushStreamWriteBuffer($streamId);
        }
    }

    private function flushStreamWriteBuffer(int $streamId): void
    {
        if ($this->connection === null || !isset($this->streams[$streamId])) {
            return;
        }

        while (true) {
            $buffer = $this->streams[$streamId]['upstream'];
            $fin = $buffer === '' && $this->streams[$streamId]['local_fin_pending'] && !$this->streams[$streamId]['local_fin_sent'];

            if ($buffer === '' && !$fin) {
                break;
            }

            $error = $this->ffi->new('uint64_t[1]');
            $payload = $buffer !== '' ? $this->copyToCBuffer($buffer) : $this->ffi->new('uint8_t[1]');
            $written = $this->ffi->quiche_conn_stream_send(
                $this->connection,
                $streamId,
                $payload,
                strlen($buffer),
                $fin,
                $error,
            );

            if ($written === QuicheBindings::ERR_DONE) {
                break;
            }

            if ($written < 0) {
                $this->closeRelay($streamId, "quiche stream send failed: {$written}");

                return;
            }

            if ($buffer !== '') {
                $this->streams[$streamId]['upstream'] = (string) substr($buffer, $written);
            }

            if ($fin && $this->streams[$streamId]['upstream'] === '') {
                $this->streams[$streamId]['local_fin_sent'] = true;
                $this->streams[$streamId]['local_fin_pending'] = false;
                break;
            }

            if ($written === 0) {
                break;
            }
        }
    }

    private function flushLocalDownstream(int $streamId): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $local = $this->streams[$streamId]['local'];
        $buffer = $this->streams[$streamId]['downstream'];

        if ($buffer !== '') {
            $written = @fwrite($local, $buffer);
            if ($written === false) {
                $this->closeRelay($streamId, 'Failed to write response data to the local socket.');

                return;
            }

            if ($written > 0) {
                $this->streams[$streamId]['downstream'] = (string) substr($buffer, $written);
            }
        }

        if ($this->streams[$streamId]['downstream'] !== '') {
            return;
        }

        $this->loop->removeWriteStream($local);

        if ($this->streams[$streamId]['remote_fin']) {
            @stream_socket_shutdown($local, STREAM_SHUT_WR);
            $this->closeRelay($streamId);
        }
    }

    /**
     * @param array{
     *     local: resource,
     *     host: string,
     *     port: int,
     *     initialData: string,
     *     onConnected: callable,
     *     onError: callable
     * } $relay
     */
    private function openRelay(array $relay): void
    {
        $streamId = $this->nextBidiStreamId;
        $this->nextBidiStreamId += 4;

        $this->streams[$streamId] = [
            'local' => $relay['local'],
            'host' => $relay['host'],
            'port' => $relay['port'],
            'onConnected' => $relay['onConnected'],
            'onError' => $relay['onError'],
            'upstream' => CommandEncoder::connect($relay['host'], $relay['port']) . $relay['initialData'],
            'downstream' => '',
            'remote_fin' => false,
            'local_fin_pending' => false,
            'local_fin_sent' => false,
        ];

        stream_set_blocking($relay['local'], false);
        $this->loop->addReadStream($relay['local'], fn () => $this->onLocalReadable($streamId));
        ($relay['onConnected'])();
        $this->flushStreamWriteBuffer($streamId);
        $this->flushEgress();
        $this->armTimeout();
    }

    private function onLocalReadable(int $streamId): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $local = $this->streams[$streamId]['local'];
        $chunk = @fread($local, 65535);

        if ($chunk === false) {
            $this->closeRelay($streamId, 'Failed to read from the local socket.');

            return;
        }

        if ($chunk === '') {
            if (feof($local)) {
                $this->streams[$streamId]['local_fin_pending'] = true;
                $this->loop->removeReadStream($local);
                $this->flushStreamWriteBuffer($streamId);
                $this->flushEgress();
            }

            return;
        }

        $this->streams[$streamId]['upstream'] .= $chunk;
        $this->flushStreamWriteBuffer($streamId);
        $this->flushEgress();
        $this->armTimeout();
    }

    private function flushEgress(): void
    {
        if ($this->connection === null || !is_resource($this->udpSocket)) {
            return;
        }

        $out = $this->ffi->new('uint8_t[65535]');
        while (true) {
            $sendInfo = $this->ffi->new('quiche_send_info');
            $written = $this->ffi->quiche_conn_send($this->connection, $out, 65535, \FFI::addr($sendInfo));
            if ($written === QuicheBindings::ERR_DONE) {
                break;
            }

            if ($written < 0) {
                $this->shutdownAllStreams("quiche send failed: {$written}");

                return;
            }

            $packet = \FFI::string($out, $written);
            $result = @fwrite($this->udpSocket, $packet);
            if ($result === false || $result === 0) {
                $this->shutdownAllStreams('Failed to write a QUIC packet to the UDP socket.');

                return;
            }
        }
    }

    private function armTimeout(): void
    {
        if ($this->connection === null) {
            return;
        }

        if ($this->timeoutTimer !== null) {
            $this->loop->cancelTimer($this->timeoutTimer);
            $this->timeoutTimer = null;
        }

        $timeout = (int) $this->ffi->quiche_conn_timeout_as_millis($this->connection);
        if ($timeout <= 0 || $timeout > 86_400_000) {
            return;
        }

        $this->timeoutTimer = $this->loop->addTimer($timeout / 1000, function (): void {
            if ($this->connection === null) {
                return;
            }

            $this->ffi?->quiche_conn_on_timeout($this->connection);
            $this->flushEgress();
            $this->armTimeout();
            $this->checkConnectionClosed();
        });
    }

    private function startAuthenticationPolling(): void
    {
        if ($this->authTimer !== null) {
            return;
        }

        $reader = new TlsKeylogReader();

        $this->authTimer = $this->loop->addPeriodicTimer(0.1, function () use ($reader): void {
            if ($this->connection === null || $this->authenticated === true) {
                if ($this->authTimer !== null) {
                    $this->loop->cancelTimer($this->authTimer);
                    $this->authTimer = null;
                }

                return;
            }

            if (!$this->ffi->quiche_conn_is_established($this->connection)) {
                return;
            }

            try {
                $secret = $reader->readLatestExporterSecret((string) $this->keylogPath);
            } catch (\Throwable) {
                return;
            }

            $this->logEstablishedProtocol();
            $streamId = $this->nextUniStreamId;
            $this->nextUniStreamId += 4;
            $token = TlsExporter::deriveTuicToken($secret, $this->node->uuid, $this->node->password);
            $payload = CommandEncoder::authenticate($this->node->uuid, $token);
            $this->log('Sending TUIC authenticate command.');
            $buffer = $this->copyToCBuffer($payload);
            $error = $this->ffi->new('uint64_t[1]');
            $written = $this->ffi->quiche_conn_stream_send(
                $this->connection,
                $streamId,
                $buffer,
                strlen($payload),
                true,
                $error,
            );

            if ($written < 0) {
                $this->recordAuthFailure();
                $this->shutdownAllStreams("Failed to send the TUIC authenticate command: {$written}");

                return;
            }

            $this->authCommandSent = true;
            $this->authenticated = true;
            $this->recordAuthSuccess();
            if ($this->handshakeTimer !== null) {
                $this->loop->cancelTimer($this->handshakeTimer);
                $this->handshakeTimer = null;
            }
            $this->flushEgress();
            $pending = $this->pendingRelays;
            $this->pendingRelays = [];
            foreach ($pending as $relay) {
                $this->openRelay($relay);
            }

            if ($this->authTimer !== null) {
                $this->loop->cancelTimer($this->authTimer);
                $this->authTimer = null;
            }
        });
    }

    private function checkConnectionClosed(): void
    {
        if ($this->connection === null || !$this->ffi->quiche_conn_is_closed($this->connection)) {
            return;
        }

        $this->shutdownAllStreams($this->describeConnectionClose());
    }

    private function shutdownAllStreams(string $reason): void
    {
        $this->log($reason);
        if (!$this->authenticated) {
            $this->recordAuthFailure();
        }
        foreach (array_keys($this->streams) as $streamId) {
            $this->closeRelay($streamId, $reason);
        }

        foreach ($this->pendingRelays as $relay) {
            ($relay['onError'])(new \RuntimeException($reason));
            if (is_resource($relay['local'])) {
                fclose($relay['local']);
            }
            $this->stats?->recordConnectionClosed();
        }

        $this->pendingRelays = [];
        $this->close();
    }

    private function closeRelay(int $streamId, ?string $reason = null, bool $closeLocal = true): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $state = $this->streams[$streamId];
        $local = $state['local'];
        $this->loop->removeReadStream($local);
        $this->loop->removeWriteStream($local);

        if ($closeLocal && is_resource($local)) {
            fclose($local);
        }

        unset($this->streams[$streamId]);
        $this->stats?->recordConnectionClosed();

        if ($reason !== null) {
            ($state['onError'])(new \RuntimeException($reason));
        }
    }

    /**
     * @return resource
     */
    private function openUdpSocket(string $ip, int $port)
    {
        $endpoint = str_contains($ip, ':') ? "udp://[{$ip}]:{$port}" : "udp://{$ip}:{$port}";
        $socket = @stream_socket_client(
            $endpoint,
            $errno,
            $error,
            $this->options->connectTimeout,
            STREAM_CLIENT_CONNECT,
        );
        if (!is_resource($socket)) {
            throw new \RuntimeException("Failed to open UDP socket to {$endpoint}: {$error} ({$errno})");
        }

        return $socket;
    }

    private function resolvePeerIp(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    return $record['ip'];
                }

                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    return $record['ipv6'];
                }
            }
        }

        $ipv4 = gethostbyname($host);
        if ($ipv4 !== $host) {
            return $ipv4;
        }

        throw new \RuntimeException("Unable to resolve TUIC server host: {$host}");
    }

    /**
     * @return \FFI\CData
     */
    private function copyToCBuffer(string $bytes): \FFI\CData
    {
        $length = max(1, strlen($bytes));
        $buffer = $this->ffi->new("uint8_t[{$length}]");

        if ($bytes !== '') {
            \FFI::memcpy($buffer, $bytes, strlen($bytes));
        }

        return $buffer;
    }

    private function createKeylogPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'php-tuic-keylog-');
        if ($path === false) {
            throw new \RuntimeException('Failed to allocate a keylog file path.');
        }

        @unlink($path);

        return $path;
    }

    private function armHandshakeTimeout(): void
    {
        if ($this->handshakeTimer !== null) {
            $this->loop->cancelTimer($this->handshakeTimer);
        }

        $this->handshakeTimer = $this->loop->addTimer($this->options->handshakeTimeout, function (): void {
            if ($this->connection === null || $this->authenticated) {
                return;
            }

            $this->stats?->recordHandshakeTimeout();
            $this->recordAuthFailure();
            $this->shutdownAllStreams(sprintf(
                'Timed out after %.1f seconds waiting for QUIC/TUIC authentication.',
                $this->options->handshakeTimeout,
            ));
        });
    }

    private function logEstablishedProtocol(): void
    {
        if ($this->connection === null || $this->ffi === null) {
            return;
        }

        $proto = $this->ffi->new('const uint8_t *[1]');
        $length = $this->ffi->new('size_t[1]');
        $this->ffi->quiche_conn_application_proto($this->connection, $proto, $length);

        if ($length[0] <= 0 || $proto[0] === null) {
            return;
        }

        $name = \FFI::string($proto[0], (int) $length[0]);
        if ($name !== '') {
            $this->log("Negotiated ALPN: {$name}");
        }
    }

    private function describeConnectionClose(): string
    {
        if ($this->connection === null || $this->ffi === null) {
            return 'The QUIC connection was closed.';
        }

        $peerReason = $this->describePeerOrLocalClose(local: false);
        if ($peerReason !== null) {
            return $peerReason;
        }

        $localReason = $this->describePeerOrLocalClose(local: true);
        if ($localReason !== null) {
            return $localReason;
        }

        return $this->authCommandSent
            ? 'The QUIC connection was closed after the TUIC authenticate command was sent.'
            : 'The QUIC connection was closed before TUIC authentication completed.';
    }

    private function describePeerOrLocalClose(bool $local): ?string
    {
        if ($this->connection === null || $this->ffi === null) {
            return null;
        }

        $isApp = $this->ffi->new('bool[1]');
        $errorCode = $this->ffi->new('uint64_t[1]');
        $reason = $this->ffi->new('const uint8_t *[1]');
        $reasonLength = $this->ffi->new('size_t[1]');

        $hasError = $local
            ? $this->ffi->quiche_conn_local_error($this->connection, $isApp, $errorCode, $reason, $reasonLength)
            : $this->ffi->quiche_conn_peer_error($this->connection, $isApp, $errorCode, $reason, $reasonLength);

        if (!$hasError) {
            return null;
        }

        $scope = $local ? 'locally' : 'by the remote peer';
        $type = $isApp[0] ? 'application' : 'transport';
        $message = "The QUIC connection was closed {$scope} ({$type} error {$errorCode[0]}";

        if ($reasonLength[0] > 0 && $reason[0] !== null) {
            $detail = trim(\FFI::string($reason[0], (int) $reasonLength[0]));
            if ($detail !== '') {
                $message .= ", reason: {$detail}";
            }
        }

        return $message . ').';
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, ['component' => 'tuic']);

            return;
        }

        fwrite(STDERR, "[tuic] {$message}\n");
    }

    private function recordAuthSuccess(): void
    {
        if ($this->authResultRecorded) {
            return;
        }

        $this->authResultRecorded = true;
        $this->stats?->recordTuicAuthSuccess();
    }

    private function recordAuthFailure(): void
    {
        if ($this->authResultRecorded) {
            return;
        }

        $this->authResultRecorded = true;
        $this->stats?->recordTuicAuthFailure();
    }
}
