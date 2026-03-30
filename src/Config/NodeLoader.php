<?php declare(strict_types=1);

namespace PhpTuic\Config;

use Symfony\Component\Yaml\Yaml;

final class NodeLoader
{
    public static function fromFile(string $path, ?string $nodeName = null): NodeConfig
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("Unable to read config file: {$path}");
        }

        $data = self::decodeByExtension($path, $raw);

        if (!is_array($data)) {
            throw new \RuntimeException("Unsupported config format in {$path}");
        }

        return self::fromArray($data, $nodeName);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data, ?string $nodeName = null): NodeConfig
    {
        $node = self::extractNode($data, $nodeName);

        return new NodeConfig(
            name: (string) self::value($node, ['name'], $nodeName ?? 'tuic-node'),
            server: self::requireString($node, ['server']),
            port: self::requireInt($node, ['port', 'server_port']),
            uuid: self::requireString($node, ['uuid']),
            password: self::requireString($node, ['password']),
            alpn: self::stringList(self::value($node, ['alpn'], ['h3'])),
            sni: (string) self::value($node, ['sni', 'server'], self::requireString($node, ['server'])),
            skipCertVerify: self::toBool(self::value($node, ['skip-cert-verify', 'skip_cert_verify'], false)),
            disableSni: self::toBool(self::value($node, ['disable-sni', 'disable_sni'], false)),
            reduceRtt: self::toBool(self::value($node, ['reduce-rtt', 'reduce_rtt'], false)),
            udpRelayMode: (string) self::value($node, ['udp-relay-mode', 'udp_relay_mode'], 'native'),
            congestionControl: (string) self::value(
                $node,
                ['congestion-controller', 'congestion_control'],
                'bbr',
            ),
        );
    }

    public static function fromString(string $raw, ?string $nodeName = null): NodeConfig
    {
        $decoded = self::decodeInline($raw);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unsupported inline node format. Expected YAML or JSON.');
        }

        return self::fromArray($decoded, $nodeName);
    }

    private static function decodeByExtension(string $path, string $raw): mixed
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        }

        return Yaml::parse($raw);
    }

    private static function decodeInline(string $raw): mixed
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new \RuntimeException('Inline node config must not be empty.');
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            try {
                return json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                // Fall through to YAML parsing so flow-style YAML still works.
            }
        }

        return Yaml::parse($trimmed);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function extractNode(array $data, ?string $nodeName): array
    {
        if (($data['type'] ?? null) === 'tuic') {
            return $data;
        }

        $proxies = $data['proxies'] ?? null;
        if (!is_array($proxies)) {
            throw new \RuntimeException('Expected a TUIC node object or a Clash config containing proxies.');
        }

        $candidates = array_values(array_filter(
            $proxies,
            static fn (mixed $proxy): bool => is_array($proxy) && (($proxy['type'] ?? null) === 'tuic'),
        ));

        if ($nodeName !== null) {
            foreach ($candidates as $candidate) {
                if (($candidate['name'] ?? null) === $nodeName) {
                    return $candidate;
                }
            }

            throw new \RuntimeException("TUIC node not found: {$nodeName}");
        }

        if ($candidates === []) {
            throw new \RuntimeException('No TUIC node found in config.');
        }

        /** @var array<mixed> $first */
        $first = $candidates[0];

        return $first;
    }

    /**
     * @param array<mixed> $data
     */
    private static function value(array $data, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return $default;
    }

    /**
     * @param array<mixed> $data
     */
    private static function requireString(array $data, array $keys): string
    {
        $value = self::value($data, $keys);
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException('Missing required string field: ' . implode('|', $keys));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private static function requireInt(array $data, array $keys): int
    {
        $value = self::value($data, $keys);
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('Missing required integer field: ' . implode('|', $keys));
        }

        if (!is_numeric((string) $value)) {
            throw new \RuntimeException('Invalid integer field: ' . implode('|', $keys));
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            throw new \RuntimeException('Expected a string list for ALPN.');
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \RuntimeException('ALPN values must be non-empty strings.');
            }

            $items[] = $item;
        }

        return $items;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return false;
    }
}
