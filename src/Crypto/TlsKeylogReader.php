<?php declare(strict_types=1);

namespace PhpTuic\Crypto;

final class TlsKeylogReader
{
    public function waitForExporterSecret(string $path, float $timeoutSeconds = 5.0): string
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $lastError = null;

        do {
            try {
                return $this->readLatestExporterSecret($path);
            } catch (\RuntimeException $e) {
                $lastError = $e;
                usleep(100_000);
            }
        } while (microtime(true) < $deadline);

        throw new \RuntimeException(
            'Timed out waiting for EXPORTER_SECRET in key log file.',
            previous: $lastError,
        );
    }

    public function readLatestExporterSecret(string $path): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Key log file not found: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read key log file: {$path}");
        }

        $secret = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (!is_array($parts) || count($parts) < 3) {
                continue;
            }

            if ($parts[0] === 'EXPORTER_SECRET') {
                $secret = $parts[2];
            }
        }

        if ($secret === null) {
            throw new \RuntimeException('EXPORTER_SECRET was not present in the key log yet.');
        }

        return $secret;
    }
}
