<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

final readonly class StatusFileWriter
{
    public function __construct(
        private string $path,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function write(array $payload): string
    {
        $directory = dirname($this->path);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create status directory "%s".', $directory));
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode status payload as JSON.');
        }

        if (file_put_contents($this->path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write status file "%s".', $this->path));
        }

        return $this->path;
    }
}
