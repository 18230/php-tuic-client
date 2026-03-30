<?php declare(strict_types=1);

namespace PhpTuic\Native\Quiche;

final class PlatformTriplet
{
    public function __construct(
        public readonly string $platform,
        public readonly string $architecture,
    ) {
    }

    public static function detect(): self
    {
        $platform = match (PHP_OS_FAMILY) {
            'Windows' => 'windows',
            'Darwin' => 'macos',
            default => 'linux',
        };

        $machine = strtolower((string) php_uname('m'));
        $architecture = match ($machine) {
            'x86_64', 'amd64' => 'x64',
            'aarch64', 'arm64' => 'arm64',
            default => preg_replace('/[^a-z0-9]+/', '-', $machine) ?: 'unknown',
        };

        return new self($platform, $architecture);
    }

    public function asString(): string
    {
        return "{$this->platform}-{$this->architecture}";
    }
}
