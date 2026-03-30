<?php

declare(strict_types=1);

namespace TuicClient\Config;

use InvalidArgumentException;

final readonly class TuicRuntimeConfig
{
    public function __construct(
        public string $local,
        public string $logLevel,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $local = trim((string) ($payload['local'] ?? '127.0.0.1:1080'));
        if ($local === '') {
            throw new InvalidArgumentException('The "local" runtime option must not be empty.');
        }

        $logLevel = trim((string) ($payload['log_level'] ?? 'info'));
        if ($logLevel === '') {
            $logLevel = 'info';
        }

        return new self(
            local: $local,
            logLevel: $logLevel,
        );
    }
}
