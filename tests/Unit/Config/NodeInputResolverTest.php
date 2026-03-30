<?php declare(strict_types=1);

namespace PhpTuic\Tests\Unit\Config;

use PhpTuic\Config\NodeInputResolver;
use PHPUnit\Framework\TestCase;

final class NodeInputResolverTest extends TestCase
{
    public function testItPrefersInlineNodeConfig(): void
    {
        $resolver = new NodeInputResolver();
        $node = $resolver->resolve(
            inlineNode: "{ name: demo, type: tuic, server: example.com, port: 443, uuid: 33333333-3333-3333-3333-333333333333, password: secret, alpn: [h3], disable-sni: false, reduce-rtt: false, udp-relay-mode: native, congestion-controller: bbr, skip-cert-verify: false, sni: example.com }",
            configPath: dirname(__DIR__, 3) . '/examples/node.example.yaml',
        );

        self::assertSame('demo', $node->name);
        self::assertSame('example.com', $node->server);
    }

    public function testItThrowsWhenNoSourceIsProvided(): void
    {
        $resolver = new NodeInputResolver();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provide either --node or --config.');

        $resolver->resolve(null, null);
    }
}
