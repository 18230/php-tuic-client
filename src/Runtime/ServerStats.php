<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

final class ServerStats
{
    private readonly int $startedAt;
    private int $activeConnections = 0;
    private int $acceptedConnections = 0;
    private int $rejectedConnections = 0;
    private int $accessDeniedConnections = 0;
    private int $connectionLimitRejections = 0;
    private int $closedConnections = 0;
    private int $handshakeTimeouts = 0;
    private int $tuicAuthSuccesses = 0;
    private int $tuicAuthFailures = 0;

    public function __construct(?int $startedAt = null)
    {
        $this->startedAt = $startedAt ?? time();
    }

    public function activeConnections(): int
    {
        return $this->activeConnections;
    }

    public function recordAcceptedConnection(): void
    {
        $this->acceptedConnections++;
        $this->activeConnections++;
    }

    public function recordAccessDenied(): void
    {
        $this->rejectedConnections++;
        $this->accessDeniedConnections++;
    }

    public function recordConnectionLimitRejected(): void
    {
        $this->rejectedConnections++;
        $this->connectionLimitRejections++;
    }

    public function recordConnectionClosed(): void
    {
        $this->closedConnections++;
        if ($this->activeConnections > 0) {
            $this->activeConnections--;
        }
    }

    public function recordHandshakeTimeout(): void
    {
        $this->handshakeTimeouts++;
    }

    public function recordTuicAuthSuccess(): void
    {
        $this->tuicAuthSuccesses++;
    }

    public function recordTuicAuthFailure(): void
    {
        $this->tuicAuthFailures++;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'started_at' => $this->startedAt,
            'uptime_seconds' => max(0, time() - $this->startedAt),
            'active_connections' => $this->activeConnections,
            'accepted_connections_total' => $this->acceptedConnections,
            'rejected_connections_total' => $this->rejectedConnections,
            'access_denied_total' => $this->accessDeniedConnections,
            'connection_limit_rejections_total' => $this->connectionLimitRejections,
            'closed_connections_total' => $this->closedConnections,
            'handshake_timeouts_total' => $this->handshakeTimeouts,
            'tuic_auth_success_total' => $this->tuicAuthSuccesses,
            'tuic_auth_failure_total' => $this->tuicAuthFailures,
        ];
    }
}
