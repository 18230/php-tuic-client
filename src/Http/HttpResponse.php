<?php declare(strict_types=1);

namespace PhpTuic\Http;

final readonly class HttpResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public string $protocolVersion,
        public int $statusCode,
        public string $reasonPhrase,
        public array $headers,
        public string $body,
        public string $raw,
    ) {
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;

        return $values[0] ?? $default;
    }

    public function json(bool $associative = true): mixed
    {
        return json_decode($this->body, $associative, 512, JSON_THROW_ON_ERROR);
    }
}
