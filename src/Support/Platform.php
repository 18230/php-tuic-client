<?php declare(strict_types=1);

namespace PhpTuic\Support;

final class Platform
{
    public static function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    public static function nullDevice(): string
    {
        return self::isWindows() ? 'NUL' : '/dev/null';
    }

    public static function gracefulTerminateSignal(): ?int
    {
        return \defined('SIGTERM') ? SIGTERM : null;
    }

    public static function forceTerminateSignal(): ?int
    {
        return \defined('SIGKILL') ? SIGKILL : null;
    }

    /**
     * @param list<int> $signals
     */
    public static function canTrapSignals(array $signals): bool
    {
        if (self::isWindows()) {
            return false;
        }

        if (!extension_loaded('pcntl')) {
            return false;
        }

        if ($signals === []) {
            return false;
        }

        foreach ($signals as $signal) {
            if (!\is_int($signal)) {
                return false;
            }
        }

        return true;
    }
}
