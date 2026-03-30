<?php

declare(strict_types=1);

namespace TuicClient\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use TuicClient\Config\TuicConfigLoader;

final class TuicConfigLoaderTest extends TestCase
{
    public function testItParsesTheExampleConfig(): void
    {
        $loader = new TuicConfigLoader();
        $config = $loader->fromFile(dirname(__DIR__, 3) . '/examples/node.example.yaml');

        self::assertSame('tuic.example.com', $config->node->server);
        self::assertSame(443, $config->node->port);
        self::assertSame('11111111-2222-3333-4444-555555555555', $config->node->uuid);
        self::assertSame(['h3'], $config->node->alpn);
        self::assertSame('127.0.0.1:1080', $config->runtime->local);
    }
}
