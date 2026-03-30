<?php declare(strict_types=1);

namespace PhpTuic\Tests\Unit\Config;

use PhpTuic\Config\NodeLoader;
use PHPUnit\Framework\TestCase;

final class NodeLoaderTest extends TestCase
{
    public function testItLoadsNodeFromExampleFile(): void
    {
        $node = NodeLoader::fromFile(dirname(__DIR__, 3) . '/examples/node.example.yaml');

        self::assertSame('demo-tuic', $node->name);
        self::assertSame('example.com', $node->server);
        self::assertSame(443, $node->port);
        self::assertSame(['h3'], $node->alpn);
    }

    public function testItLoadsNodeFromInlineYaml(): void
    {
        $node = NodeLoader::fromString("{ name: 'SG 01', type: tuic, server: example.com, port: 443, uuid: 11111111-1111-1111-1111-111111111111, password: secret, alpn: [h3], disable-sni: false, reduce-rtt: false, udp-relay-mode: native, congestion-controller: bbr, skip-cert-verify: true, sni: example.com }");

        self::assertSame('SG 01', $node->name);
        self::assertTrue($node->skipCertVerify);
        self::assertSame('bbr', $node->congestionControl);
    }

    public function testItLoadsNodeFromInlineJson(): void
    {
        $json = json_encode([
            'name' => 'json-node',
            'type' => 'tuic',
            'server' => 'example.org',
            'port' => 8443,
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'password' => 'secret',
            'alpn' => ['h3'],
            'disable-sni' => false,
            'reduce-rtt' => false,
            'udp-relay-mode' => 'native',
            'congestion-controller' => 'bbr',
            'skip-cert-verify' => false,
            'sni' => 'example.org',
        ], JSON_THROW_ON_ERROR);

        $node = NodeLoader::fromString($json);

        self::assertSame('json-node', $node->name);
        self::assertSame('example.org', $node->server);
        self::assertFalse($node->skipCertVerify);
    }
}
