<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

final readonly class RunOptions
{
    /**
     * @param list<string> $allowIps
     */
    public function __construct(
        public string $listenAddress,
        public int $maxConnections,
        public array $allowIps,
        public float $connectTimeout,
        public int $idleTimeoutSeconds,
        public float $handshakeTimeout,
        public ?string $statusFile,
        public int $statusInterval,
        public ?string $logFile,
        public ?string $pidFile,
        public bool $verbose,
    ) {
    }

    public static function fromInput(InputInterface $input, bool $verbose = false): self
    {
        $listenAddress = self::stringOption($input->getOption('listen'), '127.0.0.1:1080');
        self::assertListenAddress($listenAddress);

        return new self(
            listenAddress: $listenAddress,
            maxConnections: max(1, self::intOption($input->getOption('max-connections'), 1024)),
            allowIps: self::listOption($input->getOption('allow-ip')),
            connectTimeout: max(1.0, self::floatOption($input->getOption('connect-timeout'), 10.0)),
            idleTimeoutSeconds: max(30, self::intOption($input->getOption('idle-timeout'), 300)),
            handshakeTimeout: max(3.0, self::floatOption($input->getOption('handshake-timeout'), 15.0)),
            statusFile: self::nullableStringOption($input->getOption('status-file')),
            statusInterval: max(5, self::intOption($input->getOption('status-interval'), 10)),
            logFile: self::nullableStringOption($input->getOption('log-file')),
            pidFile: self::nullableStringOption($input->getOption('pid-file')),
            verbose: $verbose,
        );
    }

    private static function stringOption(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private static function nullableStringOption(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function intOption(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '' && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private static function floatOption(mixed $value, float $default): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && trim($value) !== '' && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    private static function listOption(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            foreach (preg_split('/[\s,;]+/', trim($entry)) ?: [] as $part) {
                if ($part !== '') {
                    $items[] = $part;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private static function assertListenAddress(string $listenAddress): void
    {
        $position = strrpos($listenAddress, ':');
        if ($position === false) {
            throw new InvalidArgumentException(sprintf('Invalid listen address "%s". Expected host:port.', $listenAddress));
        }

        $host = substr($listenAddress, 0, $position);
        $port = (int) substr($listenAddress, $position + 1);
        if ($host === '' || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('Invalid listen address "%s".', $listenAddress));
        }
    }
}
