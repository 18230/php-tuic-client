<?php declare(strict_types=1);

namespace PhpTuic\Protocol;

final class Address
{
    public static function encode(string $host, int $port): string
    {
        if ($port < 1 || $port > 65535) {
            throw new \RuntimeException("Invalid port: {$port}");
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return chr(0x01) . inet_pton($host) . pack('n', $port);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return chr(0x02) . inet_pton($host) . pack('n', $port);
        }

        $length = strlen($host);
        if ($length === 0 || $length > 255) {
            throw new \RuntimeException('Invalid domain name length.');
        }

        return chr(0x00) . chr($length) . $host . pack('n', $port);
    }
}
