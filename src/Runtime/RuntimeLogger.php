<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

final class RuntimeLogger
{
    public function __construct(
        private readonly bool $verbose = false,
        private readonly ?string $logFile = null,
    ) {
        if ($this->logFile !== null && stream_is_local($this->logFile)) {
            $directory = dirname($this->logFile);
            if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->verbose) {
            $this->write('DEBUG', $message, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . (string) json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        if ($this->logFile !== null) {
            file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);

            return;
        }

        fwrite(STDERR, $line);
    }
}
