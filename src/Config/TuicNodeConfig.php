<?php

declare(strict_types=1);

namespace TuicClient\Config;

use InvalidArgumentException;

final readonly class TuicNodeConfig
{
    /**
     * @param list<string> $alpn
     */
    public function __construct(
        public string $server,
        public int $port,
        public string $uuid,
        public string $password,
        public ?string $sni,
        public array $alpn,
        public string $udpRelayMode,
        public string $congestionController,
        public bool $allowInsecure,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $server = self::requiredString($payload, 'server');
        $port = self::requiredPort($payload, 'port');
        $uuid = self::requiredString($payload, 'uuid');
        $password = self::requiredString($payload, 'password');

        return new self(
            server: $server,
            port: $port,
            uuid: $uuid,
            password: $password,
            sni: self::optionalString($payload['sni'] ?? null),
            alpn: self::stringList($payload['alpn'] ?? ['h3']),
            udpRelayMode: self::optionalString($payload['udp_relay_mode'] ?? null) ?? 'native',
            congestionController: self::optionalString($payload['congestion_controller'] ?? null) ?? 'bbr',
            allowInsecure: self::booleanValue($payload['allow_insecure'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function requiredString(array $payload, string $key): string
    {
        $value = self::optionalString($payload[$key] ?? null);
        if ($value === null) {
            throw new InvalidArgumentException(sprintf('The "%s" option is required.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function requiredPort(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        $port = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('The "%s" option must be a valid port.', $key));
        }

        return $port;
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        $items = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                $string = self::optionalString($item);
                if ($string !== null) {
                    $items[] = $string;
                }
            }
        } else {
            $string = self::optionalString($value);
            if ($string !== null) {
                foreach (explode(',', $string) as $item) {
                    $trimmed = trim($item);
                    if ($trimmed !== '') {
                        $items[] = $trimmed;
                    }
                }
            }
        }

        return $items === [] ? ['h3'] : array_values(array_unique($items));
    }

    private static function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
