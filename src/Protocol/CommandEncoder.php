<?php declare(strict_types=1);

namespace PhpTuic\Protocol;

final class CommandEncoder
{
    public const VERSION = 0x05;

    public static function authenticate(string $uuid, string $token): string
    {
        $uuidBytes = self::uuidToBytes($uuid);
        if (strlen($token) !== 32) {
            throw new \RuntimeException('TUIC token must be exactly 32 bytes.');
        }

        return chr(self::VERSION) . chr(0x00) . $uuidBytes . $token;
    }

    public static function connect(string $host, int $port): string
    {
        return chr(self::VERSION) . chr(0x01) . Address::encode($host, $port);
    }

    public static function heartbeat(): string
    {
        return chr(self::VERSION) . chr(0x04);
    }

    private static function uuidToBytes(string $uuid): string
    {
        $uuid = strtolower(trim($uuid));
        $hex = str_replace('-', '', $uuid);

        if (!preg_match('/^[0-9a-f]{32}$/', $hex)) {
            throw new \RuntimeException("Invalid UUID: {$uuid}");
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            throw new \RuntimeException("Failed to convert UUID to bytes: {$uuid}");
        }

        return $bytes;
    }
}
