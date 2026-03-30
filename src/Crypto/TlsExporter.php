<?php declare(strict_types=1);

namespace PhpTuic\Crypto;

final class TlsExporter
{
    public static function export(
        string $exporterSecretHex,
        string $label,
        string $context,
        int $length,
    ): string {
        $exporterSecret = hex2bin(trim($exporterSecretHex));
        if ($exporterSecret === false) {
            throw new \RuntimeException('EXPORTER_SECRET is not valid hexadecimal.');
        }

        [$algorithm, $hashLength] = self::hashAlgorithmForSecret($exporterSecret);
        $derivedSecret = self::hkdfExpandLabel(
            algorithm: $algorithm,
            secret: $exporterSecret,
            label: $label,
            context: hash($algorithm, '', true),
            length: $hashLength,
        );

        return self::hkdfExpandLabel(
            algorithm: $algorithm,
            secret: $derivedSecret,
            label: 'exporter',
            context: hash($algorithm, $context, true),
            length: $length,
        );
    }

    public static function deriveTuicToken(string $exporterSecretHex, string $uuid, string $password): string
    {
        return self::export($exporterSecretHex, self::uuidBytes($uuid), $password, 32);
    }

    private static function uuidBytes(string $uuid): string
    {
        $uuid = strtolower(trim($uuid));
        if (!preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        )) {
            throw new \RuntimeException("Invalid UUID format: {$uuid}");
        }

        $bytes = hex2bin(str_replace('-', '', $uuid));
        if ($bytes === false) {
            throw new \RuntimeException("Failed to convert UUID to raw bytes: {$uuid}");
        }

        return $bytes;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function hashAlgorithmForSecret(string $secret): array
    {
        return match (strlen($secret)) {
            32 => ['sha256', 32],
            48 => ['sha384', 48],
            default => throw new \RuntimeException(
                'Unsupported EXPORTER_SECRET size; expected 32 or 48 bytes.',
            ),
        };
    }

    private static function hkdfExpandLabel(
        string $algorithm,
        string $secret,
        string $label,
        string $context,
        int $length,
    ): string {
        $fullLabel = 'tls13 ' . $label;
        if (strlen($fullLabel) > 255 || strlen($context) > 255) {
            throw new \RuntimeException('TLS label or context is too long.');
        }

        $info = pack('nC', $length, strlen($fullLabel))
            . $fullLabel
            . pack('C', strlen($context))
            . $context;

        return self::hkdfExpand($algorithm, $secret, $info, $length);
    }

    private static function hkdfExpand(string $algorithm, string $prk, string $info, int $length): string
    {
        $hashLength = strlen(hash($algorithm, '', true));
        $iterations = (int) ceil($length / $hashLength);

        if ($iterations > 255) {
            throw new \RuntimeException('Requested HKDF output is too long.');
        }

        $output = '';
        $previous = '';

        for ($counter = 1; $counter <= $iterations; $counter++) {
            $previous = hash_hmac($algorithm, $previous . $info . chr($counter), $prk, true);
            $output .= $previous;
        }

        return substr($output, 0, $length);
    }
}
