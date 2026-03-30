<?php

declare(strict_types=1);

namespace TuicClient\Config;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final class TuicConfigLoader
{
    public function fromFile(string $path): TuicClientConfig
    {
        return $this->fromArray($this->loadRawArray($path));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function fromArray(array $payload): TuicClientConfig
    {
        $nodePayload = isset($payload['node']) && is_array($payload['node']) ? $payload['node'] : $payload;
        $runtimePayload = isset($payload['runtime']) && is_array($payload['runtime']) ? $payload['runtime'] : [];

        return new TuicClientConfig(
            node: TuicNodeConfig::fromArray($nodePayload),
            runtime: TuicRuntimeConfig::fromArray($runtimePayload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function loadRawArray(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Config file not found: %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Unable to read config file: %s', $path));
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['yaml', 'yml'], true)) {
            $payload = Yaml::parse($contents);
        } else {
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException(sprintf('Config file did not decode to an array: %s', $path));
        }

        return $payload;
    }
}
